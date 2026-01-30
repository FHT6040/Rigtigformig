<?php
/**
 * Expert Availability Management
 *
 * Handles availability schedules for experts using the internal
 * booking system. Experts can set which days/times they're available.
 *
 * @package Rigtig_For_Mig
 * @since 3.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Availability {

    private static $instance = null;

    /**
     * Day names in Danish (Monday=1 ... Sunday=7)
     */
    private static $day_names = array(
        1 => 'Mandag',
        2 => 'Tirsdag',
        3 => 'Onsdag',
        4 => 'Torsdag',
        5 => 'Fredag',
        6 => 'Lørdag',
        7 => 'Søndag',
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // No hooks needed
    }

    /**
     * Get availability schedule for an expert
     *
     * @param int $expert_id Expert post ID
     * @return array Array of availability rows
     */
    public function get_availability($expert_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_availability';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE expert_id = %d ORDER BY day_of_week ASC, start_time ASC",
            $expert_id
        ));
    }

    /**
     * Get availability for a specific day
     *
     * @param int $expert_id  Expert post ID
     * @param int $day_of_week Day (1=Monday ... 7=Sunday)
     * @return array Availability rows for that day
     */
    public function get_day_availability($expert_id, $day_of_week) {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_availability';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE expert_id = %d AND day_of_week = %d AND is_active = 1 ORDER BY start_time ASC",
            $expert_id, $day_of_week
        ));
    }

    /**
     * Save availability schedule for an expert
     * Replaces all existing availability entries
     *
     * @param int   $expert_id Expert post ID
     * @param array $schedule  Array of schedule entries
     * @return bool Success
     */
    public function save_availability($expert_id, $schedule) {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_availability';

        // Delete existing schedule
        $wpdb->delete($table, array('expert_id' => $expert_id), array('%d'));

        // Insert new schedule
        foreach ($schedule as $entry) {
            $day = intval($entry['day_of_week'] ?? 0);
            $start = sanitize_text_field($entry['start_time'] ?? '');
            $end = sanitize_text_field($entry['end_time'] ?? '');
            $active = !empty($entry['is_active']) ? 1 : 0;

            if ($day < 1 || $day > 7 || empty($start) || empty($end)) {
                continue;
            }

            // Validate time format
            if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
                continue;
            }

            // Ensure end > start
            if (strtotime($end) <= strtotime($start)) {
                continue;
            }

            $wpdb->insert($table, array(
                'expert_id'   => $expert_id,
                'day_of_week' => $day,
                'start_time'  => $start . ':00',
                'end_time'    => $end . ':00',
                'is_active'   => $active,
            ), array('%d', '%d', '%s', '%s', '%d'));
        }

        return true;
    }

    /**
     * Check if a specific time is available for an expert
     *
     * @param int    $expert_id Expert post ID
     * @param string $date      Date (Y-m-d)
     * @param string $time      Time (H:i)
     * @return bool True if time is available
     */
    public function is_time_available($expert_id, $date, $time) {
        // Get day of week (1=Monday, 7=Sunday)
        $day_of_week = (int) date('N', strtotime($date));

        $slots = $this->get_day_availability($expert_id, $day_of_week);

        if (empty($slots)) {
            return false;
        }

        $check_time = strtotime($time);

        foreach ($slots as $slot) {
            $slot_start = strtotime(substr($slot->start_time, 0, 5));
            $slot_end = strtotime(substr($slot->end_time, 0, 5));

            if ($check_time >= $slot_start && $check_time < $slot_end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get available time slots for an expert on a given date
     *
     * @param int    $expert_id Expert post ID
     * @param string $date      Date (Y-m-d)
     * @param int    $duration  Session duration in minutes
     * @return array Available time slots (H:i strings)
     */
    public function get_available_slots($expert_id, $date, $duration = 60) {
        $day_of_week = (int) date('N', strtotime($date));
        $availability = $this->get_day_availability($expert_id, $day_of_week);

        if (empty($availability)) {
            return array();
        }

        // Get already booked slots
        $booked = RFM_Booking::get_instance()->get_booked_slots($expert_id, $date);

        $slots = array();
        $interval = 30; // 30-minute intervals

        foreach ($availability as $avail) {
            $start = strtotime(substr($avail->start_time, 0, 5));
            $end = strtotime(substr($avail->end_time, 0, 5));

            // Generate time slots within this availability window
            $current = $start;
            while ($current + ($duration * 60) <= $end) {
                $time_str = date('H:i', $current);

                // Check if this slot is not booked
                $is_booked = false;
                $slot_start = $current;
                $slot_end = $current + ($duration * 60);

                // Check each 30-min increment within this slot
                $check = $slot_start;
                while ($check < $slot_end) {
                    if (in_array(date('H:i', $check), $booked)) {
                        $is_booked = true;
                        break;
                    }
                    $check += 1800;
                }

                // Don't show past times for today
                if ($date === current_time('Y-m-d') && $current <= current_time('timestamp')) {
                    $is_booked = true;
                }

                if (!$is_booked) {
                    $slots[] = $time_str;
                }

                $current += $interval * 60;
            }
        }

        return array_unique($slots);
    }

    /**
     * Get available days for an expert (which days of week they work)
     *
     * @param int $expert_id Expert post ID
     * @return array Array of day numbers (1-7) that have availability
     */
    public function get_available_days($expert_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rfm_availability';

        $days = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT day_of_week FROM $table WHERE expert_id = %d AND is_active = 1 ORDER BY day_of_week ASC",
            $expert_id
        ));

        return array_map('intval', $days);
    }

    /**
     * Render availability settings form for expert dashboard
     *
     * @param int $expert_id Expert post ID
     * @return string HTML
     */
    public function render_availability_form($expert_id) {
        $availability = $this->get_availability($expert_id);

        // Organize by day
        $schedule = array();
        foreach ($availability as $row) {
            $schedule[$row->day_of_week][] = $row;
        }

        ob_start();
        ?>
        <div class="rfm-availability-form" id="rfm-availability-form">
            <?php for ($day = 1; $day <= 7; $day++):
                $day_name = self::$day_names[$day];
                $has_slots = !empty($schedule[$day]);
                $is_active = $has_slots;
            ?>
            <div class="rfm-availability-day" data-day="<?php echo $day; ?>">
                <div class="rfm-availability-day-header">
                    <label class="rfm-checkbox-label">
                        <input type="checkbox" class="rfm-day-toggle" value="<?php echo $day; ?>" <?php checked($is_active); ?>>
                        <strong><?php echo esc_html($day_name); ?></strong>
                    </label>
                </div>

                <div class="rfm-availability-slots" style="<?php echo !$is_active ? 'display:none;' : ''; ?>">
                    <?php if ($has_slots): ?>
                        <?php foreach ($schedule[$day] as $slot): ?>
                        <div class="rfm-time-slot-row">
                            <select class="rfm-time-start" name="availability[<?php echo $day; ?>][start][]">
                                <?php $this->render_time_options(substr($slot->start_time, 0, 5)); ?>
                            </select>
                            <span class="rfm-time-sep">&mdash;</span>
                            <select class="rfm-time-end" name="availability[<?php echo $day; ?>][end][]">
                                <?php $this->render_time_options(substr($slot->end_time, 0, 5)); ?>
                            </select>
                            <button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger rfm-remove-time-slot" title="<?php esc_attr_e('Fjern', 'rigtig-for-mig'); ?>">
                                <i class="dashicons dashicons-no"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="rfm-time-slot-row">
                            <select class="rfm-time-start" name="availability[<?php echo $day; ?>][start][]">
                                <?php $this->render_time_options('09:00'); ?>
                            </select>
                            <span class="rfm-time-sep">&mdash;</span>
                            <select class="rfm-time-end" name="availability[<?php echo $day; ?>][end][]">
                                <?php $this->render_time_options('17:00'); ?>
                            </select>
                            <button type="button" class="rfm-btn rfm-btn-small rfm-btn-danger rfm-remove-time-slot" title="<?php esc_attr_e('Fjern', 'rigtig-for-mig'); ?>">
                                <i class="dashicons dashicons-no"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <button type="button" class="rfm-btn rfm-btn-small rfm-btn-secondary rfm-add-time-slot" data-day="<?php echo $day; ?>">
                        + <?php _e('Tilføj tidsrum', 'rigtig-for-mig'); ?>
                    </button>
                </div>
            </div>
            <?php endfor; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render time select options
     *
     * @param string $selected Selected time (H:i)
     */
    private function render_time_options($selected = '') {
        for ($h = 6; $h <= 22; $h++) {
            for ($m = 0; $m < 60; $m += 30) {
                $time = sprintf('%02d:%02d', $h, $m);
                $display = $time;
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($time),
                    selected($time, $selected, false),
                    esc_html($display)
                );
            }
        }
    }
}
