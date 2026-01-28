<?php
/**
 * Database Management
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Database {
    
    /**
     * Create custom database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Enable error reporting for debugging
        $wpdb->show_errors();
        
        // Ratings table
        $table_ratings = $wpdb->prefix . 'rfm_ratings';
        $sql_ratings = "CREATE TABLE $table_ratings (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            expert_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            rating tinyint(1) NOT NULL,
            review text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY expert_id (expert_id),
            KEY user_id (user_id),
            UNIQUE KEY unique_rating (expert_id, user_id)
        ) $charset_collate;";
        
        dbDelta($sql_ratings);
        
        // Messages table
        $table_messages = $wpdb->prefix . 'rfm_messages';
        $sql_messages = "CREATE TABLE $table_messages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) UNSIGNED NOT NULL,
            recipient_id bigint(20) UNSIGNED NOT NULL,
            expert_id bigint(20) UNSIGNED NOT NULL,
            subject varchar(255),
            message text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY sender_id (sender_id),
            KEY recipient_id (recipient_id),
            KEY expert_id (expert_id)
        ) $charset_collate;";
        
        dbDelta($sql_messages);
        
        // Email verification tokens
        $table_verification = $wpdb->prefix . 'rfm_email_verification';
        $sql_verification = "CREATE TABLE $table_verification (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            expert_id bigint(20) UNSIGNED NOT NULL,
            email varchar(255) NOT NULL,
            token varchar(64) NOT NULL,
            expires_at datetime NOT NULL,
            verified_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY expert_id (expert_id),
            KEY token (token),
            KEY email (email)
        ) $charset_collate;";
        
        dbDelta($sql_verification);
        
        // Subscriptions table
        $table_subscriptions = $wpdb->prefix . 'rfm_subscriptions';
        $sql_subscriptions = "CREATE TABLE $table_subscriptions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            expert_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            plan varchar(50) NOT NULL,
            status varchar(50) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'DKK',
            stripe_subscription_id varchar(255),
            stripe_customer_id varchar(255),
            start_date datetime NOT NULL,
            end_date datetime,
            next_billing_date datetime,
            cancelled_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY expert_id (expert_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql_subscriptions);
        
        // Payment history
        $table_payments = $wpdb->prefix . 'rfm_payments';
        $sql_payments = "CREATE TABLE $table_payments (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subscription_id bigint(20) UNSIGNED NOT NULL,
            expert_id bigint(20) UNSIGNED NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'DKK',
            status varchar(50) NOT NULL,
            stripe_payment_intent_id varchar(255),
            stripe_invoice_id varchar(255),
            paid_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY subscription_id (subscription_id),
            KEY expert_id (expert_id)
        ) $charset_collate;";
        
        dbDelta($sql_payments);
        
        // User profiles table (for GDPR and extended data)
        $table_user_profiles = $wpdb->prefix . 'rfm_user_profiles';
        $sql_user_profiles = "CREATE TABLE $table_user_profiles (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            profile_image varchar(255),
            bio text,
            phone varchar(50),
            gdpr_consent tinyint(1) DEFAULT 1,
            gdpr_consent_date datetime DEFAULT CURRENT_TIMESTAMP,
            account_created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_login datetime,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql_user_profiles);
        
        // Message threads table (for organizing conversations)
        $table_threads = $wpdb->prefix . 'rfm_message_threads';
        $sql_threads = "CREATE TABLE $table_threads (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            expert_id bigint(20) UNSIGNED NOT NULL,
            last_message_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY thread_unique (user_id, expert_id),
            KEY user_id (user_id),
            KEY expert_id (expert_id)
        ) $charset_collate;";
        
        dbDelta($sql_threads);
        
        // Bookings table (v3.10.0)
        $table_bookings = $wpdb->prefix . 'rfm_bookings';
        $sql_bookings = "CREATE TABLE $table_bookings (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            expert_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            duration int(11) NOT NULL DEFAULT 60,
            status varchar(20) NOT NULL DEFAULT 'pending',
            note text,
            expert_note text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY expert_id (expert_id),
            KEY user_id (user_id),
            KEY booking_date (booking_date),
            KEY status (status)
        ) $charset_collate;";

        dbDelta($sql_bookings);

        // Expert availability table (v3.10.0)
        $table_availability = $wpdb->prefix . 'rfm_availability';
        $sql_availability = "CREATE TABLE $table_availability (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            expert_id bigint(20) UNSIGNED NOT NULL,
            day_of_week tinyint(1) NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY  (id),
            KEY expert_id (expert_id),
            KEY day_of_week (day_of_week)
        ) $charset_collate;";

        dbDelta($sql_availability);

        // Log any errors
        if (!empty($wpdb->last_error)) {
            error_log('RFM Database Error: ' . $wpdb->last_error);
        }

        // Verify tables were created
        $tables_to_check = array(
            'rfm_ratings',
            'rfm_messages',
            'rfm_email_verification',
            'rfm_subscriptions',
            'rfm_payments',
            'rfm_user_profiles',
            'rfm_message_threads',
            'rfm_bookings',
            'rfm_availability'
        );
        
        foreach ($tables_to_check as $table) {
            $table_name = $wpdb->prefix . $table;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if ($table_exists != $table_name) {
                error_log("RFM: Table $table_name was not created!");
            } else {
                error_log("RFM: Table $table_name created successfully");
            }
        }
        
        // Update database version
        update_option('rfm_db_version', '1.2.0');
        
        // Hide errors again
        $wpdb->hide_errors();
    }
    
    /**
     * Drop all custom tables (use with caution)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'rfm_ratings',
            $wpdb->prefix . 'rfm_messages',
            $wpdb->prefix . 'rfm_message_threads',
            $wpdb->prefix . 'rfm_email_verification',
            $wpdb->prefix . 'rfm_subscriptions',
            $wpdb->prefix . 'rfm_payments',
            $wpdb->prefix . 'rfm_user_profiles',
            $wpdb->prefix . 'rfm_bookings',
            $wpdb->prefix . 'rfm_availability'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('rfm_db_version');
    }
    
    /**
     * Get table name with prefix
     */
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'rfm_' . $table;
    }
}
