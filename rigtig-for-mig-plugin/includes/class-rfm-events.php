<?php
/**
 * Events System (Kurser & Events)
 *
 * Handles event management: post type admin, expert dashboard tab with
 * subscription-based limits, admin approval workflow, file/image uploads,
 * shortcodes with filters, social sharing, and auto-expiry of past events.
 *
 * Event limits (placeholder values — change per plan as needed):
 * - Gratis: 0 events
 * - Standard: 2 events/month
 * - Premium: 5 events/month
 *
 * Approval workflow (mirrors article system):
 * - Expert submits event -> status 'pending'
 * - Admin approves -> status 'publish'
 * - Admin rejects -> status 'draft' with _rfm_event_rejected meta
 * - Rejected events do NOT count toward quota
 *
 * @package Rigtig_For_Mig
 * @since 3.14.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Events {

    private static $instance = null;

    /**
     * Event limits per subscription tier (per month).
     * Placeholder values — adjust as needed.
     */
    const LIMITS = array(
        'free'     => 0,
        'standard' => 2,
        'premium'  => 5,
    );

    /** Max months into the future an event can be created */
    const MAX_FUTURE_MONTHS = 6;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Admin meta boxes
        add_action('add_meta_boxes', array($this, 'add_event_meta_boxes'));
        add_action('save_post_rfm_event', array($this, 'save_event_meta'), 10, 2);

        // Admin columns
        add_filter('manage_rfm_event_posts_columns', array($this, 'add_event_columns'));
        add_action('manage_rfm_event_posts_custom_column', array($this, 'render_event_columns'), 10, 2);
        add_filter('manage_edit-rfm_event_sortable_columns', array($this, 'sortable_event_columns'));
        add_action('pre_get_posts', array($this, 'sort_events_by_date'));

        // Admin approval
        add_action('admin_post_rfm_approve_event', array($this, 'handle_approve_event'));
        add_action('admin_post_rfm_reject_event', array($this, 'handle_reject_event'));
        add_action('admin_notices', array($this, 'pending_events_notice'));

        // Single template
        add_filter('single_template', array($this, 'event_template'));

        // Include events in category archives
        add_action('pre_get_posts', array($this, 'include_events_in_queries'));

        // Shortcodes
        add_shortcode('rfm_events_page', array($this, 'events_page_shortcode'));

        // AJAX handlers
        add_action('wp_ajax_rfm_save_event', array($this, 'ajax_save_event'));
        add_action('wp_ajax_rfm_delete_event', array($this, 'ajax_delete_event'));
        add_action('wp_ajax_rfm_upload_event_image', array($this, 'ajax_upload_event_image'));
        add_action('wp_ajax_rfm_upload_event_file', array($this, 'ajax_upload_event_file'));

        // Auto-expiry cron
        add_action('rfm_cleanup_expired_events', array($this, 'cleanup_expired_events'));
        if (!wp_next_scheduled('rfm_cleanup_expired_events')) {
            wp_schedule_event(time(), 'daily', 'rfm_cleanup_expired_events');
        }
    }

    // =========================================================================
    // QUOTA SYSTEM
    // =========================================================================

    public static function get_limit($plan) {
        $limits = self::LIMITS;
        return isset($limits[$plan]) ? $limits[$plan] : 0;
    }

    public static function get_period_type($plan) {
        // All plans use monthly periods for events
        return ($plan === 'free') ? 'none' : 'month';
    }

    /**
     * Count events used by expert in current period.
     * Counts published + pending (rejected do NOT count).
     */
    public function count_events_in_period($expert_id, $plan) {
        if (self::get_period_type($plan) === 'none') {
            return 0;
        }

        $args = array(
            'post_type'      => 'rfm_event',
            'post_status'    => array('publish', 'pending'),
            'meta_query'     => array(
                array(
                    'key'   => '_rfm_event_expert_id',
                    'value' => $expert_id,
                ),
            ),
            'date_query'     => array(
                array(
                    'year'  => date('Y'),
                    'month' => date('n'),
                ),
            ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );

        $query = new WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Check if expert can create a new event.
     */
    public function can_create_event($expert_id) {
        $plan  = RFM_Subscriptions::get_instance()->get_expert_plan($expert_id);
        $limit = self::get_limit($plan);

        if ($limit === 0) {
            return array(
                'allowed' => false,
                'reason'  => __('Dit abonnement tillader ikke events. Opgrader til Standard eller Premium.', 'rigtig-for-mig'),
                'used'    => 0,
                'limit'   => 0,
                'plan'    => $plan,
            );
        }

        $used = $this->count_events_in_period($expert_id, $plan);

        if ($used >= $limit) {
            return array(
                'allowed' => false,
                'reason'  => sprintf(
                    __('Du har brugt alle dine events denne måned (%d af %d).', 'rigtig-for-mig'),
                    $used,
                    $limit
                ),
                'used'    => $used,
                'limit'   => $limit,
                'plan'    => $plan,
            );
        }

        return array(
            'allowed' => true,
            'reason'  => '',
            'used'    => $used,
            'limit'   => $limit,
            'plan'    => $plan,
        );
    }

    // =========================================================================
    // QUERY HELPERS
    // =========================================================================

    public function get_expert_events($expert_id, $status = 'any', $limit = -1) {
        return get_posts(array(
            'post_type'      => 'rfm_event',
            'post_status'    => $status,
            'meta_query'     => array(
                array(
                    'key'   => '_rfm_event_expert_id',
                    'value' => $expert_id,
                ),
            ),
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));
    }

    // =========================================================================
    // STATUS HELPERS
    // =========================================================================

    public static function get_status_label($status, $event_id = 0) {
        if ($status === 'draft' && $event_id) {
            if (get_post_meta($event_id, '_rfm_event_rejected', true)) {
                return __('Afvist', 'rigtig-for-mig');
            }
        }
        switch ($status) {
            case 'publish': return __('Publiceret', 'rigtig-for-mig');
            case 'pending': return __('Afventer godkendelse', 'rigtig-for-mig');
            case 'draft':   return __('Kladde', 'rigtig-for-mig');
            default:        return ucfirst($status);
        }
    }

    public static function get_status_color($status, $event_id = 0) {
        if ($status === 'draft' && $event_id && get_post_meta($event_id, '_rfm_event_rejected', true)) {
            return '#e74c3c';
        }
        switch ($status) {
            case 'publish': return '#27ae60';
            case 'pending': return '#f39c12';
            case 'draft':   return '#95a5a6';
            default:        return '#666';
        }
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    /**
     * Save event via AJAX (create or update) from expert dashboard.
     */
    public function ajax_save_event() {
        check_ajax_referer('rfm_dashboard_tabbed', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind.', 'rigtig-for-mig')));
        }

        $user_id    = get_current_user_id();
        $expert_id  = intval($_POST['expert_id'] ?? 0);
        $event_id   = intval($_POST['event_id'] ?? 0);

        // Verify ownership
        $expert_post = get_post($expert_id);
        if (!$expert_post || $expert_post->post_author != $user_id) {
            wp_send_json_error(array('message' => __('Du har ikke tilladelse.', 'rigtig-for-mig')));
        }

        // Sanitize input
        $title       = sanitize_text_field($_POST['event_title'] ?? '');
        $content     = wp_kses_post($_POST['event_content'] ?? '');
        $category_id = intval($_POST['event_category'] ?? 0);
        $image_id    = intval($_POST['event_image_id'] ?? 0);
        $file_id     = intval($_POST['event_file_id'] ?? 0);
        $date        = sanitize_text_field($_POST['event_date'] ?? '');
        $time_start  = sanitize_text_field($_POST['event_time_start'] ?? '');
        $time_end    = sanitize_text_field($_POST['event_time_end'] ?? '');
        $location    = sanitize_text_field($_POST['event_location'] ?? '');
        $price       = sanitize_text_field($_POST['event_price'] ?? '');
        $event_url   = esc_url_raw($_POST['event_url'] ?? '');
        $format      = sanitize_text_field($_POST['event_format'] ?? '');
        $event_type  = intval($_POST['event_type'] ?? 0);
        $audience    = intval($_POST['event_audience'] ?? 0);
        $what_you_get = wp_kses_post($_POST['event_what_you_get'] ?? '');
        $who_for      = wp_kses_post($_POST['event_who_for'] ?? '');
        $who_not_for  = wp_kses_post($_POST['event_who_not_for'] ?? '');

        // Required fields
        if (empty($title)) {
            wp_send_json_error(array('message' => __('Eventet skal have en titel.', 'rigtig-for-mig')));
        }
        if (empty($content)) {
            wp_send_json_error(array('message' => __('Eventet skal have en beskrivelse.', 'rigtig-for-mig')));
        }
        if (empty($date)) {
            wp_send_json_error(array('message' => __('Eventet skal have en dato.', 'rigtig-for-mig')));
        }
        if (!$category_id) {
            wp_send_json_error(array('message' => __('Vælg en kategori for eventet.', 'rigtig-for-mig')));
        }

        // Validate date not too far in future
        $max_date = date('Y-m-d', strtotime('+' . self::MAX_FUTURE_MONTHS . ' months'));
        if ($date > $max_date) {
            wp_send_json_error(array('message' => sprintf(
                __('Eventet kan maksimalt ligge %d måneder ude i fremtiden.', 'rigtig-for-mig'),
                self::MAX_FUTURE_MONTHS
            )));
        }

        // Verify expert has this category
        $expert_categories = wp_get_object_terms($expert_id, 'rfm_category', array('fields' => 'ids'));
        if (!in_array($category_id, $expert_categories)) {
            wp_send_json_error(array('message' => __('Du kan kun oprette events i dine egne kategorier.', 'rigtig-for-mig')));
        }

        // UPDATE existing event
        if ($event_id) {
            $existing = get_post($event_id);
            if (!$existing || get_post_meta($event_id, '_rfm_event_expert_id', true) != $expert_id) {
                wp_send_json_error(array('message' => __('Eventet blev ikke fundet.', 'rigtig-for-mig')));
            }

            $was_published = ($existing->post_status === 'publish');
            $title_changed = ($existing->post_title !== $title);
            $new_status    = ($was_published && $title_changed) ? 'pending' : $existing->post_status;

            wp_update_post(array(
                'ID'           => $event_id,
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => $new_status,
            ));

            $this->save_event_fields($event_id, compact(
                'date', 'time_start', 'time_end', 'location', 'price', 'event_url',
                'format', 'what_you_get', 'who_for', 'who_not_for',
                'category_id', 'event_type', 'audience', 'image_id', 'file_id'
            ));

            delete_post_meta($event_id, '_rfm_event_rejected');
            delete_post_meta($event_id, '_rfm_event_reject_reason');

            $message = ($new_status === 'pending' && $was_published)
                ? __('Eventet er opdateret og sendt til godkendelse igen.', 'rigtig-for-mig')
                : __('Eventet er opdateret.', 'rigtig-for-mig');

            wp_send_json_success(array('message' => $message, 'event_id' => $event_id, 'status' => $new_status));
        }

        // CREATE new event
        $can_create = $this->can_create_event($expert_id);
        if (!$can_create['allowed']) {
            wp_send_json_error(array('message' => $can_create['reason']));
        }

        $event_id = wp_insert_post(array(
            'post_type'    => 'rfm_event',
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'pending',
            'post_author'  => $user_id,
        ));

        if (is_wp_error($event_id)) {
            wp_send_json_error(array('message' => $event_id->get_error_message()));
        }

        update_post_meta($event_id, '_rfm_event_expert_id', $expert_id);
        update_post_meta($event_id, '_rfm_event_provider_type', 'expert');

        $this->save_event_fields($event_id, compact(
            'date', 'time_start', 'time_end', 'location', 'price', 'event_url',
            'format', 'what_you_get', 'who_for', 'who_not_for',
            'category_id', 'event_type', 'audience', 'image_id', 'file_id'
        ));

        wp_send_json_success(array(
            'message'  => __('Eventet er indsendt til godkendelse!', 'rigtig-for-mig'),
            'event_id' => $event_id,
            'status'   => 'pending',
        ));
    }

    /**
     * Helper: persist all event meta/taxonomy fields.
     */
    private function save_event_fields($event_id, $data) {
        update_post_meta($event_id, '_rfm_event_date', $data['date']);
        update_post_meta($event_id, '_rfm_event_time_start', $data['time_start']);
        update_post_meta($event_id, '_rfm_event_time_end', $data['time_end']);
        update_post_meta($event_id, '_rfm_event_location', $data['location']);
        update_post_meta($event_id, '_rfm_event_price', $data['price']);
        update_post_meta($event_id, '_rfm_event_url', $data['event_url']);
        update_post_meta($event_id, '_rfm_event_format', $data['format']);
        update_post_meta($event_id, '_rfm_event_what_you_get', $data['what_you_get']);
        update_post_meta($event_id, '_rfm_event_who_for', $data['who_for']);
        update_post_meta($event_id, '_rfm_event_who_not_for', $data['who_not_for']);

        wp_set_object_terms($event_id, array($data['category_id']), 'rfm_category');
        if ($data['event_type']) {
            wp_set_object_terms($event_id, array($data['event_type']), 'rfm_event_type');
        }
        if ($data['audience']) {
            wp_set_object_terms($event_id, array($data['audience']), 'rfm_event_audience');
        }

        if ($data['image_id']) {
            set_post_thumbnail($event_id, $data['image_id']);
        } else {
            delete_post_thumbnail($event_id);
        }

        if ($data['file_id']) {
            update_post_meta($event_id, '_rfm_event_file_id', $data['file_id']);
        } else {
            delete_post_meta($event_id, '_rfm_event_file_id');
        }
    }

    /**
     * Delete event via AJAX.
     */
    public function ajax_delete_event() {
        check_ajax_referer('rfm_dashboard_tabbed', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind.', 'rigtig-for-mig')));
        }

        $user_id   = get_current_user_id();
        $expert_id = intval($_POST['expert_id'] ?? 0);
        $event_id  = intval($_POST['event_id'] ?? 0);

        $expert_post = get_post($expert_id);
        if (!$expert_post || $expert_post->post_author != $user_id) {
            wp_send_json_error(array('message' => __('Du har ikke tilladelse.', 'rigtig-for-mig')));
        }

        if (get_post_meta($event_id, '_rfm_event_expert_id', true) != $expert_id) {
            wp_send_json_error(array('message' => __('Eventet blev ikke fundet.', 'rigtig-for-mig')));
        }

        wp_trash_post($event_id);
        wp_send_json_success(array('message' => __('Eventet er slettet.', 'rigtig-for-mig')));
    }

    /**
     * Upload event image via AJAX.
     */
    public function ajax_upload_event_image() {
        check_ajax_referer('rfm_dashboard_tabbed', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind.', 'rigtig-for-mig')));
        }

        $user_id   = get_current_user_id();
        $expert_id = intval($_POST['expert_id'] ?? 0);

        $expert_post = get_post($expert_id);
        if (!$expert_post || $expert_post->post_author != $user_id) {
            wp_send_json_error(array('message' => __('Du har ikke tilladelse.', 'rigtig-for-mig')));
        }

        if (empty($_FILES['event_image'])) {
            wp_send_json_error(array('message' => __('Ingen fil blev uploadet.', 'rigtig-for-mig')));
        }

        $file = $_FILES['event_image'];
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($real_mime, $allowed_types)) {
            wp_send_json_error(array('message' => __('Ugyldig filtype. Kun JPG, PNG, GIF og WebP er tilladt.', 'rigtig-for-mig')));
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('Filen er for stor. Maksimum 5MB.', 'rigtig-for-mig')));
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('event_image', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        update_post_meta($attachment_id, '_rfm_owner_type', 'rfm_event');
        update_post_meta($attachment_id, '_rfm_owner_id', $expert_id);
        update_post_meta($attachment_id, '_rfm_upload_type', 'event_image');
        update_post_meta($attachment_id, '_rfm_upload_date', current_time('mysql'));

        wp_send_json_success(array(
            'message'       => __('Billede uploadet!', 'rigtig-for-mig'),
            'attachment_id' => $attachment_id,
            'image_url'     => wp_get_attachment_image_url($attachment_id, 'medium'),
        ));
    }

    /**
     * Upload event file/brochure via AJAX.
     */
    public function ajax_upload_event_file() {
        check_ajax_referer('rfm_dashboard_tabbed', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du skal være logget ind.', 'rigtig-for-mig')));
        }

        $user_id   = get_current_user_id();
        $expert_id = intval($_POST['expert_id'] ?? 0);

        $expert_post = get_post($expert_id);
        if (!$expert_post || $expert_post->post_author != $user_id) {
            wp_send_json_error(array('message' => __('Du har ikke tilladelse.', 'rigtig-for-mig')));
        }

        if (empty($_FILES['event_file'])) {
            wp_send_json_error(array('message' => __('Ingen fil blev uploadet.', 'rigtig-for-mig')));
        }

        $file = $_FILES['event_file'];

        // Validate MIME type
        $allowed_types = array(
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg', 'image/png',
        );
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($real_mime, $allowed_types)) {
            wp_send_json_error(array('message' => __('Ugyldig filtype. Kun PDF, DOC, DOCX, JPG og PNG er tilladt.', 'rigtig-for-mig')));
        }

        // Validate extension
        $filename = sanitize_file_name($file['name']);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'))) {
            wp_send_json_error(array('message' => __('Ugyldig fil-extension.', 'rigtig-for-mig')));
        }

        // Max 10 MB
        if ($file['size'] > 10 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('Filen er for stor. Maksimum 10MB.', 'rigtig-for-mig')));
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('event_file', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        update_post_meta($attachment_id, '_rfm_owner_type', 'rfm_event');
        update_post_meta($attachment_id, '_rfm_owner_id', $expert_id);
        update_post_meta($attachment_id, '_rfm_upload_type', 'event_file');
        update_post_meta($attachment_id, '_rfm_upload_date', current_time('mysql'));

        wp_send_json_success(array(
            'message'       => __('Fil uploadet!', 'rigtig-for-mig'),
            'attachment_id' => $attachment_id,
            'file_name'     => basename(get_attached_file($attachment_id)),
            'file_url'      => wp_get_attachment_url($attachment_id),
        ));
    }

    // =========================================================================
    // ADMIN META BOXES
    // =========================================================================

    public function add_event_meta_boxes() {
        add_meta_box('rfm_event_details', __('Event Detaljer', 'rigtig-for-mig'), array($this, 'render_event_details_meta_box'), 'rfm_event', 'normal', 'high');
        add_meta_box('rfm_event_match', __('Match-sektioner', 'rigtig-for-mig'), array($this, 'render_event_match_meta_box'), 'rfm_event', 'normal', 'default');
        add_meta_box('rfm_event_expert', __('Ekspert / Instruktør', 'rigtig-for-mig'), array($this, 'render_event_expert_meta_box'), 'rfm_event', 'side', 'default');
        add_meta_box('rfm_event_approval', __('Godkendelse', 'rigtig-for-mig'), array($this, 'render_event_approval_meta_box'), 'rfm_event', 'side', 'high');
        add_meta_box('rfm_event_file_box', __('Vedhæftet fil / pjece', 'rigtig-for-mig'), array($this, 'render_event_file_meta_box'), 'rfm_event', 'side', 'default');
    }

    public function render_event_details_meta_box($post) {
        wp_nonce_field('rfm_save_event_meta', 'rfm_event_meta_nonce');

        $date       = get_post_meta($post->ID, '_rfm_event_date', true);
        $time_start = get_post_meta($post->ID, '_rfm_event_time_start', true);
        $time_end   = get_post_meta($post->ID, '_rfm_event_time_end', true);
        $location   = get_post_meta($post->ID, '_rfm_event_location', true);
        $price      = get_post_meta($post->ID, '_rfm_event_price', true);
        $event_url  = get_post_meta($post->ID, '_rfm_event_url', true);
        $format     = get_post_meta($post->ID, '_rfm_event_format', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="rfm-event-date"><?php _e('Dato', 'rigtig-for-mig'); ?></label></th>
                <td><input type="date" id="rfm-event-date" name="rfm_event_date" value="<?php echo esc_attr($date); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="rfm-event-time-start"><?php _e('Starttid', 'rigtig-for-mig'); ?></label></th>
                <td><input type="time" id="rfm-event-time-start" name="rfm_event_time_start" value="<?php echo esc_attr($time_start); ?>" /></td>
            </tr>
            <tr>
                <th><label for="rfm-event-time-end"><?php _e('Sluttid', 'rigtig-for-mig'); ?></label></th>
                <td><input type="time" id="rfm-event-time-end" name="rfm_event_time_end" value="<?php echo esc_attr($time_end); ?>" /></td>
            </tr>
            <tr>
                <th><label for="rfm-event-location"><?php _e('Lokation', 'rigtig-for-mig'); ?></label></th>
                <td><input type="text" id="rfm-event-location" name="rfm_event_location" value="<?php echo esc_attr($location); ?>" class="regular-text" placeholder="<?php esc_attr_e('F.eks. København, Online, etc.', 'rigtig-for-mig'); ?>" /></td>
            </tr>
            <tr>
                <th><label for="rfm-event-price"><?php _e('Pris', 'rigtig-for-mig'); ?></label></th>
                <td><input type="text" id="rfm-event-price" name="rfm_event_price" value="<?php echo esc_attr($price); ?>" class="regular-text" placeholder="<?php esc_attr_e('F.eks. Gratis, 500 kr, Fra 1.200 kr', 'rigtig-for-mig'); ?>" /></td>
            </tr>
            <tr>
                <th><label for="rfm-event-url"><?php _e('Tilmeldingslink', 'rigtig-for-mig'); ?></label></th>
                <td><input type="url" id="rfm-event-url" name="rfm_event_url" value="<?php echo esc_url($event_url); ?>" class="regular-text" placeholder="https://" /></td>
            </tr>
            <tr>
                <th><label for="rfm-event-format"><?php _e('Format', 'rigtig-for-mig'); ?></label></th>
                <td>
                    <select id="rfm-event-format" name="rfm_event_format">
                        <option value=""><?php _e('– Vælg format –', 'rigtig-for-mig'); ?></option>
                        <option value="fysisk" <?php selected($format, 'fysisk'); ?>><?php _e('Fysisk', 'rigtig-for-mig'); ?></option>
                        <option value="online" <?php selected($format, 'online'); ?>><?php _e('Online', 'rigtig-for-mig'); ?></option>
                        <option value="hybrid" <?php selected($format, 'hybrid'); ?>><?php _e('Hybrid', 'rigtig-for-mig'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_event_match_meta_box($post) {
        $what_you_get = get_post_meta($post->ID, '_rfm_event_what_you_get', true);
        $who_for      = get_post_meta($post->ID, '_rfm_event_who_for', true);
        $who_not_for  = get_post_meta($post->ID, '_rfm_event_who_not_for', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="rfm-event-what-you-get"><?php _e('Hvad får du ud af det?', 'rigtig-for-mig'); ?></label></th>
                <td><textarea id="rfm-event-what-you-get" name="rfm_event_what_you_get" rows="4" class="large-text"><?php echo esc_textarea($what_you_get); ?></textarea>
                <p class="description"><?php _e('Resultat-orienteret beskrivelse — hvad går deltageren derfra med?', 'rigtig-for-mig'); ?></p></td>
            </tr>
            <tr>
                <th><label for="rfm-event-who-for"><?php _e('Typisk relevant hvis du...', 'rigtig-for-mig'); ?></label></th>
                <td><textarea id="rfm-event-who-for" name="rfm_event_who_for" rows="3" class="large-text"><?php echo esc_textarea($who_for); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="rfm-event-who-not-for"><?php _e('Måske ikke relevant hvis du...', 'rigtig-for-mig'); ?></label></th>
                <td><textarea id="rfm-event-who-not-for" name="rfm_event_who_not_for" rows="3" class="large-text"><?php echo esc_textarea($who_not_for); ?></textarea></td>
            </tr>
        </table>
        <?php
    }

    public function render_event_expert_meta_box($post) {
        $expert_id = get_post_meta($post->ID, '_rfm_event_expert_id', true);
        $experts = get_posts(array('post_type' => 'rfm_expert', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <select name="rfm_event_expert_id" style="width:100%;">
            <option value=""><?php _e('– Ingen ekspert –', 'rigtig-for-mig'); ?></option>
            <?php foreach ($experts as $expert): ?>
            <option value="<?php echo esc_attr($expert->ID); ?>" <?php selected($expert_id, $expert->ID); ?>><?php echo esc_html($expert->post_title); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php _e('Valgfrit: Knyt en ekspert til dette event.', 'rigtig-for-mig'); ?></p>
        <?php
    }

    public function render_event_approval_meta_box($post) {
        $status = $post->post_status;
        if ($status === 'pending') {
            $approve_url = wp_nonce_url(admin_url('admin-post.php?action=rfm_approve_event&event_id=' . $post->ID), 'rfm_approve_event_' . $post->ID);
            $reject_url  = wp_nonce_url(admin_url('admin-post.php?action=rfm_reject_event&event_id=' . $post->ID), 'rfm_reject_event_' . $post->ID);
            ?>
            <div style="text-align:center;padding:10px;">
                <p><strong><?php _e('Dette event afventer godkendelse.', 'rigtig-for-mig'); ?></strong></p>
                <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary" style="margin-right:10px;"><?php _e('Godkend', 'rigtig-for-mig'); ?></a>
                <a href="<?php echo esc_url($reject_url); ?>" class="button" style="color:#e74c3c;"><?php _e('Afvis', 'rigtig-for-mig'); ?></a>
            </div>
            <?php
        } elseif ($status === 'publish') {
            echo '<p style="color:#27ae60;font-weight:bold;text-align:center;">' . __('Eventet er godkendt og publiceret.', 'rigtig-for-mig') . '</p>';
        } elseif ($status === 'draft' && get_post_meta($post->ID, '_rfm_event_rejected', true)) {
            echo '<p style="color:#e74c3c;font-weight:bold;text-align:center;">' . __('Eventet er afvist.', 'rigtig-for-mig') . '</p>';
        }
    }

    public function render_event_file_meta_box($post) {
        $file_id = get_post_meta($post->ID, '_rfm_event_file_id', true);
        if ($file_id) {
            $file_url  = wp_get_attachment_url($file_id);
            $file_name = basename(get_attached_file($file_id));
            echo '<p><a href="' . esc_url($file_url) . '" target="_blank">' . esc_html($file_name) . '</a></p>';
        } else {
            echo '<p><em>' . __('Ingen fil vedhæftet.', 'rigtig-for-mig') . '</em></p>';
        }
    }

    public function save_event_meta($post_id, $post) {
        if (!isset($_POST['rfm_event_meta_nonce']) || !wp_verify_nonce($_POST['rfm_event_meta_nonce'], 'rfm_save_event_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $text_fields = array(
            'rfm_event_date'       => '_rfm_event_date',
            'rfm_event_time_start' => '_rfm_event_time_start',
            'rfm_event_time_end'   => '_rfm_event_time_end',
            'rfm_event_location'   => '_rfm_event_location',
            'rfm_event_price'      => '_rfm_event_price',
            'rfm_event_format'     => '_rfm_event_format',
        );
        foreach ($text_fields as $form_key => $meta_key) {
            if (isset($_POST[$form_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$form_key]));
            }
        }

        $textarea_fields = array(
            'rfm_event_what_you_get' => '_rfm_event_what_you_get',
            'rfm_event_who_for'      => '_rfm_event_who_for',
            'rfm_event_who_not_for'  => '_rfm_event_who_not_for',
        );
        foreach ($textarea_fields as $form_key => $meta_key) {
            if (isset($_POST[$form_key])) {
                update_post_meta($post_id, $meta_key, wp_kses_post($_POST[$form_key]));
            }
        }

        if (isset($_POST['rfm_event_url'])) {
            update_post_meta($post_id, '_rfm_event_url', esc_url_raw($_POST['rfm_event_url']));
        }
        if (isset($_POST['rfm_event_expert_id'])) {
            $eid = intval($_POST['rfm_event_expert_id']);
            $eid > 0 ? update_post_meta($post_id, '_rfm_event_expert_id', $eid) : delete_post_meta($post_id, '_rfm_event_expert_id');
        }
    }

    // =========================================================================
    // ADMIN COLUMNS
    // =========================================================================

    public function add_event_columns($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['rfm_event_date']     = __('Dato', 'rigtig-for-mig');
                $new['rfm_event_format']   = __('Format', 'rigtig-for-mig');
                $new['rfm_event_location'] = __('Lokation', 'rigtig-for-mig');
                $new['rfm_event_price']    = __('Pris', 'rigtig-for-mig');
                $new['rfm_event_expert']   = __('Ekspert', 'rigtig-for-mig');
                $new['rfm_event_status']   = __('Status', 'rigtig-for-mig');
            }
        }
        return $new;
    }

    public function render_event_columns($column, $post_id) {
        switch ($column) {
            case 'rfm_event_date':
                $date = get_post_meta($post_id, '_rfm_event_date', true);
                if ($date) {
                    echo esc_html(date_i18n('j. F Y', strtotime($date)));
                    $ts = get_post_meta($post_id, '_rfm_event_time_start', true);
                    if ($ts) {
                        echo '<br><small>' . esc_html($ts);
                        $te = get_post_meta($post_id, '_rfm_event_time_end', true);
                        if ($te) echo ' – ' . esc_html($te);
                        echo '</small>';
                    }
                } else { echo '—'; }
                break;
            case 'rfm_event_format':
                $f = get_post_meta($post_id, '_rfm_event_format', true);
                echo $f ? esc_html(ucfirst($f)) : '—';
                break;
            case 'rfm_event_location':
                echo esc_html(get_post_meta($post_id, '_rfm_event_location', true) ?: '—');
                break;
            case 'rfm_event_price':
                echo esc_html(get_post_meta($post_id, '_rfm_event_price', true) ?: '—');
                break;
            case 'rfm_event_expert':
                $eid = get_post_meta($post_id, '_rfm_event_expert_id', true);
                if ($eid && get_post($eid)) {
                    echo '<a href="' . esc_url(get_edit_post_link($eid)) . '">' . esc_html(get_the_title($eid)) . '</a>';
                } else { echo '—'; }
                break;
            case 'rfm_event_status':
                $status = get_post_status($post_id);
                $label  = self::get_status_label($status, $post_id);
                $color  = self::get_status_color($status, $post_id);
                echo '<span style="color:' . esc_attr($color) . ';font-weight:bold;">' . esc_html($label) . '</span>';
                if ($status === 'pending') {
                    $a_url = wp_nonce_url(admin_url('admin-post.php?action=rfm_approve_event&event_id=' . $post_id), 'rfm_approve_event_' . $post_id);
                    $r_url = wp_nonce_url(admin_url('admin-post.php?action=rfm_reject_event&event_id=' . $post_id), 'rfm_reject_event_' . $post_id);
                    echo '<br><a href="' . esc_url($a_url) . '" class="button button-small" style="color:green;">Godkend</a> ';
                    echo '<a href="' . esc_url($r_url) . '" class="button button-small" style="color:red;">Afvis</a>';
                }
                break;
        }
    }

    public function sortable_event_columns($columns) { $columns['rfm_event_date'] = 'rfm_event_date'; return $columns; }

    public function sort_events_by_date($query) {
        if (!is_admin() || !$query->is_main_query()) return;
        if ($query->get('post_type') !== 'rfm_event') return;
        if ($query->get('orderby') === 'rfm_event_date') {
            $query->set('meta_key', '_rfm_event_date');
            $query->set('orderby', 'meta_value');
        }
    }

    // =========================================================================
    // ADMIN APPROVAL
    // =========================================================================

    public function handle_approve_event() {
        $event_id = intval($_GET['event_id'] ?? 0);
        check_admin_referer('rfm_approve_event_' . $event_id);
        if (!current_user_can('manage_options')) wp_die(__('Du har ikke tilladelse.', 'rigtig-for-mig'));
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'rfm_event') wp_die(__('Event ikke fundet.', 'rigtig-for-mig'));

        wp_update_post(array('ID' => $event_id, 'post_status' => 'publish'));
        delete_post_meta($event_id, '_rfm_event_rejected');
        delete_post_meta($event_id, '_rfm_event_reject_reason');

        wp_redirect(add_query_arg(array('rfm_event_action' => 'approved'), admin_url('edit.php?post_type=rfm_event')));
        exit;
    }

    public function handle_reject_event() {
        $event_id = intval($_GET['event_id'] ?? 0);
        check_admin_referer('rfm_reject_event_' . $event_id);
        if (!current_user_can('manage_options')) wp_die(__('Du har ikke tilladelse.', 'rigtig-for-mig'));
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'rfm_event') wp_die(__('Event ikke fundet.', 'rigtig-for-mig'));

        wp_update_post(array('ID' => $event_id, 'post_status' => 'draft'));
        update_post_meta($event_id, '_rfm_event_rejected', '1');

        wp_redirect(add_query_arg(array('rfm_event_action' => 'rejected'), admin_url('edit.php?post_type=rfm_event')));
        exit;
    }

    public function pending_events_notice() {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('dashboard', 'edit-rfm_event'))) return;

        $pending = wp_count_posts('rfm_event')->pending;
        if ($pending > 0) {
            $url = admin_url('edit.php?post_type=rfm_event&post_status=pending');
            echo '<div class="notice notice-warning"><p><strong>' . __('Rigtig for mig:', 'rigtig-for-mig') . '</strong> ';
            printf(_n('%d event afventer godkendelse.', '%d events afventer godkendelse.', $pending, 'rigtig-for-mig'), $pending);
            echo ' <a href="' . esc_url($url) . '">' . __('Se events', 'rigtig-for-mig') . '</a></p></div>';
        }

        if (isset($_GET['rfm_event_action'])) {
            $r = sanitize_text_field($_GET['rfm_event_action']);
            if ($r === 'approved') echo '<div class="notice notice-success is-dismissible"><p>' . __('Eventet er godkendt og publiceret.', 'rigtig-for-mig') . '</p></div>';
            elseif ($r === 'rejected') echo '<div class="notice notice-info is-dismissible"><p>' . __('Eventet er afvist.', 'rigtig-for-mig') . '</p></div>';
        }
    }

    // =========================================================================
    // SINGLE TEMPLATE
    // =========================================================================

    public function event_template($template) {
        global $post;
        if ($post && $post->post_type === 'rfm_event') {
            $custom = RFM_PLUGIN_DIR . 'templates/single-event.php';
            if (file_exists($custom)) return $custom;
        }
        return $template;
    }

    public function include_events_in_queries($query) {
        if (is_admin() || !$query->is_main_query()) return;
        if ($query->is_tax('rfm_category')) {
            $pt = $query->get('post_type');
            if (empty($pt)) $pt = array('rfm_expert');
            elseif (!is_array($pt)) $pt = array($pt);
            if (!in_array('rfm_event', $pt)) $pt[] = 'rfm_event';
            $query->set('post_type', $pt);
        }
    }

    // =========================================================================
    // AUTO-EXPIRY
    // =========================================================================

    /**
     * Daily cron: trash events that ended more than 30 days ago.
     */
    public function cleanup_expired_events() {
        $cutoff = date('Y-m-d', strtotime('-30 days'));
        $expired = get_posts(array(
            'post_type'      => 'rfm_event',
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => '_rfm_event_date',
                    'value'   => $cutoff,
                    'compare' => '<',
                    'type'    => 'DATE',
                ),
            ),
            'posts_per_page' => 50,
            'fields'         => 'ids',
        ));

        foreach ($expired as $id) {
            wp_trash_post($id);
        }
    }

    // =========================================================================
    // SHORTCODE: [rfm_events_page]
    // =========================================================================

    public function events_page_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit'     => 12,
            'columns'   => 3,
            'category'  => '',
            'show_past' => 'false',
        ), $atts);

        $today = current_time('Y-m-d');

        // Read filters from URL
        $sel_category = sanitize_text_field($_GET['rfm_category'] ?? $atts['category']);
        $sel_type     = sanitize_text_field($_GET['rfm_event_type'] ?? '');
        $sel_audience = sanitize_text_field($_GET['rfm_audience'] ?? '');
        $sel_format   = sanitize_text_field($_GET['rfm_format'] ?? '');

        // Build query
        $args = array(
            'post_type'      => 'rfm_event',
            'posts_per_page' => intval($atts['limit']),
            'post_status'    => 'publish',
            'meta_key'       => '_rfm_event_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        );

        // Hide past by default
        if ($atts['show_past'] !== 'true') {
            $args['meta_query'] = array(
                array('key' => '_rfm_event_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'),
            );
        }

        // Tax queries
        $tax_query = array('relation' => 'AND');
        $has_tax = false;
        if ($sel_category) {
            $tax_query[] = array('taxonomy' => 'rfm_category', 'field' => 'slug', 'terms' => $sel_category);
            $has_tax = true;
        }
        if ($sel_type) {
            $tax_query[] = array('taxonomy' => 'rfm_event_type', 'field' => 'slug', 'terms' => $sel_type);
            $has_tax = true;
        }
        if ($sel_audience) {
            $tax_query[] = array('taxonomy' => 'rfm_event_audience', 'field' => 'slug', 'terms' => $sel_audience);
            $has_tax = true;
        }
        if ($has_tax) {
            $args['tax_query'] = $tax_query;
        }

        // Format filter via meta
        if ($sel_format) {
            if (!isset($args['meta_query'])) $args['meta_query'] = array();
            $args['meta_query'][] = array('key' => '_rfm_event_format', 'value' => $sel_format);
        }

        $events = new WP_Query($args);

        $categories = get_terms(array('taxonomy' => 'rfm_category', 'hide_empty' => false));
        $types      = get_terms(array('taxonomy' => 'rfm_event_type', 'hide_empty' => false));
        $audiences  = get_terms(array('taxonomy' => 'rfm_event_audience', 'hide_empty' => false));

        ob_start();
        ?>
        <div class="rfm-events-page">
            <div class="rfm-events-filter-bar">
                <form method="get" class="rfm-events-filter-form">
                    <div class="rfm-events-filters">
                        <select name="rfm_category" class="rfm-events-filter-select">
                            <option value=""><?php _e('Alle kategorier', 'rigtig-for-mig'); ?></option>
                            <?php if (!is_wp_error($categories)): foreach ($categories as $c): ?>
                            <option value="<?php echo esc_attr($c->slug); ?>" <?php selected($sel_category, $c->slug); ?>><?php echo esc_html($c->name); ?></option>
                            <?php endforeach; endif; ?>
                        </select>

                        <select name="rfm_event_type" class="rfm-events-filter-select">
                            <option value=""><?php _e('Alle typer', 'rigtig-for-mig'); ?></option>
                            <?php if (!is_wp_error($types)): foreach ($types as $t): ?>
                            <option value="<?php echo esc_attr($t->slug); ?>" <?php selected($sel_type, $t->slug); ?>><?php echo esc_html($t->name); ?></option>
                            <?php endforeach; endif; ?>
                        </select>

                        <select name="rfm_audience" class="rfm-events-filter-select">
                            <option value=""><?php _e('Alle målgrupper', 'rigtig-for-mig'); ?></option>
                            <?php if (!is_wp_error($audiences)): foreach ($audiences as $a): ?>
                            <option value="<?php echo esc_attr($a->slug); ?>" <?php selected($sel_audience, $a->slug); ?>><?php echo esc_html($a->name); ?></option>
                            <?php endforeach; endif; ?>
                        </select>

                        <select name="rfm_format" class="rfm-events-filter-select">
                            <option value=""><?php _e('Alle formater', 'rigtig-for-mig'); ?></option>
                            <option value="fysisk" <?php selected($sel_format, 'fysisk'); ?>><?php _e('Fysisk', 'rigtig-for-mig'); ?></option>
                            <option value="online" <?php selected($sel_format, 'online'); ?>><?php _e('Online', 'rigtig-for-mig'); ?></option>
                            <option value="hybrid" <?php selected($sel_format, 'hybrid'); ?>><?php _e('Hybrid', 'rigtig-for-mig'); ?></option>
                        </select>

                        <button type="submit" class="rfm-btn rfm-btn-primary"><?php _e('Filtrer', 'rigtig-for-mig'); ?></button>
                    </div>
                </form>
            </div>

            <?php if ($events->have_posts()): ?>
            <div class="rfm-events-grid rfm-columns-<?php echo intval($atts['columns']); ?>">
                <?php while ($events->have_posts()): $events->the_post(); ?>
                    <?php $this->render_event_card(get_the_ID(), $today); ?>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="rfm-events-empty">
                <p><?php _e('Der er ingen kommende kurser eller events lige nu. Kom tilbage snart!', 'rigtig-for-mig'); ?></p>
            </div>
            <?php endif; wp_reset_postdata(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_event_card($event_id, $today = '') {
        if (!$today) $today = current_time('Y-m-d');

        $date       = get_post_meta($event_id, '_rfm_event_date', true);
        $time_start = get_post_meta($event_id, '_rfm_event_time_start', true);
        $time_end   = get_post_meta($event_id, '_rfm_event_time_end', true);
        $location   = get_post_meta($event_id, '_rfm_event_location', true);
        $price      = get_post_meta($event_id, '_rfm_event_price', true);
        $expert_id  = get_post_meta($event_id, '_rfm_event_expert_id', true);
        $format     = get_post_meta($event_id, '_rfm_event_format', true);
        $categories = wp_get_object_terms($event_id, 'rfm_category');
        $category   = !empty($categories) ? $categories[0] : null;
        $cat_color  = $category ? get_term_meta($category->term_id, 'rfm_color', true) : '';
        $is_past    = ($date && $date < $today);

        $date_formatted = $date ? date_i18n('j. F Y', strtotime($date)) : '';
        $time_str = '';
        if ($time_start) { $time_str = $time_start; if ($time_end) $time_str .= ' – ' . $time_end; }

        $expert_name = '';
        if ($expert_id) { $ep = get_post($expert_id); if ($ep) $expert_name = $ep->post_title; }
        ?>
        <div class="rfm-event-card <?php echo $is_past ? 'rfm-event-past' : ''; ?>">
            <a href="<?php echo esc_url(get_permalink($event_id)); ?>" class="rfm-event-card-link">
                <div class="rfm-event-card-image">
                    <?php if (has_post_thumbnail($event_id)): ?>
                        <?php echo get_the_post_thumbnail($event_id, 'medium_large'); ?>
                    <?php else: ?>
                        <div class="rfm-event-card-placeholder"><span class="dashicons dashicons-calendar-alt"></span></div>
                    <?php endif; ?>
                    <?php if ($date_formatted): ?>
                    <div class="rfm-event-card-date-badge">
                        <span class="rfm-event-badge-day"><?php echo esc_html(date_i18n('j', strtotime($date))); ?></span>
                        <span class="rfm-event-badge-month"><?php echo esc_html(date_i18n('M', strtotime($date))); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($is_past): ?>
                    <div class="rfm-event-card-past-overlay"><span><?php _e('Afholdt', 'rigtig-for-mig'); ?></span></div>
                    <?php endif; ?>
                </div>
                <div class="rfm-event-card-content">
                    <?php if ($category): ?>
                    <span class="rfm-event-card-category" style="color:<?php echo esc_attr($cat_color ?: '#666'); ?>;"><?php echo esc_html($category->name); ?></span>
                    <?php endif; ?>
                    <h3 class="rfm-event-card-title"><?php echo esc_html(get_the_title($event_id)); ?></h3>
                    <div class="rfm-event-card-meta">
                        <?php if ($date_formatted): ?><div class="rfm-event-card-meta-item"><span class="dashicons dashicons-calendar"></span><span><?php echo esc_html($date_formatted); ?></span></div><?php endif; ?>
                        <?php if ($time_str): ?><div class="rfm-event-card-meta-item"><span class="dashicons dashicons-clock"></span><span><?php echo esc_html('kl. ' . $time_str); ?></span></div><?php endif; ?>
                        <?php if ($location): ?><div class="rfm-event-card-meta-item"><span class="dashicons dashicons-location"></span><span><?php echo esc_html($location); ?></span></div><?php endif; ?>
                        <?php if ($format): ?><div class="rfm-event-card-meta-item"><span class="dashicons dashicons-desktop"></span><span><?php echo esc_html(ucfirst($format)); ?></span></div><?php endif; ?>
                        <?php if ($expert_name): ?><div class="rfm-event-card-meta-item"><span class="dashicons dashicons-admin-users"></span><span><?php echo esc_html($expert_name); ?></span></div><?php endif; ?>
                    </div>
                    <?php if ($price): ?><div class="rfm-event-card-price"><?php echo esc_html($price); ?></div><?php endif; ?>
                </div>
            </a>
        </div>
        <?php
    }

    // =========================================================================
    // SOCIAL SHARING
    // =========================================================================

    public static function render_share_buttons($event_id) {
        $url     = urlencode(get_permalink($event_id));
        $title   = urlencode(get_the_title($event_id));
        $raw_url = get_permalink($event_id);
        ?>
        <div class="rfm-event-share">
            <span class="rfm-share-label"><?php _e('Del dette event:', 'rigtig-for-mig'); ?></span>
            <div class="rfm-share-buttons">
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $url; ?>" class="rfm-share-btn rfm-share-facebook" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e('Del på Facebook', 'rigtig-for-mig'); ?>" onclick="window.open(this.href,'fb','width=580,height=400');return false;"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $url; ?>" class="rfm-share-btn rfm-share-linkedin" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e('Del på LinkedIn', 'rigtig-for-mig'); ?>" onclick="window.open(this.href,'li','width=580,height=400');return false;"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
                <a href="mailto:?subject=<?php echo $title; ?>&body=<?php echo __('Se dette event:', 'rigtig-for-mig') . '%20' . $url; ?>" class="rfm-share-btn rfm-share-email" title="<?php esc_attr_e('Del via email', 'rigtig-for-mig'); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg></a>
                <button type="button" class="rfm-share-btn rfm-share-copy" data-url="<?php echo esc_attr($raw_url); ?>" title="<?php esc_attr_e('Kopiér link', 'rigtig-for-mig'); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg><span class="rfm-share-copy-feedback"><?php _e('Kopieret!', 'rigtig-for-mig'); ?></span></button>
            </div>
        </div>
        <script>(function(){document.querySelectorAll('.rfm-share-copy').forEach(function(b){b.addEventListener('click',function(){var u=this.getAttribute('data-url');if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u).then(function(){b.classList.add('rfm-copied');setTimeout(function(){b.classList.remove('rfm-copied');},2000);});}else{var i=document.createElement('input');i.value=u;document.body.appendChild(i);i.select();document.execCommand('copy');document.body.removeChild(i);b.classList.add('rfm-copied');setTimeout(function(){b.classList.remove('rfm-copied');},2000);}});});})();</script>
        <?php
    }

    // =========================================================================
    // DASHBOARD TAB RENDERING
    // =========================================================================

    /**
     * Render the "Kurser & Events" tab content for the expert dashboard.
     */
    public function render_dashboard_tab($expert_id, $plan) {
        $can_create = $this->can_create_event($expert_id);
        $events     = $this->get_expert_events($expert_id);
        $expert_categories = wp_get_object_terms($expert_id, 'rfm_category', array('fields' => 'all'));
        $event_types = get_terms(array('taxonomy' => 'rfm_event_type', 'hide_empty' => false));
        $audiences   = get_terms(array('taxonomy' => 'rfm_event_audience', 'hide_empty' => false));

        ?>
        <div class="rfm-events-dashboard">
            <h3><?php _e('Mine Kurser & Events', 'rigtig-for-mig'); ?></h3>

            <!-- Quota -->
            <div class="rfm-article-stats">
                <?php if ($can_create['limit'] > 0): ?>
                    <div class="rfm-article-quota">
                        <span class="rfm-quota-used"><?php echo $can_create['used']; ?></span>
                        <span class="rfm-quota-separator">/</span>
                        <span class="rfm-quota-limit"><?php echo $can_create['limit']; ?></span>
                        <span class="rfm-quota-label"><?php _e('events denne måned', 'rigtig-for-mig'); ?></span>
                    </div>
                <?php else: ?>
                    <div class="rfm-upgrade-notice">
                        <?php _e('Dit abonnement tillader ikke events. Opgrader til Standard eller Premium.', 'rigtig-for-mig'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- New Event Button -->
            <?php if ($can_create['allowed']): ?>
            <div class="rfm-article-actions" style="margin-bottom:20px;">
                <button type="button" class="rfm-btn rfm-btn-primary" id="rfm-new-event-btn">
                    + <?php _e('Opret nyt event', 'rigtig-for-mig'); ?>
                </button>
            </div>
            <?php endif; ?>

            <!-- Event Editor (hidden) -->
            <div id="rfm-event-editor" class="rfm-article-editor" style="display:none;">
                <form id="rfm-event-form" method="post">
                    <input type="hidden" name="expert_id" value="<?php echo esc_attr($expert_id); ?>" />
                    <input type="hidden" name="event_id" id="rfm-event-id" value="0" />
                    <input type="hidden" name="event_image_id" id="rfm-event-image-id" value="0" />
                    <input type="hidden" name="event_file_id" id="rfm-event-file-id" value="0" />

                    <div class="rfm-form-section">
                        <h4 id="rfm-event-editor-title"><?php _e('Nyt event', 'rigtig-for-mig'); ?></h4>

                        <div class="rfm-form-field">
                            <label for="rfm-event-title"><?php _e('Titel', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                            <input type="text" id="rfm-event-title" name="event_title" required placeholder="<?php esc_attr_e('Giv dit event en titel...', 'rigtig-for-mig'); ?>" />
                        </div>

                        <div class="rfm-form-row rfm-form-row-2">
                            <div class="rfm-form-field">
                                <label for="rfm-event-category"><?php _e('Kategori', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                                <select id="rfm-event-category" name="event_category" required>
                                    <option value=""><?php _e('– Vælg kategori –', 'rigtig-for-mig'); ?></option>
                                    <?php foreach ($expert_categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="rfm-form-field">
                                <label for="rfm-event-type"><?php _e('Event type', 'rigtig-for-mig'); ?></label>
                                <select id="rfm-event-type" name="event_type">
                                    <option value=""><?php _e('– Vælg type –', 'rigtig-for-mig'); ?></option>
                                    <?php if (!is_wp_error($event_types)): foreach ($event_types as $et): ?>
                                    <option value="<?php echo esc_attr($et->term_id); ?>"><?php echo esc_html($et->name); ?></option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="rfm-form-row rfm-form-row-3">
                            <div class="rfm-form-field">
                                <label for="rfm-event-date"><?php _e('Dato', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                                <input type="date" id="rfm-event-date" name="event_date" required min="<?php echo esc_attr(current_time('Y-m-d')); ?>" max="<?php echo esc_attr(date('Y-m-d', strtotime('+' . self::MAX_FUTURE_MONTHS . ' months'))); ?>" />
                            </div>
                            <div class="rfm-form-field">
                                <label for="rfm-event-time-start"><?php _e('Starttid', 'rigtig-for-mig'); ?></label>
                                <input type="time" id="rfm-event-time-start" name="event_time_start" />
                            </div>
                            <div class="rfm-form-field">
                                <label for="rfm-event-time-end"><?php _e('Sluttid', 'rigtig-for-mig'); ?></label>
                                <input type="time" id="rfm-event-time-end" name="event_time_end" />
                            </div>
                        </div>

                        <div class="rfm-form-row rfm-form-row-3">
                            <div class="rfm-form-field">
                                <label for="rfm-event-location"><?php _e('Lokation', 'rigtig-for-mig'); ?></label>
                                <input type="text" id="rfm-event-location" name="event_location" placeholder="<?php esc_attr_e('København, Online, etc.', 'rigtig-for-mig'); ?>" />
                            </div>
                            <div class="rfm-form-field">
                                <label for="rfm-event-format"><?php _e('Format', 'rigtig-for-mig'); ?></label>
                                <select id="rfm-event-format" name="event_format">
                                    <option value=""><?php _e('– Vælg –', 'rigtig-for-mig'); ?></option>
                                    <option value="fysisk"><?php _e('Fysisk', 'rigtig-for-mig'); ?></option>
                                    <option value="online"><?php _e('Online', 'rigtig-for-mig'); ?></option>
                                    <option value="hybrid"><?php _e('Hybrid', 'rigtig-for-mig'); ?></option>
                                </select>
                            </div>
                            <div class="rfm-form-field">
                                <label for="rfm-event-audience"><?php _e('Målgruppe', 'rigtig-for-mig'); ?></label>
                                <select id="rfm-event-audience" name="event_audience">
                                    <option value=""><?php _e('– Vælg –', 'rigtig-for-mig'); ?></option>
                                    <?php if (!is_wp_error($audiences)): foreach ($audiences as $a): ?>
                                    <option value="<?php echo esc_attr($a->term_id); ?>"><?php echo esc_html($a->name); ?></option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="rfm-form-row rfm-form-row-2">
                            <div class="rfm-form-field">
                                <label for="rfm-event-price"><?php _e('Pris', 'rigtig-for-mig'); ?></label>
                                <input type="text" id="rfm-event-price" name="event_price" placeholder="<?php esc_attr_e('Gratis, 500 kr, Fra 1.200 kr', 'rigtig-for-mig'); ?>" />
                            </div>
                            <div class="rfm-form-field">
                                <label for="rfm-event-url"><?php _e('Tilmeldingslink', 'rigtig-for-mig'); ?></label>
                                <input type="url" id="rfm-event-url" name="event_url" placeholder="https://" />
                            </div>
                        </div>

                        <div class="rfm-form-field">
                            <label for="rfm-event-content"><?php _e('Beskrivelse', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                            <textarea id="rfm-event-content" name="event_content" rows="8" required placeholder="<?php esc_attr_e('Beskriv dit event...', 'rigtig-for-mig'); ?>"></textarea>
                        </div>

                        <!-- Match Sections -->
                        <div class="rfm-form-field">
                            <label for="rfm-event-what-you-get"><?php _e('Hvad får du ud af det?', 'rigtig-for-mig'); ?></label>
                            <textarea id="rfm-event-what-you-get" name="event_what_you_get" rows="3" placeholder="<?php esc_attr_e('Resultat-orienteret: hvad går deltageren derfra med?', 'rigtig-for-mig'); ?>"></textarea>
                        </div>

                        <div class="rfm-form-row rfm-form-row-2">
                            <div class="rfm-form-field">
                                <label for="rfm-event-who-for"><?php _e('Typisk relevant hvis du...', 'rigtig-for-mig'); ?></label>
                                <textarea id="rfm-event-who-for" name="event_who_for" rows="3" placeholder="<?php esc_attr_e('F.eks. oplever stress, vil styrke dit lederskab...', 'rigtig-for-mig'); ?>"></textarea>
                            </div>
                            <div class="rfm-form-field">
                                <label for="rfm-event-who-not-for"><?php _e('Måske ikke relevant hvis du...', 'rigtig-for-mig'); ?></label>
                                <textarea id="rfm-event-who-not-for" name="event_who_not_for" rows="3" placeholder="<?php esc_attr_e('F.eks. søger individuel terapi, har akutte problemer...', 'rigtig-for-mig'); ?>"></textarea>
                            </div>
                        </div>

                        <!-- Event Image -->
                        <div class="rfm-form-field">
                            <label><?php _e('Eventbillede', 'rigtig-for-mig'); ?></label>
                            <div class="rfm-article-image-upload">
                                <div id="rfm-event-image-preview" class="rfm-article-image-preview">
                                    <span class="rfm-no-image"><?php _e('Intet billede valgt', 'rigtig-for-mig'); ?></span>
                                </div>
                                <div class="rfm-image-buttons">
                                    <button type="button" class="rfm-btn rfm-btn-secondary rfm-btn-small" id="rfm-upload-event-image-btn"><?php _e('Upload billede', 'rigtig-for-mig'); ?></button>
                                    <button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger" id="rfm-remove-event-image-btn" style="display:none;"><?php _e('Fjern billede', 'rigtig-for-mig'); ?></button>
                                </div>
                                <input type="file" id="rfm-event-image-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" />
                                <small class="rfm-field-hint"><?php _e('Anbefalet: 1200x630px, max 5MB. JPG, PNG, GIF eller WebP.', 'rigtig-for-mig'); ?></small>
                            </div>
                        </div>

                        <!-- Event File / Brochure -->
                        <div class="rfm-form-field">
                            <label><?php _e('Vedhæft fil / pjece', 'rigtig-for-mig'); ?></label>
                            <div class="rfm-event-file-upload">
                                <div id="rfm-event-file-preview" class="rfm-event-file-preview">
                                    <span class="rfm-no-file"><?php _e('Ingen fil vedhæftet', 'rigtig-for-mig'); ?></span>
                                </div>
                                <div class="rfm-image-buttons">
                                    <button type="button" class="rfm-btn rfm-btn-secondary rfm-btn-small" id="rfm-upload-event-file-btn"><?php _e('Upload fil', 'rigtig-for-mig'); ?></button>
                                    <button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger" id="rfm-remove-event-file-btn" style="display:none;"><?php _e('Fjern fil', 'rigtig-for-mig'); ?></button>
                                </div>
                                <input type="file" id="rfm-event-file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none;" />
                                <small class="rfm-field-hint"><?php _e('Max 10MB. PDF, DOC, DOCX, JPG eller PNG.', 'rigtig-for-mig'); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="rfm-form-submit rfm-article-submit-buttons">
                        <button type="submit" class="rfm-btn rfm-btn-primary rfm-btn-large" id="rfm-submit-event-btn"><?php _e('Indsend til godkendelse', 'rigtig-for-mig'); ?></button>
                        <button type="button" class="rfm-btn rfm-btn-secondary" id="rfm-cancel-event-btn"><?php _e('Annuller', 'rigtig-for-mig'); ?></button>
                    </div>

                    <div id="rfm-event-editor-message" class="rfm-message" style="display:none;"></div>
                </form>
            </div>

            <!-- Event List -->
            <div id="rfm-event-list" class="rfm-article-list">
                <?php if (empty($events)): ?>
                    <div class="rfm-no-articles">
                        <p><?php _e('Du har ingen events endnu.', 'rigtig-for-mig'); ?></p>
                        <?php if ($can_create['limit'] > 0): ?>
                            <p><?php _e('Klik "Opret nyt event" for at komme i gang!', 'rigtig-for-mig'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event):
                        $status       = $event->post_status;
                        $status_label = self::get_status_label($status, $event->ID);
                        $status_color = self::get_status_color($status, $event->ID);
                        $e_cats       = wp_get_object_terms($event->ID, 'rfm_category');
                        $e_cat_name   = !empty($e_cats) ? $e_cats[0]->name : '';
                        $e_cat_id     = !empty($e_cats) ? $e_cats[0]->term_id : '';
                        $rejected     = get_post_meta($event->ID, '_rfm_event_rejected', true);
                        $reject_reason = get_post_meta($event->ID, '_rfm_event_reject_reason', true);
                        $image_id     = get_post_thumbnail_id($event->ID);
                        $file_id      = get_post_meta($event->ID, '_rfm_event_file_id', true);
                        $e_date       = get_post_meta($event->ID, '_rfm_event_date', true);
                        $e_location   = get_post_meta($event->ID, '_rfm_event_location', true);

                        // Taxonomy term IDs for edit
                        $e_type_terms = wp_get_object_terms($event->ID, 'rfm_event_type', array('fields' => 'ids'));
                        $e_aud_terms  = wp_get_object_terms($event->ID, 'rfm_event_audience', array('fields' => 'ids'));
                    ?>
                    <div class="rfm-article-item rfm-event-item" data-event-id="<?php echo esc_attr($event->ID); ?>">
                        <?php if ($image_id): ?>
                        <div class="rfm-article-item-image"><?php echo get_the_post_thumbnail($event->ID, 'thumbnail'); ?></div>
                        <?php endif; ?>
                        <div class="rfm-article-item-content">
                            <h4 class="rfm-article-item-title">
                                <?php if ($status === 'publish'): ?>
                                    <a href="<?php echo get_permalink($event->ID); ?>" target="_blank"><?php echo esc_html($event->post_title); ?></a>
                                <?php else: ?>
                                    <?php echo esc_html($event->post_title); ?>
                                <?php endif; ?>
                            </h4>
                            <div class="rfm-article-item-meta">
                                <span class="rfm-article-status" style="color:<?php echo esc_attr($status_color); ?>;"><?php echo esc_html($status_label); ?></span>
                                <?php if ($e_date): ?><span class="rfm-article-date"><?php echo esc_html(date_i18n('j. M Y', strtotime($e_date))); ?></span><?php endif; ?>
                                <?php if ($e_location): ?><span class="rfm-article-category"><?php echo esc_html($e_location); ?></span><?php endif; ?>
                                <?php if ($e_cat_name): ?><span class="rfm-article-category"><?php echo esc_html($e_cat_name); ?></span><?php endif; ?>
                            </div>
                            <?php if ($rejected && $reject_reason): ?>
                            <div class="rfm-article-reject-reason"><strong><?php _e('Afvisningsgrund:', 'rigtig-for-mig'); ?></strong> <?php echo esc_html($reject_reason); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="rfm-article-item-actions">
                            <button type="button" class="rfm-btn rfm-btn-small rfm-edit-event-btn"
                                data-event-id="<?php echo esc_attr($event->ID); ?>"
                                data-title="<?php echo esc_attr($event->post_title); ?>"
                                data-content="<?php echo esc_attr($event->post_content); ?>"
                                data-category="<?php echo esc_attr($e_cat_id); ?>"
                                data-date="<?php echo esc_attr($e_date); ?>"
                                data-time-start="<?php echo esc_attr(get_post_meta($event->ID, '_rfm_event_time_start', true)); ?>"
                                data-time-end="<?php echo esc_attr(get_post_meta($event->ID, '_rfm_event_time_end', true)); ?>"
                                data-location="<?php echo esc_attr($e_location); ?>"
                                data-price="<?php echo esc_attr(get_post_meta($event->ID, '_rfm_event_price', true)); ?>"
                                data-url="<?php echo esc_attr(get_post_meta($event->ID, '_rfm_event_url', true)); ?>"
                                data-format="<?php echo esc_attr(get_post_meta($event->ID, '_rfm_event_format', true)); ?>"
                                data-event-type="<?php echo esc_attr(!empty($e_type_terms) ? $e_type_terms[0] : ''); ?>"
                                data-audience="<?php echo esc_attr(!empty($e_aud_terms) ? $e_aud_terms[0] : ''); ?>"
                                data-what-you-get="<?php echo esc_attr(get_post_meta($event->ID, '_rfm_event_what_you_get', true)); ?>"
                                data-who-for="<?php echo esc_attr(get_post_meta($event->ID, '_rfm_event_who_for', true)); ?>"
                                data-who-not-for="<?php echo esc_attr(get_post_meta($event->ID, '_rfm_event_who_not_for', true)); ?>"
                                data-image-id="<?php echo esc_attr($image_id); ?>"
                                data-image-url="<?php echo esc_attr($image_id ? wp_get_attachment_image_url($image_id, 'medium') : ''); ?>"
                                data-file-id="<?php echo esc_attr($file_id); ?>"
                                data-file-name="<?php echo esc_attr($file_id ? basename(get_attached_file($file_id)) : ''); ?>">
                                <?php _e('Rediger', 'rigtig-for-mig'); ?>
                            </button>
                            <button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger rfm-delete-event-btn" data-event-id="<?php echo esc_attr($event->ID); ?>">
                                <?php _e('Slet', 'rigtig-for-mig'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
