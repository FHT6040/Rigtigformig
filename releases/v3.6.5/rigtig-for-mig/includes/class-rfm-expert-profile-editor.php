<?php
/**
 * Expert Profile Editor
 *
 * Handles the expert profile editing interface with image uploads,
 * category/specialization management, and plan-based restrictions.
 *
 * Part of Phase 2 Refactoring - extracted from RFM_Frontend_Registration
 *
 * @package Rigtig_For_Mig
 * @since 3.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Expert_Profile_Editor {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register shortcode
        add_shortcode('rfm_expert_profile_edit', array($this, 'profile_edit_shortcode'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handler
        add_action('wp_ajax_rfm_update_expert_profile', array($this, 'handle_profile_update'));
    }

    /**
     * Enqueue profile editor scripts
     */
    public function enqueue_scripts() {
        // Only enqueue on frontend
        if (is_admin()) {
            return;
        }

        wp_enqueue_script(
            'rfm-expert-profile-editor',
            RFM_PLUGIN_URL . 'assets/js/expert-profile-editor.js',
            array('jquery'),
            RFM_VERSION,
            true
        );

        // Localize script
        wp_localize_script('rfm-expert-profile-editor', 'rfmProfileEditor', array(
            'strings' => array(
                'savingText' => __('Gemmer...', 'rigtig-for-mig'),
                'submitText' => __('Gem Ændringer', 'rigtig-for-mig'),
                'errorText' => __('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig')
            )
        ));
    }

    /**
     * Profile Edit Shortcode
     *
     * Renders the expert profile editing form with:
     * - User authentication and role verification
     * - Plan-based feature restrictions (banner image, category/specialization limits)
     * - Image upload handling (profile and banner images)
     * - Category and specialization selection with dynamic limits
     * - Inline JavaScript for AJAX form submission
     *
     * Security:
     * - Checks user login status
     * - Verifies expert role
     * - Validates profile ownership
     * - Includes nonce verification for form submission
     *
     * Plan-based Restrictions:
     * - Free: 1 category, 1 specialization, no banner
     * - Standard: 2 categories, 3 specializations, banner allowed
     * - Premium: All categories, 7 specializations, banner allowed
     *
     * @param array $atts Shortcode attributes (currently unused)
     * @return string The rendered profile edit form HTML
     *
     * @since 3.0.0
     * @since 3.6.0 Extracted to RFM_Expert_Profile_Editor class with bug fix for $plan initialization
     */
    public function profile_edit_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Du skal være logget ind for at redigere din profil.', 'rigtig-for-mig') . ' <a href="' . home_url('/ekspert-login/') . '">' . __('Log ind', 'rigtig-for-mig') . '</a></p>';
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        // Check if user is expert
        if (!in_array('rfm_expert_user', $user->roles) && !in_array('administrator', $user->roles)) {
            return '<p>' . __('Du har ikke adgang til denne side.', 'rigtig-for-mig') . '</p>';
        }

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

        // BUG FIX: Initialize $plan variable (was previously undefined)
        $plan = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);

        // Get current data
        $name = get_the_title($expert_id);
        $email = get_post_meta($expert_id, '_rfm_email', true);
        $phone = get_post_meta($expert_id, '_rfm_phone', true);
        $website = get_post_meta($expert_id, '_rfm_website', true);
        $about = get_post_meta($expert_id, '_rfm_about_me', true);
        $city = get_post_meta($expert_id, '_rfm_city', true);
        $years_experience = get_post_meta($expert_id, '_rfm_years_experience', true);

        // Get categories
        $expert_categories = wp_get_object_terms($expert_id, 'rfm_category', array('fields' => 'ids'));
        $all_categories = get_terms(array('taxonomy' => 'rfm_category', 'hide_empty' => false));

        // Get specializations
        $expert_specializations = wp_get_object_terms($expert_id, 'rfm_specialization', array('fields' => 'ids'));
        $all_specializations = get_terms(array('taxonomy' => 'rfm_specialization', 'hide_empty' => false));

        // Define max specializations per plan
        $max_specializations = array(
            'free' => 1,
            'standard' => 3,
            'premium' => 7
        );
        $current_max_specs = $max_specializations[$plan] ?? 1;

        ob_start();
        ?>
        <div class="rfm-profile-edit-form">
            <h2><?php _e('Rediger Din Profil', 'rigtig-for-mig'); ?></h2>

            <form id="rfm-profile-edit-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('rfm_profile_edit', 'rfm_profile_edit_nonce'); ?>
                <input type="hidden" name="expert_id" value="<?php echo esc_attr($expert_id); ?>" />

                <div class="rfm-form-section">
                    <h3><?php _e('Profil Billeder', 'rigtig-for-mig'); ?></h3>

                    <?php if ($plan === 'standard' || $plan === 'premium'): ?>
                    <p class="rfm-form-field">
                        <label for="edit_banner_image"><?php _e('Header Billede (16:9)', 'rigtig-for-mig'); ?></label>
                        <?php
                        $banner_id = get_post_meta($expert_id, '_rfm_banner_image_id', true);
                        $banner_url = $banner_id ? wp_get_attachment_image_url($banner_id, 'large') : '';
                        ?>
                        <input type="file" name="banner_image" id="edit_banner_image" accept="image/*" />
                        <small><?php _e('Upload et bredt billede til toppen af din profil (anbefalet: 1920x1080px)', 'rigtig-for-mig'); ?></small>
                        <?php if ($banner_url): ?>
                            <div class="rfm-current-image" style="margin-top: 10px;">
                                <img src="<?php echo esc_url($banner_url); ?>" style="max-width: 100%; height: auto; border-radius: 5px;" />
                                <label style="display: block; margin-top: 5px;">
                                    <input type="checkbox" name="remove_banner" value="1" />
                                    <?php _e('Fjern header billede', 'rigtig-for-mig'); ?>
                                </label>
                            </div>
                        <?php endif; ?>
                    </p>
                    <?php else: ?>
                    <div class="rfm-form-field rfm-locked-feature">
                        <label><?php _e('Header Billede (16:9)', 'rigtig-for-mig'); ?></label>
                        <div class="rfm-upgrade-notice">
                            <span class="dashicons dashicons-lock"></span>
                            <?php _e('Opgrader til Standard eller Premium for at uploade et header billede til din profil.', 'rigtig-for-mig'); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <p class="rfm-form-field">
                        <label for="edit_profile_image"><?php _e('Profil Billede (rundt)', 'rigtig-for-mig'); ?></label>
                        <?php
                        $profile_image_id = get_post_thumbnail_id($expert_id);
                        $profile_image_url = $profile_image_id ? wp_get_attachment_image_url($profile_image_id, 'thumbnail') : '';
                        ?>
                        <input type="file" name="profile_image" id="edit_profile_image" accept="image/*" />
                        <small><?php _e('Upload et kvadratisk billede af dig (anbefalet: 400x400px)', 'rigtig-for-mig'); ?></small>
                        <?php if ($profile_image_url): ?>
                            <div class="rfm-current-image" style="margin-top: 10px;">
                                <img src="<?php echo esc_url($profile_image_url); ?>" style="max-width: 150px; height: auto; border-radius: 50%;" />
                                <label style="display: block; margin-top: 5px;">
                                    <input type="checkbox" name="remove_profile_image" value="1" />
                                    <?php _e('Fjern profil billede', 'rigtig-for-mig'); ?>
                                </label>
                            </div>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="rfm-form-section">
                    <h3><?php _e('Basis Information', 'rigtig-for-mig'); ?></h3>

                    <p class="rfm-form-field">
                        <label for="edit_name"><?php _e('Dit fulde navn', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="text" name="name" id="edit_name" value="<?php echo esc_attr($name); ?>" required />
                    </p>

                    <p class="rfm-form-field">
                        <label for="edit_email"><?php _e('Email', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="email" name="email" id="edit_email" value="<?php echo esc_attr($email); ?>" required />
                    </p>

                    <p class="rfm-form-field">
                        <label for="edit_phone"><?php _e('Telefon', 'rigtig-for-mig'); ?></label>
                        <input type="tel" name="phone" id="edit_phone" value="<?php echo esc_attr($phone); ?>" />
                    </p>

                    <p class="rfm-form-field">
                        <label for="edit_website"><?php _e('Hjemmeside', 'rigtig-for-mig'); ?></label>
                        <input type="url" name="website" id="edit_website" value="<?php echo esc_attr($website); ?>" />
                    </p>

                    <p class="rfm-form-field">
                        <label for="edit_city"><?php _e('By', 'rigtig-for-mig'); ?></label>
                        <input type="text" name="city" id="edit_city" value="<?php echo esc_attr($city); ?>" />
                    </p>

                    <p class="rfm-form-field">
                        <label for="edit_experience"><?php _e('År i branchen', 'rigtig-for-mig'); ?></label>
                        <input type="number" name="years_experience" id="edit_experience" value="<?php echo esc_attr($years_experience); ?>" min="0" max="60" />
                    </p>
                </div>

                <div class="rfm-form-section">
                    <h3><?php _e('Om Mig', 'rigtig-for-mig'); ?></h3>

                    <p class="rfm-form-field">
                        <label for="edit_about"><?php _e('Fortæl om dig selv', 'rigtig-for-mig'); ?></label>
                        <textarea name="about" id="edit_about" rows="8"><?php echo esc_textarea($about); ?></textarea>
                        <small><?php _e('Beskriv din baggrund, din tilgang og hvad du kan hjælpe med.', 'rigtig-for-mig'); ?></small>
                    </p>
                </div>

                <div class="rfm-form-section">
                    <h3><?php _e('Kategorier', 'rigtig-for-mig'); ?></h3>
                    <?php
                    // Define max categories per plan
                    $max_categories = array(
                        'free' => 1,
                        'standard' => 2,
                        'premium' => 99
                    );
                    $current_max_cats = $max_categories[$plan] ?? 1;
                    ?>
                    <p class="rfm-category-limit-info">
                        <?php
                        if ($plan === 'free') {
                            _e('Du kan vælge 1 kategori. Opgrader for at vælge flere.', 'rigtig-for-mig');
                        } elseif ($plan === 'standard') {
                            _e('Du kan vælge op til 2 kategorier. Opgrader til Premium for alle kategorier.', 'rigtig-for-mig');
                        } else {
                            _e('Du kan vælge alle kategorier.', 'rigtig-for-mig');
                        }
                        ?>
                    </p>

                    <?php if ($all_categories): ?>
                        <div class="rfm-category-checkboxes"
                             id="rfm-dashboard-categories"
                             data-max="<?php echo esc_attr($current_max_cats); ?>"
                             data-plan="<?php echo esc_attr($plan); ?>">
                            <?php foreach ($all_categories as $category): ?>
                                <?php $color = RFM_Taxonomies::get_category_color($category->term_id); ?>
                                <label class="rfm-category-choice" style="border-left: 4px solid <?php echo esc_attr($color); ?>;">
                                    <input type="checkbox" name="categories[]" value="<?php echo esc_attr($category->term_id); ?>" class="rfm-category-checkbox" <?php checked(in_array($category->term_id, $expert_categories)); ?> />
                                    <strong><?php echo esc_html($category->name); ?></strong>
                                    <?php if ($category->description): ?>
                                        <br><small><?php echo esc_html($category->description); ?></small>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="rfm-category-limit-notice" id="rfm-category-limit-notice-dashboard" style="display: none; color: #e74c3c; font-weight: 500;">
                            <?php printf(__('Du har nået maksimum %d kategorier for dit medlemsniveau.', 'rigtig-for-mig'), $current_max_cats); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="rfm-form-section">
                    <h3><?php _e('Specialiseringer', 'rigtig-for-mig'); ?></h3>
                    <p class="rfm-category-limit-info">
                        <?php
                        if ($plan === 'free') {
                            _e('Du kan vælge 1 specialisering. Opgrader for at vælge flere.', 'rigtig-for-mig');
                        } elseif ($plan === 'standard') {
                            printf(__('Du kan vælge op til %d specialiseringer. Opgrader til Premium for flere.', 'rigtig-for-mig'), $current_max_specs);
                        } else {
                            printf(__('Du kan vælge op til %d specialiseringer.', 'rigtig-for-mig'), $current_max_specs);
                        }
                        ?>
                    </p>

                    <?php if ($all_specializations && !is_wp_error($all_specializations)): ?>
                        <div class="rfm-specialization-checkboxes"
                             id="rfm-dashboard-specializations"
                             data-max="<?php echo esc_attr($current_max_specs); ?>"
                             data-plan="<?php echo esc_attr($plan); ?>">
                            <?php foreach ($all_specializations as $spec): ?>
                                <label class="rfm-specialization-choice">
                                    <input type="checkbox"
                                           name="specializations[]"
                                           value="<?php echo esc_attr($spec->term_id); ?>"
                                           class="rfm-specialization-checkbox"
                                           <?php checked(in_array($spec->term_id, $expert_specializations)); ?> />
                                    <span><?php echo esc_html($spec->name); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="rfm-specialization-limit-notice" id="rfm-specialization-limit-notice" style="display: none; color: #e74c3c; font-weight: 500;">
                            <?php printf(__('Du har nået maksimum %d specialiseringer for dit medlemsniveau.', 'rigtig-for-mig'), $current_max_specs); ?>
                        </p>
                    <?php else: ?>
                        <p class="rfm-no-specializations">
                            <?php _e('Ingen specialiseringer tilgængelige. Kontakt administrator.', 'rigtig-for-mig'); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div id="rfm-profile-edit-message"></div>

                <p class="rfm-form-submit">
                    <button type="submit" class="rfm-btn rfm-btn-primary"><?php _e('Gem Ændringer', 'rigtig-for-mig'); ?></button>
                    <a href="<?php echo home_url('/ekspert-dashboard/'); ?>" class="rfm-btn"><?php _e('Tilbage til dashboard', 'rigtig-for-mig'); ?></a>
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle Profile Update (AJAX Handler)
     *
     * Processes profile update submissions via AJAX with:
     * - Nonce verification for security
     * - User authentication and ownership validation
     * - Image upload handling (profile and banner images)
     * - Image removal handling
     * - Post meta updates (email, phone, website, city, about, experience)
     * - Category and specialization updates with plan-based limits
     *
     * Security Checks:
     * - AJAX nonce verification
     * - User login status check
     * - Profile ownership validation
     *
     * Plan-based Limits (enforced server-side):
     * - Categories: Free=1, Standard=2, Premium=99
     * - Specializations: Free=1, Standard=3, Premium=7
     * - Limits are enforced by array slicing if exceeded
     *
     * File Uploads:
     * - Uses WordPress media_handle_upload() for security
     * - Attaches uploads to the expert post
     * - Handles both upload and removal operations
     * - Deletes old attachments on removal
     *
     * Response Format:
     * - Success: JSON with success=true and message
     * - Error: JSON with success=false and error message
     *
     * @since 3.0.0
     * @since 3.6.0 Extracted to RFM_Expert_Profile_Editor class
     *
     * @return void Outputs JSON response and terminates
     */
    public function handle_profile_update() {
        check_ajax_referer('rfm_profile_edit', 'rfm_profile_edit_nonce');

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

        // Handle image uploads
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Handle banner image upload
        if (!empty($_FILES['banner_image']['name'])) {
            $banner_id = media_handle_upload('banner_image', $expert_id);
            if (!is_wp_error($banner_id)) {
                update_post_meta($expert_id, '_rfm_banner_image_id', $banner_id);
            }
        }

        // Handle banner image removal
        if (isset($_POST['remove_banner']) && $_POST['remove_banner'] == '1') {
            $old_banner_id = get_post_meta($expert_id, '_rfm_banner_image_id', true);
            if ($old_banner_id) {
                wp_delete_attachment($old_banner_id, true);
                delete_post_meta($expert_id, '_rfm_banner_image_id');
            }
        }

        // Handle profile image upload
        if (!empty($_FILES['profile_image']['name'])) {
            $profile_id = media_handle_upload('profile_image', $expert_id);
            if (!is_wp_error($profile_id)) {
                set_post_thumbnail($expert_id, $profile_id);
            }
        }

        // Handle profile image removal
        if (isset($_POST['remove_profile_image']) && $_POST['remove_profile_image'] == '1') {
            delete_post_thumbnail($expert_id);
        }

        // Update post title (name)
        wp_update_post(array(
            'ID' => $expert_id,
            'post_title' => sanitize_text_field($_POST['name'])
        ));

        // Update meta fields
        update_post_meta($expert_id, '_rfm_email', sanitize_email($_POST['email']));
        update_post_meta($expert_id, '_rfm_phone', sanitize_text_field($_POST['phone']));
        update_post_meta($expert_id, '_rfm_website', esc_url_raw($_POST['website']));
        update_post_meta($expert_id, '_rfm_city', sanitize_text_field($_POST['city']));
        update_post_meta($expert_id, '_rfm_about_me', sanitize_textarea_field($_POST['about']));
        update_post_meta($expert_id, '_rfm_years_experience', intval($_POST['years_experience']));

        // Update categories
        if (isset($_POST['categories'])) {
            $categories = array_map('intval', $_POST['categories']);

            // Get current plan and validate category limit
            $plan = get_post_meta($expert_id, '_rfm_subscription_plan', true) ?: 'free';
            $max_categories = array(
                'free' => 1,
                'standard' => 2,
                'premium' => 99
            );
            $allowed_cats = $max_categories[$plan] ?? 1;

            // Limit to allowed number of categories
            if (count($categories) > $allowed_cats) {
                $categories = array_slice($categories, 0, $allowed_cats);
            }

            wp_set_object_terms($expert_id, $categories, 'rfm_category');
        } else {
            wp_set_object_terms($expert_id, array(), 'rfm_category');
        }

        // Update specializations
        if (isset($_POST['specializations'])) {
            $specializations = array_map('intval', $_POST['specializations']);

            // Validate specialization limit
            $max_specializations = array(
                'free' => 1,
                'standard' => 3,
                'premium' => 7
            );
            $allowed_specs = $max_specializations[$plan] ?? 1;

            // Limit to allowed number of specializations
            if (count($specializations) > $allowed_specs) {
                $specializations = array_slice($specializations, 0, $allowed_specs);
            }

            wp_set_object_terms($expert_id, $specializations, 'rfm_specialization');
        } else {
            wp_set_object_terms($expert_id, array(), 'rfm_specialization');
        }

        wp_send_json_success(array(
            'message' => __('✅ Din profil er opdateret!', 'rigtig-for-mig')
        ));
    }
}
