<?php
/**
 * Admin Settings
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Admin_Settings {
    
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_additional_settings'));
    }
    
    /**
     * Register additional settings
     */
    public static function register_additional_settings() {
        // Field Visibility Section
        add_settings_section(
            'rfm_field_visibility_section',
            __('Felt Synlighed', 'rigtig-for-mig'),
            array(__CLASS__, 'render_field_visibility_section'),
            'rfm-settings'
        );
        
        // Show Featured Image
        register_setting('rfm_settings', 'rfm_show_featured_image');
        add_settings_field(
            'rfm_show_featured_image',
            __('Vis profilbillede', 'rigtig-for-mig'),
            array(__CLASS__, 'render_checkbox'),
            'rfm-settings',
            'rfm_field_visibility_section',
            array('field' => 'rfm_show_featured_image', 'label' => __('Vis featured image/profilbillede på profil siden', 'rigtig-for-mig'))
        );
        
        // Show Phone
        register_setting('rfm_settings', 'rfm_show_phone');
        add_settings_field(
            'rfm_show_phone',
            __('Vis telefon', 'rigtig-for-mig'),
            array(__CLASS__, 'render_checkbox'),
            'rfm-settings',
            'rfm_field_visibility_section',
            array('field' => 'rfm_show_phone', 'label' => __('Vis telefon felt', 'rigtig-for-mig'), 'default' => true)
        );
        
        // Show Email
        register_setting('rfm_settings', 'rfm_show_email');
        add_settings_field(
            'rfm_show_email',
            __('Vis email', 'rigtig-for-mig'),
            array(__CLASS__, 'render_checkbox'),
            'rfm-settings',
            'rfm_field_visibility_section',
            array('field' => 'rfm_show_email', 'label' => __('Vis email felt', 'rigtig-for-mig'), 'default' => true)
        );
        
        // Show Website
        register_setting('rfm_settings', 'rfm_show_website');
        add_settings_field(
            'rfm_show_website',
            __('Vis hjemmeside', 'rigtig-for-mig'),
            array(__CLASS__, 'render_checkbox'),
            'rfm-settings',
            'rfm_field_visibility_section',
            array('field' => 'rfm_show_website', 'label' => __('Vis hjemmeside felt', 'rigtig-for-mig'), 'default' => true)
        );
        
        // Show Address
        register_setting('rfm_settings', 'rfm_show_address');
        add_settings_field(
            'rfm_show_address',
            __('Vis adresse', 'rigtig-for-mig'),
            array(__CLASS__, 'render_checkbox'),
            'rfm-settings',
            'rfm_field_visibility_section',
            array('field' => 'rfm_show_address', 'label' => __('Vis adresse felt', 'rigtig-for-mig'), 'default' => true)
        );
        
        // Show About Me
        register_setting('rfm_settings', 'rfm_show_about_me');
        add_settings_field(
            'rfm_show_about_me',
            __('Vis Om Mig', 'rigtig-for-mig'),
            array(__CLASS__, 'render_checkbox'),
            'rfm-settings',
            'rfm_field_visibility_section',
            array('field' => 'rfm_show_about_me', 'label' => __('Vis Om Mig sektion', 'rigtig-for-mig'), 'default' => true)
        );
        
        // Show Educations
        register_setting('rfm_settings', 'rfm_show_educations');
        add_settings_field(
            'rfm_show_educations',
            __('Vis uddannelser', 'rigtig-for-mig'),
            array(__CLASS__, 'render_checkbox'),
            'rfm-settings',
            'rfm_field_visibility_section',
            array('field' => 'rfm_show_educations', 'label' => __('Vis uddannelser sektion', 'rigtig-for-mig'), 'default' => true)
        );
        
        // Show Experience Years
        register_setting('rfm_settings', 'rfm_show_experience');
        add_settings_field(
            'rfm_show_experience',
            __('Vis erfaring', 'rigtig-for-mig'),
            array(__CLASS__, 'render_checkbox'),
            'rfm-settings',
            'rfm_field_visibility_section',
            array('field' => 'rfm_show_experience', 'label' => __('Vis år i branchen', 'rigtig-for-mig'), 'default' => true)
        );
        
        // Show Specializations
        register_setting('rfm_settings', 'rfm_show_specializations');
        add_settings_field(
            'rfm_show_specializations',
            __('Vis specialiseringer', 'rigtig-for-mig'),
            array(__CLASS__, 'render_checkbox'),
            'rfm-settings',
            'rfm_field_visibility_section',
            array('field' => 'rfm_show_specializations', 'label' => __('Vis specialiseringer', 'rigtig-for-mig'), 'default' => true)
        );
        
        // Frontend Registration Section
        add_settings_section(
            'rfm_frontend_section',
            __('Frontend Registrering', 'rigtig-for-mig'),
            array(__CLASS__, 'render_frontend_section'),
            'rfm-settings'
        );
        
        // Allow Frontend Registration
        register_setting('rfm_settings', 'rfm_allow_frontend_registration');
        add_settings_field(
            'rfm_allow_frontend_registration',
            __('Tillad frontend registrering', 'rigtig-for-mig'),
            array(__CLASS__, 'render_checkbox'),
            'rfm-settings',
            'rfm_frontend_section',
            array('field' => 'rfm_allow_frontend_registration', 'label' => __('Lad eksperter oprette profiler fra frontend', 'rigtig-for-mig'), 'default' => true)
        );
        
        // Auto-approve profiles
        register_setting('rfm_settings', 'rfm_auto_approve_experts');
        add_settings_field(
            'rfm_auto_approve_experts',
            __('Auto-godkend profiler', 'rigtig-for-mig'),
            array(__CLASS__, 'render_checkbox'),
            'rfm-settings',
            'rfm_frontend_section',
            array('field' => 'rfm_auto_approve_experts', 'label' => __('Offentliggør profiler automatisk (ellers som kladde)', 'rigtig-for-mig'), 'default' => true)
        );
    }
    
    public static function render_field_visibility_section() {
        echo '<p>' . __('Vælg hvilke felter der skal vises på ekspert profil siderne.', 'rigtig-for-mig') . '</p>';
    }
    
    public static function render_frontend_section() {
        echo '<p>' . __('Indstillinger for eksperters mulighed for selv at oprette og redigere profiler.', 'rigtig-for-mig') . '</p>';
    }
    
    public static function render_checkbox($args) {
        $default = isset($args['default']) ? $args['default'] : false;
        $value = get_option($args['field'], $default);
        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr($args['field']) . '" value="1" ' . checked($value, true, false) . ' />';
        echo ' ' . esc_html($args['label']);
        echo '</label>';
    }
}
