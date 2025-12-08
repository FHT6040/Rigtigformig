<?php
/**
 * Migration Admin Tool
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Migration_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_rfm_run_migration', array($this, 'handle_migration'));
    }

    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=rfm_bruger',
            __('Migrer Brugere', 'rigtig-for-mig'),
            __('⚙️ Migration', 'rigtig-for-mig'),
            'manage_options',
            'rfm-user-migration',
            array($this, 'render_migration_page')
        );
    }

    /**
     * Render migration admin page
     */
    public function render_migration_page() {
        // Check if migration was just run
        $migration_result = get_transient('rfm_migration_result');
        if ($migration_result) {
            delete_transient('rfm_migration_result');
        }

        // Get current counts
        global $wpdb;

        // Count WordPress users with rfm_user role
        $wp_users_count = count(get_users(array('role' => 'rfm_user')));

        // Count existing migrated profiles
        $migrated_count = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'rfm_bruger'
            AND p.post_status = 'publish'
        ");

        // Count profiles in custom table
        $table_name = $wpdb->prefix . 'rfm_user_profiles';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        $table_count = 0;
        if ($table_exists) {
            $table_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        }

        ?>
        <div class="wrap">
            <h1><?php _e('Bruger Migration: Custom Table → Custom Post Type', 'rigtig-for-mig'); ?></h1>

            <div class="notice notice-info">
                <p><strong><?php _e('Om Migration:', 'rigtig-for-mig'); ?></strong></p>
                <p><?php _e('I version 3.3.0 er Bruger-profiler blevet ændret fra custom database table til Custom Post Type (samme system som Eksperter).', 'rigtig-for-mig'); ?></p>
                <p><?php _e('Dette tool migrerer eksisterende brugerdata fra den gamle tabel (wp_rfm_user_profiles) til det nye Custom Post Type system (rfm_bruger).', 'rigtig-for-mig'); ?></p>
            </div>

            <?php if ($migration_result): ?>
                <div class="notice notice-success is-dismissible">
                    <h3><?php _e('✅ Migration Gennemført!', 'rigtig-for-mig'); ?></h3>
                    <ul>
                        <li><strong><?php _e('Total WordPress brugere:', 'rigtig-for-mig'); ?></strong> <?php echo esc_html($migration_result['total_users']); ?></li>
                        <li><strong><?php _e('Migreret:', 'rigtig-for-mig'); ?></strong> <?php echo esc_html($migration_result['migrated']); ?></li>
                        <li><strong><?php _e('Sprunget over (allerede migreret):', 'rigtig-for-mig'); ?></strong> <?php echo esc_html($migration_result['skipped']); ?></li>
                        <li><strong><?php _e('Fejl:', 'rigtig-for-mig'); ?></strong> <?php echo esc_html($migration_result['errors']); ?></li>
                    </ul>

                    <?php if (!empty($migration_result['error_messages'])): ?>
                        <details>
                            <summary><strong><?php _e('Fejl Detaljer:', 'rigtig-for-mig'); ?></strong></summary>
                            <ul>
                                <?php foreach ($migration_result['error_messages'] as $error): ?>
                                    <li><?php echo esc_html($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2><?php _e('Nuværende Status', 'rigtig-for-mig'); ?></h2>
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <th><?php _e('WordPress Brugere (rfm_user rolle):', 'rigtig-for-mig'); ?></th>
                            <td><strong><?php echo esc_html($wp_users_count); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php _e('Migrerede Profiler (Custom Post Type):', 'rigtig-for-mig'); ?></th>
                            <td><strong><?php echo esc_html($migrated_count); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php _e('Profiler i Gammel Tabel (wp_rfm_user_profiles):', 'rigtig-for-mig'); ?></th>
                            <td>
                                <?php if ($table_exists): ?>
                                    <strong><?php echo esc_html($table_count); ?></strong>
                                    <?php if ($table_count > 0): ?>
                                        <span class="dashicons dashicons-warning" style="color: orange;"></span>
                                        <em><?php _e('Tabellen findes stadig (bruges ikke længere)', 'rigtig-for-mig'); ?></em>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em><?php _e('Tabellen eksisterer ikke', 'rigtig-for-mig'); ?></em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Kør Migration', 'rigtig-for-mig'); ?></h2>

                <?php if ($wp_users_count === 0): ?>
                    <div class="notice notice-warning">
                        <p><?php _e('Der er ingen brugere at migrere.', 'rigtig-for-mig'); ?></p>
                    </div>
                <?php elseif ($wp_users_count == $migrated_count): ?>
                    <div class="notice notice-success">
                        <p><strong><?php _e('✅ Alle brugere er allerede migreret!', 'rigtig-for-mig'); ?></strong></p>
                        <p><?php _e('Du kan køre migrationen igen hvis du vil opdatere data.', 'rigtig-for-mig'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-warning">
                        <p><strong><?php _e('⚠️ Bemærk:', 'rigtig-for-mig'); ?></strong></p>
                        <p><?php echo sprintf(__('%d brugere er ikke migreret endnu.', 'rigtig-for-mig'), ($wp_users_count - $migrated_count)); ?></p>
                    </div>
                <?php endif; ?>

                <p><?php _e('Migrationen vil:', 'rigtig-for-mig'); ?></p>
                <ul style="list-style: disc; margin-left: 25px;">
                    <li><?php _e('Oprette Custom Post Type posts (rfm_bruger) for hver bruger', 'rigtig-for-mig'); ?></li>
                    <li><?php _e('Kopiere data fra wp_rfm_user_profiles til post_meta', 'rigtig-for-mig'); ?></li>
                    <li><?php _e('Kopiere email verification status fra user_meta til post_meta', 'rigtig-for-mig'); ?></li>
                    <li><?php _e('Linke Custom Post til WordPress user (via _rfm_wp_user_id)', 'rigtig-for-mig'); ?></li>
                    <li><?php _e('Springe over brugere der allerede er migreret', 'rigtig-for-mig'); ?></li>
                </ul>

                <p><strong><?php _e('⚠️ VIGTIGT:', 'rigtig-for-mig'); ?></strong></p>
                <ul style="list-style: disc; margin-left: 25px; color: #d63638;">
                    <li><?php _e('Tag backup af databasen FØR du kører migration!', 'rigtig-for-mig'); ?></li>
                    <li><?php _e('Migrationen kan IKKE rulles tilbage automatisk', 'rigtig-for-mig'); ?></li>
                    <li><?php _e('Den gamle tabel (wp_rfm_user_profiles) bliver IKKE slettet', 'rigtig-for-mig'); ?></li>
                </ul>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="rfm_run_migration">
                    <?php wp_nonce_field('rfm_migration_nonce', 'rfm_migration_nonce'); ?>

                    <button type="submit" class="button button-primary button-hero" onclick="return confirm('<?php _e('Er du sikker på at du vil køre migrationen?\n\nDu bør tage en database backup først!', 'rigtig-for-mig'); ?>');">
                        🚀 <?php _e('Kør Migration Nu', 'rigtig-for-mig'); ?>
                    </button>
                </form>
            </div>

            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('📋 Migration Log', 'rigtig-for-mig'); ?></h2>
                <p><?php _e('Migration detaljer bliver logget til WordPress error log.', 'rigtig-for-mig'); ?></p>
                <p><?php _e('Tjek din error_log fil for detaljer: wp-content/debug.log', 'rigtig-for-mig'); ?></p>
            </div>
        </div>

        <style>
            .card {
                background: white;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                padding: 20px;
            }
            .card h2 {
                margin-top: 0;
            }
        </style>
        <?php
    }

    /**
     * Handle migration POST request
     */
    public function handle_migration() {
        // Verify nonce
        if (!isset($_POST['rfm_migration_nonce']) || !wp_verify_nonce($_POST['rfm_migration_nonce'], 'rfm_migration_nonce')) {
            wp_die(__('Security check failed', 'rigtig-for-mig'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action', 'rigtig-for-mig'));
        }

        // Run migration
        $result = RFM_Migration::migrate_users_to_cpt();

        // Store result in transient (expires in 60 seconds)
        set_transient('rfm_migration_result', $result, 60);

        // Redirect back
        wp_redirect(admin_url('edit.php?post_type=rfm_bruger&page=rfm-user-migration'));
        exit;
    }
}

// Initialize
if (is_admin()) {
    new RFM_Migration_Admin();
}
