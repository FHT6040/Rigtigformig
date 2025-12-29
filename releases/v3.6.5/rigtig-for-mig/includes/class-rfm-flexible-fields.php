<?php
/**
 * Rigtig For Mig - Flexible Fields System
 * Version: 3.0.0
 * 
 * This system allows:
 * - Dynamic field definitions from admin
 * - Subscription-based field access
 * - Automatic frontend rendering
 * - Repeater fields for uddannelser, certifikater, etc.
 * - Easy addition of new fields without code changes
 */

if (!defined('ABSPATH')) exit;

class RFM_Flexible_Fields_System {
    
    private static $instance = null;
    
    private $field_types = [
        'text' => 'Tekst (kort)',
        'textarea' => 'Tekst (lang)',
        'email' => 'Email',
        'tel' => 'Telefon',
        'url' => 'URL/Link',
        'number' => 'Tal',
        'date' => 'Dato',
        'select' => 'Dropdown',
        'checkbox' => 'Checkboks',
        'repeater' => 'Gentaget gruppe (f.eks. uddannelser)',
        'image' => 'Billede upload',
        'wysiwyg' => 'Tekst editor'
    ];
    
    private $subscription_tiers = [
        'free' => 'Gratis',
        'standard' => 'Standard (219 DKK)',
        'premium' => 'Premium (399 DKK)'
    ];
    
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
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        
        // AJAX handlers for admin UI
        add_action('wp_ajax_rfm_save_field_group', [$this, 'ajax_save_field_group']);
        add_action('wp_ajax_rfm_save_field', [$this, 'ajax_save_field']);
        add_action('wp_ajax_rfm_delete_field_group', [$this, 'ajax_delete_field_group']);
        add_action('wp_ajax_rfm_delete_field', [$this, 'ajax_delete_field']);
        add_action('wp_ajax_rfm_get_field_data', [$this, 'ajax_get_field_data']);
        
        // Frontend rendering
        add_shortcode('rfm_expert_profile_editor', [$this, 'render_frontend_editor']);
        add_action('wp_ajax_rfm_save_profile_data', [$this, 'save_profile_data']);
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin page
        if ($hook !== 'rigtig-for-mig_page_rfm-field-manager') {
            return;
        }
        
        wp_enqueue_script('rfm-fields-admin', RFM_PLUGIN_URL . 'assets/js/fields-admin.js', ['jquery'], RFM_VERSION, true);
        
        wp_localize_script('rfm-fields-admin', 'rfmFieldsAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rfm_fields_admin'),
            'strings' => [
                'confirm_delete_group' => 'Er du sikker på at du vil slette denne felt-gruppe?',
                'confirm_delete_field' => 'Er du sikker på at du vil slette dette felt?',
                'error' => 'Der opstod en fejl. Prøv igen.',
                'saved' => 'Gemt!'
            ]
        ]);
    }
    
    /**
     * Add admin menu for field management
     */
    public function add_admin_menu() {
        add_submenu_page(
            'rfm-dashboard',
            'Profil Felter',
            'Profil Felter',
            'manage_options',
            'rfm-field-manager',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('rfm_fields_group', 'rfm_profile_fields');
        
        // Initialize default field groups in database if not exists
        $this->maybe_init_default_groups();
    }
    
    /**
     * Initialize default field groups in database if they don't exist
     */
    private function maybe_init_default_groups() {
        $existing_fields = get_option('rfm_profile_fields', []);
        
        // If option doesn't exist or is empty, initialize with defaults
        if (empty($existing_fields)) {
            $default_fields = $this->get_default_fields();
            update_option('rfm_profile_fields', $default_fields);
        }
        
        // Ensure default groups exist even if option was manually modified
        $default_fields = $this->get_default_fields();
        $fields_to_save = $existing_fields;
        $needs_update = false;
        
        foreach ($default_fields as $group_key => $group_data) {
            if (!isset($existing_fields[$group_key])) {
                $fields_to_save[$group_key] = $group_data;
                $needs_update = true;
            }
        }
        
        if ($needs_update) {
            update_option('rfm_profile_fields', $fields_to_save);
        }
    }
    
    /**
     * AJAX: Save field group
     */
    public function ajax_save_field_group() {
        check_ajax_referer('rfm_fields_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Ingen adgang']);
        }
        
        $group_key = sanitize_key($_POST['group_key']);
        $group_label = sanitize_text_field($_POST['group_label']);
        
        if (empty($group_key) || empty($group_label)) {
            wp_send_json_error(['message' => 'Gruppe nøgle og label er påkrævet']);
        }
        
        $fields = get_option('rfm_profile_fields', []);
        
        if (isset($fields[$group_key])) {
            wp_send_json_error(['message' => 'Denne gruppe nøgle eksisterer allerede']);
        }
        
        $fields[$group_key] = [
            'label' => $group_label,
            'is_default' => false,
            'fields' => []
        ];
        
        update_option('rfm_profile_fields', $fields);
        
        wp_send_json_success([
            'message' => 'Felt-gruppe oprettet',
            'group_key' => $group_key
        ]);
    }
    
    /**
     * AJAX: Save field
     */
    public function ajax_save_field() {
        check_ajax_referer('rfm_fields_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Ingen adgang']);
        }
        
        $group_key = sanitize_key($_POST['group_key']);
        $field_key = sanitize_key($_POST['field_key']);
        $field_data = $_POST['field_data'];
        
        if (empty($group_key) || empty($field_key)) {
            wp_send_json_error(['message' => 'Gruppe og felt nøgle er påkrævet']);
        }
        
        // Sanitize field data
        $sanitized_field = [
            'type' => sanitize_text_field($field_data['type']),
            'label' => sanitize_text_field($field_data['label']),
            'required' => (bool)($field_data['required'] ?? false),
            'subscription_required' => sanitize_text_field($field_data['subscription_required']),
            'description' => sanitize_text_field($field_data['description'] ?? ''),
        ];
        
        // Handle repeater-specific fields
        if ($sanitized_field['type'] === 'repeater') {
            $sanitized_field['max_items'] = sanitize_text_field($field_data['max_items'] ?? '');
            
            if (!empty($field_data['sub_fields'])) {
                $sanitized_field['sub_fields'] = [];
                foreach ($field_data['sub_fields'] as $sub_key => $sub_field) {
                    $sanitized_field['sub_fields'][sanitize_key($sub_key)] = [
                        'type' => sanitize_text_field($sub_field['type']),
                        'label' => sanitize_text_field($sub_field['label']),
                        'required' => (bool)($sub_field['required'] ?? false)
                    ];
                }
            }
        }
        
        $all_fields = get_option('rfm_profile_fields', []);
        
        if (!isset($all_fields[$group_key])) {
            wp_send_json_error(['message' => 'Felt-gruppe findes ikke']);
        }
        
        $all_fields[$group_key]['fields'][$field_key] = $sanitized_field;
        
        update_option('rfm_profile_fields', $all_fields);
        
        wp_send_json_success([
            'message' => 'Felt gemt',
            'field_key' => $field_key
        ]);
    }
    
    /**
     * AJAX: Delete field group
     */
    public function ajax_delete_field_group() {
        check_ajax_referer('rfm_fields_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Ingen adgang']);
        }
        
        $group_key = sanitize_key($_POST['group_key']);
        
        $fields = get_option('rfm_profile_fields', []);
        
        if (!isset($fields[$group_key])) {
            wp_send_json_error(['message' => 'Felt-gruppe findes ikke']);
        }
        
        unset($fields[$group_key]);
        update_option('rfm_profile_fields', $fields);
        
        wp_send_json_success(['message' => 'Felt-gruppe slettet']);
    }
    
    /**
     * AJAX: Delete field
     */
    public function ajax_delete_field() {
        check_ajax_referer('rfm_fields_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Ingen adgang']);
        }
        
        $group_key = sanitize_key($_POST['group_key']);
        $field_key = sanitize_key($_POST['field_key']);
        
        $fields = get_option('rfm_profile_fields', []);
        
        if (!isset($fields[$group_key]['fields'][$field_key])) {
            wp_send_json_error(['message' => 'Felt findes ikke']);
        }
        
        unset($fields[$group_key]['fields'][$field_key]);
        update_option('rfm_profile_fields', $fields);
        
        wp_send_json_success(['message' => 'Felt slettet']);
    }
    
    /**
     * AJAX: Get field data for editing
     */
    public function ajax_get_field_data() {
        check_ajax_referer('rfm_fields_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Ingen adgang']);
        }
        
        $group_key = sanitize_key($_POST['group_key']);
        $field_key = sanitize_key($_POST['field_key']);
        
        $all_fields = $this->get_fields();
        
        if (!isset($all_fields[$group_key]['fields'][$field_key])) {
            wp_send_json_error(['message' => 'Felt findes ikke']);
        }
        
        wp_send_json_success([
            'field_data' => $all_fields[$group_key]['fields'][$field_key]
        ]);
    }
    
    /**
     * Get default field groups
     */
    private function get_default_fields() {
        return [
            'basic_info' => [
                'label' => 'Basis Information',
                'is_default' => true,
                'fields' => [
                    'email' => [
                        'type' => 'email',
                        'label' => 'Email',
                        'required' => true,
                        'subscription_required' => 'free',
                        'editable' => false,
                        'description' => 'Din email adresse'
                    ],
                    'phone' => [
                        'type' => 'tel',
                        'label' => 'Telefon',
                        'required' => false,
                        'subscription_required' => 'free',
                        'description' => 'Dit telefonnummer'
                    ],
                    'bio' => [
                        'type' => 'wysiwyg',
                        'label' => 'Om mig',
                        'required' => false,
                        'subscription_required' => 'free',
                        'description' => 'Fortæl om dig selv'
                    ],
                    'website' => [
                        'type' => 'url',
                        'label' => 'Hjemmeside',
                        'required' => false,
                        'subscription_required' => 'standard',
                        'description' => 'Din hjemmeside eller portfolio'
                    ]
                ]
            ],
            'uddannelser' => [
                'label' => 'Uddannelser',
                'is_default' => true,
                'fields' => [
                    'uddannelser' => [
                        'type' => 'repeater',
                        'label' => 'Uddannelser',
                        'required' => false,
                        'subscription_required' => 'free',
                        'max_items' => 'free:1|standard:3|premium:7',
                        'sub_fields' => [
                            'navn' => [
                                'type' => 'text',
                                'label' => 'Uddannelsesnavn',
                                'required' => true
                            ],
                            'institution' => [
                                'type' => 'text',
                                'label' => 'Institution',
                                'required' => false
                            ],
                            'aar' => [
                                'type' => 'text',
                                'label' => 'År (f.eks. 2018-2022)',
                                'required' => false
                            ],
                            'experience_start_year' => [
                                'type' => 'number',
                                'label' => 'År startet i praksis',
                                'required' => false,
                                'description' => 'Hvornår begyndte du at arbejde med denne uddannelse? Bruges til at vise års erfaring.'
                            ],
                            'beskrivelse' => [
                                'type' => 'textarea',
                                'label' => 'Beskrivelse',
                                'required' => false
                            ],
                            'diplom_billede' => [
                                'type' => 'image',
                                'label' => 'Diplom/Certifikat',
                                'required' => false,
                                'subscription_required' => 'standard',
                                'description' => 'Upload billede af dit diplom eller certifikat (kun Standard og Premium medlemmer)'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Get all defined fields
     */
    public function get_fields() {
        // Since we now initialize defaults in database, just return from DB
        $fields = get_option('rfm_profile_fields', []);
        
        // Fallback: if somehow empty, return defaults
        if (empty($fields)) {
            return $this->get_default_fields();
        }
        
        return $fields;
    }
    
    /**
     * Old get_fields method kept for compatibility
     */
    private function get_fields_old() {
        $default_fields = [
            'basic_info' => [
                'label' => 'Basis Information',
                'fields' => [
                    'email' => [
                        'type' => 'email',
                        'label' => 'Email',
                        'required' => true,
                        'subscription_required' => 'free',
                        'editable' => false, // Email kan ikke ændres
                        'description' => 'Din email adresse'
                    ],
                    'phone' => [
                        'type' => 'tel',
                        'label' => 'Telefon',
                        'required' => false,
                        'subscription_required' => 'free',
                        'description' => 'Dit telefonnummer'
                    ],
                    'bio' => [
                        'type' => 'wysiwyg',
                        'label' => 'Om mig',
                        'required' => false,
                        'subscription_required' => 'free',
                        'description' => 'Fortæl om dig selv'
                    ],
                    'website' => [
                        'type' => 'url',
                        'label' => 'Hjemmeside',
                        'required' => false,
                        'subscription_required' => 'standard',
                        'description' => 'Din hjemmeside eller portfolio'
                    ]
                ]
            ],
            'uddannelser' => [
                'label' => 'Uddannelser',
                'fields' => [
                    'uddannelser' => [
                        'type' => 'repeater',
                        'label' => 'Uddannelser',
                        'required' => false,
                        'subscription_required' => 'free',
                        'max_items' => 'free:3|standard:10|premium:unlimited',
                        'sub_fields' => [
                            'navn' => [
                                'type' => 'text',
                                'label' => 'Uddannelsesnavn',
                                'required' => true
                            ],
                            'institution' => [
                                'type' => 'text',
                                'label' => 'Institution',
                                'required' => true
                            ],
                            'aar' => [
                                'type' => 'text',
                                'label' => 'År (f.eks. 2018-2022)',
                                'required' => false
                            ],
                            'beskrivelse' => [
                                'type' => 'textarea',
                                'label' => 'Beskrivelse',
                                'required' => false
                            ],
                            'diplom_billede' => [
                                'type' => 'image',
                                'label' => 'Diplom/Certifikat',
                                'required' => false,
                                'subscription_required' => 'standard',
                                'description' => 'Upload billede af dit diplom eller certifikat (kun Standard og Premium medlemmer)'
                            ]
                        ]
                    ]
                ]
            ],
            'certifikater' => [
                'label' => 'Certifikater & Kurser',
                'fields' => [
                    'certifikater' => [
                        'type' => 'repeater',
                        'label' => 'Certifikater',
                        'required' => false,
                        'subscription_required' => 'standard',
                        'max_items' => 'standard:5|premium:unlimited',
                        'sub_fields' => [
                            'navn' => [
                                'type' => 'text',
                                'label' => 'Certifikat/Kursus navn',
                                'required' => true
                            ],
                            'udsteder' => [
                                'type' => 'text',
                                'label' => 'Udstedt af',
                                'required' => false
                            ],
                            'aar' => [
                                'type' => 'text',
                                'label' => 'År',
                                'required' => false
                            ],
                            'billede' => [
                                'type' => 'image',
                                'label' => 'Certifikat billede',
                                'required' => false
                            ]
                        ]
                    ]
                ]
            ],
            'specialer' => [
                'label' => 'Specialer & Ekspertise',
                'fields' => [
                    'specialer' => [
                        'type' => 'repeater',
                        'label' => 'Specialer',
                        'required' => false,
                        'subscription_required' => 'free',
                        'max_items' => 'free:3|standard:8|premium:unlimited',
                        'sub_fields' => [
                            'navn' => [
                                'type' => 'text',
                                'label' => 'Speciale',
                                'required' => true
                            ],
                            'beskrivelse' => [
                                'type' => 'textarea',
                                'label' => 'Beskrivelse',
                                'required' => false
                            ]
                        ]
                    ]
                ]
            ],
            'priser' => [
                'label' => 'Priser & Tilbud',
                'fields' => [
                    'session_pris' => [
                        'type' => 'number',
                        'label' => 'Pris pr. session (DKK)',
                        'required' => false,
                        'subscription_required' => 'standard',
                        'description' => 'Standard pris for en session'
                    ],
                    'pakker' => [
                        'type' => 'repeater',
                        'label' => 'Pakker & Tilbud',
                        'required' => false,
                        'subscription_required' => 'premium',
                        'max_items' => 'premium:unlimited',
                        'sub_fields' => [
                            'navn' => [
                                'type' => 'text',
                                'label' => 'Pakke navn',
                                'required' => true
                            ],
                            'pris' => [
                                'type' => 'number',
                                'label' => 'Pris (DKK)',
                                'required' => true
                            ],
                            'beskrivelse' => [
                                'type' => 'textarea',
                                'label' => 'Hvad er inkluderet',
                                'required' => false
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        // Get custom fields from database
        $custom_fields = get_option('rfm_profile_fields', []);
        
        // Merge default with custom
        return array_merge($default_fields, $custom_fields);
    }
    
    /**
     * Check if user has access to a field based on subscription
     */
    public function user_can_access_field($user_id, $field_config) {
        $user_subscription = get_user_meta($user_id, 'rfm_subscription_tier', true) ?: 'free';
        $required_subscription = $field_config['subscription_required'] ?? 'free';
        
        $tier_levels = ['free' => 0, 'standard' => 1, 'premium' => 2];
        
        return $tier_levels[$user_subscription] >= $tier_levels[$required_subscription];
    }
    
    /**
     * Parse max_items string
     */
    private function parse_max_items($max_items_string, $user_subscription) {
        if (empty($max_items_string)) return PHP_INT_MAX;
        
        $limits = [];
        $parts = explode('|', $max_items_string);
        
        foreach ($parts as $part) {
            list($tier, $limit) = explode(':', $part);
            $limits[trim($tier)] = trim($limit) === 'unlimited' ? PHP_INT_MAX : (int)trim($limit);
        }
        
        return $limits[$user_subscription] ?? PHP_INT_MAX;
    }
    
    /**
     * Render admin page for field management
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;
        
        $fields = $this->get_fields();
        ?>
        <div class="wrap rfm-fields-admin-wrap">
            <h1>Profil Felter Administration</h1>
            <p class="description">
                Her kan du tilføje, redigere og administrere alle felter der er tilgængelige i ekspert-profiler.
                Felterne vil automatisk blive vist i frontend dashboard baseret på ekspertens medlemsniveau.
            </p>
            
            <div class="rfm-fields-admin">
                <div class="rfm-fields-list">
                    <h2>Eksisterende Felt-grupper</h2>
                    
                    <?php foreach ($fields as $group_key => $group): ?>
                        <div class="rfm-field-group" data-group="<?php echo esc_attr($group_key); ?>">
                            <div class="rfm-group-header">
                                <h3>
                                    <?php echo esc_html($group['label']); ?>
                                    <?php if (!empty($group['is_default'])): ?>
                                        <span class="rfm-badge rfm-badge-default">Standard</span>
                                    <?php endif; ?>
                                </h3>
                                <div class="rfm-group-actions">
                                    <button class="button button-small rfm-add-field-btn" data-group="<?php echo esc_attr($group_key); ?>">
                                        <span class="dashicons dashicons-plus"></span> Tilføj Felt
                                    </button>
                                    <?php if (empty($group['is_default'])): ?>
                                        <button class="button button-small button-link-delete rfm-delete-group-btn" data-group="<?php echo esc_attr($group_key); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Felt navn</th>
                                        <th>Type</th>
                                        <th>Kræver medlemskab</th>
                                        <th>Påkrævet</th>
                                        <th>Handlinger</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($group['fields'] as $field_key => $field): ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($field['label']); ?></strong></td>
                                            <td><?php echo esc_html($this->field_types[$field['type']] ?? $field['type']); ?></td>
                                            <td>
                                                <span class="subscription-badge subscription-<?php echo esc_attr($field['subscription_required']); ?>">
                                                    <?php echo esc_html($this->subscription_tiers[$field['subscription_required']]); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $field['required'] ? '✓ Ja' : '✗ Nej'; ?></td>
                                            <td>
                                                <?php if (empty($group['is_default'])): ?>
                                                    <button class="button button-small rfm-edit-field-btn" 
                                                            data-group="<?php echo esc_attr($group_key); ?>"
                                                            data-field="<?php echo esc_attr($field_key); ?>">
                                                        Rediger
                                                    </button>
                                                    <button class="button button-small button-link-delete rfm-delete-field-btn"
                                                            data-group="<?php echo esc_attr($group_key); ?>"
                                                            data-field="<?php echo esc_attr($field_key); ?>">
                                                        Slet
                                                    </button>
                                                <?php else: ?>
                                                    <em>Standard felt</em>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        
                                        <?php if ($field['type'] === 'repeater' && !empty($field['sub_fields'])): ?>
                                            <tr class="sub-fields-row">
                                                <td colspan="5" style="padding-left: 40px;">
                                                    <small><strong>Underfelter:</strong></small>
                                                    <ul style="margin: 5px 0;">
                                                        <?php foreach ($field['sub_fields'] as $sub_key => $sub_field): ?>
                                                            <li>
                                                                <?php echo esc_html($sub_field['label']); ?>
                                                                (<?php echo esc_html($this->field_types[$sub_field['type']]); ?>)
                                                                <?php if (!empty($field['max_items'])): ?>
                                                                    <br><em>Max antal: <?php echo esc_html($field['max_items']); ?></em>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="rfm-add-field-section">
                    <h2>Tilføj ny felt-gruppe</h2>
                    <button class="button button-primary button-large" id="rfm-add-group-btn">
                        + Tilføj ny felt-gruppe
                    </button>
                    
                    <div class="rfm-info-box" style="margin-top: 20px;">
                        <h3>💡 Tips til fremtidig fleksibilitet</h3>
                        <ul>
                            <li><strong>Repeater felter</strong> er perfekte til uddannelser, certifikater, specialer osv.</li>
                            <li><strong>Max antal begrænsninger</strong> kan sættes per subscription tier (f.eks. "free:3|standard:10|premium:unlimited")</li>
                            <li><strong>Nye felter</strong> vil automatisk blive vist i frontend når du tilføjer dem her</li>
                            <li><strong>Premium features</strong> kan låses bag "premium" subscription requirement</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <style>
                .rfm-fields-admin {
                    display: grid;
                    grid-template-columns: 2fr 1fr;
                    gap: 30px;
                    margin-top: 20px;
                }
                .rfm-field-group {
                    background: white;
                    padding: 20px;
                    margin-bottom: 20px;
                    border: 1px solid #ccd0d4;
                    box-shadow: 0 1px 1px rgba(0,0,0,.04);
                }
                .subscription-badge {
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                .subscription-free {
                    background: #e0e0e0;
                    color: #333;
                }
                .subscription-standard {
                    background: #fff3cd;
                    color: #856404;
                }
                .subscription-premium {
                    background: #d4edda;
                    color: #155724;
                }
                .sub-fields-row {
                    background: #f9f9f9;
                }
                .rfm-info-box {
                    background: #e7f5fe;
                    border-left: 4px solid #0073aa;
                    padding: 15px;
                }
                .rfm-info-box h3 {
                    margin-top: 0;
                }
            </style>
            
            <!-- Modal: Add Field Group -->
            <div id="rfm-add-group-modal" class="rfm-modal" style="display:none;">
                <div class="rfm-modal-content">
                    <div class="rfm-modal-header">
                        <h2>Tilføj Ny Felt-gruppe</h2>
                        <button class="rfm-modal-close">&times;</button>
                    </div>
                    <div class="rfm-modal-body">
                        <form id="rfm-add-group-form">
                            <table class="form-table">
                                <tr>
                                    <th><label for="group_key">Gruppe Nøgle *</label></th>
                                    <td>
                                        <input type="text" id="group_key" name="group_key" class="regular-text" required 
                                               pattern="[a-z0-9_]+" placeholder="eks: social_media">
                                        <p class="description">Kun små bogstaver, tal og underscore.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="group_label">Gruppe Label *</label></th>
                                    <td>
                                        <input type="text" id="group_label" name="group_label" class="regular-text" required 
                                               placeholder="eks: Social Media">
                                        <p class="description">Navn der vises til brugeren.</p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary">Opret Felt-gruppe</button>
                                <button type="button" class="button rfm-modal-close">Annuller</button>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Modal: Add/Edit Field -->
            <div id="rfm-field-modal" class="rfm-modal" style="display:none;">
                <div class="rfm-modal-content rfm-modal-large">
                    <div class="rfm-modal-header">
                        <h2 id="rfm-field-modal-title">Tilføj Nyt Felt</h2>
                        <button class="rfm-modal-close">&times;</button>
                    </div>
                    <div class="rfm-modal-body">
                        <form id="rfm-field-form">
                            <input type="hidden" id="field_group_key" name="group_key">
                            <input type="hidden" id="field_mode" value="add">
                            
                            <table class="form-table">
                                <tr>
                                    <th><label for="field_key">Felt Nøgle *</label></th>
                                    <td>
                                        <input type="text" id="field_key" name="field_key" class="regular-text" required 
                                               pattern="[a-z0-9_]+" placeholder="eks: linkedin_url">
                                        <p class="description">Kun små bogstaver, tal og underscore.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="field_label">Felt Label *</label></th>
                                    <td>
                                        <input type="text" id="field_label" name="field_label" class="regular-text" required 
                                               placeholder="eks: LinkedIn Profil">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="field_type">Felt Type *</label></th>
                                    <td>
                                        <select id="field_type" name="field_type" required>
                                            <?php foreach ($this->field_types as $type_key => $type_label): ?>
                                                <option value="<?php echo esc_attr($type_key); ?>"><?php echo esc_html($type_label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="field_subscription">Kræver Medlemskab *</label></th>
                                    <td>
                                        <select id="field_subscription" name="field_subscription" required>
                                            <?php foreach ($this->subscription_tiers as $tier_key => $tier_label): ?>
                                                <option value="<?php echo esc_attr($tier_key); ?>"><?php echo esc_html($tier_label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="field_required">Påkrævet</label></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="field_required" name="field_required" value="1">
                                            Eksperten skal udfylde dette felt
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="field_description">Beskrivelse</label></th>
                                    <td>
                                        <input type="text" id="field_description" name="field_description" class="large-text" 
                                               placeholder="Hjælpetekst der vises under feltet">
                                    </td>
                                </tr>
                                
                                <!-- Repeater-specific fields -->
                                <tr id="field_max_items_row" style="display:none;">
                                    <th><label for="field_max_items">Max Antal</label></th>
                                    <td>
                                        <input type="text" id="field_max_items" name="field_max_items" class="regular-text" 
                                               placeholder="free:3|standard:10|premium:unlimited">
                                        <p class="description">Format: tier:antal|tier:antal</p>
                                    </td>
                                </tr>
                                
                                <tr id="field_sub_fields_row" style="display:none;">
                                    <th><label>Underfelter</label></th>
                                    <td>
                                        <div id="sub_fields_container">
                                            <p class="description">Tilføj underfelter for repeater:</p>
                                            <div id="sub_fields_list"></div>
                                            <button type="button" class="button" id="add_sub_field_btn">+ Tilføj Underfelt</button>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="submit" class="button button-primary">Gem Felt</button>
                                <button type="button" class="button rfm-modal-close">Annuller</button>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
            
            <style>
                .rfm-group-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 15px 20px;
                    border-bottom: 1px solid #f0f0f0;
                    background: #fafafa;
                }
                .rfm-group-header h3 {
                    margin: 0;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .rfm-group-actions {
                    display: flex;
                    gap: 10px;
                }
                .rfm-badge {
                    font-size: 11px;
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                .rfm-badge-default {
                    background: #e0e0e0;
                    color: #666;
                }
                
                /* Modal Styles */
                .rfm-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.7);
                    z-index: 100000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .rfm-modal-content {
                    background: white;
                    border-radius: 4px;
                    width: 90%;
                    max-width: 600px;
                    max-height: 90vh;
                    overflow-y: auto;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                }
                .rfm-modal-large {
                    max-width: 800px;
                }
                .rfm-modal-header {
                    padding: 20px;
                    border-bottom: 1px solid #ddd;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .rfm-modal-header h2 {
                    margin: 0;
                }
                .rfm-modal-close {
                    background: none;
                    border: none;
                    font-size: 28px;
                    line-height: 1;
                    cursor: pointer;
                    color: #666;
                }
                .rfm-modal-close:hover {
                    color: #000;
                }
                .rfm-modal-body {
                    padding: 20px;
                }
                
                /* Sub-fields builder */
                .sub-field-item {
                    background: #f5f5f5;
                    padding: 15px;
                    margin-bottom: 10px;
                    border-radius: 4px;
                    border: 1px solid #ddd;
                }
                .sub-field-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 10px;
                }
                .sub-field-inputs {
                    display: grid;
                    grid-template-columns: 1fr 1fr 150px;
                    gap: 10px;
                }
                .remove-sub-field {
                    color: #a00;
                    cursor: pointer;
                }
                .remove-sub-field:hover {
                    color: #dc3232;
                }
            </style>
        </div>
        <?php
    }
    
    /**
     * Render frontend profile editor
     */
    public function render_frontend_editor($atts) {
        if (!is_user_logged_in()) {
            return '<p>Du skal være logget ind for at redigere din profil.</p>';
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        // Check if user is expert
        if (!in_array('rfm_expert_user', $user->roles)) {
            return '<p>Kun eksperter kan redigere profiler.</p>';
        }
        
        $user_subscription = get_user_meta($user_id, 'rfm_subscription_tier', true) ?: 'free';
        $fields = $this->get_fields();
        
        ob_start();
        ?>
        <div class="rfm-profile-editor">
            <form id="rfm-profile-form" class="rfm-frontend-form">
                <?php wp_nonce_field('rfm_save_profile', 'rfm_profile_nonce'); ?>
                
                <?php foreach ($fields as $group_key => $group): ?>
                    <div class="rfm-field-group-section">
                        <h2><?php echo esc_html($group['label']); ?></h2>
                        
                        <?php foreach ($group['fields'] as $field_key => $field): ?>
                            <?php
                            // Check access
                            if (!$this->user_can_access_field($user_id, $field)) {
                                $this->render_locked_field($field, $user_subscription);
                                continue;
                            }
                            
                            // Get current value
                            $meta_key = 'rfm_' . $group_key . '_' . $field_key;
                            $current_value = get_user_meta($user_id, $meta_key, true);
                            
                            // Render field
                            $this->render_field($field_key, $field, $current_value, $user_subscription);
                            ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="rfm-form-actions">
                    <button type="submit" class="rfm-button rfm-button-primary">
                        Gem ændringer
                    </button>
                    <span class="rfm-save-status"></span>
                </div>
            </form>
        </div>
        
        <style>
            .rfm-profile-editor {
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
            }
            .rfm-field-group-section {
                background: white;
                padding: 30px;
                margin-bottom: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .rfm-field-group-section h2 {
                margin-top: 0;
                padding-bottom: 15px;
                border-bottom: 2px solid #f0f0f0;
            }
            .rfm-field-wrapper {
                margin-bottom: 25px;
            }
            .rfm-field-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #333;
            }
            .rfm-field-description {
                font-size: 13px;
                color: #666;
                margin-top: 5px;
            }
            .rfm-field-required {
                color: #d63638;
            }
            .rfm-field input[type="text"],
            .rfm-field input[type="email"],
            .rfm-field input[type="tel"],
            .rfm-field input[type="url"],
            .rfm-field input[type="number"],
            .rfm-field textarea {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }
            .rfm-field textarea {
                min-height: 100px;
                resize: vertical;
            }
            .rfm-repeater-field {
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                padding: 20px;
                background: #f9f9f9;
            }
            .rfm-repeater-item {
                background: white;
                padding: 20px;
                margin-bottom: 15px;
                border-radius: 4px;
                border: 1px solid #ddd;
                position: relative;
            }
            .rfm-repeater-remove {
                position: absolute;
                top: 10px;
                right: 10px;
                background: #d63638;
                color: white;
                border: none;
                padding: 5px 12px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
            }
            .rfm-repeater-remove:hover {
                background: #b02a2c;
            }
            .rfm-repeater-add {
                margin-top: 15px;
                background: #2271b1;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }
            .rfm-repeater-add:hover {
                background: #135e96;
            }
            .rfm-repeater-limit-notice {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 12px;
                margin-top: 10px;
                font-size: 13px;
            }
            .rfm-locked-field {
                background: #f5f5f5;
                border: 2px dashed #ccc;
                padding: 20px;
                text-align: center;
                border-radius: 6px;
                margin-bottom: 20px;
            }
            .rfm-locked-field-icon {
                font-size: 40px;
                margin-bottom: 10px;
            }
            .rfm-upgrade-button {
                background: #ff9800;
                color: white;
                border: none;
                padding: 10px 25px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 600;
                margin-top: 10px;
            }
            .rfm-upgrade-button:hover {
                background: #f57c00;
            }
            .rfm-form-actions {
                text-align: center;
                padding: 20px 0;
            }
            .rfm-button {
                padding: 12px 30px;
                font-size: 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-weight: 600;
            }
            .rfm-button-primary {
                background: #00a859;
                color: white;
            }
            .rfm-button-primary:hover {
                background: #008f4a;
            }
            .rfm-save-status {
                margin-left: 15px;
                font-size: 14px;
            }
            .rfm-save-status.success {
                color: #00a859;
            }
            .rfm-save-status.error {
                color: #d63638;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle repeater add
            $(document).on('click', '.rfm-repeater-add', function(e) {
                e.preventDefault();
                var $repeater = $(this).closest('.rfm-repeater-field');
                var $items = $repeater.find('.rfm-repeater-items');
                var $template = $repeater.find('.rfm-repeater-template');
                var index = $items.find('.rfm-repeater-item').length;
                
                // Check limit
                var maxItems = parseInt($(this).data('max-items'));
                if (index >= maxItems) {
                    alert('Du har nået maksimum antal for dit medlemsniveau.');
                    return;
                }
                
                var $newItem = $template.clone();
                $newItem.removeClass('rfm-repeater-template').addClass('rfm-repeater-item');
                $newItem.find('input, textarea').each(function() {
                    var name = $(this).attr('name');
                    $(this).attr('name', name.replace('[__INDEX__]', '[' + index + ']'));
                });
                
                $items.append($newItem);
                $newItem.show();
            });
            
            // Handle repeater remove
            $(document).on('click', '.rfm-repeater-remove', function(e) {
                e.preventDefault();
                if (confirm('Er du sikker på at du vil fjerne dette element?')) {
                    $(this).closest('.rfm-repeater-item').remove();
                }
            });
            
            // Handle form submit
            $('#rfm-profile-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $status = $('.rfm-save-status');
                var $button = $form.find('button[type="submit"]');
                
                $button.prop('disabled', true);
                $status.text('Gemmer...').removeClass('success error');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: $form.serialize() + '&action=rfm_save_profile_data',
                    success: function(response) {
                        if (response.success) {
                            $status.text('✓ Gemt!').addClass('success');
                            setTimeout(function() {
                                $status.text('');
                            }, 3000);
                        } else {
                            $status.text('✗ Fejl: ' + response.data.message).addClass('error');
                        }
                    },
                    error: function() {
                        $status.text('✗ Der opstod en fejl').addClass('error');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render a single field
     */
    private function render_field($field_key, $field, $current_value, $user_subscription) {
        $required_attr = $field['required'] ? 'required' : '';
        $required_label = $field['required'] ? '<span class="rfm-field-required">*</span>' : '';
        
        echo '<div class="rfm-field-wrapper">';
        echo '<label class="rfm-field-label">' . esc_html($field['label']) . ' ' . $required_label . '</label>';
        echo '<div class="rfm-field">';
        
        switch ($field['type']) {
            case 'text':
            case 'email':
            case 'tel':
            case 'url':
            case 'number':
                $readonly = !empty($field['editable']) && !$field['editable'] ? 'readonly' : '';
                echo '<input type="' . esc_attr($field['type']) . '" 
                      name="rfm_field[' . esc_attr($field_key) . ']" 
                      value="' . esc_attr($current_value) . '" 
                      ' . $required_attr . ' 
                      ' . $readonly . '>';
                break;
                
            case 'textarea':
                echo '<textarea name="rfm_field[' . esc_attr($field_key) . ']" 
                      rows="5" ' . $required_attr . '>' . esc_textarea($current_value) . '</textarea>';
                break;
                
            case 'repeater':
                $this->render_repeater_field($field_key, $field, $current_value, $user_subscription);
                break;
                
            // Add more field types as needed
        }
        
        if (!empty($field['description'])) {
            echo '<p class="rfm-field-description">' . esc_html($field['description']) . '</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render repeater field
     */
    private function render_repeater_field($field_key, $field, $current_value, $user_subscription) {
        $items = is_array($current_value) ? $current_value : [];
        $max_items = $this->parse_max_items($field['max_items'] ?? '', $user_subscription);
        $can_add_more = count($items) < $max_items;
        
        echo '<div class="rfm-repeater-field" data-field="' . esc_attr($field_key) . '">';
        echo '<div class="rfm-repeater-items">';
        
        foreach ($items as $index => $item) {
            $this->render_repeater_item($field_key, $field['sub_fields'], $item, $index);
        }
        
        echo '</div>';
        
        // Template for new items (hidden)
        echo '<div class="rfm-repeater-template" style="display:none;">';
        $this->render_repeater_item($field_key, $field['sub_fields'], [], '__INDEX__');
        echo '</div>';
        
        if ($can_add_more) {
            echo '<button type="button" class="rfm-repeater-add" data-max-items="' . esc_attr($max_items) . '">
                  + Tilføj ' . esc_html($field['label']) . '
                  </button>';
        }
        
        if (count($items) >= $max_items && $max_items < PHP_INT_MAX) {
            echo '<div class="rfm-repeater-limit-notice">
                  Du har nået maksimum antal (' . $max_items . ') for dit medlemsniveau.
                  ' . ($user_subscription !== 'premium' ? 'Opgrader for at tilføje flere.' : '') . '
                  </div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render single repeater item
     */
    private function render_repeater_item($field_key, $sub_fields, $item, $index) {
        $user_id = get_current_user_id();
        $user_subscription = get_user_meta($user_id, '_rfm_subscription_type', true) ?: 'free';
        
        echo '<div class="rfm-repeater-item">';
        echo '<button type="button" class="rfm-repeater-remove">✕ Fjern</button>';
        
        foreach ($sub_fields as $sub_key => $sub_field) {
            $value = $item[$sub_key] ?? '';
            $name = 'rfm_field[' . $field_key . '][' . $index . '][' . $sub_key . ']';
            $required = $sub_field['required'] ? 'required' : '';
            
            // Check subscription requirement for sub-field
            $required_subscription = $sub_field['subscription_required'] ?? 'free';
            $has_access = $this->user_has_subscription_level($user_subscription, $required_subscription);
            
            // Skip låste felter helt (v2.8.7: Holder dashboardet rent)
            if (!$has_access) {
                continue; // Spring over dette felt
            }
            
            echo '<div class="rfm-field-wrapper">';
            echo '<label class="rfm-field-label">' . esc_html($sub_field['label']) . '</label>';
            
            switch ($sub_field['type']) {
                case 'text':
                    echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" ' . $required . '>';
                    break;
                case 'textarea':
                    echo '<textarea name="' . esc_attr($name) . '" rows="3" ' . $required . '>' . esc_textarea($value) . '</textarea>';
                    break;
                case 'image':
                    $this->render_image_field($name, $value, $index, $field_key, $sub_key);
                    break;
                // Add more sub-field types as needed
            }
            
            if (!empty($sub_field['description'])) {
                echo '<p class="rfm-field-description">' . esc_html($sub_field['description']) . '</p>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render image field for repeater items
     */
    private function render_image_field($name, $value, $index, $field_key, $sub_key) {
        $image_url = '';
        $image_id = '';
        
        // Value kan være attachment ID eller URL
        if (!empty($value)) {
            if (is_numeric($value)) {
                $image_id = $value;
                $image_url = wp_get_attachment_url($value);
            } else {
                $image_url = $value;
            }
        }
        
        echo '<div class="rfm-image-field" data-field="' . esc_attr($name) . '">';
        
        if ($image_url) {
            echo '<div class="rfm-image-preview">';
            echo '<img src="' . esc_url($image_url) . '" alt="Diplom/Certifikat">';
            echo '<button type="button" class="rfm-image-remove" data-field-name="' . esc_attr($name) . '">✕ Fjern billede</button>';
            echo '</div>';
            echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($image_id ? $image_id : $image_url) . '">';
        }
        
        echo '<div class="rfm-image-upload-wrapper" ' . ($image_url ? 'style="display:none;"' : '') . '>';
        echo '<input type="file" 
              name="' . esc_attr($name) . '_file" 
              accept="image/*" 
              class="rfm-image-upload"
              data-field-key="' . esc_attr($field_key) . '"
              data-sub-key="' . esc_attr($sub_key) . '"
              data-index="' . esc_attr($index) . '">';
        echo '<label class="rfm-upload-label">
              <span class="rfm-upload-icon">📷</span>
              <span class="rfm-upload-text">Vælg billede</span>
              </label>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render locked field (when subscription requirement not met)
     * Changed in v2.8.7: Skjuler låste felter helt for at holde dashboardet rent
     */
    private function render_locked_field($field, $user_subscription) {
        // Skjul låste felter helt - returnerer ingenting
        // Dette gør dashboardet mere brugervenligt og fokuseret
        return;
        
        // Hvis du vil vise låste felter igen, udkommentér return ovenfor
        // og fjern kommentaren fra koden nedenfor:
        /*
        $required_tier = $this->subscription_tiers[$field['subscription_required']];
        
        echo '<div class="rfm-locked-field">';
        echo '<div class="rfm-locked-field-icon">🔒</div>';
        echo '<h3>' . esc_html($field['label']) . '</h3>';
        echo '<p>Dette felt kræver <strong>' . esc_html($required_tier) . '</strong> medlemskab.</p>';
        echo '<button class="rfm-upgrade-button" onclick="window.location.href=\'/medlemskab\'">
              Opgrader dit medlemskab
              </button>';
        echo '</div>';
        */
    }
    
    /**
     * Save profile data from frontend
     */
    public function save_profile_data() {
        check_ajax_referer('rfm_save_profile', 'rfm_profile_nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Du skal være logget ind']);
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        if (!in_array('rfm_expert_user', $user->roles)) {
            wp_send_json_error(['message' => 'Kun eksperter kan gemme profil data']);
        }
        
        $field_data = $_POST['rfm_field'] ?? [];
        
        // Handle file uploads in repeater fields
        if (!empty($_FILES)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            foreach ($_FILES as $file_field_name => $file_data) {
                if (!empty($file_data['name'])) {
                    // Parse field name to extract field_key, index, and sub_key
                    // Format: rfm_field[uddannelser][0][diplom_billede]_file
                    if (preg_match('/rfm_field\[([^\]]+)\]\[([^\]]+)\]\[([^\]]+)\]_file/', $file_field_name, $matches)) {
                        $field_key = $matches[1];
                        $index = $matches[2];
                        $sub_key = $matches[3];
                        
                        // Create a temporary file array for this single upload
                        $temp_files = array(
                            'name' => $file_data['name'],
                            'type' => $file_data['type'],
                            'tmp_name' => $file_data['tmp_name'],
                            'error' => $file_data['error'],
                            'size' => $file_data['size']
                        );
                        
                        // Upload the file
                        $upload = wp_handle_upload($temp_files, array('test_form' => false));
                        
                        if (!isset($upload['error'])) {
                            // Create attachment
                            $attachment = array(
                                'post_mime_type' => $upload['type'],
                                'post_title' => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
                                'post_content' => '',
                                'post_status' => 'inherit'
                            );
                            
                            $attachment_id = wp_insert_attachment($attachment, $upload['file']);
                            
                            if (!is_wp_error($attachment_id)) {
                                // Generate attachment metadata
                                $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
                                wp_update_attachment_metadata($attachment_id, $attachment_data);
                                
                                // Store attachment ID in field data
                                if (!isset($field_data[$field_key])) {
                                    $field_data[$field_key] = [];
                                }
                                if (!isset($field_data[$field_key][$index])) {
                                    $field_data[$field_key][$index] = [];
                                }
                                $field_data[$field_key][$index][$sub_key] = $attachment_id;
                            }
                        }
                    }
                }
            }
        }
        
        // Save each field
        foreach ($field_data as $field_key => $field_value) {
            $meta_key = 'rfm_profile_' . $field_key;
            update_user_meta($user_id, $meta_key, $field_value);
        }
        
        wp_send_json_success(['message' => 'Profil opdateret']);
    }
}
