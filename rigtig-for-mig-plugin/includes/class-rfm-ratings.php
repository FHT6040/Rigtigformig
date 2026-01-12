<?php
/**
 * Ratings System
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Ratings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // AJAX handlers
        add_action('wp_ajax_rfm_submit_rating', array($this, 'submit_rating'));
        add_action('wp_ajax_rfm_get_ratings', array($this, 'get_ratings_ajax'));
        
        // Update average rating when a rating is added/updated
        add_action('rfm_rating_submitted', array($this, 'update_average_rating'), 10, 1);
    }
    
    /**
     * Submit a rating
     */
    public function submit_rating() {
        check_ajax_referer('rfm_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('Du skal være logget ind for at bedømme en ekspert.', 'rigtig-for-mig')
            ));
        }
        
        $expert_id = isset($_POST['expert_id']) ? intval($_POST['expert_id']) : 0;
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
        $review = isset($_POST['review']) ? sanitize_textarea_field($_POST['review']) : '';
        $user_id = get_current_user_id();
        
        // Validate
        if (!$expert_id || !$rating || $rating < 1 || $rating > 5) {
            wp_send_json_error(array(
                'message' => __('Ugyldig bedømmelse.', 'rigtig-for-mig')
            ));
        }
        
        // Check if post exists and is an expert
        if (get_post_type($expert_id) !== 'rfm_expert') {
            wp_send_json_error(array(
                'message' => __('Ekspert ikke fundet.', 'rigtig-for-mig')
            ));
        }
        
        // Prevent self-rating
        if (get_post_field('post_author', $expert_id) == $user_id) {
            wp_send_json_error(array(
                'message' => __('Du kan ikke bedømme din egen profil.', 'rigtig-for-mig')
            ));
        }
        
        global $wpdb;
        $table = RFM_Database::get_table_name('ratings');
        
        // Check if user has already rated this expert
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, created_at FROM $table WHERE expert_id = %d AND user_id = %d",
            $expert_id,
            $user_id
        ));
        
        if ($existing) {
            // Check 180-day cooldown
            $days_since_rating = floor((time() - strtotime($existing->created_at)) / (60 * 60 * 24));
            
            if ($days_since_rating < 180) {
                $days_remaining = 180 - $days_since_rating;
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Du kan først bedømme denne ekspert igen om %d dage. Du anmeldte sidst for %d dage siden.', 'rigtig-for-mig'),
                        $days_remaining,
                        $days_since_rating
                    )
                ));
            }
            
            // Update existing rating (after 180 days)
            $result = $wpdb->update(
                $table,
                array(
                    'rating' => $rating,
                    'review' => $review,
                    'created_at' => current_time('mysql') // Update timestamp
                ),
                array(
                    'expert_id' => $expert_id,
                    'user_id' => $user_id
                ),
                array('%d', '%s', '%s'),
                array('%d', '%d')
            );
        } else {
            // Insert new rating
            $result = $wpdb->insert(
                $table,
                array(
                    'expert_id' => $expert_id,
                    'user_id' => $user_id,
                    'rating' => $rating,
                    'review' => $review
                ),
                array('%d', '%d', '%d', '%s')
            );
        }

        if ($result !== false) {
            // Get rating ID
            $rating_id = $existing ? $existing->id : $wpdb->insert_id;

            // Update average rating
            $this->update_average_rating($expert_id);

            do_action('rfm_rating_submitted', $expert_id, $user_id, $rating);
            do_action('rfm_rating_created', $rating_id, $expert_id, $user_id);
            
            wp_send_json_success(array(
                'message' => __('Tak for din bedømmelse!', 'rigtig-for-mig'),
                'average' => $this->get_average_rating($expert_id),
                'count' => $this->get_rating_count($expert_id)
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig')
            ));
        }
    }
    
    /**
     * Get ratings via AJAX
     */
    public function get_ratings_ajax() {
        check_ajax_referer('rfm_nonce', 'nonce');
        
        $expert_id = isset($_POST['expert_id']) ? intval($_POST['expert_id']) : 0;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10;
        
        if (!$expert_id) {
            wp_send_json_error();
        }
        
        $ratings = $this->get_ratings($expert_id, $page, $per_page);
        
        wp_send_json_success($ratings);
    }
    
    /**
     * Get ratings for an expert
     */
    public function get_ratings($expert_id, $page = 1, $per_page = 10) {
        global $wpdb;
        $table = RFM_Database::get_table_name('ratings');
        
        $offset = ($page - 1) * $per_page;
        
        $ratings = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name as user_name 
            FROM $table r 
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID 
            WHERE r.expert_id = %d 
            ORDER BY r.created_at DESC 
            LIMIT %d OFFSET %d",
            $expert_id,
            $per_page,
            $offset
        ));
        
        return $ratings;
    }
    
    /**
     * Get average rating
     */
    public function get_average_rating($expert_id) {
        $average = get_post_meta($expert_id, '_rfm_average_rating', true);
        return $average ? floatval($average) : 0;
    }
    
    /**
     * Get rating count
     */
    public function get_rating_count($expert_id) {
        $count = get_post_meta($expert_id, '_rfm_rating_count', true);
        return $count ? intval($count) : 0;
    }
    
    /**
     * Update average rating for an expert
     */
    public function update_average_rating($expert_id) {
        global $wpdb;
        $table = RFM_Database::get_table_name('ratings');
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(rating) as average, COUNT(*) as count 
            FROM $table 
            WHERE expert_id = %d",
            $expert_id
        ));
        
        if ($stats) {
            update_post_meta($expert_id, '_rfm_average_rating', round($stats->average, 1));
            update_post_meta($expert_id, '_rfm_rating_count', $stats->count);
        }
    }
    
    /**
     * Check if user has rated an expert
     */
    public function has_user_rated($expert_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        global $wpdb;
        $table = RFM_Database::get_table_name('ratings');
        
        $rating = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE expert_id = %d AND user_id = %d",
            $expert_id,
            $user_id
        ));
        
        return !empty($rating);
    }
    
    /**
     * Get user's rating for an expert
     */
    public function get_user_rating($expert_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return null;
        }
        
        global $wpdb;
        $table = RFM_Database::get_table_name('ratings');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE expert_id = %d AND user_id = %d",
            $expert_id,
            $user_id
        ));
    }
    
    /**
     * Get all ratings by a user
     */
    public function get_user_ratings($user_id = null, $limit = 20) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return array();
        }
        
        global $wpdb;
        $table = RFM_Database::get_table_name('ratings');
        
        $ratings = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, p.post_title as expert_name 
            FROM $table r 
            LEFT JOIN {$wpdb->posts} p ON r.expert_id = p.ID 
            WHERE r.user_id = %d 
            ORDER BY r.created_at DESC 
            LIMIT %d",
            $user_id,
            $limit
        ));
        
        return $ratings;
    }
    
    /**
     * Check if user can rate expert (180-day cooldown)
     */
    public function can_user_rate($expert_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        global $wpdb;
        $table = RFM_Database::get_table_name('ratings');
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT created_at FROM $table WHERE expert_id = %d AND user_id = %d",
            $expert_id,
            $user_id
        ));
        
        if (!$existing) {
            return true; // Never rated before
        }
        
        // Check 180-day cooldown
        $days_since_rating = floor((time() - strtotime($existing->created_at)) / (60 * 60 * 24));
        
        return $days_since_rating >= 180;
    }
    
    /**
     * Display rating stars HTML
     */
    public static function display_stars($rating, $show_number = true) {
        $rating = floatval($rating);
        $full_stars = floor($rating);
        $half_star = ($rating - $full_stars) >= 0.5;
        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
        
        $html = '<div class="rfm-rating-stars">';
        
        // Full stars
        for ($i = 0; $i < $full_stars; $i++) {
            $html .= '<span class="rfm-star rfm-star-full">★</span>';
        }
        
        // Half star
        if ($half_star) {
            $html .= '<span class="rfm-star rfm-star-half">★</span>';
        }
        
        // Empty stars
        for ($i = 0; $i < $empty_stars; $i++) {
            $html .= '<span class="rfm-star rfm-star-empty">☆</span>';
        }
        
        if ($show_number) {
            $html .= ' <span class="rfm-rating-number">' . number_format($rating, 1) . '</span>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}
