# Rigtig for Mig - Technical Context for Claude Code

**Last Updated:** December 5, 2024  
**Current Version:** 3.1.4  
**Platform:** WordPress Plugin  
**Language:** Danish  
**Database Prefix:** `wp_rigtig` (NOT standard `wp_`)

---

## 🎯 PROJECT OVERVIEW

### Purpose
"Rigtig for mig" (Right for me) is a Danish marketplace platform connecting users with therapeutic and wellness experts including therapists, coaches, mentors, and advisors.

### Business Model
- **For Users:** Free to browse and create accounts
- **For Experts:** Three-tier subscription model
  - Free: 0 DKK/month - Basic profile
  - Standard: 219 DKK/month - Enhanced features
  - Premium: 399 DKK/month - Full access

### Core Philosophy
**Frontend-First Design:** Experts and users should NEVER need WordPress admin access. All functionality must be accessible through custom frontend dashboards with AJAX-powered interactions.

---

## 📊 PLATFORM STRUCTURE

### Four Service Categories (Color-Coded)
Each category has its own visual identity:

1. **Hjerne & Psyke** (Brain & Psychology)
   - Color: Cyan (#00BCD4)
   - Experts: Psychologists, therapists, counselors

2. **Krop & Bevægelse** (Body & Movement)
   - Color: Green (#4CAF50)
   - Experts: Physical therapists, fitness coaches, yoga instructors

3. **Kost & Sundhed** (Food & Health)
   - Color: Orange (#FF9800)
   - Experts: Nutritionists, dietitians, health coaches

4. **Sjæl & Mening** (Soul & Meaning)
   - Color: Purple (#9C27B0)
   - Experts: Life coaches, spiritual advisors, mentors

### Multi-Category Support
Experts can belong to multiple categories simultaneously:
- Each category gets its own profile tab
- Category-specific content (bio, experience, specializations)
- Unified contact information across categories
- URL structure: `/ekspert/slug/?kategori=category-slug`

---

## 🗄️ DATABASE ARCHITECTURE

### Custom Prefix
**CRITICAL:** Database uses `wp_rigtig` prefix, NOT `wp_`

```sql
-- User tables
wp_rigtig_users
wp_rigtig_usermeta

-- Standard WordPress tables  
wp_rigtig_posts
wp_rigtig_postmeta
wp_rigtig_options
```

### Key User Meta Fields

#### Expert Meta
```php
// Profile basics
'rfm_expert_profile' => true/false
'rfm_subscription_tier' => 'free|standard|premium'
'rfm_expert_categories' => array('hjerne-psyke', 'sjael-mening')
'rfm_verified' => true/false
'rfm_verification_code' => string

// Category-specific data (per category)
'rfm_bio_{category_slug}' => text
'rfm_erfaring_{category_slug}' => text
'rfm_uddannelser_{category_slug}' => serialized array
'rfm_specialiseringer_{category_slug}' => array of IDs

// Universal contact
'rfm_phone' => string
'rfm_website' => string
'rfm_company_name' => string (Standard/Premium only)

// Activity tracking
'_rfm_last_active' => timestamp
```

#### User Meta
```php
'rfm_user_profile' => true/false
'rfm_verified' => true/false
'rfm_verification_code' => string
'_rfm_last_active' => timestamp
```

### Custom Taxonomies

#### Categories
```php
Taxonomy: 'rfm_category'
Hierarchical: Yes
Slugs: 
  - hjerne-psyke
  - krop-bevaegelse  
  - kost-sundhed
  - sjael-mening
```

#### Specializations  
```php
Taxonomy: 'rfm_specialisering'
Hierarchical: Yes (parent = category)
Attached to: Expert profiles
Max per expert: Free=3, Standard=10, Premium=unlimited
```

---

## 🏗️ PLUGIN ARCHITECTURE

### Directory Structure
```
rigtig-for-mig-plugin/
├── rigtig-for-mig.php          # Main plugin file
├── includes/                    # Core functionality
│   ├── class-rfm-activator.php
│   ├── class-rfm-deactivator.php
│   ├── class-rfm-loader.php
│   ├── class-rfm-expert-profile.php      # Expert profile management
│   ├── class-rfm-user-profile.php        # User profile management
│   ├── class-rfm-flexible-fields.php     # Dynamic field system
│   ├── class-rfm-bulk-import.php         # CSV imports
│   ├── class-rfm-online-status.php       # Activity tracking
│   ├── class-rfm-frontend-registration.php  # Auth & dashboards
│   └── class-rfm-shortcodes.php          # Shortcode handlers
├── admin/                       # WordPress admin
│   ├── class-rfm-admin.php
│   ├── css/
│   └── js/
├── public/                      # Frontend
│   ├── class-rfm-public.php
│   ├── css/
│   └── js/
└── assets/
    ├── css/
    └── js/
        ├── public.js            # Frontend interactions
        ├── fields-admin.js      # Field management UI
        └── bulk-import.js       # CSV upload handling
```

### Key Classes & Responsibilities

#### RFM_Frontend_Registration
**Purpose:** Authentication, dashboards, user/expert management  
**Key Methods:**
- `register_user()` / `register_expert()` - Registration with email verification
- `login_user()` - Unified login (username or email + password)
- `render_expert_dashboard()` - Expert profile editor
- `render_user_dashboard()` - User profile manager
- `send_verification_email()` - GDPR-compliant verification

#### RFM_Expert_Profile
**Purpose:** Expert-specific functionality  
**Key Methods:**
- `get_expert_data()` - Retrieve all expert data
- `update_expert_profile()` - AJAX profile updates
- `handle_category_switch()` - Multi-category management
- `render_expert_card()` - Profile card for listings
- `render_expert_profile_page()` - Full profile view with tabs

#### RFM_Flexible_Fields
**Purpose:** Dynamic field management system  
**Features:**
- Admin UI for adding/editing fields without code
- Subscription-based field visibility
- Repeater fields (education, certifications)
- Field groups and subfields
- AJAX-powered CRUD operations

**Key Methods:**
- `get_field_groups()` - Retrieve field configuration
- `render_field()` - Generate HTML for any field type
- `save_field_data()` - Handle field submissions
- `check_subscription_access()` - Verify user tier

#### RFM_Online_Status
**Purpose:** Track user activity  
**Mechanism:**
- JavaScript heartbeat every 5 minutes
- Updates `_rfm_last_active` timestamp
- Admin panel shows green (online) / red (offline) indicators
- Tracks both experts AND regular users

#### RFM_Public
**Purpose:** Frontend assets and AJAX handlers  
**Critical Fix (v3.1.4):** Added `enqueue_scripts()` method  
**Key Methods:**
- `enqueue_scripts()` - Load CSS, JS, and localize data
- `handle_profile_update()` - AJAX profile saves
- `handle_avatar_upload()` - Image uploads
- `handle_password_change()` - Password updates
- `handle_gdpr_download()` - Data export

---

## ⚡ CRITICAL JAVASCRIPT CONFIGURATION

### The Problem (Pre v3.1.4)
JavaScript files existed but were never enqueued to the browser, causing ALL frontend interactions to fail silently.

### The Solution (v3.1.4+)
```php
// class-rfm-public.php
public function enqueue_scripts() {
    // 1. Enqueue CSS
    wp_enqueue_style(
        'rfm-public',
        plugin_dir_url(dirname(__FILE__)) . 'public/css/rfm-public.css',
        array(),
        RFM_VERSION
    );
    
    // 2. Enqueue JavaScript  
    wp_enqueue_script(
        'rfm-public',
        plugin_dir_url(dirname(__FILE__)) . 'public/js/public.js',
        array('jquery'),
        RFM_VERSION,
        true
    );
    
    // 3. Localize Script (inject PHP data into JS)
    wp_localize_script('rfm-public', 'rfmData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rfm_nonce'),
        'strings' => array(
            'updating' => 'Gemmer...',
            'success' => 'Gemt!',
            'error' => 'Fejl!'
        ),
        'user_id' => get_current_user_id(),
        'is_user_logged_in' => is_user_logged_in()
    ));
}
```

**Result:** Frontend JavaScript now has access to:
- `rfmData.ajaxurl` - AJAX endpoint
- `rfmData.nonce` - Security token
- `rfmData.strings` - Localized messages
- `rfmData.user_id` - Current user ID

---

## 🔧 FRONTEND JAVASCRIPT (public.js)

### Key Event Handlers

#### Profile Updates
```javascript
$('#rfm-user-profile-form').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: rfmData.ajaxurl,
        type: 'POST',
        data: {
            action: 'rfm_update_user_profile',
            nonce: rfmData.nonce,
            display_name: $('#display_name').val(),
            phone: $('#phone').val(),
            bio: $('#bio').val()
        },
        success: function(response) {
            if (response.success) {
                showSuccessMessage(response.data.message);
            }
        }
    });
});
```

#### Avatar Upload
```javascript
$('#user_avatar_upload').on('change', function(e) {
    var formData = new FormData();
    formData.append('action', 'rfm_upload_avatar');
    formData.append('nonce', rfmData.nonce);
    formData.append('avatar', e.target.files[0]);
    
    $.ajax({
        url: rfmData.ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                $('#user-avatar-img').attr('src', response.data.avatar_url);
            }
        }
    });
});
```

#### Online Status Heartbeat
```javascript
// Ping server every 5 minutes
setInterval(function() {
    if (rfmData.is_user_logged_in) {
        $.ajax({
            url: rfmData.ajaxurl,
            type: 'POST',
            data: {
                action: 'rfm_heartbeat',
                nonce: rfmData.nonce
            }
        });
    }
}, 300000); // 300000ms = 5 minutes
```

---

## 🎨 FRONTEND DASHBOARDS

### Expert Dashboard (`/ekspert-dashboard`)
**Shortcode:** `[rfm_expert_dashboard]`

#### Sections:
1. **Profilbillede** - Avatar upload with preview
2. **Basis Information** - Name, email (read-only), phone, website
3. **Kategorier** - Multi-category selector with tabs
4. **Per-Category Content:**
   - Om mig (bio)
   - Erfaring (experience)
   - Uddannelser (education) - Repeater field
   - Specialiseringer (specializations) - Taxonomy checkboxes
   - Sprog (languages) - Repeater field
5. **Indstillinger**
   - Password change
   - GDPR data download
   - Delete account
6. **Log ud** button

### User Dashboard (`/bruger-dashboard`)
**Shortcode:** `[rfm_user_dashboard]`

#### Sections:
1. **Profilbillede** - Avatar upload
2. **Basis Information** - Name, email (read-only), phone
3. **Om mig** - Bio textarea
4. **Indstillinger**
   - Password change
   - GDPR data download
   - Delete account
5. **Log ud** button

### Common Features:
- ✅ Real-time AJAX updates (no page reload)
- ✅ Success/error message display
- ✅ Client-side validation
- ✅ Subscription tier enforcement
- ✅ Locked fields show upgrade prompts

---

## 🔐 AUTHENTICATION SYSTEM

### Registration Flow

#### Expert Registration
```php
1. User submits form with:
   - Username
   - Email
   - Password
   - Initial category selection
   - Subscription tier (defaults to 'free')

2. System validates:
   - Username/email not taken
   - Password strength (min 8 chars)
   - Valid email format

3. Creates WordPress user:
   - Role: 'rfm_expert_user'
   - Status: Unverified initially

4. Generates verification code:
   - Random 32-char string
   - Stored in user meta

5. Sends verification email:
   - Contains verification link
   - Expires after 24 hours

6. User clicks link → Account verified → Can login
```

#### User Registration
Same flow but with role: `'rfm_user'`

### Login System
**Unified Login:** Accepts either username OR email + password

```php
// Login function
public function login_user($credentials) {
    // 1. Determine if input is email or username
    $user_login = $credentials['username'];
    
    if (is_email($user_login)) {
        $user = get_user_by('email', $user_login);
        if ($user) {
            $user_login = $user->user_login;
        }
    }
    
    // 2. Authenticate
    $user = wp_signon(array(
        'user_login' => $user_login,
        'user_password' => $credentials['password'],
        'remember' => true
    ));
    
    // 3. Check verification
    if (!get_user_meta($user->ID, 'rfm_verified', true)) {
        wp_logout();
        return new WP_Error('not_verified', 'Du skal verificere din email først');
    }
    
    // 4. Update last active
    update_user_meta($user->ID, '_rfm_last_active', current_time('timestamp'));
    
    return $user;
}
```

### Password Reset
**Shortcode:** `[rfm_reset_password]`

1. User enters email
2. System generates reset token
3. Email sent with reset link
4. User sets new password
5. Token invalidated

---

## 📝 FLEXIBLE FIELDS SYSTEM

### Purpose
Allow admins to add/modify profile fields without uploading code. Fields can be locked behind subscription tiers.

### Field Types Supported
```php
'text'       => 'Tekst (kort)',
'textarea'   => 'Tekst (lang)',
'email'      => 'Email',
'tel'        => 'Telefon',
'url'        => 'URL/Link',
'number'     => 'Tal',
'date'       => 'Dato',
'select'     => 'Dropdown',
'checkbox'   => 'Checkboks',
'image'      => 'Billede upload',
'repeater'   => 'Gentaget gruppe (uddannelser, certificeringer, osv.)'
```

### Field Configuration
```php
array(
    'field_id' => 'company_name',
    'label' => 'Firmanavn',
    'type' => 'text',
    'required' => false,
    'subscription_required' => 'standard',  // Lock behind tier
    'description' => 'Vises på dit profilkort',
    'placeholder' => 'Indtast firmanavn',
    'max_length' => 100
)
```

### Repeater Fields Example (Education)
```php
array(
    'field_id' => 'uddannelser',
    'type' => 'repeater',
    'label' => 'Uddannelser',
    'max_items' => 'free:3|standard:10|premium:unlimited',
    'sub_fields' => array(
        'navn' => array(
            'type' => 'text',
            'label' => 'Uddannelsesnavn',
            'required' => true
        ),
        'institution' => array(
            'type' => 'text',
            'label' => 'Institution',
            'required' => true
        ),
        'aar' => array(
            'type' => 'text',
            'label' => 'År',
            'required' => false
        ),
        'beskrivelse' => array(
            'type' => 'textarea',
            'label' => 'Beskrivelse'
        ),
        'diplom_billede' => array(
            'type' => 'image',
            'label' => 'Diplom/Certifikat',
            'subscription_required' => 'standard'
        )
    )
)
```

### Admin UI
**Location:** WordPress Admin → Rigtig for mig → Profil Felter

**Features:**
- Visual field builder
- Drag-and-drop ordering (planned)
- Modal-based editing
- Preview of field rendering
- Subscription tier dropdown
- Repeater sub-field builder

---

## 📂 CSV BULK IMPORT SYSTEM

### Purpose
Efficiently import large datasets (categories, specializations, experts) via CSV files.

### Supported Import Types

#### 1. Categories
```csv
navn,beskrivelse,ikon,farve,slug
"Hjerne & Psyke","Terapi og psykologi","🧠","#00BCD4","hjerne-psyke"
```

#### 2. Specializations
```csv
navn,beskrivelse,forældre,kategori_slug
"Angstbehandling","Behandling af angst","","hjerne-psyke"
"Panikangst","Specifik angstbehandling","Angstbehandling","hjerne-psyke"
```

**Important:** Parent relationships use the "forældre" column with exact name matching.

#### 3. Experts (Future)
Not yet implemented but structure planned.

### Import Process
```javascript
// 1. Upload CSV
$('#rfm-csv-file').change(function() {
    var formData = new FormData();
    formData.append('action', 'rfm_preview_import');
    formData.append('file', this.files[0]);
    formData.append('type', 'specializations');
    
    $.ajax({
        url: ajaxurl,
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            // Show preview table
        }
    });
});

// 2. Confirm import
$('#confirm-import-btn').click(function() {
    $.ajax({
        url: ajaxurl,
        data: {
            action: 'rfm_execute_import',
            data: previewData,
            type: 'specializations'
        },
        success: function(response) {
            // Show results
        }
    });
});
```

### Error Handling
- Duplicate detection
- Parent validation
- CSV format validation
- Progress tracking for large imports
- Rollback on failure

---

## 🐛 COMMON ISSUES & SOLUTIONS

### Issue 1: JavaScript Not Working
**Symptoms:** Forms don't submit, buttons don't respond, AJAX fails

**Causes:**
1. Browser cache (90% of issues!)
2. JavaScript not enqueued
3. `rfmData` undefined
4. LSCache holding old version

**Solutions:**
```bash
# 1. ALWAYS clear cache first
CTRL + SHIFT + DELETE → Clear all

# 2. Test in private window
CTRL + SHIFT + N (Chrome)

# 3. Hard refresh
CTRL + F5

# 4. Check console for errors
F12 → Console → Look for red errors

# 5. Verify rfmData exists
F12 → Console → Type: rfmData
Should show: {ajaxurl: "...", nonce: "...", ...}

# 6. If using LSCache, purge from WordPress admin
LSCache → Toolbox → Purge All
```

### Issue 2: Online Status Not Updating
**Symptoms:** Users always show offline (red dot)

**Cause:** User role not tracked by `RFM_Online_Status`

**Solution (Fixed in v3.1.4):**
```php
// Before: Only tracked rfm_expert_user
if (!in_array('rfm_expert_user', $user->roles)) {
    return;
}

// After: Track both roles
if (!in_array('rfm_expert_user', $user->roles) && 
    !in_array('rfm_user', $user->roles)) {
    return;
}
```

### Issue 3: Profile Updates Not Saving
**Symptoms:** Form submits but data doesn't persist

**Debugging Steps:**
```javascript
// 1. Check AJAX request is sent
F12 → Network → Filter: XHR
Look for: rfm_update_user_profile

// 2. Check request payload
Click request → Payload tab
Verify: action, nonce, and form data present

// 3. Check response
Response tab → Should see:
{success: true, data: {message: "..."}}

// 4. If response shows error:
Check: Nonce validation, user permissions, data sanitization
```

### Issue 4: Images Not Uploading
**Causes:**
- Max file size exceeded (2MB limit)
- Wrong MIME type
- Upload permissions issue

**Solution:**
```php
// Verify file upload settings
ini_set('upload_max_filesize', '2M');
ini_set('post_max_size', '2M');

// Check MIME type
$allowed_types = array('image/jpeg', 'image/png', 'image/gif');
$file_type = $file['type'];
if (!in_array($file_type, $allowed_types)) {
    return new WP_Error('invalid_type', 'Kun JPG, PNG eller GIF filer');
}
```

### Issue 5: LSCache Conflicts
**Symptoms:** Changes made but not visible on frontend

**Solution:**
```php
// Add to functions that update user data
do_action('litespeed_purge_all');

// Or from admin:
LSCache → Toolbox → Purge All → Purge All Public Pages
```

---

## 📋 VERSION HISTORY & CHANGELOG

### v3.1.4 (December 4, 2024) - CRITICAL FIX
**Problem:** JavaScript not loading, profiles not updating

**Fixes:**
- ✅ Added `enqueue_scripts()` to `RFM_Public`
- ✅ Proper script localization with `rfmData`
- ✅ Online status now tracks regular users
- ✅ Removed duplicate script enqueuing
- ✅ Fixed logout action (was 'rfm_expert_logout', now 'rfm_logout')

**Impact:** ALL frontend AJAX functionality now works

### v3.1.3 (December 4, 2024) - HOTFIX
**Fixes:**
- Added missing JavaScript handlers for user dashboard
- Fixed avatar upload response field (image_url → avatar_url)
- Added GDPR data download handler
- Fixed password change validation

### v3.1.2 (December 3, 2024)
**Features:**
- Multi-category profile support with tabs
- Category-specific content management
- URL-based category switching
- Online status admin panel display

### v3.1.1 (December 2, 2024)
**Fixes:**
- Cleaned duplicate profile information
- Improved dashboard tab structure
- Synchronized admin field settings with frontend

### v3.1.0 (December 1, 2024)
**Features:**
- Complete user system (alongside existing expert system)
- Unified authentication (username OR email login)
- User dashboard with GDPR compliance
- Review system with spam prevention
- Email verification for both user types

### v3.0.0 (November 27, 2024)
**Major Release:**
- Flexible fields system
- Admin UI for field management
- Subscription-based field locking
- Repeater fields
- CSV bulk import with preview

### v2.8.8 (November 26, 2024)
**Features:**
- Diplom/certificate uploads for paid members
- Image field type in flexible fields
- Subscription requirement on sub-fields

### v2.8.0 (November 21, 2024)
**Features:**
- Improved CSV import with parent relationships
- Better error handling and validation
- Progress tracking

### v2.7.0 (November 17, 2024)
**Features:**
- Modal-based field editing UI
- AJAX field CRUD operations
- Admin dashboard for field management

---

## 🎯 DEVELOPMENT PATTERNS & BEST PRACTICES

### 1. Always Use AJAX for Frontend Interactions
```javascript
// GOOD: No page reload
$.ajax({
    url: rfmData.ajaxurl,
    type: 'POST',
    data: {
        action: 'rfm_custom_action',
        nonce: rfmData.nonce,
        // ... more data
    },
    success: function(response) {
        // Update UI
    }
});

// BAD: Form submission that reloads page
$('form').submit(); // Avoid this!
```

### 2. Always Verify Nonces
```php
// In AJAX handler
check_ajax_referer('rfm_nonce', 'nonce');
```

### 3. Sanitize ALL User Input
```php
$name = sanitize_text_field($_POST['name']);
$email = sanitize_email($_POST['email']);
$bio = sanitize_textarea_field($_POST['bio']);
$url = esc_url_raw($_POST['url']);
```

### 4. Use Transients for Expensive Queries
```php
// Cache for 1 hour
$data = get_transient('rfm_expert_list');
if (false === $data) {
    $data = expensive_query();
    set_transient('rfm_expert_list', $data, HOUR_IN_SECONDS);
}
```

### 5. Maintain Backward Compatibility
```php
// When adding new fields, provide defaults
$company_name = get_user_meta($user_id, 'rfm_company_name', true);
if (empty($company_name)) {
    $company_name = get_user_meta($user_id, 'display_name', true);
}
```

### 6. Test with Cache Disabled
During development, always test with:
- Browser cache disabled
- LSCache disabled
- Private browsing mode

### 7. Log Errors for Debugging
```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('RFM Debug: ' . print_r($data, true));
}
```

### 8. Use WP_Query Best Practices
```php
// GOOD: Specific fields
$query = new WP_Query(array(
    'post_type' => 'expert',
    'fields' => 'ids', // Only get IDs
    'no_found_rows' => true, // Skip pagination count
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false
));

// BAD: Default query (gets everything)
$query = new WP_Query(array(
    'post_type' => 'expert'
));
```

---

## 🚀 PLANNED FEATURES & ROADMAP

### Short Term (Next 1-2 versions)

#### Advanced Search
```php
// Search by:
- Location (requires location field addition)
- Category
- Specialization
- Subscription tier
- Online status
- Review rating (when reviews implemented)

// Implementation approach:
- Add location field to flexible fields
- Create RFM_Search class
- AJAX-powered search interface
- URL parameter support (?location=aarhus&kategori=hjerne-psyke)
```

#### Messaging System
```php
// User → Expert messaging
- Message storage in custom table
- Real-time notifications
- Email notifications
- Dashboard inbox
- Message threading
```

### Medium Term (3-6 months)

#### Payment Integration
```php
// Subscription management
- Stripe integration
- Auto-renewal
- Upgrade/downgrade flows
- Grace periods
- Payment history
```

#### Review System Enhancement
```php
// Already basic system exists
- Star ratings
- Review moderation
- Response from experts
- Review helpful voting
- Spam detection
```

#### Analytics Dashboard
```php
// For experts
- Profile views
- Contact clicks
- Search appearances
- Conversion tracking
```

### Long Term (6+ months)

#### Booking System
```php
// Appointment scheduling
- Calendar integration
- Time slot management
- Confirmation emails
- Cancellation handling
```

#### Multi-Language Support
```php
// Currently Danish only
- WPML integration
- Translation-ready strings
- Language switcher
```

---

## 🔧 TECHNICAL DEBT & KNOWN LIMITATIONS

### Current Limitations

1. **No Search Functionality**
   - Users can browse by category but can't search by location or keywords
   - Planned for next major version

2. **Basic Review System**
   - Exists but needs moderation features
   - No sorting/filtering

3. **No Payment Processing**
   - Subscription tiers exist in code only
   - Manual admin management required

4. **Limited Mobile Optimization**
   - Dashboards work but could be more touch-friendly
   - Some modals need mobile refinement

5. **No Automated Testing**
   - All testing is manual
   - Need unit tests for core functions

6. **Database Query Optimization**
   - Some queries could use indexes
   - User meta queries sometimes inefficient

### Technical Debt to Address

1. **Code Organization**
   - Some classes have grown too large
   - Need better separation of concerns
   - Template system needs refactoring

2. **Error Handling**
   - Inconsistent error handling patterns
   - Need centralized error logging
   - User-facing errors need better messages

3. **Documentation**
   - Inline comments need improvement
   - Function docblocks incomplete
   - Need developer API documentation

4. **Performance**
   - Dashboard loads could be faster
   - Image optimization needed
   - Lazy loading for expert lists

5. **Security Audits**
   - Need third-party security review
   - Rate limiting on AJAX endpoints
   - Better CSRF protection beyond nonces

---

## 📞 DEBUGGING CHECKLIST

When something doesn't work, follow this checklist:

### Step 1: Clear ALL Caches
```bash
□ Browser cache (CTRL+SHIFT+DELETE)
□ LSCache (if enabled)
□ WordPress object cache
□ Test in private/incognito window
□ Hard refresh (CTRL+F5) on every page
```

### Step 2: Check Console
```bash
□ Open DevTools (F12)
□ Go to Console tab
□ Look for red errors
□ Check if rfmData is defined
□ Verify AJAX requests in Network tab
```

### Step 3: Verify Plugin State
```bash
□ Plugin version matches expected
□ All files uploaded correctly
□ No file permission issues
□ PHP version meets requirements (7.4+)
```

### Step 4: Check User State
```bash
□ User is logged in
□ User has correct role
□ User is verified
□ Subscription tier is set
```

### Step 5: AJAX Debugging
```javascript
// Add this to public.js temporarily
jQuery(document).ajaxComplete(function(event, xhr, settings) {
    console.log('AJAX Complete:', settings.url, xhr.responseJSON);
});
```

### Step 6: PHP Error Logging
```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Check: wp-content/debug.log
```

---

## 🎓 KEY LEARNINGS & INSIGHTS

### 1. Frontend-First is Crucial
Never require users to access WordPress admin. Every feature must work through custom dashboards. This has driven the entire architecture.

### 2. Cache is the #1 Support Issue
90% of "it doesn't work" issues are cache-related. Always instruct users to:
- Clear browser cache
- Test in private window
- Purge LSCache

### 3. JavaScript Must Be Properly Enqueued
The v3.1.4 crisis taught us that JavaScript files can exist but won't run if not properly enqueued and localized. Always verify in browser console that `rfmData` is defined.

### 4. Flexible Fields Are Essential
Hardcoding profile fields doesn't scale. The flexible field system allows adding features without code uploads, which is critical for growth.

### 5. Multi-Category Support is Complex
Supporting experts in multiple categories requires careful data architecture:
- Per-category content storage
- Unified contact information
- Tab-based UI
- URL parameter handling
- Backward compatibility

### 6. AJAX is King for UX
Every form submission should use AJAX. Page reloads feel outdated and break user flow.

### 7. Subscription Tiers Need Backend Enforcement
Frontend hiding isn't enough. Always verify subscription tier on the backend before saving data.

### 8. Mobile-First Design Matters
Many users access dashboards on mobile. Test every feature on small screens.

### 9. Danish Language Throughout
All user-facing strings must be in Danish. This includes error messages, success notifications, and field labels.

### 10. Backward Compatibility is Sacred
With 50+ experts on the platform, every update must preserve existing data and functionality. Migration scripts are essential.

---

## 🔐 SECURITY CONSIDERATIONS

### Authentication
- ✅ Passwords hashed with bcrypt
- ✅ Email verification required
- ✅ Session timeout after inactivity
- ⚠️ Consider 2FA for admin accounts
- ⚠️ Rate limiting on login attempts

### AJAX Security
- ✅ Nonce verification on all requests
- ✅ Capability checks before actions
- ⚠️ Add rate limiting per user
- ⚠️ Log suspicious activity

### File Uploads
- ✅ MIME type validation
- ✅ File size limits
- ✅ Rename uploaded files
- ⚠️ Scan for malware
- ⚠️ Store outside web root

### Data Privacy (GDPR)
- ✅ Data download feature
- ✅ Account deletion
- ✅ Email verification
- ⚠️ Add data retention policies
- ⚠️ Cookie consent banner
- ⚠️ Privacy policy generator

### SQL Injection
- ✅ Using $wpdb->prepare()
- ✅ Sanitizing all inputs
- ⚠️ Audit all custom queries

### XSS Prevention
- ✅ Escaping all outputs
- ✅ Using wp_kses() for HTML
- ⚠️ Content Security Policy headers

---

## 📚 USEFUL CODE SNIPPETS

### Get Expert Data
```php
$expert_id = 123;
$category = 'hjerne-psyke';

// Basic info
$user = get_userdata($expert_id);
$tier = get_user_meta($expert_id, 'rfm_subscription_tier', true);

// Category-specific
$bio = get_user_meta($expert_id, "rfm_bio_{$category}", true);
$experience = get_user_meta($expert_id, "rfm_erfaring_{$category}", true);
$education = get_user_meta($expert_id, "rfm_uddannelser_{$category}", true);

// Contact (universal)
$phone = get_user_meta($expert_id, 'rfm_phone', true);
$website = get_user_meta($expert_id, 'rfm_website', true);
```

### Check Subscription Access
```php
function has_access($user_id, $required_tier) {
    $current_tier = get_user_meta($user_id, 'rfm_subscription_tier', true);
    
    $tiers = array('free' => 1, 'standard' => 2, 'premium' => 3);
    
    return $tiers[$current_tier] >= $tiers[$required_tier];
}
```

### Render Field with Subscription Check
```php
function render_locked_field($field, $user_id) {
    $has_access = has_access($user_id, $field['subscription_required']);
    
    if (!$has_access) {
        echo '<div class="rfm-locked-field">';
        echo '<i class="fas fa-lock"></i>';
        echo '<p>Opgrader til ' . ucfirst($field['subscription_required']) . ' for at låse op</p>';
        echo '</div>';
        return;
    }
    
    // Render actual field
    render_field($field);
}
```

### AJAX Response Template
```php
function ajax_handler() {
    // 1. Verify nonce
    check_ajax_referer('rfm_nonce', 'nonce');
    
    // 2. Check permissions
    if (!current_user_can('edit_user', $user_id)) {
        wp_send_json_error(array(
            'message' => 'Du har ikke tilladelse til denne handling'
        ));
        return;
    }
    
    // 3. Sanitize input
    $data = sanitize_text_field($_POST['data']);
    
    // 4. Perform action
    $result = update_user_meta($user_id, 'field_name', $data);
    
    // 5. Send response
    if ($result) {
        wp_send_json_success(array(
            'message' => 'Data gemt succesfuldt!'
        ));
    } else {
        wp_send_json_error(array(
            'message' => 'Kunne ikke gemme data'
        ));
    }
}
add_action('wp_ajax_rfm_custom_action', 'ajax_handler');
```

### Query Experts by Category
```php
function get_experts_by_category($category_slug, $args = array()) {
    $defaults = array(
        'role' => 'rfm_expert_user',
        'meta_query' => array(
            array(
                'key' => 'rfm_verified',
                'value' => '1',
                'compare' => '='
            ),
            array(
                'key' => 'rfm_expert_categories',
                'value' => $category_slug,
                'compare' => 'LIKE'
            )
        ),
        'orderby' => 'display_name',
        'order' => 'ASC'
    );
    
    $args = wp_parse_args($args, $defaults);
    
    return get_users($args);
}
```

---

## 🎯 DEVELOPMENT WORKFLOW

### For New Features

1. **Plan**
   - Write feature spec
   - Identify affected files
   - Plan database changes
   - Consider backward compatibility

2. **Develop**
   - Update version number
   - Add to CHANGELOG
   - Write code with comments
   - Test with cache disabled

3. **Test**
   - Test as free user
   - Test as standard user
   - Test as premium user
   - Test on mobile
   - Test with caching enabled

4. **Deploy**
   - Upload to WordPress
   - Activate plugin
   - Test live site
   - Monitor for errors
   - Clear LSCache

5. **Document**
   - Update this document
   - Create user guide if needed
   - Send changelog to Frank

### For Bug Fixes

1. **Reproduce**
   - Clear cache
   - Follow exact steps
   - Check console errors
   - Note browser/device

2. **Debug**
   - Add logging
   - Check AJAX requests
   - Verify data flow
   - Isolate cause

3. **Fix**
   - Update version number
   - Add to CHANGELOG
   - Fix root cause
   - Add preventive measures

4. **Verify**
   - Test fix works
   - Test didn't break other features
   - Test on multiple browsers

5. **Deploy**
   - Upload hotfix
   - Verify live
   - Update documentation

---

## 💬 COMMUNICATION WITH FRANK

### What Frank Needs to Know

1. **Version Numbers**
   - Always mention current version
   - Clearly state what's new

2. **Cache Instructions**
   - Always remind to clear cache
   - Provide exact steps
   - Emphasize testing in private window

3. **Testing Steps**
   - Provide clear checklist
   - Include screenshots
   - Note what to look for

4. **Limitations**
   - Be upfront about what's not implemented
   - Explain technical constraints
   - Suggest workarounds

5. **Next Steps**
   - Clear action items
   - Timeline expectations
   - Dependencies

### What Claude Code Should Ask Frank

1. **Before Major Changes**
   - "This will change X. Confirm?"
   - "This requires Y. Proceed?"

2. **When Unclear**
   - "Should this be Free/Standard/Premium?"
   - "Which categories should this apply to?"

3. **For Testing**
   - "Can you test scenario X?"
   - "Does Y work as expected?"

---

## 🏁 GETTING STARTED FOR CLAUDE CODE

### Initial Setup

1. **Clone/Download Plugin**
   ```bash
   # Plugin will be at /mnt/user-data/uploads/
   cd /mnt/user-data/uploads/
   unzip rigtig-for-mig-v3.1.4.zip -d /home/claude/
   cd /home/claude/rigtig-for-mig-plugin/
   ```

2. **Understand File Structure**
   ```bash
   # Key files to know
   rigtig-for-mig.php                 # Main file, version number
   includes/class-rfm-public.php      # Frontend AJAX, scripts
   public/js/public.js                # Frontend JavaScript
   includes/class-rfm-flexible-fields.php  # Field system
   includes/class-rfm-frontend-registration.php  # Dashboards
   ```

3. **Check Current Version**
   ```bash
   grep "Version:" rigtig-for-mig.php
   grep "RFM_VERSION" rigtig-for-mig.php
   ```

4. **Read Recent Changelog**
   ```bash
   # Look for CHANGELOG-X.X.X.md files
   ls -la | grep CHANGELOG
   cat CHANGELOG-3.1.4.md
   ```

### For Making Changes

1. **Increment Version**
   ```php
   // In rigtig-for-mig.php
   * Version: 3.1.5  // Update this
   define('RFM_VERSION', '3.1.5');  // And this
   ```

2. **Add to Changelog**
   ```bash
   # Create new file
   touch CHANGELOG-3.1.5.md
   # Document changes
   ```

3. **Test Your Changes**
   - Always clear cache between tests
   - Test in private browser window first
   - Check console for errors
   - Verify AJAX requests

4. **Package for Upload**
   ```bash
   cd /home/claude/
   zip -r rigtig-for-mig-v3.1.5.zip rigtig-for-mig-plugin/ -x "*.git*" "*.DS_Store"
   cp rigtig-for-mig-v3.1.5.zip /mnt/user-data/outputs/
   ```

5. **Create User Guide**
   ```bash
   # For significant changes
   touch /mnt/user-data/outputs/UPDATE-GUIDE-v3.1.5.md
   # Include:
   - What's new
   - How to test
   - Cache clearing instructions
   - Known issues
   ```

---

## 🎉 SUCCESS METRICS

### User Engagement
- Number of registered experts
- Number of registered users
- Profile completion rates
- Active users (logged in past 7 days)

### Platform Health
- Page load times
- AJAX response times
- Error rates
- Cache hit rates

### Business Metrics
- Conversion rate (free → paid)
- Churn rate
- Average time to conversion
- Revenue per expert

---

## 📞 SUPPORT & MAINTENANCE

### Common Support Issues

1. **"Changes don't show up"**
   → Cache issue, provide clearing instructions

2. **"Can't upload image"**
   → File size or type issue, check error log

3. **"Form doesn't save"**
   → JavaScript error, check console

4. **"Can't log in"**
   → Email not verified or wrong credentials

5. **"Missing features"**
   → Subscription tier limitation, explain upgrade

### Maintenance Schedule

**Daily:**
- Monitor error logs
- Check online status tracking

**Weekly:**
- Review new registrations
- Check for spam accounts
- Verify backup completion

**Monthly:**
- Database optimization
- Update WordPress core/plugins
- Security scan
- Performance review

**Quarterly:**
- User feedback review
- Feature priority assessment
- Technical debt evaluation
- Security audit

---

## 🎓 RESOURCES & REFERENCES

### WordPress Codex
- [Plugin API](https://codex.wordpress.org/Plugin_API)
- [AJAX in Plugins](https://codex.wordpress.org/AJAX_in_Plugins)
- [Roles and Capabilities](https://codex.wordpress.org/Roles_and_Capabilities)
- [Data Validation](https://developer.wordpress.org/plugins/security/data-validation/)

### Security
- [WordPress Security Whitepaper](https://wordpress.org/about/security/)
- [GDPR Compliance Guide](https://wordpress.org/about/privacy/)

### Performance
- [WordPress Optimization Guide](https://developer.wordpress.org/advanced-administration/performance/optimization/)
- [Database Optimization](https://developer.wordpress.org/advanced-administration/performance/optimization/#optimize-your-database)

### Testing
- [WP-CLI](https://wp-cli.org/)
- [WordPress Debugging](https://wordpress.org/documentation/article/debugging-in-wordpress/)

---

## 📝 FINAL NOTES FOR CLAUDE CODE

### Key Principles
1. **Frontend-first:** Never require admin access
2. **AJAX everything:** No page reloads
3. **Cache-aware:** Always consider caching
4. **Subscription-based:** Enforce tiers
5. **Danish language:** All user-facing text
6. **Mobile-friendly:** Test on small screens
7. **Backward compatible:** Never break existing data

### Before You Code
- ✅ Check latest version number
- ✅ Review recent changelog
- ✅ Understand affected features
- ✅ Plan database changes
- ✅ Consider cache impact

### While You Code
- ✅ Comment your changes
- ✅ Sanitize inputs
- ✅ Validate data
- ✅ Handle errors gracefully
- ✅ Use wp_send_json_success/error

### After You Code
- ✅ Test with cache disabled
- ✅ Test with cache enabled
- ✅ Test on mobile
- ✅ Check console for errors
- ✅ Update documentation

### When You're Done
- ✅ Update version numbers
- ✅ Create changelog
- ✅ Package plugin
- ✅ Write update guide
- ✅ Test one more time!

---

**Document Version:** 1.0  
**Last Updated:** December 5, 2024  
**Plugin Version:** 3.1.4  
**Maintainer:** Frank (rigtigformig.dk)  
**Next Review:** After v3.2.0 release

---

*This document is a living reference. Update it with every significant change to the plugin.*
