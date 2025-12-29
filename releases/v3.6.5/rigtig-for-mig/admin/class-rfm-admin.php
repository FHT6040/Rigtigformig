<?php
/**
 * Admin functionality
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('manage_rfm_expert_posts_columns', array($this, 'add_expert_columns'));
        add_action('manage_rfm_expert_posts_custom_column', array($this, 'render_expert_columns'), 10, 2);
        add_action('admin_notices', array($this, 'check_expert_role'));
        add_action('admin_post_rfm_fix_roles', array($this, 'fix_roles'));
    }
    
    /**
     * Check if expert role exists and show notice
     */
    public function check_expert_role() {
        if (!get_role('rfm_expert_user')) {
            ?>
            <div class="notice notice-error">
                <p><strong>⚠️ Rigtig for mig:</strong> "Ekspert" bruger-rollen mangler!</p>
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=rfm_fix_roles'), 'rfm_fix_roles'); ?>" class="button button-primary">
                        🔧 Opret "Ekspert" Rolle Nu
                    </a>
                </p>
            </div>
            <?php
        }
        
        // Show success message if roles were just fixed
        if (isset($_GET['role-fixed']) && $_GET['role-fixed'] == '1') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✅ Success!</strong> "Ekspert" rollen er nu oprettet. Du kan nu tildele den til brugere.</p>
            </div>
            <?php
        }
    }
    
    /**
     * Fix roles - create expert role
     */
    public function fix_roles() {
        // Check nonce
        check_admin_referer('rfm_fix_roles');
        
        // Check admin permissions
        if (!current_user_can('manage_options')) {
            wp_die('Du har ikke tilladelse til at udføre denne handling.');
        }
        
        // Remove role first to ensure clean creation
        remove_role('rfm_expert_user');
        
        // Create role with proper capabilities
        $role = add_role(
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
        
        // Redirect back with success message
        wp_redirect(add_query_arg(
            array('role-fixed' => '1'),
            wp_get_referer()
        ));
        exit;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Rigtig for mig', 'rigtig-for-mig'),
            __('Rigtig for mig', 'rigtig-for-mig'),
            'manage_options',
            'rfm-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-groups',
            6
        );
        
        add_submenu_page(
            'rfm-dashboard',
            __('Dashboard', 'rigtig-for-mig'),
            __('Dashboard', 'rigtig-for-mig'),
            'manage_options',
            'rfm-dashboard',
            array($this, 'render_dashboard_page')
        );
        
        add_submenu_page(
            'rfm-dashboard',
            __('Indstillinger', 'rigtig-for-mig'),
            __('Indstillinger', 'rigtig-for-mig'),
            'manage_options',
            'rfm-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        global $wpdb;
        
        // Get statistics
        $total_experts = wp_count_posts('rfm_expert')->publish;
        $free_experts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_rfm_subscription_plan' AND meta_value = 'free'");
        $standard_experts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_rfm_subscription_plan' AND meta_value = 'standard'");
        $premium_experts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_rfm_subscription_plan' AND meta_value = 'premium'");
        
        $ratings_table = RFM_Database::get_table_name('ratings');
        $total_ratings = $wpdb->get_var("SELECT COUNT(*) FROM {$ratings_table}");
        
        // Get online experts count
        $online_status = RFM_Online_Status::get_instance();
        $online_count = $online_status->get_online_count();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Rigtig for mig - Dashboard', 'rigtig-for-mig'); ?></h1>
            
            <div class="rfm-dashboard-stats">
                <div class="rfm-stat-box">
                    <h3><?php echo number_format($total_experts); ?></h3>
                    <p><?php _e('Total Eksperter', 'rigtig-for-mig'); ?></p>
                </div>
                
                <div class="rfm-stat-box">
                    <h3><?php echo number_format($premium_experts); ?></h3>
                    <p><?php _e('Premium Eksperter', 'rigtig-for-mig'); ?></p>
                </div>
                
                <div class="rfm-stat-box">
                    <h3><?php echo number_format($standard_experts); ?></h3>
                    <p><?php _e('Standard Eksperter', 'rigtig-for-mig'); ?></p>
                </div>
                
                <div class="rfm-stat-box">
                    <h3><?php echo number_format($total_ratings); ?></h3>
                    <p><?php _e('Total Bedømmelser', 'rigtig-for-mig'); ?></p>
                </div>
                
                <div class="rfm-stat-box rfm-stat-online">
                    <h3>
                        <span class="rfm-status-indicator rfm-status-online"></span>
                        <?php echo number_format($online_count); ?>
                    </h3>
                    <p><?php _e('Online Nu', 'rigtig-for-mig'); ?></p>
                </div>
            </div>
            
            <h2><?php _e('Seneste Eksperter', 'rigtig-for-mig'); ?></h2>
            <?php
            $recent_experts = get_posts(array(
                'post_type' => 'rfm_expert',
                'posts_per_page' => 10,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            if ($recent_experts): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><?php _e('Status', 'rigtig-for-mig'); ?></th>
                            <th><?php _e('Navn', 'rigtig-for-mig'); ?></th>
                            <th><?php _e('Email', 'rigtig-for-mig'); ?></th>
                            <th><?php _e('Plan', 'rigtig-for-mig'); ?></th>
                            <th><?php _e('Rating', 'rigtig-for-mig'); ?></th>
                            <th><?php _e('Sidst aktiv', 'rigtig-for-mig'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_experts as $expert): ?>
                            <tr>
                                <td><?php echo $online_status->render_status_indicator($expert->ID, false); ?></td>
                                <td><a href="<?php echo get_edit_post_link($expert->ID); ?>"><?php echo get_the_title($expert->ID); ?></a></td>
                                <td><?php echo get_post_meta($expert->ID, '_rfm_email', true); ?></td>
                                <td><?php echo ucfirst(get_post_meta($expert->ID, '_rfm_subscription_plan', true)); ?></td>
                                <td><?php echo number_format(RFM_Ratings::get_instance()->get_average_rating($expert->ID), 1); ?> ★</td>
                                <td><?php echo $online_status->get_last_active_time($expert->ID); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Rigtig for mig - Indstillinger', 'rigtig-for-mig'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('rfm_settings');
                do_settings_sections('rfm-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Pricing settings
        add_settings_section(
            'rfm_pricing_section',
            __('Priser', 'rigtig-for-mig'),
            array($this, 'render_pricing_section'),
            'rfm-settings'
        );
        
        register_setting('rfm_settings', 'rfm_standard_price');
        add_settings_field(
            'rfm_standard_price',
            __('Standard pris (kr/mdr)', 'rigtig-for-mig'),
            array($this, 'render_price_field'),
            'rfm-settings',
            'rfm_pricing_section',
            array('field' => 'rfm_standard_price', 'default' => 219)
        );
        
        register_setting('rfm_settings', 'rfm_premium_price');
        add_settings_field(
            'rfm_premium_price',
            __('Premium pris (kr/mdr)', 'rigtig-for-mig'),
            array($this, 'render_price_field'),
            'rfm-settings',
            'rfm_pricing_section',
            array('field' => 'rfm_premium_price', 'default' => 399)
        );
        
        // Email settings
        add_settings_section(
            'rfm_email_section',
            __('Email Indstillinger', 'rigtig-for-mig'),
            array($this, 'render_email_section'),
            'rfm-settings'
        );
        
        register_setting('rfm_settings', 'rfm_email_verification');
        add_settings_field(
            'rfm_email_verification',
            __('Email verificering', 'rigtig-for-mig'),
            array($this, 'render_checkbox_field'),
            'rfm-settings',
            'rfm_email_section',
            array('field' => 'rfm_email_verification', 'label' => __('Kræv email verificering for nye eksperter', 'rigtig-for-mig'))
        );
    }
    
    public function render_pricing_section() {
        echo '<p>' . __('Indstil priserne for abonnementsplanerne.', 'rigtig-for-mig') . '</p>';
    }
    
    public function render_email_section() {
        echo '<p>' . __('Indstil email indstillinger for platformen.', 'rigtig-for-mig') . '</p>';
    }
    
    public function render_price_field($args) {
        $value = get_option($args['field'], $args['default']);
        echo '<input type="number" name="' . esc_attr($args['field']) . '" value="' . esc_attr($value) . '" min="0" step="1" />';
    }
    
    public function render_checkbox_field($args) {
        $value = get_option($args['field'], true);
        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr($args['field']) . '" value="1" ' . checked($value, true, false) . ' />';
        echo ' ' . esc_html($args['label']);
        echo '</label>';
    }
    
    /**
     * Add custom columns to expert list
     */
    public function add_expert_columns($columns) {
        // Build completely custom column order for cleaner admin view
        $new_columns = array(
            'cb' => $columns['cb'],
            'rfm_status' => '<span class="dashicons dashicons-visibility" title="' . esc_attr__('Online Status', 'rigtig-for-mig') . '"></span>',
            'title' => $columns['title'],
            'rfm_plan' => __('Plan', 'rigtig-for-mig'),
            'rfm_rating' => __('Rating', 'rigtig-for-mig'),
            'rfm_verified' => __('Verificeret', 'rigtig-for-mig'),
            'rfm_last_active' => __('Sidst aktiv', 'rigtig-for-mig'),
            'author' => __('Forfatter', 'rigtig-for-mig'),
            'taxonomy-rfm_category' => __('Kategori', 'rigtig-for-mig'),
            'date' => $columns['date']
        );
        
        return $new_columns;
    }
    
    /**
     * Render custom columns
     */
    public function render_expert_columns($column, $post_id) {
        $online_status = RFM_Online_Status::get_instance();
        
        switch ($column) {
            case 'rfm_status':
                echo $online_status->render_status_indicator($post_id, false);
                break;
                
            case 'rfm_plan':
                $plan = get_post_meta($post_id, '_rfm_subscription_plan', true);
                echo ucfirst($plan ?: 'free');
                break;
                
            case 'rfm_rating':
                $rating = RFM_Ratings::get_instance()->get_average_rating($post_id);
                $count = RFM_Ratings::get_instance()->get_rating_count($post_id);
                echo number_format($rating, 1) . ' ★ (' . $count . ')';
                break;
                
            case 'rfm_verified':
                $verified = get_post_meta($post_id, '_rfm_email_verified', true);
                if ($verified) {
                    echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span>';
                } else {
                    echo '<span class="dashicons dashicons-warning" style="color: orange;"></span>';
                }
                break;
                
            case 'rfm_last_active':
                echo $online_status->get_last_active_time($post_id);
                break;
        }
    }
}
