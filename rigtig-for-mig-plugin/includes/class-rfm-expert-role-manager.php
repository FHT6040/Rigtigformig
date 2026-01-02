<?php
/**
 * Expert Role Manager
 *
 * Centralizes all role and permission management for experts including:
 * - Role creation and capability assignment
 * - Admin access restrictions
 * - Admin bar visibility control
 * - Post editing permissions
 *
 * Part of Phase 2.3 Refactoring - consolidated from multiple sources:
 * - class-rfm-frontend-registration.php (Lines 95-158)
 * - class-rfm-expert-authentication.php (Lines 239-305)
 * - rigtig-for-mig.php (Lines 334-350)
 *
 * @package Rigtig_For_Mig
 * @since 3.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Expert_Role_Manager {

    /**
     * Singleton instance
     *
     * @var RFM_Expert_Role_Manager|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return RFM_Expert_Role_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize hooks
     */
    private function __construct() {
        // Role creation
        add_action('init', array($this, 'add_expert_role'));

        // Admin access restrictions
        add_action('admin_init', array($this, 'restrict_expert_admin_access'));
        add_action('admin_init', array($this, 'block_admin_access_for_experts'));

        // Capability filtering
        add_filter('user_has_cap', array($this, 'expert_edit_own_profile'), 10, 4);

        // Admin bar hiding - Consolidated from RFM_Expert_Authentication
        add_action('after_setup_theme', array($this, 'hide_admin_bar_for_experts'));
        add_filter('show_admin_bar', array($this, 'hide_admin_bar_filter'));
        add_action('init', array($this, 'remove_admin_bar_for_experts'), 9);
        add_filter('body_class', array($this, 'add_expert_body_class'));
    }

    /**
     * Check and ensure expert user role exists
     *
     * Creates the rfm_expert_user role with appropriate capabilities if it doesn't exist.
     * Consolidated from class-rfm-frontend-registration.php and rigtig-for-mig.php
     *
     * @since 3.6.0
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
    }

    /**
     * Restrict expert admin access to only their profile
     *
     * Allows experts to access only specific admin pages like their profile,
     * posts, and upload pages. Redirects to frontend dashboard for other pages.
     *
     * Extracted from class-rfm-frontend-registration.php (Lines 119-135)
     *
     * @since 3.6.0
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
     * Block admin access for expert users (non-admins)
     *
     * Prevents expert users from accessing the admin area entirely unless they
     * also have administrator role. Redirects to frontend dashboard.
     * This is the broader check that happens before restrict_expert_admin_access.
     *
     * Consolidated from RFM_Expert_Authentication (Lines 290-305)
     *
     * @since 3.6.0
     */
    public function block_admin_access_for_experts() {
        // Allow AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (is_user_logged_in()) {
            $user = wp_get_current_user();

            // Experts (non-admins) should not access admin area
            if (in_array('rfm_expert_user', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
                wp_redirect(home_url('/ekspert-dashboard/'));
                exit;
            }
        }
    }

    /**
     * Allow experts to edit only their own profile posts
     *
     * Filters user capabilities to grant edit permissions for their own
     * rfm_expert posts while preventing editing of other experts' profiles.
     *
     * Extracted from class-rfm-frontend-registration.php (Lines 140-158)
     *
     * @since 3.6.0
     *
     * @param array   $allcaps All capabilities of the user
     * @param array   $caps    Required capabilities
     * @param array   $args    Arguments including requested capability and object ID
     * @param WP_User $user    The user object
     * @return array Modified capabilities array
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
     * Hide admin bar for expert users
     *
     * Uses show_admin_bar() to disable the admin bar for expert users on the frontend.
     *
     * Consolidated from RFM_Expert_Authentication (Lines 239-246)
     *
     * @since 3.6.0
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
     *
     * Filters the show_admin_bar value to return false for expert users.
     *
     * Consolidated from RFM_Expert_Authentication (Lines 251-259)
     *
     * @since 3.6.0
     *
     * @param bool $show_admin_bar Whether to show the admin bar
     * @return bool Modified show_admin_bar value
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
     *
     * Adds filter to forcefully remove admin bar and removes the admin bar bump CSS.
     *
     * Consolidated from RFM_Expert_Authentication (Lines 264-272)
     *
     * @since 3.6.0
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
     *
     * Adds 'rfm-expert-user' class to the body element for styling purposes.
     *
     * Consolidated from RFM_Expert_Authentication (Lines 277-285)
     *
     * @since 3.6.0
     *
     * @param array $classes Array of body classes
     * @return array Modified array of body classes
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
}
