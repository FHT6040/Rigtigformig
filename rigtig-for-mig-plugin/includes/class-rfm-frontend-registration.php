<?php
/**
 * Frontend Expert Registration and Profile Management
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Frontend_Registration {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Shortcodes for frontend forms
        add_shortcode('rfm_expert_registration', array($this, 'registration_form_shortcode'));
        add_shortcode('rfm_expert_dashboard', array($this, 'dashboard_shortcode'));
        add_shortcode('rfm_expert_login', array($this, 'login_form_shortcode'));
        add_shortcode('rfm_expert_profile_edit', array($this, 'profile_edit_shortcode'));
        add_shortcode('rfm_expert_dashboard_tabbed', array($this, 'tabbed_dashboard_shortcode'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handlers
        add_action('wp_ajax_rfm_submit_expert_registration', array($this, 'handle_registration'));
        add_action('wp_ajax_nopriv_rfm_submit_expert_registration', array($this, 'handle_registration'));

        add_action('wp_ajax_rfm_expert_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_rfm_expert_login', array($this, 'handle_login'));

        add_action('wp_ajax_rfm_update_expert_profile', array($this, 'handle_profile_update'));

        add_action('wp_ajax_rfm_update_dashboard_profile', array($this, 'handle_dashboard_profile_update'));

        add_action('wp_ajax_rfm_upload_education_image', array($this, 'handle_education_image_upload'));

        add_action('wp_ajax_rfm_expert_logout', array($this, 'handle_logout'));

        // Add expert role
        add_action('init', array($this, 'add_expert_role'));

        // Custom login redirect
        add_filter('login_redirect', array($this, 'expert_login_redirect'), 10, 3);

        // Custom logout redirect
        add_filter('logout_redirect', array($this, 'expert_logout_redirect'), 10, 3);

        // Hide admin bar for experts - multiple hooks to ensure it works
        add_action('after_setup_theme', array($this, 'hide_admin_bar_for_experts'));
        add_filter('show_admin_bar', array($this, 'hide_admin_bar_filter'));
        add_action('init', array($this, 'remove_admin_bar_for_experts'), 9);

        // Add body class for experts
        add_filter('body_class', array($this, 'add_expert_body_class'));

        // Block admin access for experts
        add_action('admin_init', array($this, 'block_admin_access_for_experts'));

        // Redirect experts away from wp-login.php
        add_action('login_init', array($this, 'redirect_experts_from_wp_login'));
    }

    /**
     * Enqueue expert forms scripts
     */
    public function enqueue_scripts() {
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        // Check if page has expert login or registration shortcode
        if (has_shortcode($post->post_content, 'rfm_expert_login') ||
            has_shortcode($post->post_content, 'rfm_expert_registration')) {

            wp_enqueue_script(
                'rfm-expert-forms',
                RFM_PLUGIN_URL . 'assets/js/expert-forms.js',
                array('jquery'),
                RFM_VERSION,
                true
            );

            wp_localize_script('rfm-expert-forms', 'rfmExpertForms', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'strings' => array(
                    'loggingIn' => __('Logger ind...', 'rigtig-for-mig'),
                    'login' => __('Log ind', 'rigtig-for-mig'),
                    'error' => __('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig'),
                    'passwordMismatch' => __('Adgangskoderne matcher ikke.', 'rigtig-for-mig'),
                    'creating' => __('Opretter...', 'rigtig-for-mig'),
                    'createProfile' => __('Opret Profil', 'rigtig-for-mig')
                )
            ));
        }
    }
    
    /**
     * Check and ensure expert user role exists
     */
    public function add_expert_role() {
        // Check if role exists, if not create it
        if (!get_role('rfm_expert_user')) {
            add_role(
                'rfm_expert_user',
                __('Ekspert', 'rigtig-for-mig'),
                array(
                    'read' => true,
                    'edit_posts' => true,
                    'edit_published_posts' => true,
                    'delete_posts' => false,
                    'upload_files' => true,
                )
            );
        }
        
        // Allow experts to access admin for editing their profile
        add_action('admin_init', array($this, 'restrict_expert_admin_access'));
        add_filter('user_has_cap', array($this, 'expert_edit_own_profile'), 10, 4);
    }
    
    /**
     * Restrict expert admin access to only their profile
     */
    public function restrict_expert_admin_access() {
        $user = wp_get_current_user();
        
        if (in_array('rfm_expert_user', $user->roles)) {
            // Get current screen
            $screen = get_current_screen();
            
            // Allow only specific admin pages
            $allowed_pages = array('post', 'upload', 'profile', 'user-edit');
            
            if ($screen && !in_array($screen->base, $allowed_pages)) {
                // Redirect to their dashboard if trying to access other admin pages
                wp_redirect(home_url('/ekspert-dashboard/'));
                exit;
            }
        }
    }
    
    /**
     * Allow experts to edit only their own profile posts
     */
    public function expert_edit_own_profile($allcaps, $caps, $args, $user) {
        if (!isset($args[0]) || !isset($args[2])) {
            return $allcaps;
        }
        
        // Check if user is expert
        if (isset($user->roles) && in_array('rfm_expert_user', $user->roles)) {
            $post_id = $args[2];
            $post = get_post($post_id);
            
            // Allow editing only if it's their own expert post
            if ($post && $post->post_type === 'rfm_expert' && $post->post_author == $user->ID) {
                $allcaps['edit_post'] = true;
                $allcaps['edit_published_posts'] = true;
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Login form shortcode
     */
    public function login_form_shortcode($atts) {
        if (is_user_logged_in()) {
            return '<p>' . __('Du er allerede logget ind.', 'rigtig-for-mig') . ' <a href="' . esc_url(home_url('/ekspert-dashboard/')) . '">' . __('Gå til dit dashboard', 'rigtig-for-mig') . '</a></p>';
        }
        
        ob_start();
        ?>
        <div class="rfm-login-form">
            <h2><?php _e('Ekspert Login', 'rigtig-for-mig'); ?></h2>
            
            <form id="rfm-expert-login-form" method="post">
                <?php wp_nonce_field('rfm_login', 'rfm_login_nonce'); ?>
                
                <p class="rfm-form-field">
                    <label for="login_email"><?php _e('Email', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                    <input type="email" name="email" id="login_email" required />
                </p>
                
                <p class="rfm-form-field">
                    <label for="login_password"><?php _e('Adgangskode', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                    <input type="password" name="password" id="login_password" required />
                </p>
                
                <p class="rfm-form-field">
                    <label>
                        <input type="checkbox" name="remember" value="1" />
                        <?php _e('Husk mig', 'rigtig-for-mig'); ?>
                    </label>
                </p>
                
                <div id="rfm-login-message"></div>
                
                <p class="rfm-form-submit">
                    <button type="submit" class="rfm-btn rfm-btn-primary"><?php _e('Log ind', 'rigtig-for-mig'); ?></button>
                </p>
                
                <p class="rfm-form-links">
                    <a href="<?php echo home_url('/glemt-adgangskode/'); ?>"><?php _e('Glemt adgangskode?', 'rigtig-for-mig'); ?></a>
                    <span class="sep">|</span>
                    <a href="<?php echo home_url('/opret-ekspert-profil/'); ?>"><?php _e('Opret ny profil', 'rigtig-for-mig'); ?></a>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#rfm-expert-login-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $button = $form.find('button[type="submit"]');
                var $message = $('#rfm-login-message');
                
                // Disable button
                $button.prop('disabled', true).text('Logger ind...');
                $message.html('');
                
                $.ajax({
                    url: rfmData.ajaxurl,
                    type: 'POST',
                    data: $form.serialize() + '&action=rfm_expert_login',
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="rfm-success">' + response.data.message + '</div>');
                            
                            // Redirect after 1 second
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1000);
                        } else {
                            $message.html('<div class="rfm-error">' + response.data.message + '</div>');
                            $button.prop('disabled', false).text('Log ind');
                        }
                    },
                    error: function() {
                        $message.html('<div class="rfm-error">Der opstod en fejl. Prøv igen.</div>');
                        $button.prop('disabled', false).text('Log ind');
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle login submission
     */
    public function handle_login() {
        check_ajax_referer('rfm_login', 'rfm_login_nonce');
        
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        
        if (empty($email) || empty($password)) {
            wp_send_json_error(array('message' => __('Email og adgangskode er påkrævet.', 'rigtig-for-mig')));
        }
        
        // Check if user exists first
        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error(array('message' => __('Ingen bruger fundet med denne email. Har du oprettet en profil?', 'rigtig-for-mig')));
        }
        
        // Try to authenticate
        $creds = array(
            'user_login'    => $email,
            'user_password' => $password,
            'remember'      => $remember
        );
        
        $signed_in = wp_signon($creds, false);
        
        if (is_wp_error($signed_in)) {
            wp_send_json_error(array('message' => __('Forkert adgangskode. Prøv igen eller klik "Glemt adgangskode".', 'rigtig-for-mig')));
        }
        
        // Check if user is an expert
        if (!in_array('rfm_expert_user', $signed_in->roles) && !in_array('administrator', $signed_in->roles)) {
            wp_logout();
            wp_send_json_error(array('message' => __('Din bruger er ikke en ekspert. Kontakt administrator for at få tildelt ekspert rolle.', 'rigtig-for-mig')));
        }
        
        wp_send_json_success(array(
            'message' => __('Login succesfuld! Omdirigerer...', 'rigtig-for-mig'),
            'redirect' => home_url('/ekspert-dashboard/')
        ));
    }
    
    /**
     * Handle AJAX logout
     */
    public function handle_logout() {
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du er ikke logget ind.', 'rigtig-for-mig')));
        }
        
        // Get current user before logout
        $user = wp_get_current_user();
        $is_expert = in_array('rfm_expert_user', (array) $user->roles);
        
        // Perform logout
        wp_logout();
        
        // Send success response
        wp_send_json_success(array(
            'message' => __('Du er nu logget ud.', 'rigtig-for-mig'),
            'redirect' => $is_expert ? home_url('/login/') : home_url()
        ));
    }
    
    /**
     * Custom login redirect for experts
     */
    public function expert_login_redirect($redirect_to, $request, $user) {
        if (isset($user->roles) && in_array('rfm_expert_user', $user->roles)) {
            return home_url('/ekspert-dashboard/');
        }
        return $redirect_to;
    }
    
    /**
     * Custom logout redirect for experts
     */
    public function expert_logout_redirect($redirect_to, $requested_redirect_to, $user) {
        if (isset($user->roles) && in_array('rfm_expert_user', (array) $user->roles)) {
            return home_url('/login/');
        }
        return $redirect_to;
    }
    
    /**
     * Redirect experts away from wp-login.php to frontend login
     */
    public function redirect_experts_from_wp_login() {
        // Only redirect if user is logged in and is an expert
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_expert_user', (array) $user->roles)) {
                // If they're trying to access wp-login.php while logged in, send them to dashboard
                wp_redirect(home_url('/ekspert-dashboard/'));
                exit;
            }
        }
    }
    
    /**
     * Hide admin bar for expert users
     */
    public function hide_admin_bar_for_experts() {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_expert_user', (array) $user->roles)) {
                show_admin_bar(false);
            }
        }
    }
    
    /**
     * Filter to hide admin bar for expert users
     */
    public function hide_admin_bar_filter($show_admin_bar) {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_expert_user', (array) $user->roles)) {
                return false;
            }
        }
        return $show_admin_bar;
    }
    
    /**
     * Remove admin bar completely for expert users
     */
    public function remove_admin_bar_for_experts() {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_expert_user', (array) $user->roles)) {
                add_filter('show_admin_bar', '__return_false');
                remove_action('wp_head', '_admin_bar_bump_cb');
            }
        }
    }
    
    /**
     * Add body class for expert users
     */
    public function add_expert_body_class($classes) {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_expert_user', (array) $user->roles)) {
                $classes[] = 'rfm-expert-user';
            }
        }
        return $classes;
    }
    
    /**
     * Block admin access for expert users
     */
    public function block_admin_access_for_experts() {
        // Allow AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('rfm_expert_user', (array) $user->roles)) {
                // Redirect experts away from admin area to their dashboard
                wp_redirect(home_url('/ekspert-dashboard/'));
                exit;
            }
        }
    }
    
    /**
     * Registration form shortcode
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
        
        <script>
        jQuery(document).ready(function($) {
            $('#rfm-expert-registration-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $button = $form.find('button[type="submit"]');
                var $message = $('#rfm-registration-message');
                
                // Validate passwords match
                if ($('#reg_password').val() !== $('#reg_password_confirm').val()) {
                    $message.html('<div class="rfm-error">Adgangskoderne matcher ikke.</div>');
                    return;
                }
                
                // Disable button
                $button.prop('disabled', true).text('Opretter...');
                
                $.ajax({
                    url: rfmData.ajaxurl,
                    type: 'POST',
                    data: $form.serialize() + '&action=rfm_submit_expert_registration',
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="rfm-success">' + response.data.message + '</div>');
                            $form[0].reset();
                            
                            // Redirect after 2 seconds
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 2000);
                        } else {
                            $message.html('<div class="rfm-error">' + response.data.message + '</div>');
                            $button.prop('disabled', false).text('Opret Profil');
                        }
                    },
                    error: function() {
                        $message.html('<div class="rfm-error">Der opstod en fejl. Prøv igen.</div>');
                        $button.prop('disabled', false).text('Opret Profil');
                    }
                });
            });
        });
        </script>
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
    
    /**
     * Profile edit shortcode (frontend editor)
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
        
        <script>
        jQuery(document).ready(function($) {
            $('#rfm-profile-edit-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $button = $form.find('button[type="submit"]');
                var $message = $('#rfm-profile-edit-message');
                
                // Disable button
                $button.prop('disabled', true).text('Gemmer...');
                $message.html('');
                
                // Create FormData to handle file uploads
                var formData = new FormData(this);
                formData.append('action', 'rfm_update_expert_profile');
                
                $.ajax({
                    url: rfmData.ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="rfm-success">' + response.data.message + '</div>');
                            $button.prop('disabled', false).text('Gem Ændringer');
                            
                            // Scroll to message
                            $('html, body').animate({
                                scrollTop: $message.offset().top - 100
                            }, 500);
                            
                            // Reload page after 2 seconds to show new images
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $message.html('<div class="rfm-error">' + response.data.message + '</div>');
                            $button.prop('disabled', false).text('Gem Ændringer');
                        }
                    },
                    error: function() {
                        $message.html('<div class="rfm-error">Der opstod en fejl. Prøv igen.</div>');
                        $button.prop('disabled', false).text('Gem Ændringer');
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle profile update
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
    
    /**
     * Dashboard shortcode
     * Now redirects to the tabbed dashboard for a unified experience
     */
    public function dashboard_shortcode($atts) {
        // Use the new tabbed dashboard system
        return $this->tabbed_dashboard_shortcode($atts);
    }
    
    /**
     * Legacy dashboard shortcode (kept for reference)
     * @deprecated Use tabbed_dashboard_shortcode instead
     */
    public function legacy_dashboard_shortcode($atts) {
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
        
        // Get current data
        $name = get_the_title($expert_id);
        $email = get_post_meta($expert_id, '_rfm_email', true);
        $phone = get_post_meta($expert_id, '_rfm_phone', true);
        $website = get_post_meta($expert_id, '_rfm_website', true);
        $company_name = get_post_meta($expert_id, '_rfm_company_name', true);
        $about = get_post_meta($expert_id, '_rfm_about_me', true);
        
        // Get educations
        $educations = get_post_meta($expert_id, '_rfm_educations', true);
        if (!is_array($educations)) {
            $educations = array();
        }
        
        // Define max educations per plan
        $max_educations = array(
            'free' => 1,
            'standard' => 2,
            'premium' => 7
        );
        $current_max = $max_educations[$plan] ?? 1;
        
        // Check if fields are locked based on plan
        $is_free = ($plan === 'free');
        $is_standard_or_higher = ($plan === 'standard' || $plan === 'premium');
        
        // Get languages
        $languages = get_post_meta($expert_id, '_rfm_languages', true);
        if (!is_array($languages)) {
            $languages = array();
        }
        
        // Get language fields from flexible fields system
        $flexible_fields = RFM_Flexible_Fields_System::get_instance();
        $all_fields = $flexible_fields->get_fields();
        
        // Get language field group if it exists
        $language_fields = [];
        $language_group_required = false;
        
        if (isset($all_fields['sprog']) && isset($all_fields['sprog']['fields'])) {
            $language_fields = $all_fields['sprog']['fields'];
        } else {
            // Fallback to default languages if no custom group defined
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
        
        ob_start();
        ?>
        <div class="rfm-expert-dashboard">
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
                <a href="<?php echo home_url('/rediger-profil/'); ?>" class="rfm-btn">
                    <?php _e('Rediger profil', 'rigtig-for-mig'); ?>
                </a>
                <a href="#" id="rfm-logout-btn" class="rfm-btn">
                    <?php _e('Log ud', 'rigtig-for-mig'); ?>
                </a>
            </div>
            
            <!-- Profile Edit Form -->
            <form id="rfm-dashboard-profile-form" method="post" style="margin-top: 40px;">
                <?php wp_nonce_field('rfm_dashboard_update', 'rfm_dashboard_nonce'); ?>
                <input type="hidden" name="expert_id" value="<?php echo esc_attr($expert_id); ?>" />
                
                <!-- Basis Information -->
                <div class="rfm-form-section">
                    <h3><?php _e('Basis Information', 'rigtig-for-mig'); ?></h3>
                    
                    <div class="rfm-form-field">
                        <label for="dashboard_name"><?php _e('Dit fulde navn', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="text" name="name" id="dashboard_name" value="<?php echo esc_attr($name); ?>" required />
                    </div>
                    
                    <div class="rfm-form-field">
                        <label for="dashboard_email"><?php _e('Email', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                        <input type="email" name="email" id="dashboard_email" value="<?php echo esc_attr($email); ?>" required />
                    </div>
                    
                    <div class="rfm-form-field">
                        <label for="dashboard_phone"><?php _e('Telefon', 'rigtig-for-mig'); ?></label>
                        <input type="tel" name="phone" id="dashboard_phone" value="<?php echo esc_attr($phone); ?>" placeholder="<?php esc_attr_e('Dit telefonnummer', 'rigtig-for-mig'); ?>" />
                    </div>
                </div>
                
                <!-- Om Mig -->
                <div class="rfm-form-section">
                    <h3><?php _e('Om Mig', 'rigtig-for-mig'); ?></h3>
                    
                    <div class="rfm-form-field">
                        <label for="dashboard_about"><?php _e('Fortæl om dig selv', 'rigtig-for-mig'); ?></label>
                        <textarea name="about" id="dashboard_about" rows="6" placeholder="<?php esc_attr_e('Skriv lidt om dig selv...', 'rigtig-for-mig'); ?>"><?php echo esc_textarea($about); ?></textarea>
                    </div>
                </div>
                
                <!-- Locked Fields (Free Plan) -->
                <?php if ($is_free): ?>
                <div class="rfm-form-section">
                    <h3><?php _e('Hjemmeside', 'rigtig-for-mig'); ?></h3>
                    <div class="rfm-field-locked-message">
                        🔒 <?php _e('Dette felt kræver', 'rigtig-for-mig'); ?> <strong>Standard (219 DKK/md)</strong> <?php _e('medlemskab', 'rigtig-for-mig'); ?>
                        <br>
                        <button type="button" class="rfm-btn" style="margin-top: 10px; background: #FFA500; color: white;">
                            <?php _e('Opgrader til Standard', 'rigtig-for-mig'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="rfm-form-section">
                    <h3><?php _e('Firma navn', 'rigtig-for-mig'); ?></h3>
                    <div class="rfm-field-locked-message">
                        🔒 <?php _e('Dette felt kræver', 'rigtig-for-mig'); ?> <strong>Standard (219 DKK/md)</strong> <?php _e('medlemskab', 'rigtig-for-mig'); ?>
                        <br>
                        <button type="button" class="rfm-btn" style="margin-top: 10px; background: #FFA500; color: white;">
                            <?php _e('Opgrader til Standard', 'rigtig-for-mig'); ?>
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <!-- Unlocked Fields (Standard/Premium) -->
                <div class="rfm-form-section">
                    <h3><?php _e('Hjemmeside', 'rigtig-for-mig'); ?></h3>
                    
                    <div class="rfm-form-field">
                        <label for="dashboard_website"><?php _e('Hjemmeside', 'rigtig-for-mig'); ?></label>
                        <input type="url" name="website" id="dashboard_website" value="<?php echo esc_attr($website); ?>" placeholder="https://www.example.com" />
                    </div>
                </div>
                
                <div class="rfm-form-section">
                    <h3><?php _e('Firma navn', 'rigtig-for-mig'); ?></h3>
                    
                    <div class="rfm-form-field">
                        <label for="dashboard_company"><?php _e('Firma navn', 'rigtig-for-mig'); ?></label>
                        <input type="text" name="company_name" id="dashboard_company" value="<?php echo esc_attr($company_name); ?>" placeholder="<?php esc_attr_e('Dit firma', 'rigtig-for-mig'); ?>" />
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Uddannelser -->
                <div class="rfm-form-section rfm-education-section">
                    <h3><?php _e('Uddannelser', 'rigtig-for-mig'); ?></h3>
                    <p class="rfm-section-description">
                        <?php 
                        if ($plan === 'free') {
                            printf(__('Du kan tilføje op til %d uddannelse. Opgrader for at tilføje flere.', 'rigtig-for-mig'), $current_max);
                        } elseif ($plan === 'standard') {
                            printf(__('Du kan tilføje op til %d uddannelser. Opgrader til Premium for at tilføje flere.', 'rigtig-for-mig'), $current_max);
                        } else {
                            printf(__('Du kan tilføje op til %d uddannelser.', 'rigtig-for-mig'), $current_max);
                        }
                        ?>
                    </p>
                    
                    <div id="rfm-educations-container" 
                         data-max="<?php echo esc_attr($current_max); ?>"
                         data-plan="<?php echo esc_attr($plan); ?>">
                        
                        <?php if (!empty($educations)): ?>
                            <?php foreach ($educations as $index => $education): ?>
                                <div class="rfm-education-item" data-index="<?php echo $index; ?>">
                                    <button type="button" class="rfm-education-remove" title="<?php esc_attr_e('Fjern uddannelse', 'rigtig-for-mig'); ?>">✕</button>
                                    
                                    <div class="rfm-form-field">
                                        <label><?php _e('Uddannelsesnavn', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                                        <input type="text" 
                                               name="educations[<?php echo $index; ?>][name]" 
                                               value="<?php echo esc_attr($education['name'] ?? ''); ?>" 
                                               placeholder="<?php esc_attr_e('F.eks. Psykologuddannelse', 'rigtig-for-mig'); ?>"
                                               required />
                                    </div>
                                    
                                    <div class="rfm-form-field">
                                        <label><?php _e('Institution', 'rigtig-for-mig'); ?></label>
                                        <input type="text" 
                                               name="educations[<?php echo $index; ?>][institution]" 
                                               value="<?php echo esc_attr($education['institution'] ?? ''); ?>" 
                                               placeholder="<?php esc_attr_e('F.eks. Københavns Universitet', 'rigtig-for-mig'); ?>" />
                                    </div>
                                    
                                    <div class="rfm-form-row">
                                        <div class="rfm-form-field rfm-form-field-half">
                                            <label><?php _e('År (start)', 'rigtig-for-mig'); ?></label>
                                            <input type="text" 
                                                   name="educations[<?php echo $index; ?>][year_start]" 
                                                   value="<?php echo esc_attr($education['year_start'] ?? ''); ?>" 
                                                   placeholder="<?php esc_attr_e('2018', 'rigtig-for-mig'); ?>" />
                                        </div>
                                        <div class="rfm-form-field rfm-form-field-half">
                                            <label><?php _e('År (slut)', 'rigtig-for-mig'); ?></label>
                                            <input type="text" 
                                                   name="educations[<?php echo $index; ?>][year_end]" 
                                                   value="<?php echo esc_attr($education['year_end'] ?? ''); ?>" 
                                                   placeholder="<?php esc_attr_e('2022', 'rigtig-for-mig'); ?>" />
                                        </div>
                                    </div>
                                    
                                    <div class="rfm-form-field">
                                        <label><?php _e('År startet i praksis', 'rigtig-for-mig'); ?></label>
                                        <input type="number" 
                                               name="educations[<?php echo $index; ?>][experience_start_year]" 
                                               value="<?php echo esc_attr($education['experience_start_year'] ?? ''); ?>" 
                                               placeholder="<?php echo esc_attr(date('Y')); ?>"
                                               min="1950"
                                               max="<?php echo esc_attr(date('Y')); ?>" />
                                        <p class="rfm-field-hint"><?php _e('Hvornår begyndte du at arbejde med denne uddannelse? Bruges til at vise års erfaring.', 'rigtig-for-mig'); ?></p>
                                    </div>
                                    
                                    <div class="rfm-form-field">
                                        <label><?php _e('Beskrivelse', 'rigtig-for-mig'); ?></label>
                                        <textarea name="educations[<?php echo $index; ?>][description]" 
                                                  rows="3" 
                                                  placeholder="<?php esc_attr_e('Beskriv hvad du lærte...', 'rigtig-for-mig'); ?>"><?php echo esc_textarea($education['description'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <?php if ($is_standard_or_higher): ?>
                                    <!-- Billede upload kun for betalende medlemmer -->
                                    <div class="rfm-form-field rfm-education-image">
                                        <label><?php _e('Diplom/Certifikat billede', 'rigtig-for-mig'); ?></label>
                                        
                                        <?php 
                                        $image_id = $education['image_id'] ?? '';
                                        $has_image = !empty($image_id);
                                        ?>
                                        
                                        <div class="rfm-image-upload-wrapper">
                                            <input type="hidden" 
                                                   name="educations[<?php echo $index; ?>][image_id]" 
                                                   value="<?php echo esc_attr($image_id); ?>"
                                                   class="rfm-education-image-id" />
                                            
                                            <div class="rfm-image-preview <?php echo $has_image ? 'has-image' : ''; ?>">
                                                <?php if ($has_image): ?>
                                                    <?php echo wp_get_attachment_image($image_id, 'medium'); ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="rfm-image-buttons">
                                                <button type="button" class="rfm-btn rfm-btn-small rfm-upload-education-image">
                                                    <?php echo $has_image ? __('Skift billede', 'rigtig-for-mig') : __('Upload billede', 'rigtig-for-mig'); ?>
                                                </button>
                                                <?php if ($has_image): ?>
                                                <button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger rfm-remove-education-image">
                                                    <?php _e('Fjern', 'rigtig-for-mig'); ?>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <input type="file" 
                                                   class="rfm-education-image-input" 
                                                   accept="image/jpeg,image/png,image/gif,image/webp"
                                                   style="display: none;" />
                                        </div>
                                        <p class="rfm-field-hint"><?php _e('Maks 5MB. Typer: JPG, PNG, GIF, WebP', 'rigtig-for-mig'); ?></p>
                                    </div>
                                    <?php else: ?>
                                    <div class="rfm-form-field rfm-locked-feature">
                                        <label><?php _e('Diplom/Certifikat billede', 'rigtig-for-mig'); ?></label>
                                        <div class="rfm-upgrade-notice">
                                            <span class="dashicons dashicons-lock"></span>
                                            <?php _e('Opgrader til Standard eller Premium for at uploade billeder af dine certifikater.', 'rigtig-for-mig'); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Template for new education (hidden) -->
                    <template id="rfm-education-template">
                        <div class="rfm-education-item" data-index="__INDEX__">
                            <button type="button" class="rfm-education-remove" title="<?php esc_attr_e('Fjern uddannelse', 'rigtig-for-mig'); ?>">✕</button>
                            
                            <div class="rfm-form-field">
                                <label><?php _e('Uddannelsesnavn', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                                <input type="text" 
                                       name="educations[__INDEX__][name]" 
                                       placeholder="<?php esc_attr_e('F.eks. Psykologuddannelse', 'rigtig-for-mig'); ?>"
                                       required />
                            </div>
                            
                            <div class="rfm-form-field">
                                <label><?php _e('Institution', 'rigtig-for-mig'); ?></label>
                                <input type="text" 
                                       name="educations[__INDEX__][institution]" 
                                       placeholder="<?php esc_attr_e('F.eks. Københavns Universitet', 'rigtig-for-mig'); ?>" />
                            </div>
                            
                            <div class="rfm-form-row">
                                <div class="rfm-form-field rfm-form-field-half">
                                    <label><?php _e('År (start)', 'rigtig-for-mig'); ?></label>
                                    <input type="text" 
                                           name="educations[__INDEX__][year_start]" 
                                           placeholder="<?php esc_attr_e('2018', 'rigtig-for-mig'); ?>" />
                                </div>
                                <div class="rfm-form-field rfm-form-field-half">
                                    <label><?php _e('År (slut)', 'rigtig-for-mig'); ?></label>
                                    <input type="text" 
                                           name="educations[__INDEX__][year_end]" 
                                           placeholder="<?php esc_attr_e('2022', 'rigtig-for-mig'); ?>" />
                                </div>
                            </div>
                            
                            <div class="rfm-form-field">
                                <label><?php _e('År startet i praksis', 'rigtig-for-mig'); ?></label>
                                <input type="number" 
                                       name="educations[__INDEX__][experience_start_year]" 
                                       placeholder="<?php echo esc_attr(date('Y')); ?>"
                                       min="1950"
                                       max="<?php echo esc_attr(date('Y')); ?>" />
                                <p class="rfm-field-hint"><?php _e('Hvornår begyndte du at arbejde med denne uddannelse? Bruges til at vise års erfaring.', 'rigtig-for-mig'); ?></p>
                            </div>
                            
                            <div class="rfm-form-field">
                                <label><?php _e('Beskrivelse', 'rigtig-for-mig'); ?></label>
                                <textarea name="educations[__INDEX__][description]" 
                                          rows="3" 
                                          placeholder="<?php esc_attr_e('Beskriv hvad du lærte...', 'rigtig-for-mig'); ?>"></textarea>
                            </div>
                            
                            <?php if ($is_standard_or_higher): ?>
                            <div class="rfm-form-field rfm-education-image">
                                <label><?php _e('Diplom/Certifikat billede', 'rigtig-for-mig'); ?></label>
                                
                                <div class="rfm-image-upload-wrapper">
                                    <input type="hidden" 
                                           name="educations[__INDEX__][image_id]" 
                                           value=""
                                           class="rfm-education-image-id" />
                                    
                                    <div class="rfm-image-preview"></div>
                                    
                                    <div class="rfm-image-buttons">
                                        <button type="button" class="rfm-btn rfm-btn-small rfm-upload-education-image">
                                            <?php _e('Upload billede', 'rigtig-for-mig'); ?>
                                        </button>
                                    </div>
                                    
                                    <input type="file" 
                                           class="rfm-education-image-input" 
                                           accept="image/jpeg,image/png,image/gif,image/webp"
                                           style="display: none;" />
                                </div>
                                <p class="rfm-field-hint"><?php _e('Maks 5MB. Typer: JPG, PNG, GIF, WebP', 'rigtig-for-mig'); ?></p>
                            </div>
                            <?php else: ?>
                            <div class="rfm-form-field rfm-locked-feature">
                                <label><?php _e('Diplom/Certifikat billede', 'rigtig-for-mig'); ?></label>
                                <div class="rfm-upgrade-notice">
                                    <span class="dashicons dashicons-lock"></span>
                                    <?php _e('Opgrader til Standard eller Premium for at uploade billeder.', 'rigtig-for-mig'); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </template>
                    
                    <div class="rfm-add-education-wrapper">
                        <button type="button" id="rfm-add-education" class="rfm-btn rfm-btn-secondary">
                            <?php _e('+ Tilføj uddannelse', 'rigtig-for-mig'); ?>
                        </button>
                        <span id="rfm-education-limit-notice" class="rfm-limit-notice" style="display: none;">
                            <?php printf(__('Maksimum %d uddannelser for dit medlemsniveau.', 'rigtig-for-mig'), $current_max); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Sprog -->
                <div class="rfm-form-section">
                    <h3><?php _e('Sprog', 'rigtig-for-mig'); ?></h3>
                    
                    <?php foreach ($language_fields as $lang_code => $lang_data): 
                        $lang_label = is_array($lang_data) ? ($lang_data['label'] ?? ucfirst($lang_code)) : $lang_data;
                        $is_required = is_array($lang_data) ? ($lang_data['required'] ?? false) : false;
                    ?>
                    <div class="rfm-form-field">
                        <label>
                            <input type="checkbox" 
                                   name="languages[]" 
                                   value="<?php echo esc_attr($lang_code); ?>" 
                                   <?php checked(in_array($lang_code, $languages)); ?> />
                            <?php echo esc_html($lang_label); ?>
                            <?php if ($is_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Submit -->
                <div id="rfm-dashboard-message"></div>
                
                <p class="rfm-form-submit" style="text-align: center;">
                    <button type="submit" class="rfm-btn" style="background: #FFC107; color: #333; font-weight: bold; padding: 15px 40px; font-size: 16px;">
                        <?php _e('Gem ændringer', 'rigtig-for-mig'); ?>
                    </button>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle form submission
            $('#rfm-dashboard-profile-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $button = $form.find('button[type="submit"]');
                var $message = $('#rfm-dashboard-message');
                
                // Disable button
                $button.prop('disabled', true).text('Gemmer...');
                $message.html('');
                
                $.ajax({
                    url: rfmData.ajaxurl,
                    type: 'POST',
                    data: $form.serialize() + '&action=rfm_update_dashboard_profile',
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="rfm-success">' + response.data.message + '</div>');
                            $button.prop('disabled', false).text('Gem ændringer');
                            
                            // Scroll to message
                            $('html, body').animate({
                                scrollTop: $message.offset().top - 100
                            }, 500);
                        } else {
                            $message.html('<div class="rfm-error">' + response.data.message + '</div>');
                            $button.prop('disabled', false).text('Gem ændringer');
                        }
                    },
                    error: function() {
                        $message.html('<div class="rfm-error">Der opstod en fejl. Prøv igen.</div>');
                        $button.prop('disabled', false).text('Gem ændringer');
                    }
                });
            });
            
            // Handle logout button
            $('#rfm-logout-btn').on('click', function(e) {
                e.preventDefault();
                
                if (confirm('Er du sikker på at du vil logge ud?')) {
                    $.ajax({
                        url: rfmData.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rfm_expert_logout'
                        },
                        success: function(response) {
                            if (response.success) {
                                window.location.href = response.data.redirect;
                            }
                        }
                    });
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle dashboard profile update
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
     * Handle education image upload via AJAX
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
     * - "Generelt" tab contains name, contact, languages, and category selection
     * - One tab per selected category with category-specific profile data
     * 
     * Usage: [rfm_expert_dashboard_tabbed]
     */
    public function tabbed_dashboard_shortcode($atts) {
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
        
        // Get expert's current categories
        $expert_categories = wp_get_object_terms($expert_id, 'rfm_category', array('fields' => 'all'));
        if (is_wp_error($expert_categories)) {
            $expert_categories = array();
        }
        
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
                                foreach ($all_categories as $category): 
                                    $color = RFM_Taxonomies::get_category_color($category->term_id);
                                ?>
                                <label class="rfm-category-choice" style="--cat-color: <?php echo esc_attr($color); ?>;">
                                    <input type="checkbox" 
                                           name="categories[]" 
                                           value="<?php echo esc_attr($category->term_id); ?>"
                                           class="rfm-category-checkbox"
                                           <?php checked(in_array($category->term_id, $expert_cat_ids)); ?> />
                                    <span><?php echo esc_html($category->name); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            
                            <p class="rfm-category-limit-notice" id="rfm-category-limit-notice" style="display: none; color: #e74c3c;">
                                <?php printf(__('Du har valgt det maksimale antal kategorier (%d).', 'rigtig-for-mig'), $allowed_categories); ?>
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
                
                <!-- Education Template for Category Profiles -->
                <template id="rfm-category-education-template">
                    <?php $this->render_category_education_item(array(), '__INDEX__', $is_standard_or_higher, '__CATEGORY_ID__'); ?>
                </template>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.rfm-tab-btn').on('click', function() {
                var tab = $(this).data('tab');
                
                // Update active tab button
                $('.rfm-tab-btn').removeClass('active');
                $(this).addClass('active');
                
                // Update active tab content
                $('.rfm-tab-content').removeClass('active');
                $('[data-tab-content="' + tab + '"]').addClass('active');
                
                // Scroll to top of tabs
                $('html, body').animate({
                    scrollTop: $('.rfm-dashboard-tabs').offset().top - 50
                }, 300);
            });
            
            // General profile form submission
            $('#rfm-general-profile-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $button = $form.find('button[type="submit"]');
                var $message = $('#rfm-tabbed-dashboard-message');
                
                $button.prop('disabled', true).text('<?php _e('Gemmer...', 'rigtig-for-mig'); ?>');
                $message.html('');
                
                $.ajax({
                    url: rfmData.ajaxurl,
                    type: 'POST',
                    data: $form.serialize() + '&action=rfm_save_general_profile&nonce=' + $form.find('[name="rfm_tabbed_nonce"]').val(),
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="rfm-success">' + response.data.message + '</div>');
                            // Reload page to update category tabs
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            $message.html('<div class="rfm-error">' + response.data.message + '</div>');
                            $button.prop('disabled', false).text('<?php _e('Gem generelle oplysninger', 'rigtig-for-mig'); ?>');
                        }
                    },
                    error: function() {
                        $message.html('<div class="rfm-error"><?php _e('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig'); ?></div>');
                        $button.prop('disabled', false).text('<?php _e('Gem generelle oplysninger', 'rigtig-for-mig'); ?>');
                    }
                });
            });
            
            // Category profile form submissions
            $('.rfm-category-profile-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $button = $form.find('button[type="submit"]');
                var originalText = $button.text();
                var $message = $('#rfm-tabbed-dashboard-message');
                
                $button.prop('disabled', true).text('<?php _e('Gemmer...', 'rigtig-for-mig'); ?>');
                $message.html('');
                
                $.ajax({
                    url: rfmData.ajaxurl,
                    type: 'POST',
                    data: $form.serialize() + '&action=rfm_save_category_profile&nonce=' + $form.find('[name="rfm_tabbed_nonce"]').val(),
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="rfm-success">' + response.data.message + '</div>');
                            $('html, body').animate({
                                scrollTop: $message.offset().top - 100
                            }, 300);
                        } else {
                            $message.html('<div class="rfm-error">' + response.data.message + '</div>');
                        }
                        $button.prop('disabled', false).text(originalText);
                    },
                    error: function() {
                        $message.html('<div class="rfm-error"><?php _e('Der opstod en fejl. Prøv igen.', 'rigtig-for-mig'); ?></div>');
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            // Category checkbox limit
            var $catCheckboxes = $('#rfm-tabbed-categories');
            var maxCats = parseInt($catCheckboxes.data('max')) || 1;
            
            function updateCategoryLimit() {
                var $checkboxes = $catCheckboxes.find('.rfm-category-checkbox');
                var checkedCount = $checkboxes.filter(':checked').length;
                
                if (checkedCount >= maxCats) {
                    $checkboxes.not(':checked').prop('disabled', true);
                    $('#rfm-category-limit-notice').show();
                } else {
                    $checkboxes.prop('disabled', false);
                    $('#rfm-category-limit-notice').hide();
                }
            }
            
            $catCheckboxes.on('change', '.rfm-category-checkbox', updateCategoryLimit);
            updateCategoryLimit();
            
            // Specialization limits per category
            $('.rfm-specialization-checkboxes').each(function() {
                var $container = $(this);
                var maxSpecs = parseInt($container.data('max')) || 1;
                
                function updateSpecLimit() {
                    var $checkboxes = $container.find('.rfm-spec-checkbox');
                    var checkedCount = $checkboxes.filter(':checked').length;
                    
                    if (checkedCount >= maxSpecs) {
                        $checkboxes.not(':checked').prop('disabled', true);
                        $container.siblings('.rfm-spec-limit-notice').show();
                    } else {
                        $checkboxes.prop('disabled', false);
                        $container.siblings('.rfm-spec-limit-notice').hide();
                    }
                }
                
                $container.on('change', '.rfm-spec-checkbox', updateSpecLimit);
                updateSpecLimit();
            });
            
            // Add education for category
            $('.rfm-add-category-education').on('click', function() {
                var categoryId = $(this).data('category-id');
                var $container = $(this).closest('.rfm-category-education-section').find('.rfm-category-educations-container');
                var maxEducations = parseInt($container.data('max')) || 1;
                var currentCount = $container.find('.rfm-category-education-item').length;
                
                if (currentCount >= maxEducations) {
                    $(this).siblings('.rfm-cat-education-limit-notice').show();
                    return;
                }
                
                var template = $('#rfm-category-education-template').html();
                var newIndex = Date.now();
                
                template = template.replace(/__INDEX__/g, newIndex);
                template = template.replace(/__CATEGORY_ID__/g, categoryId);
                
                $container.append(template);
                
                // Check limit again
                if ($container.find('.rfm-category-education-item').length >= maxEducations) {
                    $(this).siblings('.rfm-cat-education-limit-notice').show();
                }
            });
            
            // Remove education
            $(document).on('click', '.rfm-category-education-remove', function() {
                var $item = $(this).closest('.rfm-category-education-item');
                var $container = $item.closest('.rfm-category-educations-container');
                
                $item.slideUp(300, function() {
                    $(this).remove();
                    // Hide limit notice
                    $container.closest('.rfm-category-education-section').find('.rfm-cat-education-limit-notice').hide();
                });
            });
            
            // Handle logout
            $('#rfm-logout-btn').on('click', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: rfmData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rfm_expert_logout',
                        nonce: '<?php echo wp_create_nonce('rfm_logout'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect;
                        }
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render a single education item for category profiles
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
