<?php
/**
 * Plugin Name: Rigtig for mig - Ekspert Markedsplads
 * Plugin URI: https://rigtigformig.dk
 * Description: En komplet markedsplads for terapeuter, coaches, mentorer og vejledere med profilsider, ratings, abonnementer og multi-language support.
 * Version: 3.9.7
 * Author: Rigtig for mig
 * Author URI: https://rigtigformig.dk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rigtig-for-mig
 * Domain Path: /languages
 * Author: Frank H.
 * GitHub Plugin URI: FHT6040/Rigtigformig
 * Primary Branch: claude/explain-codebase-mj7bh0cyz8e4f4wb-tpHwD
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('RFM_VERSION', '3.9.7');
define('RFM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RFM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RFM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Debug mode - only enable in development
define('RFM_DEBUG', defined('WP_DEBUG') && WP_DEBUG);

/**
 * Helper function for conditional logging
 * Only logs when RFM_DEBUG is true
 *
 * @param string $message Log message
 */
function rfm_log($message) {
    if (RFM_DEBUG) {
        error_log('RFM: ' . $message);
    }
}

/**
 * Main Plugin Class
 */
class Rigtig_For_Mig {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-post-types.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-taxonomies.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-database.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-migration.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-upload-manager.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-email-verification.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-subscriptions.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-ratings.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-messages.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-notifications.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-expert-profile.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-postal-codes.php';

        // Expert system - Refactored modular classes (v3.5.0+)
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-expert-authentication.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-expert-registration.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-expert-dashboard.php'; // Phase 2.1 (v3.6.0)
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-expert-profile-editor.php'; // Phase 2.2 (v3.6.0)
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-expert-role-manager.php'; // Phase 2.3 (v3.6.0)

        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-flexible-fields.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-bulk-import.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-password-reset.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-online-status.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-category-profiles.php';
        
        // User system classes
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-user-registration.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-user-dashboard.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-contact-protection.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-debug-helper.php';

        // Admin classes
        require_once RFM_PLUGIN_DIR . 'admin/class-rfm-admin.php';
        require_once RFM_PLUGIN_DIR . 'admin/class-rfm-admin-settings.php';
        require_once RFM_PLUGIN_DIR . 'admin/class-rfm-user-admin.php';
        require_once RFM_PLUGIN_DIR . 'admin/class-rfm-migration-admin.php';
        require_once RFM_PLUGIN_DIR . 'admin/class-rfm-email-templates.php';  // v3.9.0
        require_once RFM_PLUGIN_DIR . 'admin/class-rfm-mass-email.php';       // v3.9.0
        require_once RFM_PLUGIN_DIR . 'admin/assign-categories-tool.php';     // v3.9.3

        if (is_admin()) {
            // Additional admin-only code can go here
        }
        
        // Public classes
        require_once RFM_PLUGIN_DIR . 'public/class-rfm-public.php';
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-shortcodes.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Initialize components
        add_action('init', array($this, 'init_components'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Override WordPress login URL to use custom login page
        add_filter('login_url', array($this, 'custom_login_url'), 10, 3);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create custom tables
        RFM_Database::create_tables();
        
        // Register post types and taxonomies
        RFM_Post_Types::register();
        RFM_Taxonomies::register();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set default options
        $this->set_default_options();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Override WordPress login URL to use custom login page
     *
     * @param string $login_url The login URL
     * @param string $redirect The redirect URL after login
     * @param bool $force_reauth Whether to force reauthentication
     * @return string Custom login URL
     */
    public function custom_login_url($login_url, $redirect = '', $force_reauth = false) {
        // Only use custom login for frontend users (Eksperter og Brugere)
        // Check if redirect points to frontend dashboards
        $use_custom_login = false;

        if (!empty($redirect)) {
            $redirect_lower = strtolower($redirect);
            if (strpos($redirect_lower, 'ekspert-dashboard') !== false ||
                strpos($redirect_lower, 'bruger-dashboard') !== false) {
                $use_custom_login = true;
            }
        }

        // If not a frontend dashboard redirect, use standard WordPress login
        if (!$use_custom_login) {
            return $login_url;
        }

        // Use custom /login/ page for frontend users
        $custom_login = home_url('/login/');

        if (!empty($redirect)) {
            $custom_login = add_query_arg('redirect_to', urlencode($redirect), $custom_login);
        }

        if ($force_reauth) {
            $custom_login = add_query_arg('reauth', '1', $custom_login);
        }

        return $custom_login;
    }

    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'rfm_free_features' => array(
                'basic_profile',
                'single_category',
                'basic_listing'
            ),
            'rfm_standard_price' => 219,
            'rfm_standard_features' => array(
                'enhanced_profile',
                'multiple_categories',
                'featured_badge',
                'basic_analytics'
            ),
            'rfm_premium_price' => 399,
            'rfm_premium_features' => array(
                'premium_profile',
                'unlimited_categories',
                'top_placement',
                'advanced_analytics',
                'priority_support'
            ),
            'rfm_currency' => 'DKK',
            'rfm_email_verification' => true
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Load text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'rigtig-for-mig',
            false,
            dirname(RFM_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Register post types and taxonomies
        RFM_Post_Types::register();
        RFM_Taxonomies::register();
        
        // Initialize other components
        RFM_Email_Verification::get_instance();
        RFM_Subscriptions::get_instance();
        RFM_Ratings::get_instance();
        RFM_Messages::get_instance();
        RFM_Notifications::get_instance();
        RFM_Expert_Profile::get_instance();
        RFM_Shortcodes::get_instance();

        // Expert system - Refactored modular classes (v3.5.0+)
        RFM_Expert_Authentication::get_instance();
        RFM_Expert_Registration::get_instance();
        RFM_Expert_Dashboard::get_instance(); // Phase 2.1 (v3.6.0)
        RFM_Expert_Profile_Editor::get_instance(); // Phase 2.2 (v3.6.0)
        RFM_Expert_Role_Manager::get_instance(); // Phase 2.3 (v3.6.0)

        RFM_Flexible_Fields_System::get_instance();
        RFM_Password_Reset::get_instance();
        RFM_Online_Status::get_instance();
        RFM_Category_Profiles::get_instance();
        
        // Initialize user system
        RFM_User_Registration::get_instance();
        RFM_User_Dashboard::get_instance();
        RFM_Contact_Protection::get_instance();

        // Initialize debug helper (only when WP_DEBUG is enabled)
        RFM_Debug_Helper::get_instance();

        // Initialize upload manager (v3.4.0)
        RFM_Upload_Manager::get_instance();
        
        // Initialize bulk import (admin only)
        if (is_admin()) {
            RFM_Bulk_Import::init();
        }
        
        // Initialize admin settings
        RFM_Admin_Settings::init();

        // Initialize email templates and mass email (v3.9.0)
        if (is_admin()) {
            RFM_Email_Templates::get_instance();
            RFM_Mass_Email::get_instance();
        }
        
        // Register Elementor widgets if Elementor is active
        add_action('elementor/widgets/register', array($this, 'register_elementor_widgets'));
        
        if (is_admin()) {
            RFM_Admin::get_instance();
            RFM_User_Admin::get_instance();
        } else {
            RFM_Public::get_instance();
        }
    }
    
    /**
     * Register Elementor widgets
     */
    public function register_elementor_widgets($widgets_manager) {
        require_once RFM_PLUGIN_DIR . 'includes/class-rfm-elementor-widget.php';
        $widgets_manager->register(new RFM_Elementor_Expert_Widget());
    }
    
    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets() {
        // Note: Public assets are now enqueued by RFM_Public class
        // This method is kept for backwards compatibility and can be used for global assets
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        wp_enqueue_style(
            'rfm-admin-styles',
            RFM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            RFM_VERSION
        );
        
        wp_enqueue_script(
            'rfm-admin-scripts',
            RFM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            RFM_VERSION,
            true
        );
    }
}

/**
 * Initialize the plugin
 */
function rfm_init() {
    return Rigtig_For_Mig::get_instance();
}

/**
 * Plugin activation
 */
function rfm_activate() {
    // Create database tables
    require_once RFM_PLUGIN_DIR . 'includes/class-rfm-database.php';
    RFM_Database::create_tables();
    
    // Register post types and taxonomies
    require_once RFM_PLUGIN_DIR . 'includes/class-rfm-post-types.php';
    require_once RFM_PLUGIN_DIR . 'includes/class-rfm-taxonomies.php';
    RFM_Post_Types::register();
    RFM_Taxonomies::register();
    
    // Create expert user role
    rfm_create_expert_role();
    
    // Create regular user role
    rfm_create_user_role();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Create expert user role
 */
function rfm_create_expert_role() {
    // Remove role first to ensure clean registration
    remove_role('rfm_expert_user');
    
    // Add role with capabilities
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

/**
 * Create regular user role
 */
function rfm_create_user_role() {
    // Remove role first to ensure clean registration
    remove_role('rfm_user');
    
    // Add role with capabilities
    add_role(
        'rfm_user',
        __('Bruger', 'rigtig-for-mig'),
        array(
            'read' => true,
        )
    );
}

/**
 * Plugin deactivation
 */
function rfm_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Register hooks
register_activation_hook(__FILE__, 'rfm_activate');
register_deactivation_hook(__FILE__, 'rfm_deactivate');

// Start the plugin
rfm_init();
