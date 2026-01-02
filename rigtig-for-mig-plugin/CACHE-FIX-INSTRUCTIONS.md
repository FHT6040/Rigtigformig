# LiteSpeed Cache Fix Instructions for v3.7.0

## Problem Summary
User Dashboard AJAX returns 302 redirect or "response.data is undefined" due to LiteSpeed Cache serving stale JavaScript and HTML with expired nonces.

## Root Cause
1. **Stale JavaScript**: LiteSpeed Cache is serving old minified JS (3470a946ec.min.js) instead of new v3.7.0 code
2. **Expired Nonces**: Cached HTML contains old nonces that fail verification, causing 302 redirects
3. **AJAX Caching**: LiteSpeed might be caching admin-ajax.php responses
4. **Server-level Cache**: Even after deactivating the plugin, server-level LiteSpeed Cache is still active

## Solution Applied (Code Changes)

### 1. Aggressive Cache-Busting (class-rfm-user-dashboard.php)
- Added file timestamp to script version: `RFM_VERSION . '.' . filemtime()`
- This forces browser to fetch new JS file on every code change

### 2. Fresh Nonce Generation (class-rfm-user-dashboard.php)
- Nonce now generated fresh on every page load in `wp_localize_script`
- Nonce included in localized data: `rfmUserDashboard.nonce`
- JavaScript uses this fresh nonce instead of cached form nonce

### 3. No-Cache Headers (class-rfm-user-dashboard.php)
- Added meta tags to prevent browser/proxy caching:
  - `Cache-Control: no-cache, no-store, must-revalidate`
  - `Pragma: no-cache`
  - `Expires: 0`
- Added `nocache_headers()` to AJAX handlers

### 4. Enhanced JavaScript Error Handling (user-dashboard.js)
- Dependency check: Verifies `rfmUserDashboard` exists before running
- Comprehensive error logging for all AJAX failures
- Detects 302 redirects and HTML responses
- Shows user-friendly error messages in Danish
- Debug mode support: Logs when WP_DEBUG is enabled

### 5. Comprehensive Server Logging (class-rfm-user-dashboard.php)
- Logs all AJAX requests when WP_DEBUG is enabled
- Tracks nonce verification failures
- Logs user authentication status
- Captures full request/response cycle

## Required Manual Steps

### Step 1: Enable WordPress Debug Mode (Temporarily)
Add to `wp-config.php` before "That's all, stop editing!":

```php
// Enable debugging to see RFM User Dashboard logs
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', 0);
```

### Step 2: Clear ALL Caches

#### A. WordPress Admin
1. Go to LiteSpeed Cache settings (even if plugin is deactivated)
2. Clear all caches: Purge All

#### B. Browser Cache
1. Open browser DevTools (F12)
2. Right-click Reload button → "Empty Cache and Hard Reload"
3. Or use Ctrl+Shift+Delete → Clear all cached images and files

#### C. Server-level Cache (CRITICAL)
If you have server access (SSH/FTP):

```bash
# Find and delete LiteSpeed cache directories
find /path/to/wordpress -type d -name "*cache*" -o -name "*boost*"
rm -rf /path/to/wordpress/wp-content/cache/*
```

Or via cPanel:
1. File Manager → wp-content/cache → Delete all
2. Check for: wp-content/litespeed/ → Delete if exists

#### D. CDN Cache (if using Cloudflare, etc.)
1. Cloudflare Dashboard → Caching → Purge Everything
2. Other CDN: Use their cache purge feature

### Step 3: Add .htaccess Rules (WordPress Root Directory)

Add this to the TOP of your WordPress .htaccess file (before WordPress rules):

```apache
# ============================================
# RFM User Dashboard - Cache Prevention
# ============================================

# Prevent caching of admin-ajax.php (ALL AJAX requests)
<Files "admin-ajax.php">
    <IfModule mod_headers.c>
        Header set Cache-Control "no-cache, no-store, must-revalidate, max-age=0"
        Header set Pragma "no-cache"
        Header set Expires "0"
    </IfModule>
    <IfModule LiteSpeed>
        CacheDisable public
    </IfModule>
</Files>

# Prevent caching of User Dashboard pages
<IfModule mod_rewrite.c>
    # If URL contains user dashboard shortcode or page
    RewriteCond %{REQUEST_URI} /user-dashboard [NC,OR]
    RewriteCond %{QUERY_STRING} page_id=.*user.*dashboard [NC]
    RewriteRule .* - [E=Cache-Control:no-cache]

    # Set no-cache headers for dashboard pages
    <IfModule mod_headers.c>
        Header set Cache-Control "no-cache, no-store, must-revalidate" env=Cache-Control
        Header set Pragma "no-cache" env=Cache-Control
        Header set Expires "0" env=Cache-Control
    </IfModule>
</IfModule>

# LiteSpeed specific: Disable cache for logged-in users
<IfModule LiteSpeed>
    # Don't cache pages for logged-in users
    RewriteEngine On
    RewriteCond %{HTTP_COOKIE} wordpress_logged_in [NC]
    RewriteRule .* - [E=Cache-Control:vary=1]
</IfModule>

# ============================================
# End RFM Cache Prevention
# ============================================
```

### Step 4: LiteSpeed Cache Plugin Settings (If Active)

If you choose to keep LiteSpeed Cache plugin active:

1. **Cache Settings**:
   - Cache → Cache Control
   - Add to "Do Not Cache URIs": `/wp-admin/admin-ajax.php`
   - Add to "Do Not Cache Query Strings": `action=rfm_update_user_profile`, `action=rfm_user_logout`

2. **Exclude Settings**:
   - Cache → Excludes
   - "Do Not Cache Roles": Check "Subscriber" (or whatever role uses user dashboard)
   - "Do Not Cache Cookies": Add `wordpress_logged_in_`

3. **Optimization Settings**:
   - Optimization → CSS Settings → CSS Minify: OFF (for testing)
   - Optimization → JS Settings → JS Minify: OFF (for testing)
   - Optimization → JS Settings → JS Combine: OFF (for testing)

4. **Purge Cache**: Purge All after making these changes

### Step 5: Test the Fix

1. **Open Browser Console** (F12 → Console tab)
2. **Navigate to User Dashboard page**
3. **Look for debug output**:
   ```
   RFM User Dashboard v3.7.0 initialized
   AJAX URL: https://yoursite.com/wp-admin/admin-ajax.php
   Nonce available: Yes
   ```

4. **Try updating profile**:
   - Fill in form
   - Click "Gem ændringer"
   - Watch console for detailed logs

5. **Check for errors**:
   - ❌ If you see: "rfmUserDashboard object is not defined" → Script localization failed, cache still active
   - ❌ If you see: "302 Redirect detected" → Nonce is expired, page HTML is cached
   - ✅ If you see: "Profile updated successfully" → It's working!

### Step 6: Check WordPress Debug Log

If still having issues, check the debug log:

```bash
# Location: wp-content/debug.log
tail -f wp-content/debug.log
```

Look for:
- `RFM User Dashboard: Profile update request received`
- `RFM User Dashboard: Nonce verification FAILED` (if nonce is the problem)
- `RFM User Dashboard: User not logged in` (if session expired)

## Expected Behavior After Fix

### Success Indicators:
1. ✅ Browser console shows "RFM User Dashboard v3.7.0 initialized"
2. ✅ No cached JS file (3470a946ec.min.js) - you see user-dashboard.js?ver=3.7.0.TIMESTAMP
3. ✅ Form submission works without errors
4. ✅ Success message appears: "✅ Din profil er opdateret!"
5. ✅ Debug log (if enabled) shows successful AJAX handling

### Failure Indicators:
1. ❌ Console error: "rfmUserDashboard object is not defined"
2. ❌ AJAX returns HTML instead of JSON
3. ❌ Status 302 in Network tab
4. ❌ Error: "response.data is undefined"
5. ❌ Old cached file still loading

## Permanent Solution

Once working, you can:

1. **Keep WP_DEBUG disabled** (remove from wp-config.php) for production
2. **Keep .htaccess rules** in place permanently
3. **Configure LiteSpeed Cache** to exclude logged-in users and AJAX
4. **Monitor** for future cache issues

## Alternative: Disable LiteSpeed Cache Completely

If issues persist:

1. Deactivate LiteSpeed Cache plugin in WordPress admin
2. Delete plugin files
3. Contact your hosting provider to disable server-level LiteSpeed Cache
4. Or switch to a different caching solution (W3 Total Cache, WP Super Cache, etc.)

## Support

If you continue to experience issues after following all steps:

1. Share the browser console output (screenshot)
2. Share the wp-content/debug.log relevant entries
3. Confirm which steps were completed
4. Check your hosting control panel for server-level cache settings

---

**Last Updated**: v3.7.0
**Date**: 2026-01-02
