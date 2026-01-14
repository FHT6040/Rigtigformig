<?php
/**
 * Mass Email Management
 *
 * v3.9.0: Send bulk emails to users and experts with filtering
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Mass_Email {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'), 13);
        add_action('admin_init', array($this, 'handle_send_email'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'rfm-dashboard',
            __('Send Massemail', 'rigtig-for-mig'),
            __('Send Massemail', 'rigtig-for-mig'),
            'manage_options',
            'rfm-mass-email',
            array($this, 'render_mass_email_page')
        );
    }

    /**
     * Get recipient groups
     */
    public function get_recipient_groups() {
        global $wpdb;

        // Count users
        $all_users_count = count(get_users(array('role' => 'rfm_user', 'fields' => 'ID')));
        $verified_users_count = RFM_Email_Verification::get_verified_users_count();
        $unverified_users_count = $all_users_count - $verified_users_count;

        // Count experts
        $all_experts_count = wp_count_posts('rfm_expert')->publish;
        $premium_experts_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_rfm_subscription_plan' AND meta_value = 'premium'");
        $standard_experts_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_rfm_subscription_plan' AND meta_value = 'standard'");
        $free_experts_count = $all_experts_count - $premium_experts_count - $standard_experts_count;

        return array(
            'all_users' => array(
                'name' => __('Alle Brugere', 'rigtig-for-mig'),
                'count' => $all_users_count,
                'description' => __('Alle registrerede brugere', 'rigtig-for-mig'),
            ),
            'verified_users' => array(
                'name' => __('Verificerede Brugere', 'rigtig-for-mig'),
                'count' => $verified_users_count,
                'description' => __('Brugere der har verificeret deres email', 'rigtig-for-mig'),
            ),
            'unverified_users' => array(
                'name' => __('Uverificerede Brugere', 'rigtig-for-mig'),
                'count' => $unverified_users_count,
                'description' => __('Brugere der endnu ikke har verificeret deres email', 'rigtig-for-mig'),
            ),
            'all_experts' => array(
                'name' => __('Alle Eksperter', 'rigtig-for-mig'),
                'count' => $all_experts_count,
                'description' => __('Alle publicerede eksperter', 'rigtig-for-mig'),
            ),
            'premium_experts' => array(
                'name' => __('Premium Eksperter', 'rigtig-for-mig'),
                'count' => $premium_experts_count,
                'description' => __('Eksperter med Premium abonnement', 'rigtig-for-mig'),
            ),
            'standard_experts' => array(
                'name' => __('Standard Eksperter', 'rigtig-for-mig'),
                'count' => $standard_experts_count,
                'description' => __('Eksperter med Standard abonnement', 'rigtig-for-mig'),
            ),
            'free_experts' => array(
                'name' => __('Gratis Eksperter', 'rigtig-for-mig'),
                'count' => $free_experts_count,
                'description' => __('Eksperter med gratis plan', 'rigtig-for-mig'),
            ),
        );
    }

    /**
     * Get recipients based on group
     */
    public function get_recipients($group) {
        global $wpdb;
        $recipients = array();

        switch ($group) {
            case 'all_users':
                $users = get_users(array('role' => 'rfm_user', 'fields' => array('ID', 'user_email', 'display_name')));
                foreach ($users as $user) {
                    $recipients[] = array(
                        'email' => $user->user_email,
                        'name' => $user->display_name,
                        'type' => 'user'
                    );
                }
                break;

            case 'verified_users':
                $users = get_users(array('role' => 'rfm_user', 'fields' => array('ID', 'user_email', 'display_name')));
                foreach ($users as $user) {
                    if (RFM_Email_Verification::is_user_verified($user->ID)) {
                        $recipients[] = array(
                            'email' => $user->user_email,
                            'name' => $user->display_name,
                            'type' => 'user'
                        );
                    }
                }
                break;

            case 'unverified_users':
                $users = get_users(array('role' => 'rfm_user', 'fields' => array('ID', 'user_email', 'display_name')));
                foreach ($users as $user) {
                    if (!RFM_Email_Verification::is_user_verified($user->ID)) {
                        $recipients[] = array(
                            'email' => $user->user_email,
                            'name' => $user->display_name,
                            'type' => 'user'
                        );
                    }
                }
                break;

            case 'all_experts':
                $experts = get_posts(array(
                    'post_type' => 'rfm_expert',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'fields' => 'ids'
                ));
                foreach ($experts as $expert_id) {
                    $email = get_post_meta($expert_id, '_rfm_email', true);
                    if ($email) {
                        $recipients[] = array(
                            'email' => $email,
                            'name' => get_the_title($expert_id),
                            'type' => 'expert'
                        );
                    }
                }
                break;

            case 'premium_experts':
                $expert_ids = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_rfm_subscription_plan' AND meta_value = 'premium'");
                foreach ($expert_ids as $expert_id) {
                    $post = get_post($expert_id);
                    if ($post && $post->post_status === 'publish') {
                        $email = get_post_meta($expert_id, '_rfm_email', true);
                        if ($email) {
                            $recipients[] = array(
                                'email' => $email,
                                'name' => get_the_title($expert_id),
                                'type' => 'expert'
                            );
                        }
                    }
                }
                break;

            case 'standard_experts':
                $expert_ids = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_rfm_subscription_plan' AND meta_value = 'standard'");
                foreach ($expert_ids as $expert_id) {
                    $post = get_post($expert_id);
                    if ($post && $post->post_status === 'publish') {
                        $email = get_post_meta($expert_id, '_rfm_email', true);
                        if ($email) {
                            $recipients[] = array(
                                'email' => $email,
                                'name' => get_the_title($expert_id),
                                'type' => 'expert'
                            );
                        }
                    }
                }
                break;

            case 'free_experts':
                $experts = get_posts(array(
                    'post_type' => 'rfm_expert',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'meta_query' => array(
                        'relation' => 'OR',
                        array(
                            'key' => '_rfm_subscription_plan',
                            'value' => 'free',
                            'compare' => '='
                        ),
                        array(
                            'key' => '_rfm_subscription_plan',
                            'compare' => 'NOT EXISTS'
                        )
                    )
                ));
                foreach ($experts as $expert) {
                    $email = get_post_meta($expert->ID, '_rfm_email', true);
                    if ($email) {
                        $recipients[] = array(
                            'email' => $email,
                            'name' => get_the_title($expert->ID),
                            'type' => 'expert'
                        );
                    }
                }
                break;
        }

        return $recipients;
    }

    /**
     * Render mass email page
     */
    public function render_mass_email_page() {
        $groups = $this->get_recipient_groups();
        $templates = RFM_Email_Templates::get_instance()->get_available_templates();

        ?>
        <div class="wrap">
            <h1><?php _e('Send Massemail', 'rigtig-for-mig'); ?></h1>

            <?php if (isset($_GET['sent'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>✅</strong> <?php printf(__('Email sendt til %d modtagere!', 'rigtig-for-mig'), intval($_GET['count'])); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong>❌</strong> <?php _e('Der opstod en fejl ved afsendelse af emails.', 'rigtig-for-mig'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="" id="rfm-mass-email-form">
                <?php wp_nonce_field('rfm_send_mass_email'); ?>
                <input type="hidden" name="action" value="rfm_send_mass_email">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="recipient_group"><?php _e('Send til', 'rigtig-for-mig'); ?></label>
                        </th>
                        <td>
                            <select name="recipient_group" id="recipient_group" class="regular-text" required>
                                <option value=""><?php _e('-- Vælg modtagere --', 'rigtig-for-mig'); ?></option>
                                <?php foreach ($groups as $group_id => $group): ?>
                                    <option value="<?php echo esc_attr($group_id); ?>">
                                        <?php echo esc_html($group['name']); ?> (<?php echo $group['count']; ?> modtagere)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description" id="group-description"></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php _e('Brug Template', 'rigtig-for-mig'); ?></label>
                        </th>
                        <td>
                            <select id="use_template" class="regular-text">
                                <option value=""><?php _e('Skriv custom email', 'rigtig-for-mig'); ?></option>
                                <?php foreach ($templates as $template_id => $template): ?>
                                    <option value="<?php echo esc_attr($template_id); ?>">
                                        <?php echo esc_html($template['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Vælg en template eller skriv en custom besked nedenfor.', 'rigtig-for-mig'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="email_subject"><?php _e('Email Emne', 'rigtig-for-mig'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="email_subject"
                                   name="email_subject"
                                   class="large-text"
                                   required>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="email_body"><?php _e('Email Besked', 'rigtig-for-mig'); ?></label>
                        </th>
                        <td>
                            <textarea id="email_body"
                                      name="email_body"
                                      rows="12"
                                      class="large-text"
                                      required></textarea>
                            <p class="description">
                                <?php _e('Du kan bruge {navn} som placeholder for modtagerens navn.', 'rigtig-for-mig'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                    <h3 style="margin-top: 0;"><?php _e('⚠️ Advarsel', 'rigtig-for-mig'); ?></h3>
                    <p><?php _e('Du er ved at sende email til flere modtagere. Dette kan ikke fortrydes.', 'rigtig-for-mig'); ?></p>
                    <p><?php _e('Sørg for at du har kontrolleret emne og besked grundigt.', 'rigtig-for-mig'); ?></p>
                </div>

                <p class="submit">
                    <button type="button" class="button" id="rfm-preview-mass-email">
                        <?php _e('👁️ Preview Email', 'rigtig-for-mig'); ?>
                    </button>
                    <button type="submit" class="button button-primary" id="rfm-send-mass-email">
                        <?php _e('📧 Send Email', 'rigtig-for-mig'); ?>
                    </button>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var groups = <?php echo json_encode($groups); ?>;
            var templates = <?php echo json_encode($templates); ?>;

            // Update description when group is selected
            $('#recipient_group').on('change', function() {
                var groupId = $(this).val();
                if (groupId && groups[groupId]) {
                    $('#group-description').text(groups[groupId].description);
                } else {
                    $('#group-description').text('');
                }
            });

            // Load template when selected
            $('#use_template').on('change', function() {
                var templateId = $(this).val();
                if (templateId && templates[templateId]) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rfm_get_template_content',
                            template_id: templateId,
                            _wpnonce: '<?php echo wp_create_nonce('rfm_get_template'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#email_subject').val(response.data.subject);
                                $('#email_body').val(response.data.body);
                            }
                        }
                    });
                }
            });

            // Preview email
            $('#rfm-preview-mass-email').on('click', function() {
                var subject = $('#email_subject').val();
                var body = $('#email_body').val();
                var group = $('#recipient_group option:selected').text();

                if (!subject || !body) {
                    alert('Udfyld venligst emne og besked først.');
                    return;
                }

                var preview = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">';
                preview += '<p><strong>Til:</strong> ' + group + '</p>';
                preview += '<h3 style="margin: 0 0 10px 0; padding-bottom: 10px; border-bottom: 2px solid #2271b1;">Emne: ' + subject + '</h3>';
                preview += '<div style="white-space: pre-wrap; line-height: 1.6;">' + body + '</div>';
                preview += '</div>';

                var win = window.open('', 'Email Preview', 'width=700,height=600');
                win.document.write('<html><head><title>Email Preview</title></head><body>' + preview + '</body></html>');
            });

            // Confirm before sending
            $('#rfm-mass-email-form').on('submit', function(e) {
                var count = $('#recipient_group option:selected').text().match(/\((\d+) modtagere\)/);
                var recipientCount = count ? count[1] : '0';

                if (!confirm('Er du sikker på at du vil sende denne email til ' + recipientCount + ' modtagere?\n\nDette kan ikke fortrydes!')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
        </script>

        <style>
            #group-description {
                font-style: italic;
                color: #666;
            }
        </style>
        <?php
    }

    /**
     * Handle send email
     */
    public function handle_send_email() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'rfm_send_mass_email') {
            return;
        }

        check_admin_referer('rfm_send_mass_email');

        if (!current_user_can('manage_options')) {
            wp_die(__('Ingen tilladelse', 'rigtig-for-mig'));
        }

        $group = sanitize_key($_POST['recipient_group']);
        $subject = sanitize_text_field($_POST['email_subject']);
        $body = sanitize_textarea_field($_POST['email_body']);

        // Get recipients
        $recipients = $this->get_recipients($group);

        if (empty($recipients)) {
            wp_redirect(add_query_arg(
                array('page' => 'rfm-mass-email', 'error' => '1'),
                admin_url('admin.php')
            ));
            exit;
        }

        // Send emails
        $sent_count = 0;
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        foreach ($recipients as $recipient) {
            // Replace {navn} placeholder
            $personalized_body = str_replace('{navn}', $recipient['name'], $body);
            $personalized_subject = str_replace('{navn}', $recipient['name'], $subject);

            if (wp_mail($recipient['email'], $personalized_subject, $personalized_body, $headers)) {
                $sent_count++;
            }

            // Small delay to avoid overwhelming server
            usleep(100000); // 0.1 seconds
        }

        wp_redirect(add_query_arg(
            array('page' => 'rfm-mass-email', 'sent' => '1', 'count' => $sent_count),
            admin_url('admin.php')
        ));
        exit;
    }
}

// AJAX handler for getting template content
add_action('wp_ajax_rfm_get_template_content', function() {
    check_ajax_referer('rfm_get_template');

    $template_id = isset($_POST['template_id']) ? sanitize_key($_POST['template_id']) : '';

    if (!$template_id) {
        wp_send_json_error(array('message' => 'Invalid template ID'));
    }

    $template = RFM_Email_Templates::get_instance()->get_template($template_id);

    if (!$template) {
        wp_send_json_error(array('message' => 'Template not found'));
    }

    wp_send_json_success($template);
});
