# Rigtig for Mig - Quick Start for Claude Code

**Plugin:** Rigtig for Mig (Danish wellness marketplace)  
**Current Version:** 3.1.4  
**Database Prefix:** `wp_rigtig` ⚠️ (NOT wp_)  
**Language:** Danish

---

## ⚡ INSTANT CONTEXT

### What Is This?
WordPress plugin that creates a marketplace for wellness experts (therapists, coaches, etc.) with:
- 4 color-coded categories (Brain, Body, Food, Soul)
- 3 subscription tiers (Free, Standard 219 DKK, Premium 399 DKK)
- Multi-category expert profiles with tabs
- Frontend dashboards (NO admin access needed)
- AJAX-powered everything

### Critical Files You'll Edit Most
```
rigtig-for-mig-plugin/
├── includes/class-rfm-public.php          ← Frontend AJAX handlers
├── public/js/public.js                    ← Frontend JavaScript  
├── includes/class-rfm-frontend-registration.php  ← Dashboards
├── includes/class-rfm-flexible-fields.php ← Field system
└── includes/class-rfm-expert-profile.php  ← Expert functionality
```

---

## 🚀 START HERE (3 STEPS)

### 1. Download Plugin
```bash
# From uploads folder
cd /mnt/user-data/uploads/
unzip rigtig-for-mig-v3.1.4.zip -d /home/claude/
cd /home/claude/rigtig-for-mig-plugin/
```

### 2. Check Version
```bash
grep "Version:" rigtig-for-mig.php
# Should show: Version: 3.1.4

grep "define('RFM_VERSION" rigtig-for-mig.php
# Should show: define('RFM_VERSION', '3.1.4');
```

### 3. Understand Structure
```bash
# View main file
cat rigtig-for-mig.php | head -50

# See all classes
ls includes/*.php

# See JavaScript
ls public/js/*.js
```

**You're ready to code!**

---

## 💡 MOST IMPORTANT THINGS TO KNOW

### 1. Cache is Your Enemy
90% of "it doesn't work" = cache issue!

**Solution:**
```javascript
// Always test in:
CTRL + SHIFT + N  // Private window
CTRL + F5         // Hard refresh

// Check if JS is loaded:
F12 → Console → Type: rfmData
// Should show: {ajaxurl: "...", nonce: "..."}
```

### 2. JavaScript Must Be Enqueued
v3.1.4 fixed this but ALWAYS verify:

```php
// In class-rfm-public.php
public function enqueue_scripts() {
    wp_enqueue_script('rfm-public', ..., RFM_VERSION, true);
    wp_localize_script('rfm-public', 'rfmData', array(...));
}
```

### 3. Database Prefix is NOT wp_
```php
// WRONG
$wpdb->users

// CORRECT  
$wpdb->prefix . 'users'  // Becomes: wp_rigtig_users
```

### 4. Everything is AJAX
```javascript
// All form submissions should be:
$('form').on('submit', function(e) {
    e.preventDefault();  // Stop page reload
    $.ajax({
        url: rfmData.ajaxurl,
        type: 'POST',
        data: {
            action: 'rfm_your_action',
            nonce: rfmData.nonce,
            // ... data
        }
    });
});
```

### 5. Multi-Category Data Storage
```php
// Category-specific data uses underscore:
rfm_bio_hjerne-psyke
rfm_bio_sjael-mening

// Universal data has no category:
rfm_phone
rfm_website
```

---

## 🎯 COMMON TASKS

### Add New AJAX Handler

**Step 1: Add to public.js**
```javascript
$('#my-button').on('click', function() {
    $.ajax({
        url: rfmData.ajaxurl,
        type: 'POST',
        data: {
            action: 'rfm_my_action',
            nonce: rfmData.nonce,
            value: $('#my-input').val()
        },
        success: function(response) {
            if (response.success) {
                alert(response.data.message);
            }
        }
    });
});
```

**Step 2: Add handler in class-rfm-public.php**
```php
public function ajax_my_action() {
    check_ajax_referer('rfm_nonce', 'nonce');
    
    $value = sanitize_text_field($_POST['value']);
    
    // Do something
    
    wp_send_json_success(array(
        'message' => 'Gemt!'
    ));
}

// Hook it
add_action('wp_ajax_rfm_my_action', array($this, 'ajax_my_action'));
```

### Add New Profile Field

**Step 1: Add to flexible fields (if using system)**
```php
// WordPress Admin → Rigtig for mig → Profil Felter
// Use UI to add field

// OR add to default fields in class-rfm-flexible-fields.php
'my_field' => array(
    'type' => 'text',
    'label' => 'Mit Felt',
    'subscription_required' => 'standard'
)
```

**Step 2: Render in dashboard**
```php
// In render_expert_dashboard() or render_user_dashboard()
$field_value = get_user_meta($user_id, 'rfm_my_field', true);
?>
<div class="rfm-form-group">
    <label><?php echo $field['label']; ?></label>
    <input type="text" name="my_field" value="<?php echo esc_attr($field_value); ?>">
</div>
```

**Step 3: Save in AJAX handler**
```php
// In handle_profile_update()
if (isset($_POST['my_field'])) {
    $value = sanitize_text_field($_POST['my_field']);
    update_user_meta($user_id, 'rfm_my_field', $value);
}
```

### Update Plugin Version

**Step 1: Update version numbers**
```php
// rigtig-for-mig.php line 16-17:
* Version: 3.1.5
define('RFM_VERSION', '3.1.5');
```

**Step 2: Create changelog**
```bash
touch CHANGELOG-3.1.5.md
```

```markdown
# CHANGELOG - Version 3.1.5
**Release Date:** [Date]

## New Features
- Added X feature

## Bug Fixes  
- Fixed Y issue

## Technical Changes
- Updated Z
```

**Step 3: Package**
```bash
cd /home/claude/
zip -r rigtig-for-mig-v3.1.5.zip rigtig-for-mig-plugin/ -x "*.git*" "*.DS_Store"
cp rigtig-for-mig-v3.1.5.zip /mnt/user-data/outputs/
```

---

## 🐛 DEBUG CHECKLIST

Problem? Follow these steps:

```bash
✓ Clear browser cache (CTRL+SHIFT+DELETE)
✓ Test in private window (CTRL+SHIFT+N)  
✓ Hard refresh (CTRL+F5)
✓ Check console (F12 → Console tab)
✓ Verify rfmData exists (type "rfmData" in console)
✓ Check Network tab for AJAX requests
✓ Purge LSCache if enabled
✓ Check PHP error log (wp-content/debug.log)
```

---

## 📝 CODE PATTERNS

### AJAX Success/Error
```php
// Success
wp_send_json_success(array(
    'message' => 'Det virkede!',
    'data' => $result
));

// Error
wp_send_json_error(array(
    'message' => 'Noget gik galt!',
    'code' => 'error_code'
));
```

### Get User Data
```php
$user_id = get_current_user_id();
$user = get_userdata($user_id);
$tier = get_user_meta($user_id, 'rfm_subscription_tier', true);
$category = 'hjerne-psyke';
$bio = get_user_meta($user_id, "rfm_bio_{$category}", true);
```

### Check Subscription Access
```php
function has_access($user_id, $required_tier) {
    $tiers = array('free' => 1, 'standard' => 2, 'premium' => 3);
    $current = get_user_meta($user_id, 'rfm_subscription_tier', true);
    return $tiers[$current] >= $tiers[$required_tier];
}
```

### Sanitize Input
```php
$text = sanitize_text_field($_POST['text']);
$email = sanitize_email($_POST['email']);
$textarea = sanitize_textarea_field($_POST['textarea']);
$url = esc_url_raw($_POST['url']);
$int = intval($_POST['number']);
```

---

## 🎓 KEY CONCEPTS

### Frontend-First Philosophy
- Users/experts NEVER access WordPress admin
- Everything works through custom dashboards
- AJAX for all interactions

### Subscription Tiers
```php
'free' => array(
    'specializations' => 3,
    'categories' => 1,
    'features' => 'basic'
),
'standard' => array(
    'specializations' => 10,
    'categories' => 2,
    'features' => 'enhanced'
),
'premium' => array(
    'specializations' => 'unlimited',
    'categories' => 'unlimited',
    'features' => 'all'
)
```

### Multi-Category Architecture
- Expert can have multiple categories
- Each category has own profile tab
- Category-specific: bio, experience, education, specializations
- Universal: phone, website, company name, password

---

## ⚠️ COMMON MISTAKES

### 1. Forgetting Nonce Verification
```php
// BAD
function my_ajax() {
    $value = $_POST['value'];
}

// GOOD
function my_ajax() {
    check_ajax_referer('rfm_nonce', 'nonce');
    $value = sanitize_text_field($_POST['value']);
}
```

### 2. Not Sanitizing Input
```php
// BAD
update_user_meta($id, 'field', $_POST['value']);

// GOOD
$value = sanitize_text_field($_POST['value']);
update_user_meta($id, 'field', $value);
```

### 3. Using Wrong Database Prefix
```php
// BAD
SELECT * FROM wp_users

// GOOD
global $wpdb;
$wpdb->get_results("SELECT * FROM {$wpdb->prefix}users");
```

### 4. Page Reloads on Form Submit
```javascript
// BAD
$('form').submit(function() {
    // No e.preventDefault()!
});

// GOOD
$('form').submit(function(e) {
    e.preventDefault();
    // AJAX here
});
```

### 5. Not Incrementing Version
```php
// Remember BOTH places:
* Version: 3.1.5              ← Header
define('RFM_VERSION', '3.1.5'); ← Constant
```

---

## 🔥 HOT FIXES

### JavaScript Not Loading?
```php
// Check class-rfm-public.php has:
public function enqueue_scripts() { ... }

// And constructor has:
add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
```

### AJAX Returning 0?
```php
// Verify action hook:
add_action('wp_ajax_rfm_your_action', array($this, 'your_method'));
// For logged-out users also add:
add_action('wp_ajax_nopriv_rfm_your_action', array($this, 'your_method'));
```

### Styles Not Applied?
```php
// Check cache cleared
// Verify CSS enqueued in enqueue_scripts()
// Check browser DevTools → Sources for CSS file
```

---

## 📚 NEXT STEPS

### For Small Changes
1. Edit the file
2. Save
3. Test (clear cache!)
4. If works → update version & create changelog

### For New Features
1. Read full context doc (RIGTIG-FOR-MIG-CLAUDE-CODE-CONTEXT.md)
2. Plan database changes
3. Write code with comments
4. Test thoroughly
5. Update version & changelog
6. Create user guide

### For Bug Fixes
1. Reproduce issue
2. Check console/logs
3. Fix root cause
4. Test fix
5. Increment version (patch number)
6. Add to changelog

---

## 💬 TALKING TO FRANK

### Always Include
1. **Version number** - "This is for v3.1.5"
2. **Cache warning** - "Remember to clear cache!"
3. **Testing steps** - "Test by doing X, Y, Z"
4. **What changed** - "Fixed X, added Y"

### Always Ask
1. "Should this be Free/Standard/Premium?"
2. "Which categories does this apply to?"
3. "Can you test this scenario?"

---

## ✨ FINAL TIPS

1. **When in doubt, check console** - F12 is your friend
2. **Cache is always the problem** - Until proven otherwise
3. **Test in private window** - Avoid cache issues
4. **Version numbers matter** - Update them!
5. **Danish language** - All user-facing text
6. **Frontend-first** - No admin access needed
7. **AJAX everything** - No page reloads
8. **Security first** - Nonce, sanitize, validate

---

**Ready to code? Check full context doc for deep details:**  
→ RIGTIG-FOR-MIG-CLAUDE-CODE-CONTEXT.md

**Questions? Common patterns are in the full doc!**

---

*Quick Start v1.0 - December 5, 2024*
