<?php
/**
 * User Registration and Login Management
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_User_Registration {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Shortcodes
        add_shortcode('rfm_user_registration', array($this, 'registration_form_shortcode'));
        add_shortcode('rfm_login', array($this, 'unified_login_form_shortcode')); // Unified for both users and experts
        
        // AJAX handlers
        add_action('wp_ajax_rfm_submit_user_registration', array($this, 'handle_registration'));
        add_action('wp_ajax_nopriv_rfm_submit_user_registration', array($this, 'handle_registration'));
        
        add_action('wp_ajax_rfm_unified_login', array($this, 'handle_unified_login'));
        add_action('wp_ajax_nopriv_rfm_unified_login', array($this, 'handle_unified_login'));
        
        add_action('wp_ajax_rfm_logout', array($this, 'handle_logout'));
        
        // Add user role
        add_action('init', array($this, 'add_user_role'));
        
        // Custom login redirect
        add_filter('login_redirect', array($this, 'user_login_redirect'), 10, 3);
        
        // Hide admin bar for regular users
        add_action('after_setup_theme', array($this, 'hide_admin_bar_for_users'));
        add_filter('show_admin_bar', array($this, 'hide_admin_bar_filter'));
        
        // Block admin access for users
        add_action('admin_init', array($this, 'block_admin_access_for_users'));
    }
    
    /**
     * Create/ensure user role exists
     */
    public function add_user_role() {
        if (!get_role('rfm_user')) {
            add_role(
                'rfm_user',
                __('Bruger', 'rigtig-for-mig'),
                array(
                    'read' => true,
                )
            );
        }
    }
    
    /**
     * User registration form shortcode
     */
    public function registration_form_shortcode($atts) {
        // If already logged in, redirect to dashboard
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_user', $user->roles)) {
                return '<p>' . __('Du er allerede logget ind.', 'rigtig-for-mig') . ' <a href="' . home_url('/bruger-dashboard') . '">' . __('Gå til dashboard', 'rigtig-for-mig') . '</a></p>';
            }
        }
        
        ob_start();
        ?>
        <div class="rfm-user-registration-container">
            <div class="rfm-form-wrapper">
                <h2><?php _e('Opret brugerprofil', 'rigtig-for-mig'); ?></h2>
                <p class="rfm-form-description"><?php _e('Det er gratis at oprette en brugerprofil. For at kontakte eksperter skal du være logget ind.', 'rigtig-for-mig'); ?></p>
                
                <form id="rfm-user-registration-form" class="rfm-form">
                    <div class="rfm-form-group">
                        <label for="user_username"><?php _e('Brugernavn', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="text" id="user_username" name="username" required 
                               placeholder="<?php _e('Vælg et brugernavn', 'rigtig-for-mig'); ?>">
                        <small class="rfm-field-note"><?php _e('Dette navn vil være synligt for eksperter', 'rigtig-for-mig'); ?></small>
                    </div>
                    
                    <div class="rfm-form-group">
                        <label for="user_email"><?php _e('E-mail', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="email" id="user_email" name="email" required 
                               placeholder="<?php _e('Din e-mail adresse', 'rigtig-for-mig'); ?>">
                        <small class="rfm-field-note"><?php _e('Du vil modtage en bekræftelses-e-mail', 'rigtig-for-mig'); ?></small>
                    </div>
                    
                    <div class="rfm-form-group">
                        <label for="user_password"><?php _e('Adgangskode', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="password" id="user_password" name="password" required 
                               placeholder="<?php _e('Vælg en stærk adgangskode', 'rigtig-for-mig'); ?>">
                        <small class="rfm-field-note"><?php _e('Mindst 8 tegn', 'rigtig-for-mig'); ?></small>
                    </div>
                    
                    <div class="rfm-form-group">
                        <label for="user_password_confirm"><?php _e('Bekræft adgangskode', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="password" id="user_password_confirm" name="password_confirm" required 
                               placeholder="<?php _e('Gentag adgangskode', 'rigtig-for-mig'); ?>">
                    </div>
                    
                    <div class="rfm-form-group rfm-checkbox-group">
                        <label>
                            <input type="checkbox" id="user_gdpr_consent" name="gdpr_consent" required>
                            <?php _e('Jeg accepterer', 'rigtig-for-mig'); ?> 
                            <a href="<?php echo home_url('/privatlivspolitik'); ?>" target="_blank"><?php _e('privatlivspolitikken', 'rigtig-for-mig'); ?></a> 
                            <?php _e('og giver samtykke til behandling af mine personlige oplysninger', 'rigtig-for-mig'); ?> <span class="required">*</span>
                        </label>
                    </div>
                    
                    <div class="rfm-form-messages"></div>
                    
                    <button type="submit" class="rfm-btn rfm-btn-primary">
                        <?php _e('Opret profil', 'rigtig-for-mig'); ?>
                    </button>
                    
                    <p class="rfm-form-footer">
                        <?php _e('Har du allerede en profil?', 'rigtig-for-mig'); ?> 
                        <a href="<?php echo home_url('/login'); ?>"><?php _e('Log ind her', 'rigtig-for-mig'); ?></a>
                    </p>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#rfm-user-registration-form').on('submit', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $button = $form.find('button[type="submit"]');
                const $messages = $form.find('.rfm-form-messages');
                
                // Validate passwords match
                const password = $('#user_password').val();
                const passwordConfirm = $('#user_password_confirm').val();
                
                if (password !== passwordConfirm) {
                    $messages.html('<div class="rfm-message rfm-message-error"><?php _e('Adgangskoderne matcher ikke', 'rigtig-for-mig'); ?></div>');
                    return;
                }
                
                if (password.length < 8) {
                    $messages.html('<div class="rfm-message rfm-message-error"><?php _e('Adgangskoden skal være mindst 8 tegn', 'rigtig-for-mig'); ?></div>');
                    return;
                }
                
                if (!$('#user_gdpr_consent').is(':checked')) {
                    $messages.html('<div class="rfm-message rfm-message-error"><?php _e('Du skal acceptere privatlivspolitikken', 'rigtig-for-mig'); ?></div>');
                    return;
                }
                
                $button.prop('disabled', true).text('<?php _e('Opretter...', 'rigtig-for-mig'); ?>');
                $messages.html('');
                
                $.ajax({
                    url: rfmData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rfm_submit_user_registration',
                        nonce: rfmData.nonce,
                        username: $('#user_username').val(),
                        email: $('#user_email').val(),
                        password: password,
                        gdpr_consent: $('#user_gdpr_consent').is(':checked') ? 1 : 0
                    },
                    success: function(response) {
                        if (response.success) {
                            $messages.html('<div class="rfm-message rfm-message-success">' + response.data.message + '</div>');
                            $form[0].reset();
                            
                            // Redirect to verification page
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 2000);
                        } else {
                            $messages.html('<div class="rfm-message rfm-message-error">' + response.data.message + '</div>');
                            $button.prop('disabled', false).text('<?php _e('Opret profil', 'rigtig-for-mig'); ?>');
                        }
                    },
                    error: function() {
                        $messages.html('<div class="rfm-message rfm-message-error"><?php _e('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig'); ?></div>');
                        $button.prop('disabled', false).text('<?php _e('Opret profil', 'rigtig-for-mig'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Unified login form for both users and experts
     */
    public function unified_login_form_shortcode($atts) {
        // If already logged in, redirect based on role
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            
            if (in_array('rfm_expert_user', $user->roles)) {
                return '<p>' . __('Du er allerede logget ind som ekspert.', 'rigtig-for-mig') . ' <a href="' . home_url('/ekspert-dashboard') . '">' . __('Gå til dashboard', 'rigtig-for-mig') . '</a></p>';
            } elseif (in_array('rfm_user', $user->roles)) {
                return '<p>' . __('Du er allerede logget ind.', 'rigtig-for-mig') . ' <a href="' . home_url('/bruger-dashboard') . '">' . __('Gå til dashboard', 'rigtig-for-mig') . '</a></p>';
            }
        }
        
        // Check for verified success parameter
        $verified_success = isset($_GET['verified']) && $_GET['verified'] === 'success';
        
        ob_start();
        ?>
        <div class="rfm-login-container">
            <div class="rfm-form-wrapper">
                <h2><?php _e('Log ind', 'rigtig-for-mig'); ?></h2>
                <p class="rfm-form-description"><?php _e('Log ind med din e-mail eller brugernavn', 'rigtig-for-mig'); ?></p>
                
                <?php if ($verified_success): ?>
                    <div class="rfm-message rfm-message-success">
                        <strong>✅ <?php _e('E-mail bekræftet!', 'rigtig-for-mig'); ?></strong><br>
                        <?php _e('Din e-mail er nu verificeret. Du kan nu logge ind.', 'rigtig-for-mig'); ?>
                    </div>
                <?php endif; ?>
                
                <form id="rfm-unified-login-form" class="rfm-form">
                    <div class="rfm-form-group">
                        <label for="login_identifier"><?php _e('E-mail eller brugernavn', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="text" id="login_identifier" name="identifier" required 
                               placeholder="<?php _e('Din e-mail eller brugernavn', 'rigtig-for-mig'); ?>">
                    </div>
                    
                    <div class="rfm-form-group">
                        <label for="login_password"><?php _e('Adgangskode', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="password" id="login_password" name="password" required 
                               placeholder="<?php _e('Din adgangskode', 'rigtig-for-mig'); ?>">
                    </div>
                    
                    <div class="rfm-form-group rfm-checkbox-group">
                        <label>
                            <input type="checkbox" id="login_remember" name="remember">
                            <?php _e('Husk mig', 'rigtig-for-mig'); ?>
                        </label>
                    </div>
                    
                    <div class="rfm-form-messages"></div>
                    
                    <button type="submit" class="rfm-btn rfm-btn-primary">
                        <?php _e('Log ind', 'rigtig-for-mig'); ?>
                    </button>
                    
                    <p class="rfm-form-footer">
                        <a href="<?php echo home_url('/glemt-adgangskode'); ?>"><?php _e('Glemt adgangskode?', 'rigtig-for-mig'); ?></a>
                    </p>
                    
                    <div class="rfm-login-options">
                        <p class="rfm-separator"><?php _e('Har du ikke en profil?', 'rigtig-for-mig'); ?></p>
                        <div class="rfm-button-group">
                            <a href="<?php echo home_url('/opret-bruger'); ?>" class="rfm-btn rfm-btn-secondary">
                                <?php _e('Opret brugerprofil', 'rigtig-for-mig'); ?>
                            </a>
                            <a href="<?php echo home_url('/opret-ekspert'); ?>" class="rfm-btn rfm-btn-secondary">
                                <?php _e('Bliv ekspert', 'rigtig-for-mig'); ?>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#rfm-unified-login-form').on('submit', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $button = $form.find('button[type="submit"]');
                const $messages = $form.find('.rfm-form-messages');
                
                $button.prop('disabled', true).text('<?php _e('Logger ind...', 'rigtig-for-mig'); ?>');
                $messages.html('');
                
                $.ajax({
                    url: rfmData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rfm_unified_login',
                        nonce: rfmData.nonce,
                        identifier: $('#login_identifier').val(),
                        password: $('#login_password').val(),
                        remember: $('#login_remember').is(':checked') ? 1 : 0
                    },
                    success: function(response) {
                        if (response.success) {
                            $messages.html('<div class="rfm-message rfm-message-success">' + response.data.message + '</div>');
                            
                            // Redirect to appropriate dashboard
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1000);
                        } else {
                            $messages.html('<div class="rfm-message rfm-message-error">' + response.data.message + '</div>');
                            $button.prop('disabled', false).text('<?php _e('Log ind', 'rigtig-for-mig'); ?>');
                        }
                    },
                    error: function() {
                        $messages.html('<div class="rfm-message rfm-message-error"><?php _e('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig'); ?></div>');
                        $button.prop('disabled', false).text('<?php _e('Log ind', 'rigtig-for-mig'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle user registration
     */
    public function handle_registration() {
        check_ajax_referer('rfm_nonce', 'nonce');
        
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $gdpr_consent = isset($_POST['gdpr_consent']) ? intval($_POST['gdpr_consent']) : 0;
        
        // Validate
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error(array(
                'message' => __('Alle felter er påkrævede', 'rigtig-for-mig')
            ));
        }
        
        if (!$gdpr_consent) {
            wp_send_json_error(array(
                'message' => __('Du skal acceptere privatlivspolitikken', 'rigtig-for-mig')
            ));
        }
        
        if (strlen($password) < 8) {
            wp_send_json_error(array(
                'message' => __('Adgangskoden skal være mindst 8 tegn', 'rigtig-for-mig')
            ));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array(
                'message' => __('Ugyldig e-mail adresse', 'rigtig-for-mig')
            ));
        }
        
        // Check if username exists
        if (username_exists($username)) {
            wp_send_json_error(array(
                'message' => __('Brugernavnet er allerede i brug', 'rigtig-for-mig')
            ));
        }
        
        // Check if email exists
        if (email_exists($email)) {
            wp_send_json_error(array(
                'message' => __('E-mail adressen er allerede registreret', 'rigtig-for-mig')
            ));
        }
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array(
                'message' => $user_id->get_error_message()
            ));
        }
        
        // Set user role
        $user = new WP_User($user_id);
        $user->set_role('rfm_user');

        // Create user profile (Custom Post Type)
        $profile_post_id = RFM_Migration::create_user_profile_on_registration($user_id, $email, $username);

        if (!$profile_post_id) {
            // Rollback: delete WordPress user if profile creation failed
            wp_delete_user($user_id);
            wp_send_json_error(array(
                'message' => __('Der opstod en fejl ved oprettelse af profil. Prøv igen.', 'rigtig-for-mig')
            ));
        }

        // Save GDPR consent to profile post meta
        update_post_meta($profile_post_id, '_rfm_gdpr_consent', $gdpr_consent);
        update_post_meta($profile_post_id, '_rfm_gdpr_consent_date', current_time('mysql'));

        // Send verification email
        $verification = RFM_Email_Verification::get_instance();
        $token = $verification->create_verification_token($user_id, 0, $email);

        if ($token) {
            $verification->send_user_verification_email($email, $token, 'user');
        }

        // Set user as unverified (now stored in post_meta)
        RFM_Migration::set_user_verified($user_id, false);
        update_user_meta($user_id, 'rfm_account_status', 'pending_verification');
        
        wp_send_json_success(array(
            'message' => __('Din profil er oprettet! Tjek din e-mail for at bekræfte din konto.', 'rigtig-for-mig'),
            // FIX (v3.6.1): Changed redirect to login page to avoid confusion
            // User should go to login page and check email, not a confirmation page
            'redirect' => home_url('/login')
        ));
    }
    
    /**
     * Handle unified login (both users and experts)
     */
    public function handle_unified_login() {
        check_ajax_referer('rfm_nonce', 'nonce');
        
        $identifier = sanitize_text_field($_POST['identifier']); // Can be email or username
        $password = $_POST['password'];
        $remember = isset($_POST['remember']) ? true : false;
        
        // Validate
        if (empty($identifier) || empty($password)) {
            wp_send_json_error(array(
                'message' => __('Alle felter er påkrævede', 'rigtig-for-mig')
            ));
        }
        
        // Determine if identifier is email or username
        $user = null;
        if (is_email($identifier)) {
            $user = get_user_by('email', $identifier);
        } else {
            $user = get_user_by('login', $identifier);
        }
        
        if (!$user) {
            wp_send_json_error(array(
                'message' => __('Ugyldigt brugernavn/e-mail eller adgangskode', 'rigtig-for-mig')
            ));
        }
        
        // Check password
        if (!wp_check_password($password, $user->user_pass, $user->ID)) {
            wp_send_json_error(array(
                'message' => __('Ugyldigt brugernavn/e-mail eller adgangskode', 'rigtig-for-mig')
            ));
        }
        
        // Check if email is verified (unified check using Custom Post Type)
        $verified = false;

        if (in_array('rfm_expert_user', $user->roles)) {
            // For experts: Check if they have an expert post and if it's verified
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
            $verified = RFM_Migration::is_user_verified($user->ID);
        }

        if (!$verified) {
            wp_send_json_error(array(
                'message' => __('Din e-mail er ikke bekræftet. Tjek din indbakke.', 'rigtig-for-mig')
            ));
        }
        
        // Log user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);
        do_action('wp_login', $user->user_login, $user);

        // Update last login (using unified migration helper)
        if (in_array('rfm_user', $user->roles)) {
            RFM_Migration::update_last_login($user->ID);
        }
        
        // Determine redirect based on role
        $redirect = home_url();
        if (in_array('rfm_expert_user', $user->roles)) {
            $redirect = home_url('/ekspert-dashboard');
        } elseif (in_array('rfm_user', $user->roles)) {
            $redirect = home_url('/bruger-dashboard');
        }
        
        wp_send_json_success(array(
            'message' => __('Du er nu logget ind!', 'rigtig-for-mig'),
            'redirect' => $redirect
        ));
    }
    
    /**
     * Handle logout
     */
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

        // Clear all caches to prevent showing cached logged-in content
        wp_cache_flush();

        // Clear LiteSpeed cache if active
        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        }

        // Trigger cache clearing hooks for popular cache plugins
        do_action('litespeed_purge_all');
        do_action('w3tc_flush_all');
        do_action('wp_cache_clear_cache');

        // Send no-cache headers
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        do_action('rfm_user_logged_out');

        wp_send_json_success(array(
            'message' => __('Du er nu logget ud', 'rigtig-for-mig'),
            'redirect' => home_url(),
            'clear_cache' => true  // Signal to JavaScript for hard reload
        ));
    }
    
    /**
     * Custom login redirect
     */
    public function user_login_redirect($redirect_to, $request, $user) {
        if (isset($user->roles) && is_array($user->roles)) {
            if (in_array('rfm_user', $user->roles)) {
                return home_url('/bruger-dashboard');
            }
        }
        return $redirect_to;
    }
    
    /**
     * Hide admin bar for users
     */
    public function hide_admin_bar_for_users() {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_user', $user->roles)) {
                show_admin_bar(false);
            }
        }
    }
    
    public function hide_admin_bar_filter($show) {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_user', $user->roles)) {
                return false;
            }
        }
        return $show;
    }
    
    /**
     * Block admin access for regular users
     */
    public function block_admin_access_for_users() {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            
            if (in_array('rfm_user', $user->roles)) {
                // Block all admin access for regular users
                wp_redirect(home_url('/bruger-dashboard'));
                exit;
            }
        }
    }
}
