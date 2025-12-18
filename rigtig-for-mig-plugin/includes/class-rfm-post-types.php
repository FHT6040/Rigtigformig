<?php
/**
 * Custom Post Types
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Post_Types {
    
    /**
     * Register custom post types
     */
    public static function register() {
        self::register_expert_post_type();
        self::register_user_post_type();

        // Enable Elementor for expert post type
        add_action('init', array(__CLASS__, 'enable_elementor_support'), 20);
    }
    
    /**
     * Enable Elementor support for expert post type
     */
    public static function enable_elementor_support() {
        // Check if Elementor is active
        if (did_action('elementor/loaded')) {
            // Add expert post type to Elementor support
            add_post_type_support('rfm_expert', 'elementor');
        }
    }
    
    /**
     * Register Expert post type
     */
    private static function register_expert_post_type() {
        $labels = array(
            'name'                  => _x('Eksperter', 'Post type general name', 'rigtig-for-mig'),
            'singular_name'         => _x('Ekspert', 'Post type singular name', 'rigtig-for-mig'),
            'menu_name'             => _x('Eksperter', 'Admin Menu text', 'rigtig-for-mig'),
            'name_admin_bar'        => _x('Ekspert', 'Add New on Toolbar', 'rigtig-for-mig'),
            'add_new'               => __('Tilføj Ny', 'rigtig-for-mig'),
            'add_new_item'          => __('Tilføj Ny Ekspert', 'rigtig-for-mig'),
            'new_item'              => __('Ny Ekspert', 'rigtig-for-mig'),
            'edit_item'             => __('Rediger Ekspert', 'rigtig-for-mig'),
            'view_item'             => __('Vis Ekspert', 'rigtig-for-mig'),
            'all_items'             => __('Alle Eksperter', 'rigtig-for-mig'),
            'search_items'          => __('Søg Eksperter', 'rigtig-for-mig'),
            'parent_item_colon'     => __('Forældre Eksperter:', 'rigtig-for-mig'),
            'not_found'             => __('Ingen eksperter fundet.', 'rigtig-for-mig'),
            'not_found_in_trash'    => __('Ingen eksperter fundet i papirkurv.', 'rigtig-for-mig'),
            'featured_image'        => _x('Profilbillede', 'Overrides the "Featured Image" phrase', 'rigtig-for-mig'),
            'set_featured_image'    => _x('Sæt profilbillede', 'Overrides the "Set featured image" phrase', 'rigtig-for-mig'),
            'remove_featured_image' => _x('Fjern profilbillede', 'Overrides the "Remove featured image" phrase', 'rigtig-for-mig'),
            'use_featured_image'    => _x('Brug som profilbillede', 'Overrides the "Use as featured image" phrase', 'rigtig-for-mig'),
            'archives'              => _x('Ekspert arkiver', 'The post type archive label', 'rigtig-for-mig'),
            'insert_into_item'      => _x('Indsæt i ekspert', 'Overrides the "Insert into post" phrase', 'rigtig-for-mig'),
            'uploaded_to_this_item' => _x('Uploadet til denne ekspert', 'Overrides the "Uploaded to this post" phrase', 'rigtig-for-mig'),
            'filter_items_list'     => _x('Filtrer ekspert liste', 'Screen reader text for the filter links', 'rigtig-for-mig'),
            'items_list_navigation' => _x('Ekspert liste navigation', 'Screen reader text for the pagination', 'rigtig-for-mig'),
            'items_list'            => _x('Ekspert liste', 'Screen reader text for the items list', 'rigtig-for-mig'),
        );
        
        $args = array(
            'labels'             => $labels,
            'description'        => __('Ekspert profiler for Rigtig for mig markedsplads', 'rigtig-for-mig'),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'ekspert'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-admin-users',
            'supports'           => array('title', 'editor', 'thumbnail', 'excerpt', 'author', 'elementor'),
            'show_in_rest'       => true, // Required for Elementor
            'taxonomies'         => array('rfm_category', 'rfm_specialization'),
        );
        
        register_post_type('rfm_expert', $args);
        
        // Add custom meta boxes
        add_action('add_meta_boxes', array(__CLASS__, 'add_expert_meta_boxes'));
        add_action('save_post_rfm_expert', array(__CLASS__, 'save_expert_meta'), 10, 2);
    }
    
    /**
     * Add custom meta boxes for expert profiles
     */
    public static function add_expert_meta_boxes() {
        // Profile Information
        add_meta_box(
            'rfm_profile_info',
            __('Profil Information', 'rigtig-for-mig'),
            array(__CLASS__, 'render_profile_info_meta_box'),
            'rfm_expert',
            'normal',
            'high'
        );
        
        // About Me
        add_meta_box(
            'rfm_about_me',
            __('Om Mig', 'rigtig-for-mig'),
            array(__CLASS__, 'render_about_me_meta_box'),
            'rfm_expert',
            'normal',
            'high'
        );
        
        // Education & Experience
        add_meta_box(
            'rfm_education',
            __('Uddannelse & Erfaring', 'rigtig-for-mig'),
            array(__CLASS__, 'render_education_meta_box'),
            'rfm_expert',
            'normal',
            'high'
        );
        
        // Subscription & Status
        add_meta_box(
            'rfm_subscription',
            __('Abonnement & Status', 'rigtig-for-mig'),
            array(__CLASS__, 'render_subscription_meta_box'),
            'rfm_expert',
            'side',
            'default'
        );
        
        // Banner Image
        add_meta_box(
            'rfm_banner_image',
            __('Banner Billede', 'rigtig-for-mig'),
            array(__CLASS__, 'render_banner_image_meta_box'),
            'rfm_expert',
            'side',
            'default'
        );
    }
    
    /**
     * Render about me meta box
     */
    public static function render_about_me_meta_box($post) {
        $about_me = get_post_meta($post->ID, '_rfm_about_me', true);
        
        ?>
        <p><em><?php _e('Fortæl lidt om dig selv, din baggrund og din tilgang til dit arbejde.', 'rigtig-for-mig'); ?></em></p>
        <textarea id="rfm_about_me" name="rfm_about_me" rows="10" style="width: 100%;"><?php echo esc_textarea($about_me); ?></textarea>
        <?php
    }
    
    /**
     * Render profile information meta box
     */
    public static function render_profile_info_meta_box($post) {
        wp_nonce_field('rfm_expert_meta_nonce', 'rfm_expert_meta_nonce');
        
        $phone = get_post_meta($post->ID, '_rfm_phone', true);
        $email = get_post_meta($post->ID, '_rfm_email', true);
        $website = get_post_meta($post->ID, '_rfm_website', true);
        $address = get_post_meta($post->ID, '_rfm_address', true);
        $city = get_post_meta($post->ID, '_rfm_city', true);
        $postal_code = get_post_meta($post->ID, '_rfm_postal_code', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="rfm_email"><?php _e('Email', 'rigtig-for-mig'); ?></label></th>
                <td>
                    <input type="email" id="rfm_email" name="rfm_email" value="<?php echo esc_attr($email); ?>" class="regular-text" required />
                    <?php if (get_post_meta($post->ID, '_rfm_email_verified', true)): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        <span style="color: green;"><?php _e('Verificeret', 'rigtig-for-mig'); ?></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning" style="color: orange;"></span>
                        <span style="color: orange;"><?php _e('Ikke verificeret', 'rigtig-for-mig'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="rfm_phone"><?php _e('Telefon', 'rigtig-for-mig'); ?></label></th>
                <td><input type="tel" id="rfm_phone" name="rfm_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="rfm_website"><?php _e('Hjemmeside', 'rigtig-for-mig'); ?></label></th>
                <td><input type="url" id="rfm_website" name="rfm_website" value="<?php echo esc_attr($website); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="rfm_address"><?php _e('Adresse', 'rigtig-for-mig'); ?></label></th>
                <td><input type="text" id="rfm_address" name="rfm_address" value="<?php echo esc_attr($address); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="rfm_city"><?php _e('By', 'rigtig-for-mig'); ?></label></th>
                <td><input type="text" id="rfm_city" name="rfm_city" value="<?php echo esc_attr($city); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="rfm_postal_code"><?php _e('Postnummer', 'rigtig-for-mig'); ?></label></th>
                <td><input type="text" id="rfm_postal_code" name="rfm_postal_code" value="<?php echo esc_attr($postal_code); ?>" class="regular-text" /></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render education meta box
     */
    public static function render_education_meta_box($post) {
        $educations = get_post_meta($post->ID, '_rfm_educations', true);
        $years_experience = get_post_meta($post->ID, '_rfm_years_experience', true);
        
        if (!is_array($educations)) {
            $educations = array(array('title' => '', 'institution' => '', 'year' => ''));
        }
        ?>
        <div id="rfm-educations-wrapper">
            <table class="form-table">
                <tr>
                    <th><label for="rfm_years_experience"><?php _e('År i branchen', 'rigtig-for-mig'); ?></label></th>
                    <td><input type="number" id="rfm_years_experience" name="rfm_years_experience" value="<?php echo esc_attr($years_experience); ?>" min="0" /></td>
                </tr>
            </table>
            
            <h4><?php _e('Uddannelser', 'rigtig-for-mig'); ?></h4>
            <div id="rfm-educations-list">
                <?php foreach ($educations as $index => $education): ?>
                    <div class="rfm-education-item" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; background: #f9f9f9;">
                        <p>
                            <label><?php _e('Uddannelsesnavn', 'rigtig-for-mig'); ?></label><br>
                            <input type="text" name="rfm_educations[<?php echo $index; ?>][title]" value="<?php echo esc_attr($education['title']); ?>" class="regular-text" />
                        </p>
                        <p>
                            <label><?php _e('Institution', 'rigtig-for-mig'); ?></label><br>
                            <input type="text" name="rfm_educations[<?php echo $index; ?>][institution]" value="<?php echo esc_attr($education['institution']); ?>" class="regular-text" />
                        </p>
                        <p>
                            <label><?php _e('År', 'rigtig-for-mig'); ?></label><br>
                            <input type="text" name="rfm_educations[<?php echo $index; ?>][year]" value="<?php echo esc_attr($education['year']); ?>" />
                        </p>
                        <button type="button" class="button rfm-remove-education"><?php _e('Fjern', 'rigtig-for-mig'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="rfm-add-education"><?php _e('Tilføj Uddannelse', 'rigtig-for-mig'); ?></button>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var educationIndex = <?php echo count($educations); ?>;
            
            $('#rfm-add-education').on('click', function() {
                var html = '<div class="rfm-education-item" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; background: #f9f9f9;">' +
                    '<p><label><?php _e('Uddannelsesnavn', 'rigtig-for-mig'); ?></label><br>' +
                    '<input type="text" name="rfm_educations[' + educationIndex + '][title]" class="regular-text" /></p>' +
                    '<p><label><?php _e('Institution', 'rigtig-for-mig'); ?></label><br>' +
                    '<input type="text" name="rfm_educations[' + educationIndex + '][institution]" class="regular-text" /></p>' +
                    '<p><label><?php _e('År', 'rigtig-for-mig'); ?></label><br>' +
                    '<input type="text" name="rfm_educations[' + educationIndex + '][year]" /></p>' +
                    '<button type="button" class="button rfm-remove-education"><?php _e('Fjern', 'rigtig-for-mig'); ?></button>' +
                    '</div>';
                $('#rfm-educations-list').append(html);
                educationIndex++;
            });
            
            $(document).on('click', '.rfm-remove-education', function() {
                $(this).closest('.rfm-education-item').remove();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render subscription meta box
     */
    public static function render_subscription_meta_box($post) {
        $subscription_plan = get_post_meta($post->ID, '_rfm_subscription_plan', true);
        $subscription_status = get_post_meta($post->ID, '_rfm_subscription_status', true);
        $subscription_end = get_post_meta($post->ID, '_rfm_subscription_end', true);
        
        ?>
        <p>
            <label for="rfm_subscription_plan"><strong><?php _e('Abonnementsplan', 'rigtig-for-mig'); ?></strong></label><br>
            <select id="rfm_subscription_plan" name="rfm_subscription_plan" style="width: 100%;">
                <option value="free" <?php selected($subscription_plan, 'free'); ?>><?php _e('Gratis', 'rigtig-for-mig'); ?></option>
                <option value="standard" <?php selected($subscription_plan, 'standard'); ?>><?php _e('Standard (219 kr/mdr)', 'rigtig-for-mig'); ?></option>
                <option value="premium" <?php selected($subscription_plan, 'premium'); ?>><?php _e('Premium (399 kr/mdr)', 'rigtig-for-mig'); ?></option>
            </select>
        </p>
        
        <p>
            <label for="rfm_subscription_status"><strong><?php _e('Status', 'rigtig-for-mig'); ?></strong></label><br>
            <select id="rfm_subscription_status" name="rfm_subscription_status" style="width: 100%;">
                <option value="active" <?php selected($subscription_status, 'active'); ?>><?php _e('Aktiv', 'rigtig-for-mig'); ?></option>
                <option value="pending" <?php selected($subscription_status, 'pending'); ?>><?php _e('Afventer', 'rigtig-for-mig'); ?></option>
                <option value="expired" <?php selected($subscription_status, 'expired'); ?>><?php _e('Udløbet', 'rigtig-for-mig'); ?></option>
                <option value="cancelled" <?php selected($subscription_status, 'cancelled'); ?>><?php _e('Annulleret', 'rigtig-for-mig'); ?></option>
            </select>
        </p>
        
        <p>
            <label for="rfm_subscription_end"><strong><?php _e('Abonnement udløber', 'rigtig-for-mig'); ?></strong></label><br>
            <input type="date" id="rfm_subscription_end" name="rfm_subscription_end" value="<?php echo esc_attr($subscription_end); ?>" style="width: 100%;" />
        </p>
        <?php
    }
    
    /**
     * Render banner image meta box
     */
    public static function render_banner_image_meta_box($post) {
        $banner_image_id = get_post_meta($post->ID, '_rfm_banner_image_id', true);
        $banner_image_url = $banner_image_id ? wp_get_attachment_url($banner_image_id) : '';
        ?>
        <div class="rfm-banner-image-wrapper">
            <input type="hidden" id="rfm_banner_image_id" name="rfm_banner_image_id" value="<?php echo esc_attr($banner_image_id); ?>" />
            <div id="rfm-banner-preview" style="margin-bottom: 10px;">
                <?php if ($banner_image_url): ?>
                    <img src="<?php echo esc_url($banner_image_url); ?>" style="max-width: 100%; height: auto;" />
                <?php endif; ?>
            </div>
            <button type="button" class="button" id="rfm-upload-banner"><?php _e('Upload Banner', 'rigtig-for-mig'); ?></button>
            <button type="button" class="button" id="rfm-remove-banner" <?php echo !$banner_image_url ? 'style="display:none;"' : ''; ?>><?php _e('Fjern Banner', 'rigtig-for-mig'); ?></button>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var bannerFrame;
            
            $('#rfm-upload-banner').on('click', function(e) {
                e.preventDefault();
                
                if (bannerFrame) {
                    bannerFrame.open();
                    return;
                }
                
                bannerFrame = wp.media({
                    title: '<?php _e('Vælg Banner Billede', 'rigtig-for-mig'); ?>',
                    button: {
                        text: '<?php _e('Brug dette billede', 'rigtig-for-mig'); ?>'
                    },
                    multiple: false
                });
                
                bannerFrame.on('select', function() {
                    var attachment = bannerFrame.state().get('selection').first().toJSON();
                    $('#rfm_banner_image_id').val(attachment.id);
                    $('#rfm-banner-preview').html('<img src="' + attachment.url + '" style="max-width: 100%; height: auto;" />');
                    $('#rfm-remove-banner').show();
                });
                
                bannerFrame.open();
            });
            
            $('#rfm-remove-banner').on('click', function(e) {
                e.preventDefault();
                $('#rfm_banner_image_id').val('');
                $('#rfm-banner-preview').html('');
                $(this).hide();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save expert meta data
     */
    public static function save_expert_meta($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['rfm_expert_meta_nonce']) || !wp_verify_nonce($_POST['rfm_expert_meta_nonce'], 'rfm_expert_meta_nonce')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save profile information
        $fields = array('email', 'phone', 'website', 'address', 'city', 'postal_code', 'about_me');
        foreach ($fields as $field) {
            if (isset($_POST['rfm_' . $field])) {
                if ($field === 'about_me') {
                    update_post_meta($post_id, '_rfm_' . $field, sanitize_textarea_field($_POST['rfm_' . $field]));
                } else {
                    update_post_meta($post_id, '_rfm_' . $field, sanitize_text_field($_POST['rfm_' . $field]));
                }
            }
        }
        
        // Save education
        if (isset($_POST['rfm_educations'])) {
            $educations = array_map(function($education) {
                return array(
                    'title' => sanitize_text_field($education['title']),
                    'institution' => sanitize_text_field($education['institution']),
                    'year' => sanitize_text_field($education['year'])
                );
            }, $_POST['rfm_educations']);
            update_post_meta($post_id, '_rfm_educations', $educations);
        }
        
        if (isset($_POST['rfm_years_experience'])) {
            update_post_meta($post_id, '_rfm_years_experience', intval($_POST['rfm_years_experience']));
        }
        
        // Save subscription data
        if (isset($_POST['rfm_subscription_plan'])) {
            update_post_meta($post_id, '_rfm_subscription_plan', sanitize_text_field($_POST['rfm_subscription_plan']));
        }
        
        if (isset($_POST['rfm_subscription_status'])) {
            update_post_meta($post_id, '_rfm_subscription_status', sanitize_text_field($_POST['rfm_subscription_status']));
        }
        
        if (isset($_POST['rfm_subscription_end'])) {
            update_post_meta($post_id, '_rfm_subscription_end', sanitize_text_field($_POST['rfm_subscription_end']));
        }
        
        // Save banner image
        if (isset($_POST['rfm_banner_image_id'])) {
            update_post_meta($post_id, '_rfm_banner_image_id', intval($_POST['rfm_banner_image_id']));
        }
    }

    /**
     * Register User post type (for regular users - Brugere)
     */
    private static function register_user_post_type() {
        $labels = array(
            'name'                  => _x('Brugere', 'Post type general name', 'rigtig-for-mig'),
            'singular_name'         => _x('Bruger', 'Post type singular name', 'rigtig-for-mig'),
            'menu_name'             => _x('Brugere', 'Admin Menu text', 'rigtig-for-mig'),
            'name_admin_bar'        => _x('Bruger', 'Add New on Toolbar', 'rigtig-for-mig'),
            'add_new'               => __('Tilføj Ny', 'rigtig-for-mig'),
            'add_new_item'          => __('Tilføj Ny Bruger', 'rigtig-for-mig'),
            'new_item'              => __('Ny Bruger', 'rigtig-for-mig'),
            'edit_item'             => __('Rediger Bruger', 'rigtig-for-mig'),
            'view_item'             => __('Vis Bruger', 'rigtig-for-mig'),
            'all_items'             => __('Alle Brugere', 'rigtig-for-mig'),
            'search_items'          => __('Søg Brugere', 'rigtig-for-mig'),
            'not_found'             => __('Ingen brugere fundet.', 'rigtig-for-mig'),
            'not_found_in_trash'    => __('Ingen brugere fundet i papirkurv.', 'rigtig-for-mig'),
            'featured_image'        => _x('Profilbillede', 'Overrides the "Featured Image" phrase', 'rigtig-for-mig'),
            'set_featured_image'    => _x('Sæt profilbillede', 'Overrides the "Set featured image" phrase', 'rigtig-for-mig'),
            'remove_featured_image' => _x('Fjern profilbillede', 'Overrides the "Remove featured image" phrase', 'rigtig-for-mig'),
            'use_featured_image'    => _x('Brug som profilbillede', 'Overrides the "Use as featured image" phrase', 'rigtig-for-mig'),
        );

        $args = array(
            'labels'             => $labels,
            'description'        => __('Bruger profiler for Rigtig for mig markedsplads', 'rigtig-for-mig'),
            'public'             => false,  // Not public - only admin access
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'rfm-dashboard',  // Show under main RFM menu
            'query_var'          => true,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-groups',
            'supports'           => array('title', 'thumbnail'),
            'show_in_rest'       => true,
        );

        register_post_type('rfm_bruger', $args);

        // Add custom meta boxes
        add_action('add_meta_boxes', array(__CLASS__, 'add_user_meta_boxes'));
        add_action('save_post_rfm_bruger', array(__CLASS__, 'save_user_meta'), 10, 2);
    }

    /**
     * Add custom meta boxes for user profiles
     */
    public static function add_user_meta_boxes() {
        // Profile Information
        add_meta_box(
            'rfm_user_profile_info',
            __('Profil Information', 'rigtig-for-mig'),
            array(__CLASS__, 'render_user_profile_info_meta_box'),
            'rfm_bruger',
            'normal',
            'high'
        );

        // Bio
        add_meta_box(
            'rfm_user_bio',
            __('Bio / Om Mig', 'rigtig-for-mig'),
            array(__CLASS__, 'render_user_bio_meta_box'),
            'rfm_bruger',
            'normal',
            'high'
        );

        // WordPress User Link & Status
        add_meta_box(
            'rfm_user_status',
            __('Login & Status', 'rigtig-for-mig'),
            array(__CLASS__, 'render_user_status_meta_box'),
            'rfm_bruger',
            'side',
            'high'
        );
    }

    /**
     * Render user profile information meta box
     */
    public static function render_user_profile_info_meta_box($post) {
        wp_nonce_field('rfm_user_meta_nonce', 'rfm_user_meta_nonce');

        $phone = get_post_meta($post->ID, '_rfm_phone', true);
        $email = get_post_meta($post->ID, '_rfm_email', true);
        $email_verified = get_post_meta($post->ID, '_rfm_email_verified', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="rfm_user_email"><?php _e('Email', 'rigtig-for-mig'); ?></label></th>
                <td>
                    <input type="email" id="rfm_user_email" name="rfm_user_email" value="<?php echo esc_attr($email); ?>" class="regular-text" required />
                    <?php if ($email_verified === '1'): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        <span style="color: green;"><?php _e('Verificeret', 'rigtig-for-mig'); ?></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning" style="color: orange;"></span>
                        <span style="color: orange;"><?php _e('Ikke verificeret', 'rigtig-for-mig'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="rfm_user_phone"><?php _e('Telefon', 'rigtig-for-mig'); ?></label></th>
                <td><input type="tel" id="rfm_user_phone" name="rfm_user_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="rfm_user_email_verified"><?php _e('Email Verificeret', 'rigtig-for-mig'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="rfm_user_email_verified" name="rfm_user_email_verified" value="1" <?php checked($email_verified, '1'); ?> />
                        <?php _e('Ja, email er verificeret', 'rigtig-for-mig'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render user bio meta box
     */
    public static function render_user_bio_meta_box($post) {
        $bio = get_post_meta($post->ID, '_rfm_bio', true);

        ?>
        <p><em><?php _e('Fortæl lidt om dig selv.', 'rigtig-for-mig'); ?></em></p>
        <textarea id="rfm_user_bio" name="rfm_user_bio" rows="10" style="width: 100%;"><?php echo esc_textarea($bio); ?></textarea>
        <?php
    }

    /**
     * Render user status meta box (WordPress user link)
     */
    public static function render_user_status_meta_box($post) {
        $wp_user_id = get_post_meta($post->ID, '_rfm_wp_user_id', true);
        $account_created = get_post_meta($post->ID, '_rfm_account_created_at', true);
        $last_login = get_post_meta($post->ID, '_rfm_last_login', true);

        ?>
        <p>
            <label for="rfm_wp_user_id"><strong><?php _e('WordPress User ID', 'rigtig-for-mig'); ?></strong></label><br>
            <input type="number" id="rfm_wp_user_id" name="rfm_wp_user_id" value="<?php echo esc_attr($wp_user_id); ?>" style="width: 100%;" />
            <small><?php _e('ID på den WordPress bruger der logger ind', 'rigtig-for-mig'); ?></small>
        </p>

        <?php if ($wp_user_id):
            $wp_user = get_user_by('ID', $wp_user_id);
            if ($wp_user): ?>
                <p>
                    <strong><?php _e('WordPress Bruger:', 'rigtig-for-mig'); ?></strong><br>
                    <?php echo esc_html($wp_user->user_login); ?><br>
                    <a href="<?php echo get_edit_user_link($wp_user_id); ?>" target="_blank"><?php _e('Rediger WP bruger', 'rigtig-for-mig'); ?></a>
                </p>
            <?php endif;
        endif; ?>

        <hr>

        <p>
            <strong><?php _e('Konto Oprettet:', 'rigtig-for-mig'); ?></strong><br>
            <?php echo $account_created ? esc_html($account_created) : __('Ikke angivet', 'rigtig-for-mig'); ?>
        </p>

        <p>
            <strong><?php _e('Sidste Login:', 'rigtig-for-mig'); ?></strong><br>
            <?php echo $last_login ? esc_html($last_login) : __('Aldrig', 'rigtig-for-mig'); ?>
        </p>
        <?php
    }

    /**
     * Save user meta data
     */
    public static function save_user_meta($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['rfm_user_meta_nonce']) || !wp_verify_nonce($_POST['rfm_user_meta_nonce'], 'rfm_user_meta_nonce')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save profile information
        if (isset($_POST['rfm_user_email'])) {
            update_post_meta($post_id, '_rfm_email', sanitize_email($_POST['rfm_user_email']));
        }

        if (isset($_POST['rfm_user_phone'])) {
            update_post_meta($post_id, '_rfm_phone', sanitize_text_field($_POST['rfm_user_phone']));
        }

        if (isset($_POST['rfm_user_bio'])) {
            update_post_meta($post_id, '_rfm_bio', sanitize_textarea_field($_POST['rfm_user_bio']));
        }

        // Save email verified status
        $email_verified = isset($_POST['rfm_user_email_verified']) ? '1' : '0';
        update_post_meta($post_id, '_rfm_email_verified', $email_verified);

        // If verified, save timestamp
        if ($email_verified === '1' && !get_post_meta($post_id, '_rfm_email_verified_at', true)) {
            update_post_meta($post_id, '_rfm_email_verified_at', current_time('mysql'));
        }

        // Save WordPress user ID link
        if (isset($_POST['rfm_wp_user_id'])) {
            update_post_meta($post_id, '_rfm_wp_user_id', intval($_POST['rfm_wp_user_id']));
        }
    }
}
