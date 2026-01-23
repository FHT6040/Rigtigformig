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
        add_filter('posts_search', array($this, 'extend_expert_search'), 10, 2);
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
        if ($query->is_post_type_archive('rfm_expert') || (is_search() && $query->get('post_type') === 'rfm_expert')) {

            // Set posts per page for search results (show all results)
            if ($query->is_search()) {
                $query->set('posts_per_page', -1); // Show all results for search
            }

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
            
            // Location filter with radius support
            if (isset($_GET['rfm_location']) && !empty($_GET['rfm_location'])) {
                $location = sanitize_text_field($_GET['rfm_location']);
                $radius = isset($_GET['rfm_radius']) ? floatval($_GET['rfm_radius']) : 0;

                // If radius is specified, use coordinate-based search
                if ($radius > 0) {
                    // Try to get coordinates from postal code first
                    $coordinates = RFM_Postal_Codes::get_coordinates($location);

                    // If not found as postal code, try searching by city name
                    if (!$coordinates && class_exists('RFM_Postal_Codes')) {
                        $coordinates = RFM_Postal_Codes::get_coordinates_by_city($location);
                    }

                    if ($coordinates) {
                        // Find experts within radius
                        $expert_ids = $this->find_experts_within_radius(
                            $coordinates['latitude'],
                            $coordinates['longitude'],
                            $radius
                        );

                        if (!empty($expert_ids)) {
                            $query->set('post__in', $expert_ids);
                        } else {
                            // No experts found within radius - return empty result
                            $query->set('post__in', array(0));
                        }
                    } else {
                        // Invalid postal code and city not found - fall back to city search
                        $meta_query = $query->get('meta_query') ?: array();
                        $meta_query[] = array(
                            'key' => '_rfm_city',
                            'value' => $location,
                            'compare' => 'LIKE'
                        );
                        $query->set('meta_query', $meta_query);
                    }
                } else {
                    // No radius - use traditional city name search
                    $meta_query = $query->get('meta_query') ?: array();
                    $meta_query[] = array(
                        'key' => '_rfm_city',
                        'value' => $location,
                        'compare' => 'LIKE'
                    );
                    $query->set('meta_query', $meta_query);
                }
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
     * Extend expert search to include taxonomies and meta fields
     *
     * Searches in:
     * - Post title (default)
     * - Post content (default)
     * - Taxonomies: rfm_category, rfm_specialization
     * - Meta fields: _rfm_about_me
     *
     * @param string $search SQL search string
     * @param WP_Query $query The WP_Query instance
     * @return string Modified SQL search string
     */
    public function extend_expert_search($search, $query) {
        global $wpdb;

        // Only modify expert searches with a search term
        if (empty($search) || !$query->is_search() || $query->get('post_type') !== 'rfm_expert') {
            return $search;
        }

        $search_term = $query->get('s');
        if (empty($search_term)) {
            return $search;
        }

        // Get the search term prepared for SQL LIKE
        $like = '%' . $wpdb->esc_like($search_term) . '%';

        // Build extended search query
        $search_parts = array();

        // 1. Explicit search in post_title (to handle special characters like "—")
        $search_parts[] = $wpdb->prepare("
            {$wpdb->posts}.post_title LIKE %s
        ", $like);

        // 2. Search in post_content
        $search_parts[] = $wpdb->prepare("
            {$wpdb->posts}.post_content LIKE %s
        ", $like);

        // 3. Search in taxonomies (rfm_category, rfm_specialization)
        $search_parts[] = $wpdb->prepare("
            {$wpdb->posts}.ID IN (
                SELECT DISTINCT tr.object_id
                FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tt.taxonomy IN ('rfm_category', 'rfm_specialization')
                AND t.name LIKE %s
            )
        ", $like);

        // 4. Search in meta fields (_rfm_about_me)
        $search_parts[] = $wpdb->prepare("
            {$wpdb->posts}.ID IN (
                SELECT DISTINCT post_id
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_rfm_about_me'
                AND meta_value LIKE %s
            )
        ", $like);

        // Combine all search parts with OR
        $extended_search = '(' . implode(' OR ', $search_parts) . ')';

        // Replace the default WordPress search with our custom search
        // We handle post_title and post_content ourselves now to support special characters
        $search = " AND {$extended_search} ";

        return $search;
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
     * Find experts within a given radius from coordinates
     *
     * @param float $latitude Center latitude
     * @param float $longitude Center longitude
     * @param float $radius_km Radius in kilometers
     * @return array Array of expert post IDs within radius, sorted by distance
     */
    private function find_experts_within_radius($latitude, $longitude, $radius_km) {
        global $wpdb;

        // Get all experts with coordinates
        $experts_with_coords = $wpdb->get_results("
            SELECT
                p.ID,
                lat.meta_value AS latitude,
                lng.meta_value AS longitude
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} lat ON p.ID = lat.post_id AND lat.meta_key = '_rfm_latitude'
            INNER JOIN {$wpdb->postmeta} lng ON p.ID = lng.post_id AND lng.meta_key = '_rfm_longitude'
            WHERE p.post_type = 'rfm_expert'
            AND p.post_status = 'publish'
            AND lat.meta_value != ''
            AND lng.meta_value != ''
        ");

        $expert_ids = array();
        $expert_distances = array();

        foreach ($experts_with_coords as $expert) {
            $distance = RFM_Postal_Codes::calculate_distance(
                $latitude,
                $longitude,
                floatval($expert->latitude),
                floatval($expert->longitude)
            );

            if ($distance <= $radius_km) {
                $expert_ids[] = intval($expert->ID);
                $expert_distances[intval($expert->ID)] = $distance;
            }
        }

        // Sort by distance (closest first)
        if (!empty($expert_ids)) {
            usort($expert_ids, function($a, $b) use ($expert_distances) {
                return $expert_distances[$a] <=> $expert_distances[$b];
            });
        }

        return $expert_ids;
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
