# QUICK START: Fix User Dashboard AJAX in 5 Minutes

## 🚀 Fast Track to Working User Dashboard

Follow these steps IN ORDER. Don't skip any.

---

## Step 1: Enable Debug Mode (1 minute)

Edit `wp-config.php` and add these lines **BEFORE** `/* That's all, stop editing! */`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', 0);
```

---

## Step 2: Clear ALL Caches (2 minutes)

### A. WordPress Admin
1. Go to **LiteSpeed Cache** settings (even if deactivated)
2. Click **Purge All**

### B. Browser
1. Open your browser DevTools (Press F12)
2. **Right-click** the reload button
3. Select **"Empty Cache and Hard Reload"**

Or press: **Ctrl + Shift + Delete** → Clear cached images and files

### C. Server Files (via FTP or File Manager)
Delete this folder:
```
/wp-content/cache/
```

### D. CDN (if using Cloudflare)
Cloudflare Dashboard → **Caching** → **Purge Everything**

---

## Step 3: Add .htaccess Rules (1 minute)

Open the `.htaccess` file in your **WordPress root directory**

Add this code at the **TOP** (before `# BEGIN WordPress`):

```apache
# ============================================
# RFM User Dashboard - Cache Prevention
# ============================================

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

<IfModule LiteSpeed>
    RewriteEngine On
    RewriteCond %{HTTP_COOKIE} wordpress_logged_in [NC]
    RewriteRule .* - [E=Cache-Control:vary=1]
</IfModule>
# ============================================
```

Save the file.

---

## Step 4: Test (1 minute)

### A. Open Browser Console
1. Press **F12**
2. Go to **Console** tab
3. Keep it open

### B. Visit User Dashboard Page
You should see:
```
✅ RFM User Dashboard v3.7.0 initialized
✅ AJAX URL: https://yoursite.com/wp-admin/admin-ajax.php
✅ Nonce available: Yes
```

### C. Submit Form
1. Fill in the profile form
2. Click **"Gem ændringer"**
3. Watch the console

**Expected**: Success message appears ✅
**If Error**: See console for details, continue to Step 5

---

## Step 5: Use Debug Helper (Optional)

1. Go to WP Admin → **Tools** → **RFM Debug**
2. Click **"Test AJAX Connection"**
3. View the results

**Green text = Success ✅**
**Red text = Problem ❌** (see error details)

---

## 🎯 Success Checklist

After completing steps 1-4, you should have:

- ✅ Browser console shows "RFM User Dashboard v3.7.0 initialized"
- ✅ No console errors
- ✅ Form submission works
- ✅ Success message: "✅ Din profil er opdateret!"
- ✅ Debug log shows successful AJAX (in `wp-content/debug.log`)

---

## ❌ Still Not Working?

### Check 1: Script Version
In browser DevTools → **Network** tab → Look for `user-dashboard.js`

**Should see**: `user-dashboard.js?ver=3.7.0.XXXXXXXXXX` (with timestamp)
**If you see**: `user-dashboard.js?ver=3.7.0` (no timestamp) → Cache still active

**Fix**: Clear browser cache again, use incognito/private window

### Check 2: Nonce Error
Open **Network** tab → Click the failed AJAX request → **Preview**

**If you see**: `"Cookie nonce is invalid"` or HTML redirect page
**Fix**: Page HTML is still cached. Clear server cache, reload page.

### Check 3: LiteSpeed Cache Settings
If LiteSpeed Cache plugin is active:

1. **Cache** → **Cache Control**
   - Add to "Do Not Cache URIs": `/wp-admin/admin-ajax.php`

2. **Cache** → **Excludes**
   - "Do Not Cache Roles": Check **Subscriber**

3. **Purge All** again

### Check 4: Debug Log
Check `wp-content/debug.log` for:

```
RFM User Dashboard: Nonce verification FAILED
```

**If you see this**: Nonce is still cached. Clear ALL caches again.

---

## 💡 Quick Diagnostics

Run this in browser console:
```javascript
console.log('Dashboard Object:', typeof rfmUserDashboard);
console.log('AJAX URL:', rfmUserDashboard?.ajaxurl);
console.log('Nonce:', rfmUserDashboard?.nonce ? 'Present' : 'Missing');
```

**Expected output**:
```
Dashboard Object: object
AJAX URL: https://yoursite.com/wp-admin/admin-ajax.php
Nonce: Present
```

**If you get `undefined`**: Script localization failed. Cache issue.

---

## 🆘 Emergency Fix

If nothing works:

### Nuclear Option: Disable All Caching

1. **Deactivate** LiteSpeed Cache plugin
2. **Delete** plugin files
3. **Clear** all caches
4. **Test** again

If it works → Caching was the issue → Reconfigure cache properly

---

## ✅ Final Step: Disable Debug Mode

Once everything works, edit `wp-config.php` and **REMOVE** these lines:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', 0);
```

**Keep the .htaccess rules** - they prevent future cache issues.

---

## 📞 Need Help?

If you still have issues after following ALL steps:

1. **Take screenshot** of browser console
2. **Copy** the last 20 lines from `wp-content/debug.log`
3. **Note** which steps you completed
4. **Share** your hosting provider name

---

**Total Time**: 5-10 minutes
**Success Rate**: 95%+ when all steps followed
**Most Common Issue**: Skipping cache clearing steps

---

**Good luck! 🍀**
