<?php
/**
 * Upload Manager - Isolated Upload Handling for Experts & Users
 *
 * Handles custom upload directories and automatic cleanup for RFM uploads
 *
 * @package Rigtig_For_Mig
 * @since 3.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Upload_Manager {

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
        // Custom upload directory
        add_filter('upload_dir', array($this, 'custom_upload_directory'));

        // Tag attachments with owner info
        add_action('add_attachment', array($this, 'tag_attachment_owner'));

        // Auto-delete attachments when post is deleted
        add_action('before_delete_post', array($this, 'delete_post_attachments'), 10, 2);
        add_action('wp_trash_post', array($this, 'trash_post_attachments'));

        // Auto-delete attachments when user is deleted
        add_action('delete_user', array($this, 'delete_user_attachments'), 10, 3);

        // Filter Media Library (hide RFM uploads from standard view)
        add_filter('ajax_query_attachments_args', array($this, 'filter_media_library'));

        // Add custom columns to Media Library for RFM uploads
        add_filter('manage_media_columns', array($this, 'add_media_columns'));
        add_action('manage_media_custom_column', array($this, 'render_media_column'), 10, 2);
    }

    /**
     * Set custom upload directory for Experts and Users
     *
     * Creates isolated directories: /rfm/users/{user_id}/ and /rfm/experts/{expert_id}/
     *
     * @param array $dirs WordPress upload directories
     * @return array Modified upload directories
     */
    public function custom_upload_directory($dirs) {
        // Check if this is a user avatar upload via AJAX
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

            // User avatar upload from frontend dashboard
            if ($action === 'rfm_upload_user_avatar') {
                if (is_user_logged_in()) {
                    $user_id = get_current_user_id();
                    $custom_dir = '/rfm/users/' . $user_id;
                    $dirs['path'] = $dirs['basedir'] . $custom_dir;
                    $dirs['url'] = $dirs['baseurl'] . $custom_dir;
                    $dirs['subdir'] = $custom_dir;

                    error_log("RFM Upload: Redirecting user {$user_id} avatar upload to {$dirs['path']}");
                    return $dirs;
                }
            }

            // Expert profile image and banner upload
            if ($action === 'rfm_upload_expert_avatar' || $action === 'rfm_upload_expert_banner' || $action === 'rfm_upload_expert_image') {
                $post_id = $this->get_current_post_id();
                if ($post_id) {
                    $custom_dir = '/rfm/experts/' . $post_id;
                    $dirs['path'] = $dirs['basedir'] . $custom_dir;
                    $dirs['url'] = $dirs['baseurl'] . $custom_dir;
                    $dirs['subdir'] = $custom_dir;

                    error_log("RFM Upload: Redirecting expert {$post_id} upload to {$dirs['path']}");
                    return $dirs;
                }
            }
        }

        // Get current post being edited (admin context)
        $post_id = $this->get_current_post_id();

        if (!$post_id) {
            return $dirs;
        }

        $post_type = get_post_type($post_id);

        // Set custom directory based on post type
        if ($post_type === 'rfm_expert') {
            $custom_dir = '/rfm/experts/' . $post_id;
            $dirs['path'] = $dirs['basedir'] . $custom_dir;
            $dirs['url'] = $dirs['baseurl'] . $custom_dir;
            $dirs['subdir'] = $custom_dir;

            error_log("RFM Upload: Redirecting expert post {$post_id} upload to {$dirs['path']}");

        } elseif ($post_type === 'rfm_bruger') {
            $custom_dir = '/rfm/brugere/' . $post_id;
            $dirs['path'] = $dirs['basedir'] . $custom_dir;
            $dirs['url'] = $dirs['baseurl'] . $custom_dir;
            $dirs['subdir'] = $custom_dir;

            error_log("RFM Upload: Redirecting bruger post {$post_id} upload to {$dirs['path']}");
        }

        return $dirs;
    }

    /**
     * Get current post ID from various contexts
     *
     * @return int|false Post ID or false
     */
    private function get_current_post_id() {
        // Check $_POST for expert_id (new dashboard AJAX handlers)
        if (isset($_POST['expert_id']) && intval($_POST['expert_id']) > 0) {
            return intval($_POST['expert_id']);
        }

        // Check $_POST for post_id (media upload via meta box)
        if (isset($_POST['post_id']) && intval($_POST['post_id']) > 0) {
            return intval($_POST['post_id']);
        }

        // Check $_REQUEST for post_id
        if (isset($_REQUEST['post_id']) && intval($_REQUEST['post_id']) > 0) {
            return intval($_REQUEST['post_id']);
        }

        // Check global $post
        global $post;
        if (isset($post->ID)) {
            return $post->ID;
        }

        // Check if we're in admin editing a post
        if (is_admin() && isset($_GET['post'])) {
            return intval($_GET['post']);
        }

        return false;
    }

    /**
     * Tag attachment with owner information
     *
     * @param int $attachment_id Attachment post ID
     */
    public function tag_attachment_owner($attachment_id) {
        // Check if this is a user avatar upload
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

            if ($action === 'rfm_upload_user_avatar' && is_user_logged_in()) {
                $user_id = get_current_user_id();
                update_post_meta($attachment_id, '_rfm_owner_type', 'user');
                update_post_meta($attachment_id, '_rfm_owner_id', $user_id);
                update_post_meta($attachment_id, '_rfm_upload_type', 'avatar');
                update_post_meta($attachment_id, '_rfm_upload_date', current_time('mysql'));

                error_log("RFM Upload: Tagged user avatar $attachment_id for user $user_id");
                return;
            }

            // Check if this is an expert avatar or banner upload
            if ($action === 'rfm_upload_expert_avatar' || $action === 'rfm_upload_expert_banner') {
                $expert_id = isset($_POST['expert_id']) ? intval($_POST['expert_id']) : 0;
                if ($expert_id > 0) {
                    $upload_type = ($action === 'rfm_upload_expert_banner') ? 'banner' : 'avatar';
                    update_post_meta($attachment_id, '_rfm_owner_type', 'rfm_expert');
                    update_post_meta($attachment_id, '_rfm_owner_id', $expert_id);
                    update_post_meta($attachment_id, '_rfm_upload_type', $upload_type);
                    update_post_meta($attachment_id, '_rfm_upload_date', current_time('mysql'));

                    error_log("RFM Upload: Tagged expert $upload_type $attachment_id for expert $expert_id");
                    return;
                }
            }
        }

        // Check for post-based uploads
        $parent_id = wp_get_post_parent_id($attachment_id);

        if (!$parent_id) {
            return;
        }

        $parent_type = get_post_type($parent_id);

        // Only tag RFM-related uploads
        if (in_array($parent_type, array('rfm_expert', 'rfm_bruger'))) {
            update_post_meta($attachment_id, '_rfm_owner_type', $parent_type);
            update_post_meta($attachment_id, '_rfm_owner_id', $parent_id);
            update_post_meta($attachment_id, '_rfm_upload_date', current_time('mysql'));

            error_log("RFM Upload: Tagged attachment $attachment_id with owner type=$parent_type, id=$parent_id");
        }
    }

    /**
     * Delete all attachments when parent post is permanently deleted
     *
     * @param int $post_id Post ID being deleted
     * @param WP_Post $post Post object
     */
    public function delete_post_attachments($post_id, $post) {
        if (!$post) {
            return;
        }

        $post_type = $post->post_type;

        // Only handle RFM post types
        if (!in_array($post_type, array('rfm_expert', 'rfm_bruger'))) {
            return;
        }

        error_log("RFM Upload: Deleting attachments for $post_type ID $post_id");

        // Find all attachments that belong to this post
        $attachments = get_posts(array(
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => array(
                array(
                    'key'     => '_rfm_owner_id',
                    'value'   => $post_id,
                    'compare' => '='
                )
            )
        ));

        $deleted_count = 0;

        foreach ($attachments as $attachment) {
            // Force delete attachment and files
            $deleted = wp_delete_attachment($attachment->ID, true);

            if ($deleted) {
                $deleted_count++;
                error_log("RFM Upload: Deleted attachment {$attachment->ID} ({$attachment->post_title})");
            } else {
                error_log("RFM Upload: Failed to delete attachment {$attachment->ID}");
            }
        }

        error_log("RFM Upload: Deleted $deleted_count attachments for $post_type ID $post_id");
    }

    /**
     * Trash attachments when parent post is trashed
     *
     * @param int $post_id Post ID being trashed
     */
    public function trash_post_attachments($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return;
        }

        $post_type = $post->post_type;

        // Only handle RFM post types
        if (!in_array($post_type, array('rfm_expert', 'rfm_bruger'))) {
            return;
        }

        error_log("RFM Upload: Trashing attachments for $post_type ID $post_id");

        // Find all attachments
        $attachments = get_posts(array(
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => array(
                array(
                    'key'     => '_rfm_owner_id',
                    'value'   => $post_id,
                    'compare' => '='
                )
            )
        ));

        foreach ($attachments as $attachment) {
            wp_trash_post($attachment->ID);
            error_log("RFM Upload: Trashed attachment {$attachment->ID}");
        }
    }

    /**
     * Delete all attachments when user is deleted
     *
     * @param int $user_id User ID being deleted
     * @param int $reassign ID of user to reassign posts to
     * @param WP_User $user User object
     */
    public function delete_user_attachments($user_id, $reassign, $user) {
        error_log("RFM Upload: Deleting attachments for user ID $user_id");

        // Find all attachments that belong to this user
        $attachments = get_posts(array(
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => array(
                array(
                    'key'     => '_rfm_owner_type',
                    'value'   => 'user',
                    'compare' => '='
                ),
                array(
                    'key'     => '_rfm_owner_id',
                    'value'   => $user_id,
                    'compare' => '='
                )
            )
        ));

        $deleted_count = 0;

        foreach ($attachments as $attachment) {
            // Force delete attachment and files
            $deleted = wp_delete_attachment($attachment->ID, true);

            if ($deleted) {
                $deleted_count++;
                error_log("RFM Upload: Deleted user attachment {$attachment->ID} ({$attachment->post_title})");
            } else {
                error_log("RFM Upload: Failed to delete user attachment {$attachment->ID}");
            }
        }

        // Also delete the user's upload directory if it exists
        $upload_dir = wp_upload_dir();
        $user_dir = $upload_dir['basedir'] . '/rfm/users/' . $user_id;

        if (is_dir($user_dir)) {
            $this->delete_directory_recursive($user_dir);
            error_log("RFM Upload: Deleted user directory: $user_dir");
        }

        error_log("RFM Upload: Deleted $deleted_count attachments for user ID $user_id");
    }

    /**
     * Recursively delete a directory and all its contents
     *
     * @param string $dir Directory path
     * @return bool Success
     */
    private function delete_directory_recursive($dir) {
        if (!is_dir($dir)) {
            return false;
        }

        $items = array_diff(scandir($dir), array('.', '..'));

        foreach ($items as $item) {
            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->delete_directory_recursive($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * Filter Media Library to hide RFM uploads from standard view
     *
     * @param array $query Query arguments
     * @return array Modified query
     */
    public function filter_media_library($query) {
        // Only filter if not specifically viewing RFM uploads
        if (isset($_REQUEST['rfm_uploads']) && $_REQUEST['rfm_uploads'] === 'show') {
            // Show ONLY RFM uploads
            $query['meta_query'] = array(
                array(
                    'key'     => '_rfm_owner_type',
                    'compare' => 'EXISTS'
                )
            );
        } else {
            // Hide RFM uploads from standard view
            $query['meta_query'] = array(
                array(
                    'key'     => '_rfm_owner_type',
                    'compare' => 'NOT EXISTS'
                )
            );
        }

        return $query;
    }

    /**
     * Add custom columns to Media Library
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_media_columns($columns) {
        $columns['rfm_owner'] = __('RFM Ejer', 'rigtig-for-mig');
        return $columns;
    }

    /**
     * Render custom column content
     *
     * @param string $column_name Column name
     * @param int $attachment_id Attachment ID
     */
    public function render_media_column($column_name, $attachment_id) {
        if ($column_name !== 'rfm_owner') {
            return;
        }

        $owner_type = get_post_meta($attachment_id, '_rfm_owner_type', true);
        $owner_id = get_post_meta($attachment_id, '_rfm_owner_id', true);

        if (!$owner_type || !$owner_id) {
            echo '<span style="color: #999;">—</span>';
            return;
        }

        $owner_post = get_post($owner_id);

        if (!$owner_post) {
            echo '<span style="color: #d63638;">Slettet</span>';
            return;
        }

        $type_label = $owner_type === 'rfm_expert' ? 'Ekspert' : 'Bruger';
        $edit_link = get_edit_post_link($owner_id);

        echo '<strong>' . esc_html($type_label) . ':</strong><br>';
        echo '<a href="' . esc_url($edit_link) . '" target="_blank">';
        echo esc_html($owner_post->post_title);
        echo '</a>';
    }

    /**
     * Get all attachments for a specific post
     *
     * @param int $post_id Post ID
     * @return array Array of attachment posts
     */
    public static function get_post_attachments($post_id) {
        return get_posts(array(
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => array(
                array(
                    'key'     => '_rfm_owner_id',
                    'value'   => $post_id,
                    'compare' => '='
                )
            )
        ));
    }

    /**
     * Get upload statistics
     *
     * @return array Statistics array
     */
    public static function get_upload_stats() {
        global $wpdb;

        $expert_uploads = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_rfm_owner_type'
            AND meta_value = 'rfm_expert'
        ");

        $user_uploads = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_rfm_owner_type'
            AND meta_value = 'rfm_bruger'
        ");

        return array(
            'expert_uploads' => intval($expert_uploads),
            'user_uploads'   => intval($user_uploads),
            'total_uploads'  => intval($expert_uploads) + intval($user_uploads)
        );
    }
}
