<?php
/**
 * Contact Information Protection
 * 
 * Protects expert contact info (phone, email, website) from non-logged-in users
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Contact_Protection {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add filters to protect contact information
        add_filter('rfm_display_expert_phone', array($this, 'protect_phone'), 10, 2);
        add_filter('rfm_display_expert_email', array($this, 'protect_email'), 10, 2);
        add_filter('rfm_display_expert_website', array($this, 'protect_website'), 10, 2);
        
        // Shortcode for login prompt
        add_shortcode('rfm_contact_login_prompt', array($this, 'login_prompt_shortcode'));
    }
    
    /**
     * Check if user can view contact info
     */
    public function can_view_contact_info() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        
        // Allow logged-in users and experts to view
        if (in_array('rfm_user', $user->roles) || in_array('rfm_expert_user', $user->roles)) {
            return true;
        }
        
        // Admins can always view
        if (in_array('administrator', $user->roles)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Protect phone number
     */
    public function protect_phone($phone, $expert_id) {
        if ($this->can_view_contact_info()) {
            return $phone;
        }
        
        return '<span class="rfm-protected-info">' . 
               '<i class="rfm-lock-icon">🔒</i> ' .
               sprintf(__('<a href="%s">Log ind</a> for at se telefonnummer', 'rigtig-for-mig'), home_url('/login')) . 
               '</span>';
    }
    
    /**
     * Protect email
     */
    public function protect_email($email, $expert_id) {
        if ($this->can_view_contact_info()) {
            return $email;
        }
        
        return '<span class="rfm-protected-info">' . 
               '<i class="rfm-lock-icon">🔒</i> ' .
               sprintf(__('<a href="%s">Log ind</a> for at se e-mail adresse', 'rigtig-for-mig'), home_url('/login')) . 
               '</span>';
    }
    
    /**
     * Protect website
     */
    public function protect_website($website, $expert_id) {
        if ($this->can_view_contact_info()) {
            return $website;
        }
        
        return '<span class="rfm-protected-info">' . 
               '<i class="rfm-lock-icon">🔒</i> ' .
               sprintf(__('<a href="%s">Log ind</a> for at se hjemmeside', 'rigtig-for-mig'), home_url('/login')) . 
               '</span>';
    }
    
    /**
     * Get protected contact info HTML
     */
    public static function get_protected_contact_html($type, $value, $expert_id) {
        $instance = self::get_instance();
        
        if ($instance->can_view_contact_info()) {
            // Return actual value based on type
            switch ($type) {
                case 'phone':
                    return '<a href="tel:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
                case 'email':
                    return '<a href="mailto:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
                case 'website':
                    $url = esc_url($value);
                    return '<a href="' . $url . '" target="_blank" rel="noopener">' . esc_html($value) . '</a>';
                default:
                    return esc_html($value);
            }
        }
        
        // Return protected message
        return $instance->get_protected_message($type);
    }
    
    /**
     * Get protected message
     */
    private function get_protected_message($type) {
        $messages = array(
            'phone' => __('Log ind for at se telefonnummer', 'rigtig-for-mig'),
            'email' => __('Log ind for at se e-mail adresse', 'rigtig-for-mig'),
            'website' => __('Log ind for at se hjemmeside', 'rigtig-for-mig')
        );
        
        $message = isset($messages[$type]) ? $messages[$type] : __('Log ind for at se kontaktinfo', 'rigtig-for-mig');
        
        return '<span class="rfm-protected-info">' . 
               '<svg class="rfm-lock-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>' .
               ' <a href="' . home_url('/login') . '">' . $message . '</a>' .
               '</span>';
    }
    
    /**
     * Login prompt shortcode
     */
    public function login_prompt_shortcode($atts) {
        if ($this->can_view_contact_info()) {
            return ''; // Don't show anything if already logged in
        }
        
        $atts = shortcode_atts(array(
            'message' => __('Log ind for at se ekspertens kontaktinformation', 'rigtig-for-mig')
        ), $atts);
        
        ob_start();
        ?>
        <div class="rfm-contact-login-prompt">
            <div class="rfm-prompt-icon">🔒</div>
            <div class="rfm-prompt-content">
                <p><?php echo esc_html($atts['message']); ?></p>
                <div class="rfm-prompt-actions">
                    <a href="<?php echo home_url('/login'); ?>" class="rfm-btn rfm-btn-primary">
                        <?php _e('Log ind', 'rigtig-for-mig'); ?>
                    </a>
                    <a href="<?php echo home_url('/opret-bruger'); ?>" class="rfm-btn rfm-btn-secondary">
                        <?php _e('Opret gratis brugerprofil', 'rigtig-for-mig'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Helper function to check and display contact info
     * Use this in templates
     */
    public static function display_contact_field($label, $type, $value, $expert_id, $icon = '') {
        if (empty($value)) {
            return '';
        }
        
        $protected_html = self::get_protected_contact_html($type, $value, $expert_id);
        
        ob_start();
        ?>
        <div class="rfm-contact-field rfm-contact-<?php echo esc_attr($type); ?>">
            <?php if ($icon): ?>
                <span class="rfm-contact-icon"><?php echo $icon; ?></span>
            <?php endif; ?>
            <div class="rfm-contact-content">
                <?php if ($label): ?>
                    <span class="rfm-contact-label"><?php echo esc_html($label); ?>:</span>
                <?php endif; ?>
                <span class="rfm-contact-value"><?php echo $protected_html; ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
