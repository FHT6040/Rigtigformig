# RFM AJAX Guidelines - VIGTIGT! 🚨

**ALLE AJAX handlers i dette projekt SKAL bruge `ajax-handler.php` - IKKE WordPress `admin-ajax.php`**

## Hvorfor?

✅ Undgår LiteSpeed Cache redirect problemer
✅ Hurtigere (bypasser WordPress routing overhead)
✅ Centraliseret AJAX handling
✅ Lettere at debugge
✅ Konsistent error handling

---

## 📋 Tjekliste for NY AJAX Funktionalitet

Når du tilføjer ny AJAX funktionalitet:

- [ ] **1. Tilføj case i `ajax-handler.php` switch statement (linje ~86-115)**
- [ ] **2. Opret `rfm_direct_*` funktion i `ajax-handler.php`**
- [ ] **3. Brug `ob_end_clean()` som første linje i funktionen**
- [ ] **4. Verificer nonce ALTID**
- [ ] **5. Tjek `is_user_logged_in()` hvis påkrævet**
- [ ] **6. Brug `wp_send_json_success()` eller `wp_send_json_error()`**
- [ ] **7. Afslut med `exit;`**
- [ ] **8. I JavaScript: brug `rfmData.ajaxurl` (IKKE admin-ajax.php)**
- [ ] **9. Test at det virker**
- [ ] **10. Clear LiteSpeed Cache efter deploy**

---

## ✅ KORREKT Måde - ajax-handler.php

### Step 1: Tilføj case i switch statement

Åbn `ajax-handler.php` og find switch statement (omkring linje 86):

```php
// Find switch statement:
switch ($action) {
    case 'rfm_update_user_profile':
        rfm_direct_update_user_profile();
        break;

    // ... eksisterende cases ...

    // TILFØJ DIN NYE CASE HER:
    case 'rfm_din_nye_action':
        rfm_direct_din_nye_action();
        break;

    default:
        ob_end_clean();
        wp_send_json_error(array('message' => 'Ugyldig handling: ' . $action), 400);
        exit;
}
```

### Step 2: Opret handler function

I samme fil (`ajax-handler.php`), tilføj funktionen efter de eksisterende functions:

```php
/**
 * Handle din nye funktionalitet
 *
 * @since 3.x.x
 */
function rfm_direct_din_nye_action() {
    // VIGTIGT: Clear output buffer først!
    ob_end_clean();

    // Verificer nonce ALTID
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_dashboard_tabbed')) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    // Check login hvis påkrævet
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du skal være logget ind.'), 401);
        exit;
    }

    $user_id = get_current_user_id();

    // Sanitize input
    $data = isset($_POST['data']) ? sanitize_text_field($_POST['data']) : '';

    if (empty($data)) {
        wp_send_json_error(array('message' => 'Ugyldig data.'));
        exit;
    }

    // Din logik her
    // ...

    // Debug logging (kun i development)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('RFM: Din action udført for user ' . $user_id);
    }

    // Success response
    wp_send_json_success(array(
        'message' => 'Success!'
    ));
    exit;
}
```

### Step 3: JavaScript Implementation

I din JavaScript fil (f.eks. `expert-dashboard.js` eller `user-dashboard.js`):

```javascript
// ✅ KORREKT - Brug rfmData.ajaxurl
$('#my-form').on('submit', function(e) {
    e.preventDefault();

    $.ajax({
        url: rfmData.ajaxurl,  // Dette peger på ajax-handler.php
        type: 'POST',
        data: {
            action: 'rfm_din_nye_action',
            nonce: rfmDashboard.nonce,  // eller rfmUserDashboard.nonce
            data: 'your data here'
        },
        success: function(response) {
            if (response.success) {
                console.log('Success:', response.data.message);
            } else {
                console.error('Error:', response.data.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', error);
        }
    });
});
```

---

## ❌ FORKERT Måde - UNDGÅ DETTE!

```php
// ❌ UNDGÅ DETTE (bruger WordPress admin-ajax.php system):
class My_Class {
    public function __construct() {
        add_action('wp_ajax_rfm_action', array($this, 'handler'));
        add_action('wp_ajax_nopriv_rfm_action', array($this, 'handler'));
    }

    public function handler() {
        // Dette går gennem admin-ajax.php - UNDGÅ!
    }
}
```

```javascript
// ❌ UNDGÅ DETTE:
$.ajax({
    url: ajaxurl,  // Dette er admin-ajax.php
    url: '/wp-admin/admin-ajax.php',  // UNDGÅ!
```

---

## 🔍 Eksisterende AJAX Handlers i ajax-handler.php

Se eksempler i `ajax-handler.php`:

1. **rfm_update_user_profile** (linje ~108-172)
   - User dashboard profil opdatering
   - Viser nonce verification, input sanitization

2. **rfm_upload_user_avatar** (linje ~179-257)
   - Avatar upload med file validation
   - Viser file handling, WordPress media functions

3. **rfm_save_general_profile** (linje ~302-375)
   - Ekspert generelle oplysninger + kategorier
   - Viser category limit enforcement

4. **rfm_save_category_profile** (linje ~382-493)
   - Kategori-specifik profil
   - Viser complex data sanitization, array handling

---

## 📂 Hvor finder jeg hvad?

### Frontend Scripts (JavaScript):
- **Ekspert Dashboard:** `assets/js/expert-dashboard.js`
- **User Dashboard:** `assets/js/user-dashboard.js`
- **Public/General:** `assets/js/public.js`

### Backend Handlers:
- **ALLE AJAX handlers:** `ajax-handler.php` ← HER SKAL DE VÆRE!
- ~~IKKE i PHP class files!~~

### AJAX URL Configuration:
- **Ekspert/Public scripts:** `public/class-rfm-public.php` (line 52)
- **User Dashboard:** `includes/class-rfm-user-dashboard.php` (line ~44)

Begge peger på: `RFM_PLUGIN_URL . 'ajax-handler.php'`

---

## 🐛 Debug Tips

### Check om din AJAX handler bliver kaldt:

I `ajax-handler.php`, tilføj debug logging:

```php
// I toppen af switch statement (linje ~80):
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('RFM Direct AJAX: Action = ' . $action);
    error_log('RFM Direct AJAX: POST data = ' . print_r($_POST, true));
}
```

### Check browser console:

```javascript
console.log('AJAX URL:', rfmData.ajaxurl);
console.log('Sending action:', 'rfm_din_action');
```

### Tjek WordPress debug.log:

```bash
tail -f /path/to/wp-content/debug.log
```

---

## 🚀 After Deploy Checklist

Efter du har deployed ny AJAX funktionalitet:

1. **Clear LiteSpeed Cache:**
   - WordPress Admin → LiteSpeed Cache → Toolbox → Purge All

2. **Test i browser:**
   - Hard refresh: Ctrl+Shift+R (Windows) / Cmd+Shift+R (Mac)
   - Åbn DevTools Console (F12)
   - Test funktionaliteten
   - Tjek for JavaScript errors

3. **Verify i debug.log:**
   - Check at din action bliver logget
   - Check for PHP errors

---

## 📞 Nonce Verification Reference

### Forskellige nonce keys i projektet:

```php
// Ekspert Dashboard (tabbed)
wp_verify_nonce($nonce, 'rfm_dashboard_tabbed')

// Ekspert Dashboard (logout)
wp_verify_nonce($nonce, 'rfm_dashboard_logout')

// User Dashboard
wp_verify_nonce($nonce, 'rfm_user_dashboard')

// General/Public
wp_verify_nonce($nonce, 'rfm_nonce')
```

Match altid med den nonce der sendes fra JavaScript!

---

## 📚 Relaterede Filer

- `AJAX-FIX-SUMMARY.md` - Historik om hvorfor vi migrerede til ajax-handler.php
- `CACHE-FIX-INSTRUCTIONS.md` - LiteSpeed Cache setup
- `ajax-handler.php` - ALLE AJAX handlers (~520 linjer)

---

## ⚠️ VIGTIG REGEL

**Hvis du ser `add_action('wp_ajax_*')` i PHP kode, SKAL det flyttes til `ajax-handler.php`!**

Dette er ikke en anbefaling - det er **obligatorisk** for alle RFM AJAX handlers.

---

*Sidst opdateret: v3.8.2*
*For spørgsmål: Se ajax-handler.php for eksempler*
