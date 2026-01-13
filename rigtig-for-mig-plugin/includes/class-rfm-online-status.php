<?php
/**
 * Expert Online Status Tracking
 * 
 * Tracks when experts are active and provides online/offline indicators
 *
 * @package Rigtig_For_Mig
 * @since 2.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Online_Status {
    
    private static $instance = null;
    
    /**
     * Minutes before user is considered offline
     */
    const ONLINE_THRESHOLD_MINUTES = 15;
    
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
        // Track activity on init for logged-in users
        add_action('init', [$this, 'track_user_activity']);
        
        // Also track on AJAX requests
        add_action('wp_ajax_rfm_heartbeat', [$this, 'ajax_heartbeat']);
        
        // Add heartbeat script on frontend for experts
        add_action('wp_footer', [$this, 'add_heartbeat_script'], 100);
        
        // Admin hooks
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
    }
    
    /**
     * Track user activity - updates last_active timestamp
     */
    public function track_user_activity() {
        if (!is_user_logged_in()) {
            rfm_log("Online Status: Not logged in, skipping activity tracking");
            return;
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        // Track both expert users AND regular users
        if (!$user || (!in_array('rfm_expert_user', (array) $user->roles) && !in_array('rfm_user', (array) $user->roles))) {
            if ($user) {
                rfm_log("Online Status: User ID $user_id has roles: " . implode(', ', $user->roles) . " - not tracking");
            } else {
                rfm_log("Online Status: User data not found for ID $user_id");
            }
            return;
        }

        // Update last active timestamp
        $timestamp = current_time('timestamp');
        update_user_meta($user_id, '_rfm_last_active', $timestamp);
        rfm_log("Online Status: Updated activity for user ID $user_id - timestamp: $timestamp (" . date('Y-m-d H:i:s', $timestamp) . ")");
    }
    
    /**
     * AJAX heartbeat handler for keeping session active
     */
    public function ajax_heartbeat() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rfm_heartbeat_nonce')) {
            rfm_log("Online Status HEARTBEAT: Nonce verification failed");
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        if (!is_user_logged_in()) {
            rfm_log("Online Status HEARTBEAT: Not logged in");
            wp_send_json_error(['message' => 'Not logged in']);
        }

        $user_id = get_current_user_id();
        $timestamp = current_time('timestamp');
        update_user_meta($user_id, '_rfm_last_active', $timestamp);

        rfm_log("Online Status HEARTBEAT: User ID $user_id - heartbeat received, updated timestamp to $timestamp (" . date('Y-m-d H:i:s', $timestamp) . ")");

        wp_send_json_success(['message' => 'Activity tracked', 'timestamp' => $timestamp]);
    }
    
    /**
     * Add heartbeat script directly in footer for experts and users
     */
    public function add_heartbeat_script() {
        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        $user_id = $user->ID;

        // For both expert users and regular users
        if (!in_array('rfm_expert_user', (array) $user->roles) && !in_array('rfm_user', (array) $user->roles)) {
            rfm_log("Online Status SCRIPT: User ID $user_id has roles: " . implode(', ', $user->roles) . " - not adding heartbeat script");
            return;
        }

        rfm_log("Online Status SCRIPT: Adding heartbeat script for user ID $user_id with roles: " . implode(', ', $user->roles));

        $nonce = wp_create_nonce('rfm_heartbeat_nonce');
        $ajax_url = admin_url('admin-ajax.php');
        ?>
        <script type="text/javascript">
        (function() {
            var rfmHeartbeatNonce = '<?php echo esc_js($nonce); ?>';
            var rfmAjaxUrl = '<?php echo esc_js($ajax_url); ?>';

            console.log('RFM Online Status: Heartbeat script loaded for user ID <?php echo $user_id; ?>');

            function rfmSendHeartbeat() {
                console.log('RFM Online Status: Sending heartbeat...');
                var xhr = new XMLHttpRequest();
                xhr.open('POST', rfmAjaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        console.log('RFM Online Status: Heartbeat response:', xhr.responseText);
                    } else {
                        console.error('RFM Online Status: Heartbeat failed with status:', xhr.status);
                    }
                };
                xhr.onerror = function() {
                    console.error('RFM Online Status: Heartbeat request error');
                };
                xhr.send('action=rfm_heartbeat&nonce=' + encodeURIComponent(rfmHeartbeatNonce));
            }

            // Send heartbeat immediately on page load
            console.log('RFM Online Status: Sending initial heartbeat on page load');
            rfmSendHeartbeat();

            // Send heartbeat every 5 minutes (300000ms)
            setInterval(rfmSendHeartbeat, 300000);
            console.log('RFM Online Status: Heartbeat interval set to 5 minutes');
            
            // Also send on visibility change (when user returns to tab)
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    rfmSendHeartbeat();
                }
            });
        })();
        </script>
        <?php
    }
    
    /**
     * Enqueue admin styles for online status indicators
     */
    public function enqueue_admin_styles($hook) {
        // Only on our pages
        if (strpos($hook, 'rfm') === false && strpos($hook, 'rigtig') === false) {
            return;
        }
        
        wp_add_inline_style('rfm-admin-styles', '
            .rfm-online-status {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .rfm-status-indicator {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                display: inline-block;
            }
            .rfm-status-online {
                background-color: #22c55e;
                box-shadow: 0 0 4px #22c55e;
            }
            .rfm-status-offline {
                background-color: #ef4444;
            }
            .rfm-status-text {
                font-size: 12px;
                color: #666;
            }
        ');
    }
    
    /**
     * Check if an expert is online
     * 
     * @param int $expert_id Expert post ID
     * @return bool True if online, false if offline
     */
    public function is_expert_online($expert_id) {
        // Get the user ID associated with this expert profile
        $expert_post = get_post($expert_id);
        
        if (!$expert_post) {
            return false;
        }
        
        $user_id = $expert_post->post_author;
        
        return $this->is_user_online($user_id);
    }
    
    /**
     * Check if a user is online
     *
     * @param int $user_id WordPress user ID
     * @return bool True if online, false if offline
     */
    public function is_user_online($user_id) {
        $last_active = get_user_meta($user_id, '_rfm_last_active', true);

        if (empty($last_active)) {
            rfm_log("Online Status CHECK: User ID $user_id has NO _rfm_last_active value - marked as OFFLINE");
            return false;
        }

        $current = current_time('timestamp');
        $threshold = $current - (self::ONLINE_THRESHOLD_MINUTES * 60);
        $is_online = ($last_active > $threshold);

        $last_active_date = date('Y-m-d H:i:s', $last_active);
        $threshold_date = date('Y-m-d H:i:s', $threshold);
        $minutes_ago = round(($current - $last_active) / 60, 1);

        rfm_log("Online Status CHECK: User ID $user_id - Last active: $last_active_date ($minutes_ago min ago), Threshold: $threshold_date, Status: " . ($is_online ? 'ONLINE' : 'OFFLINE'));

        return $is_online;
    }
    
    /**
     * Get last active time for an expert
     * 
     * @param int $expert_id Expert post ID
     * @return string|null Human-readable time or null
     */
    public function get_last_active_time($expert_id) {
        $expert_post = get_post($expert_id);
        
        if (!$expert_post) {
            return null;
        }
        
        $user_id = $expert_post->post_author;
        $last_active = get_user_meta($user_id, '_rfm_last_active', true);
        
        if (empty($last_active)) {
            return __('Aldrig', 'rigtig-for-mig');
        }
        
        return human_time_diff($last_active, current_time('timestamp')) . ' ' . __('siden', 'rigtig-for-mig');
    }
    
    /**
     * Render online status indicator HTML
     * 
     * @param int $expert_id Expert post ID
     * @param bool $show_text Whether to show text label
     * @return string HTML output
     */
    public function render_status_indicator($expert_id, $show_text = true) {
        $is_online = $this->is_expert_online($expert_id);
        $last_active = $this->get_last_active_time($expert_id);
        
        $status_class = $is_online ? 'rfm-status-online' : 'rfm-status-offline';
        $status_text = $is_online ? __('Online', 'rigtig-for-mig') : __('Offline', 'rigtig-for-mig');
        
        $html = '<span class="rfm-online-status" title="' . esc_attr(__('Sidst aktiv:', 'rigtig-for-mig') . ' ' . $last_active) . '">';
        $html .= '<span class="rfm-status-indicator ' . $status_class . '"></span>';
        
        if ($show_text) {
            $html .= '<span class="rfm-status-text">' . esc_html($status_text) . '</span>';
        }
        
        $html .= '</span>';
        
        return $html;
    }
    
    /**
     * Get count of online experts
     * 
     * @return int Number of online experts
     */
    public function get_online_count() {
        global $wpdb;
        
        $threshold = current_time('timestamp') - (self::ONLINE_THRESHOLD_MINUTES * 60);
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT um.user_id) 
            FROM {$wpdb->usermeta} um
            INNER JOIN {$wpdb->usermeta} um2 ON um.user_id = um2.user_id
            WHERE um.meta_key = '_rfm_last_active' 
            AND um.meta_value > %d
            AND um2.meta_key = '{$wpdb->prefix}capabilities'
            AND um2.meta_value LIKE %s
        ", $threshold, '%rfm_expert_user%'));
        
        return (int) $count;
    }
}
