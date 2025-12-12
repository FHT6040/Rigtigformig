<?php
/**
 * User Admin Management
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_User_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_user_admin_menu'), 11);
        add_action('admin_init', array($this, 'check_user_role'));
        add_action('admin_post_rfm_fix_user_role', array($this, 'fix_user_role'));
        
        // AJAX handlers
        add_action('wp_ajax_rfm_delete_user_admin', array($this, 'handle_admin_delete_user'));
        add_action('wp_ajax_rfm_toggle_user_status', array($this, 'handle_toggle_user_status'));
        add_action('wp_ajax_rfm_export_user_data', array($this, 'handle_export_user_data'));
    }
    
    /**
     * Check if user role exists
     */
    public function check_user_role() {
        if (!get_role('rfm_user')) {
            add_action('admin_notices', array($this, 'role_missing_notice'));
        }
    }
    
    /**
     * Show notice if role is missing
     */
    public function role_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>⚠️ Rigtig for mig:</strong> "Bruger" rollen mangler!</p>
            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=rfm_fix_user_role'), 'rfm_fix_user_role'); ?>" class="button button-primary">
                    🔧 Opret "Bruger" Rolle Nu
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Fix user role
     */
    public function fix_user_role() {
        check_admin_referer('rfm_fix_user_role');
        
        if (!current_user_can('manage_options')) {
            wp_die('Du har ikke tilladelse til at udføre denne handling.');
        }
        
        // Remove and recreate role
        remove_role('rfm_user');
        
        add_role(
            'rfm_user',
            __('Bruger', 'rigtig-for-mig'),
            array(
                'read' => true,
            )
        );
        
        wp_redirect(add_query_arg(
            array('role-fixed' => 'user'),
            admin_url('admin.php?page=rfm-users')
        ));
        exit;
    }
    
    /**
     * Add admin menu for users
     */
    public function add_user_admin_menu() {
        add_submenu_page(
            'rfm-dashboard',
            __('Brugere', 'rigtig-for-mig'),
            __('Brugere', 'rigtig-for-mig'),
            'manage_options',
            'rfm-users',
            array($this, 'render_users_page')
        );
    }
    
    /**
     * Render users admin page
     */
    public function render_users_page() {
        global $wpdb;

        // Define table name
        $profiles_table = $wpdb->prefix . 'rfm_user_profiles';

        // Get all users with rfm_user role
        $users = get_users(array(
            'role' => 'rfm_user',
            'orderby' => 'registered',
            'order' => 'DESC'
        ));
        
        $total_users = count($users);
        
        // Get online users count
        $online_status = RFM_Online_Status::get_instance();
        $online_users = 0;
        
        foreach ($users as $user) {
            if ($online_status->is_user_online($user->ID)) {
                $online_users++;
            }
        }
        
        // Get statistics using helper methods
        $verified_users = RFM_Email_Verification::get_verified_users_count();
        $pending_users = $total_users - $verified_users;
        
        ?>
        <div class="wrap">
            <h1><?php _e('Brugere', 'rigtig-for-mig'); ?></h1>
            
            <?php if (isset($_GET['role-fixed']) && $_GET['role-fixed'] == 'user'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>✅ Success!</strong> "Bruger" rollen er nu oprettet.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['user-deleted'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>✅</strong> Brugeren er slettet succesfuldt.</p>
                </div>
            <?php endif; ?>
            
            <div class="rfm-dashboard-stats">
                <div class="rfm-stat-box">
                    <h3><?php echo number_format($total_users); ?></h3>
                    <p><?php _e('Total Brugere', 'rigtig-for-mig'); ?></p>
                </div>
                
                <div class="rfm-stat-box">
                    <h3><?php echo number_format($verified_users); ?></h3>
                    <p><?php _e('Verificerede Brugere', 'rigtig-for-mig'); ?></p>
                </div>
                
                <div class="rfm-stat-box">
                    <h3><?php echo number_format($pending_users); ?></h3>
                    <p><?php _e('Afventende Verificering', 'rigtig-for-mig'); ?></p>
                </div>
                
                <div class="rfm-stat-box rfm-stat-online">
                    <h3>
                        <span class="rfm-status-indicator rfm-status-online"></span>
                        <?php echo number_format($online_users); ?>
                    </h3>
                    <p><?php _e('Online Nu', 'rigtig-for-mig'); ?></p>
                </div>
            </div>
            
            <h2><?php _e('Alle Brugere', 'rigtig-for-mig'); ?></h2>
            
            <?php if ($users): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><?php _e('Status', 'rigtig-for-mig'); ?></th>
                            <th><?php _e('Brugernavn', 'rigtig-for-mig'); ?></th>
                            <th><?php _e('Email', 'rigtig-for-mig'); ?></th>
                            <th><?php _e('Telefon', 'rigtig-for-mig'); ?></th>
                            <th><?php _e('Verificeret', 'rigtig-for-mig'); ?></th>
                            <th><?php _e('Registreret', 'rigtig-for-mig'); ?></th>
                            <th><?php _e('Sidst aktiv', 'rigtig-for-mig'); ?></th>
                            <th><?php _e('Handlinger', 'rigtig-for-mig'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): 
                            $profile = $wpdb->get_row($wpdb->prepare(
                                "SELECT * FROM $profiles_table WHERE user_id = %d",
                                $user->ID
                            ));
                            
                            $is_online = $online_status->is_user_online($user->ID);
                            $verified = RFM_Email_Verification::is_user_verified($user->ID);
                            $last_login = $profile ? $profile->last_login : null;
                        ?>
                            <tr>
                                <td>
                                    <span class="rfm-status-indicator <?php echo $is_online ? 'rfm-status-online' : 'rfm-status-offline'; ?>" 
                                          title="<?php echo $is_online ? __('Online', 'rigtig-for-mig') : __('Offline', 'rigtig-for-mig'); ?>">
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                    <small><?php echo esc_html($user->user_login); ?></small>
                                </td>
                                <td>
                                    <a href="mailto:<?php echo esc_attr($user->user_email); ?>">
                                        <?php echo esc_html($user->user_email); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo $profile && $profile->phone ? esc_html($profile->phone) : '—'; ?>
                                </td>
                                <td>
                                    <?php if ($verified): ?>
                                        <span style="color: green;">✓ <?php _e('Ja', 'rigtig-for-mig'); ?></span>
                                    <?php else: ?>
                                        <span style="color: orange;">⏳ <?php _e('Afventende', 'rigtig-for-mig'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date_i18n(get_option('date_format'), strtotime($user->user_registered)); ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($last_login) {
                                        echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_login));
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>" class="button button-small">
                                        <?php _e('Rediger', 'rigtig-for-mig'); ?>
                                    </a>
                                    <button class="button button-small rfm-delete-user" data-user-id="<?php echo $user->ID; ?>" data-username="<?php echo esc_attr($user->user_login); ?>">
                                        <?php _e('Slet', 'rigtig-for-mig'); ?>
                                    </button>
                                    <a href="<?php echo admin_url('admin.php?page=rfm-users&action=view-profile&user_id=' . $user->ID); ?>" class="button button-small">
                                        <?php _e('Se profil', 'rigtig-for-mig'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('Ingen brugere fundet.', 'rigtig-for-mig'); ?></p>
            <?php endif; ?>
            
            <div class="rfm-admin-actions" style="margin-top: 20px;">
                <h3><?php _e('Eksporter brugerdata', 'rigtig-for-mig'); ?></h3>
                <p><?php _e('Download en CSV-fil med alle brugerdata (GDPR compliant)', 'rigtig-for-mig'); ?></p>
                <button id="rfm-export-users" class="button button-primary">
                    <?php _e('Eksporter alle brugere (CSV)', 'rigtig-for-mig'); ?>
                </button>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Delete user
            $('.rfm-delete-user').on('click', function() {
                const userId = $(this).data('user-id');
                const username = $(this).data('username');
                
                if (!confirm('Er du sikker på at du vil slette brugeren "' + username + '"? Dette kan ikke fortrydes!')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rfm_delete_user_admin',
                        user_id: userId,
                        _wpnonce: '<?php echo wp_create_nonce('rfm_delete_user_admin'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            });
            
            // Export users
            $('#rfm-export-users').on('click', function() {
                window.location.href = ajaxurl + '?action=rfm_export_user_data&_wpnonce=' + '<?php echo wp_create_nonce('rfm_export_user_data'); ?>';
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle admin user deletion
     */
    public function handle_admin_delete_user() {
        check_ajax_referer('rfm_delete_user_admin');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Ingen tilladelse', 'rigtig-for-mig')));
        }
        
        $user_id = intval($_POST['user_id']);
        
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Ugyldig bruger ID', 'rigtig-for-mig')));
        }
        
        // Delete user data
        global $wpdb;
        
        $wpdb->delete($wpdb->prefix . 'rfm_user_profiles', array('user_id' => $user_id));
        $wpdb->delete($wpdb->prefix . 'rfm_messages', array('sender_id' => $user_id));
        $wpdb->delete($wpdb->prefix . 'rfm_messages', array('recipient_id' => $user_id));
        $wpdb->delete($wpdb->prefix . 'rfm_message_threads', array('user_id' => $user_id));
        $wpdb->delete($wpdb->prefix . 'rfm_ratings', array('user_id' => $user_id));
        
        // Delete WordPress user
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($user_id);
        
        wp_send_json_success(array('message' => __('Bruger slettet', 'rigtig-for-mig')));
    }
    
    /**
     * Handle user data export
     */
    public function handle_export_user_data() {
        check_ajax_referer('rfm_export_user_data');
        
        if (!current_user_can('manage_options')) {
            wp_die('Ingen tilladelse');
        }
        
        global $wpdb;
        
        $users = get_users(array('role' => 'rfm_user'));
        $profiles_table = $wpdb->prefix . 'rfm_user_profiles';
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=brugere-export-' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array(
            'Bruger ID',
            'Brugernavn',
            'Email',
            'Visningsnavn',
            'Telefon',
            'Bio',
            'Verificeret',
            'GDPR Samtykke',
            'Registreret',
            'Sidst aktiv'
        ));
        
        // Data rows
        foreach ($users as $user) {
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $profiles_table WHERE user_id = %d",
                $user->ID
            ));

            $verified = RFM_Email_Verification::is_user_verified($user->ID);
            
            fputcsv($output, array(
                $user->ID,
                $user->user_login,
                $user->user_email,
                $user->display_name,
                $profile ? $profile->phone : '',
                $profile ? $profile->bio : '',
                $verified ? 'Ja' : 'Nej',
                $profile && $profile->gdpr_consent ? 'Ja' : 'Nej',
                $user->user_registered,
                $profile && $profile->last_login ? $profile->last_login : ''
            ));
        }
        
        fclose($output);
        exit;
    }
}
