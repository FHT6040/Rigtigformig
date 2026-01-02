<?php
/**
 * Expert Registration Management
 *
 * Handles expert registration form and submission
 *
 * @package Rigtig_For_Mig
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Expert_Registration {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Shortcodes
        add_shortcode('rfm_expert_registration', array($this, 'registration_form_shortcode'));

        // AJAX handlers
        add_action('wp_ajax_rfm_submit_expert_registration', array($this, 'handle_registration'));
        add_action('wp_ajax_nopriv_rfm_submit_expert_registration', array($this, 'handle_registration'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue registration scripts
     */
    public function enqueue_scripts() {
        // Only load on frontend
        if (is_admin()) {
            return;
        }

        wp_enqueue_script(
            'rfm-expert-registration',
            RFM_PLUGIN_URL . 'assets/js/expert-registration.js',
            array('jquery'),
            RFM_VERSION,
            true
        );

        wp_localize_script('rfm-expert-registration', 'rfmRegistration', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'strings' => array(
                'creating' => __('Opretter...', 'rigtig-for-mig'),
                'createProfile' => __('Opret Profil', 'rigtig-for-mig'),
                'passwordMismatch' => __('Adgangskoderne matcher ikke.', 'rigtig-for-mig'),
                'error' => __('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig')
            )
        ));
    }

    /**
     * Expert registration form shortcode
     */
    public function registration_form_shortcode($atts) {
        if (!get_option('rfm_allow_frontend_registration', true)) {
            return '<p>' . __('Registrering er i øjeblikket ikke tilgængelig.', 'rigtig-for-mig') . '</p>';
        }

        if (is_user_logged_in()) {
            return '<p>' . __('Du er allerede logget ind.', 'rigtig-for-mig') . ' <a href="' . esc_url(home_url('/ekspert-dashboard/')) . '">' . __('Gå til dit dashboard', 'rigtig-for-mig') . '</a></p>';
        }

        $plans = RFM_Subscriptions::get_plans();

        ob_start();
        ?>
        <div class="rfm-registration-form">
            <h2><?php _e('Opret Ekspert Profil', 'rigtig-for-mig'); ?></h2>

            <form id="rfm-expert-registration-form" method="post">
                <?php wp_nonce_field('rfm_registration', 'rfm_registration_nonce'); ?>

                <div class="rfm-form-section">
                    <h3><?php _e('Konto Information', 'rigtig-for-mig'); ?></h3>

                    <p class="rfm-form-field">
                        <label for="reg_name"><?php _e('Dit fulde navn', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="text" name="name" id="reg_name" required />
                    </p>

                    <p class="rfm-form-field">
                        <label for="reg_email"><?php _e('Email', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="email" name="email" id="reg_email" required />
                    </p>

                    <p class="rfm-form-field">
                        <label for="reg_password"><?php _e('Adgangskode', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="password" name="password" id="reg_password" required />
                    </p>

                    <p class="rfm-form-field">
                        <label for="reg_password_confirm"><?php _e('Bekræft adgangskode', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="password" name="password_confirm" id="reg_password_confirm" required />
                    </p>
                </div>

                <div class="rfm-form-section">
                    <h3><?php _e('Profil Information', 'rigtig-for-mig'); ?></h3>

                    <p class="rfm-form-field">
                        <label for="reg_phone"><?php _e('Telefon', 'rigtig-for-mig'); ?></label>
                        <input type="tel" name="phone" id="reg_phone" />
                    </p>

                    <p class="rfm-form-field">
                        <label for="reg_website"><?php _e('Hjemmeside', 'rigtig-for-mig'); ?></label>
                        <input type="url" name="website" id="reg_website" />
                    </p>

                    <p class="rfm-form-field">
                        <label for="reg_about"><?php _e('Om dig', 'rigtig-for-mig'); ?></label>
                        <textarea name="about" id="reg_about" rows="5"></textarea>
                    </p>

                    <p class="rfm-form-field">
                        <label for="reg_city"><?php _e('By', 'rigtig-for-mig'); ?></label>
                        <input type="text" name="city" id="reg_city" />
                    </p>
                </div>

                <div class="rfm-form-section">
                    <h3><?php _e('Vælg Kategori', 'rigtig-for-mig'); ?> <span class="required">*</span></h3>
                    <p class="rfm-category-limit-info">
                        <?php _e('Gratis: 1 kategori | Standard: op til 2 kategorier | Premium: alle kategorier', 'rigtig-for-mig'); ?>
                    </p>

                    <div class="rfm-category-checkboxes"
                         id="rfm-registration-categories"
                         data-limit-free="1"
                         data-limit-standard="2"
                         data-limit-premium="99">
                    <?php
                    $categories = get_terms(array(
                        'taxonomy' => 'rfm_category',
                        'hide_empty' => false
                    ));

                    if ($categories) {
                        foreach ($categories as $category) {
                            $color = RFM_Taxonomies::get_category_color($category->term_id);
                            echo '<label class="rfm-category-choice" style="border-left: 4px solid ' . esc_attr($color) . ';">';
                            echo '<input type="checkbox" name="categories[]" value="' . esc_attr($category->term_id) . '" class="rfm-category-checkbox" />';
                            echo ' <strong>' . esc_html($category->name) . '</strong>';
                            echo '<br><small>' . esc_html($category->description) . '</small>';
                            echo '</label>';
                        }
                    }
                    ?>
                    </div>
                    <p class="rfm-category-limit-notice" id="rfm-category-limit-notice" style="display: none; color: #e74c3c; font-weight: 500;">
                        <?php _e('Du har nået maksimum antal kategorier for den valgte plan.', 'rigtig-for-mig'); ?>
                    </p>
                </div>

                <div class="rfm-form-section">
                    <h3><?php _e('Vælg Abonnementsplan', 'rigtig-for-mig'); ?> <span class="required">*</span></h3>

                    <div class="rfm-plan-choices">
                        <?php foreach ($plans as $plan_id => $plan): ?>
                            <label class="rfm-plan-choice">
                                <input type="radio" name="plan" value="<?php echo esc_attr($plan_id); ?>" <?php checked($plan_id, 'free'); ?> required />
                                <div class="rfm-plan-details">
                                    <h4><?php echo esc_html($plan['name']); ?></h4>
                                    <div class="rfm-plan-price">
                                        <?php if ($plan['price'] > 0): ?>
                                            <?php echo number_format($plan['price'], 0, ',', '.'); ?> kr/mdr
                                        <?php else: ?>
                                            <?php _e('Gratis', 'rigtig-for-mig'); ?>
                                        <?php endif; ?>
                                    </div>
                                    <ul class="rfm-plan-features">
                                        <?php foreach ($plan['features'] as $feature): ?>
                                            <li><?php echo esc_html($feature); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <p class="rfm-form-field">
                    <label>
                        <input type="checkbox" name="terms" required />
                        <?php _e('Jeg accepterer vilkår og betingelser', 'rigtig-for-mig'); ?> <span class="required">*</span>
                    </label>
                </p>

                <div id="rfm-registration-message"></div>

                <p class="rfm-form-submit">
                    <button type="submit" class="rfm-btn rfm-btn-primary"><?php _e('Opret Profil', 'rigtig-for-mig'); ?></button>
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle registration submission
     */
    public function handle_registration() {
        check_ajax_referer('rfm_registration', 'rfm_registration_nonce');

        // Validate required fields
        if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['password'])) {
            wp_send_json_error(array('message' => __('Alle obligatoriske felter skal udfyldes.', 'rigtig-for-mig')));
        }

        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $phone = sanitize_text_field($_POST['phone']);
        $website = esc_url_raw($_POST['website']);
        $about = sanitize_textarea_field($_POST['about']);
        $city = sanitize_text_field($_POST['city']);
        $categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : array();
        $plan = sanitize_text_field($_POST['plan']);

        // Validate category limits based on plan
        $max_categories = array(
            'free' => 1,
            'standard' => 2,
            'premium' => 99
        );
        $allowed_cats = $max_categories[$plan] ?? 1;

        if (count($categories) > $allowed_cats) {
            // Limit to allowed number of categories
            $categories = array_slice($categories, 0, $allowed_cats);
        }

        if (empty($categories)) {
            wp_send_json_error(array('message' => __('Vælg mindst én kategori.', 'rigtig-for-mig')));
        }

        // Check if email exists
        if (email_exists($email)) {
            wp_send_json_error(array('message' => __('Denne email er allerede registreret.', 'rigtig-for-mig')));
        }

        // Create user with expert role
        $user_id = wp_create_user($email, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }

        // Get user object
        $user = new WP_User($user_id);

        // Remove default role and set expert role
        $user->remove_role('subscriber');
        $user->add_role('rfm_expert_user');

        // Update user data
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $name
        ));

        // Create expert profile
        // If email verification is required, set to draft until verified
        // Otherwise use auto_approve setting
        $require_email_verification = get_option('rfm_email_verification', true);
        $auto_approve = get_option('rfm_auto_approve_experts', true); // Default to true

        // If email verification is required, always start as draft
        $post_status = 'draft';
        if (!$require_email_verification && $auto_approve) {
            $post_status = 'publish';
        }

        $expert_id = wp_insert_post(array(
            'post_title' => $name,
            'post_type' => 'rfm_expert',
            'post_status' => $post_status,
            'post_author' => $user_id
        ));

        if (!$expert_id || is_wp_error($expert_id)) {
            wp_send_json_error(array('message' => __('Kunne ikke oprette profil. Prøv igen.', 'rigtig-for-mig')));
        }

        // Save profile meta
        update_post_meta($expert_id, '_rfm_email', $email);
        update_post_meta($expert_id, '_rfm_phone', $phone);
        update_post_meta($expert_id, '_rfm_website', $website);
        update_post_meta($expert_id, '_rfm_about_me', $about);
        update_post_meta($expert_id, '_rfm_city', $city);
        update_post_meta($expert_id, '_rfm_subscription_plan', $plan);
        update_post_meta($expert_id, '_rfm_subscription_status', 'active');

        // Set categories
        if (!empty($categories)) {
            wp_set_object_terms($expert_id, $categories, 'rfm_category');
        }

        // Create subscription
        RFM_Subscriptions::get_instance()->create_subscription($expert_id, $user_id, $plan);

        // Send verification email
        RFM_Email_Verification::get_instance()->send_verification_email($expert_id, $email, $user_id);

        // Auto-login user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        wp_send_json_success(array(
            'message' => __('Din profil er oprettet! Du bliver omdirigeret...', 'rigtig-for-mig'),
            'redirect' => home_url('/ekspert-dashboard/')
        ));
    }
}
