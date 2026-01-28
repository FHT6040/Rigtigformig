<?php
/**
 * User Dashboard Management
 *
 * v3.7.2 - LiteSpeed Cache compatibility improvements
 * - Added LiteSpeed-specific no-cache headers
 * - Enhanced cache-busting with user ID and timestamp
 * - Centralized cache prevention for AJAX handlers
 *
 * v3.7.0 - Complete clean rebuild following Expert Dashboard pattern
 * - Profile updates only (name, phone, bio)
 * - Clean AJAX handler with proper nonce verification
 * - Follows WordPress best practices
 * - Uses WordPress native user_meta (no custom tables)
 *
 * @package Rigtig_For_Mig
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_User_Dashboard {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register shortcodes
        add_shortcode('rfm_user_dashboard', array($this, 'dashboard_shortcode'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handlers
        add_action('wp_ajax_rfm_update_user_profile', array($this, 'handle_profile_update'));
        add_action('wp_ajax_rfm_upload_user_avatar', array($this, 'handle_avatar_upload'));
        add_action('wp_ajax_rfm_user_logout', array($this, 'handle_logout'));
    }

    /**
     * Send LiteSpeed-specific no-cache headers for AJAX responses
     *
     * This prevents LiteSpeed Cache from caching AJAX responses even when
     * standard no-cache headers are present.
     *
     * @since 3.7.2
     */
    private function send_litespeed_nocache_headers() {
        // Prevent headers already sent errors
        if (headers_sent()) {
            return;
        }

        // Standard WordPress no-cache
        nocache_headers();

        // LiteSpeed-specific cache control headers
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('X-LiteSpeed-Tag: rfm-ajax,rfm-no-cache');
        header('X-LiteSpeed-Vary: cookie');

        // Comprehensive cache prevention
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Type: application/json; charset=utf-8');

        // Vary header for logged-in users (prevents serving cached responses)
        if (is_user_logged_in()) {
            header('Vary: Cookie');
        }
    }

    /**
     * Enqueue dashboard scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on frontend (not admin)
        if (is_admin()) {
            return;
        }

        // ENHANCED CACHE BUSTING: Version + timestamp + user ID
        // This ensures LiteSpeed Cache serves the correct file per user
        $script_path = RFM_PLUGIN_DIR . 'assets/js/user-dashboard.js';
        $file_time = file_exists($script_path) ? filemtime($script_path) : time();
        $user_id = is_user_logged_in() ? get_current_user_id() : '0';
        $script_version = RFM_VERSION . '.' . $file_time . '.' . $user_id;

        // Enqueue dashboard script globally on all frontend pages
        wp_enqueue_script(
            'rfm-user-dashboard',
            RFM_PLUGIN_URL . 'assets/js/user-dashboard.js',
            array('jquery'),
            $script_version,  // Enhanced cache-busting version
            true
        );

        // Generate fresh nonce on every page load
        $nonce = wp_create_nonce('rfm_user_dashboard');

        // Additional cache-buster for AJAX requests
        $cache_buster = wp_create_nonce('rfm_script_' . $file_time);

        // Localize script with translations and data
        wp_localize_script('rfm-user-dashboard', 'rfmUserDashboard', array(
            'ajaxurl' => RFM_PLUGIN_URL . 'ajax-handler.php',  // Direct AJAX handler to avoid redirects
            'nonce' => $nonce,  // Fresh nonce
            'cache_buster' => $cache_buster,  // NEW: Cache-buster for AJAX requests
            'timestamp' => time(),  // NEW: Current timestamp
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'version' => RFM_VERSION,
            'currentUserId' => get_current_user_id(),
            'strings' => array(
                'savingText' => __('Gemmer...', 'rigtig-for-mig'),
                'submitText' => __('Gem ændringer', 'rigtig-for-mig'),
                'errorText' => __('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig'),
                'loggingOut' => __('Logger ud...', 'rigtig-for-mig')
            )
        ));

        // Add anti-cache headers for AJAX requests
        add_action('wp_head', array($this, 'add_nocache_headers'), 1);
    }

    /**
     * Add no-cache headers to prevent LiteSpeed from caching pages with the dashboard
     *
     * @since 3.7.2 - Added LiteSpeed-specific meta tags
     */
    public function add_nocache_headers() {
        if (!is_user_logged_in()) {
            return;
        }

        echo "<!-- RFM User Dashboard v" . RFM_VERSION . " - Cache Bypass Active (LiteSpeed Compatible) -->\n";

        // Standard no-cache meta tags
        echo '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate, private" />' . "\n";
        echo '<meta http-equiv="Pragma" content="no-cache" />' . "\n";
        echo '<meta http-equiv="Expires" content="0" />' . "\n";

        // LiteSpeed-specific meta tags
        echo '<meta http-equiv="X-LiteSpeed-Cache-Control" content="no-cache" />' . "\n";
        echo '<meta http-equiv="X-LiteSpeed-Vary" content="cookie" />' . "\n";
    }

    /**
     * Dashboard Shortcode
     *
     * Displays user dashboard with simple profile management.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output of the dashboard
     */
    public function dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="rfm-message rfm-message-warning">' .
                   __('Du skal være logget ind for at se denne side.', 'rigtig-for-mig') .
                   ' <a href="' . wp_login_url(get_permalink()) . '">' .
                   __('Log ind her', 'rigtig-for-mig') . '</a></div>';
        }

        $user = wp_get_current_user();

        // Get user profile data from user_meta
        $phone = get_user_meta($user->ID, '_rfm_phone', true);
        $bio = get_user_meta($user->ID, '_rfm_bio', true);
        $avatar_id = get_user_meta($user->ID, '_rfm_avatar_id', true);

        ob_start();
        ?>
        <div class="rfm-user-dashboard">
            <div class="rfm-dashboard-header">
                <h2><?php printf(__('Velkommen, %s', 'rigtig-for-mig'), esc_html($user->display_name)); ?></h2>
                <button id="rfm-user-logout-btn" class="rfm-btn rfm-btn-secondary">
                    <?php _e('Log ud', 'rigtig-for-mig'); ?>
                </button>
            </div>

            <!-- Global Message Area -->
            <div id="rfm-user-dashboard-message"></div>

            <!-- Profile Section -->
            <div class="rfm-dashboard-section">
                <h3><?php _e('Min Profil', 'rigtig-for-mig'); ?></h3>

                <form id="rfm-user-profile-form" method="post">
                    <?php wp_nonce_field('rfm_user_dashboard', 'rfm_user_nonce'); ?>

                    <!-- Avatar Upload -->
                    <div class="rfm-form-field">
                        <label><?php _e('Profilbillede', 'rigtig-for-mig'); ?></label>
                        <input type="hidden" id="user_avatar_id" name="avatar_id" value="<?php echo esc_attr($avatar_id); ?>" />

                        <div class="rfm-avatar-preview <?php echo !empty($avatar_id) ? 'has-avatar' : ''; ?>">
                            <?php if (!empty($avatar_id)): ?>
                                <?php echo wp_get_attachment_image($avatar_id, 'thumbnail', false, array('class' => 'rfm-user-avatar')); ?>
                            <?php else: ?>
                                <div class="rfm-avatar-placeholder">
                                    <span class="dashicons dashicons-admin-users"></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="rfm-avatar-buttons">
                            <button type="button" id="rfm-upload-avatar-btn" class="rfm-btn rfm-btn-small">
                                <?php echo !empty($avatar_id) ? __('Skift billede', 'rigtig-for-mig') : __('Upload billede', 'rigtig-for-mig'); ?>
                            </button>
                            <?php if (!empty($avatar_id)): ?>
                            <button type="button" id="rfm-remove-avatar-btn" class="rfm-btn rfm-btn-small rfm-btn-danger">
                                <?php _e('Fjern', 'rigtig-for-mig'); ?>
                            </button>
                            <?php endif; ?>
                        </div>

                        <input type="file" id="rfm-avatar-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;" />
                        <small class="rfm-field-note">
                            <?php _e('Upload et profilbillede (maks 5MB). Anbefalet: JPG, PNG eller WebP', 'rigtig-for-mig'); ?>
                        </small>
                    </div>

                    <div class="rfm-form-field">
                        <label for="user_display_name">
                            <?php _e('Visningsnavn', 'rigtig-for-mig'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="text"
                               id="user_display_name"
                               name="display_name"
                               value="<?php echo esc_attr($user->display_name); ?>"
                               required />
                        <small class="rfm-field-note">
                            <?php _e('Dette navn vises for eksperter', 'rigtig-for-mig'); ?>
                        </small>
                    </div>

                    <div class="rfm-form-field">
                        <label for="user_email">
                            <?php _e('E-mail', 'rigtig-for-mig'); ?>
                        </label>
                        <input type="email"
                               id="user_email"
                               value="<?php echo esc_attr($user->user_email); ?>"
                               readonly />
                        <small class="rfm-field-note">
                            <?php _e('Kontakt admin for at ændre e-mail', 'rigtig-for-mig'); ?>
                        </small>
                    </div>

                    <div class="rfm-form-field">
                        <label for="user_phone">
                            <?php _e('Telefon', 'rigtig-for-mig'); ?>
                        </label>
                        <input type="tel"
                               id="user_phone"
                               name="phone"
                               value="<?php echo esc_attr($phone); ?>" />
                        <small class="rfm-field-note">
                            <?php _e('Valgfrit - kun synligt for dig og admin', 'rigtig-for-mig'); ?>
                        </small>
                    </div>

                    <div class="rfm-form-field">
                        <label for="user_bio">
                            <?php _e('Om mig', 'rigtig-for-mig'); ?>
                        </label>
                        <textarea id="user_bio"
                                  name="bio"
                                  rows="5"><?php echo esc_textarea($bio); ?></textarea>
                        <small class="rfm-field-note">
                            <?php _e('Valgfrit - synligt for eksperter', 'rigtig-for-mig'); ?>
                        </small>
                    </div>

                    <div class="rfm-form-submit">
                        <button type="submit" class="rfm-btn rfm-btn-primary rfm-btn-large">
                            <?php _e('Gem ændringer', 'rigtig-for-mig'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bookings Section -->
            <div class="rfm-dashboard-section rfm-bookings-section">
                <?php echo RFM_Booking::get_instance()->render_user_bookings($user->ID); ?>
            </div>

            <!-- Messages Section -->
            <div class="rfm-dashboard-section rfm-messages-section">
                <h3>
                    <?php _e('Mine Beskeder', 'rigtig-for-mig'); ?>
                    <span class="rfm-unread-count" id="rfm-unread-count" style="display: none;"></span>
                </h3>

                <div class="rfm-messages-actions" style="margin-bottom: 15px;">
                    <button id="rfm-mark-all-read-btn" class="rfm-btn rfm-btn-secondary" style="display: none;">
                        <?php _e('Marker alle som læst', 'rigtig-for-mig'); ?>
                    </button>
                </div>

                <div class="rfm-messages-container">
                    <div class="rfm-messages-loading">
                        <?php _e('Indlæser beskeder...', 'rigtig-for-mig'); ?>
                    </div>
                    <div id="rfm-conversations-list" class="rfm-conversations-list"></div>
                </div>
            </div>

            <!-- Message Thread Modal -->
            <div id="rfm-thread-modal" class="rfm-modal" style="display: none;">
                <div class="rfm-modal-content rfm-thread-modal-content">
                    <div class="rfm-modal-header">
                        <h3 id="rfm-thread-title"></h3>
                        <span class="rfm-modal-close">&times;</span>
                    </div>
                    <div class="rfm-thread-messages" id="rfm-thread-messages"></div>
                    <form id="rfm-reply-form" class="rfm-reply-form">
                        <textarea id="rfm-reply-text"
                                  name="message"
                                  class="rfm-form-control"
                                  rows="3"
                                  placeholder="<?php esc_attr_e('Skriv dit svar...', 'rigtig-for-mig'); ?>"
                                  required></textarea>
                        <button type="submit" class="rfm-btn rfm-btn-primary">
                            <?php _e('Send svar', 'rigtig-for-mig'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle Profile Update
     *
     * Processes AJAX requests to update user profile information
     * including display name, phone, and bio.
     *
     * Security: Verifies nonce and user login status.
     *
     * @since 3.7.2 - Added LiteSpeed-specific no-cache headers
     * @since 3.7.0
     */
    public function handle_profile_update() {
        // Send LiteSpeed-specific no-cache headers FIRST
        $this->send_litespeed_nocache_headers();

        // COMPREHENSIVE ERROR LOGGING
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RFM User Dashboard: Profile update request received');
            error_log('RFM User Dashboard: POST data = ' . print_r($_POST, true));
            error_log('RFM User Dashboard: User logged in = ' . (is_user_logged_in() ? 'yes' : 'no'));
            error_log('RFM User Dashboard: User ID = ' . get_current_user_id());
        }

        // Verify nonce
        try {
            check_ajax_referer('rfm_user_dashboard', 'rfm_user_nonce');
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RFM User Dashboard: Nonce verification FAILED - ' . $e->getMessage());
            }
            wp_send_json_error(array(
                'message' => __('Sikkerhedstjek fejlede. Genindlæs siden og prøv igen.', 'rigtig-for-mig'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG ? 'Nonce verification failed' : null
            ), 403);
        }

        if (!is_user_logged_in()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RFM User Dashboard: User not logged in');
            }
            wp_send_json_error(array(
                'message' => __('Du skal være logget ind.', 'rigtig-for-mig'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG ? 'User not logged in' : null
            ), 401);
        }

        $user_id = get_current_user_id();

        // Sanitize and validate input
        $display_name = isset($_POST['display_name']) ? sanitize_text_field($_POST['display_name']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $bio = isset($_POST['bio']) ? sanitize_textarea_field($_POST['bio']) : '';

        if (empty($display_name)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RFM User Dashboard: Display name is empty');
            }
            wp_send_json_error(array('message' => __('Visningsnavn er påkrævet.', 'rigtig-for-mig')));
        }

        // Update WordPress user
        $update_result = wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $display_name
        ));

        if (is_wp_error($update_result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RFM User Dashboard: wp_update_user failed - ' . $update_result->get_error_message());
            }
            wp_send_json_error(array(
                'message' => __('Kunne ikke opdatere profil.', 'rigtig-for-mig'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG ? $update_result->get_error_message() : null
            ));
        }

        // Update user meta
        update_user_meta($user_id, '_rfm_phone', $phone);
        update_user_meta($user_id, '_rfm_bio', $bio);

        // Update avatar if provided
        if (isset($_POST['avatar_id'])) {
            $avatar_id = absint($_POST['avatar_id']);
            update_user_meta($user_id, '_rfm_avatar_id', $avatar_id);
        }

        // Update last login timestamp
        update_user_meta($user_id, '_rfm_last_login', current_time('mysql'));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RFM User Dashboard: Profile updated successfully for user ' . $user_id);
        }

        wp_send_json_success(array(
            'message' => __('✅ Din profil er opdateret!', 'rigtig-for-mig'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG ? array(
                'user_id' => $user_id,
                'display_name' => $display_name,
                'timestamp' => current_time('mysql')
            ) : null
        ));
    }

    /**
     * Handle Avatar Upload via AJAX
     *
     * Processes image uploads for user avatars.
     *
     * Validates:
     * - User authentication
     * - File type (JPG, PNG, GIF, WebP only)
     * - File size (max 5MB)
     *
     * @since 3.7.4
     */
    public function handle_avatar_upload() {
        // Send LiteSpeed-specific no-cache headers FIRST
        $this->send_litespeed_nocache_headers();

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RFM: Avatar upload request received');
            error_log('RFM: POST data: ' . print_r($_POST, true));
            error_log('RFM: FILES data: ' . print_r($_FILES, true));
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rfm_user_dashboard')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RFM: Nonce verification failed');
            }
            wp_send_json_error(array('message' => __('Sikkerhedstjek fejlede. Prøv igen.', 'rigtig-for-mig')));
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind.', 'rigtig-for-mig')));
        }

        $user_id = get_current_user_id();

        // Check if file was uploaded
        if (empty($_FILES['avatar_image'])) {
            wp_send_json_error(array('message' => __('Ingen fil blev uploadet.', 'rigtig-for-mig')));
        }

        $file = $_FILES['avatar_image'];

        // Validate file type - SECURITY: Check actual file content, not just browser-supplied MIME type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');

        // Check actual MIME type from file content (more secure than $_FILES['type'])
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($real_mime, $allowed_types)) {
            wp_send_json_error(array('message' => __('Ugyldig filtype. Kun billeder (JPG, PNG, GIF, WebP) er tilladt.', 'rigtig-for-mig')));
        }

        // Additional security: Validate file extension
        $filename = sanitize_file_name($file['name']);
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed_exts = array('jpg', 'jpeg', 'png', 'gif', 'webp');

        if (!in_array($file_ext, $allowed_exts)) {
            wp_send_json_error(array('message' => __('Ugyldig fil-extension. Kun .jpg, .png, .gif og .webp er tilladt.', 'rigtig-for-mig')));
        }

        // Validate file size (max 5MB)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($file['size'] > $max_size) {
            wp_send_json_error(array('message' => __('Filen er for stor. Maksimum 5MB.', 'rigtig-for-mig')));
        }

        // Include WordPress media functions
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Upload the file
        $attachment_id = media_handle_upload('avatar_image', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        // Update user meta
        update_user_meta($user_id, '_rfm_avatar_id', $attachment_id);

        // Get the image URL
        $image_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
        $image_html = wp_get_attachment_image($attachment_id, 'thumbnail', false, array('class' => 'rfm-user-avatar'));

        wp_send_json_success(array(
            'message' => __('Profilbillede uploadet!', 'rigtig-for-mig'),
            'attachment_id' => $attachment_id,
            'image_url' => $image_url,
            'image_html' => $image_html
        ));
    }

    /**
     * Handle User Logout
     *
     * Processes AJAX logout requests.
     *
     * @since 3.7.2 - Added LiteSpeed-specific no-cache headers
     * @since 3.7.0
     */
    public function handle_logout() {
        // Send LiteSpeed-specific no-cache headers FIRST
        $this->send_litespeed_nocache_headers();

        // COMPREHENSIVE ERROR LOGGING
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RFM User Dashboard: Logout request received');
            error_log('RFM User Dashboard: User ID = ' . get_current_user_id());
        }

        // Verify nonce
        try {
            check_ajax_referer('rfm_user_dashboard', 'rfm_user_nonce');
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RFM User Dashboard: Logout nonce verification FAILED - ' . $e->getMessage());
            }
            wp_send_json_error(array(
                'message' => __('Sikkerhedstjek fejlede.', 'rigtig-for-mig'),
                'redirect' => home_url(),
                'debug' => defined('WP_DEBUG') && WP_DEBUG ? 'Nonce verification failed' : null
            ), 403);
        }

        wp_logout();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RFM User Dashboard: User logged out successfully');
        }

        wp_send_json_success(array(
            'message' => __('Du er nu logget ud', 'rigtig-for-mig'),
            'redirect' => home_url()
        ));
    }
}
