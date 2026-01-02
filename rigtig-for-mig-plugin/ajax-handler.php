<?php
/**
 * Direct AJAX Handler for RFM User Dashboard
 *
 * This bypasses admin-ajax.php to avoid redirect issues
 *
 * @since 3.7.3
 * @updated 3.7.5 - Added avatar upload support
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

    // Validate file type
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
    if (!in_array($file['type'], $allowed_types)) {
        wp_send_json_error(array('message' => 'Ugyldig filtype. Kun JPG, PNG, GIF og WebP er tilladt.'));
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
