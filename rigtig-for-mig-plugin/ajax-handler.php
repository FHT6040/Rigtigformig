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

    case 'rfm_unified_login':
        rfm_direct_unified_login();
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

/**
 * Handle expert general profile save
 *
 * @since 3.8.2
 */
function rfm_direct_save_general_profile() {
    ob_end_clean();

    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_dashboard_tabbed')) {
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

    if (isset($_POST['categories'])) {
        $categories = array_map('intval', $_POST['categories']);
        $categories = array_slice($categories, 0, $allowed_cats);
        wp_set_object_terms($expert_id, $categories, 'rfm_category');

        error_log("RFM: Saved categories for expert $expert_id: " . implode(', ', $categories));
    }

    wp_send_json_success(array(
        'message' => '✅ Generelle oplysninger gemt!'
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

    // Verify nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

    if (empty($nonce) || !wp_verify_nonce($nonce, 'rfm_dashboard_tabbed')) {
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
 * Handle unified login (for both users and experts)
 *
 * @since 3.8.3
 */
function rfm_direct_unified_login() {
    ob_end_clean();

    // Verify nonce
    $nonce = isset($_POST["nonce"]) ? sanitize_text_field($_POST["nonce"]) : "";

    if (empty($nonce) || \!wp_verify_nonce($nonce, "rfm_nonce")) {
        wp_send_json_error(array("message" => "Sikkerhedstjek fejlede."), 403);
        exit;
    }

    $identifier = isset($_POST["identifier"]) ? sanitize_text_field($_POST["identifier"]) : "";
    $password = isset($_POST["password"]) ? $_POST["password"] : "";
    $remember = isset($_POST["remember"]) ? true : false;

    // Validate
    if (empty($identifier) || empty($password)) {
        wp_send_json_error(array("message" => "Alle felter er påkrævede"));
        exit;
    }

    // Determine if identifier is email or username
    $user = null;
    if (is_email($identifier)) {
        $user = get_user_by("email", $identifier);
    } else {
        $user = get_user_by("login", $identifier);
    }

    if (\!$user) {
        wp_send_json_error(array("message" => "Ugyldigt brugernavn/e-mail eller adgangskode"));
        exit;
    }

    // Check password
    if (\!wp_check_password($password, $user->user_pass, $user->ID)) {
        wp_send_json_error(array("message" => "Ugyldigt brugernavn/e-mail eller adgangskode"));
        exit;
    }

    // Check if email is verified
    $verified = false;

    if (in_array("rfm_expert_user", $user->roles)) {
        // For experts: Check if they have an expert post and if it is verified
        $expert_posts = get_posts(array(
            "post_type" => "rfm_expert",
            "author" => $user->ID,
            "posts_per_page" => 1,
            "post_status" => "publish"
        ));

        if (\!empty($expert_posts)) {
            $verified = (bool) get_post_meta($expert_posts[0]->ID, "_rfm_email_verified", true);
        }
    } else {
        // For regular users: Check using unified migration helper
        if (class_exists("RFM_Migration")) {
            $verified = RFM_Migration::is_user_verified($user->ID);
        } else {
            // Fallback if class not available
            $verified = get_user_meta($user->ID, "_rfm_email_verified", true);
        }
    }

    if (\!$verified) {
        wp_send_json_error(array("message" => "Din e-mail er ikke bekræftet. Tjek din indbakke."));
        exit;
    }

    // Log user in
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, $remember);
    do_action("wp_login", $user->user_login, $user);

    // Update last login
    if (in_array("rfm_user", $user->roles)) {
        if (class_exists("RFM_Migration")) {
            RFM_Migration::update_last_login($user->ID);
        } else {
            update_user_meta($user->ID, "_rfm_last_login", current_time("mysql"));
        }
    }

    // Determine redirect based on role
    $redirect = home_url();
    if (in_array("rfm_expert_user", $user->roles)) {
        $redirect = home_url("/ekspert-dashboard");
    } elseif (in_array("rfm_user", $user->roles)) {
        $redirect = home_url("/bruger-dashboard");
    }

    error_log("RFM: User {$user->ID} logged in successfully. Redirect: {$redirect}");

    wp_send_json_success(array(
        "message" => "Du er nu logget ind\!",
        "redirect" => $redirect
    ));
    exit;
}

