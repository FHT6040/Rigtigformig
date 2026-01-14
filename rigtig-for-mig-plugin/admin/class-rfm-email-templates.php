<?php
/**
 * Email Templates Management
 *
 * v3.9.0: New email templates system for customizable email content
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Email_Templates {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'), 12);
        add_action('admin_init', array($this, 'handle_save_template'));
        add_action('admin_init', array($this, 'handle_reset_template'));
        add_action('wp_ajax_rfm_preview_email', array($this, 'handle_preview_email'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'rfm-dashboard',
            __('Email Templates', 'rigtig-for-mig'),
            __('Email Templates', 'rigtig-for-mig'),
            'manage_options',
            'rfm-email-templates',
            array($this, 'render_templates_page')
        );
    }

    /**
     * Get all available templates
     */
    public function get_available_templates() {
        return array(
            'welcome_user' => array(
                'name' => __('Velkomst Email (Brugere)', 'rigtig-for-mig'),
                'description' => __('Sendes til nye brugere efter registrering', 'rigtig-for-mig'),
                'placeholders' => array(
                    '{navn}' => __('Brugerens fulde navn', 'rigtig-for-mig'),
                    '{email}' => __('Brugerens email', 'rigtig-for-mig'),
                    '{site_name}' => __('Hjemmesidens navn', 'rigtig-for-mig'),
                    '{login_url}' => __('Link til login side', 'rigtig-for-mig'),
                ),
                'default_subject' => 'Velkommen til {site_name}!',
                'default_body' => "Hej {navn},

Velkommen til {site_name}!

Din konto er nu oprettet med email: {email}

Du kan logge ind her: {login_url}

Vi glæder os til at hjælpe dig med at finde den rigtige ekspert.

Med venlig hilsen,
{site_name} teamet"
            ),
            'welcome_expert' => array(
                'name' => __('Velkomst Email (Eksperter)', 'rigtig-for-mig'),
                'description' => __('Sendes til nye eksperter efter profil oprettelse', 'rigtig-for-mig'),
                'placeholders' => array(
                    '{navn}' => __('Ekspertens fulde navn', 'rigtig-for-mig'),
                    '{email}' => __('Ekspertens email', 'rigtig-for-mig'),
                    '{site_name}' => __('Hjemmesidens navn', 'rigtig-for-mig'),
                    '{profil_url}' => __('Link til ekspertens profil', 'rigtig-for-mig'),
                    '{dashboard_url}' => __('Link til ekspert dashboard', 'rigtig-for-mig'),
                ),
                'default_subject' => 'Velkommen som ekspert på {site_name}!',
                'default_body' => "Hej {navn},

Velkommen til {site_name}!

Din ekspertprofil er nu oprettet og klar.

Se din profil: {profil_url}
Gå til dit dashboard: {dashboard_url}

Vi glæder os til at have dig som en del af vores platform.

Med venlig hilsen,
{site_name} teamet"
            ),
            'verification_user' => array(
                'name' => __('Email Verificering (Brugere)', 'rigtig-for-mig'),
                'description' => __('Sendes til brugere for at verificere deres email', 'rigtig-for-mig'),
                'placeholders' => array(
                    '{navn}' => __('Brugerens fulde navn', 'rigtig-for-mig'),
                    '{site_name}' => __('Hjemmesidens navn', 'rigtig-for-mig'),
                    '{verification_url}' => __('Verifikationslink', 'rigtig-for-mig'),
                ),
                'default_subject' => 'Verificer din email på {site_name}',
                'default_body' => "Hej {navn},

Tak for din registrering på {site_name}.

For at bekræfte din email-adresse, klik på linket nedenfor:

{verification_url}

Linket er gyldigt i 24 timer.

Hvis du ikke har oprettet denne konto, kan du ignorere denne email.

Med venlig hilsen,
{site_name} teamet"
            ),
            'verification_expert' => array(
                'name' => __('Email Verificering (Eksperter)', 'rigtig-for-mig'),
                'description' => __('Sendes til eksperter for at verificere deres email', 'rigtig-for-mig'),
                'placeholders' => array(
                    '{navn}' => __('Ekspertens fulde navn', 'rigtig-for-mig'),
                    '{site_name}' => __('Hjemmesidens navn', 'rigtig-for-mig'),
                    '{verification_url}' => __('Verifikationslink', 'rigtig-for-mig'),
                ),
                'default_subject' => 'Verificer din ekspertprofil på {site_name}',
                'default_body' => "Hej {navn},

Tak for at oprette en ekspertprofil på {site_name}.

For at bekræfte din email-adresse og aktivere din profil, klik på linket nedenfor:

{verification_url}

Linket er gyldigt i 24 timer.

Hvis du ikke har oprettet denne profil, kan du ignorere denne email.

Med venlig hilsen,
{site_name} teamet"
            ),
            'new_message' => array(
                'name' => __('Ny Besked Notifikation', 'rigtig-for-mig'),
                'description' => __('Sendes når en bruger modtager en ny besked', 'rigtig-for-mig'),
                'placeholders' => array(
                    '{modtager_navn}' => __('Modtagerens navn', 'rigtig-for-mig'),
                    '{afsender_navn}' => __('Afsenderens navn', 'rigtig-for-mig'),
                    '{besked_preview}' => __('Første 100 tegn af beskeden', 'rigtig-for-mig'),
                    '{besked_url}' => __('Link til beskeder', 'rigtig-for-mig'),
                    '{site_name}' => __('Hjemmesidens navn', 'rigtig-for-mig'),
                ),
                'default_subject' => 'Ny besked fra {afsender_navn}',
                'default_body' => "Hej {modtager_navn},

Du har modtaget en ny besked fra {afsender_navn} på {site_name}:

\"{besked_preview}\"

Læs og svar på beskeden her: {besked_url}

Med venlig hilsen,
{site_name} teamet"
            ),
            'new_rating' => array(
                'name' => __('Ny Rating Notifikation', 'rigtig-for-mig'),
                'description' => __('Sendes til eksperter når de modtager en ny rating', 'rigtig-for-mig'),
                'placeholders' => array(
                    '{ekspert_navn}' => __('Ekspertens navn', 'rigtig-for-mig'),
                    '{rating}' => __('Antal stjerner (1-5)', 'rigtig-for-mig'),
                    '{anmeldelse}' => __('Anmeldelsestekst', 'rigtig-for-mig'),
                    '{bruger_navn}' => __('Brugerens navn', 'rigtig-for-mig'),
                    '{profil_url}' => __('Link til ekspertens profil', 'rigtig-for-mig'),
                    '{site_name}' => __('Hjemmesidens navn', 'rigtig-for-mig'),
                ),
                'default_subject' => 'Ny anmeldelse modtaget ({rating} stjerner)',
                'default_body' => "Hej {ekspert_navn},

Du har modtaget en ny {rating}-stjernet anmeldelse på {site_name}!

Fra: {bruger_navn}

\"{anmeldelse}\"

Se din profil: {profil_url}

Med venlig hilsen,
{site_name} teamet"
            ),
        );
    }

    /**
     * Get template content
     */
    public function get_template($template_id, $return_default = false) {
        $templates = $this->get_available_templates();

        if (!isset($templates[$template_id])) {
            return false;
        }

        if ($return_default) {
            return $templates[$template_id];
        }

        $subject = get_option('rfm_email_template_' . $template_id . '_subject', $templates[$template_id]['default_subject']);
        $body = get_option('rfm_email_template_' . $template_id . '_body', $templates[$template_id]['default_body']);

        return array(
            'subject' => $subject,
            'body' => $body,
            'placeholders' => $templates[$template_id]['placeholders']
        );
    }

    /**
     * Render templates page
     */
    public function render_templates_page() {
        $current_template = isset($_GET['template']) ? sanitize_key($_GET['template']) : 'welcome_user';
        $templates = $this->get_available_templates();

        if (!isset($templates[$current_template])) {
            $current_template = 'welcome_user';
        }

        $template_data = $this->get_template($current_template);
        $default_data = $this->get_template($current_template, true);

        ?>
        <div class="wrap">
            <h1><?php _e('Email Templates', 'rigtig-for-mig'); ?></h1>

            <?php if (isset($_GET['saved'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>✅</strong> <?php _e('Template gemt succesfuldt!', 'rigtig-for-mig'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['reset'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>✅</strong> <?php _e('Template nulstillet til standard!', 'rigtig-for-mig'); ?></p>
                </div>
            <?php endif; ?>

            <div class="rfm-email-templates-container" style="display: flex; gap: 20px;">
                <!-- Template List -->
                <div class="rfm-template-list" style="flex: 0 0 250px;">
                    <h2><?php _e('Templates', 'rigtig-for-mig'); ?></h2>
                    <ul class="rfm-template-nav">
                        <?php foreach ($templates as $id => $template): ?>
                            <li class="<?php echo $current_template === $id ? 'active' : ''; ?>">
                                <a href="<?php echo admin_url('admin.php?page=rfm-email-templates&template=' . $id); ?>">
                                    <?php echo esc_html($template['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Template Editor -->
                <div class="rfm-template-editor" style="flex: 1;">
                    <h2><?php echo esc_html($templates[$current_template]['name']); ?></h2>
                    <p><?php echo esc_html($templates[$current_template]['description']); ?></p>

                    <form method="post" action="">
                        <?php wp_nonce_field('rfm_save_email_template'); ?>
                        <input type="hidden" name="action" value="rfm_save_email_template">
                        <input type="hidden" name="template_id" value="<?php echo esc_attr($current_template); ?>">

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="template_subject"><?php _e('Email Emne', 'rigtig-for-mig'); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                           id="template_subject"
                                           name="template_subject"
                                           value="<?php echo esc_attr($template_data['subject']); ?>"
                                           class="large-text"
                                           required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="template_body"><?php _e('Email Indhold', 'rigtig-for-mig'); ?></label>
                                </th>
                                <td>
                                    <textarea id="template_body"
                                              name="template_body"
                                              rows="15"
                                              class="large-text"
                                              required><?php echo esc_textarea($template_data['body']); ?></textarea>
                                    <p class="description">
                                        <?php _e('Brug placeholders for at indsætte dynamisk indhold.', 'rigtig-for-mig'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <!-- Placeholders Info -->
                        <div class="rfm-placeholders-info" style="background: #f0f0f1; padding: 15px; margin: 20px 0; border-radius: 4px;">
                            <h3><?php _e('Tilgængelige Placeholders', 'rigtig-for-mig'); ?></h3>
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th><?php _e('Placeholder', 'rigtig-for-mig'); ?></th>
                                        <th><?php _e('Beskrivelse', 'rigtig-for-mig'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($template_data['placeholders'] as $placeholder => $description): ?>
                                        <tr>
                                            <td><code><?php echo esc_html($placeholder); ?></code></td>
                                            <td><?php echo esc_html($description); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php _e('Gem Template', 'rigtig-for-mig'); ?>
                            </button>

                            <button type="button"
                                    class="button"
                                    onclick="if(confirm('Er du sikker på at du vil nulstille denne template til standard?')) { window.location.href='<?php echo wp_nonce_url(admin_url('admin.php?page=rfm-email-templates&template=' . $current_template . '&action=reset_template'), 'rfm_reset_email_template'); ?>'; }">
                                <?php _e('Nulstil til Standard', 'rigtig-for-mig'); ?>
                            </button>

                            <button type="button" class="button" id="rfm-preview-email">
                                <?php _e('Preview Email', 'rigtig-for-mig'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>
        </div>

        <style>
            .rfm-template-nav {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .rfm-template-nav li {
                margin: 0;
                padding: 0;
                border-bottom: 1px solid #ddd;
            }
            .rfm-template-nav li a {
                display: block;
                padding: 10px 15px;
                text-decoration: none;
                color: #2271b1;
            }
            .rfm-template-nav li.active a,
            .rfm-template-nav li a:hover {
                background: #f0f0f1;
                color: #2271b1;
            }
            .rfm-template-nav li.active a {
                font-weight: bold;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#rfm-preview-email').on('click', function() {
                var subject = $('#template_subject').val();
                var body = $('#template_body').val();

                var preview = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">';
                preview += '<h3 style="margin: 0 0 10px 0; padding-bottom: 10px; border-bottom: 2px solid #2271b1;">Emne: ' + subject + '</h3>';
                preview += '<div style="white-space: pre-wrap; line-height: 1.6;">' + body + '</div>';
                preview += '</div>';

                var win = window.open('', 'Email Preview', 'width=700,height=600');
                win.document.write('<html><head><title>Email Preview</title></head><body>' + preview + '</body></html>');
            });
        });
        </script>
        <?php
    }

    /**
     * Handle save template
     */
    public function handle_save_template() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'rfm_save_email_template') {
            return;
        }

        check_admin_referer('rfm_save_email_template');

        if (!current_user_can('manage_options')) {
            wp_die(__('Ingen tilladelse', 'rigtig-for-mig'));
        }

        $template_id = sanitize_key($_POST['template_id']);
        $subject = sanitize_text_field($_POST['template_subject']);
        $body = sanitize_textarea_field($_POST['template_body']);

        update_option('rfm_email_template_' . $template_id . '_subject', $subject);
        update_option('rfm_email_template_' . $template_id . '_body', $body);

        wp_redirect(add_query_arg(
            array('page' => 'rfm-email-templates', 'template' => $template_id, 'saved' => '1'),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Handle reset template
     */
    public function handle_reset_template() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'reset_template') {
            return;
        }

        check_admin_referer('rfm_reset_email_template');

        if (!current_user_can('manage_options')) {
            wp_die(__('Ingen tilladelse', 'rigtig-for-mig'));
        }

        $template_id = isset($_GET['template']) ? sanitize_key($_GET['template']) : '';

        if ($template_id) {
            delete_option('rfm_email_template_' . $template_id . '_subject');
            delete_option('rfm_email_template_' . $template_id . '_body');
        }

        wp_redirect(add_query_arg(
            array('page' => 'rfm-email-templates', 'template' => $template_id, 'reset' => '1'),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Send email using template
     *
     * @param string $template_id Template ID
     * @param string $to Recipient email
     * @param array $placeholders Placeholder values
     * @return bool Success
     */
    public static function send_email($template_id, $to, $placeholders = array()) {
        $instance = self::get_instance();
        $template = $instance->get_template($template_id);

        if (!$template) {
            return false;
        }

        // Add default placeholders
        $placeholders['{site_name}'] = get_bloginfo('name');

        // Replace placeholders in subject and body
        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $template['subject']);
        $body = str_replace(array_keys($placeholders), array_values($placeholders), $template['body']);

        // Set headers
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        // Send email
        return wp_mail($to, $subject, $body, $headers);
    }
}
