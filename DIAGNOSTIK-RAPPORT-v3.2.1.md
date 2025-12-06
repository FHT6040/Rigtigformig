# 🔍 DIAGNOSTIK RAPPORT - Version 3.2.1
**Dato:** 6. december 2024
**Analyseret af:** Claude Code
**Nuværende version:** 3.2.1
**Rapporterede problemer:** Avatar upload fejler, Logout cache issue, Verificeringsstatus forkert

---

## 📋 RAPPORTEREDE PROBLEMER

### Problem 1: Avatar Upload Virker Ikke
**Symptom:**
- Bruger klikker "Upload profilbillede"
- "Gemmer..." knap vises (button disabled)
- Men intet sker - billedet gemmes ikke
- Ingen fejlbesked vises

### Problem 2: Logout Cache Issue
**Symptom:**
- Bruger logger ud og kommer til forsiden ✓
- Men ved retur til login-siden står der "Du er logget ind"
- Dashboard kan stadig tilgås
- Billedet er ikke gemt (fra Problem 1)

### Problem 3: Admin Panel Verificeringsstatus
**Symptom:**
- Bruger er verificeret (email confirmed)
- Men admin panel viser "Afventende Verificering"
- Samtidig vises brugeren ikke korrekt i admin panel

---

## 🔎 ROOT CAUSE ANALYSE

### 🔴 PROBLEM 1: Avatar Upload - DATABASE ROW MANGLER

**Årsag:**
```php
// class-rfm-user-dashboard.php:808-812
$wpdb->update(
    $table,
    array('profile_image' => $image_url),
    array('user_id' => $user_id)
);
```

**FEJL: `$wpdb->update()` virker KUN hvis rækken allerede eksisterer!**

**Scenarie:**
1. Bruger registreres via `class-rfm-user-registration.php:395-400`
2. Row INSERT sker med: `user_id`, `gdpr_consent`, `gdpr_consent_date`, `account_created_at`
3. **MEN:** `phone`, `bio`, `profile_image` er IKKE sat (NULL)
4. Hvis noget går galt under registrering, kan rækken mangle helt

**Resultat:**
- UPDATE finder ingen row → returnerer 0 rows affected
- Ingen fejl kastes (wpdb->update returnerer false/0 men ingen exception)
- JavaScript får SUCCESS response (fordi wp_send_json_success kaldes alligevel!)
- Men billedet er aldrig gemt

**Bevis:**
```php
// AJAX handler returnerer altid success:
wp_send_json_success(array(
    'message' => __('Profilbillede uploadet succesfuldt', 'rigtig-for-mig'),
    'avatar_url' => $image_url
));
```
Men `$wpdb->update()` resultat tjekkes ALDRIG! ❌

---

### 🟡 PROBLEM 2: Logout Cache - SAMME FEJL SOM v3.1.4

**Årsag:**
```php
// class-rfm-user-registration.php:516-535
public function handle_logout() {
    check_ajax_referer('rfm_nonce', 'nonce');

    // Destroy all sessions
    wp_destroy_current_session();
    wp_clear_auth_cookie();
    wp_set_current_user(0);

    // Clear all cookies
    if (isset($_COOKIE)) {
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'wordpress_') === 0 || strpos($name, 'wp_') === 0) {
                setcookie($name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            }
        }
    }

    // Logout
    wp_logout();

    // ❌ INGEN CACHE RENSNING!
    // ❌ INGEN NO-CACHE HEADERS!

    wp_send_json_success(array(
        'message' => __('Du er nu logget ud', 'rigtig-for-mig'),
        'redirect' => home_url()
    ));
}
```

**FEJL: Mangler cache-rensning præcis som dokumenteret i FEJLRAPPORT-v3.1.4.md!**

**Dette blev ALDRIG fixet fra v3.1.4 til v3.2.1!**

**Resultat:**
- Browser cache: Dashboard side stadig i cache
- Server cache (LiteSpeed): Login status cached
- WordPress object cache: User metadata cached
- Når bruger går tilbage til login-side: Cached version viser "Du er logget ind"

---

### 🟠 PROBLEM 3: Verificeringsstatus - USER META vs DISPLAY

**Årsag:**
```php
// class-rfm-user-admin.php:209
$verified = get_user_meta($user->ID, 'rfm_email_verified', true);
```

**Mulige årsager:**
1. **User meta `rfm_email_verified` er ikke sat korrekt**
   - Skal være `'1'` (string) eller `1` (int)
   - Kan være `'0'`, `0`, `false`, eller helt missing

2. **Email verification workflow virker ikke**
   - Email sendes, men verification link virker ikke
   - Eller user meta opdateres ikke efter klik

3. **Admin panel counting logic fejler**
   ```php
   // class-rfm-user-admin.php:129-133
   WHERE um.meta_key = 'rfm_email_verified'
   AND um.meta_value = '1'  // String '1' required!
   ```

**Debug Check Nødvendig:**
```sql
SELECT user_id, meta_key, meta_value
FROM wp_usermeta
WHERE user_id = [Frank HiT's ID]
AND meta_key = 'rfm_email_verified';
```

---

## 💡 HVAD JEG VILLE GØRE ANDERLEDES

### 🎯 Arkitektur Forbedringer fra v3.1.4 til Nu

#### **1. DATABASE OPERATIONS - UPSERT PATTERN**

**Problem i v3.1.4 og v3.2.1:**
```php
// Antager at row eksisterer
$wpdb->update($table, $data, array('user_id' => $user_id));
// Hvis row ikke eksisterer: SILENT FAILURE ❌
```

**Hvad jeg ville gøre:**
```php
/**
 * Safe update/insert for user profile
 * Checks if row exists, INSERT if not, UPDATE if exists
 */
private function upsert_user_profile_data($user_id, $data) {
    global $wpdb;
    $table = $wpdb->prefix . 'rfm_user_profiles';

    // Check if profile exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE user_id = %d",
        $user_id
    ));

    if ($exists) {
        // UPDATE existing row
        $result = $wpdb->update(
            $table,
            $data,
            array('user_id' => $user_id),
            array('%s', '%s', '%s'),  // format for data
            array('%d')               // format for where
        );

        if ($result === false) {
            error_log("RFM ERROR: Failed to UPDATE user profile for user $user_id");
            error_log("RFM ERROR: " . $wpdb->last_error);
            return false;
        }
    } else {
        // INSERT new row
        $data['user_id'] = $user_id;
        $data['account_created_at'] = current_time('mysql');

        $result = $wpdb->insert(
            $table,
            $data,
            array('%d', '%s', '%s', '%s')  // formats
        );

        if ($result === false) {
            error_log("RFM ERROR: Failed to INSERT user profile for user $user_id");
            error_log("RFM ERROR: " . $wpdb->last_error);
            return false;
        }
    }

    return true;
}
```

**Fordele:**
- ✅ Ingen silent failures
- ✅ Virker uanset om row eksisterer eller ej
- ✅ Proper error logging
- ✅ Returnerer success/failure for error handling

---

#### **2. AVATAR UPLOAD - PROPER ERROR HANDLING**

**Problem i v3.2.1:**
```php
$wpdb->update($table, array('profile_image' => $image_url), array('user_id' => $user_id));

// ❌ Ingen check om update lykkedes!
wp_send_json_success(array(
    'message' => __('Profilbillede uploadet succesfuldt', 'rigtig-for-mig'),
    'avatar_url' => $image_url
));
```

**Hvad jeg ville gøre:**
```php
public function handle_avatar_upload() {
    check_ajax_referer('rfm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('Du skal være logget ind', 'rigtig-for-mig')));
    }

    $user_id = get_current_user_id();

    if (empty($_FILES['avatar'])) {
        wp_send_json_error(array('message' => __('Ingen fil uploadet', 'rigtig-for-mig')));
    }

    // Validate file size (2MB max)
    if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
        wp_send_json_error(array('message' => __('Billedet må maksimalt være 2 MB', 'rigtig-for-mig')));
    }

    // Validate file type
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
    if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
        wp_send_json_error(array('message' => __('Kun JPG, PNG og GIF er tilladt', 'rigtig-for-mig')));
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $attachment_id = media_handle_upload('avatar', 0);

    if (is_wp_error($attachment_id)) {
        error_log('RFM ERROR: Avatar upload failed - ' . $attachment_id->get_error_message());
        wp_send_json_error(array('message' => $attachment_id->get_error_message()));
    }

    $image_url = wp_get_attachment_url($attachment_id);

    // ✅ USE UPSERT METHOD
    $success = $this->upsert_user_profile_data($user_id, array(
        'profile_image' => $image_url
    ));

    if (!$success) {
        // Cleanup uploaded file since we couldn't save reference
        wp_delete_attachment($attachment_id, true);

        wp_send_json_error(array(
            'message' => __('Kunne ikke gemme profilbillede i databasen', 'rigtig-for-mig')
        ));
    }

    // Clear caches
    wp_cache_delete($user_id, 'users');
    wp_cache_delete($user_id, 'user_meta');

    if (function_exists('litespeed_purge_all')) {
        litespeed_purge_all();
    }
    do_action('litespeed_purge_all');

    wp_send_json_success(array(
        'message' => __('Profilbillede uploadet succesfuldt', 'rigtig-for-mig'),
        'avatar_url' => $image_url
    ));
}
```

**Fordele:**
- ✅ File size validation før upload
- ✅ File type validation
- ✅ Database operation verification
- ✅ Cleanup hvis database save fejler
- ✅ Cache clearing
- ✅ Proper error messages til brugeren

---

#### **3. LOGOUT - KOMPLET CACHE RENSNING**

**Problem i v3.1.4 OG v3.2.1:**
```php
// Ingen cache rensning! ❌
wp_logout();
wp_send_json_success(...);
```

**Hvad jeg ville gøre (som dokumenteret i FEJLRAPPORT-v3.1.4.md):**
```php
public function handle_logout() {
    check_ajax_referer('rfm_nonce', 'nonce');

    $user_id = get_current_user_id();

    // Destroy all sessions
    wp_destroy_current_session();
    wp_clear_auth_cookie();
    wp_set_current_user(0);

    // Clear all cookies
    if (isset($_COOKIE)) {
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'wordpress_') === 0 || strpos($name, 'wp_') === 0) {
                setcookie($name, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            }
        }
    }

    // Logout
    wp_logout();

    // ✅ CLEAR ALL CACHES
    wp_cache_flush();

    // Clear user-specific caches
    if ($user_id) {
        wp_cache_delete($user_id, 'users');
        wp_cache_delete($user_id, 'user_meta');
    }

    // Clear LiteSpeed cache
    if (function_exists('litespeed_purge_all')) {
        litespeed_purge_all();
    }
    do_action('litespeed_purge_all');
    do_action('w3tc_flush_all');
    do_action('wp_cache_clear_cache');

    // ✅ SEND NO-CACHE HEADERS
    if (!headers_sent()) {
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }

    do_action('rfm_user_logged_out');

    wp_send_json_success(array(
        'message' => __('Du er nu logget ud', 'rigtig-for-mig'),
        'redirect' => home_url(),
        'clear_cache' => true  // ✅ Signal til JavaScript
    ));
}
```

**JavaScript side - FORCE RELOAD:**
```javascript
// I dashboard shortcode inline script:
$('#rfm-logout-btn').on('click', function(e) {
    e.preventDefault();

    var $button = $(this);
    $button.prop('disabled', true).text('Logger ud...');

    $.ajax({
        url: rfmData.ajaxurl,
        type: 'POST',
        data: {
            action: 'rfm_logout',
            nonce: rfmData.nonce
        },
        cache: false,  // ✅ Disable AJAX cache
        success: function(response) {
            if (response.data.clear_cache) {
                // ✅ FORCE full page reload without cache
                window.location.replace(response.data.redirect);

                // ✅ Additional cache clear attempt
                if ('caches' in window) {
                    caches.keys().then(function(names) {
                        for (let name of names) {
                            caches.delete(name);
                        }
                    });
                }
            } else {
                window.location.href = response.data.redirect;
            }
        },
        error: function(xhr, status, error) {
            console.error('Logout error:', error);
            // Force redirect anyway
            window.location.replace('<?php echo home_url(); ?>');
        }
    });
});
```

**Fordele:**
- ✅ Alle caches renses (browser, WordPress, LiteSpeed)
- ✅ No-cache headers sendes
- ✅ JavaScript bruger `window.location.replace()` (ikke cached)
- ✅ Service Worker caches renses også
- ✅ Virker selv hvis AJAX fejler

---

#### **4. EMAIL VERIFICATION - GARANTERET KONSISTENS**

**Problem:**
User meta `rfm_email_verified` kan være:
- Missing (aldrig sat)
- `0` eller `'0'` (string zero)
- `false`
- `1` eller `'1'` (success)

**Hvad jeg ville gøre:**
```php
/**
 * Set user verified status
 * Ensures consistent data type (string '1' or '0')
 */
public function set_user_verified($user_id, $verified = true) {
    // Store as string for MySQL consistency
    $value = $verified ? '1' : '0';

    update_user_meta($user_id, 'rfm_email_verified', $value);

    // Also store timestamp
    if ($verified) {
        update_user_meta($user_id, 'rfm_email_verified_at', current_time('mysql'));
    }

    error_log("RFM: User $user_id verification set to: $value");

    return true;
}

/**
 * Check if user is verified
 * Returns boolean, handles all edge cases
 */
public function is_user_verified($user_id) {
    $verified = get_user_meta($user_id, 'rfm_email_verified', true);

    // Handle all possible values
    return ($verified === '1' || $verified === 1 || $verified === true);
}

/**
 * Get verified users count
 * Consistent with storage format
 */
public function get_verified_users_count() {
    global $wpdb;

    return $wpdb->get_var("
        SELECT COUNT(DISTINCT u.ID)
        FROM {$wpdb->users} u
        INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
        WHERE um.meta_key = 'rfm_email_verified'
        AND um.meta_value IN ('1', 1)  -- Accept both string and int
        AND EXISTS (
            SELECT 1 FROM {$wpdb->usermeta} um2
            WHERE um2.user_id = u.ID
            AND um2.meta_key = 'wp_capabilities'
            AND um2.meta_value LIKE '%rfm_user%'
        )
    ");
}
```

**Fordele:**
- ✅ Konsistent datatype (altid string '1' eller '0')
- ✅ Helper methods for setting/checking
- ✅ Timestamp for når verificering skete
- ✅ Counting query accepterer både string og int
- ✅ Logging for debugging

---

## 🔧 ANBEFALET FIX PLAN

### Prioritet 1: KRITISKE FIXES (v3.2.2)

#### **Fix 1: Avatar Upload**
```php
// In class-rfm-user-dashboard.php

// Add helper method
private function ensure_user_profile_exists($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'rfm_user_profiles';

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE user_id = %d",
        $user_id
    ));

    if (!$exists) {
        $wpdb->insert($table, array(
            'user_id' => $user_id,
            'account_created_at' => current_time('mysql')
        ));
    }

    return true;
}

// Update handle_avatar_upload()
public function handle_avatar_upload() {
    // ... existing validation ...

    $image_url = wp_get_attachment_url($attachment_id);

    // ✅ ENSURE ROW EXISTS
    $this->ensure_user_profile_exists($user_id);

    // Now UPDATE will always work
    $result = $wpdb->update(
        $table,
        array('profile_image' => $image_url),
        array('user_id' => $user_id)
    );

    // ✅ CHECK RESULT
    if ($result === false) {
        wp_delete_attachment($attachment_id, true);
        wp_send_json_error(array('message' => __('Database fejl', 'rigtig-for-mig')));
    }

    // ... rest of code ...
}
```

#### **Fix 2: Logout Cache**
```php
// In class-rfm-user-registration.php

public function handle_logout() {
    // ... existing code ...

    wp_logout();

    // ✅ ADD CACHE CLEARING
    wp_cache_flush();

    if (function_exists('litespeed_purge_all')) {
        litespeed_purge_all();
    }
    do_action('litespeed_purge_all');
    do_action('w3tc_flush_all');

    if (!headers_sent()) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    wp_send_json_success(array(
        'message' => __('Du er nu logget ud', 'rigtig-for-mig'),
        'redirect' => home_url(),
        'clear_cache' => true
    ));
}
```

#### **Fix 3: Verification Status**
```sql
-- Run this SQL query to check Frank HiT's status:
SELECT u.ID, u.user_login, u.user_email, um.meta_value as verified
FROM wp_users u
LEFT JOIN wp_usermeta um ON u.ID = um.user_id AND um.meta_key = 'rfm_email_verified'
WHERE u.user_login = 'frank' OR u.user_email LIKE '%frank%';

-- If meta_value is NULL or '0', fix it:
UPDATE wp_usermeta
SET meta_value = '1'
WHERE user_id = [Frank's ID]
AND meta_key = 'rfm_email_verified';

-- If row doesn't exist, insert it:
INSERT INTO wp_usermeta (user_id, meta_key, meta_value)
VALUES ([Frank's ID], 'rfm_email_verified', '1');
```

---

### Prioritet 2: ARKITEKTUR FORBEDRINGER (v3.3.0)

1. **Implementer UPSERT pattern overalt**
   - Alle profile updates
   - Alle user meta operations
   - Konsistent error handling

2. **Centraliseret Cache Management**
   ```php
   class RFM_Cache_Manager {
       public static function clear_user_cache($user_id) {
           wp_cache_delete($user_id, 'users');
           wp_cache_delete($user_id, 'user_meta');
           // ... etc
       }

       public static function clear_all_cache() {
           wp_cache_flush();
           if (function_exists('litespeed_purge_all')) {
               litespeed_purge_all();
           }
           // ... etc
       }
   }
   ```

3. **Verification Helper Class**
   ```php
   class RFM_User_Verification {
       public static function set_verified($user_id, $verified = true);
       public static function is_verified($user_id);
       public static function get_verified_count();
   }
   ```

---

## 📊 SAMMENLIGNING: v3.1.4 vs v3.2.1

| Issue | v3.1.4 | v3.2.1 | Status |
|-------|--------|--------|--------|
| Duplicate event handlers | ❌ FEJL | ✅ FIXET | LØST i v3.2.0 |
| Logout cache clearing | ❌ FEJL | ❌ STADIG FEJL | ALDRIG FIXET |
| Avatar upload database check | ❌ FEJL | ❌ STADIG FEJL | ALDRIG FIXET |
| Verification status consistency | ❌ FEJL | ❌ STADIG FEJL | ALDRIG FIXET |

**Konklusion:**
v3.2.0/v3.2.1 fixede duplicate event handlers, men **ALLE andre v3.1.4 issues eksisterer stadig!**

---

## 🎯 HVAD ER ANDERLEDES DENNE GANG?

### Tidligere Approach (v3.1.4):
- ❌ Antog at database rows altid eksisterer
- ❌ Ingen error checking på database operations
- ❌ Ingen cache management
- ❌ Silent failures overalt

### Ny Approach (foreslået):
- ✅ Defensive programming - check alt
- ✅ UPSERT pattern for database safety
- ✅ Comprehensive cache management
- ✅ Explicit error handling og logging
- ✅ Fail-safe defaults
- ✅ Transaction-like thinking (cleanup on failure)

---

## 🔍 DEBUG STEPS TIL AT VERIFICERE ISSUES

### Step 1: Check Frank HiT's Database Records
```sql
-- Check if profile row exists
SELECT * FROM wp_rfm_user_profiles WHERE user_id = (
    SELECT ID FROM wp_users WHERE user_login = 'frank'
);

-- Check verification meta
SELECT * FROM wp_usermeta
WHERE user_id = (SELECT ID FROM wp_users WHERE user_login = 'frank')
AND meta_key LIKE '%verified%';
```

### Step 2: Check Browser Console
```javascript
// On avatar upload, check console for:
- "RFM DEBUG: Avatar upload started"
- "RFM DEBUG: Avatar upload response: ..."
- Any errors?
```

### Step 3: Check WordPress Debug Log
```bash
# Check wp-content/debug.log for:
tail -f wp-content/debug.log | grep "RFM"
```

### Step 4: Test Logout Flow
```
1. Log ind
2. Åbn Developer Tools → Network tab
3. Klik "Log ud"
4. Check Response Headers for "Cache-Control"
5. Check om cache headers er sat
```

---

## 📋 KONKLUSION

**HOVEDPROBLEMET:**
Version 3.2.1 fokuserede kun på at tilføje manglende handlers, men addresserede IKKE de fundamentale arkitektur problemer fra v3.1.4.

**ROOT CAUSES:**
1. Manglende database operation validation
2. Manglende cache management
3. Manglende error handling
4. Inkonsistent data storage (verification meta)

**LØSNING:**
Implementer de foreslåede fixes i prioriteret rækkefølge.

---

*Diagnostik completed: 6. december 2024*
*Claude Code - System Architecture Analyst* 🔍
