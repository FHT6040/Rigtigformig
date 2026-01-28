<?php
/**
 * Expert Profile Display
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Expert_Profile {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_filter('the_content', array($this, 'modify_expert_content'));
        add_filter('single_template', array($this, 'load_expert_template'));
    }
    
    /**
     * Load custom template for expert single pages
     */
    public function load_expert_template($template) {
        if (is_singular('rfm_expert')) {
            $custom_template = RFM_PLUGIN_DIR . 'templates/single-expert.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }
    
    /**
     * Modify expert content display
     */
    public function modify_expert_content($content) {
        if (!is_singular('rfm_expert') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        $expert_id = get_the_ID();
        
        // Add profile sections before content
        $profile_content = $this->get_profile_header($expert_id);
        $profile_content .= $content;
        $profile_content .= $this->get_profile_details($expert_id);
        $profile_content .= $this->get_ratings_section($expert_id);
        $profile_content .= $this->get_message_modal($expert_id);

        return $profile_content;
    }
    
    /**
     * Get profile header HTML
     */
    private function get_profile_header($expert_id) {
        // Get plan first
        $plan = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);
        
        // Banner image only for Standard and Premium plans
        $banner_id = get_post_meta($expert_id, '_rfm_banner_image_id', true);
        $show_banner = ($plan === 'standard' || $plan === 'premium') && $banner_id;
        $banner_url = $show_banner ? wp_get_attachment_image_url($banner_id, 'full') : '';
        
        $phone = get_post_meta($expert_id, '_rfm_phone', true);
        $email = get_post_meta($expert_id, '_rfm_email', true);
        $website = get_post_meta($expert_id, '_rfm_website', true);
        $company_name = get_post_meta($expert_id, '_rfm_company_name', true);
        
        // Company name also only for Standard and Premium
        $show_company = ($plan === 'standard' || $plan === 'premium') && !empty($company_name);
        
        $average_rating = RFM_Ratings::get_instance()->get_average_rating($expert_id);
        $rating_count = RFM_Ratings::get_instance()->get_rating_count($expert_id);
        
        // Check if admin wants to show featured image
        $show_featured = get_option('rfm_show_featured_image', true);
        
        // Get categories for tabs
        $categories = get_the_terms($expert_id, 'rfm_category');
        $has_multiple_categories = $categories && count($categories) > 1;
        
        // Determine active category from URL parameter
        $active_category_slug = isset($_GET['kategori']) ? sanitize_text_field($_GET['kategori']) : '';
        $active_category = null;
        
        if ($categories) {
            if ($active_category_slug) {
                foreach ($categories as $cat) {
                    if ($cat->slug === $active_category_slug) {
                        $active_category = $cat;
                        break;
                    }
                }
            }
            // Default to first category if not specified
            if (!$active_category) {
                $active_category = $categories[0];
            }
        }
        
        ob_start();
        ?>
        <div class="rfm-expert-header">
            <?php if ($banner_url): ?>
                <div class="rfm-banner" style="background-image: url('<?php echo esc_url($banner_url); ?>');"></div>
            <?php endif; ?>
            
            <div class="rfm-profile-main">
                <?php if ($show_featured && has_post_thumbnail($expert_id)): ?>
                    <div class="rfm-profile-image">
                        <?php echo get_the_post_thumbnail($expert_id, 'medium', array('class' => 'rfm-expert-photo')); ?>
                    </div>
                <?php endif; ?>
                
                <div class="rfm-profile-info">
                    <?php if ($show_company): ?>
                        <p class="rfm-expert-company"><?php echo esc_html($company_name); ?></p>
                    <?php endif; ?>
                    
                    <h1 class="rfm-expert-name"><?php the_title(); ?></h1>
                    
                    <div class="rfm-expert-rating">
                        <?php echo RFM_Ratings::display_stars($average_rating); ?>
                        <span class="rfm-rating-count">(<?php echo $rating_count; ?> <?php _e('bedømmelser', 'rigtig-for-mig'); ?>)</span>
                    </div>
                    
                    <div class="rfm-expert-categories">
                        <?php
                        if ($categories) {
                            foreach ($categories as $category) {
                                $color = RFM_Taxonomies::get_category_color($category->term_id);
                                echo '<span class="rfm-category-badge" style="background-color: ' . esc_attr($color) . ';">' . esc_html($category->name) . '</span> ';
                            }
                        }
                        ?>
                    </div>
                    
                    <div class="rfm-expert-contact">
                        <?php if (get_option('rfm_show_phone', true) && $phone): ?>
                            <span class="rfm-contact-item">
                                <i class="dashicons dashicons-phone"></i>
                                <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (get_option('rfm_show_email', true) && $email): ?>
                            <span class="rfm-contact-item">
                                <i class="dashicons dashicons-email"></i>
                                <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (get_option('rfm_show_website', true) && $website): ?>
                            <span class="rfm-contact-item">
                                <i class="dashicons dashicons-admin-site"></i>
                                <a href="<?php echo esc_url($website); ?>" target="_blank"><?php _e('Hjemmeside', 'rigtig-for-mig'); ?></a>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php
                    // Show different actions based on user status
                    if (is_user_logged_in()) {
                        $current_user = wp_get_current_user();
                        $post_author = get_post_field('post_author', $expert_id);

                        if ($current_user->ID == $post_author && in_array('rfm_expert_user', (array) $current_user->roles)) {
                            // Show dashboard and logout for expert viewing own profile
                            ?>
                            <div class="rfm-profile-actions">
                                <a href="<?php echo home_url('/ekspert-dashboard/'); ?>" class="rfm-btn rfm-btn-secondary">
                                    <i class="dashicons dashicons-dashboard"></i>
                                    <?php _e('Gå til dashboard', 'rigtig-for-mig'); ?>
                                </a>
                                <a href="#" id="rfm-logout-btn" class="rfm-btn rfm-btn-logout">
                                    <i class="dashicons dashicons-exit"></i>
                                    <?php _e('Log ud', 'rigtig-for-mig'); ?>
                                </a>
                            </div>
                            <?php
                        } else {
                            // Show "Send Message" button for other logged-in users
                            ?>
                            <div class="rfm-profile-actions">
                                <button type="button" id="rfm-send-message-btn" class="rfm-btn rfm-btn-primary" data-expert-id="<?php echo esc_attr($expert_id); ?>">
                                    <i class="dashicons dashicons-email-alt"></i>
                                    <?php _e('Send besked', 'rigtig-for-mig'); ?>
                                </button>
                            </div>
                            <?php
                        }
                    } else {
                        // Show login prompt for non-logged-in users
                        ?>
                        <div class="rfm-profile-actions">
                            <p class="rfm-login-prompt">
                                <?php _e('Log ind for at sende en besked til denne ekspert', 'rigtig-for-mig'); ?>
                                <a href="<?php echo home_url('/login/?redirect_to=' . urlencode(get_permalink())); ?>" class="rfm-btn rfm-btn-secondary">
                                    <?php _e('Log ind', 'rigtig-for-mig'); ?>
                                </a>
                            </p>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    

    /**
     * Get profile details HTML
     */
    private function get_profile_details($expert_id) {
        $category_profiles = RFM_Category_Profiles::get_instance();
        $expert_categories = wp_get_object_terms($expert_id, 'rfm_category', array('fields' => 'all'));
        
        if (is_wp_error($expert_categories) || empty($expert_categories)) {
            $expert_categories = array();
        }
        
        $has_multiple_categories = count($expert_categories) > 1;
        
        // Determine active category from URL parameter
        $active_category_slug = isset($_GET['kategori']) ? sanitize_text_field($_GET['kategori']) : '';
        $active_category = null;
        
        if (!empty($expert_categories)) {
            if ($active_category_slug) {
                foreach ($expert_categories as $cat) {
                    if ($cat->slug === $active_category_slug) {
                        $active_category = $cat;
                        break;
                    }
                }
            }
            // Default to first category
            if (!$active_category) {
                $active_category = $expert_categories[0];
            }
        }
        
        ob_start();
        ?>
        <div class="rfm-expert-details">
            
            <?php if ($has_multiple_categories): ?>
            <!-- Category Tabs for Profile -->
            <div class="rfm-profile-category-tabs">
                <div class="rfm-profile-tabs-nav">
                    <?php foreach ($expert_categories as $cat): 
                        $color = RFM_Taxonomies::get_category_color($cat->term_id);
                        $is_active = ($active_category && $cat->term_id === $active_category->term_id);
                        $tab_url = add_query_arg('kategori', $cat->slug, get_permalink($expert_id));
                    ?>
                    <a href="<?php echo esc_url($tab_url); ?>" 
                       class="rfm-profile-tab <?php echo $is_active ? 'active' : ''; ?>"
                       style="--tab-color: <?php echo esc_attr($color); ?>;">
                        <?php echo esc_html($cat->name); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <p class="rfm-profile-tab-hint">
                    <?php _e('Klik på en kategori for at se specifik information', 'rigtig-for-mig'); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php
            // Get data for active category (or combined if no category system data)
            if ($active_category) {
                $cat_profile = $category_profiles->get_category_profile($expert_id, $active_category->term_id);
                
                // When we have multiple categories, ONLY show category-specific data
                // Don't fall back to old general data that might belong to another category
                if ($has_multiple_categories) {
                    $about_me = !empty($cat_profile['about_me']) ? $cat_profile['about_me'] : '';
                    $all_educations = !empty($cat_profile['educations']) ? $cat_profile['educations'] : array();
                } else {
                    // Single category: use fallback to old data if needed
                    $about_me = !empty($cat_profile['about_me']) ? $cat_profile['about_me'] : get_post_meta($expert_id, '_rfm_about_me', true);
                    $all_educations = !empty($cat_profile['educations']) ? $cat_profile['educations'] : array();
                }
                
                // Calculate experience
                $max_experience_years = 0;
                if (!empty($cat_profile['experience_start_year'])) {
                    $max_experience_years = RFM_Category_Profiles::calculate_experience_years($cat_profile['experience_start_year']);
                }
                
                // Get specializations for this category
                $display_specializations = array();
                if (!empty($cat_profile['specializations'])) {
                    foreach ($cat_profile['specializations'] as $spec_id) {
                        $spec = get_term($spec_id, 'rfm_specialization');
                        if ($spec && !is_wp_error($spec)) {
                            $display_specializations[] = $spec;
                        }
                    }
                }
            } else {
                $about_me = get_post_meta($expert_id, '_rfm_about_me', true);
                $all_educations = array();
                $max_experience_years = 0;
                $display_specializations = get_the_terms($expert_id, 'rfm_specialization');
                if (is_wp_error($display_specializations)) {
                    $display_specializations = array();
                }
            }
            
            // Fallback: get from old education format if no category educations
            // BUT only if we don't have multiple categories (otherwise data might belong to other category)
            if (empty($all_educations) && !$has_multiple_categories) {
                $old_educations = get_post_meta($expert_id, '_rfm_educations', true);
                if (is_array($old_educations)) {
                    $all_educations = $old_educations;
                    
                    // Calculate experience from old format
                    foreach ($old_educations as $edu) {
                        if (!empty($edu['experience_start_year'])) {
                            $years = RFM_Category_Profiles::calculate_experience_years($edu['experience_start_year']);
                            if ($years > $max_experience_years) {
                                $max_experience_years = $years;
                            }
                        }
                    }
                }
            }
            
            // Fallback for specializations (only for single category)
            if (empty($display_specializations) && !$has_multiple_categories) {
                $display_specializations = get_the_terms($expert_id, 'rfm_specialization');
                if (is_wp_error($display_specializations)) {
                    $display_specializations = array();
                }
            }
            
            // Fallback to old years_experience (only for single category)
            if ($max_experience_years == 0 && !$has_multiple_categories) {
                $old_years = get_post_meta($expert_id, '_rfm_years_experience', true);
                if ($old_years) {
                    $max_experience_years = intval($old_years);
                }
            }
            ?>
            
            <?php if ($active_category && $has_multiple_categories): ?>
            <div class="rfm-category-context" style="border-left: 4px solid <?php echo esc_attr(RFM_Taxonomies::get_category_color($active_category->term_id)); ?>; padding-left: 15px; margin-bottom: 20px;">
                <p style="margin: 0; color: #666; font-size: 14px;">
                    <?php printf(__('Viser information som %s ekspert', 'rigtig-for-mig'), '<strong>' . esc_html($active_category->name) . '</strong>'); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (get_option('rfm_show_about_me', true) && $about_me): ?>
                <div class="rfm-detail-section">
                    <h3><?php _e('Om Mig', 'rigtig-for-mig'); ?></h3>
                    <div class="rfm-about-me-content">
                        <?php echo wpautop(esc_html($about_me)); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (get_option('rfm_show_educations', true) && !empty($all_educations)): ?>
                <div class="rfm-detail-section">
                    <h3><?php _e('Uddannelser', 'rigtig-for-mig'); ?></h3>
                    <div class="rfm-educations-list">
                        <?php foreach ($all_educations as $education):
                            // Build the header line: "Institution | v/Instructor" format (v3.4.1)
                            $header_parts = array();
                            if (!empty($education['institution'])) {
                                $header_parts[] = esc_html($education['institution']);
                            }
                            if (!empty($education['instructor'])) {
                                $header_parts[] = 'v/' . esc_html($education['instructor']);
                            } elseif (!empty($education['title']) && !empty($education['institution'])) {
                                // Use title as instructor name if institution exists
                                $header_parts[] = 'v/' . esc_html($education['title']);
                            }

                            // If no institution but title exists, use title as main header
                            if (empty($education['institution']) && !empty($education['title'])) {
                                $header_text = esc_html($education['title']);
                            } else {
                                $header_text = implode(' | ', $header_parts);
                            }

                            $has_certificate = !empty($education['image_id']);
                        ?>
                            <div class="rfm-education-item <?php echo $has_certificate ? 'has-certificate' : ''; ?>">

                                <div class="rfm-education-content">
                                    <?php
                                    // Show certificate image to the right if it exists
                                    if ($has_certificate):
                                        $image_id = $education['image_id'];
                                        $image_url = wp_get_attachment_image_url($image_id, 'medium');
                                        $image_full = wp_get_attachment_image_url($image_id, 'full');
                                        if ($image_url):
                                    ?>
                                        <div class="rfm-education-certificate rfm-float-right">
                                            <a href="<?php echo esc_url($image_full); ?>" class="rfm-certificate-link" target="_blank" title="<?php esc_attr_e('Se certifikat i fuld størrelse', 'rigtig-for-mig'); ?>">
                                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php esc_attr_e('Diplom/Certifikat', 'rigtig-for-mig'); ?>" class="rfm-certificate-img" />
                                            </a>
                                        </div>
                                    <?php
                                        endif;
                                    endif;
                                    ?>

                                    <?php if (!empty($header_text)): ?>
                                        <p class="rfm-education-header"><strong><?php echo $header_text; ?></strong></p>
                                    <?php endif; ?>

                                    <?php if (!empty($education['description'])): ?>
                                        <div class="rfm-education-description"><?php echo nl2br(esc_html($education['description'])); ?></div>
                                    <?php endif; ?>

                                    <div class="rfm-clear"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (get_option('rfm_show_experience', true) && $max_experience_years > 0): ?>
                <div class="rfm-detail-section">
                    <h3><?php _e('Erfaring', 'rigtig-for-mig'); ?></h3>
                    <p class="rfm-experience-years">
                        <?php printf(_n('%d års erfaring', '%d års erfaring', $max_experience_years, 'rigtig-for-mig'), $max_experience_years); ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if (get_option('rfm_show_specializations', true) && !empty($display_specializations)): ?>
                <div class="rfm-detail-section">
                    <h3><?php _e('Specialiseringer', 'rigtig-for-mig'); ?></h3>
                    <div class="rfm-specializations-list">
                        <?php foreach ($display_specializations as $specialization): ?>
                            <span class="rfm-specialization-tag"><?php echo esc_html($specialization->name); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php
            // Get languages
            $languages = get_post_meta($expert_id, '_rfm_languages', true);
            if (!empty($languages) && is_array($languages) && count($languages) > 0):
                // Get language field labels from flexible fields system
                $flexible_fields = RFM_Flexible_Fields_System::get_instance();
                $all_fields = $flexible_fields->get_fields();
                $language_fields = array();
                if (isset($all_fields['sprog']) && isset($all_fields['sprog']['fields'])) {
                    $language_fields = $all_fields['sprog']['fields'];
                }

                $language_names = array();
                foreach ($languages as $lang) {
                    $lang_key = strtolower($lang);
                    // Use label from flexible fields system if available, otherwise use capitalized key
                    if (isset($language_fields[$lang_key]['label'])) {
                        $language_names[] = $language_fields[$lang_key]['label'];
                    } else {
                        $language_names[] = ucfirst($lang);
                    }
                }
            ?>
                <div class="rfm-detail-section">
                    <h3><?php _e('Sprog', 'rigtig-for-mig'); ?></h3>
                    <div class="rfm-languages-list">
                        <?php foreach ($language_names as $language_name): ?>
                            <span class="rfm-language-tag"><?php echo esc_html($language_name); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get ratings section HTML
     */
    private function get_ratings_section($expert_id) {
        $ratings = RFM_Ratings::get_instance()->get_ratings($expert_id, 1, 5);
        $user_rating = RFM_Ratings::get_instance()->get_user_rating($expert_id);
        
        ob_start();
        ?>
        <div class="rfm-ratings-section">
            <h3><?php _e('Bedømmelser', 'rigtig-for-mig'); ?></h3>
            
            <?php if (is_user_logged_in()): ?>
                <div class="rfm-rating-form">
                    <h4><?php _e('Bedøm denne ekspert', 'rigtig-for-mig'); ?></h4>
                    <form id="rfm-submit-rating-form">
                        <div class="rfm-star-rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" <?php checked($user_rating && $user_rating->rating == $i); ?> />
                                <label for="star<?php echo $i; ?>">★</label>
                            <?php endfor; ?>
                        </div>
                        <textarea name="review" placeholder="<?php esc_attr_e('Skriv din anmeldelse...', 'rigtig-for-mig'); ?>"><?php echo $user_rating ? esc_textarea($user_rating->review) : ''; ?></textarea>
                        <input type="hidden" name="expert_id" value="<?php echo esc_attr($expert_id); ?>" />
                        <button type="submit" class="rfm-btn rfm-btn-primary"><?php _e('Send bedømmelse', 'rigtig-for-mig'); ?></button>
                    </form>
                </div>
            <?php else: ?>
                <p><?php _e('Du skal være logget ind for at bedømme denne ekspert.', 'rigtig-for-mig'); ?></p>
            <?php endif; ?>
            
            <div class="rfm-ratings-list">
                <?php if ($ratings): ?>
                    <?php foreach ($ratings as $rating): ?>
                        <div class="rfm-rating-item">
                            <div class="rfm-rating-header">
                                <strong><?php echo esc_html($rating->user_name); ?></strong>
                                <?php echo RFM_Ratings::display_stars($rating->rating, false); ?>
                                <span class="rfm-rating-date"><?php echo human_time_diff(strtotime($rating->created_at), current_time('timestamp')); ?> <?php _e('siden', 'rigtig-for-mig'); ?></span>
                            </div>
                            <?php if ($rating->review): ?>
                                <p class="rfm-rating-review"><?php echo esc_html($rating->review); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><?php _e('Ingen bedømmelser endnu.', 'rigtig-for-mig'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get message modal HTML
     */
    private function get_message_modal($expert_id) {
        // Only show modal if user is logged in and not viewing own profile
        if (!is_user_logged_in()) {
            return '';
        }

        $current_user = wp_get_current_user();
        $post_author = get_post_field('post_author', $expert_id);

        if ($current_user->ID == $post_author) {
            return '';
        }

        $expert_name = get_the_title($expert_id);

        ob_start();
        ?>
        <!-- Message Modal -->
        <div id="rfm-message-modal" class="rfm-modal" style="display: none;">
            <div class="rfm-modal-content">
                <span class="rfm-modal-close">&times;</span>
                <h3><?php printf(__('Send besked til %s', 'rigtig-for-mig'), esc_html($expert_name)); ?></h3>

                <form id="rfm-message-form" data-expert-id="<?php echo esc_attr($expert_id); ?>">
                    <div class="rfm-form-group">
                        <label for="rfm-message-subject"><?php _e('Emne', 'rigtig-for-mig'); ?></label>
                        <input type="text"
                               id="rfm-message-subject"
                               name="subject"
                               class="rfm-form-control"
                               placeholder="<?php esc_attr_e('Hvad drejer beskeden sig om?', 'rigtig-for-mig'); ?>"
                               required>
                    </div>

                    <div class="rfm-form-group">
                        <label for="rfm-message-text"><?php _e('Besked', 'rigtig-for-mig'); ?> *</label>
                        <textarea id="rfm-message-text"
                                  name="message"
                                  class="rfm-form-control"
                                  rows="6"
                                  placeholder="<?php esc_attr_e('Skriv din besked her...', 'rigtig-for-mig'); ?>"
                                  required></textarea>
                    </div>

                    <div id="rfm-message-form-message"></div>

                    <div class="rfm-form-actions">
                        <button type="button" class="rfm-btn rfm-btn-secondary rfm-modal-close">
                            <?php _e('Annuller', 'rigtig-for-mig'); ?>
                        </button>
                        <button type="submit" class="rfm-btn rfm-btn-primary">
                            <i class="dashicons dashicons-email-alt"></i>
                            <?php _e('Send besked', 'rigtig-for-mig'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
