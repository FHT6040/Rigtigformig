<?php
/**
 * RFM Notifications System
 *
 * Handles email notifications for messages and ratings
 *
 * @package Rigtig_For_Mig
 * @since 3.8.38
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Notifications {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hook into message sent action
        add_action('rfm_message_sent', array($this, 'notify_message_sent'), 10, 4);

        // Hook into rating created action
        add_action('rfm_rating_created', array($this, 'notify_rating_created'), 10, 3);
    }

    /**
     * Send email notification when a new message is sent to an expert
     *
     * @param int $message_id Message ID
     * @param int $sender_id User ID of sender
     * @param int $recipient_id User ID of recipient (expert)
     * @param int $expert_id Expert post ID
     */
    public function notify_message_sent($message_id, $sender_id, $recipient_id, $expert_id) {
        // Get recipient email
        $recipient = get_userdata($recipient_id);
        if (!$recipient) {
            return;
        }

        // Get sender name
        $sender = get_userdata($sender_id);
        $sender_name = $sender ? $sender->display_name : 'En bruger';

        // Get expert name
        $expert_name = get_the_title($expert_id);

        // Email subject
        $subject = sprintf(
            __('Ny besked vedrørende %s', 'rigtig-for-mig'),
            $expert_name
        );

        // Email message
        $message = sprintf(
            __("Hej %s,\n\nDu har modtaget en ny besked fra %s vedrørende din ekspert-profil '%s'.\n\nLog ind på dit dashboard for at læse og svare på beskeden:\n%s\n\nMed venlig hilsen,\n%s", 'rigtig-for-mig'),
            $recipient->display_name,
            $sender_name,
            $expert_name,
            home_url('/ekspert-dashboard/'),
            get_bloginfo('name')
        );

        // Email headers
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        // Send email
        $sent = wp_mail($recipient->user_email, $subject, $message, $headers);

        if (!$sent) {
            error_log('RFM: Failed to send message notification to ' . $recipient->user_email);
        }

        return $sent;
    }

    /**
     * Send email notification when a new rating is submitted for an expert
     *
     * @param int $rating_id Rating ID
     * @param int $expert_id Expert post ID
     * @param int $user_id User ID who submitted the rating
     */
    public function notify_rating_created($rating_id, $expert_id, $user_id) {
        // Get expert author (recipient)
        $expert_author_id = get_post_field('post_author', $expert_id);
        if (!$expert_author_id) {
            return;
        }

        $recipient = get_userdata($expert_author_id);
        if (!$recipient) {
            return;
        }

        // Get user name
        $user = get_userdata($user_id);
        $user_name = $user ? $user->display_name : 'En bruger';

        // Get expert name
        $expert_name = get_the_title($expert_id);

        // Get rating details
        global $wpdb;
        $table = RFM_Database::get_table_name('ratings');
        $rating = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $rating_id
        ));

        if (!$rating) {
            return;
        }

        // Email subject
        $subject = sprintf(
            __('Ny bedømmelse på %s', 'rigtig-for-mig'),
            $expert_name
        );

        // Email message
        $message = sprintf(
            __("Hej %s,\n\nDin ekspert-profil '%s' har modtaget en ny bedømmelse fra %s.\n\nBedømmelse: %d/5 stjerner\nKommentar: %s\n\nLog ind på dit dashboard for at se alle dine bedømmelser:\n%s\n\nMed venlig hilsen,\n%s", 'rigtig-for-mig'),
            $recipient->display_name,
            $expert_name,
            $user_name,
            $rating->rating,
            !empty($rating->comment) ? $rating->comment : 'Ingen kommentar',
            home_url('/ekspert-dashboard/'),
            get_bloginfo('name')
        );

        // Email headers
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        // Send email
        $sent = wp_mail($recipient->user_email, $subject, $message, $headers);

        if (!$sent) {
            error_log('RFM: Failed to send rating notification to ' . $recipient->user_email);
        }

        return $sent;
    }
}
