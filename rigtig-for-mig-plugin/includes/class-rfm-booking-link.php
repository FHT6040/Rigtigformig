<?php
/**
 * External Booking Link Management
 *
 * Allows experts (Standard/Premium) to add an external booking link
 * to their profile (Calendly, Cal.com, Google Calendar, etc.)
 *
 * @package Rigtig_For_Mig
 * @since 3.9.8
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Booking_Link {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // No hooks needed - methods called directly from other classes
    }

    /**
     * Get booking URL for an expert
     *
     * @param int $expert_id Expert post ID
     * @return string|false Booking URL or false if not set
     */
    public function get_booking_url($expert_id) {
        $url = get_post_meta($expert_id, '_rfm_booking_url', true);
        return !empty($url) ? esc_url($url) : false;
    }

    /**
     * Get booking button text for an expert
     *
     * @param int $expert_id Expert post ID
     * @return string Button text (default: "Book tid")
     */
    public function get_booking_button_text($expert_id) {
        $text = get_post_meta($expert_id, '_rfm_booking_button_text', true);
        return !empty($text) ? esc_html($text) : __('Book tid', 'rigtig-for-mig');
    }

    /**
     * Check if booking is enabled for an expert
     *
     * @param int $expert_id Expert post ID
     * @return bool True if booking is enabled and URL is set
     */
    public function is_booking_enabled($expert_id) {
        // Check if expert has booking feature access (Standard/Premium)
        if (!RFM_Subscriptions::can_use_feature($expert_id, 'booking')) {
            return false;
        }

        // Check if booking is explicitly enabled
        $enabled = get_post_meta($expert_id, '_rfm_booking_enabled', true);
        if ($enabled !== '1' && $enabled !== 'yes' && $enabled !== true) {
            return false;
        }

        // Check if URL is set
        $url = $this->get_booking_url($expert_id);
        return !empty($url);
    }

    /**
     * Render booking button HTML for expert profile
     * Opens in a modal/popup instead of leaving the site
     *
     * @param int $expert_id Expert post ID
     * @return string HTML for booking button or empty string
     */
    public function render_booking_button($expert_id) {
        if (!$this->is_booking_enabled($expert_id)) {
            return '';
        }

        $url = $this->get_booking_url($expert_id);
        $text = $this->get_booking_button_text($expert_id);
        $expert_name = get_the_title($expert_id);

        ob_start();
        ?>
        <button type="button"
                class="rfm-btn rfm-btn-booking"
                id="rfm-open-booking-modal"
                data-booking-url="<?php echo esc_url($url); ?>"
                data-expert-name="<?php echo esc_attr($expert_name); ?>"
                title="<?php esc_attr_e('Book tid', 'rigtig-for-mig'); ?>">
            <i class="dashicons dashicons-calendar-alt"></i>
            <?php echo esc_html($text); ?>
        </button>
        <?php
        return ob_get_clean();
    }

    /**
     * Render booking modal HTML
     * Contains an iframe that loads the external booking system
     *
     * @param int $expert_id Expert post ID
     * @return string HTML for booking modal
     */
    public function render_booking_modal($expert_id) {
        if (!$this->is_booking_enabled($expert_id)) {
            return '';
        }

        $url = $this->get_booking_url($expert_id);
        $expert_name = get_the_title($expert_id);

        ob_start();
        ?>
        <!-- Booking Modal -->
        <div id="rfm-booking-modal" class="rfm-modal rfm-booking-modal" style="display: none;">
            <div class="rfm-booking-modal-content">
                <div class="rfm-booking-modal-header">
                    <h3>
                        <i class="dashicons dashicons-calendar-alt"></i>
                        <?php printf(__('Book tid hos %s', 'rigtig-for-mig'), esc_html($expert_name)); ?>
                    </h3>
                    <button type="button" class="rfm-booking-modal-close" aria-label="<?php esc_attr_e('Luk', 'rigtig-for-mig'); ?>">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="rfm-booking-modal-body">
                    <div class="rfm-booking-iframe-loader">
                        <i class="dashicons dashicons-update rfm-spin"></i>
                        <p><?php _e('Indlæser booking...', 'rigtig-for-mig'); ?></p>
                    </div>
                    <iframe
                        id="rfm-booking-iframe"
                        src=""
                        data-src="<?php echo esc_url($url); ?>"
                        frameborder="0"
                        allowfullscreen
                        allow="payment; camera; microphone"
                        title="<?php esc_attr_e('Booking kalender', 'rigtig-for-mig'); ?>">
                    </iframe>
                </div>
                <div class="rfm-booking-modal-footer">
                    <a href="<?php echo esc_url($url); ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="rfm-booking-external-link">
                        <i class="dashicons dashicons-external"></i>
                        <?php _e('Åbn i nyt vindue', 'rigtig-for-mig'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Save booking settings for an expert
     *
     * @param int $expert_id Expert post ID
     * @param array $data Booking data (url, button_text, enabled)
     * @return bool Success status
     */
    public function save_booking_settings($expert_id, $data) {
        // Validate expert has booking feature
        if (!RFM_Subscriptions::can_use_feature($expert_id, 'booking')) {
            return false;
        }

        // Sanitize and save URL
        if (isset($data['booking_url'])) {
            $url = esc_url_raw($data['booking_url']);
            update_post_meta($expert_id, '_rfm_booking_url', $url);
        }

        // Sanitize and save button text
        if (isset($data['booking_button_text'])) {
            $text = sanitize_text_field($data['booking_button_text']);
            update_post_meta($expert_id, '_rfm_booking_button_text', $text);
        }

        // Save enabled status
        if (isset($data['booking_enabled'])) {
            $enabled = $data['booking_enabled'] ? '1' : '0';
            update_post_meta($expert_id, '_rfm_booking_enabled', $enabled);
        }

        return true;
    }

    /**
     * Get all booking settings for an expert
     *
     * @param int $expert_id Expert post ID
     * @return array Booking settings
     */
    public function get_booking_settings($expert_id) {
        return array(
            'booking_url' => get_post_meta($expert_id, '_rfm_booking_url', true),
            'booking_button_text' => get_post_meta($expert_id, '_rfm_booking_button_text', true),
            'booking_enabled' => get_post_meta($expert_id, '_rfm_booking_enabled', true) === '1'
        );
    }

    /**
     * Render booking settings form for expert dashboard
     *
     * @param int $expert_id Expert post ID
     * @return string HTML for booking settings form
     */
    public function render_booking_settings_form($expert_id) {
        $settings = $this->get_booking_settings($expert_id);
        $can_use_booking = RFM_Subscriptions::can_use_feature($expert_id, 'booking');

        ob_start();
        ?>
        <div class="rfm-booking-settings">
            <h3><?php _e('Booking Indstillinger', 'rigtig-for-mig'); ?></h3>

            <?php if (!$can_use_booking): ?>
                <div class="rfm-upgrade-notice">
                    <p>
                        <i class="dashicons dashicons-lock"></i>
                        <?php _e('Booking-funktionen kræver Standard eller Premium abonnement.', 'rigtig-for-mig'); ?>
                    </p>
                    <a href="<?php echo home_url('/priser/'); ?>" class="rfm-btn rfm-btn-secondary">
                        <?php _e('Se abonnementer', 'rigtig-for-mig'); ?>
                    </a>
                </div>
            <?php else: ?>
                <p class="rfm-form-description">
                    <?php _e('Tilføj et link til dit booking-system (Calendly, Cal.com, Google Calendar, Tidio, eller lignende).', 'rigtig-for-mig'); ?>
                </p>

                <div class="rfm-form-group">
                    <label class="rfm-checkbox-label">
                        <input type="checkbox"
                               name="booking_enabled"
                               id="rfm-booking-enabled"
                               value="1"
                               <?php checked($settings['booking_enabled'], true); ?>>
                        <?php _e('Aktiver booking-knap på min profil', 'rigtig-for-mig'); ?>
                    </label>
                </div>

                <div class="rfm-form-group">
                    <label for="rfm-booking-url"><?php _e('Booking link', 'rigtig-for-mig'); ?></label>
                    <input type="url"
                           id="rfm-booking-url"
                           name="booking_url"
                           class="rfm-form-control"
                           value="<?php echo esc_attr($settings['booking_url']); ?>"
                           placeholder="https://calendly.com/dit-firma">
                    <small class="rfm-form-hint">
                        <?php _e('Indsæt det fulde link til dit booking-system.', 'rigtig-for-mig'); ?>
                    </small>
                </div>

                <div class="rfm-form-group">
                    <label for="rfm-booking-button-text"><?php _e('Knap-tekst (valgfrit)', 'rigtig-for-mig'); ?></label>
                    <input type="text"
                           id="rfm-booking-button-text"
                           name="booking_button_text"
                           class="rfm-form-control"
                           value="<?php echo esc_attr($settings['booking_button_text']); ?>"
                           placeholder="<?php esc_attr_e('Book tid', 'rigtig-for-mig'); ?>">
                    <small class="rfm-form-hint">
                        <?php _e('Standard: "Book tid". Du kan ændre teksten til f.eks. "Book en gratis samtale".', 'rigtig-for-mig'); ?>
                    </small>
                </div>

                <div class="rfm-form-actions">
                    <button type="button" id="rfm-save-booking-settings" class="rfm-btn rfm-btn-primary">
                        <i class="dashicons dashicons-yes"></i>
                        <?php _e('Gem booking-indstillinger', 'rigtig-for-mig'); ?>
                    </button>
                </div>

                <div id="rfm-booking-message" class="rfm-form-message" style="display: none;"></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
