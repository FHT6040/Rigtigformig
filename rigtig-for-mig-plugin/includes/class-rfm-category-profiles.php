<?php
/**
 * Category-Specific Profile Management
 * 
 * Handles storing and retrieving category-specific profile data
 * (educations, specializations, about me, years experience)
 *
 * @package Rigtig_For_Mig
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Category_Profiles {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // AJAX handlers for category profile updates
        add_action('wp_ajax_rfm_save_category_profile', array($this, 'save_category_profile'));
        add_action('wp_ajax_rfm_save_general_profile', array($this, 'save_general_profile'));
    }
    
    /**
     * Get category profile data for an expert
     * 
     * @param int $expert_id Expert post ID
     * @param int $category_id Category term ID
     * @return array Category profile data
     */
    public function get_category_profile($expert_id, $category_id) {
        $meta_key = '_rfm_category_profile_' . $category_id;
        $profile = get_post_meta($expert_id, $meta_key, true);
        
        if (!is_array($profile)) {
            // Return default structure
            $profile = array(
                'about_me' => '',
                'years_experience' => 0,
                'experience_start_year' => '',
                'educations' => array(),
                'specializations' => array()
            );
        }
        
        return $profile;
    }
    
    /**
     * Save category profile data
     * 
     * @param int $expert_id Expert post ID
     * @param int $category_id Category term ID
     * @param array $data Profile data
     * @return bool Success
     */
    public function update_category_profile($expert_id, $category_id, $data) {
        $meta_key = '_rfm_category_profile_' . $category_id;
        
        $profile = array(
            'about_me' => sanitize_textarea_field($data['about_me'] ?? ''),
            'years_experience' => absint($data['years_experience'] ?? 0),
            'experience_start_year' => absint($data['experience_start_year'] ?? 0),
            'educations' => $this->sanitize_educations($data['educations'] ?? array()),
            'specializations' => array_map('absint', $data['specializations'] ?? array())
        );
        
        return update_post_meta($expert_id, $meta_key, $profile);
    }
    
    /**
     * Sanitize educations array
     */
    private function sanitize_educations($educations) {
        if (!is_array($educations)) {
            return array();
        }
        
        $sanitized = array();
        foreach ($educations as $edu) {
            if (empty($edu['name'])) {
                continue;
            }
            
            $sanitized[] = array(
                'name' => sanitize_text_field($edu['name']),
                'institution' => sanitize_text_field($edu['institution'] ?? ''),
                'year_start' => sanitize_text_field($edu['year_start'] ?? ''),
                'year_end' => sanitize_text_field($edu['year_end'] ?? ''),
                'experience_start_year' => absint($edu['experience_start_year'] ?? 0),
                'description' => sanitize_textarea_field($edu['description'] ?? ''),
                'image_id' => absint($edu['image_id'] ?? 0)
            );
        }
        
        return $sanitized;
    }
    
    /**
     * Get specializations for a specific category
     * 
     * @param int $category_id Category term ID
     * @return array Specialization terms
     */
    public function get_specializations_for_category($category_id) {
        // Get all specializations
        $all_specs = get_terms(array(
            'taxonomy' => 'rfm_specialization',
            'hide_empty' => false
        ));
        
        if (is_wp_error($all_specs)) {
            return array();
        }
        
        // In future, could filter by category relationship
        // For now, return all specializations
        return $all_specs;
    }
    
    /**
     * AJAX: Save general profile (name, contact, languages, categories)
     */
    public function save_general_profile() {
        check_ajax_referer('rfm_dashboard_tabbed', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind.', 'rigtig-for-mig')));
        }
        
        $user_id = get_current_user_id();
        $expert_id = intval($_POST['expert_id']);
        
        // Verify ownership
        $post = get_post($expert_id);
        if (!$post || $post->post_author != $user_id) {
            wp_send_json_error(array('message' => __('Du har ikke tilladelse.', 'rigtig-for-mig')));
        }
        
        // Get plan for validation
        $plan = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);
        
        // Update post title (name)
        wp_update_post(array(
            'ID' => $expert_id,
            'post_title' => sanitize_text_field($_POST['name'])
        ));
        
        // Update basic meta
        update_post_meta($expert_id, '_rfm_email', sanitize_email($_POST['email']));
        update_post_meta($expert_id, '_rfm_phone', sanitize_text_field($_POST['phone'] ?? ''));
        
        // Update fields based on plan
        if ($plan === 'standard' || $plan === 'premium') {
            update_post_meta($expert_id, '_rfm_website', esc_url_raw($_POST['website'] ?? ''));
            update_post_meta($expert_id, '_rfm_company_name', sanitize_text_field($_POST['company_name'] ?? ''));
        }
        
        // Update languages
        if (isset($_POST['languages'])) {
            $languages = array_map('sanitize_text_field', $_POST['languages']);
            update_post_meta($expert_id, '_rfm_languages', $languages);
        } else {
            update_post_meta($expert_id, '_rfm_languages', array());
        }
        
        // Update categories with limit validation
        $max_categories = array(
            'free' => 1,
            'standard' => 2,
            'premium' => 99
        );
        $allowed_cats = $max_categories[$plan] ?? 1;
        
        if (isset($_POST['categories'])) {
            $categories = array_map('intval', $_POST['categories']);
            $categories = array_slice($categories, 0, $allowed_cats);
            wp_set_object_terms($expert_id, $categories, 'rfm_category');
        }
        
        wp_send_json_success(array(
            'message' => __('✅ Generelle oplysninger gemt!', 'rigtig-for-mig')
        ));
    }
    
    /**
     * AJAX: Save category-specific profile
     */
    public function save_category_profile() {
        check_ajax_referer('rfm_dashboard_tabbed', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind.', 'rigtig-for-mig')));
        }
        
        $user_id = get_current_user_id();
        $expert_id = intval($_POST['expert_id']);
        $category_id = intval($_POST['category_id']);
        
        // Verify ownership
        $post = get_post($expert_id);
        if (!$post || $post->post_author != $user_id) {
            wp_send_json_error(array('message' => __('Du har ikke tilladelse.', 'rigtig-for-mig')));
        }
        
        // Verify expert has this category
        $expert_categories = wp_get_object_terms($expert_id, 'rfm_category', array('fields' => 'ids'));
        if (!in_array($category_id, $expert_categories)) {
            wp_send_json_error(array('message' => __('Du har ikke denne kategori.', 'rigtig-for-mig')));
        }
        
        // Get plan for validation
        $plan = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);
        
        // Define limits
        $max_educations = array(
            'free' => 1,
            'standard' => 3,
            'premium' => 7
        );
        $max_specs = array(
            'free' => 1,
            'standard' => 3,
            'premium' => 7
        );
        
        $allowed_educations = $max_educations[$plan] ?? 1;
        $allowed_specs = $max_specs[$plan] ?? 1;
        
        // Prepare data
        $educations = isset($_POST['educations']) ? $_POST['educations'] : array();
        $educations = array_slice($educations, 0, $allowed_educations);
        
        $specializations = isset($_POST['specializations']) ? array_map('intval', $_POST['specializations']) : array();
        $specializations = array_slice($specializations, 0, $allowed_specs);
        
        $data = array(
            'about_me' => $_POST['about_me'] ?? '',
            'years_experience' => $_POST['years_experience'] ?? 0,
            'experience_start_year' => $_POST['experience_start_year'] ?? '',
            'educations' => $educations,
            'specializations' => $specializations
        );
        
        // Save category profile
        $this->update_category_profile($expert_id, $category_id, $data);
        
        // Also update the global specializations taxonomy relationship
        // Get all specializations from all category profiles
        $all_specs = array();
        foreach ($expert_categories as $cat_id) {
            $cat_profile = $this->get_category_profile($expert_id, $cat_id);
            if (!empty($cat_profile['specializations'])) {
                $all_specs = array_merge($all_specs, $cat_profile['specializations']);
            }
        }
        $all_specs = array_unique($all_specs);
        wp_set_object_terms($expert_id, $all_specs, 'rfm_specialization');
        
        $category = get_term($category_id, 'rfm_category');
        $category_name = $category ? $category->name : '';
        
        wp_send_json_success(array(
            'message' => sprintf(__('✅ Profil for "%s" gemt!', 'rigtig-for-mig'), $category_name)
        ));
    }
    
    /**
     * Get combined data for frontend display
     * 
     * @param int $expert_id Expert post ID
     * @param int|null $category_id Specific category (null for all)
     * @return array Combined profile data
     */
    public function get_display_data($expert_id, $category_id = null) {
        if ($category_id) {
            return $this->get_category_profile($expert_id, $category_id);
        }
        
        // Get all category profiles combined
        $expert_categories = wp_get_object_terms($expert_id, 'rfm_category', array('fields' => 'ids'));
        
        $combined = array(
            'about_me' => '',
            'years_experience' => 0,
            'educations' => array(),
            'specializations' => array()
        );
        
        foreach ($expert_categories as $cat_id) {
            $profile = $this->get_category_profile($expert_id, $cat_id);
            
            // Use first non-empty about_me
            if (empty($combined['about_me']) && !empty($profile['about_me'])) {
                $combined['about_me'] = $profile['about_me'];
            }
            
            // Use highest years_experience
            if ($profile['years_experience'] > $combined['years_experience']) {
                $combined['years_experience'] = $profile['years_experience'];
            }
            
            // Combine educations
            $combined['educations'] = array_merge($combined['educations'], $profile['educations']);
            
            // Combine specializations
            $combined['specializations'] = array_merge($combined['specializations'], $profile['specializations']);
        }
        
        // Remove duplicate specializations
        $combined['specializations'] = array_unique($combined['specializations']);
        
        return $combined;
    }
    
    /**
     * Calculate years of experience from start year
     * 
     * @param int $start_year Year started
     * @return int Years of experience
     */
    public static function calculate_experience_years($start_year) {
        if (empty($start_year) || $start_year < 1950) {
            return 0;
        }
        
        $current_year = (int) date('Y');
        return max(0, $current_year - (int) $start_year);
    }
}
