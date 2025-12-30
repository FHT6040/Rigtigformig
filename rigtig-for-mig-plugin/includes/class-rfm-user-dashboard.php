<?php
/**
 * User Dashboard Management
 *
 * v3.7.0 - Complete clean rebuild following Expert Dashboard pattern
 *
 * Minimal, focused implementation:
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
        add_action('wp_ajax_rfm_user_logout', array($this, 'handle_logout'));
    }

    /**
     * Enqueue dashboard scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on frontend (not admin)
        if (is_admin()) {
            return;
        }

        // Enqueue dashboard script globally on all frontend pages
        // This matches the approach used by Expert Dashboard
        wp_enqueue_script(
            'rfm-user-dashboard',
            RFM_PLUGIN_URL . 'assets/js/user-dashboard.js',
            array('jquery'),
            RFM_VERSION,
            true
        );

        // Localize script with translations and data
        // Same pattern as Expert Dashboard
        wp_localize_script('rfm-user-dashboard', 'rfmUserDashboard', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'strings' => array(
                'savingText' => __('Gemmer...', 'rigtig-for-mig'),
                'submitText' => __('Gem ændringer', 'rigtig-for-mig'),
                'errorText' => __('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig'),
                'loggingOut' => __('Logger ud...', 'rigtig-for-mig')
            )
        ));
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
     * @since 3.7.0
     */
    public function handle_profile_update() {
        check_ajax_referer('rfm_user_dashboard', 'rfm_user_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind.', 'rigtig-for-mig')));
        }

        $user_id = get_current_user_id();

        // Sanitize and validate input
        $display_name = sanitize_text_field($_POST['display_name']);
        $phone = sanitize_text_field($_POST['phone']);
        $bio = sanitize_textarea_field($_POST['bio']);

        if (empty($display_name)) {
            wp_send_json_error(array('message' => __('Visningsnavn er påkrævet.', 'rigtig-for-mig')));
        }

        // Update WordPress user
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $display_name
        ));

        // Update user meta
        update_user_meta($user_id, '_rfm_phone', $phone);
        update_user_meta($user_id, '_rfm_bio', $bio);

        // Update last login timestamp
        update_user_meta($user_id, '_rfm_last_login', current_time('mysql'));

        wp_send_json_success(array(
            'message' => __('✅ Din profil er opdateret!', 'rigtig-for-mig')
        ));
    }

    /**
     * Handle User Logout
     *
     * Processes AJAX logout requests.
     *
     * @since 3.7.0
     */
    public function handle_logout() {
        check_ajax_referer('rfm_user_dashboard', 'rfm_user_nonce');

        wp_logout();

        wp_send_json_success(array(
            'message' => __('Du er nu logget ud', 'rigtig-for-mig'),
            'redirect' => home_url()
        ));
    }
}
