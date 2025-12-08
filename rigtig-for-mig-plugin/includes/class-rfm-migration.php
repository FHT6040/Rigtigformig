<?php
/**
 * Migration Script: Convert Users from Custom Table to Custom Post Type
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Migration {

    /**
     * Migrate users from wp_rfm_user_profiles table to rfm_bruger custom post type
     */
    public static function migrate_users_to_cpt() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rfm_user_profiles';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array(
                'success' => false,
                'message' => 'Table wp_rfm_user_profiles does not exist'
            );
        }

        $migrated_count = 0;
        $skipped_count = 0;
        $error_count = 0;
        $errors = array();

        error_log('RFM MIGRATION: Starting user migration to Custom Post Type');

        // Get all WordPress users with rfm_user role
        $users = get_users(array('role' => 'rfm_user'));

        foreach ($users as $user) {
            try {
                // Check if already migrated
                $existing_post = get_posts(array(
                    'post_type' => 'rfm_bruger',
                    'meta_key' => '_rfm_wp_user_id',
                    'meta_value' => $user->ID,
                    'post_status' => 'any',
                    'posts_per_page' => 1
                ));

                if (!empty($existing_post)) {
                    error_log("RFM MIGRATION: User {$user->ID} already migrated (post ID: {$existing_post[0]->ID}), skipping");
                    $skipped_count++;
                    continue;
                }

                // Get profile data from custom table
                $profile = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE user_id = %d",
                    $user->ID
                ));

                // Create custom post
                $post_data = array(
                    'post_type'   => 'rfm_bruger',
                    'post_title'  => $user->display_name,
                    'post_status' => 'publish',
                    'post_author' => 1,  // Admin
                );

                $post_id = wp_insert_post($post_data, true);

                if (is_wp_error($post_id)) {
                    throw new Exception("Failed to create post: " . $post_id->get_error_message());
                }

                // Link to WordPress user (CRITICAL!)
                update_post_meta($post_id, '_rfm_wp_user_id', $user->ID);

                // Migrate email (from WordPress user)
                update_post_meta($post_id, '_rfm_email', $user->user_email);

                // Migrate email verification status from user_meta
                $email_verified = get_user_meta($user->ID, 'rfm_email_verified', true);
                $verified_value = ($email_verified === '1' || $email_verified === 1 || $email_verified === true) ? '1' : '0';
                update_post_meta($post_id, '_rfm_email_verified', $verified_value);

                // Migrate verification date if exists
                $verified_at = get_user_meta($user->ID, 'rfm_email_verified_at', true);
                if ($verified_at) {
                    update_post_meta($post_id, '_rfm_email_verified_at', $verified_at);
                }

                // Migrate data from custom table if exists
                if ($profile) {
                    // Migrate profile image (featured image)
                    if ($profile->profile_image) {
                        set_post_thumbnail($post_id, intval($profile->profile_image));
                    }

                    // Migrate bio
                    if ($profile->bio) {
                        update_post_meta($post_id, '_rfm_bio', $profile->bio);
                    }

                    // Migrate phone
                    if ($profile->phone) {
                        update_post_meta($post_id, '_rfm_phone', $profile->phone);
                    }

                    // Migrate GDPR consent
                    if (isset($profile->gdpr_consent)) {
                        update_post_meta($post_id, '_rfm_gdpr_consent', $profile->gdpr_consent);
                    }

                    if ($profile->gdpr_consent_date) {
                        update_post_meta($post_id, '_rfm_gdpr_consent_date', $profile->gdpr_consent_date);
                    }

                    // Migrate timestamps
                    if ($profile->account_created_at) {
                        update_post_meta($post_id, '_rfm_account_created_at', $profile->account_created_at);
                    }

                    if ($profile->last_login) {
                        update_post_meta($post_id, '_rfm_last_login', $profile->last_login);
                    }
                }

                error_log("RFM MIGRATION: Successfully migrated user {$user->ID} ({$user->user_login}) to post {$post_id}");
                $migrated_count++;

            } catch (Exception $e) {
                $error_count++;
                $error_msg = "Failed to migrate user {$user->ID}: " . $e->getMessage();
                error_log("RFM MIGRATION ERROR: $error_msg");
                $errors[] = $error_msg;
            }
        }

        $summary = array(
            'success' => true,
            'total_users' => count($users),
            'migrated' => $migrated_count,
            'skipped' => $skipped_count,
            'errors' => $error_count,
            'error_messages' => $errors
        );

        error_log('RFM MIGRATION: Completed - ' . json_encode($summary));

        return $summary;
    }

    /**
     * Get user profile post by WordPress user ID
     */
    public static function get_user_profile_by_wp_user_id($wp_user_id) {
        $posts = get_posts(array(
            'post_type' => 'rfm_bruger',
            'meta_key' => '_rfm_wp_user_id',
            'meta_value' => $wp_user_id,
            'post_status' => 'any',
            'posts_per_page' => 1
        ));

        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Create user profile post when new WordPress user registers
     */
    public static function create_user_profile_on_registration($user_id, $user_email = '', $display_name = '') {
        // Check if profile already exists
        $existing = self::get_user_profile_by_wp_user_id($user_id);
        if ($existing) {
            error_log("RFM: User profile already exists for WP user $user_id (post ID: {$existing->ID})");
            return $existing->ID;
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            error_log("RFM ERROR: WordPress user $user_id not found");
            return false;
        }

        // Create custom post
        $post_data = array(
            'post_type'   => 'rfm_bruger',
            'post_title'  => $display_name ?: $user->display_name,
            'post_status' => 'publish',
            'post_author' => 1,
        );

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            error_log("RFM ERROR: Failed to create user profile post: " . $post_id->get_error_message());
            return false;
        }

        // Link to WordPress user
        update_post_meta($post_id, '_rfm_wp_user_id', $user_id);

        // Set email
        update_post_meta($post_id, '_rfm_email', $user_email ?: $user->user_email);

        // Set email verification to false
        update_post_meta($post_id, '_rfm_email_verified', '0');

        // Set account created timestamp
        update_post_meta($post_id, '_rfm_account_created_at', current_time('mysql'));

        error_log("RFM: Created user profile post $post_id for WordPress user $user_id");

        return $post_id;
    }

    /**
     * Update last login timestamp
     */
    public static function update_last_login($wp_user_id) {
        $profile = self::get_user_profile_by_wp_user_id($wp_user_id);
        if ($profile) {
            update_post_meta($profile->ID, '_rfm_last_login', current_time('mysql'));
            error_log("RFM: Updated last login for user $wp_user_id (post ID: {$profile->ID})");
        }
    }

    /**
     * Set user verified status
     */
    public static function set_user_verified($wp_user_id, $verified = true) {
        $profile = self::get_user_profile_by_wp_user_id($wp_user_id);
        if ($profile) {
            $value = $verified ? '1' : '0';
            update_post_meta($profile->ID, '_rfm_email_verified', $value);

            if ($verified) {
                update_post_meta($profile->ID, '_rfm_email_verified_at', current_time('mysql'));
            }

            error_log("RFM: Set user $wp_user_id verification to: $value");
            return true;
        }

        error_log("RFM ERROR: Could not find user profile for WP user $wp_user_id");
        return false;
    }

    /**
     * Check if user is verified
     */
    public static function is_user_verified($wp_user_id) {
        $profile = self::get_user_profile_by_wp_user_id($wp_user_id);
        if ($profile) {
            $verified = get_post_meta($profile->ID, '_rfm_email_verified', true);
            return ($verified === '1');
        }
        return false;
    }
}
