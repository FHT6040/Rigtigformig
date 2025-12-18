<?php
/**
 * Expert Authentication Management
 *
 * Handles expert login, logout, redirects, and UI customization
 *
 * @package Rigtig_For_Mig
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Expert_Authentication {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Shortcodes
        add_shortcode('rfm_expert_login', array($this, 'login_form_shortcode'));

        // AJAX handlers
        add_action('wp_ajax_rfm_expert_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_rfm_expert_login', array($this, 'handle_login'));
        add_action('wp_ajax_rfm_expert_logout', array($this, 'handle_logout'));

        // Login/Logout redirects
        add_filter('login_redirect', array($this, 'expert_login_redirect'), 10, 3);
        add_filter('logout_redirect', array($this, 'expert_logout_redirect'), 10, 3);
        add_action('login_init', array($this, 'redirect_experts_from_wp_login'));

        // Hide admin bar for experts
        add_action('after_setup_theme', array($this, 'hide_admin_bar_for_experts'));
        add_filter('show_admin_bar', array($this, 'hide_admin_bar_filter'));
        add_action('init', array($this, 'remove_admin_bar_for_experts'), 9);

        // Add body class for experts
        add_filter('body_class', array($this, 'add_expert_body_class'));

        // Block admin access for experts
        add_action('admin_init', array($this, 'block_admin_access_for_experts'));

        // Enqueue authentication scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue authentication scripts
     */
    public function enqueue_scripts() {
        // Only load on frontend
        if (is_admin()) {
            return;
        }

        wp_enqueue_script(
            'rfm-expert-authentication',
            RFM_PLUGIN_URL . 'assets/js/expert-authentication.js',
            array('jquery'),
            RFM_VERSION,
            true
        );

        wp_localize_script('rfm-expert-authentication', 'rfmAuth', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rfm_auth'),
            'strings' => array(
                'loggingIn' => __('Logger ind...', 'rigtig-for-mig'),
                'login' => __('Log ind', 'rigtig-for-mig'),
                'error' => __('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig')
            )
        ));
    }

    /**
     * Expert login form shortcode
     */
    public function login_form_shortcode($atts) {
        if (is_user_logged_in()) {
            return '<p>' . __('Du er allerede logget ind.', 'rigtig-for-mig') . ' <a href="' . esc_url(home_url('/ekspert-dashboard/')) . '">' . __('Gå til dit dashboard', 'rigtig-for-mig') . '</a></p>';
        }

        ob_start();
        ?>
        <div class="rfm-login-form">
            <h2><?php _e('Ekspert Login', 'rigtig-for-mig'); ?></h2>

            <form id="rfm-expert-login-form" method="post">
                <?php wp_nonce_field('rfm_login', 'rfm_login_nonce'); ?>

                <p class="rfm-form-field">
                    <label for="login_email"><?php _e('Email', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                    <input type="email" name="email" id="login_email" required />
                </p>

                <p class="rfm-form-field">
                    <label for="login_password"><?php _e('Adgangskode', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                    <input type="password" name="password" id="login_password" required />
                </p>

                <p class="rfm-form-field">
                    <label>
                        <input type="checkbox" name="remember" value="1" />
                        <?php _e('Husk mig', 'rigtig-for-mig'); ?>
                    </label>
                </p>

                <div id="rfm-login-message"></div>

                <p class="rfm-form-submit">
                    <button type="submit" class="rfm-btn rfm-btn-primary"><?php _e('Log ind', 'rigtig-for-mig'); ?></button>
                </p>

                <p class="rfm-form-links">
                    <a href="<?php echo home_url('/glemt-adgangskode/'); ?>"><?php _e('Glemt adgangskode?', 'rigtig-for-mig'); ?></a>
                    <span class="sep">|</span>
                    <a href="<?php echo home_url('/opret-ekspert-profil/'); ?>"><?php _e('Opret ny profil', 'rigtig-for-mig'); ?></a>
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle login submission via AJAX
     */
    public function handle_login() {
        check_ajax_referer('rfm_login', 'rfm_login_nonce');

        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);

        if (empty($email) || empty($password)) {
            wp_send_json_error(array('message' => __('Email og adgangskode er påkrævet.', 'rigtig-for-mig')));
        }

        // Check if user exists first
        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error(array('message' => __('Ingen bruger fundet med denne email. Har du oprettet en profil?', 'rigtig-for-mig')));
        }

        // Try to authenticate
        $creds = array(
            'user_login'    => $email,
            'user_password' => $password,
            'remember'      => $remember
        );

        $signed_in = wp_signon($creds, false);

        if (is_wp_error($signed_in)) {
            wp_send_json_error(array('message' => __('Forkert adgangskode. Prøv igen eller klik "Glemt adgangskode".', 'rigtig-for-mig')));
        }

        // Check if user is an expert
        if (!in_array('rfm_expert_user', $signed_in->roles) && !in_array('administrator', $signed_in->roles)) {
            wp_logout();
            wp_send_json_error(array('message' => __('Din bruger er ikke en ekspert. Kontakt administrator for at få tildelt ekspert rolle.', 'rigtig-for-mig')));
        }

        wp_send_json_success(array(
            'message' => __('Login succesfuld! Omdirigerer...', 'rigtig-for-mig'),
            'redirect' => home_url('/ekspert-dashboard/')
        ));
    }

    /**
     * Handle AJAX logout
     */
    public function handle_logout() {
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du er ikke logget ind.', 'rigtig-for-mig')));
        }

        // Get current user before logout
        $user = wp_get_current_user();
        $is_expert = in_array('rfm_expert_user', (array) $user->roles);

        // Perform logout
        wp_logout();

        // Send success response
        wp_send_json_success(array(
            'message' => __('Du er nu logget ud.', 'rigtig-for-mig'),
            'redirect' => $is_expert ? home_url('/login/') : home_url()
        ));
    }

    /**
     * Custom login redirect for experts
     */
    public function expert_login_redirect($redirect_to, $request, $user) {
        if (isset($user->roles) && in_array('rfm_expert_user', $user->roles)) {
            return home_url('/ekspert-dashboard/');
        }
        return $redirect_to;
    }

    /**
     * Custom logout redirect for experts
     */
    public function expert_logout_redirect($redirect_to, $requested_redirect_to, $user) {
        if (isset($user->roles) && in_array('rfm_expert_user', (array) $user->roles)) {
            return home_url('/login/');
        }
        return $redirect_to;
    }

    /**
     * Redirect experts away from wp-login.php to frontend login
     */
    public function redirect_experts_from_wp_login() {
        // Only redirect if user is logged in and is an expert
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_expert_user', (array) $user->roles)) {
                // If they're trying to access wp-login.php while logged in, send them to dashboard
                wp_redirect(home_url('/ekspert-dashboard/'));
                exit;
            }
        }
    }

    /**
     * Hide admin bar for expert users
     */
    public function hide_admin_bar_for_experts() {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_expert_user', (array) $user->roles)) {
                show_admin_bar(false);
            }
        }
    }

    /**
     * Filter to hide admin bar for expert users
     */
    public function hide_admin_bar_filter($show_admin_bar) {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_expert_user', (array) $user->roles)) {
                return false;
            }
        }
        return $show_admin_bar;
    }

    /**
     * Remove admin bar completely for expert users
     */
    public function remove_admin_bar_for_experts() {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_expert_user', (array) $user->roles)) {
                add_filter('show_admin_bar', '__return_false');
                remove_action('wp_head', '_admin_bar_bump_cb');
            }
        }
    }

    /**
     * Add body class for expert users
     */
    public function add_expert_body_class($classes) {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_expert_user', (array) $user->roles)) {
                $classes[] = 'rfm-expert-user';
            }
        }
        return $classes;
    }

    /**
     * Block admin access for expert users
     */
    public function block_admin_access_for_experts() {
        // Allow AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (is_user_logged_in()) {
            $user = wp_get_current_user();

            // Experts (non-admins) should not access admin area
            if (in_array('rfm_expert_user', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
                wp_redirect(home_url('/ekspert-dashboard/'));
                exit;
            }
        }
    }
}
