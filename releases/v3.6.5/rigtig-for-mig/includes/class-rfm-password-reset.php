<?php
/**
 * Password Reset Handler for RFM Expert Users
 * Handles lost password and password reset functionality
 *
 * @package Rigtig_For_Mig
 * @version 2.8.6
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Password_Reset {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Shortcodes
        add_shortcode('rfm_lost_password', array($this, 'lost_password_form'));
        add_shortcode('rfm_reset_password', array($this, 'reset_password_form'));
        
        // AJAX handlers
        add_action('wp_ajax_rfm_lost_password', array($this, 'handle_lost_password'));
        add_action('wp_ajax_nopriv_rfm_lost_password', array($this, 'handle_lost_password'));
        
        add_action('wp_ajax_rfm_reset_password', array($this, 'handle_reset_password'));
        add_action('wp_ajax_nopriv_rfm_reset_password', array($this, 'handle_reset_password'));
        
        // Override WordPress lost password URL for experts
        add_filter('lostpassword_url', array($this, 'custom_lostpassword_url'), 10, 2);
    }
    
    /**
     * Custom lost password URL
     */
    public function custom_lostpassword_url($lostpassword_url, $redirect) {
        // Get the custom lost password page
        $custom_page = get_option('rfm_lost_password_page');
        
        if ($custom_page) {
            $lostpassword_url = get_permalink($custom_page);
            
            if ($redirect) {
                $lostpassword_url = add_query_arg('redirect_to', urlencode($redirect), $lostpassword_url);
            }
        }
        
        return $lostpassword_url;
    }
    
    /**
     * Lost Password Form Shortcode
     */
    public function lost_password_form($atts) {
        // If user is logged in, redirect to dashboard
        if (is_user_logged_in()) {
            return '<p>Du er allerede logget ind. <a href="' . esc_url(home_url('/ekspert-dashboard/')) . '">Gå til dashboard</a></p>';
        }
        
        ob_start();
        
        // Check for success message
        $success = isset($_GET['reset']) && $_GET['reset'] === 'sent';
        
        ?>
        <div class="rfm-lost-password-form">
            <h2><?php _e('Glemt adgangskode?', 'rigtig-for-mig'); ?></h2>
            
            <?php if ($success): ?>
                <div class="rfm-success">
                    <p><?php _e('Vi har sendt dig en email med instruktioner til at nulstille din adgangskode. Tjek din indbakke.', 'rigtig-for-mig'); ?></p>
                </div>
                <p><a href="<?php echo esc_url(home_url('/ekspert-login/')); ?>"><?php _e('Tilbage til login', 'rigtig-for-mig'); ?></a></p>
            <?php else: ?>
                <p><?php _e('Indtast din email adresse, så sender vi dig et link til at nulstille din adgangskode.', 'rigtig-for-mig'); ?></p>
                
                <form id="rfm-lost-password-form" method="post">
                    <div class="rfm-form-field">
                        <label for="user_email"><?php _e('Email adresse', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="email" id="user_email" name="user_email" required>
                    </div>
                    
                    <div class="rfm-form-submit">
                        <button type="submit" class="rfm-btn rfm-btn-primary">
                            <?php _e('Send nulstillings-link', 'rigtig-for-mig'); ?>
                        </button>
                    </div>
                    
                    <div class="rfm-form-links">
                        <a href="<?php echo esc_url(home_url('/ekspert-login/')); ?>"><?php _e('Tilbage til login', 'rigtig-for-mig'); ?></a>
                    </div>
                    
                    <div class="rfm-status-message"></div>
                    
                    <?php wp_nonce_field('rfm_lost_password', 'rfm_lost_password_nonce'); ?>
                </form>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#rfm-lost-password-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $status = $('.rfm-status-message');
                var $button = $form.find('button[type="submit"]');
                var email = $('#user_email').val();
                
                // Disable button
                $button.prop('disabled', true).text('<?php _e('Sender...', 'rigtig-for-mig'); ?>');
                $status.removeClass('rfm-error rfm-success').text('');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'rfm_lost_password',
                        user_email: email,
                        nonce: $form.find('#rfm_lost_password_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            // Redirect to success page
                            window.location.href = response.data.redirect;
                        } else {
                            $status.addClass('rfm-error').text(response.data.message);
                            $button.prop('disabled', false).text('<?php _e('Send nulstillings-link', 'rigtig-for-mig'); ?>');
                        }
                    },
                    error: function() {
                        $status.addClass('rfm-error').text('<?php _e('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig'); ?>');
                        $button.prop('disabled', false).text('<?php _e('Send nulstillings-link', 'rigtig-for-mig'); ?>');
                    }
                });
            });
        });
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Reset Password Form Shortcode
     */
    public function reset_password_form($atts) {
        // If user is logged in, redirect to dashboard
        if (is_user_logged_in()) {
            return '<p>Du er allerede logget ind. <a href="' . esc_url(home_url('/ekspert-dashboard/')) . '">Gå til dashboard</a></p>';
        }
        
        // Get reset key and login from URL
        $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        $login = isset($_GET['login']) ? sanitize_text_field($_GET['login']) : '';
        
        if (empty($key) || empty($login)) {
            return '<div class="rfm-error"><p>' . __('Ugyldigt nulstillings-link. Anmod venligst om et nyt.', 'rigtig-for-mig') . '</p><p><a href="' . esc_url(home_url('/glemt-adgangskode/')) . '">' . __('Anmod om ny', 'rigtig-for-mig') . '</a></p></div>';
        }
        
        // Verify the key
        $user = check_password_reset_key($key, $login);
        
        if (is_wp_error($user)) {
            return '<div class="rfm-error"><p>' . __('Dette nulstillings-link er udløbet eller ugyldigt. Anmod venligst om et nyt.', 'rigtig-for-mig') . '</p><p><a href="' . esc_url(home_url('/glemt-adgangskode/')) . '">' . __('Anmod om ny', 'rigtig-for-mig') . '</a></p></div>';
        }
        
        ob_start();
        
        // Check for success
        $success = isset($_GET['password']) && $_GET['password'] === 'changed';
        
        ?>
        <div class="rfm-reset-password-form">
            <h2><?php _e('Nulstil din adgangskode', 'rigtig-for-mig'); ?></h2>
            
            <?php if ($success): ?>
                <div class="rfm-success">
                    <p><?php _e('Din adgangskode er blevet ændret. Du kan nu logge ind med din nye adgangskode.', 'rigtig-for-mig'); ?></p>
                </div>
                <p><a href="<?php echo esc_url(home_url('/ekspert-login/')); ?>" class="rfm-btn rfm-btn-primary"><?php _e('Gå til login', 'rigtig-for-mig'); ?></a></p>
            <?php else: ?>
                <p><?php _e('Indtast din nye adgangskode nedenfor.', 'rigtig-for-mig'); ?></p>
                
                <form id="rfm-reset-password-form" method="post">
                    <div class="rfm-form-field">
                        <label for="new_password"><?php _e('Ny adgangskode', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="password" id="new_password" name="new_password" required minlength="8">
                        <p class="rfm-field-description"><?php _e('Mindst 8 tegn', 'rigtig-for-mig'); ?></p>
                    </div>
                    
                    <div class="rfm-form-field">
                        <label for="confirm_password"><?php _e('Bekræft adgangskode', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>
                    
                    <input type="hidden" name="reset_key" value="<?php echo esc_attr($key); ?>">
                    <input type="hidden" name="reset_login" value="<?php echo esc_attr($login); ?>">
                    
                    <div class="rfm-form-submit">
                        <button type="submit" class="rfm-btn rfm-btn-primary">
                            <?php _e('Nulstil adgangskode', 'rigtig-for-mig'); ?>
                        </button>
                    </div>
                    
                    <div class="rfm-status-message"></div>
                    
                    <?php wp_nonce_field('rfm_reset_password', 'rfm_reset_password_nonce'); ?>
                </form>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#rfm-reset-password-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $status = $('.rfm-status-message');
                var $button = $form.find('button[type="submit"]');
                var password = $('#new_password').val();
                var confirm = $('#confirm_password').val();
                
                // Validate passwords match
                if (password !== confirm) {
                    $status.addClass('rfm-error').text('<?php _e('Adgangskoderne matcher ikke', 'rigtig-for-mig'); ?>');
                    return;
                }
                
                // Disable button
                $button.prop('disabled', true).text('<?php _e('Nulstiller...', 'rigtig-for-mig'); ?>');
                $status.removeClass('rfm-error rfm-success').text('');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'rfm_reset_password',
                        new_password: password,
                        reset_key: $form.find('[name="reset_key"]').val(),
                        reset_login: $form.find('[name="reset_login"]').val(),
                        nonce: $form.find('#rfm_reset_password_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            // Redirect to success state
                            window.location.href = response.data.redirect;
                        } else {
                            $status.addClass('rfm-error').text(response.data.message);
                            $button.prop('disabled', false).text('<?php _e('Nulstil adgangskode', 'rigtig-for-mig'); ?>');
                        }
                    },
                    error: function() {
                        $status.addClass('rfm-error').text('<?php _e('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig'); ?>');
                        $button.prop('disabled', false).text('<?php _e('Nulstil adgangskode', 'rigtig-for-mig'); ?>');
                    }
                });
            });
        });
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle Lost Password AJAX Request
     */
    public function handle_lost_password() {
        check_ajax_referer('rfm_lost_password', 'nonce');
        
        $email = sanitize_email($_POST['user_email']);
        
        if (empty($email)) {
            wp_send_json_error(array('message' => __('Email adresse er påkrævet', 'rigtig-for-mig')));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Ugyldig email adresse', 'rigtig-for-mig')));
        }
        
        // Get user by email
        $user = get_user_by('email', $email);
        
        if (!$user) {
            // For security, don't reveal if email exists or not
            wp_send_json_success(array(
                'message' => __('Hvis denne email findes i vores system, har vi sendt et nulstillings-link.', 'rigtig-for-mig'),
                'redirect' => add_query_arg('reset', 'sent', home_url('/glemt-adgangskode/'))
            ));
            return;
        }
        
        // Check if user is an expert
        if (!in_array('rfm_expert_user', $user->roles)) {
            wp_send_json_success(array(
                'message' => __('Hvis denne email findes i vores system, har vi sendt et nulstillings-link.', 'rigtig-for-mig'),
                'redirect' => add_query_arg('reset', 'sent', home_url('/glemt-adgangskode/'))
            ));
            return;
        }
        
        // Generate reset key
        $key = get_password_reset_key($user);
        
        if (is_wp_error($key)) {
            wp_send_json_error(array('message' => __('Der opstod en fejl. Prøv igen senere.', 'rigtig-for-mig')));
        }
        
        // Send reset email
        $sent = $this->send_reset_email($user, $key);
        
        if ($sent) {
            wp_send_json_success(array(
                'message' => __('Nulstillings-link sendt til din email', 'rigtig-for-mig'),
                'redirect' => add_query_arg('reset', 'sent', home_url('/glemt-adgangskode/'))
            ));
        } else {
            wp_send_json_error(array('message' => __('Email kunne ikke sendes. Kontakt support.', 'rigtig-for-mig')));
        }
    }
    
    /**
     * Handle Reset Password AJAX Request
     */
    public function handle_reset_password() {
        check_ajax_referer('rfm_reset_password', 'nonce');
        
        $new_password = $_POST['new_password'];
        $key = sanitize_text_field($_POST['reset_key']);
        $login = sanitize_text_field($_POST['reset_login']);
        
        if (empty($new_password) || empty($key) || empty($login)) {
            wp_send_json_error(array('message' => __('Alle felter er påkrævet', 'rigtig-for-mig')));
        }
        
        if (strlen($new_password) < 8) {
            wp_send_json_error(array('message' => __('Adgangskoden skal være mindst 8 tegn', 'rigtig-for-mig')));
        }
        
        // Verify the reset key
        $user = check_password_reset_key($key, $login);
        
        if (is_wp_error($user)) {
            wp_send_json_error(array('message' => __('Ugyldigt eller udløbet nulstillings-link', 'rigtig-for-mig')));
        }
        
        // Reset the password
        reset_password($user, $new_password);
        
        // Send confirmation email
        $this->send_password_changed_email($user);
        
        wp_send_json_success(array(
            'message' => __('Din adgangskode er blevet ændret', 'rigtig-for-mig'),
            'redirect' => add_query_arg('password', 'changed', home_url('/nulstil-adgangskode/'))
        ));
    }
    
    /**
     * Send password reset email
     */
    private function send_reset_email($user, $key) {
        $reset_url = add_query_arg(
            array(
                'key' => $key,
                'login' => rawurlencode($user->user_login)
            ),
            home_url('/nulstil-adgangskode/')
        );
        
        $site_name = get_bloginfo('name');
        $user_login = $user->user_login;
        
        $subject = sprintf(__('[%s] Nulstil din adgangskode', 'rigtig-for-mig'), $site_name);
        
        $message = sprintf(__('Hej %s,', 'rigtig-for-mig'), $user_login) . "\r\n\r\n";
        $message .= __('Vi har modtaget en anmodning om at nulstille din adgangskode.', 'rigtig-for-mig') . "\r\n\r\n";
        $message .= __('Klik på følgende link for at nulstille din adgangskode:', 'rigtig-for-mig') . "\r\n\r\n";
        $message .= $reset_url . "\r\n\r\n";
        $message .= __('Dette link er gyldigt i 24 timer.', 'rigtig-for-mig') . "\r\n\r\n";
        $message .= __('Hvis du ikke har anmodet om at nulstille din adgangskode, kan du ignorere denne email.', 'rigtig-for-mig') . "\r\n\r\n";
        $message .= sprintf(__('Venlig hilsen,%s%s', 'rigtig-for-mig'), "\r\n", $site_name);
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Send password changed confirmation email
     */
    private function send_password_changed_email($user) {
        $site_name = get_bloginfo('name');
        
        $subject = sprintf(__('[%s] Din adgangskode er blevet ændret', 'rigtig-for-mig'), $site_name);
        
        $message = sprintf(__('Hej %s,', 'rigtig-for-mig'), $user->user_login) . "\r\n\r\n";
        $message .= __('Din adgangskode er blevet ændret succesfuldt.', 'rigtig-for-mig') . "\r\n\r\n";
        $message .= __('Hvis du ikke har foretaget denne ændring, kontakt os øjeblikkeligt.', 'rigtig-for-mig') . "\r\n\r\n";
        $message .= __('Du kan nu logge ind med din nye adgangskode:', 'rigtig-for-mig') . "\r\n";
        $message .= home_url('/ekspert-login/') . "\r\n\r\n";
        $message .= sprintf(__('Venlig hilsen,%s%s', 'rigtig-for-mig'), "\r\n", $site_name);
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        wp_mail($user->user_email, $subject, $message, $headers);
    }
}

// Initialize
RFM_Password_Reset::get_instance();
