<?php
/**
 * Expert Dashboard Management
 *
 * Handles the expert dashboard interface with tabbed navigation,
 * profile management, and category-specific content.
 *
 * Part of Phase 2 Refactoring - extracted from RFM_Frontend_Registration
 *
 * @package Rigtig_For_Mig
 * @since 3.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Expert_Dashboard {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register shortcodes
        add_shortcode('rfm_expert_dashboard', array($this, 'dashboard_shortcode'));
        add_shortcode('rfm_expert_dashboard_tabbed', array($this, 'tabbed_dashboard_shortcode'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handlers
        add_action('wp_ajax_rfm_update_dashboard_profile', array($this, 'handle_dashboard_profile_update'));
        add_action('wp_ajax_rfm_upload_education_image', array($this, 'handle_education_image_upload'));
    }

    /**
     * Enqueue dashboard scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on frontend (not admin)
        if (is_admin()) {
            return;
        }

        // Enqueue dashboard script globally on all frontend pages
        // This matches the approach used by other dashboard systems
        wp_enqueue_script(
            'rfm-expert-dashboard',
            RFM_PLUGIN_URL . 'assets/js/expert-dashboard.js',
            array('jquery'),
            RFM_VERSION,
            true
        );

        // Localize script with translations and data
        wp_localize_script('rfm-expert-dashboard', 'rfmDashboard', array(
            'ajaxurl' => RFM_PLUGIN_URL . 'ajax-handler.php',  // Direct AJAX handler
            'nonce' => wp_create_nonce('rfm_expert_dashboard'),
            'strings' => array(
                'savingText' => __('Gemmer...', 'rigtig-for-mig'),
                'submitGeneralText' => __('Gem generelle oplysninger', 'rigtig-for-mig'),
                'errorText' => __('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig')
            ),
            'logoutNonce' => wp_create_nonce('rfm_logout')
        ));
    }

    /**
     * Dashboard Shortcode
     *
     * Alias for the tabbed dashboard shortcode. This provides backward compatibility
     * while using the new tabbed dashboard system.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output of the dashboard
     */
    public function dashboard_shortcode($atts) {
        // Use the new tabbed dashboard system
        return $this->tabbed_dashboard_shortcode($atts);
    }

    /**
     * Handle Dashboard Profile Update (Legacy AJAX Handler)
     *
     * Processes AJAX requests to update general expert profile information
     * including name, contact details, educations, and languages.
     *
     * Security: Verifies nonce and user ownership of the expert profile.
     * Plan restrictions: Standard/Premium users can update website and company name.
     *
     * @since 3.4.0
     */
    public function handle_dashboard_profile_update() {
        check_ajax_referer('rfm_dashboard_update', 'rfm_dashboard_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind.', 'rigtig-for-mig')));
        }

        $user_id = get_current_user_id();
        $expert_id = intval($_POST['expert_id']);

        // Verify ownership
        $post = get_post($expert_id);
        if (!$post || $post->post_author != $user_id) {
            wp_send_json_error(array('message' => __('Du har ikke tilladelse til at redigere denne profil.', 'rigtig-for-mig')));
        }

        // Get plan to check what fields are accessible
        $plan = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);
        $is_standard_or_higher = ($plan === 'standard' || $plan === 'premium');

        // Update post title (name)
        wp_update_post(array(
            'ID' => $expert_id,
            'post_title' => sanitize_text_field($_POST['name'])
        ));

        // Update basic meta fields (available to all)
        update_post_meta($expert_id, '_rfm_email', sanitize_email($_POST['email']));
        update_post_meta($expert_id, '_rfm_phone', sanitize_text_field($_POST['phone']));
        update_post_meta($expert_id, '_rfm_about_me', sanitize_textarea_field($_POST['about']));

        // Update fields only for Standard/Premium
        if ($is_standard_or_higher) {
            if (isset($_POST['website'])) {
                update_post_meta($expert_id, '_rfm_website', esc_url_raw($_POST['website']));
            }
            if (isset($_POST['company_name'])) {
                update_post_meta($expert_id, '_rfm_company_name', sanitize_text_field($_POST['company_name']));
            }
        }

        // Update educations
        if (isset($_POST['educations']) && is_array($_POST['educations'])) {
            $educations = array();

            foreach ($_POST['educations'] as $index => $education) {
                // Skip if name is empty
                if (empty($education['name'])) {
                    continue;
                }

                $edu_item = array(
                    'name' => sanitize_text_field($education['name']),
                    'institution' => sanitize_text_field($education['institution'] ?? ''),
                    'year_start' => sanitize_text_field($education['year_start'] ?? ''),
                    'year_end' => sanitize_text_field($education['year_end'] ?? ''),
                    'experience_start_year' => absint($education['experience_start_year'] ?? 0),
                    'description' => sanitize_textarea_field($education['description'] ?? ''),
                );

                // Only save image_id for Standard/Premium members
                if ($is_standard_or_higher && !empty($education['image_id'])) {
                    $edu_item['image_id'] = absint($education['image_id']);
                }

                $educations[] = $edu_item;
            }

            update_post_meta($expert_id, '_rfm_educations', $educations);
        } else {
            // Keep existing if not submitted
            // delete_post_meta($expert_id, '_rfm_educations');
        }

        // Update languages
        if (isset($_POST['languages']) && is_array($_POST['languages'])) {
            $languages = array_map('sanitize_text_field', $_POST['languages']);
            update_post_meta($expert_id, '_rfm_languages', $languages);
        } else {
            delete_post_meta($expert_id, '_rfm_languages');
        }

        wp_send_json_success(array(
            'message' => __('✅ Din profil er opdateret!', 'rigtig-for-mig')
        ));
    }

    /**
     * Handle Education Image Upload via AJAX
     *
     * Processes image uploads for education certificates/diplomas.
     * Only available to Standard and Premium members.
     *
     * Validates:
     * - User authentication
     * - Subscription level (Standard/Premium required)
     * - File type (JPG, PNG, GIF, WebP only)
     * - File size (max 5MB)
     *
     * @since 3.4.0
     */
    public function handle_education_image_upload() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rfm_nonce')) {
            wp_send_json_error(array('message' => __('Sikkerhedstjek fejlede. Prøv igen.', 'rigtig-for-mig')));
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind.', 'rigtig-for-mig')));
        }

        $user_id = get_current_user_id();

        // Get user's expert profile
        $expert_posts = get_posts(array(
            'post_type' => 'rfm_expert',
            'author' => $user_id,
            'posts_per_page' => 1
        ));

        if (empty($expert_posts)) {
            wp_send_json_error(array('message' => __('Ekspert profil ikke fundet.', 'rigtig-for-mig')));
        }

        $expert_id = $expert_posts[0]->ID;

        // Check subscription - only Standard/Premium can upload images
        $plan = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);

        if ($plan === 'free') {
            wp_send_json_error(array('message' => __('Opgrader til Standard eller Premium for at uploade billeder.', 'rigtig-for-mig')));
        }

        // Check if file was uploaded
        if (empty($_FILES['education_image'])) {
            wp_send_json_error(array('message' => __('Ingen fil blev uploadet.', 'rigtig-for-mig')));
        }

        $file = $_FILES['education_image'];

        // Validate file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(array('message' => __('Ugyldig filtype. Kun JPG, PNG, GIF og WebP er tilladt.', 'rigtig-for-mig')));
        }

        // Validate file size (max 5MB)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($file['size'] > $max_size) {
            wp_send_json_error(array('message' => __('Filen er for stor. Maksimum 5MB.', 'rigtig-for-mig')));
        }

        // Include WordPress media functions
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Upload the file
        $attachment_id = media_handle_upload('education_image', $expert_id);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        // Get the image URL
        $image_url = wp_get_attachment_image_url($attachment_id, 'medium');
        $image_html = wp_get_attachment_image($attachment_id, 'medium');

        wp_send_json_success(array(
            'message' => __('Billede uploadet!', 'rigtig-for-mig'),
            'attachment_id' => $attachment_id,
            'image_url' => $image_url,
            'image_html' => $image_html
        ));
    }

    /**
     * Tabbed Dashboard Shortcode
     *
     * Displays a tabbed interface where:
     * - General tab: Basic profile info, languages, category selection
     * - Category tabs: Category-specific profiles with educations and specializations
     *
     * Features:
     * - Plan-based limits (Free: 1 category, Standard: 2, Premium: unlimited)
     * - Category-specific educations and specializations
     * - Real-time form validation and AJAX submission
     * - Dynamic tab creation based on selected categories
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output of the tabbed dashboard
     * @since 3.5.0
     */
    public function tabbed_dashboard_shortcode($atts) {
        // Prevent caching of dashboard page to ensure fresh category data
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('X-LiteSpeed-Cache-Control: no-cache');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        if (!is_user_logged_in()) {
            return '<p>' . __('Du skal være logget ind for at se dit dashboard.', 'rigtig-for-mig') . ' <a href="' . wp_login_url(get_permalink()) . '">' . __('Log ind', 'rigtig-for-mig') . '</a></p>';
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        // Get user's expert profile
        $expert_posts = get_posts(array(
            'post_type' => 'rfm_expert',
            'author' => $user_id,
            'posts_per_page' => 1
        ));

        if (empty($expert_posts)) {
            return '<p>' . __('Du har ikke en ekspert profil endnu.', 'rigtig-for-mig') . '</p>';
        }

        $expert_id = $expert_posts[0]->ID;
        $plan = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);
        $average_rating = RFM_Ratings::get_instance()->get_average_rating($expert_id);
        $rating_count = RFM_Ratings::get_instance()->get_rating_count($expert_id);

        // Get current general data
        $name = get_the_title($expert_id);
        $email = get_post_meta($expert_id, '_rfm_email', true);
        $phone = get_post_meta($expert_id, '_rfm_phone', true);
        $website = get_post_meta($expert_id, '_rfm_website', true);
        $company_name = get_post_meta($expert_id, '_rfm_company_name', true);

        // Get languages
        $languages = get_post_meta($expert_id, '_rfm_languages', true);
        if (!is_array($languages)) {
            $languages = array();
        }

        // Get expert's current categories (force fresh data, bypass cache)
        clean_object_term_cache($expert_id, 'rfm_expert');
        $expert_categories = wp_get_object_terms($expert_id, 'rfm_category', array('fields' => 'all'));
        if (is_wp_error($expert_categories)) {
            $expert_categories = array();
        }

        // DEBUG: Log what categories we retrieved for this expert
        $expert_cat_ids_debug = array_map(function($cat) { return $cat->term_id; }, $expert_categories);
        error_log("RFM RENDER DEBUG: Expert ID $expert_id - Retrieved categories from DB: " . implode(', ', $expert_cat_ids_debug));
        error_log("RFM RENDER DEBUG: Total categories found: " . count($expert_categories));

        // Get all available categories
        $all_categories = get_terms(array(
            'taxonomy' => 'rfm_category',
            'hide_empty' => false
        ));

        // Get all specializations
        $all_specializations = get_terms(array(
            'taxonomy' => 'rfm_specialization',
            'hide_empty' => false
        ));

        // Define limits
        $max_categories = array('free' => 1, 'standard' => 2, 'premium' => 99);
        $max_educations = array('free' => 1, 'standard' => 3, 'premium' => 7);
        $max_specs = array('free' => 1, 'standard' => 3, 'premium' => 7);

        $allowed_categories = $max_categories[$plan] ?? 1;
        $allowed_educations = $max_educations[$plan] ?? 1;
        $allowed_specs = $max_specs[$plan] ?? 1;

        $is_standard_or_higher = ($plan === 'standard' || $plan === 'premium');

        // Get language fields from flexible fields system
        $flexible_fields = RFM_Flexible_Fields_System::get_instance();
        $all_fields = $flexible_fields->get_fields();

        $language_fields = array();
        if (isset($all_fields['sprog']) && isset($all_fields['sprog']['fields'])) {
            $language_fields = $all_fields['sprog']['fields'];
        } else {
            $language_fields = array(
                'dansk' => ['label' => 'Dansk', 'required' => false],
                'engelsk' => ['label' => 'English', 'required' => false],
                'svensk' => ['label' => 'Svenska', 'required' => false],
                'norsk' => ['label' => 'Norsk / Bokmål', 'required' => false],
                'suomi' => ['label' => 'Suomi', 'required' => false],
                'faeroyskt' => ['label' => 'Føroyskt', 'required' => false],
                'kalaallisut' => ['label' => 'Kalaallisut', 'required' => false],
                'espanol' => ['label' => 'Español', 'required' => false],
                'italiano' => ['label' => 'Italiano', 'required' => false],
                'deutsch' => ['label' => 'Deutsch', 'required' => false],
                'arabic' => ['label' => 'العربية (al-arabiya)', 'required' => false]
            );
        }

        // Category profiles instance
        $category_profiles = RFM_Category_Profiles::get_instance();

        ob_start();
        ?>
        <div class="rfm-expert-dashboard rfm-tabbed-dashboard">
            <h2><?php _e('Ekspert Dashboard', 'rigtig-for-mig'); ?></h2>

            <!-- Stats Section -->
            <div class="rfm-dashboard-stats">
                <div class="rfm-stat-box">
                    <h3><?php echo number_format($average_rating, 1); ?> ★</h3>
                    <p><?php _e('Gennemsnitlig rating', 'rigtig-for-mig'); ?></p>
                </div>

                <div class="rfm-stat-box">
                    <h3><?php echo $rating_count; ?></h3>
                    <p><?php _e('Bedømmelser', 'rigtig-for-mig'); ?></p>
                </div>

                <div class="rfm-stat-box">
                    <h3><?php echo ucfirst($plan); ?></h3>
                    <p><?php _e('Din plan', 'rigtig-for-mig'); ?></p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="rfm-dashboard-actions">
                <a href="<?php echo get_permalink($expert_id); ?>" class="rfm-btn rfm-btn-primary" target="_blank">
                    <?php _e('Se min profil', 'rigtig-for-mig'); ?>
                </a>
                <a href="#" id="rfm-logout-btn" class="rfm-btn">
                    <?php _e('Log ud', 'rigtig-for-mig'); ?>
                </a>
            </div>

            <!-- Tab Navigation -->
            <div class="rfm-dashboard-tabs">
                <div class="rfm-tabs-navigation">
                    <button type="button" class="rfm-tab-btn active" data-tab="general">
                        ★ <?php _e('Generelt', 'rigtig-for-mig'); ?>
                    </button>
                    <?php foreach ($expert_categories as $category): ?>
                        <?php $color = RFM_Taxonomies::get_category_color($category->term_id); ?>
                        <button type="button"
                                class="rfm-tab-btn"
                                data-tab="category-<?php echo esc_attr($category->term_id); ?>"
                                style="--tab-color: <?php echo esc_attr($color); ?>;">
                            <?php echo esc_html($category->name); ?>
                        </button>
                    <?php endforeach; ?>
                    <button type="button" class="rfm-tab-btn" data-tab="messages">
                        <i class="dashicons dashicons-email-alt"></i> <?php _e('Beskeder', 'rigtig-for-mig'); ?>
                        <span class="rfm-unread-count" id="rfm-expert-unread-count" style="display: none;"></span>
                    </button>
                </div>

                <!-- Global Message Area -->
                <div id="rfm-tabbed-dashboard-message"></div>

                <!-- Tab Content: General -->
                <div class="rfm-tab-content active" data-tab-content="general">
                    <form id="rfm-general-profile-form" method="post">
                        <?php wp_nonce_field('rfm_dashboard_tabbed', 'rfm_tabbed_nonce'); ?>
                        <input type="hidden" name="expert_id" value="<?php echo esc_attr($expert_id); ?>" />

                        <!-- Basis Information -->
                        <div class="rfm-form-section">
                            <h3><?php _e('Basis Information', 'rigtig-for-mig'); ?></h3>

                            <div class="rfm-form-field">
                                <label for="general_name"><?php _e('Dit fulde navn', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                                <input type="text" name="name" id="general_name" value="<?php echo esc_attr($name); ?>" required />
                            </div>

                            <div class="rfm-form-field">
                                <label for="general_email"><?php _e('Email', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                                <input type="email" name="email" id="general_email" value="<?php echo esc_attr($email); ?>" required />
                            </div>

                            <div class="rfm-form-field">
                                <label for="general_phone"><?php _e('Telefon', 'rigtig-for-mig'); ?></label>
                                <input type="tel" name="phone" id="general_phone" value="<?php echo esc_attr($phone); ?>" />
                            </div>

                            <?php if ($is_standard_or_higher): ?>
                            <div class="rfm-form-field">
                                <label for="general_website"><?php _e('Hjemmeside', 'rigtig-for-mig'); ?></label>
                                <input type="url" name="website" id="general_website" value="<?php echo esc_attr($website); ?>" placeholder="https://www.example.com" />
                            </div>

                            <div class="rfm-form-field">
                                <label for="general_company"><?php _e('Firma navn', 'rigtig-for-mig'); ?></label>
                                <input type="text" name="company_name" id="general_company" value="<?php echo esc_attr($company_name); ?>" />
                            </div>
                            <?php else: ?>
                            <div class="rfm-upgrade-notice">
                                🔒 <?php _e('Hjemmeside og firma navn kræver Standard eller Premium medlemskab.', 'rigtig-for-mig'); ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Sprog -->
                        <div class="rfm-form-section">
                            <h3><?php _e('Sprog', 'rigtig-for-mig'); ?></h3>
                            <div class="rfm-language-grid">
                                <?php foreach ($language_fields as $lang_code => $lang_data):
                                    $lang_label = is_array($lang_data) ? ($lang_data['label'] ?? ucfirst($lang_code)) : $lang_data;
                                ?>
                                <label class="rfm-language-choice">
                                    <input type="checkbox"
                                           name="languages[]"
                                           value="<?php echo esc_attr($lang_code); ?>"
                                           <?php checked(in_array($lang_code, $languages)); ?> />
                                    <span><?php echo esc_html($lang_label); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Kategorier -->
                        <div class="rfm-form-section">
                            <h3><?php _e('Kategorier', 'rigtig-for-mig'); ?></h3>
                            <p class="rfm-section-description">
                                <?php
                                if ($plan === 'free') {
                                    _e('Du kan vælge 1 kategori. Opgrader for at vælge flere.', 'rigtig-for-mig');
                                } elseif ($plan === 'standard') {
                                    _e('Du kan vælge op til 2 kategorier. Opgrader til Premium for flere.', 'rigtig-for-mig');
                                } else {
                                    _e('Du kan vælge alle kategorier.', 'rigtig-for-mig');
                                }
                                ?>
                            </p>

                            <div class="rfm-category-checkboxes"
                                 id="rfm-tabbed-categories"
                                 data-max="<?php echo esc_attr($allowed_categories); ?>">
                                <?php
                                $expert_cat_ids = array_map(function($cat) { return $cat->term_id; }, $expert_categories);
                                error_log("RFM RENDER DEBUG: About to render checkboxes. Expert category IDs: " . implode(', ', $expert_cat_ids));

                                foreach ($all_categories as $category):
                                    $color = RFM_Taxonomies::get_category_color($category->term_id);
                                    $is_checked = in_array($category->term_id, $expert_cat_ids);
                                    error_log("RFM RENDER DEBUG: Category {$category->term_id} ({$category->name}) - Should be checked: " . ($is_checked ? 'YES' : 'NO'));
                                ?>
                                <label class="rfm-category-choice" style="--cat-color: <?php echo esc_attr($color); ?>;">
                                    <input type="checkbox"
                                           name="categories[]"
                                           value="<?php echo esc_attr($category->term_id); ?>"
                                           class="rfm-category-checkbox"
                                           <?php checked($is_checked); ?> />
                                    <span><?php echo esc_html($category->name); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>

                            <p class="rfm-category-limit-notice" id="rfm-category-limit-notice" style="display: none; color: #e74c3c; margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                                ⚠️ <?php printf(__('Du har valgt mere end %d kategorier. Kun de første %d vil blive gemt. Fjern ét valg først for at vælge en anden kategori.', 'rigtig-for-mig'), $allowed_categories, $allowed_categories); ?>
                            </p>

                            <p class="rfm-category-info" style="margin-top: 15px; padding: 10px; background: #f0f8ff; border-radius: 5px;">
                                💡 <?php _e('Når du gemmer kategorier, oprettes der automatisk en ny fane for hver kategori, hvor du kan tilføje kategori-specifik information.', 'rigtig-for-mig'); ?>
                            </p>
                        </div>

                        <!-- Submit General -->
                        <div class="rfm-form-submit">
                            <button type="submit" class="rfm-btn rfm-btn-primary rfm-btn-large">
                                <?php _e('Gem generelle oplysninger', 'rigtig-for-mig'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tab Content: Category-Specific Profiles -->
                <?php foreach ($expert_categories as $category):
                    $color = RFM_Taxonomies::get_category_color($category->term_id);
                    $cat_profile = $category_profiles->get_category_profile($expert_id, $category->term_id);
                    $cat_about = $cat_profile['about_me'] ?? '';
                    $cat_experience_year = $cat_profile['experience_start_year'] ?? '';
                    $cat_educations = $cat_profile['educations'] ?? array();
                    $cat_specs = $cat_profile['specializations'] ?? array();
                ?>
                <div class="rfm-tab-content" data-tab-content="category-<?php echo esc_attr($category->term_id); ?>">
                    <form class="rfm-category-profile-form" method="post" data-category-id="<?php echo esc_attr($category->term_id); ?>">
                        <?php wp_nonce_field('rfm_dashboard_tabbed', 'rfm_tabbed_nonce'); ?>
                        <input type="hidden" name="expert_id" value="<?php echo esc_attr($expert_id); ?>" />
                        <input type="hidden" name="category_id" value="<?php echo esc_attr($category->term_id); ?>" />

                        <div class="rfm-category-header" style="border-left: 4px solid <?php echo esc_attr($color); ?>; padding-left: 15px; margin-bottom: 30px;">
                            <h3 style="margin: 0; color: <?php echo esc_attr($color); ?>;">
                                <?php echo esc_html($category->name); ?>
                            </h3>
                            <p style="margin: 5px 0 0 0; color: #666;">
                                <?php _e('Her kan du tilføje information specifikt for denne kategori.', 'rigtig-for-mig'); ?>
                            </p>
                        </div>

                        <!-- Om Mig for this category -->
                        <div class="rfm-form-section">
                            <h4><?php printf(__('Om mig som %s', 'rigtig-for-mig'), esc_html($category->name)); ?></h4>

                            <div class="rfm-form-field">
                                <label><?php _e('Fortæl om din ekspertise inden for denne kategori', 'rigtig-for-mig'); ?></label>
                                <textarea name="about_me" rows="5" placeholder="<?php esc_attr_e('Beskriv din baggrund og erfaring inden for dette område...', 'rigtig-for-mig'); ?>"><?php echo esc_textarea($cat_about); ?></textarea>
                            </div>

                            <div class="rfm-form-field">
                                <label><?php _e('År startet i praksis (for denne kategori)', 'rigtig-for-mig'); ?></label>
                                <input type="number"
                                       name="experience_start_year"
                                       value="<?php echo esc_attr($cat_experience_year); ?>"
                                       min="1950"
                                       max="<?php echo date('Y'); ?>"
                                       placeholder="<?php echo date('Y'); ?>" />
                                <p class="rfm-field-hint"><?php _e('Hvornår begyndte du at arbejde inden for dette felt? Bruges til at beregne års erfaring.', 'rigtig-for-mig'); ?></p>
                            </div>
                        </div>

                        <!-- Uddannelser for this category -->
                        <div class="rfm-form-section rfm-category-education-section">
                            <h4><?php printf(__('Uddannelser relateret til %s', 'rigtig-for-mig'), esc_html($category->name)); ?></h4>
                            <p class="rfm-section-description">
                                <?php
                                if ($plan === 'free') {
                                    printf(__('Du kan tilføje op til %d uddannelse. Opgrader for at tilføje flere.', 'rigtig-for-mig'), $allowed_educations);
                                } elseif ($plan === 'standard') {
                                    printf(__('Du kan tilføje op til %d uddannelser. Opgrader til Premium for flere.', 'rigtig-for-mig'), $allowed_educations);
                                } else {
                                    printf(__('Du kan tilføje op til %d uddannelser.', 'rigtig-for-mig'), $allowed_educations);
                                }
                                ?>
                            </p>

                            <div class="rfm-category-educations-container"
                                 data-max="<?php echo esc_attr($allowed_educations); ?>"
                                 data-category-id="<?php echo esc_attr($category->term_id); ?>">

                                <?php if (!empty($cat_educations)): ?>
                                    <?php foreach ($cat_educations as $index => $edu): ?>
                                        <?php $this->render_category_education_item($edu, $index, $is_standard_or_higher, $category->term_id); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="rfm-add-education-wrapper">
                                <button type="button" class="rfm-btn rfm-btn-secondary rfm-add-category-education" data-category-id="<?php echo esc_attr($category->term_id); ?>">
                                    <?php _e('+ Tilføj uddannelse', 'rigtig-for-mig'); ?>
                                </button>
                                <span class="rfm-cat-education-limit-notice" style="display: none; color: #e74c3c; margin-left: 10px;">
                                    <?php printf(__('Maksimum %d uddannelser.', 'rigtig-for-mig'), $allowed_educations); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Specialiseringer for this category -->
                        <div class="rfm-form-section">
                            <h4><?php printf(__('Specialiseringer inden for %s', 'rigtig-for-mig'), esc_html($category->name)); ?></h4>
                            <p class="rfm-section-description">
                                <?php
                                if ($plan === 'free') {
                                    printf(__('Du kan vælge %d specialisering. Opgrader for at vælge flere.', 'rigtig-for-mig'), $allowed_specs);
                                } elseif ($plan === 'standard') {
                                    printf(__('Du kan vælge op til %d specialiseringer. Opgrader til Premium for flere.', 'rigtig-for-mig'), $allowed_specs);
                                } else {
                                    printf(__('Du kan vælge op til %d specialiseringer.', 'rigtig-for-mig'), $allowed_specs);
                                }
                                ?>
                            </p>

                            <div class="rfm-specialization-checkboxes"
                                 data-max="<?php echo esc_attr($allowed_specs); ?>"
                                 data-category-id="<?php echo esc_attr($category->term_id); ?>">
                                <?php foreach ($all_specializations as $spec): ?>
                                <label class="rfm-specialization-choice">
                                    <input type="checkbox"
                                           name="specializations[]"
                                           value="<?php echo esc_attr($spec->term_id); ?>"
                                           class="rfm-spec-checkbox"
                                           <?php checked(in_array($spec->term_id, $cat_specs)); ?> />
                                    <span><?php echo esc_html($spec->name); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>

                            <p class="rfm-spec-limit-notice" style="display: none; color: #e74c3c; margin-top: 10px;">
                                <?php printf(__('Du har valgt det maksimale antal specialiseringer (%d).', 'rigtig-for-mig'), $allowed_specs); ?>
                            </p>
                        </div>

                        <!-- Submit Category Profile -->
                        <div class="rfm-form-submit">
                            <button type="submit" class="rfm-btn rfm-btn-primary rfm-btn-large" style="background-color: <?php echo esc_attr($color); ?>;">
                                <?php printf(__('Gem %s profil', 'rigtig-for-mig'), esc_html($category->name)); ?>
                            </button>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>

                <!-- Tab Content: Messages -->
                <div class="rfm-tab-content" data-tab-content="messages">
                    <div class="rfm-expert-messages-container">
                        <h3><?php _e('Mine Beskeder', 'rigtig-for-mig'); ?></h3>
                        <div class="rfm-messages-actions" style="margin-bottom: 15px;">
                            <button id="rfm-expert-mark-all-read-btn" class="rfm-btn rfm-btn-secondary" style="display: none;">
                                <?php _e('Marker alle som læst', 'rigtig-for-mig'); ?>
                            </button>
                        </div>
                        <div class="rfm-messages-loading" style="text-align: center; padding: 40px 20px; color: #666;">
                            <i class="dashicons dashicons-update" style="font-size: 24px; animation: spin 1s linear infinite;"></i>
                            <p><?php _e('Indlæser beskeder...', 'rigtig-for-mig'); ?></p>
                        </div>
                        <div id="rfm-expert-conversations-list" class="rfm-conversations-list" style="display: none;"></div>
                        <div id="rfm-expert-no-messages" class="rfm-no-messages" style="display: none; text-align: center; padding: 40px 20px; color: #666;">
                            <i class="dashicons dashicons-email-alt" style="font-size: 48px; opacity: 0.3;"></i>
                            <p><?php _e('Du har ingen beskeder endnu.', 'rigtig-for-mig'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Education Template for Category Profiles -->
                <template id="rfm-category-education-template">
                    <?php $this->render_category_education_item(array(), '__INDEX__', $is_standard_or_higher, '__CATEGORY_ID__'); ?>
                </template>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a Single Education Item for Category Profiles
     *
     * Renders the HTML for a single education entry within a category profile.
     * Includes fields for: name, institution, years, description, and optional image upload.
     *
     * Used by:
     * - tabbed_dashboard_shortcode() to render existing educations
     * - JavaScript template for dynamically adding new educations
     *
     * @param array $education The education data array
     * @param string|int $index The index for form field naming
     * @param bool $is_standard_or_higher Whether user can upload images
     * @param int $category_id The category ID this education belongs to
     * @since 3.5.0
     */
    private function render_category_education_item($education, $index, $is_standard_or_higher, $category_id) {
        ?>
        <div class="rfm-category-education-item" data-index="<?php echo esc_attr($index); ?>">
            <button type="button" class="rfm-category-education-remove" title="<?php esc_attr_e('Fjern uddannelse', 'rigtig-for-mig'); ?>">✕</button>

            <div class="rfm-form-field">
                <label><?php _e('Uddannelsesnavn', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                <input type="text"
                       name="educations[<?php echo esc_attr($index); ?>][name]"
                       value="<?php echo esc_attr($education['name'] ?? ''); ?>"
                       placeholder="<?php esc_attr_e('F.eks. Psykologuddannelse', 'rigtig-for-mig'); ?>" />
            </div>

            <div class="rfm-form-field">
                <label><?php _e('Institution', 'rigtig-for-mig'); ?></label>
                <input type="text"
                       name="educations[<?php echo esc_attr($index); ?>][institution]"
                       value="<?php echo esc_attr($education['institution'] ?? ''); ?>"
                       placeholder="<?php esc_attr_e('F.eks. Københavns Universitet', 'rigtig-for-mig'); ?>" />
            </div>

            <div class="rfm-form-row">
                <div class="rfm-form-field rfm-form-field-half">
                    <label><?php _e('År (start)', 'rigtig-for-mig'); ?></label>
                    <input type="text"
                           name="educations[<?php echo esc_attr($index); ?>][year_start]"
                           value="<?php echo esc_attr($education['year_start'] ?? ''); ?>"
                           placeholder="2018" />
                </div>
                <div class="rfm-form-field rfm-form-field-half">
                    <label><?php _e('År (slut)', 'rigtig-for-mig'); ?></label>
                    <input type="text"
                           name="educations[<?php echo esc_attr($index); ?>][year_end]"
                           value="<?php echo esc_attr($education['year_end'] ?? ''); ?>"
                           placeholder="2022" />
                </div>
            </div>

            <div class="rfm-form-field">
                <label><?php _e('Beskrivelse', 'rigtig-for-mig'); ?></label>
                <textarea name="educations[<?php echo esc_attr($index); ?>][description]"
                          rows="3"
                          placeholder="<?php esc_attr_e('Beskriv hvad du lærte...', 'rigtig-for-mig'); ?>"><?php echo esc_textarea($education['description'] ?? ''); ?></textarea>
            </div>

            <?php if ($is_standard_or_higher): ?>
            <div class="rfm-form-field">
                <label><?php _e('Diplom/Certifikat billede', 'rigtig-for-mig'); ?></label>
                <input type="hidden"
                       name="educations[<?php echo esc_attr($index); ?>][image_id]"
                       value="<?php echo esc_attr($education['image_id'] ?? ''); ?>"
                       class="rfm-cat-education-image-id" />

                <div class="rfm-image-preview <?php echo !empty($education['image_id']) ? 'has-image' : ''; ?>">
                    <?php if (!empty($education['image_id'])): ?>
                        <?php echo wp_get_attachment_image($education['image_id'], 'medium'); ?>
                    <?php endif; ?>
                </div>

                <div class="rfm-image-buttons">
                    <button type="button" class="rfm-btn rfm-btn-small rfm-upload-cat-education-image">
                        <?php echo !empty($education['image_id']) ? __('Skift billede', 'rigtig-for-mig') : __('Upload billede', 'rigtig-for-mig'); ?>
                    </button>
                    <?php if (!empty($education['image_id'])): ?>
                    <button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger rfm-remove-cat-education-image">
                        <?php _e('Fjern', 'rigtig-for-mig'); ?>
                    </button>
                    <?php endif; ?>
                </div>

                <input type="file"
                       class="rfm-cat-education-image-input"
                       accept="image/jpeg,image/png,image/gif,image/webp"
                       style="display: none;" />
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
