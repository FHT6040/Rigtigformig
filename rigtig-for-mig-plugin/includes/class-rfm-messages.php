<?php
/**
 * RFM Messages System
 *
 * Handles messaging between users and experts
 *
 * @package Rigtig_For_Mig
 * @since 3.8.29
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Messages {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // AJAX handlers - will be routed through ajax-handler.php
        // No need for add_action here as ajax-handler.php handles routing
    }

    /**
     * Send a message from user to expert
     *
     * @param int $sender_id User ID sending the message
     * @param int $expert_id Expert post ID
     * @param string $subject Message subject
     * @param string $message Message content
     * @return int|false Message ID on success, false on failure
     */
    public function send_message($sender_id, $expert_id, $subject, $message) {
        global $wpdb;
        $table = RFM_Database::get_table_name('messages');

        // Get expert author ID (recipient)
        $expert_author_id = get_post_field('post_author', $expert_id);

        if (!$expert_author_id) {
            return false;
        }

        $result = $wpdb->insert(
            $table,
            array(
                'sender_id' => $sender_id,
                'recipient_id' => $expert_author_id,
                'expert_id' => $expert_id,
                'subject' => $subject,
                'message' => $message,
                'is_read' => 0
            ),
            array('%d', '%d', '%d', '%s', '%s', '%d')
        );

        if ($result) {
            // Update or create message thread
            $this->update_thread($sender_id, $expert_id);

            // Trigger action for notifications
            do_action('rfm_message_sent', $wpdb->insert_id, $sender_id, $expert_author_id, $expert_id);

            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get messages for a user (inbox)
     *
     * @param int $user_id User ID
     * @param string $box 'inbox' or 'sent'
     * @param int $limit Number of messages to retrieve
     * @param int $offset Offset for pagination
     * @return array Messages
     */
    public function get_messages($user_id, $box = 'inbox', $limit = 20, $offset = 0) {
        global $wpdb;
        $table = RFM_Database::get_table_name('messages');

        $field = ($box === 'inbox') ? 'recipient_id' : 'sender_id';

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*,
                    u.display_name as sender_name,
                    p.post_title as expert_name
             FROM $table m
             LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
             LEFT JOIN {$wpdb->posts} p ON m.expert_id = p.ID
             WHERE m.$field = %d
             ORDER BY m.created_at DESC
             LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        ));

        return $messages;
    }

    /**
     * Get conversation between user and expert
     *
     * @param int $user_id User ID
     * @param int $expert_id Expert post ID
     * @return array Messages in the conversation
     */
    public function get_conversation($user_id, $expert_id) {
        global $wpdb;
        $table = RFM_Database::get_table_name('messages');

        $expert_author_id = get_post_field('post_author', $expert_id);

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*,
                    u.display_name as sender_name
             FROM $table m
             LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE m.expert_id = %d
             AND ((m.sender_id = %d AND m.recipient_id = %d)
                  OR (m.sender_id = %d AND m.recipient_id = %d))
             ORDER BY m.created_at ASC",
            $expert_id,
            $user_id,
            $expert_author_id,
            $expert_author_id,
            $user_id
        ));

        return $messages;
    }

    /**
     * Mark message as read
     *
     * @param int $message_id Message ID
     * @param int $user_id User ID (to verify ownership)
     * @return bool Success
     */
    public function mark_as_read($message_id, $user_id) {
        global $wpdb;
        $table = RFM_Database::get_table_name('messages');

        $result = $wpdb->update(
            $table,
            array('is_read' => 1),
            array(
                'id' => $message_id,
                'recipient_id' => $user_id
            ),
            array('%d'),
            array('%d', '%d')
        );

        return $result !== false;
    }

    /**
     * Get unread message count for user
     *
     * @param int $user_id User ID
     * @return int Unread count
     */
    public function get_unread_count($user_id) {
        global $wpdb;
        $table = RFM_Database::get_table_name('messages');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE recipient_id = %d AND is_read = 0",
            $user_id
        ));

        return intval($count);
    }

    /**
     * Delete a message
     *
     * @param int $message_id Message ID
     * @param int $user_id User ID (to verify ownership)
     * @return bool Success
     */
    public function delete_message($message_id, $user_id) {
        global $wpdb;
        $table = RFM_Database::get_table_name('messages');

        // Verify user owns this message (either sender or recipient)
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d
             AND (sender_id = %d OR recipient_id = %d)",
            $message_id,
            $user_id,
            $user_id
        ));

        if (!$message) {
            return false;
        }

        $result = $wpdb->delete(
            $table,
            array('id' => $message_id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Mark all messages as read for a user
     *
     * @param int $user_id User ID
     * @return bool Success
     */
    public function mark_all_as_read($user_id) {
        global $wpdb;
        $table = RFM_Database::get_table_name('messages');

        $result = $wpdb->update(
            $table,
            array('is_read' => 1),
            array('recipient_id' => $user_id, 'is_read' => 0),
            array('%d'),
            array('%d', '%d')
        );

        return $result !== false;
    }

    /**
     * Update message thread timestamp
     *
     * @param int $user_id User ID
     * @param int $expert_id Expert post ID
     */
    private function update_thread($user_id, $expert_id) {
        global $wpdb;
        $table = RFM_Database::get_table_name('message_threads');

        // Check if thread exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND expert_id = %d",
            $user_id,
            $expert_id
        ));

        if ($existing) {
            // Update timestamp
            $wpdb->update(
                $table,
                array('last_message_at' => current_time('mysql')),
                array('id' => $existing->id),
                array('%s'),
                array('%d')
            );
        } else {
            // Create new thread
            $wpdb->insert(
                $table,
                array(
                    'user_id' => $user_id,
                    'expert_id' => $expert_id,
                    'last_message_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s')
            );
        }
    }

    /**
     * Get active conversations for a user
     *
     * @param int $user_id User ID
     * @param string $type 'user' or 'expert'
     * @return array Conversations
     */
    public function get_conversations($user_id, $type = 'user') {
        global $wpdb;
        $messages_table = RFM_Database::get_table_name('messages');

        if ($type === 'expert') {
            // For experts: get all conversations grouped by expert_id and the OTHER user
            // Use CASE to identify the counterpart user (the other person in the conversation)
            $conversations = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    conv.expert_id,
                    conv.counterpart_user_id as user_id,
                    u.display_name as user_name,
                    p.post_title as expert_name,
                    conv.last_message_at,
                    conv.unread_count,
                    last_msg.message as last_message
                FROM (
                    SELECT
                        m.expert_id,
                        CASE
                            WHEN m.sender_id = %d THEN m.recipient_id
                            ELSE m.sender_id
                        END as counterpart_user_id,
                        MAX(m.created_at) as last_message_at,
                        SUM(CASE WHEN m.is_read = 0 AND m.recipient_id = %d THEN 1 ELSE 0 END) as unread_count
                    FROM $messages_table m
                    WHERE m.sender_id = %d OR m.recipient_id = %d
                    GROUP BY m.expert_id, CASE WHEN m.sender_id = %d THEN m.recipient_id ELSE m.sender_id END
                ) as conv
                LEFT JOIN {$wpdb->users} u ON conv.counterpart_user_id = u.ID
                LEFT JOIN {$wpdb->posts} p ON conv.expert_id = p.ID
                LEFT OUTER JOIN (
                    SELECT
                        m2.expert_id,
                        CASE
                            WHEN m2.sender_id = %d THEN m2.recipient_id
                            ELSE m2.sender_id
                        END as counterpart_user_id,
                        m2.message
                    FROM $messages_table m2
                    INNER JOIN (
                        SELECT
                            m3.expert_id,
                            CASE
                                WHEN m3.sender_id = %d THEN m3.recipient_id
                                ELSE m3.sender_id
                            END as counterpart_user_id,
                            MAX(m3.created_at) as max_created_at
                        FROM $messages_table m3
                        WHERE m3.sender_id = %d OR m3.recipient_id = %d
                        GROUP BY m3.expert_id, counterpart_user_id
                    ) latest ON m2.expert_id = latest.expert_id
                        AND CASE
                            WHEN m2.sender_id = %d THEN m2.recipient_id
                            ELSE m2.sender_id
                        END = latest.counterpart_user_id
                        AND m2.created_at = latest.max_created_at
                    WHERE m2.sender_id = %d OR m2.recipient_id = %d
                ) last_msg ON conv.expert_id = last_msg.expert_id
                    AND conv.counterpart_user_id = last_msg.counterpart_user_id
                ORDER BY conv.last_message_at DESC",
                $user_id, // CASE sender check
                $user_id, // unread count
                $user_id, // WHERE sender
                $user_id, // WHERE recipient
                $user_id, // GROUP BY CASE sender check
                $user_id, // last message CASE
                $user_id, // last message CASE inner
                $user_id, // last message WHERE
                $user_id, // last message WHERE
                $user_id, // last message CASE join
                $user_id, // last message WHERE
                $user_id  // last message WHERE
            ));

            // Attach all messages to each conversation
            foreach ($conversations as &$conv) {
                $conv->messages = $wpdb->get_results($wpdb->prepare(
                    "SELECT m.*, u.display_name as sender_name
                     FROM $messages_table m
                     LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
                     WHERE m.expert_id = %d
                     AND ((m.sender_id = %d AND m.recipient_id = %d)
                          OR (m.sender_id = %d AND m.recipient_id = %d))
                     ORDER BY m.created_at ASC",
                    $conv->expert_id,
                    $conv->user_id,
                    $user_id,
                    $user_id,
                    $conv->user_id
                ));
                $conv->message_count = count($conv->messages);
            }
        } else {
            // For users: get all conversations grouped by expert_id
            // The counterpart is always the expert's author
            $conversations = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    conv.expert_id,
                    conv.counterpart_user_id as expert_author_id,
                    p.post_title as expert_name,
                    conv.last_message_at,
                    conv.unread_count,
                    last_msg.message as last_message
                FROM (
                    SELECT
                        m.expert_id,
                        CASE
                            WHEN m.sender_id = %d THEN m.recipient_id
                            ELSE m.sender_id
                        END as counterpart_user_id,
                        MAX(m.created_at) as last_message_at,
                        SUM(CASE WHEN m.is_read = 0 AND m.recipient_id = %d THEN 1 ELSE 0 END) as unread_count
                    FROM $messages_table m
                    WHERE m.sender_id = %d OR m.recipient_id = %d
                    GROUP BY m.expert_id, CASE WHEN m.sender_id = %d THEN m.recipient_id ELSE m.sender_id END
                ) as conv
                LEFT JOIN {$wpdb->posts} p ON conv.expert_id = p.ID
                LEFT OUTER JOIN (
                    SELECT
                        m2.expert_id,
                        CASE
                            WHEN m2.sender_id = %d THEN m2.recipient_id
                            ELSE m2.sender_id
                        END as counterpart_user_id,
                        m2.message
                    FROM $messages_table m2
                    INNER JOIN (
                        SELECT
                            m3.expert_id,
                            CASE
                                WHEN m3.sender_id = %d THEN m3.recipient_id
                                ELSE m3.sender_id
                            END as counterpart_user_id,
                            MAX(m3.created_at) as max_created_at
                        FROM $messages_table m3
                        WHERE m3.sender_id = %d OR m3.recipient_id = %d
                        GROUP BY m3.expert_id, counterpart_user_id
                    ) latest ON m2.expert_id = latest.expert_id
                        AND CASE
                            WHEN m2.sender_id = %d THEN m2.recipient_id
                            ELSE m2.sender_id
                        END = latest.counterpart_user_id
                        AND m2.created_at = latest.max_created_at
                    WHERE m2.sender_id = %d OR m2.recipient_id = %d
                ) last_msg ON conv.expert_id = last_msg.expert_id
                    AND conv.counterpart_user_id = last_msg.counterpart_user_id
                ORDER BY conv.last_message_at DESC",
                $user_id, // CASE sender check
                $user_id, // unread count
                $user_id, // WHERE sender
                $user_id, // WHERE recipient
                $user_id, // GROUP BY CASE sender check
                $user_id, // last message CASE
                $user_id, // last message CASE inner
                $user_id, // last message WHERE
                $user_id, // last message WHERE
                $user_id, // last message CASE join
                $user_id, // last message WHERE
                $user_id  // last message WHERE
            ));

            // Attach all messages to each conversation
            foreach ($conversations as &$conv) {
                $expert_author_id = get_post_field('post_author', $conv->expert_id);
                $conv->messages = $wpdb->get_results($wpdb->prepare(
                    "SELECT m.*, u.display_name as sender_name
                     FROM $messages_table m
                     LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
                     WHERE m.expert_id = %d
                     AND ((m.sender_id = %d AND m.recipient_id = %d)
                          OR (m.sender_id = %d AND m.recipient_id = %d))
                     ORDER BY m.created_at ASC",
                    $conv->expert_id,
                    $user_id,
                    $expert_author_id,
                    $expert_author_id,
                    $user_id
                ));
                $conv->message_count = count($conv->messages);
            }
        }

        return $conversations;
    }
}
