<?php
/**
 * Email Verification System
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Email_Verification {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('save_post_rfm_expert', array($this, 'check_email_change'), 20, 2);
        add_action('template_redirect', array($this, 'handle_verification_link')); // Changed from 'init' to 'template_redirect'
        add_action('rfm_send_verification_email', array($this, 'send_verification_email'), 10, 3);
    }
    
    /**
     * Check if email has changed and send verification
     */
    public function check_email_change($post_id, $post) {
        // Skip autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        $new_email = isset($_POST['rfm_email']) ? sanitize_email($_POST['rfm_email']) : '';
        $old_email = get_post_meta($post_id, '_rfm_email', true);
        
        // If email has changed or is new
        if ($new_email && $new_email !== $old_email) {
            // Mark as unverified
            update_post_meta($post_id, '_rfm_email_verified', false);
            
            // Send verification email
            $this->send_verification_email($post_id, $new_email, get_current_user_id());
        }
    }
    
    /**
     * Send verification email
     */
    public function send_verification_email($expert_id, $email, $user_id) {
        global $wpdb;
        $table = RFM_Database::get_table_name('email_verification');
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Delete old tokens for this expert
        $wpdb->delete(
            $table,
            array('expert_id' => $expert_id),
            array('%d')
        );
        
        // Insert new token
        $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'expert_id' => $expert_id,
                'email' => $email,
                'token' => $token,
                'expires_at' => $expires_at
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
        
        // Create verification URL
        $verification_url = add_query_arg(
            array(
                'rfm_verify' => 'email',
                'token' => $token
            ),
            home_url()
        );
        
        // Get expert name
        $expert_name = get_the_title($expert_id);
        
        // Email subject
        $subject = sprintf(__('Verificer din email for %s', 'rigtig-for-mig'), get_bloginfo('name'));
        
        // Email body
        $message = sprintf(
            __('Hej %s,

Tak for at oprette en ekspertprofil på %s.

For at bekræfte din email-adresse, skal du klikke på linket nedenfor:

%s

Linket er gyldigt i 24 timer.

Hvis du ikke har oprettet denne profil, kan du ignorere denne email.

Med venlig hilsen,
%s teamet', 'rigtig-for-mig'),
            $expert_name,
            get_bloginfo('name'),
            $verification_url,
            get_bloginfo('name')
        );
        
        // Set email headers
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        // Send email
        $sent = wp_mail($email, $subject, $message, $headers);
        
        if ($sent) {
            // Log success
            do_action('rfm_verification_email_sent', $expert_id, $email);
        } else {
            // Log error
            do_action('rfm_verification_email_failed', $expert_id, $email);
        }
        
        return $sent;
    }
    
    /**
     * Handle verification link click
     */
    public function handle_verification_link() {
        // Check if this is a verification request
        if (!isset($_GET['rfm_verify'])) {
            return;
        }
        
        $verify_type = $_GET['rfm_verify'];
        
        // Handle both expert and user verification
        if ($verify_type !== 'email' && $verify_type !== 'user_email') {
            return;
        }
        
        if (!isset($_GET['token'])) {
            wp_die(__('Ugyldig verificeringslink - token mangler.', 'rigtig-for-mig'), __('Fejl', 'rigtig-for-mig'), array('response' => 400));
        }
        
        $token = sanitize_text_field($_GET['token']);
        
        global $wpdb;
        $table = RFM_Database::get_table_name('email_verification');
        
        // Get verification record
        $verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE token = %s AND verified_at IS NULL",
            $token
        ));
        
        if (!$verification) {
            wp_die(
                __('Denne verificeringslink er ugyldig eller allerede blevet brugt.', 'rigtig-for-mig'),
                __('Verificering Fejlet', 'rigtig-for-mig'),
                array(
                    'response' => 400,
                    'back_link' => home_url()
                )
            );
        }
        
        // Check if token has expired
        if (strtotime($verification->expires_at) < time()) {
            wp_die(
                __('Verificeringslinket er udløbet. Gå til din profil for at anmode om en ny verifikationsemail.', 'rigtig-for-mig'),
                __('Link Udløbet', 'rigtig-for-mig'),
                array(
                    'response' => 400,
                    'back_link' => home_url()
                )
            );
        }
        
        // Mark as verified in database
        $updated = $wpdb->update(
            $table,
            array('verified_at' => current_time('mysql')),
            array('id' => $verification->id),
            array('%s'),
            array('%d')
        );
        
        if ($updated === false) {
            wp_die(
                __('Der opstod en fejl ved verificering. Prøv igen eller kontakt support.', 'rigtig-for-mig'),
                __('Database Fejl', 'rigtig-for-mig'),
                array('response' => 500)
            );
        }
        
        // Handle user verification
        if ($verify_type === 'user_email' && $verification->expert_id == 0) {
            // Update user meta
            update_user_meta($verification->user_id, 'rfm_email_verified', 1);
            update_user_meta($verification->user_id, 'rfm_account_status', 'active');
            
            // Trigger action
            do_action('rfm_user_email_verified', $verification->user_id);
            
            // Redirect to login with success message
            $redirect_url = add_query_arg(
                array('verified' => 'success'),
                home_url('/login')
            );
            
            wp_safe_redirect($redirect_url);
            exit;
        }
        
        // Handle expert verification
        if ($verify_type === 'email' && $verification->expert_id > 0) {
            // Update expert meta
            update_post_meta($verification->expert_id, '_rfm_email_verified', true);
            
            // Check if auto-publish is enabled
            $expert_post = get_post($verification->expert_id);
            if ($expert_post && $expert_post->post_status === 'draft') {
                $auto_approve = get_option('rfm_auto_approve_profiles', false);
                
                if ($auto_approve || get_option('rfm_email_verification', true)) {
                    wp_update_post(array(
                        'ID' => $verification->expert_id,
                        'post_status' => 'publish'
                    ));
                }
            }
            
            // Trigger action
            do_action('rfm_email_verified', $verification->expert_id, $verification->user_id);
            
            // Get expert profile URL
            $expert_url = get_permalink($verification->expert_id);
            
            // Redirect with success
            $redirect_url = add_query_arg(
                array('verified' => 'success'),
                $expert_url ? $expert_url : home_url()
            );
            
            wp_safe_redirect($redirect_url);
            exit;
        }
        
        // Fallback redirect
        wp_safe_redirect(home_url());
        exit;
    }
    
    /**
     * Check if email is verified
     */
    public static function is_email_verified($expert_id) {
        return (bool) get_post_meta($expert_id, '_rfm_email_verified', true);
    }
    
    /**
     * Create verification token for user or expert
     * 
     * @param int $user_id WordPress user ID
     * @param int $expert_id Expert post ID (0 for regular users)
     * @param string $email Email address
     * @return string|false Token on success, false on failure
     */
    public function create_verification_token($user_id, $expert_id, $email) {
        global $wpdb;
        $table = RFM_Database::get_table_name('email_verification');
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Delete old tokens for this user/expert
        if ($expert_id > 0) {
            $wpdb->delete($table, array('expert_id' => $expert_id), array('%d'));
        } else {
            $wpdb->delete($table, array('user_id' => $user_id, 'expert_id' => 0), array('%d', '%d'));
        }
        
        // Insert new token
        $inserted = $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'expert_id' => $expert_id,
                'email' => $email,
                'token' => $token,
                'expires_at' => $expires_at
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
        
        if ($inserted === false) {
            error_log('RFM: Failed to insert verification token for user ' . $user_id);
            return false;
        }
        
        return $token;
    }
    
    /**
     * Send verification email for user (not expert)
     * 
     * @param string $email Email address
     * @param string $token Verification token
     * @param string $type 'user' or 'expert'
     * @return bool
     */
    public function send_user_verification_email($email, $token, $type = 'user') {
        // Create verification URL
        $verification_url = add_query_arg(
            array(
                'rfm_verify' => $type === 'user' ? 'user_email' : 'email',
                'token' => $token
            ),
            home_url()
        );
        
        // Email subject
        $subject = sprintf(__('Verificer din email for %s', 'rigtig-for-mig'), get_bloginfo('name'));
        
        // Get user info
        $user_name = $type === 'user' ? __('Bruger', 'rigtig-for-mig') : __('Ekspert', 'rigtig-for-mig');
        
        // Email body
        $message = sprintf(
            __('Hej,

Tak for at oprette en profil på %s.

For at bekræfte din email-adresse, skal du klikke på linket nedenfor:

%s

Linket er gyldigt i 24 timer.

Hvis du ikke har oprettet denne profil, kan du ignorere denne email.

Med venlig hilsen,
%s teamet', 'rigtig-for-mig'),
            get_bloginfo('name'),
            $verification_url,
            get_bloginfo('name')
        );
        
        // Set email headers
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        // Send email
        $sent = wp_mail($email, $subject, $message, $headers);
        
        if (!$sent) {
            error_log('RFM: Failed to send verification email to ' . $email);
        }
        
        return $sent;
    }
    
    /**
     * Resend verification email
     */
    public function resend_verification($expert_id) {
        $email = get_post_meta($expert_id, '_rfm_email', true);
        $user_id = get_post_field('post_author', $expert_id);

        if ($email && $user_id) {
            return $this->send_verification_email($expert_id, $email, $user_id);
        }

        return false;
    }

    /**
     * Set user verified status
     * Ensures consistent data type (string '1' or '0')
     *
     * @param int $user_id WordPress user ID
     * @param bool $verified True to mark as verified, false for unverified
     * @return bool True on success
     */
    public static function set_user_verified($user_id, $verified = true) {
        // Store as string for MySQL consistency
        $value = $verified ? '1' : '0';

        update_user_meta($user_id, 'rfm_email_verified', $value);

        // Also store timestamp when verified
        if ($verified) {
            update_user_meta($user_id, 'rfm_email_verified_at', current_time('mysql'));
        }

        error_log('RFM INFO: User ' . $user_id . ' verification set to: ' . $value);

        return true;
    }

    /**
     * Check if user is verified
     * Returns boolean, handles all edge cases
     *
     * @param int $user_id WordPress user ID
     * @return bool True if verified
     */
    public static function is_user_verified($user_id) {
        $verified = get_user_meta($user_id, 'rfm_email_verified', true);

        // Handle all possible values
        return ($verified === '1' || $verified === 1 || $verified === true);
    }

    /**
     * Get verified users count
     * Consistent with storage format
     *
     * @return int Number of verified users
     */
    public static function get_verified_users_count() {
        global $wpdb;

        return (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'rfm_email_verified'
            AND um.meta_value IN ('1', 1)
            AND EXISTS (
                SELECT 1 FROM {$wpdb->usermeta} um2
                WHERE um2.user_id = u.ID
                AND um2.meta_key = 'wp_capabilities'
                AND um2.meta_value LIKE '%rfm_user%'
            )
        ");
    }

    /**
     * Get verification timestamp for a user
     *
     * @param int $user_id WordPress user ID
     * @return string|false Timestamp or false if not verified
     */
    public static function get_user_verification_date($user_id) {
        if (!self::is_user_verified($user_id)) {
            return false;
        }

        return get_user_meta($user_id, 'rfm_email_verified_at', true);
    }
}
