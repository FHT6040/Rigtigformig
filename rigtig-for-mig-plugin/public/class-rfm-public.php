<?php
/**
 * Public-facing functionality
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Public {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_filter('pre_get_posts', array($this, 'modify_expert_query'));
        add_action('template_redirect', array($this, 'handle_expert_actions'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue public scripts and styles
     */
    public function enqueue_scripts() {
        // Enqueue CSS
        wp_enqueue_style(
            'rfm-public',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/public.css',
            array(),
            RFM_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'rfm-public',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/public.js',
            array('jquery'),
            RFM_VERSION,
            true
        );

        // Localize script with data
        wp_localize_script('rfm-public', 'rfmData', array(
            'ajaxurl' => RFM_PLUGIN_URL . 'ajax-handler.php',
            'nonce' => wp_create_nonce('rfm_nonce'),
            'strings' => array(
                'loading' => __('Indlæser...', 'rigtig-for-mig'),
                'error' => __('Der opstod en fejl', 'rigtig-for-mig'),
                'success' => __('Succes!', 'rigtig-for-mig'),
                'confirm_delete' => __('Er du sikker? Dette kan ikke fortrydes!', 'rigtig-for-mig')
            ),
            'user_id' => get_current_user_id(),
            'is_user_logged_in' => is_user_logged_in()
        ));
    }
    
    /**
     * Modify expert query based on search parameters
     */
    public function modify_expert_query($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Expert archive or search
        if ($query->is_post_type_archive('rfm_expert') || (is_search() && get_query_var('post_type') === 'rfm_expert')) {
            
            // Category filter
            if (isset($_GET['rfm_category']) && !empty($_GET['rfm_category'])) {
                $tax_query = $query->get('tax_query') ?: array();
                $tax_query[] = array(
                    'taxonomy' => 'rfm_category',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($_GET['rfm_category'])
                );
                $query->set('tax_query', $tax_query);
            }
            
            // Location filter (by city)
            if (isset($_GET['rfm_location']) && !empty($_GET['rfm_location'])) {
                $meta_query = $query->get('meta_query') ?: array();
                $meta_query[] = array(
                    'key' => '_rfm_city',
                    'value' => sanitize_text_field($_GET['rfm_location']),
                    'compare' => 'LIKE'
                );
                $query->set('meta_query', $meta_query);
            }
            
            // Sort by rating (default)
            if (!$query->get('orderby')) {
                $query->set('meta_key', '_rfm_average_rating');
                $query->set('orderby', 'meta_value_num');
                $query->set('order', 'DESC');
            }
            
            // Boost premium experts
            add_filter('posts_orderby', array($this, 'boost_premium_experts'), 10, 2);
        }
    }
    
    /**
     * Boost premium experts in search results
     */
    public function boost_premium_experts($orderby, $query) {
        if (is_admin() || !$query->is_main_query()) {
            return $orderby;
        }
        
        if ($query->is_post_type_archive('rfm_expert') || (is_search() && get_query_var('post_type') === 'rfm_expert')) {
            global $wpdb;
            
            // Add premium boost to ordering
            $orderby = "
                CASE 
                    WHEN {$wpdb->postmeta}.meta_value = 'premium' THEN 1
                    WHEN {$wpdb->postmeta}.meta_value = 'standard' THEN 2
                    ELSE 3
                END ASC,
                {$orderby}
            ";
        }
        
        return $orderby;
    }
    
    /**
     * Handle expert actions (like email verification confirmation)
     */
    public function handle_expert_actions() {
        // Email verification success
        if (isset($_GET['verified']) && $_GET['verified'] === 'success') {
            add_action('wp_footer', array($this, 'show_verification_success'));
        }
    }
    
    /**
     * Show verification success message
     */
    public function show_verification_success() {
        ?>
        <div class="rfm-notification rfm-notification-success" style="position: fixed; top: 20px; right: 20px; z-index: 99999; background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; border: 1px solid #c3e6cb; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 400px;">
            <button class="rfm-notification-close" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer; color: #155724;">×</button>
            <strong>✅ Email Verificeret!</strong>
            <p style="margin: 10px 0 0 0;">Din email er nu bekræftet. Tak!</p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.rfm-notification-close').on('click', function() {
                $(this).closest('.rfm-notification').fadeOut();
            });
            setTimeout(function() {
                $('.rfm-notification').fadeOut();
            }, 5000);
        });
        </script>
        <?php
    }
}
