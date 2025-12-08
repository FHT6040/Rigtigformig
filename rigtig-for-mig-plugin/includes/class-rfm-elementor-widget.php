<?php
/**
 * Elementor Expert Profile Widget
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Elementor_Expert_Widget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'rfm_expert_profile';
    }
    
    public function get_title() {
        return __('Ekspert Profil Data', 'rigtig-for-mig');
    }
    
    public function get_icon() {
        return 'eicon-person';
    }
    
    public function get_categories() {
        return ['general'];
    }
    
    protected function register_controls() {
        
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Indhold', 'rigtig-for-mig'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'show_element',
            [
                'label' => __('Vis Element', 'rigtig-for-mig'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'email',
                'options' => [
                    'email' => __('Email', 'rigtig-for-mig'),
                    'phone' => __('Telefon', 'rigtig-for-mig'),
                    'website' => __('Hjemmeside', 'rigtig-for-mig'),
                    'address' => __('Adresse', 'rigtig-for-mig'),
                    'rating' => __('Rating', 'rigtig-for-mig'),
                    'categories' => __('Kategorier', 'rigtig-for-mig'),
                    'specializations' => __('Specialiseringer', 'rigtig-for-mig'),
                    'educations' => __('Uddannelser', 'rigtig-for-mig'),
                    'experience' => __('År i branchen', 'rigtig-for-mig'),
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Style', 'rigtig-for-mig'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'text_color',
            [
                'label' => __('Tekst Farve', 'rigtig-for-mig'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rfm-elementor-widget' => 'color: {{VALUE}}',
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();
        $post_id = get_the_ID();
        
        if (get_post_type($post_id) !== 'rfm_expert') {
            echo '<p>' . __('Dette element virker kun på ekspert sider.', 'rigtig-for-mig') . '</p>';
            return;
        }
        
        echo '<div class="rfm-elementor-widget">';
        
        switch ($settings['show_element']) {
            case 'email':
                $email = get_post_meta($post_id, '_rfm_email', true);
                if ($email) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                }
                break;
                
            case 'phone':
                $phone = get_post_meta($post_id, '_rfm_phone', true);
                if ($phone) {
                    echo '<a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a>';
                }
                break;
                
            case 'website':
                $website = get_post_meta($post_id, '_rfm_website', true);
                if ($website) {
                    echo '<a href="' . esc_url($website) . '" target="_blank">' . esc_html($website) . '</a>';
                }
                break;
                
            case 'address':
                $address = get_post_meta($post_id, '_rfm_address', true);
                $city = get_post_meta($post_id, '_rfm_city', true);
                $postal = get_post_meta($post_id, '_rfm_postal_code', true);
                if ($address || $city) {
                    echo esc_html($address);
                    if ($address && ($city || $postal)) echo ', ';
                    echo esc_html($postal . ' ' . $city);
                }
                break;
                
            case 'rating':
                $average = RFM_Ratings::get_instance()->get_average_rating($post_id);
                $count = RFM_Ratings::get_instance()->get_rating_count($post_id);
                echo RFM_Ratings::display_stars($average);
                echo ' <span class="rfm-rating-count">(' . $count . ')</span>';
                break;
                
            case 'categories':
                $categories = get_the_terms($post_id, 'rfm_category');
                if ($categories) {
                    echo '<div class="rfm-categories">';
                    foreach ($categories as $category) {
                        $color = RFM_Taxonomies::get_category_color($category->term_id);
                        echo '<span class="rfm-category-badge" style="background-color: ' . esc_attr($color) . '; color: white; padding: 5px 15px; border-radius: 15px; margin-right: 5px;">' . esc_html($category->name) . '</span>';
                    }
                    echo '</div>';
                }
                break;
                
            case 'specializations':
                $specs = get_the_terms($post_id, 'rfm_specialization');
                if ($specs) {
                    echo '<div class="rfm-specializations">';
                    foreach ($specs as $spec) {
                        echo '<span class="rfm-spec-tag" style="background: #f5f5f5; padding: 5px 12px; border-radius: 15px; margin-right: 5px; display: inline-block; margin-bottom: 5px;">' . esc_html($spec->name) . '</span>';
                    }
                    echo '</div>';
                }
                break;
                
            case 'educations':
                $educations = get_post_meta($post_id, '_rfm_educations', true);
                if ($educations && is_array($educations)) {
                    echo '<div class="rfm-educations">';
                    foreach ($educations as $edu) {
                        if (!empty($edu['title'])) {
                            echo '<div class="rfm-edu-item" style="margin-bottom: 15px;">';
                            echo '<h4 style="margin: 0 0 5px 0;">' . esc_html($edu['title']) . '</h4>';
                            if (!empty($edu['institution'])) {
                                echo '<p style="margin: 0; color: #666;">' . esc_html($edu['institution']) . '</p>';
                            }
                            if (!empty($edu['year'])) {
                                echo '<p style="margin: 0; color: #999; font-size: 14px;">' . esc_html($edu['year']) . '</p>';
                            }
                            echo '</div>';
                        }
                    }
                    echo '</div>';
                }
                break;
                
            case 'experience':
                $years = get_post_meta($post_id, '_rfm_years_experience', true);
                if ($years) {
                    echo '<div class="rfm-experience">';
                    echo '<strong>' . esc_html($years) . '</strong> ' . __('år i branchen', 'rigtig-for-mig');
                    echo '</div>';
                }
                break;
        }
        
        echo '</div>';
    }
}
