<?php
/**
 * Debug Helper for User Dashboard AJAX Issues
 *
 * Provides a debug page in WordPress admin to diagnose cache and AJAX issues.
 * Only available when WP_DEBUG is enabled.
 *
 * @package Rigtig_For_Mig
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Debug_Helper {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Only enable in debug mode
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        add_action('admin_menu', array($this, 'add_debug_menu'));
        add_action('wp_ajax_rfm_test_ajax', array($this, 'test_ajax_handler'));
    }

    /**
     * Add debug menu to WordPress admin
     */
    public function add_debug_menu() {
        add_management_page(
            'RFM Debug Helper',
            'RFM Debug',
            'manage_options',
            'rfm-debug-helper',
            array($this, 'render_debug_page')
        );
    }

    /**
     * Test AJAX handler
     */
    public function test_ajax_handler() {
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');

        $test_data = array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'is_logged_in' => is_user_logged_in(),
            'nonce_received' => isset($_POST['nonce']) ? 'Yes' : 'No',
            'nonce_valid' => isset($_POST['nonce']) ? wp_verify_nonce($_POST['nonce'], 'rfm_debug_test') : false,
            'server_info' => array(
                'PHP_VERSION' => PHP_VERSION,
                'WP_VERSION' => get_bloginfo('version'),
                'RFM_VERSION' => RFM_VERSION,
                'LITESPEED_CACHE_ACTIVE' => is_plugin_active('litespeed-cache/litespeed-cache.php'),
            )
        );

        wp_send_json_success($test_data);
    }

    /**
     * Render debug page
     */
    public function render_debug_page() {
        ?>
        <div class="wrap">
            <h1>RFM User Dashboard Debug Helper</h1>

            <div class="card" style="max-width: 800px;">
                <h2>System Status</h2>
                <table class="widefat">
                    <tr>
                        <td><strong>RFM Plugin Version</strong></td>
                        <td><?php echo RFM_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>WordPress Version</strong></td>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>PHP Version</strong></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>WP_DEBUG Enabled</strong></td>
                        <td><?php echo defined('WP_DEBUG') && WP_DEBUG ? '✅ Yes' : '❌ No'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>LiteSpeed Cache Active</strong></td>
                        <td><?php echo is_plugin_active('litespeed-cache/litespeed-cache.php') ? '⚠️ Yes (Potential Issue)' : '✅ No'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>User Dashboard File Modified</strong></td>
                        <td><?php echo date('Y-m-d H:i:s', filemtime(RFM_PLUGIN_DIR . 'assets/js/user-dashboard.js')); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Current User ID</strong></td>
                        <td><?php echo get_current_user_id(); ?></td>
                    </tr>
                </table>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Cache Status</h2>
                <table class="widefat">
                    <tr>
                        <td><strong>Object Cache</strong></td>
                        <td><?php echo wp_using_ext_object_cache() ? '⚠️ Active' : '✅ Not Active'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Transients</strong></td>
                        <td>
                            <?php
                            global $wpdb;
                            $transient_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
                            echo $transient_count . ' transients in database';
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>AJAX Test</h2>
                <p>Click the button below to test AJAX functionality:</p>

                <button id="rfm-test-ajax" class="button button-primary">Test AJAX Connection</button>
                <button id="rfm-test-nonce" class="button button-secondary">Test Nonce Verification</button>

                <div id="rfm-test-results" style="margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 5px; display: none;">
                    <h3>Test Results:</h3>
                    <pre id="rfm-test-output" style="background: white; padding: 10px; overflow: auto;"></pre>
                </div>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Recent Debug Log Entries</h2>
                <?php
                $log_file = WP_CONTENT_DIR . '/debug.log';
                if (file_exists($log_file)) {
                    $log_content = file_get_contents($log_file);
                    $log_lines = explode("\n", $log_content);
                    $rfm_logs = array_filter($log_lines, function($line) {
                        return strpos($line, 'RFM User Dashboard') !== false || strpos($line, 'RFM:') !== false;
                    });
                    $recent_logs = array_slice(array_reverse($rfm_logs), 0, 20);

                    if (!empty($recent_logs)) {
                        echo '<pre style="background: #2c3e50; color: #ecf0f1; padding: 15px; overflow: auto; max-height: 400px;">';
                        foreach ($recent_logs as $log) {
                            echo esc_html($log) . "\n";
                        }
                        echo '</pre>';
                    } else {
                        echo '<p>No RFM debug log entries found.</p>';
                    }
                } else {
                    echo '<p>Debug log file not found at: ' . esc_html($log_file) . '</p>';
                    echo '<p>Make sure WP_DEBUG_LOG is enabled in wp-config.php</p>';
                }
                ?>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Quick Actions</h2>
                <p>
                    <a href="<?php echo admin_url('options-general.php?page=litespeed-cache'); ?>" class="button">
                        LiteSpeed Cache Settings
                    </a>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button">
                        Manage Plugins
                    </a>
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var testNonce = '<?php echo wp_create_nonce('rfm_debug_test'); ?>';

            $('#rfm-test-ajax').on('click', function() {
                var $results = $('#rfm-test-results');
                var $output = $('#rfm-test-output');

                $results.show();
                $output.text('Testing AJAX connection...\n\n');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rfm_test_ajax',
                        nonce: testNonce
                    },
                    success: function(response) {
                        $output.text('✅ AJAX Success!\n\n' + JSON.stringify(response, null, 2));
                    },
                    error: function(xhr, status, error) {
                        $output.text('❌ AJAX Error!\n\n' +
                            'Status: ' + status + '\n' +
                            'Error: ' + error + '\n' +
                            'Status Code: ' + xhr.status + '\n\n' +
                            'Response:\n' + xhr.responseText);
                    }
                });
            });

            $('#rfm-test-nonce').on('click', function() {
                var $results = $('#rfm-test-results');
                var $output = $('#rfm-test-output');

                $results.show();
                $output.text('Testing without nonce...\n\n');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rfm_test_ajax'
                        // Intentionally NOT sending nonce
                    },
                    success: function(response) {
                        $output.text('Response received:\n\n' + JSON.stringify(response, null, 2));
                    },
                    error: function(xhr, status, error) {
                        $output.text('Expected behavior - request without nonce:\n\n' +
                            'Status: ' + status + '\n' +
                            'Error: ' + error + '\n' +
                            'Status Code: ' + xhr.status + '\n\n' +
                            'Response:\n' + xhr.responseText);
                    }
                });
            });
        });
        </script>
        <?php
    }
}
