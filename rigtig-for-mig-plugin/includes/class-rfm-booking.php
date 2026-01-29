<?php
/**
 * Internal Booking System
 *
 * Handles booking creation, management, and display for the
 * internal booking calendar system (Fase 2).
 *
 * @package Rigtig_For_Mig
 * @since 3.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Booking {

    private static $instance = null;

    const STATUS_PENDING   = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // No hooks needed - methods called directly
    }

    /**
     * Create a new booking
     *
     * @param int    $expert_id  Expert post ID
     * @param int    $user_id    User ID
     * @param string $date       Booking date (Y-m-d)
     * @param string $time       Booking time (H:i)
     * @param int    $duration   Duration in minutes
     * @param string $note       Optional note from user
     * @param string $user_phone Optional phone number from user
     * @return int|false Booking ID on success, false on failure
     */
    public function create_booking($expert_id, $user_id, $date, $time, $duration = 60, $note = '', $user_phone = '') {
        global $wpdb;

        // Validate inputs
        if (!$expert_id || !$user_id || !$date || !$time) {
            return false;
        }

        // Check expert has booking feature
        if (!RFM_Subscriptions::can_use_feature($expert_id, 'booking')) {
            return false;
        }

        // Validate date is in the future
        $booking_datetime = strtotime($date . ' ' . $time);
        if ($booking_datetime <= current_time('timestamp')) {
            return false;
        }

        // Check for conflicts (double booking)
        if ($this->has_conflict($expert_id, $date, $time, $duration)) {
            return false;
        }

        // Check availability
        $availability = RFM_Availability::get_instance();
        if (!$availability->is_time_available($expert_id, $date, $time)) {
            return false;
        }

        $table = $wpdb->prefix . 'rfm_bookings';
        $result = $wpdb->insert($table, array(
            'expert_id'    => $expert_id,
            'user_id'      => $user_id,
            'booking_date' => $date,
            'booking_time' => $time . ':00',
            'duration'     => $duration,
            'status'       => self::STATUS_PENDING,
            'note'         => sanitize_textarea_field($note),
            'user_phone'   => sanitize_text_field($user_phone),
        ), array('%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s'));

        if ($result) {
            $booking_id = $wpdb->insert_id;

            // Also save phone to user profile for future auto-fill
            if (!empty($user_phone)) {
                update_user_meta($user_id, '_rfm_phone', sanitize_text_field($user_phone));
            }

            $this->send_booking_notification($booking_id, 'new');
            return $booking_id;
        }

        return false;
    }

    /**
     * Check for scheduling conflicts
     *
     * @param int    $expert_id Expert post ID
     * @param string $date      Date (Y-m-d)
     * @param string $time      Time (H:i)
     * @param int    $duration  Duration in minutes
     * @param int    $exclude_id Booking ID to exclude (for updates)
     * @return bool True if conflict exists
     */
    public function has_conflict($expert_id, $date, $time, $duration = 60, $exclude_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_bookings';

        $new_start = strtotime($date . ' ' . $time);
        $new_end = $new_start + ($duration * 60);

        // Get all confirmed/pending bookings for this expert on this date
        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT booking_time, duration FROM $table
             WHERE expert_id = %d
             AND booking_date = %s
             AND status IN ('pending', 'confirmed')
             AND id != %d",
            $expert_id, $date, $exclude_id
        ));

        foreach ($existing as $booking) {
            $existing_start = strtotime($date . ' ' . $booking->booking_time);
            $existing_end = $existing_start + ($booking->duration * 60);

            // Check overlap
            if ($new_start < $existing_end && $new_end > $existing_start) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update booking status
     *
     * @param int    $booking_id Booking ID
     * @param string $status     New status
     * @param string $expert_note Optional note from expert
     * @return bool Success
     */
    public function update_status($booking_id, $status, $expert_note = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_bookings';

        $valid_statuses = array(self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_CANCELLED, self::STATUS_COMPLETED);
        if (!in_array($status, $valid_statuses)) {
            return false;
        }

        $data = array('status' => $status);
        $format = array('%s');

        if ($expert_note !== null) {
            $data['expert_note'] = sanitize_textarea_field($expert_note);
            $format[] = '%s';
        }

        $result = $wpdb->update($table, $data, array('id' => $booking_id), $format, array('%d'));

        if ($result !== false) {
            $this->send_booking_notification($booking_id, $status);
            return true;
        }

        return false;
    }

    /**
     * Get a single booking
     *
     * @param int $booking_id Booking ID
     * @return object|null Booking object
     */
    public function get_booking($booking_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_bookings';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $booking_id
        ));
    }

    /**
     * Get bookings for an expert
     *
     * @param int    $expert_id Expert post ID
     * @param string $status    Optional status filter
     * @param string $from_date Optional start date filter
     * @param int    $limit     Number of bookings to return
     * @return array Booking objects
     */
    public function get_expert_bookings($expert_id, $status = '', $from_date = '', $limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_bookings';

        $sql = "SELECT b.*, u.display_name as user_name, u.user_email as user_email
                FROM $table b
                LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
                WHERE b.expert_id = %d";
        $params = array($expert_id);

        if ($status) {
            $sql .= " AND b.status = %s";
            $params[] = $status;
        }

        if ($from_date) {
            $sql .= " AND b.booking_date >= %s";
            $params[] = $from_date;
        }

        $sql .= " ORDER BY b.booking_date ASC, b.booking_time ASC LIMIT %d";
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Get bookings for a user
     *
     * @param int    $user_id User ID
     * @param string $status  Optional status filter
     * @param int    $limit   Number of bookings to return
     * @return array Booking objects
     */
    public function get_user_bookings($user_id, $status = '', $limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_bookings';

        $sql = "SELECT b.*, p.post_title as expert_name
                FROM $table b
                LEFT JOIN {$wpdb->posts} p ON b.expert_id = p.ID
                WHERE b.user_id = %d";
        $params = array($user_id);

        if ($status) {
            $sql .= " AND b.status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY b.booking_date DESC, b.booking_time DESC LIMIT %d";
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Get booked time slots for an expert on a given date
     *
     * @param int    $expert_id Expert post ID
     * @param string $date      Date (Y-m-d)
     * @return array Array of booked time strings (H:i)
     */
    public function get_booked_slots($expert_id, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_bookings';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT booking_time, duration FROM $table
             WHERE expert_id = %d
             AND booking_date = %s
             AND status IN ('pending', 'confirmed')",
            $expert_id, $date
        ));

        $booked = array();
        foreach ($results as $row) {
            $start = strtotime($date . ' ' . $row->booking_time);
            $end = $start + ($row->duration * 60);
            // Mark each 30-min slot as booked
            $current = $start;
            while ($current < $end) {
                $booked[] = date('H:i', $current);
                $current += 1800; // 30 minutes
            }
        }

        return array_unique($booked);
    }

    /**
     * Count pending bookings for an expert
     *
     * @param int $expert_id Expert post ID
     * @return int Number of pending bookings
     */
    public function count_pending($expert_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_bookings';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE expert_id = %d AND status = 'pending'",
            $expert_id
        ));
    }

    /**
     * Send email notification for booking events
     *
     * @param int    $booking_id Booking ID
     * @param string $event      Event type (new, confirmed, cancelled, completed)
     */
    private function send_booking_notification($booking_id, $event) {
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return;
        }

        $expert_name = get_the_title($booking->expert_id);
        $expert_email = get_post_meta($booking->expert_id, '_rfm_email', true);
        $user = get_user_by('ID', $booking->user_id);

        if (!$user || !$expert_email) {
            return;
        }

        $date_formatted = date_i18n('d. F Y', strtotime($booking->booking_date));
        $time_formatted = date_i18n('H:i', strtotime($booking->booking_time));
        $site_name = get_bloginfo('name');

        $headers = array('Content-Type: text/html; charset=UTF-8');

        switch ($event) {
            case 'new':
                // Notify expert about new booking request
                $subject = sprintf(__('[%s] Ny booking-anmodning fra %s', 'rigtig-for-mig'), $site_name, $user->display_name);
                $message = sprintf(
                    '<h2>%s</h2>
                    <p>%s har anmodet om en booking hos dig.</p>
                    <table style="border-collapse: collapse; width: 100%%;">
                        <tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>
                        <tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>
                        <tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%d %s</td></tr>
                        %s
                    </table>
                    <p><a href="%s" style="display:inline-block;padding:10px 20px;background:#4CAF50;color:#fff;text-decoration:none;border-radius:5px;">%s</a></p>',
                    __('Ny booking-anmodning', 'rigtig-for-mig'),
                    esc_html($user->display_name),
                    __('Dato', 'rigtig-for-mig'), $date_formatted,
                    __('Tidspunkt', 'rigtig-for-mig'), $time_formatted,
                    __('Varighed', 'rigtig-for-mig'), $booking->duration, __('minutter', 'rigtig-for-mig'),
                    $booking->note ? '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . __('Besked', 'rigtig-for-mig') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($booking->note) . '</td></tr>' : '',
                    home_url('/ekspert-dashboard/'),
                    __('Gå til dashboard', 'rigtig-for-mig')
                );
                wp_mail($expert_email, $subject, $message, $headers);

                // Also notify user about their booking request
                $user_subject = sprintf(__('[%s] Din booking-anmodning er sendt', 'rigtig-for-mig'), $site_name);
                $user_message = sprintf(
                    '<h2>%s</h2>
                    <p>%s</p>
                    <table style="border-collapse: collapse; width: 100%%;">
                        <tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>
                        <tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>
                        <tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>
                    </table>
                    <p>%s</p>',
                    __('Booking-anmodning sendt', 'rigtig-for-mig'),
                    sprintf(__('Du har sendt en booking-anmodning til %s.', 'rigtig-for-mig'), esc_html($expert_name)),
                    __('Ekspert', 'rigtig-for-mig'), esc_html($expert_name),
                    __('Dato', 'rigtig-for-mig'), $date_formatted,
                    __('Tidspunkt', 'rigtig-for-mig'), $time_formatted,
                    __('Du får besked når eksperten accepterer eller afviser din booking.', 'rigtig-for-mig')
                );
                wp_mail($user->user_email, $user_subject, $user_message, $headers);
                break;

            case 'confirmed':
                // Notify user that booking is confirmed
                $subject = sprintf(__('[%s] Din booking hos %s er bekræftet!', 'rigtig-for-mig'), $site_name, $expert_name);

                // Generate calendar links
                $calendar_links = $this->get_calendar_links($booking);

                $calendar_html = sprintf(
                    '<div style="margin-top: 20px; padding: 15px; background: #f0f7ff; border-radius: 8px; border: 1px solid #d0e3f7;">
                        <p style="margin: 0 0 10px 0; font-weight: 600; color: #333;">%s</p>
                        <p style="margin: 0;">
                            <a href="%s" target="_blank" style="display: inline-block; padding: 8px 16px; background: #4285F4; color: #fff; text-decoration: none; border-radius: 5px; margin-right: 8px; margin-bottom: 5px;">Google Kalender</a>
                            <a href="%s" target="_blank" style="display: inline-block; padding: 8px 16px; background: #0078D4; color: #fff; text-decoration: none; border-radius: 5px; margin-right: 8px; margin-bottom: 5px;">Outlook</a>
                            <a href="%s" download="booking.ics" style="display: inline-block; padding: 8px 16px; background: #555; color: #fff; text-decoration: none; border-radius: 5px; margin-bottom: 5px;">Apple Kalender (.ics)</a>
                        </p>
                    </div>',
                    __('Føj til din kalender:', 'rigtig-for-mig'),
                    esc_url($calendar_links['google']),
                    esc_url($calendar_links['outlook']),
                    esc_attr($calendar_links['ics'])
                );

                $message = sprintf(
                    '<h2 style="color: #4CAF50;">%s</h2>
                    <p>%s</p>
                    <table style="border-collapse: collapse; width: 100%%;">
                        <tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>
                        <tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>
                        <tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>
                        %s
                    </table>
                    %s',
                    __('Booking bekræftet!', 'rigtig-for-mig'),
                    sprintf(__('Din booking hos %s er nu bekræftet.', 'rigtig-for-mig'), esc_html($expert_name)),
                    __('Ekspert', 'rigtig-for-mig'), esc_html($expert_name),
                    __('Dato', 'rigtig-for-mig'), $date_formatted,
                    __('Tidspunkt', 'rigtig-for-mig'), $time_formatted,
                    $booking->expert_note ? '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . __('Besked fra ekspert', 'rigtig-for-mig') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($booking->expert_note) . '</td></tr>' : '',
                    $calendar_html
                );
                wp_mail($user->user_email, $subject, $message, $headers);
                break;

            case 'cancelled':
                // Notify user that booking is cancelled
                $subject = sprintf(__('[%s] Din booking hos %s er aflyst', 'rigtig-for-mig'), $site_name, $expert_name);
                $message = sprintf(
                    '<h2 style="color: #e74c3c;">%s</h2>
                    <p>%s</p>
                    <table style="border-collapse: collapse; width: 100%%;">
                        <tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>
                        <tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>
                        <tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>%s</strong></td><td style="padding: 8px; border: 1px solid #ddd;">%s</td></tr>
                        %s
                    </table>
                    <p>%s</p>',
                    __('Booking aflyst', 'rigtig-for-mig'),
                    sprintf(__('Din booking hos %s er blevet aflyst.', 'rigtig-for-mig'), esc_html($expert_name)),
                    __('Ekspert', 'rigtig-for-mig'), esc_html($expert_name),
                    __('Dato', 'rigtig-for-mig'), $date_formatted,
                    __('Tidspunkt', 'rigtig-for-mig'), $time_formatted,
                    $booking->expert_note ? '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>' . __('Årsag', 'rigtig-for-mig') . '</strong></td><td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($booking->expert_note) . '</td></tr>' : '',
                    sprintf('<a href="%s">%s</a>', get_permalink($booking->expert_id), __('Se ekspertens profil for at booke et nyt tidspunkt', 'rigtig-for-mig'))
                );
                wp_mail($user->user_email, $subject, $message, $headers);
                break;
        }
    }

    /**
     * Render booking calendar HTML for expert profile
     *
     * @param int $expert_id Expert post ID
     * @return string HTML
     */
    public function render_booking_calendar($expert_id) {
        // Check if internal booking is enabled
        $booking_mode = get_post_meta($expert_id, '_rfm_booking_mode', true);
        if ($booking_mode !== 'internal') {
            return '';
        }

        if (!RFM_Subscriptions::can_use_feature($expert_id, 'booking')) {
            return '';
        }

        $expert_name = get_the_title($expert_id);
        $duration = (int) get_post_meta($expert_id, '_rfm_booking_duration', true);
        if (!$duration) {
            $duration = 60;
        }

        ob_start();
        ?>
        <div class="rfm-booking-calendar-section" id="rfm-booking-calendar"
             data-expert-id="<?php echo esc_attr($expert_id); ?>"
             data-duration="<?php echo esc_attr($duration); ?>">

            <h3><i class="dashicons dashicons-calendar-alt"></i> <?php printf(__('Book tid hos %s', 'rigtig-for-mig'), esc_html($expert_name)); ?></h3>

            <div class="rfm-booking-calendar-wrapper">
                <!-- Calendar Navigation -->
                <div class="rfm-calendar-nav">
                    <button type="button" class="rfm-calendar-prev rfm-btn rfm-btn-small">&laquo; <?php _e('Forrige', 'rigtig-for-mig'); ?></button>
                    <span class="rfm-calendar-month-label"></span>
                    <button type="button" class="rfm-calendar-next rfm-btn rfm-btn-small"><?php _e('Næste', 'rigtig-for-mig'); ?> &raquo;</button>
                </div>

                <!-- Calendar Grid -->
                <div class="rfm-calendar-grid">
                    <div class="rfm-calendar-header">
                        <span><?php _e('Man', 'rigtig-for-mig'); ?></span>
                        <span><?php _e('Tir', 'rigtig-for-mig'); ?></span>
                        <span><?php _e('Ons', 'rigtig-for-mig'); ?></span>
                        <span><?php _e('Tor', 'rigtig-for-mig'); ?></span>
                        <span><?php _e('Fre', 'rigtig-for-mig'); ?></span>
                        <span><?php _e('Lør', 'rigtig-for-mig'); ?></span>
                        <span><?php _e('Søn', 'rigtig-for-mig'); ?></span>
                    </div>
                    <div class="rfm-calendar-days"></div>
                </div>

                <!-- Time Slots -->
                <div class="rfm-time-slots" style="display: none;">
                    <h4 class="rfm-time-slots-date"></h4>
                    <div class="rfm-time-slots-grid"></div>
                </div>

                <!-- Booking Form -->
                <div class="rfm-booking-form-container" style="display: none;">
                    <h4><?php _e('Bekræft din booking', 'rigtig-for-mig'); ?></h4>
                    <div class="rfm-booking-summary">
                        <p><strong><?php _e('Dato:', 'rigtig-for-mig'); ?></strong> <span class="rfm-booking-selected-date"></span></p>
                        <p><strong><?php _e('Tid:', 'rigtig-for-mig'); ?></strong> <span class="rfm-booking-selected-time"></span></p>
                        <p><strong><?php _e('Varighed:', 'rigtig-for-mig'); ?></strong> <?php echo $duration; ?> <?php _e('minutter', 'rigtig-for-mig'); ?></p>
                    </div>

                    <?php if (is_user_logged_in()):
                        $current_user_id = get_current_user_id();
                        $user_phone = get_user_meta($current_user_id, '_rfm_phone', true);
                    ?>
                    <form id="rfm-booking-submit-form">
                        <input type="hidden" name="expert_id" value="<?php echo esc_attr($expert_id); ?>" />
                        <input type="hidden" name="booking_date" value="" />
                        <input type="hidden" name="booking_time" value="" />
                        <input type="hidden" name="duration" value="<?php echo esc_attr($duration); ?>" />

                        <div class="rfm-form-group">
                            <label for="rfm-booking-phone"><?php _e('Telefonnummer', 'rigtig-for-mig'); ?> <span class="required">*</span></label>
                            <input type="tel" id="rfm-booking-phone" name="user_phone"
                                   value="<?php echo esc_attr($user_phone); ?>"
                                   placeholder="<?php esc_attr_e('Dit telefonnummer', 'rigtig-for-mig'); ?>"
                                   required />
                            <small class="rfm-field-hint"><?php _e('Så eksperten kan kontakte dig vedr. bookingen.', 'rigtig-for-mig'); ?></small>
                        </div>

                        <div class="rfm-form-group">
                            <label for="rfm-booking-note"><?php _e('Besked til eksperten (valgfrit)', 'rigtig-for-mig'); ?></label>
                            <textarea id="rfm-booking-note" name="note" rows="3"
                                      placeholder="<?php esc_attr_e('Fortæl kort hvad du gerne vil tale om...', 'rigtig-for-mig'); ?>"></textarea>
                        </div>

                        <div class="rfm-booking-form-actions">
                            <button type="button" class="rfm-btn rfm-btn-secondary rfm-booking-back">
                                <?php _e('Tilbage', 'rigtig-for-mig'); ?>
                            </button>
                            <button type="submit" class="rfm-btn rfm-btn-primary rfm-btn-booking-confirm">
                                <i class="dashicons dashicons-calendar-alt"></i>
                                <?php _e('Book tid', 'rigtig-for-mig'); ?>
                            </button>
                        </div>

                        <div id="rfm-booking-form-message" class="rfm-form-message" style="display: none;"></div>
                    </form>
                    <?php else: ?>
                    <div class="rfm-booking-login-prompt">
                        <p><?php _e('Du skal være logget ind for at booke en tid.', 'rigtig-for-mig'); ?></p>
                        <a href="<?php echo home_url('/login/?redirect_to=' . urlencode(get_permalink())); ?>" class="rfm-btn rfm-btn-primary">
                            <?php _e('Log ind', 'rigtig-for-mig'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render booking management section for expert dashboard
     *
     * @param int $expert_id Expert post ID
     * @return string HTML
     */
    public function render_expert_bookings_dashboard($expert_id) {
        $today = current_time('Y-m-d');
        $pending_bookings = $this->get_expert_bookings($expert_id, self::STATUS_PENDING);
        $upcoming_bookings = $this->get_expert_bookings($expert_id, self::STATUS_CONFIRMED, $today);
        $past_bookings = $this->get_expert_bookings($expert_id, '', '', 20);

        // Filter past bookings to only completed/cancelled ones
        $past_bookings = array_filter($past_bookings, function($b) use ($today) {
            return $b->booking_date < $today || in_array($b->status, array('cancelled', 'completed'));
        });
        $past_bookings = array_slice($past_bookings, 0, 20);

        $booking_mode = get_post_meta($expert_id, '_rfm_booking_mode', true);
        $duration = (int) get_post_meta($expert_id, '_rfm_booking_duration', true);
        if (!$duration) $duration = 60;

        ob_start();
        ?>
        <div class="rfm-booking-dashboard">
            <!-- Booking Mode Selection -->
            <div class="rfm-form-section">
                <h3><?php _e('Booking-tilstand', 'rigtig-for-mig'); ?></h3>
                <p class="rfm-form-description"><?php _e('Vælg hvordan brugere kan booke dig.', 'rigtig-for-mig'); ?></p>

                <div class="rfm-booking-mode-selector">
                    <label class="rfm-radio-card <?php echo ($booking_mode === 'external' || (!$booking_mode && get_post_meta($expert_id, '_rfm_booking_url', true))) ? 'active' : ''; ?>">
                        <input type="radio" name="booking_mode" value="external"
                               <?php checked($booking_mode, 'external'); ?>
                               <?php if (!$booking_mode && get_post_meta($expert_id, '_rfm_booking_url', true)) echo 'checked'; ?>>
                        <div class="rfm-radio-card-content">
                            <i class="dashicons dashicons-external"></i>
                            <strong><?php _e('Eksternt booking-link', 'rigtig-for-mig'); ?></strong>
                            <p><?php _e('Link til dit eget booking-system (Calendly, Cal.com, osv.)', 'rigtig-for-mig'); ?></p>
                        </div>
                    </label>

                    <label class="rfm-radio-card <?php echo $booking_mode === 'internal' ? 'active' : ''; ?>">
                        <input type="radio" name="booking_mode" value="internal" <?php checked($booking_mode, 'internal'); ?>>
                        <div class="rfm-radio-card-content">
                            <i class="dashicons dashicons-calendar-alt"></i>
                            <strong><?php _e('Internt booking-system', 'rigtig-for-mig'); ?></strong>
                            <p><?php _e('Brug vores indbyggede kalender med tilgængelighed og booking-styring.', 'rigtig-for-mig'); ?></p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- External Booking Settings (re-use existing) -->
            <div class="rfm-booking-mode-panel" id="rfm-booking-external-panel" style="<?php echo $booking_mode === 'internal' ? 'display:none;' : ''; ?>">
                <?php echo RFM_Booking_Link::get_instance()->render_booking_settings_form($expert_id); ?>
            </div>

            <!-- Internal Booking Settings -->
            <div class="rfm-booking-mode-panel" id="rfm-booking-internal-panel" style="<?php echo $booking_mode !== 'internal' ? 'display:none;' : ''; ?>">

                <!-- Session Duration -->
                <div class="rfm-form-section">
                    <h3><?php _e('Sessions-varighed', 'rigtig-for-mig'); ?></h3>
                    <div class="rfm-form-group">
                        <select id="rfm-booking-duration" name="booking_duration">
                            <option value="30" <?php selected($duration, 30); ?>>30 <?php _e('minutter', 'rigtig-for-mig'); ?></option>
                            <option value="45" <?php selected($duration, 45); ?>>45 <?php _e('minutter', 'rigtig-for-mig'); ?></option>
                            <option value="60" <?php selected($duration, 60); ?>>60 <?php _e('minutter', 'rigtig-for-mig'); ?></option>
                            <option value="90" <?php selected($duration, 90); ?>>90 <?php _e('minutter', 'rigtig-for-mig'); ?></option>
                            <option value="120" <?php selected($duration, 120); ?>>120 <?php _e('minutter', 'rigtig-for-mig'); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Availability Management -->
                <div class="rfm-form-section">
                    <h3><?php _e('Tilgængelighed', 'rigtig-for-mig'); ?></h3>
                    <p class="rfm-form-description"><?php _e('Angiv hvornår du er tilgængelig for bookinger.', 'rigtig-for-mig'); ?></p>
                    <?php echo RFM_Availability::get_instance()->render_availability_form($expert_id); ?>
                </div>

                <div class="rfm-form-actions">
                    <button type="button" id="rfm-save-internal-booking" class="rfm-btn rfm-btn-primary">
                        <i class="dashicons dashicons-yes"></i>
                        <?php _e('Gem booking-indstillinger', 'rigtig-for-mig'); ?>
                    </button>
                </div>
                <div id="rfm-internal-booking-message" class="rfm-form-message" style="display: none;"></div>

                <!-- Pending Bookings -->
                <?php if (!empty($pending_bookings)): ?>
                <div class="rfm-form-section rfm-booking-list-section">
                    <h3>
                        <?php _e('Ventende bookinger', 'rigtig-for-mig'); ?>
                        <span class="rfm-badge rfm-badge-warning"><?php echo count($pending_bookings); ?></span>
                    </h3>
                    <div class="rfm-booking-list">
                        <?php foreach ($pending_bookings as $booking): ?>
                        <div class="rfm-booking-card rfm-booking-pending" data-booking-id="<?php echo esc_attr($booking->id); ?>">
                            <div class="rfm-booking-card-header">
                                <span class="rfm-booking-date">
                                    <?php echo date_i18n('d. M Y', strtotime($booking->booking_date)); ?>
                                    <?php _e('kl.', 'rigtig-for-mig'); ?>
                                    <?php echo date_i18n('H:i', strtotime($booking->booking_time)); ?>
                                </span>
                                <span class="rfm-booking-status rfm-status-pending"><?php _e('Afventer', 'rigtig-for-mig'); ?></span>
                            </div>
                            <div class="rfm-booking-card-body">
                                <p><strong><?php _e('Bruger:', 'rigtig-for-mig'); ?></strong> <?php echo esc_html($booking->user_name); ?></p>
                                <?php if ($booking->note): ?>
                                <p><strong><?php _e('Besked:', 'rigtig-for-mig'); ?></strong> <?php echo esc_html($booking->note); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="rfm-booking-card-actions">
                                <button type="button" class="rfm-btn rfm-btn-small rfm-btn-confirm-booking" data-id="<?php echo esc_attr($booking->id); ?>">
                                    <i class="dashicons dashicons-yes"></i> <?php _e('Accepter', 'rigtig-for-mig'); ?>
                                </button>
                                <button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger rfm-btn-cancel-booking" data-id="<?php echo esc_attr($booking->id); ?>">
                                    <i class="dashicons dashicons-no"></i> <?php _e('Afvis', 'rigtig-for-mig'); ?>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Upcoming Bookings -->
                <?php if (!empty($upcoming_bookings)): ?>
                <div class="rfm-form-section rfm-booking-list-section">
                    <h3><?php _e('Kommende bookinger', 'rigtig-for-mig'); ?></h3>
                    <div class="rfm-booking-list">
                        <?php foreach ($upcoming_bookings as $booking):
                            $cal_links = $this->get_calendar_links($booking);
                        ?>
                        <div class="rfm-booking-card rfm-booking-confirmed">
                            <div class="rfm-booking-card-header">
                                <span class="rfm-booking-date">
                                    <?php echo date_i18n('d. M Y', strtotime($booking->booking_date)); ?>
                                    <?php _e('kl.', 'rigtig-for-mig'); ?>
                                    <?php echo date_i18n('H:i', strtotime($booking->booking_time)); ?>
                                </span>
                                <span class="rfm-booking-status rfm-status-confirmed"><?php _e('Bekræftet', 'rigtig-for-mig'); ?></span>
                            </div>
                            <div class="rfm-booking-card-body">
                                <p><strong><?php _e('Bruger:', 'rigtig-for-mig'); ?></strong> <?php echo esc_html($booking->user_name); ?></p>
                                <p><strong><?php _e('E-mail:', 'rigtig-for-mig'); ?></strong> <a href="mailto:<?php echo esc_attr($booking->user_email); ?>"><?php echo esc_html($booking->user_email); ?></a></p>
                                <?php if (!empty($booking->user_phone)): ?>
                                <p><strong><?php _e('Telefon:', 'rigtig-for-mig'); ?></strong> <a href="tel:<?php echo esc_attr($booking->user_phone); ?>"><?php echo esc_html($booking->user_phone); ?></a></p>
                                <?php endif; ?>
                            </div>
                            <div class="rfm-booking-card-actions rfm-calendar-actions">
                                <span class="rfm-calendar-label"><?php _e('Føj til kalender:', 'rigtig-for-mig'); ?></span>
                                <a href="<?php echo esc_url($cal_links['google']); ?>" target="_blank" class="rfm-btn rfm-btn-small rfm-btn-calendar rfm-btn-google" title="Google Kalender">
                                    <i class="dashicons dashicons-google"></i> Google
                                </a>
                                <a href="<?php echo esc_url($cal_links['outlook']); ?>" target="_blank" class="rfm-btn rfm-btn-small rfm-btn-calendar rfm-btn-outlook" title="Outlook">
                                    <i class="dashicons dashicons-email"></i> Outlook
                                </a>
                                <a href="<?php echo esc_attr($cal_links['ics']); ?>" download="booking-<?php echo esc_attr($booking->id); ?>.ics" class="rfm-btn rfm-btn-small rfm-btn-calendar rfm-btn-ics" title="Apple Kalender / .ics">
                                    <i class="dashicons dashicons-calendar-alt"></i> .ics
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (empty($pending_bookings) && empty($upcoming_bookings)): ?>
                <div class="rfm-form-section">
                    <p style="text-align: center; color: #666; padding: 20px;">
                        <i class="dashicons dashicons-calendar-alt" style="font-size: 32px; opacity: 0.3; display: block; margin-bottom: 10px;"></i>
                        <?php _e('Du har ingen bookinger endnu.', 'rigtig-for-mig'); ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Past Bookings -->
                <?php if (!empty($past_bookings)): ?>
                <div class="rfm-form-section rfm-booking-list-section">
                    <h3><?php _e('Tidligere bookinger', 'rigtig-for-mig'); ?></h3>
                    <div class="rfm-booking-list">
                        <?php foreach ($past_bookings as $booking):
                            $status_label = $this->get_status_label($booking->status);
                            $status_class = 'rfm-status-' . $booking->status;
                        ?>
                        <div class="rfm-booking-card rfm-booking-<?php echo esc_attr($booking->status); ?>" data-booking-id="<?php echo esc_attr($booking->id); ?>">
                            <div class="rfm-booking-card-header">
                                <span class="rfm-booking-date">
                                    <?php echo date_i18n('d. M Y', strtotime($booking->booking_date)); ?>
                                    <?php _e('kl.', 'rigtig-for-mig'); ?>
                                    <?php echo date_i18n('H:i', strtotime($booking->booking_time)); ?>
                                </span>
                                <span class="rfm-booking-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                            </div>
                            <div class="rfm-booking-card-body">
                                <p><strong><?php _e('Bruger:', 'rigtig-for-mig'); ?></strong> <?php echo esc_html($booking->user_name); ?></p>
                            </div>
                            <div class="rfm-booking-card-actions">
                                <button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger rfm-btn-delete-booking" data-id="<?php echo esc_attr($booking->id); ?>" data-role="expert">
                                    <i class="dashicons dashicons-trash"></i> <?php _e('Slet', 'rigtig-for-mig'); ?>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render user's bookings list for user dashboard
     *
     * @param int $user_id User ID
     * @return string HTML
     */
    public function render_user_bookings($user_id) {
        $bookings = $this->get_user_bookings($user_id);

        ob_start();
        ?>
        <div class="rfm-user-bookings">
            <h3><i class="dashicons dashicons-calendar-alt"></i> <?php _e('Mine bookinger', 'rigtig-for-mig'); ?></h3>

            <?php if (empty($bookings)): ?>
            <p style="text-align: center; color: #666; padding: 20px;">
                <?php _e('Du har ingen bookinger endnu.', 'rigtig-for-mig'); ?>
            </p>
            <?php else: ?>
            <div class="rfm-booking-list">
                <?php foreach ($bookings as $booking):
                    $status_class = 'rfm-status-' . $booking->status;
                    $status_label = $this->get_status_label($booking->status);
                    $is_upcoming = $booking->booking_date >= current_time('Y-m-d') && in_array($booking->status, array('pending', 'confirmed'));
                ?>
                <div class="rfm-booking-card rfm-booking-<?php echo esc_attr($booking->status); ?>">
                    <div class="rfm-booking-card-header">
                        <span class="rfm-booking-date">
                            <?php echo date_i18n('d. M Y', strtotime($booking->booking_date)); ?>
                            <?php _e('kl.', 'rigtig-for-mig'); ?>
                            <?php echo date_i18n('H:i', strtotime($booking->booking_time)); ?>
                        </span>
                        <span class="rfm-booking-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                    </div>
                    <div class="rfm-booking-card-body">
                        <p>
                            <strong><?php _e('Ekspert:', 'rigtig-for-mig'); ?></strong>
                            <a href="<?php echo get_permalink($booking->expert_id); ?>"><?php echo esc_html($booking->expert_name); ?></a>
                        </p>
                        <?php if ($booking->expert_note): ?>
                        <p><strong><?php _e('Besked fra ekspert:', 'rigtig-for-mig'); ?></strong> <?php echo esc_html($booking->expert_note); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_upcoming && $booking->status === 'pending'): ?>
                    <div class="rfm-booking-card-actions">
                        <button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger rfm-btn-user-cancel-booking" data-id="<?php echo esc_attr($booking->id); ?>">
                            <i class="dashicons dashicons-no"></i> <?php _e('Annuller', 'rigtig-for-mig'); ?>
                        </button>
                    </div>
                    <?php elseif (!$is_upcoming || $booking->status === 'cancelled'): ?>
                    <div class="rfm-booking-card-actions">
                        <button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger rfm-btn-delete-booking" data-id="<?php echo esc_attr($booking->id); ?>" data-role="user">
                            <i class="dashicons dashicons-trash"></i> <?php _e('Slet', 'rigtig-for-mig'); ?>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Delete a booking (only past or cancelled bookings)
     *
     * @param int    $booking_id Booking ID
     * @param int    $requester_id User ID of the person deleting
     * @param string $role 'expert' or 'user'
     * @return bool Success
     */
    public function delete_booking($booking_id, $requester_id, $role = 'user') {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_bookings';

        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return false;
        }

        // Verify ownership
        if ($role === 'expert') {
            $post = get_post($booking->expert_id);
            if (!$post || $post->post_author != $requester_id) {
                return false;
            }
        } else {
            if ($booking->user_id != $requester_id) {
                return false;
            }
        }

        // Only allow deletion of past or cancelled bookings
        $today = current_time('Y-m-d');
        $is_past = $booking->booking_date < $today;
        $is_cancelled = $booking->status === self::STATUS_CANCELLED;

        if (!$is_past && !$is_cancelled) {
            return false;
        }

        $result = $wpdb->delete($table, array('id' => $booking_id), array('%d'));
        return $result !== false;
    }

    /**
     * Generate "Add to Calendar" links for a booking
     *
     * @param object $booking Booking object
     * @return array Array with 'google', 'outlook', 'ics' URLs/content
     */
    public function get_calendar_links($booking) {
        $expert_name = get_the_title($booking->expert_id);
        $user = get_user_by('ID', $booking->user_id);

        $title = sprintf('Booking: %s', $expert_name);
        $description = sprintf(
            "Booking hos %s\nVarighed: %d minutter",
            $expert_name,
            $booking->duration
        );
        if (!empty($booking->note)) {
            $description .= "\nNote: " . $booking->note;
        }

        // Build start and end datetimes
        $start_datetime = $booking->booking_date . ' ' . $booking->booking_time;
        $end_timestamp = strtotime($start_datetime) + ($booking->duration * 60);

        // Google Calendar format: YYYYMMDDTHHmmSSZ (UTC)
        $google_start = gmdate('Ymd\THis\Z', strtotime($start_datetime));
        $google_end = gmdate('Ymd\THis\Z', $end_timestamp);

        $google_url = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            . '&text=' . rawurlencode($title)
            . '&dates=' . $google_start . '/' . $google_end
            . '&details=' . rawurlencode($description);

        // Outlook Web format
        $outlook_start = gmdate('Y-m-d\TH:i:s\Z', strtotime($start_datetime));
        $outlook_end = gmdate('Y-m-d\TH:i:s\Z', $end_timestamp);

        $outlook_url = 'https://outlook.live.com/calendar/0/deeplink/compose?subject='
            . rawurlencode($title)
            . '&startdt=' . rawurlencode($outlook_start)
            . '&enddt=' . rawurlencode($outlook_end)
            . '&body=' . rawurlencode($description);

        // ICS content
        $ics_start = gmdate('Ymd\THis\Z', strtotime($start_datetime));
        $ics_end = gmdate('Ymd\THis\Z', $end_timestamp);
        $ics_now = gmdate('Ymd\THis\Z');
        $uid = 'rfm-booking-' . $booking->id . '@' . parse_url(home_url(), PHP_URL_HOST);

        $ics_content = "BEGIN:VCALENDAR\r\n"
            . "VERSION:2.0\r\n"
            . "PRODID:-//Rigtig for mig//Booking//DA\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:" . $uid . "\r\n"
            . "DTSTAMP:" . $ics_now . "\r\n"
            . "DTSTART:" . $ics_start . "\r\n"
            . "DTEND:" . $ics_end . "\r\n"
            . "SUMMARY:" . $this->ics_escape($title) . "\r\n"
            . "DESCRIPTION:" . $this->ics_escape($description) . "\r\n"
            . "END:VEVENT\r\n"
            . "END:VCALENDAR";

        // Create a data URI for the .ics file
        $ics_data_uri = 'data:text/calendar;charset=utf-8,' . rawurlencode($ics_content);

        return array(
            'google'  => $google_url,
            'outlook' => $outlook_url,
            'ics'     => $ics_data_uri,
        );
    }

    /**
     * Escape text for ICS format
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    private function ics_escape($text) {
        $text = str_replace(array("\r\n", "\n", "\r"), "\\n", $text);
        $text = str_replace(array(",", ";", "\\"), array("\\,", "\\;", "\\\\"), $text);
        return $text;
    }

    /**
     * Get human-readable status label
     *
     * @param string $status Status key
     * @return string Translated label
     */
    public function get_status_label($status) {
        $labels = array(
            'pending'   => __('Afventer', 'rigtig-for-mig'),
            'confirmed' => __('Bekræftet', 'rigtig-for-mig'),
            'cancelled' => __('Aflyst', 'rigtig-for-mig'),
            'completed' => __('Gennemført', 'rigtig-for-mig'),
        );
        return $labels[$status] ?? $status;
    }
}
