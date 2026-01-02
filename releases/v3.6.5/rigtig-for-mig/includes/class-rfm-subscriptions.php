<?php
/**
 * Subscriptions Management
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Subscriptions {
    
    private static $instance = null;
    
    // Subscription plans
    const PLAN_FREE = 'free';
    const PLAN_STANDARD = 'standard';
    const PLAN_PREMIUM = 'premium';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // AJAX handlers
        add_action('wp_ajax_rfm_upgrade_plan', array($this, 'upgrade_plan'));
        add_action('wp_ajax_rfm_cancel_subscription', array($this, 'cancel_subscription'));
        
        // Cron jobs for subscription management
        add_action('rfm_check_expired_subscriptions', array($this, 'check_expired_subscriptions'));
        
        if (!wp_next_scheduled('rfm_check_expired_subscriptions')) {
            wp_schedule_event(time(), 'daily', 'rfm_check_expired_subscriptions');
        }
    }
    
    /**
     * Get subscription plans
     */
    public static function get_plans() {
        return array(
            self::PLAN_FREE => array(
                'name' => __('Gratis', 'rigtig-for-mig'),
                'price' => 0,
                'features' => array(
                    __('Basis profil', 'rigtig-for-mig'),
                    __('1 kategori', 'rigtig-for-mig'),
                    __('Vis i søgeresultater', 'rigtig-for-mig')
                )
            ),
            self::PLAN_STANDARD => array(
                'name' => __('Standard', 'rigtig-for-mig'),
                'price' => get_option('rfm_standard_price', 219),
                'features' => array(
                    __('Udvidet profil', 'rigtig-for-mig'),
                    __('Op til 3 kategorier', 'rigtig-for-mig'),
                    __('Featured badge', 'rigtig-for-mig'),
                    __('Basis statistik', 'rigtig-for-mig'),
                    __('Besked system', 'rigtig-for-mig')
                )
            ),
            self::PLAN_PREMIUM => array(
                'name' => __('Premium', 'rigtig-for-mig'),
                'price' => get_option('rfm_premium_price', 399),
                'features' => array(
                    __('Premium profil', 'rigtig-for-mig'),
                    __('Ubegrænsede kategorier', 'rigtig-for-mig'),
                    __('Top placering', 'rigtig-for-mig'),
                    __('Avanceret statistik', 'rigtig-for-mig'),
                    __('Prioriteret support', 'rigtig-for-mig'),
                    __('Booking integration', 'rigtig-for-mig'),
                    __('Banner billede', 'rigtig-for-mig')
                )
            )
        );
    }
    
    /**
     * Get expert's current subscription
     */
    public function get_expert_subscription($expert_id) {
        global $wpdb;
        $table = RFM_Database::get_table_name('subscriptions');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE expert_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1",
            $expert_id
        ));
    }
    
    /**
     * Get subscription plan from post meta (fallback)
     */
    public function get_expert_plan($expert_id) {
        $subscription = $this->get_expert_subscription($expert_id);
        
        if ($subscription) {
            return $subscription->plan;
        }
        
        // Fallback to post meta
        $plan = get_post_meta($expert_id, '_rfm_subscription_plan', true);
        return $plan ? $plan : self::PLAN_FREE;
    }
    
    /**
     * Create or update subscription
     */
    public function create_subscription($expert_id, $user_id, $plan, $payment_data = array()) {
        global $wpdb;
        $table = RFM_Database::get_table_name('subscriptions');
        
        $plans = self::get_plans();
        
        if (!isset($plans[$plan])) {
            return false;
        }
        
        $plan_data = $plans[$plan];
        
        // Cancel any existing active subscriptions
        $wpdb->update(
            $table,
            array(
                'status' => 'cancelled',
                'cancelled_at' => current_time('mysql')
            ),
            array(
                'expert_id' => $expert_id,
                'status' => 'active'
            ),
            array('%s', '%s'),
            array('%d', '%s')
        );
        
        // Create new subscription
        $data = array(
            'expert_id' => $expert_id,
            'user_id' => $user_id,
            'plan' => $plan,
            'status' => $plan === self::PLAN_FREE ? 'active' : 'pending',
            'amount' => $plan_data['price'],
            'currency' => 'DKK',
            'start_date' => current_time('mysql'),
            'end_date' => $plan === self::PLAN_FREE ? null : date('Y-m-d H:i:s', strtotime('+1 month')),
            'next_billing_date' => $plan === self::PLAN_FREE ? null : date('Y-m-d H:i:s', strtotime('+1 month'))
        );
        
        // Add Stripe data if provided
        if (isset($payment_data['stripe_subscription_id'])) {
            $data['stripe_subscription_id'] = $payment_data['stripe_subscription_id'];
        }
        
        if (isset($payment_data['stripe_customer_id'])) {
            $data['stripe_customer_id'] = $payment_data['stripe_customer_id'];
        }
        
        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            // Update post meta
            update_post_meta($expert_id, '_rfm_subscription_plan', $plan);
            update_post_meta($expert_id, '_rfm_subscription_status', $data['status']);
            
            do_action('rfm_subscription_created', $expert_id, $plan, $wpdb->insert_id);
            
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Activate subscription (after payment)
     */
    public function activate_subscription($subscription_id) {
        global $wpdb;
        $table = RFM_Database::get_table_name('subscriptions');
        
        $result = $wpdb->update(
            $table,
            array('status' => 'active'),
            array('id' => $subscription_id),
            array('%s'),
            array('%d')
        );
        
        if ($result) {
            $subscription = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $subscription_id
            ));
            
            if ($subscription) {
                update_post_meta($subscription->expert_id, '_rfm_subscription_status', 'active');
                do_action('rfm_subscription_activated', $subscription->expert_id, $subscription->plan);
            }
        }
        
        return $result;
    }
    
    /**
     * Cancel subscription
     */
    public function cancel_subscription() {
        check_ajax_referer('rfm_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Ikke autoriseret', 'rigtig-for-mig')));
        }
        
        $expert_id = isset($_POST['expert_id']) ? intval($_POST['expert_id']) : 0;
        $user_id = get_current_user_id();
        
        // Verify ownership
        if (get_post_field('post_author', $expert_id) != $user_id) {
            wp_send_json_error(array('message' => __('Ikke autoriseret', 'rigtig-for-mig')));
        }
        
        global $wpdb;
        $table = RFM_Database::get_table_name('subscriptions');
        
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'cancelled',
                'cancelled_at' => current_time('mysql')
            ),
            array(
                'expert_id' => $expert_id,
                'status' => 'active'
            ),
            array('%s', '%s'),
            array('%d', '%s')
        );
        
        if ($result) {
            // Downgrade to free plan
            update_post_meta($expert_id, '_rfm_subscription_plan', self::PLAN_FREE);
            update_post_meta($expert_id, '_rfm_subscription_status', 'cancelled');
            
            do_action('rfm_subscription_cancelled', $expert_id);
            
            wp_send_json_success(array('message' => __('Abonnement annulleret', 'rigtig-for-mig')));
        }
        
        wp_send_json_error(array('message' => __('Kunne ikke annullere abonnement', 'rigtig-for-mig')));
    }
    
    /**
     * Check for expired subscriptions
     */
    public function check_expired_subscriptions() {
        global $wpdb;
        $table = RFM_Database::get_table_name('subscriptions');
        
        // Find expired subscriptions
        $expired = $wpdb->get_results(
            "SELECT * FROM $table 
            WHERE status = 'active' 
            AND end_date IS NOT NULL 
            AND end_date < NOW()"
        );
        
        foreach ($expired as $subscription) {
            // Mark as expired
            $wpdb->update(
                $table,
                array('status' => 'expired'),
                array('id' => $subscription->id),
                array('%s'),
                array('%d')
            );
            
            // Downgrade to free
            update_post_meta($subscription->expert_id, '_rfm_subscription_plan', self::PLAN_FREE);
            update_post_meta($subscription->expert_id, '_rfm_subscription_status', 'expired');
            
            // Send notification email
            do_action('rfm_subscription_expired', $subscription->expert_id, $subscription);
        }
    }
    
    /**
     * Check if expert can use feature based on plan
     */
    public static function can_use_feature($expert_id, $feature) {
        $plan = self::get_instance()->get_expert_plan($expert_id);
        
        $feature_map = array(
            'banner_image' => array(self::PLAN_PREMIUM),
            'multiple_categories' => array(self::PLAN_STANDARD, self::PLAN_PREMIUM),
            'unlimited_categories' => array(self::PLAN_PREMIUM),
            'featured_badge' => array(self::PLAN_STANDARD, self::PLAN_PREMIUM),
            'messaging' => array(self::PLAN_STANDARD, self::PLAN_PREMIUM),
            'booking' => array(self::PLAN_PREMIUM),
            'top_placement' => array(self::PLAN_PREMIUM)
        );
        
        if (!isset($feature_map[$feature])) {
            return true; // Feature not restricted
        }
        
        return in_array($plan, $feature_map[$feature]);
    }
}
