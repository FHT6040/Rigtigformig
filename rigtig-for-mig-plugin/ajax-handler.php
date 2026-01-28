<?php
/**
 * Direct AJAX Handler for RFM - ALL AJAX Requests
 *
 * IMPORTANT: ALL new AJAX handlers MUST be added here!
 * See AJAX-GUIDELINES.md for implementation instructions.
 *
 * This bypasses admin-ajax.php to avoid LiteSpeed Cache redirect issues
 *
 * @since 3.7.3
 * @updated 3.7.5 - Added avatar upload support
 * @updated 3.8.2 - Added expert dashboard handlers (save profile, categories, logout)
 */

// Find wp-load.php without using relative paths (open_basedir safe)
$wp_load_path = false;

// Method 1: Use ABSPATH if defined (won't be, but check anyway)
if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-load.php')) {
    $wp_load_path = ABSPATH . 'wp-load.php';
}

// Method 2: Search upward from current directory
if (!$wp_load_path) {
    $dir = dirname(__FILE__);
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/wp-load.php')) {
            $wp_load_path = $dir . '/wp-load.php';
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break; // Reached root
        $dir = $parent;
    }
}

// Method 3: Use document root
if (!$wp_load_path && isset($_SERVER['DOCUMENT_ROOT'])) {
    $doc_root = $_SERVER['DOCUMENT_ROOT'];
    if (file_exists($doc_root . '/wp-load.php')) {
        $wp_load_path = $doc_root . '/wp-load.php';
    }
}

// Method 4: Common paths
if (!$wp_load_path) {
    $common_paths = array(
        '/home/www/rigtigformig.dk/wp-load.php',
        '/var/www/html/wp-load.php',
        '/var/www/rigtigformig.dk/wp-load.php',
    );
    foreach ($common_paths as $path) {
        if (file_exists($path)) {
            $wp_load_path = $path;
            break;
        }
    }
}

if (!$wp_load_path) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => false,
        'data' => array('message' => 'WordPress kunne ikke findes. Kontakt administrator.')
    ));
    exit;
}

// Load WordPress
require_once $wp_load_path;

// Set JSON headers immediately
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');
// LiteSpeed Cache specific headers
header('X-LiteSpeed-Cache-Control: no-cache');
header('Pragma: no-cache');
header('Expires: 0');

// Prevent any HTML output
ob_start();

// Get the action
$action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';

// Debug logging
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('RFM Direct AJAX: Action = ' . $action);
    error_log('RFM Direct AJAX: User logged in = ' . (is_user_logged_in() ? 'yes' : 'no'));
}

// Handle different actions
switch ($action) {
    case 'rfm_update_user_profile':
        rfm_direct_update_user_profile();
        break;

    case 'rfm_upload_user_avatar':
        rfm_direct_upload_user_avatar();
        break;

    case 'rfm_user_logout':
        rfm_direct_user_logout();
        break;

    case 'rfm_save_general_profile':
        rfm_direct_save_general_profile();
        break;

    case 'rfm_save_category_profile':
        rfm_direct_save_category_profile();
        break;

    case 'rfm_expert_logout':
        rfm_direct_expert_logout();
        break;

    case 'rfm_upload_expert_avatar':
        rfm_direct_upload_expert_avatar();
        break;

    case 'rfm_upload_expert_banner':
        rfm_direct_upload_expert_banner();
        break;

    case 'rfm_unified_login':
        rfm_direct_unified_login();
        break;

    case 'rfm_submit_rating':
        rfm_direct_submit_rating();
        break;

    case 'rfm_send_message':
        rfm_direct_send_message();
        break;

    case 'rfm_get_messages':
        rfm_direct_get_messages();
        break;

    case 'rfm_get_conversation':
        rfm_direct_get_conversation();
        break;

    case 'rfm_mark_message_read':
        rfm_direct_mark_message_read();
        break;

    case 'rfm_mark_all_messages_read':
        rfm_direct_mark_all_messages_read();
        break;

    case 'rfm_delete_message':
        rfm_direct_delete_message();
        break;

    case 'rfm_get_conversations':
        rfm_direct_get_conversations();
        break;

    case 'rfm_heartbeat':
        rfm_direct_heartbeat();
        break;

    case 'rfm_save_booking_settings':
        rfm_direct_save_booking_settings();
        break;

    case 'rfm_save_internal_booking_settings':
        rfm_direct_save_internal_booking_settings();
        break;

    case 'rfm_get_available_slots':
        rfm_direct_get_available_slots();
        break;

    case 'rfm_create_booking':
        rfm_direct_create_booking();
        break;

    case 'rfm_update_booking_status':
        rfm_direct_update_booking_status();
        break;

    case 'rfm_cancel_user_booking':
        rfm_direct_cancel_user_booking();
        break;

    case 'rfm_get_available_days':
        rfm_direct_get_available_days();
        break;

    default:
        ob_end_clean();
        wp_send_json_error(array('message' => 'Ugyldig handling: ' . $action), 400);
        exit;
}

/**
 * Handle profile update
 */
function rfm_direct_update_user_profile() {
    // Clear any output
    ob_end_clean();

    // Verify nonce
    $nonce = isset($_POST['rfm_user_nonce']) ? sanitize_text_field($_POST['rfm_user_nonce']) : '';

    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_user_dashboard')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RFM Direct AJAX: Nonce verification FAILED');
        }
        wp_send_json_error(array(
            'message' => 'Sikkerhedstjek fejlede. Genindlæs siden og prøv igen.'
        ), 403);
        exit;
    }

    // Check login
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du skal være logget ind.'), 401);
        exit;
    }

    $user_id = get_current_user_id();

    // Get and sanitize input
    $display_name = isset($_POST['display_name']) ? sanitize_text_field($_POST['display_name']) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $bio = isset($_POST['bio']) ? sanitize_textarea_field($_POST['bio']) : '';
    $avatar_id = isset($_POST['avatar_id']) ? absint($_POST['avatar_id']) : 0;

    if (empty($display_name)) {
        wp_send_json_error(array('message' => 'Visningsnavn er påkrævet.'));
        exit;
    }

    // Update WordPress user
    $result = wp_update_user(array(
        'ID' => $user_id,
        'display_name' => $display_name
    ));

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => 'Kunne ikke opdatere profil: ' . $result->get_error_message()));
        exit;
    }

    // Update user meta
    update_user_meta($user_id, '_rfm_phone', $phone);
    update_user_meta($user_id, '_rfm_bio', $bio);

    // Update avatar if provided
    if ($avatar_id > 0) {
        update_user_meta($user_id, '_rfm_avatar_id', $avatar_id);
    }

    update_user_meta($user_id, '_rfm_last_login', current_time('mysql'));

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('RFM Direct AJAX: Profile updated for user ' . $user_id);
    }

    wp_send_json_success(array('message' => '✅ Din profil er opdateret!'));
    exit;
}

/**
 * Handle avatar upload
 *
 * @since 3.7.5
 */
function rfm_direct_upload_user_avatar() {
    // Clear any output
    ob_end_clean();

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('RFM: Avatar upload request received (direct AJAX)');
        error_log('RFM: POST data: ' . print_r($_POST, true));
        error_log('RFM: FILES data: ' . print_r($_FILES, true));
    }

    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_user_dashboard')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RFM: Nonce verification failed');
        }
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede. Prøv igen.'));
        exit;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du skal være logget ind.'));
        exit;
    }

    $user_id = get_current_user_id();

    // Check if file was uploaded
    if (empty($_FILES['avatar_image'])) {
        wp_send_json_error(array('message' => 'Ingen fil blev uploadet.'));
        exit;
    }

    $file = $_FILES['avatar_image'];

    // Validate file type - SECURITY: Check actual file content, not just browser-supplied MIME type
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');

    // Check actual MIME type from file content (more secure than $_FILES['type'])
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $real_mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($real_mime, $allowed_types)) {
        wp_send_json_error(array('message' => 'Ugyldig filtype. Kun billeder (JPG, PNG, GIF, WebP) er tilladt.'));
        exit;
    }

    // Additional security: Validate file extension
    $filename = sanitize_file_name($file['name']);
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed_exts = array('jpg', 'jpeg', 'png', 'gif', 'webp');

    if (!in_array($file_ext, $allowed_exts)) {
        wp_send_json_error(array('message' => 'Ugyldig fil-extension. Kun .jpg, .png, .gif og .webp er tilladt.'));
        exit;
    }

    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $max_size) {
        wp_send_json_error(array('message' => 'Filen er for stor. Maksimum 5MB.'));
        exit;
    }

    // Include WordPress media functions
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Upload the file
    $attachment_id = media_handle_upload('avatar_image', 0);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        exit;
    }

    // Update user meta
    update_user_meta($user_id, '_rfm_avatar_id', $attachment_id);

    // Get the image URL
    $image_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
    $image_html = wp_get_attachment_image($attachment_id, 'thumbnail', false, array('class' => 'rfm-user-avatar'));

    wp_send_json_success(array(
        'message' => 'Profilbillede uploadet!',
        'attachment_id' => $attachment_id,
        'image_url' => $image_url,
        'image_html' => $image_html
    ));
    exit;
}

/**
 * Handle logout
 */
function rfm_direct_user_logout() {
    ob_end_clean();

    // Verify nonce
    $nonce = isset($_POST['rfm_user_nonce']) ? sanitize_text_field($_POST['rfm_user_nonce']) : '';

    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_user_dashboard')) {
        wp_send_json_error(array(
            'message' => 'Sikkerhedstjek fejlede.',
            'redirect' => home_url()
        ), 403);
        exit;
    }

    wp_logout();

    wp_send_json_success(array(
        'message' => 'Du er nu logget ud',
        'redirect' => home_url()
    ));
    exit;
}

/**
 * Handle expert general profile save
 *
 * @since 3.8.2
 */
function rfm_direct_save_general_profile() {
    ob_end_clean();

    // Verify nonce - accept both 'nonce' and 'rfm_tabbed_nonce' field names
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (empty($nonce)) {
        $nonce = isset($_POST['rfm_tabbed_nonce']) ? sanitize_text_field($_POST['rfm_tabbed_nonce']) : '';
    }

    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_dashboard_tabbed')) {
        error_log('RFM: Nonce verification failed. Nonce: ' . $nonce . ', POST keys: ' . implode(', ', array_keys($_POST)));
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede. Genindlæs siden og prøv igen.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du skal være logget ind.'), 401);
        exit;
    }

    $user_id = get_current_user_id();
    $expert_id = intval($_POST['expert_id']);

    // Verify ownership
    $post = get_post($expert_id);
    if (!$post || $post->post_author != $user_id) {
        wp_send_json_error(array('message' => 'Du har ikke tilladelse.'), 403);
        exit;
    }

    // Get plan for validation
    $plan = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);

    // Update post title (name)
    wp_update_post(array(
        'ID' => $expert_id,
        'post_title' => sanitize_text_field($_POST['name'])
    ));

    // Update basic meta
    update_post_meta($expert_id, '_rfm_email', sanitize_email($_POST['email']));
    update_post_meta($expert_id, '_rfm_phone', sanitize_text_field($_POST['phone'] ?? ''));

    // Update location fields (available for all plans)
    if (isset($_POST['address'])) {
        update_post_meta($expert_id, '_rfm_address', sanitize_text_field($_POST['address']));
    }
    if (isset($_POST['city'])) {
        update_post_meta($expert_id, '_rfm_city', sanitize_text_field($_POST['city']));
    }

    // Update postal code and auto-populate coordinates
    if (isset($_POST['postal_code'])) {
        $postal_code = sanitize_text_field($_POST['postal_code']);
        update_post_meta($expert_id, '_rfm_postal_code', $postal_code);

        // Auto-populate coordinates from postal code
        if (!empty($postal_code) && class_exists('RFM_Postal_Codes')) {
            $coordinates = RFM_Postal_Codes::get_coordinates($postal_code);
            if ($coordinates) {
                update_post_meta($expert_id, '_rfm_latitude', $coordinates['latitude']);
                update_post_meta($expert_id, '_rfm_longitude', $coordinates['longitude']);
            } else {
                // Invalid postal code - clear coordinates
                delete_post_meta($expert_id, '_rfm_latitude');
                delete_post_meta($expert_id, '_rfm_longitude');
            }
        }
    }

    // Update fields based on plan
    if ($plan === 'standard' || $plan === 'premium') {
        update_post_meta($expert_id, '_rfm_website', esc_url_raw($_POST['website'] ?? ''));
        update_post_meta($expert_id, '_rfm_company_name', sanitize_text_field($_POST['company_name'] ?? ''));
    }

    // Update languages
    if (isset($_POST['languages'])) {
        $languages = array_map('sanitize_text_field', $_POST['languages']);
        update_post_meta($expert_id, '_rfm_languages', $languages);
    } else {
        update_post_meta($expert_id, '_rfm_languages', array());
    }

    // Update categories with limit validation
    $max_categories = array(
        'free' => 1,
        'standard' => 2,
        'premium' => 99
    );
    $allowed_cats = $max_categories[$plan] ?? 1;

    $success_message = '✅ Generelle oplysninger gemt!';

    if (isset($_POST['categories'])) {
        $submitted_categories = array_map('intval', $_POST['categories']);
        $categories_count = count($submitted_categories);

        error_log("RFM DEBUG: Submitted categories: " . implode(', ', $submitted_categories));
        error_log("RFM DEBUG: Plan: $plan, Allowed: $allowed_cats");

        // Trim to allowed limit
        $categories = array_slice($submitted_categories, 0, $allowed_cats);

        error_log("RFM DEBUG: Categories after slice: " . implode(', ', $categories));

        // Set terms
        $result = wp_set_object_terms($expert_id, $categories, 'rfm_category');

        if (is_wp_error($result)) {
            error_log("RFM ERROR: Failed to set terms: " . $result->get_error_message());
        } else {
            error_log("RFM SUCCESS: Set terms result: " . print_r($result, true));
        }

        // CRITICAL: Clear WordPress object cache to ensure fresh data on page reload
        clean_object_term_cache($expert_id, 'rfm_expert');
        wp_cache_delete($expert_id, 'rfm_expert_terms');
        wp_cache_delete('rfm_category_relationships_' . $expert_id);

        // Verify what was actually saved
        $saved_terms = wp_get_object_terms($expert_id, 'rfm_category', array('fields' => 'ids'));
        error_log("RFM VERIFY: Saved term IDs in DB: " . implode(', ', $saved_terms));

        error_log("RFM: Saved categories for expert $expert_id: " . implode(', ', $categories));

        // Inform user if some categories were removed due to limit
        if ($categories_count > $allowed_cats) {
            $success_message = sprintf(
                '✅ Oplysninger gemt! Note: Kun %d kategorier blev gemt (dit abonnement tillader %d).',
                count($categories),
                $allowed_cats
            );
        }
    }

    wp_send_json_success(array(
        'message' => $success_message
    ));
    exit;
}

/**
 * Handle expert category profile save
 *
 * @since 3.8.2
 */
function rfm_direct_save_category_profile() {
    ob_end_clean();

    // Verify nonce - accept both 'nonce' and 'rfm_tabbed_nonce' field names
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (empty($nonce)) {
        $nonce = isset($_POST['rfm_tabbed_nonce']) ? sanitize_text_field($_POST['rfm_tabbed_nonce']) : '';
    }

    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_dashboard_tabbed')) {
        error_log('RFM: Category profile - Nonce verification failed');
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede. Genindlæs siden og prøv igen.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du skal være logget ind.'), 401);
        exit;
    }

    $user_id = get_current_user_id();
    $expert_id = intval($_POST['expert_id']);
    $category_id = intval($_POST['category_id']);

    // Verify ownership
    $post = get_post($expert_id);
    if (!$post || $post->post_author != $user_id) {
        wp_send_json_error(array('message' => 'Du har ikke tilladelse.'), 403);
        exit;
    }

    // Verify expert has this category
    $expert_categories = wp_get_object_terms($expert_id, 'rfm_category', array('fields' => 'ids'));
    if (!in_array($category_id, $expert_categories)) {
        wp_send_json_error(array('message' => 'Du har ikke denne kategori.'), 400);
        exit;
    }

    // Get plan for validation
    $plan = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);

    // Define limits
    $max_educations = array(
        'free' => 1,
        'standard' => 3,
        'premium' => 7
    );
    $max_specs = array(
        'free' => 1,
        'standard' => 3,
        'premium' => 7
    );

    $allowed_educations = $max_educations[$plan] ?? 1;
    $allowed_specs = $max_specs[$plan] ?? 1;

    // Prepare educations data
    $educations = isset($_POST['educations']) ? $_POST['educations'] : array();
    $sanitized_educations = array();

    if (is_array($educations)) {
        foreach ($educations as $edu) {
            if (empty($edu['name'])) {
                continue;
            }

            $sanitized_educations[] = array(
                'name' => sanitize_text_field($edu['name']),
                'institution' => sanitize_text_field($edu['institution'] ?? ''),
                'year_start' => sanitize_text_field($edu['year_start'] ?? ''),
                'year_end' => sanitize_text_field($edu['year_end'] ?? ''),
                'experience_start_year' => absint($edu['experience_start_year'] ?? 0),
                'description' => sanitize_textarea_field($edu['description'] ?? ''),
                'image_id' => absint($edu['image_id'] ?? 0)
            );
        }
    }

    $sanitized_educations = array_slice($sanitized_educations, 0, $allowed_educations);

    // Prepare specializations
    $specializations = isset($_POST['specializations']) ? array_map('intval', $_POST['specializations']) : array();
    $specializations = array_slice($specializations, 0, $allowed_specs);

    // Prepare category profile data
    $profile = array(
        'about_me' => sanitize_textarea_field($_POST['about_me'] ?? ''),
        'years_experience' => absint($_POST['years_experience'] ?? 0),
        'experience_start_year' => absint($_POST['experience_start_year'] ?? 0),
        'educations' => $sanitized_educations,
        'specializations' => $specializations
    );

    // Save category profile
    $meta_key = '_rfm_category_profile_' . $category_id;
    update_post_meta($expert_id, $meta_key, $profile);

    // Update global specializations taxonomy
    $all_specs = array();
    foreach ($expert_categories as $cat_id) {
        $cat_profile = get_post_meta($expert_id, '_rfm_category_profile_' . $cat_id, true);
        if (!empty($cat_profile['specializations'])) {
            $all_specs = array_merge($all_specs, $cat_profile['specializations']);
        }
    }
    $all_specs = array_unique($all_specs);
    wp_set_object_terms($expert_id, $all_specs, 'rfm_specialization');

    $category = get_term($category_id, 'rfm_category');
    $category_name = $category ? $category->name : '';

    wp_send_json_success(array(
        'message' => sprintf('✅ Profil for "%s" gemt!', $category_name)
    ));
    exit;
}

/**
 * Handle expert logout
 *
 * @since 3.8.2
 */
function rfm_direct_expert_logout() {
    ob_end_clean();

    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_logout')) {
        wp_send_json_error(array(
            'message' => 'Sikkerhedstjek fejlede.',
            'redirect' => home_url()
        ), 403);
        exit;
    }

    wp_logout();

    wp_send_json_success(array(
        'message' => 'Du er nu logget ud',
        'redirect' => home_url()
    ));
    exit;
}

/**
 * Handle expert avatar upload
 *
 * @since 3.9.1
 */
function rfm_direct_upload_expert_avatar() {
    ob_end_clean();

    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_dashboard_tabbed')) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du skal være logget ind.'), 401);
        exit;
    }

    $user_id = get_current_user_id();
    $expert_id = isset($_POST['expert_id']) ? intval($_POST['expert_id']) : 0;

    // Verify ownership
    $post = get_post($expert_id);
    if (!$post || $post->post_author != $user_id) {
        wp_send_json_error(array('message' => 'Du har ikke tilladelse.'), 403);
        exit;
    }

    // Check if file was uploaded
    if (empty($_FILES['avatar_image'])) {
        wp_send_json_error(array('message' => 'Ingen fil blev uploadet.'));
        exit;
    }

    $file = $_FILES['avatar_image'];

    // Validate file type - SECURITY: Check actual file content
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $real_mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($real_mime, $allowed_types)) {
        wp_send_json_error(array('message' => 'Ugyldig filtype. Kun billeder (JPG, PNG, GIF, WebP) er tilladt.'));
        exit;
    }

    // Validate file extension
    $filename = sanitize_file_name($file['name']);
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed_exts = array('jpg', 'jpeg', 'png', 'gif', 'webp');

    if (!in_array($file_ext, $allowed_exts)) {
        wp_send_json_error(array('message' => 'Ugyldig fil-extension.'));
        exit;
    }

    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        wp_send_json_error(array('message' => 'Filen er for stor. Maksimum 5MB.'));
        exit;
    }

    // Include WordPress media functions
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Upload the file
    $attachment_id = media_handle_upload('avatar_image', $expert_id);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        exit;
    }

    // Set as featured image (used as avatar)
    set_post_thumbnail($expert_id, $attachment_id);

    // Get the image URL
    $image_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');

    wp_send_json_success(array(
        'message' => 'Profilbillede uploadet!',
        'attachment_id' => $attachment_id,
        'image_url' => $image_url
    ));
    exit;
}

/**
 * Handle expert banner upload
 *
 * @since 3.9.1
 */
function rfm_direct_upload_expert_banner() {
    ob_end_clean();

    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_dashboard_tabbed')) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du skal være logget ind.'), 401);
        exit;
    }

    $user_id = get_current_user_id();
    $expert_id = isset($_POST['expert_id']) ? intval($_POST['expert_id']) : 0;

    // Verify ownership
    $post = get_post($expert_id);
    if (!$post || $post->post_author != $user_id) {
        wp_send_json_error(array('message' => 'Du har ikke tilladelse.'), 403);
        exit;
    }

    // Check if expert has Premium plan
    $plan = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);
    if ($plan !== 'premium') {
        wp_send_json_error(array('message' => 'Banner billeder kræver Premium abonnement.'), 403);
        exit;
    }

    // Check if file was uploaded
    if (empty($_FILES['banner_image'])) {
        wp_send_json_error(array('message' => 'Ingen fil blev uploadet.'));
        exit;
    }

    $file = $_FILES['banner_image'];

    // Validate file type - SECURITY: Check actual file content
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $real_mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($real_mime, $allowed_types)) {
        wp_send_json_error(array('message' => 'Ugyldig filtype. Kun billeder (JPG, PNG, GIF, WebP) er tilladt.'));
        exit;
    }

    // Validate file extension
    $filename = sanitize_file_name($file['name']);
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed_exts = array('jpg', 'jpeg', 'png', 'gif', 'webp');

    if (!in_array($file_ext, $allowed_exts)) {
        wp_send_json_error(array('message' => 'Ugyldig fil-extension.'));
        exit;
    }

    // Validate file size (max 10MB for banners)
    $max_size = 10 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        wp_send_json_error(array('message' => 'Filen er for stor. Maksimum 10MB.'));
        exit;
    }

    // Include WordPress media functions
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Upload the file
    $attachment_id = media_handle_upload('banner_image', $expert_id);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        exit;
    }

    // Save as banner meta field
    update_post_meta($expert_id, '_rfm_banner_image_id', $attachment_id);

    // Get the image URL
    $image_url = wp_get_attachment_image_url($attachment_id, 'large');

    wp_send_json_success(array(
        'message' => 'Banner billede uploadet!',
        'attachment_id' => $attachment_id,
        'image_url' => $image_url
    ));
    exit;
}


/**
 * Handle unified login (for both users and experts)
 *
 * @since 3.8.4
 */
function rfm_direct_unified_login() {
    ob_end_clean();

    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_nonce')) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    $identifier = isset($_POST['identifier']) ? sanitize_text_field($_POST['identifier']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = isset($_POST['remember']) ? true : false;

    // Validate
    if (empty($identifier) || empty($password)) {
        wp_send_json_error(array('message' => 'Alle felter er påkrævede'));
        exit;
    }

    // Determine if identifier is email or username
    $user = null;
    if (is_email($identifier)) {
        $user = get_user_by('email', $identifier);
    } else {
        $user = get_user_by('login', $identifier);
    }

    if (!$user) {
        wp_send_json_error(array('message' => 'Ugyldigt brugernavn/e-mail eller adgangskode'));
        exit;
    }

    // Check password
    if (!wp_check_password($password, $user->user_pass, $user->ID)) {
        wp_send_json_error(array('message' => 'Ugyldigt brugernavn/e-mail eller adgangskode'));
        exit;
    }

    // Check if email is verified
    $verified = false;

    if (in_array('rfm_expert_user', $user->roles)) {
        // For experts: Check if they have an expert post and if it is verified
        $expert_posts = get_posts(array(
            'post_type' => 'rfm_expert',
            'author' => $user->ID,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));

        if (!empty($expert_posts)) {
            $verified = (bool) get_post_meta($expert_posts[0]->ID, '_rfm_email_verified', true);
        }
    } else {
        // For regular users: Check using unified migration helper
        if (class_exists('RFM_Migration')) {
            $verified = RFM_Migration::is_user_verified($user->ID);
        } else {
            // Fallback if class not available
            $verified = get_user_meta($user->ID, '_rfm_email_verified', true);
        }
    }

    if (!$verified) {
        wp_send_json_error(array('message' => 'Din e-mail er ikke bekræftet. Tjek din indbakke.'));
        exit;
    }

    // Log user in
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, $remember);
    do_action('wp_login', $user->user_login, $user);

    // Update last login
    if (in_array('rfm_user', $user->roles)) {
        if (class_exists('RFM_Migration')) {
            RFM_Migration::update_last_login($user->ID);
        } else {
            update_user_meta($user->ID, '_rfm_last_login', current_time('mysql'));
        }
    }

    // Determine redirect based on role
    $redirect = home_url();
    if (in_array('rfm_expert_user', $user->roles)) {
        $redirect = home_url('/ekspert-dashboard');
    } elseif (in_array('rfm_user', $user->roles)) {
        $redirect = home_url('/bruger-dashboard');
    }

    error_log("RFM: User {$user->ID} logged in successfully. Redirect: {$redirect}");

    wp_send_json_success(array(
        'message' => 'Du er nu logget ind!',
        'redirect' => $redirect
    ));
    exit;
}

/**
 * Handle rating submission
 *
 * @since 3.8.27
 */
function rfm_direct_submit_rating() {
    ob_end_clean();

    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_nonce')) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array(
            'message' => __('Du skal være logget ind for at bedømme en ekspert.', 'rigtig-for-mig')
        ));
        exit;
    }

    $expert_id = isset($_POST['expert_id']) ? intval($_POST['expert_id']) : 0;
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $review = isset($_POST['review']) ? sanitize_textarea_field($_POST['review']) : '';
    $user_id = get_current_user_id();

    // Validate
    if (!$expert_id || !$rating || $rating < 1 || $rating > 5) {
        wp_send_json_error(array(
            'message' => __('Ugyldig bedømmelse.', 'rigtig-for-mig')
        ));
        exit;
    }

    // Check if post exists and is an expert
    if (get_post_type($expert_id) !== 'rfm_expert') {
        wp_send_json_error(array(
            'message' => __('Ekspert ikke fundet.', 'rigtig-for-mig')
        ));
        exit;
    }

    // Prevent self-rating
    if (get_post_field('post_author', $expert_id) == $user_id) {
        wp_send_json_error(array(
            'message' => __('Du kan ikke bedømme din egen profil.', 'rigtig-for-mig')
        ));
        exit;
    }

    global $wpdb;
    $table = RFM_Database::get_table_name('ratings');

    // Check if user has already rated this expert
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, created_at FROM $table WHERE expert_id = %d AND user_id = %d",
        $expert_id,
        $user_id
    ));

    if ($existing) {
        // Check 180-day cooldown
        $days_since_rating = floor((time() - strtotime($existing->created_at)) / (60 * 60 * 24));

        if ($days_since_rating < 180) {
            $days_remaining = 180 - $days_since_rating;
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Du kan først bedømme denne ekspert igen om %d dage. Du anmeldte sidst for %d dage siden.', 'rigtig-for-mig'),
                    $days_remaining,
                    $days_since_rating
                )
            ));
            exit;
        }

        // Update existing rating (after 180 days)
        $result = $wpdb->update(
            $table,
            array(
                'rating' => $rating,
                'review' => $review,
                'created_at' => current_time('mysql')
            ),
            array(
                'expert_id' => $expert_id,
                'user_id' => $user_id
            ),
            array('%d', '%s', '%s'),
            array('%d', '%d')
        );
    } else {
        // Insert new rating
        $result = $wpdb->insert(
            $table,
            array(
                'expert_id' => $expert_id,
                'user_id' => $user_id,
                'rating' => $rating,
                'review' => $review
            ),
            array('%d', '%d', '%d', '%s')
        );
    }

    if ($result !== false) {
        // Update average rating using RFM_Ratings class
        if (class_exists('RFM_Ratings')) {
            $ratings_instance = RFM_Ratings::get_instance();
            $ratings_instance->update_average_rating($expert_id);
        }

        do_action('rfm_rating_submitted', $expert_id, $user_id, $rating);

        wp_send_json_success(array(
            'message' => __('Din bedømmelse er indsendt!', 'rigtig-for-mig')
        ));
    } else {
        wp_send_json_error(array(
            'message' => __('Der opstod en fejl. Prøv venligst igen.', 'rigtig-for-mig')
        ));
    }
    exit;
}

/**
 * Handle sending a message
 *
 * @since 3.8.29
 */
function rfm_direct_send_message() {
    ob_end_clean();

    // Verify nonce - accept both rfm_nonce and rfm_user_dashboard for compatibility
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    $nonce_valid = (!empty($nonce) && (wp_verify_nonce($nonce, 'rfm_nonce') || wp_verify_nonce($nonce, 'rfm_user_dashboard') || wp_verify_nonce($nonce, 'rfm_expert_dashboard')));

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array(
            'message' => __('Du skal være logget ind for at sende en besked.', 'rigtig-for-mig')
        ));
        exit;
    }

    $expert_id = isset($_POST['expert_id']) ? intval($_POST['expert_id']) : 0;
    $recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;
    $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
    $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
    $sender_id = get_current_user_id();

    // Validate
    if (!$expert_id || empty($message)) {
        wp_send_json_error(array(
            'message' => __('Besked og ekspert-ID er påkrævet.', 'rigtig-for-mig')
        ));
        exit;
    }

    // Check if expert exists
    if (get_post_type($expert_id) !== 'rfm_expert') {
        wp_send_json_error(array(
            'message' => __('Ekspert ikke fundet.', 'rigtig-for-mig')
        ));
        exit;
    }

    // Determine recipient
    // If recipient_id is provided (expert replying to user), use it
    // Otherwise, get expert author ID (user sending to expert)
    if (!$recipient_id) {
        $recipient_id = get_post_field('post_author', $expert_id);
        if (!$recipient_id) {
            wp_send_json_error(array(
                'message' => __('Modtager ikke fundet.', 'rigtig-for-mig')
            ));
            exit;
        }
    }

    // Prevent sending message to self
    if ($sender_id == $recipient_id) {
        wp_send_json_error(array(
            'message' => __('Du kan ikke sende en besked til dig selv.', 'rigtig-for-mig')
        ));
        exit;
    }

    // Send message directly using database
    if (class_exists('RFM_Messages')) {
        global $wpdb;
        $table = RFM_Database::get_table_name('messages');

        $result = $wpdb->insert(
            $table,
            array(
                'sender_id' => $sender_id,
                'recipient_id' => $recipient_id,
                'expert_id' => $expert_id,
                'subject' => $subject,
                'message' => $message,
                'is_read' => 0
            ),
            array('%d', '%d', '%d', '%s', '%s', '%d')
        );

        if ($result) {
            $message_id = $wpdb->insert_id;

            // Update thread
            // Determine the user_id for the thread (always the non-expert user)
            $expert_author = get_post_field('post_author', $expert_id);
            $thread_user_id = ($sender_id == $expert_author) ? $recipient_id : $sender_id;

            // Update thread timestamp
            $threads_table = RFM_Database::get_table_name('message_threads');
            $existing_thread = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $threads_table WHERE user_id = %d AND expert_id = %d",
                $thread_user_id,
                $expert_id
            ));

            if ($existing_thread) {
                $wpdb->update(
                    $threads_table,
                    array('last_message_at' => current_time('mysql')),
                    array('id' => $existing_thread->id),
                    array('%s'),
                    array('%d')
                );
            } else {
                $wpdb->insert(
                    $threads_table,
                    array(
                        'user_id' => $thread_user_id,
                        'expert_id' => $expert_id,
                        'last_message_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%s')
                );
            }

            // Trigger notification action
            do_action('rfm_message_sent', $message_id, $sender_id, $recipient_id, $expert_id);

            wp_send_json_success(array(
                'message' => __('Din besked er sendt!', 'rigtig-for-mig'),
                'message_id' => $message_id
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Der opstod en fejl ved afsendelse. Prøv venligst igen.', 'rigtig-for-mig')
            ));
        }
    } else {
        wp_send_json_error(array(
            'message' => __('Besked systemet er ikke tilgængeligt.', 'rigtig-for-mig')
        ));
    }
    exit;
}

/**
 * Handle getting messages
 *
 * @since 3.8.29
 */
function rfm_direct_get_messages() {
    ob_end_clean();

    // Verify nonce - accept both rfm_nonce and rfm_user_dashboard for compatibility
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    $nonce_valid = (!empty($nonce) && (wp_verify_nonce($nonce, 'rfm_nonce') || wp_verify_nonce($nonce, 'rfm_user_dashboard') || wp_verify_nonce($nonce, 'rfm_expert_dashboard')));

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array(
            'message' => __('Du skal være logget ind.', 'rigtig-for-mig')
        ));
        exit;
    }

    $user_id = get_current_user_id();
    $box = isset($_POST['box']) ? sanitize_text_field($_POST['box']) : 'inbox';
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

    if (class_exists('RFM_Messages')) {
        $messages = RFM_Messages::get_instance();
        $messages_list = $messages->get_messages($user_id, $box, $limit, $offset);

        wp_send_json_success(array(
            'messages' => $messages_list
        ));
    } else {
        wp_send_json_error(array(
            'message' => __('Besked systemet er ikke tilgængeligt.', 'rigtig-for-mig')
        ));
    }
    exit;
}

/**
 * Handle getting conversation
 *
 * @since 3.8.29
 */
function rfm_direct_get_conversation() {
    ob_end_clean();

    // Verify nonce - accept both rfm_nonce and rfm_user_dashboard for compatibility
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    $nonce_valid = (!empty($nonce) && (wp_verify_nonce($nonce, 'rfm_nonce') || wp_verify_nonce($nonce, 'rfm_user_dashboard') || wp_verify_nonce($nonce, 'rfm_expert_dashboard')));

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array(
            'message' => __('Du skal være logget ind.', 'rigtig-for-mig')
        ));
        exit;
    }

    $current_user_id = get_current_user_id();
    $expert_id = isset($_POST['expert_id']) ? intval($_POST['expert_id']) : 0;
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    // If user_id is not provided, use current user (for regular users viewing their own conversations)
    if (!$user_id) {
        $user_id = $current_user_id;
    }

    if (!$expert_id) {
        wp_send_json_error(array(
            'message' => __('Ekspert-ID er påkrævet.', 'rigtig-for-mig')
        ));
        exit;
    }

    // Security check: verify user is authorized to view this conversation
    // Either they are the user in the conversation, or they are the expert
    $expert_author_id = get_post_field('post_author', $expert_id);
    if ($current_user_id != $user_id && $current_user_id != $expert_author_id) {
        wp_send_json_error(array(
            'message' => __('Du har ikke adgang til denne samtale.', 'rigtig-for-mig')
        ));
        exit;
    }

    if (class_exists('RFM_Messages')) {
        $messages = RFM_Messages::get_instance();
        $conversation = $messages->get_conversation($user_id, $expert_id);

        // Mark messages as read for the current user
        foreach ($conversation as $message) {
            if ($message->recipient_id == $current_user_id && $message->is_read == 0) {
                $messages->mark_as_read($message->id, $current_user_id);
            }
        }

        wp_send_json_success(array(
            'messages' => $conversation
        ));
    } else {
        wp_send_json_error(array(
            'message' => __('Besked systemet er ikke tilgængeligt.', 'rigtig-for-mig')
        ));
    }
    exit;
}

/**
 * Handle marking message as read
 *
 * @since 3.8.29
 */
function rfm_direct_mark_message_read() {
    ob_end_clean();

    // Verify nonce - accept both rfm_nonce and rfm_user_dashboard for compatibility
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    $nonce_valid = (!empty($nonce) && (wp_verify_nonce($nonce, 'rfm_nonce') || wp_verify_nonce($nonce, 'rfm_user_dashboard') || wp_verify_nonce($nonce, 'rfm_expert_dashboard')));

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array(
            'message' => __('Du skal være logget ind.', 'rigtig-for-mig')
        ));
        exit;
    }

    $user_id = get_current_user_id();
    $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;

    if (!$message_id) {
        wp_send_json_error(array(
            'message' => __('Besked-ID er påkrævet.', 'rigtig-for-mig')
        ));
        exit;
    }

    if (class_exists('RFM_Messages')) {
        $messages = RFM_Messages::get_instance();
        $success = $messages->mark_as_read($message_id, $user_id);

        if ($success) {
            wp_send_json_success(array(
                'message' => __('Besked markeret som læst.', 'rigtig-for-mig')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Kunne ikke markere besked som læst.', 'rigtig-for-mig')
            ));
        }
    } else {
        wp_send_json_error(array(
            'message' => __('Besked systemet er ikke tilgængeligt.', 'rigtig-for-mig')
        ));
    }
    exit;
}

/**
 * Handle marking all messages as read for a user
 *
 * @since 3.8.38
 */
function rfm_direct_mark_all_messages_read() {
    ob_end_clean();

    // Verify nonce - accept both rfm_nonce and rfm_user_dashboard and rfm_expert_dashboard for compatibility
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    $nonce_valid = (!empty($nonce) && (wp_verify_nonce($nonce, 'rfm_nonce') || wp_verify_nonce($nonce, 'rfm_user_dashboard') || wp_verify_nonce($nonce, 'rfm_expert_dashboard')));

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array(
            'message' => __('Du skal være logget ind.', 'rigtig-for-mig')
        ));
        exit;
    }

    $user_id = get_current_user_id();

    if (class_exists('RFM_Messages')) {
        $messages = RFM_Messages::get_instance();
        $success = $messages->mark_all_as_read($user_id);

        if ($success) {
            wp_send_json_success(array(
                'message' => __('Alle beskeder markeret som læst.', 'rigtig-for-mig'),
                'unread_count' => 0
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Kunne ikke markere alle beskeder som læst.', 'rigtig-for-mig')
            ));
        }
    } else {
        wp_send_json_error(array(
            'message' => __('Besked systemet er ikke tilgængeligt.', 'rigtig-for-mig')
        ));
    }
    exit;
}

/**
 * Handle deleting message
 *
 * @since 3.8.29
 */
function rfm_direct_delete_message() {
    ob_end_clean();

    // Verify nonce - accept both rfm_nonce and rfm_user_dashboard for compatibility
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    $nonce_valid = (!empty($nonce) && (wp_verify_nonce($nonce, 'rfm_nonce') || wp_verify_nonce($nonce, 'rfm_user_dashboard') || wp_verify_nonce($nonce, 'rfm_expert_dashboard')));

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array(
            'message' => __('Du skal være logget ind.', 'rigtig-for-mig')
        ));
        exit;
    }

    $user_id = get_current_user_id();
    $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;

    if (!$message_id) {
        wp_send_json_error(array(
            'message' => __('Besked-ID er påkrævet.', 'rigtig-for-mig')
        ));
        exit;
    }

    if (class_exists('RFM_Messages')) {
        $messages = RFM_Messages::get_instance();
        $success = $messages->delete_message($message_id, $user_id);

        if ($success) {
            wp_send_json_success(array(
                'message' => __('Besked slettet.', 'rigtig-for-mig')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Kunne ikke slette besked.', 'rigtig-for-mig')
            ));
        }
    } else {
        wp_send_json_error(array(
            'message' => __('Besked systemet er ikke tilgængeligt.', 'rigtig-for-mig')
        ));
    }
    exit;
}

/**
 * Handle getting conversations
 *
 * @since 3.8.29
 */
function rfm_direct_get_conversations() {
    ob_end_clean();

    // Verify nonce - accept both rfm_nonce and rfm_user_dashboard for compatibility
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    $nonce_valid = (!empty($nonce) && (wp_verify_nonce($nonce, 'rfm_nonce') || wp_verify_nonce($nonce, 'rfm_user_dashboard') || wp_verify_nonce($nonce, 'rfm_expert_dashboard')));

    if (!$nonce_valid) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array(
            'message' => __('Du skal være logget ind.', 'rigtig-for-mig')
        ));
        exit;
    }

    $user_id = get_current_user_id();
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'user';

    if (class_exists('RFM_Messages')) {
        $messages = RFM_Messages::get_instance();
        $conversations = $messages->get_conversations($user_id, $type);
        $unread_count = $messages->get_unread_count($user_id);

        wp_send_json_success(array(
            'conversations' => $conversations,
            'unread_count' => $unread_count
        ));
    } else {
        wp_send_json_error(array(
            'message' => __('Besked systemet er ikke tilgængeligt.', 'rigtig-for-mig')
        ));
    }
    exit;
}

/**
 * Handle online status heartbeat from frontend
 *
 * @since 3.8.41
 */
function rfm_direct_heartbeat() {
    ob_end_clean();

    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'rfm_heartbeat_nonce')) {
        rfm_log("Online Status HEARTBEAT (ajax-handler): Nonce verification failed");
        wp_send_json_error(array('message' => 'Invalid nonce'));
        exit;
    }

    if (!is_user_logged_in()) {
        rfm_log("Online Status HEARTBEAT (ajax-handler): Not logged in");
        wp_send_json_error(array('message' => 'Not logged in'));
        exit;
    }

    $user_id = get_current_user_id();
    $timestamp = current_time('timestamp');
    update_user_meta($user_id, '_rfm_last_active', $timestamp);

    rfm_log("Online Status HEARTBEAT (ajax-handler): User ID $user_id - heartbeat received via ajax-handler.php, updated timestamp to $timestamp (" . date('Y-m-d H:i:s', $timestamp) . ")");

    wp_send_json_success(array(
        'message' => 'Activity tracked',
        'timestamp' => $timestamp,
        'user_id' => $user_id
    ));
    exit;
}

/**
 * Handle saving booking settings for experts
 *
 * @since 3.9.8
 */
function rfm_direct_save_booking_settings() {
    ob_end_clean();

    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (empty($nonce)) {
        $nonce = isset($_POST['rfm_tabbed_nonce']) ? sanitize_text_field($_POST['rfm_tabbed_nonce']) : '';
    }

    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_dashboard_tabbed')) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede. Genindlæs siden og prøv igen.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du skal være logget ind.'), 401);
        exit;
    }

    $user_id = get_current_user_id();
    $expert_id = isset($_POST['expert_id']) ? intval($_POST['expert_id']) : 0;

    // Verify ownership
    $post = get_post($expert_id);
    if (!$post || $post->post_author != $user_id) {
        wp_send_json_error(array('message' => 'Du har ikke tilladelse.'), 403);
        exit;
    }

    // Check if expert has booking feature
    if (!RFM_Subscriptions::can_use_feature($expert_id, 'booking')) {
        wp_send_json_error(array('message' => 'Booking kræver Standard eller Premium abonnement.'), 403);
        exit;
    }

    // Prepare booking data
    $booking_data = array(
        'booking_url' => isset($_POST['booking_url']) ? esc_url_raw($_POST['booking_url']) : '',
        'booking_button_text' => isset($_POST['booking_button_text']) ? sanitize_text_field($_POST['booking_button_text']) : '',
        'booking_enabled' => isset($_POST['booking_enabled']) && ($_POST['booking_enabled'] === '1' || $_POST['booking_enabled'] === 'true')
    );

    // Save booking settings
    if (class_exists('RFM_Booking_Link')) {
        $result = RFM_Booking_Link::get_instance()->save_booking_settings($expert_id, $booking_data);

        if ($result) {
            wp_send_json_success(array(
                'message' => '✅ Booking-indstillinger gemt!'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Kunne ikke gemme booking-indstillinger.'
            ));
        }
    } else {
        wp_send_json_error(array(
            'message' => 'Booking-systemet er ikke tilgængeligt.'
        ));
    }
    exit;
}

/**
 * Save internal booking settings (mode, availability, duration)
 *
 * @since 3.10.0
 */
function rfm_direct_save_internal_booking_settings() {
    ob_end_clean();

    // Verify nonce
    $nonce = sanitize_text_field($_POST['nonce'] ?? $_POST['rfm_tabbed_nonce'] ?? '');
    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_dashboard_tabbed')) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du skal være logget ind.'), 401);
        exit;
    }

    $user_id = get_current_user_id();
    $expert_id = intval($_POST['expert_id'] ?? 0);

    // Verify ownership
    $post = get_post($expert_id);
    if (!$post || $post->post_author != $user_id) {
        wp_send_json_error(array('message' => 'Du har ikke tilladelse.'), 403);
        exit;
    }

    if (!RFM_Subscriptions::can_use_feature($expert_id, 'booking')) {
        wp_send_json_error(array('message' => 'Booking kræver Standard eller Premium abonnement.'), 403);
        exit;
    }

    // Save booking mode
    $mode = sanitize_text_field($_POST['booking_mode'] ?? 'external');
    if (!in_array($mode, array('external', 'internal'))) {
        $mode = 'external';
    }
    update_post_meta($expert_id, '_rfm_booking_mode', $mode);

    // Save duration
    $duration = intval($_POST['booking_duration'] ?? 60);
    if (!in_array($duration, array(30, 45, 60, 90, 120))) {
        $duration = 60;
    }
    update_post_meta($expert_id, '_rfm_booking_duration', $duration);

    // Save availability schedule
    if (isset($_POST['availability']) && is_array($_POST['availability'])) {
        $schedule = array();
        foreach ($_POST['availability'] as $entry) {
            $day = intval($entry['day_of_week'] ?? 0);
            $start = sanitize_text_field($entry['start_time'] ?? '');
            $end = sanitize_text_field($entry['end_time'] ?? '');
            $active = !empty($entry['is_active']) ? 1 : 0;

            if ($day >= 1 && $day <= 7 && !empty($start) && !empty($end)) {
                $schedule[] = array(
                    'day_of_week' => $day,
                    'start_time'  => $start,
                    'end_time'    => $end,
                    'is_active'   => $active,
                );
            }
        }
        RFM_Availability::get_instance()->save_availability($expert_id, $schedule);
    }

    // If mode is internal, also enable booking flag
    if ($mode === 'internal') {
        update_post_meta($expert_id, '_rfm_booking_enabled', '1');
    }

    wp_send_json_success(array('message' => 'Booking-indstillinger gemt!'));
    exit;
}

/**
 * Get available time slots for a given expert and date
 *
 * @since 3.10.0
 */
function rfm_direct_get_available_slots() {
    ob_end_clean();

    $expert_id = intval($_GET['expert_id'] ?? $_POST['expert_id'] ?? 0);
    $date = sanitize_text_field($_GET['date'] ?? $_POST['date'] ?? '');

    if (!$expert_id || !$date) {
        wp_send_json_error(array('message' => 'Manglende data.'), 400);
        exit;
    }

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(array('message' => 'Ugyldigt datoformat.'), 400);
        exit;
    }

    // Don't allow past dates
    if ($date < current_time('Y-m-d')) {
        wp_send_json_success(array('slots' => array()));
        exit;
    }

    $duration = (int) get_post_meta($expert_id, '_rfm_booking_duration', true);
    if (!$duration) $duration = 60;

    $slots = RFM_Availability::get_instance()->get_available_slots($expert_id, $date, $duration);

    wp_send_json_success(array('slots' => $slots, 'duration' => $duration));
    exit;
}

/**
 * Get available days of week for an expert
 *
 * @since 3.10.0
 */
function rfm_direct_get_available_days() {
    ob_end_clean();

    $expert_id = intval($_GET['expert_id'] ?? $_POST['expert_id'] ?? 0);

    if (!$expert_id) {
        wp_send_json_error(array('message' => 'Manglende data.'), 400);
        exit;
    }

    $days = RFM_Availability::get_instance()->get_available_days($expert_id);

    wp_send_json_success(array('days' => $days));
    exit;
}

/**
 * Create a new booking
 *
 * @since 3.10.0
 */
function rfm_direct_create_booking() {
    ob_end_clean();

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du skal være logget ind for at booke.'), 401);
        exit;
    }

    // Verify nonce
    $nonce = sanitize_text_field($_POST['nonce'] ?? '');
    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_booking_nonce')) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    $user_id = get_current_user_id();
    $expert_id = intval($_POST['expert_id'] ?? 0);
    $date = sanitize_text_field($_POST['booking_date'] ?? '');
    $time = sanitize_text_field($_POST['booking_time'] ?? '');
    $duration = intval($_POST['duration'] ?? 60);
    $note = sanitize_textarea_field($_POST['note'] ?? '');

    if (!$expert_id || !$date || !$time) {
        wp_send_json_error(array('message' => 'Manglende booking-data.'), 400);
        exit;
    }

    // Prevent booking own profile
    $post = get_post($expert_id);
    if ($post && $post->post_author == $user_id) {
        wp_send_json_error(array('message' => 'Du kan ikke booke din egen profil.'), 400);
        exit;
    }

    $booking_id = RFM_Booking::get_instance()->create_booking(
        $expert_id, $user_id, $date, $time, $duration, $note
    );

    if ($booking_id) {
        wp_send_json_success(array(
            'message' => 'Din booking-anmodning er sendt! Du får besked når eksperten svarer.',
            'booking_id' => $booking_id,
        ));
    } else {
        wp_send_json_error(array(
            'message' => 'Kunne ikke oprette booking. Tidspunktet er muligvis ikke tilgængeligt.'
        ));
    }
    exit;
}

/**
 * Update booking status (expert confirms/cancels)
 *
 * @since 3.10.0
 */
function rfm_direct_update_booking_status() {
    ob_end_clean();

    $nonce = sanitize_text_field($_POST['nonce'] ?? $_POST['rfm_tabbed_nonce'] ?? '');
    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_dashboard_tabbed')) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du skal være logget ind.'), 401);
        exit;
    }

    $user_id = get_current_user_id();
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');
    $expert_note = sanitize_textarea_field($_POST['expert_note'] ?? '');

    if (!$booking_id || !$status) {
        wp_send_json_error(array('message' => 'Manglende data.'), 400);
        exit;
    }

    // Get booking and verify expert ownership
    $booking = RFM_Booking::get_instance()->get_booking($booking_id);
    if (!$booking) {
        wp_send_json_error(array('message' => 'Booking ikke fundet.'), 404);
        exit;
    }

    $post = get_post($booking->expert_id);
    if (!$post || $post->post_author != $user_id) {
        wp_send_json_error(array('message' => 'Du har ikke tilladelse.'), 403);
        exit;
    }

    $result = RFM_Booking::get_instance()->update_status($booking_id, $status, $expert_note);

    if ($result) {
        $status_label = RFM_Booking::get_instance()->get_status_label($status);
        wp_send_json_success(array(
            'message' => sprintf('Booking opdateret til: %s', $status_label),
        ));
    } else {
        wp_send_json_error(array('message' => 'Kunne ikke opdatere booking.'));
    }
    exit;
}

/**
 * Cancel a booking (user cancels their own)
 *
 * @since 3.10.0
 */
function rfm_direct_cancel_user_booking() {
    ob_end_clean();

    $nonce = sanitize_text_field($_POST['nonce'] ?? '');
    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_user_dashboard')) {
        wp_send_json_error(array('message' => 'Sikkerhedstjek fejlede.'), 403);
        exit;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du skal være logget ind.'), 401);
        exit;
    }

    $user_id = get_current_user_id();
    $booking_id = intval($_POST['booking_id'] ?? 0);

    if (!$booking_id) {
        wp_send_json_error(array('message' => 'Manglende data.'), 400);
        exit;
    }

    $booking = RFM_Booking::get_instance()->get_booking($booking_id);
    if (!$booking || $booking->user_id != $user_id) {
        wp_send_json_error(array('message' => 'Du har ikke tilladelse.'), 403);
        exit;
    }

    if ($booking->status !== 'pending') {
        wp_send_json_error(array('message' => 'Kun ventende bookinger kan annulleres.'), 400);
        exit;
    }

    $result = RFM_Booking::get_instance()->update_status($booking_id, 'cancelled');

    if ($result) {
        wp_send_json_success(array('message' => 'Booking annulleret.'));
    } else {
        wp_send_json_error(array('message' => 'Kunne ikke annullere booking.'));
    }
    exit;
}
