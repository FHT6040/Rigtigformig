<?php
/**
 * Shortcodes
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Shortcodes {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('rfm_expert_list', array($this, 'expert_list_shortcode'));
        add_shortcode('rfm_expert_search', array($this, 'expert_search_shortcode'));
        add_shortcode('rfm_category_boxes', array($this, 'category_boxes_shortcode'));
    }
    
    /**
     * Expert list shortcode
     * Usage: [rfm_expert_list category="krop-bevaegelse" limit="12" debug="true"]
     */
    public function expert_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
            'limit' => 12,
            'orderby' => 'rating',
            'columns' => 3,
            'debug' => false
        ), $atts);
        
        $args = array(
            'post_type' => 'rfm_expert',
            'posts_per_page' => intval($atts['limit']),
            'post_status' => 'publish'
        );
        
        if ($atts['category']) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'rfm_category',
                    'field' => 'slug',
                    'terms' => $atts['category']
                )
            );
        }
        
        // Sort by rating or date
        // Note: We always fetch all posts and sort them to include experts without ratings

        $query = new WP_Query($args);

        // If sorting by rating, manually sort the posts array to include experts without ratings
        if ($atts['orderby'] === 'rating' && $query->have_posts()) {
            $posts = $query->posts;

            usort($posts, function($a, $b) {
                $rating_a = floatval(get_post_meta($a->ID, '_rfm_average_rating', true));
                $rating_b = floatval(get_post_meta($b->ID, '_rfm_average_rating', true));

                // Sort by rating descending
                if ($rating_b != $rating_a) {
                    return $rating_b <=> $rating_a;
                }

                // If ratings are equal, sort by date descending
                return strtotime($b->post_date) <=> strtotime($a->post_date);
            });

            $query->posts = $posts;
            $query->post_count = count($posts);
        }
        
        ob_start();
        
        // Debug mode
        if ($atts['debug'] === 'true' || $atts['debug'] === '1') {
            echo '<div style="background: #fff3cd; padding: 20px; margin: 20px 0; border: 2px solid #856404; border-radius: 5px;">';
            echo '<h3 style="margin-top: 0;">🔍 DEBUG MODE</h3>';
            echo '<p><strong>Query Args:</strong></p>';
            echo '<pre style="background: white; padding: 10px; overflow: auto;">' . print_r($args, true) . '</pre>';
            echo '<p><strong>Found Posts (published):</strong> ' . $query->found_posts . '</p>';
            echo '<p><strong>Post Count:</strong> ' . $query->post_count . '</p>';
            
            // Check all rfm_expert posts regardless of status
            $all_experts = new WP_Query(array(
                'post_type' => 'rfm_expert',
                'posts_per_page' => -1,
                'post_status' => 'any'
            ));
            echo '<p><strong>Total Expert Posts (all statuses):</strong> ' . $all_experts->found_posts . '</p>';
            
            if ($all_experts->have_posts()) {
                echo '<p><strong>All Expert Posts:</strong></p>';
                echo '<ul>';
                while ($all_experts->have_posts()) {
                    $all_experts->the_post();
                    $status = get_post_status();
                    $status_label = $status === 'publish' ? '✅ Published' : '❌ ' . ucfirst($status);
                    echo '<li><strong>' . get_the_title() . '</strong> - ' . $status_label . ' (ID: ' . get_the_ID() . ')</li>';
                }
                wp_reset_postdata();
                echo '</ul>';
            } else {
                echo '<p style="color: red;"><strong>⚠️ NO EXPERT POSTS FOUND AT ALL!</strong></p>';
            }
            
            echo '</div>';
        }
        
        if ($query->have_posts()):
            ?>
            <div class="rfm-expert-grid rfm-columns-<?php echo esc_attr($atts['columns']); ?>">
                <?php while ($query->have_posts()): $query->the_post(); ?>
                    <?php $this->render_expert_card(get_the_ID(), $atts['category']); ?>
                <?php endwhile; ?>
            </div>
            <?php
            wp_reset_postdata();
        else:
            ?>
            <p><?php _e('Ingen eksperter fundet.', 'rigtig-for-mig'); ?></p>
            <?php
        endif;
        
        return ob_get_clean();
    }
    
    /**
     * Expert search shortcode
     * Usage: [rfm_expert_search]
     */
    public function expert_search_shortcode($atts) {
        $categories = get_terms(array(
            'taxonomy' => 'rfm_category',
            'hide_empty' => false
        ));
        
        ob_start();
        ?>
        <div class="rfm-search-box">
            <form id="rfm-expert-search-form" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                <input type="hidden" name="post_type" value="rfm_expert" />
                
                <div class="rfm-search-fields">
                    <div class="rfm-search-field">
                        <input type="text" 
                               name="s" 
                               placeholder="<?php esc_attr_e('Søg efter ekspert eller specialisering...', 'rigtig-for-mig'); ?>" 
                               value="<?php echo get_search_query(); ?>" />
                    </div>
                    
                    <div class="rfm-search-field">
                        <select name="rfm_category">
                            <option value=""><?php _e('Alle Kategorier', 'rigtig-for-mig'); ?></option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->slug); ?>" <?php selected(isset($_GET['rfm_category']) && $_GET['rfm_category'] === $category->slug); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="rfm-search-field">
                        <input type="text" 
                               name="rfm_location" 
                               placeholder="<?php esc_attr_e('By', 'rigtig-for-mig'); ?>" 
                               value="<?php echo isset($_GET['rfm_location']) ? esc_attr($_GET['rfm_location']) : ''; ?>" />
                    </div>
                    
                    <button type="submit" class="rfm-search-btn">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Søg', 'rigtig-for-mig'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Category boxes shortcode (de 4 hovedkategorier)
     * Usage: [rfm_category_boxes]
     */
    public function category_boxes_shortcode($atts) {
        $categories = get_terms(array(
            'taxonomy' => 'rfm_category',
            'hide_empty' => false
        ));
        
        if (!$categories || is_wp_error($categories)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="rfm-category-boxes">
            <?php foreach ($categories as $category): ?>
                <?php
                $color = RFM_Taxonomies::get_category_color($category->term_id);
                $icon = RFM_Taxonomies::get_category_icon($category->term_id);
                $link = get_term_link($category);
                ?>
                <div class="rfm-category-box" style="background-color: <?php echo esc_attr($color); ?>;">
                    <a href="<?php echo esc_url($link); ?>">
                        <div class="rfm-category-icon">
                            <i class="rfm-icon-<?php echo esc_attr($icon); ?>"></i>
                        </div>
                        <h3><?php echo esc_html($category->name); ?></h3>
                        <p><?php echo esc_html($category->description); ?></p>
                        <span class="rfm-category-count"><?php echo $category->count; ?> <?php _e('eksperter', 'rigtig-for-mig'); ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render expert card
     * 
     * @param int $expert_id Expert post ID
     * @param string $category_slug Optional category slug to link to
     */
    private function render_expert_card($expert_id, $category_slug = '') {
        $average_rating = RFM_Ratings::get_instance()->get_average_rating($expert_id);
        $rating_count = RFM_Ratings::get_instance()->get_rating_count($expert_id);
        $categories = get_the_terms($expert_id, 'rfm_category');
        $plan = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);
        
        // Get company name (only for Standard/Premium)
        $company_name = '';
        if ($plan === 'standard' || $plan === 'premium') {
            $company_name = get_post_meta($expert_id, '_rfm_company_name', true);
        }
        
        // Build permalink with optional category parameter
        $permalink = get_permalink($expert_id);
        if ($category_slug) {
            $permalink = add_query_arg('kategori', $category_slug, $permalink);
        }
        
        // Get education data - first check category profiles, then fall back to old format
        $first_education = null;
        $experience_years = 0;
        
        // Try to get from category profile if we have a category
        if ($category_slug && class_exists('RFM_Category_Profiles')) {
            $category_term = get_term_by('slug', $category_slug, 'rfm_category');
            if ($category_term) {
                $cat_profile = RFM_Category_Profiles::get_instance()->get_category_profile($expert_id, $category_term->term_id);
                if (!empty($cat_profile['educations'])) {
                    $first_education = $cat_profile['educations'][0];
                }
                if (!empty($cat_profile['experience_start_year'])) {
                    $experience_years = RFM_Category_Profiles::calculate_experience_years($cat_profile['experience_start_year']);
                }
            }
        }
        
        // Fallback to old education format
        if (!$first_education) {
            $educations = get_post_meta($expert_id, '_rfm_educations', true);
            if (!empty($educations) && is_array($educations)) {
                $first_education = $educations[0];
                
                // Calculate years of experience from experience_start_year
                if (!empty($first_education['experience_start_year']) && $experience_years == 0) {
                    $start_year = (int) $first_education['experience_start_year'];
                    $current_year = (int) date('Y');
                    $experience_years = max(0, $current_year - $start_year);
                }
            }
        }
        
        // Get languages
        $languages = get_post_meta($expert_id, '_rfm_languages', true);
        $language_names = array();
        if (!empty($languages) && is_array($languages)) {
            // Get language field labels from flexible fields system
            $flexible_fields = RFM_Flexible_Fields_System::get_instance();
            $all_fields = $flexible_fields->get_fields();
            $language_fields = array();
            if (isset($all_fields['sprog']) && isset($all_fields['sprog']['fields'])) {
                $language_fields = $all_fields['sprog']['fields'];
            }

            foreach ($languages as $lang) {
                $lang_key = strtolower($lang);
                // Use label from flexible fields system if available, otherwise use capitalized key
                if (isset($language_fields[$lang_key]['label'])) {
                    $language_names[] = $language_fields[$lang_key]['label'];
                } else {
                    $language_names[] = ucfirst($lang);
                }
            }
        }
        
        ?>
        <div class="rfm-expert-card <?php echo $plan === 'premium' ? 'rfm-featured' : ''; ?>">
            <a href="<?php echo esc_url($permalink); ?>">
                <div class="rfm-expert-card-image">
                    <?php echo get_the_post_thumbnail($expert_id, 'medium'); ?>
                    <?php if ($plan === 'premium'): ?>
                        <span class="rfm-featured-badge"><?php _e('Premium', 'rigtig-for-mig'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="rfm-expert-card-content">
                    <?php if ($company_name): ?>
                        <p class="rfm-expert-card-company"><?php echo esc_html($company_name); ?></p>
                    <?php endif; ?>
                    
                    <h3><?php echo get_the_title($expert_id); ?></h3>
                    
                    <?php if ($categories): ?>
                        <div class="rfm-expert-card-categories">
                            <?php foreach ($categories as $category): ?>
                                <span class="rfm-category-tag"><?php echo esc_html($category->name); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($first_education && !empty($first_education['name'])): ?>
                        <div class="rfm-expert-card-education">
                            <span class="rfm-education-name"><?php echo esc_html($first_education['name']); ?></span>
                            <?php if ($experience_years > 0): ?>
                                <span class="rfm-experience-years">
                                    <?php printf(_n('%d års erfaring', '%d års erfaring', $experience_years, 'rigtig-for-mig'), $experience_years); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($language_names)): ?>
                        <div class="rfm-expert-card-languages">
                            <span class="rfm-languages-label"><?php _e('Sprog:', 'rigtig-for-mig'); ?></span>
                            <?php echo esc_html(implode(', ', array_slice($language_names, 0, 3))); ?>
                            <?php if (count($language_names) > 3): ?>
                                <span class="rfm-more-languages">+<?php echo count($language_names) - 3; ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="rfm-expert-card-rating">
                        <?php echo RFM_Ratings::display_stars($average_rating); ?>
                        <span class="rfm-rating-count">(<?php echo $rating_count; ?>)</span>
                    </div>
                </div>
            </a>
        </div>
        <?php
    }
}
