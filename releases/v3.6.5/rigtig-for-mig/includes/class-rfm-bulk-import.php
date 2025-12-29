<?php
/**
 * Bulk Import for Specializations
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Bulk_Import {
    
    /**
     * Initialize the bulk import functionality
     */
    public static function init() {
        // Add admin menu
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'), 25);
        
        // Handle file upload
        add_action('admin_post_rfm_bulk_import', array(__CLASS__, 'handle_import'));
        
        // AJAX handler for preview
        add_action('wp_ajax_rfm_preview_import', array(__CLASS__, 'ajax_preview_import'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    }
    
    /**
     * Add admin menu page
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'rfm-dashboard',
            __('Bulk Import Specialiseringer', 'rigtig-for-mig'),
            __('Bulk Import', 'rigtig-for-mig'),
            'manage_options',
            'rfm-bulk-import',
            array(__CLASS__, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue scripts and styles
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'rigtig-for-mig_page_rfm-bulk-import') {
            return;
        }
        
        wp_enqueue_style(
            'rfm-bulk-import',
            plugin_dir_url(__DIR__) . 'assets/css/bulk-import.css',
            array(),
            '2.8.0'
        );
        
        wp_enqueue_script(
            'rfm-bulk-import',
            plugin_dir_url(__DIR__) . 'assets/js/bulk-import.js',
            array('jquery'),
            '2.8.0',
            true
        );
        
        wp_localize_script('rfm-bulk-import', 'rfmBulkImport', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rfm_bulk_import_nonce'),
        ));
    }
    
    /**
     * Render admin page
     */
    public static function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Du har ikke adgang til denne side.', 'rigtig-for-mig'));
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Bulk Import af Specialiseringer', 'rigtig-for-mig'); ?></h1>
            
            <?php
            // Show current taxonomy status
            $categories = get_terms(array('taxonomy' => 'rfm_category', 'hide_empty' => false));
            $specializations = get_terms(array('taxonomy' => 'rfm_specialization', 'hide_empty' => false));
            
            if (!empty($categories)) {
                echo '<div class="notice notice-info" style="margin-top: 20px;">';
                echo '<h3 style="margin-top: 10px;">ℹ️ Nuværende Status</h3>';
                echo '<p><strong>Kategorier (rfm_category):</strong> ' . count($categories) . ' kategorier fundet</p>';
                echo '<p><strong>Specialiseringer (rfm_specialization):</strong> ' . count($specializations) . ' specialiseringer fundet</p>';
                echo '<p style="margin-bottom: 10px;"><strong>⚠️ Vigtigt:</strong> Denne import tilføjer til <strong>Specialiseringer</strong>, ikke Kategorier. ';
                echo 'Du kan importere hele hierarkiet (hovedkategorier + underkategorier) til Specialiseringer!</p>';
                echo '</div>';
            }
            ?>
            
            <div class="rfm-bulk-import-container">
                <div class="rfm-import-instructions">
                    <h2>📋 Sådan bruger du bulk import:</h2>
                    
                    <div style="background: #e7f7ff; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 20px;">
                        <h3 style="margin-top: 0;">💡 Vigtigt om Hierarkisk Struktur</h3>
                        <p><strong>Denne import opretter specialiseringer med hierarki!</strong></p>
                        <p>Du kan importere både:</p>
                        <ul style="margin-bottom: 10px;">
                            <li><strong>Hovedkategorier</strong> (uden forælder) - fx "Hjerne & Psyke", "Krop & Bevægelse"</li>
                            <li><strong>Underkategorier</strong> (med forælder) - fx "Life coaching" under "Hjerne & Psyke"</li>
                        </ul>
                        <p style="margin-bottom: 0;"><strong>Tips:</strong> Importer alt i én fil! Systemet opretter automatisk hovedkategorierne først, så underkategorierne kan få korrekte forældrer.</p>
                    </div>
                    
                    <ol>
                        <li><strong>Forbered din fil:</strong> Opret en CSV eller Excel fil med specialiseringerne</li>
                        <li><strong>Format:</strong> En specialisering pr. linje. Du kan have kolonner for:
                            <ul>
                                <li><code>Navn</code> (påkrævet) - Navn på specialiseringen</li>
                                <li><code>Korttitel</code> (valgfri) - URL-venlig slug</li>
                                <li><code>Beskrivelse</code> (valgfri) - Beskrivelse af specialiseringen</li>
                                <li><code>Forælder</code> (valgfri) - Navn på forældre-kategori (for underkategorier)</li>
                            </ul>
                        </li>
                        <li><strong>Upload filen</strong> nedenfor</li>
                        <li><strong>Preview</strong> hvad der vil blive importeret</li>
                        <li><strong>Importer</strong> når du er klar!</li>
                    </ol>
                    
                    <div class="rfm-example-box">
                        <h3>💡 Eksempel på CSV format med Hierarki:</h3>
                        <pre>Navn,Korttitel,Beskrivelse,Forælder
Hjerne & Psyke,hjerne-psyke,Psykologer og coaching,
Krop & Bevægelse,krop-bevaegelse,Fysioterapeuter og træning,
Life coaching,life-coaching,Personlig udvikling,Hjerne & Psyke
Yoga,yoga,Yoga instruktion,Krop & Bevægelse
Depression,depression,Behandling af depression,Hjerne & Psyke</pre>
                        <p><em>Eller bare en simpel liste uden forældrer:</em></p>
                        <pre>Navn
Angst behandling
Depression
Stresshåndtering
Yoga</pre>
                        <p style="margin-top: 15px;">
                            <a href="<?php echo plugin_dir_url(__DIR__) . 'templates/specialisering-import-template.csv'; ?>" 
                               class="button button-secondary" 
                               download>
                                📥 Download CSV Skabelon
                            </a>
                        </p>
                    </div>
                </div>
                
                <div class="rfm-upload-section">
                    <form id="rfm-bulk-import-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field('rfm_bulk_import_action', 'rfm_bulk_import_nonce'); ?>
                        <input type="hidden" name="action" value="rfm_bulk_import" />
                        
                        <div class="rfm-form-group">
                            <label for="import_file">
                                <strong>📁 Vælg fil:</strong>
                            </label>
                            <input 
                                type="file" 
                                name="import_file" 
                                id="import_file" 
                                accept=".csv,.xlsx,.xls,.txt"
                                required
                            />
                            <p class="description">
                                Understøttede formater: CSV, Excel (.xlsx, .xls), TXT
                            </p>
                        </div>
                        
                        <div class="rfm-form-group">
                            <label>
                                <input type="checkbox" name="skip_duplicates" value="1" checked />
                                <strong>Spring duplikater over</strong>
                            </label>
                            <p class="description">
                                Importer ikke specialiseringer der allerede eksisterer i systemet.
                            </p>
                        </div>
                        
                        <div class="rfm-form-group">
                            <label>
                                <input type="checkbox" name="create_parent" value="1" id="create_parent_checkbox" />
                                <strong>Opret forældre automatisk</strong>
                            </label>
                            <p class="description" style="color: #d63638;">
                                ⚠️ <strong>VIGTIGT:</strong> Hvis dine specialiseringer har forældrekategorier (f.eks. "Hjerne & Psyke"), 
                                skal denne checkbox være markeret! Ellers vil specialiseringer med ikke-eksisterende forældrer 
                                blive importeret uden forældre eller helt springe over.
                            </p>
                            <p class="description" style="margin-top: 5px;">
                                💡 <strong>Tip:</strong> Importer først hovedkategorier (uden forældrer), og derefter underkategorier.
                                Eller markér denne checkbox for at oprette alt i én import.
                            </p>
                        </div>
                        
                        <div class="rfm-button-group">
                            <button type="button" id="preview-import" class="button button-secondary">
                                👁️ Preview Import
                            </button>
                            <button type="submit" id="execute-import" class="button button-primary" disabled>
                                ⚡ Importer Nu
                            </button>
                        </div>
                    </form>
                </div>
                
                <div id="rfm-preview-section" style="display: none;">
                    <h2>🔍 Preview af Import</h2>
                    <div id="rfm-preview-content"></div>
                    <div id="rfm-preview-stats"></div>
                </div>
                
                <div id="rfm-import-results" style="display: none;">
                    <h2>✅ Import Resultater</h2>
                    <div id="rfm-results-content"></div>
                </div>
                
                <div id="rfm-import-progress" style="display: none;">
                    <h2>⏳ Importerer...</h2>
                    <div class="rfm-progress-bar">
                        <div class="rfm-progress-fill"></div>
                    </div>
                    <p class="rfm-progress-text">0 af 0 importeret</p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for preview
     */
    public static function ajax_preview_import() {
        // Check nonce
        check_ajax_referer('rfm_bulk_import_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Ingen adgang'));
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['file'])) {
            wp_send_json_error(array('message' => 'Ingen fil uploaded'));
        }
        
        $file = $_FILES['file'];
        
        // Parse the file
        $data = self::parse_file($file['tmp_name'], $file['name']);
        
        if (is_wp_error($data)) {
            wp_send_json_error(array('message' => $data->get_error_message()));
        }
        
        // Get existing terms
        $existing_terms = get_terms(array(
            'taxonomy' => 'rfm_specialization',
            'hide_empty' => false,
            'fields' => 'names',
        ));
        
        // Check which terms already exist
        $to_import = array();
        $duplicates = array();
        $missing_parents = array();
        
        foreach ($data as $item) {
            $name = $item['name'];
            
            if (in_array($name, $existing_terms)) {
                $duplicates[] = $item;
            } else {
                $to_import[] = $item;
                
                // Check if parent exists
                if (!empty($item['parent'])) {
                    $parent_exists = in_array($item['parent'], $existing_terms);
                    if (!$parent_exists) {
                        // Check if parent is in the import list
                        $parent_in_import = false;
                        foreach ($data as $check_item) {
                            if ($check_item['name'] === $item['parent']) {
                                $parent_in_import = true;
                                break;
                            }
                        }
                        
                        if (!$parent_in_import && !in_array($item['parent'], $missing_parents)) {
                            $missing_parents[] = $item['parent'];
                        }
                    }
                }
            }
        }
        
        // Prepare preview HTML
        $preview_html = '<div class="rfm-preview-tables">';
        
        // Warning about missing parents
        if (!empty($missing_parents)) {
            $preview_html .= '<div class="rfm-preview-warning" style="background: #fff3cd; border-left: 4px solid #856404; padding: 15px; margin-bottom: 20px;">';
            $preview_html .= '<h3 style="margin-top: 0; color: #856404;">⚠️ Manglende Forældrekategorier</h3>';
            $preview_html .= '<p><strong>Disse forældrekategorier eksisterer ikke:</strong></p>';
            $preview_html .= '<ul style="list-style: disc; margin-left: 20px;">';
            foreach ($missing_parents as $parent) {
                $preview_html .= '<li><strong>' . esc_html($parent) . '</strong></li>';
            }
            $preview_html .= '</ul>';
            $preview_html .= '<p style="margin-bottom: 0;"><strong>Løsning:</strong> Markér checkboxen <strong>"Opret forældre automatisk"</strong> nedenfor før du importerer.</p>';
            $preview_html .= '</div>';
        }
        
        // New terms to import
        if (!empty($to_import)) {
            $preview_html .= '<div class="rfm-preview-table-wrapper">';
            $preview_html .= '<h3>✅ Vil blive importeret (' . count($to_import) . '):</h3>';
            $preview_html .= '<table class="wp-list-table widefat fixed striped">';
            $preview_html .= '<thead><tr>';
            $preview_html .= '<th>Navn</th>';
            $preview_html .= '<th>Korttitel</th>';
            $preview_html .= '<th>Beskrivelse</th>';
            $preview_html .= '<th>Forælder</th>';
            $preview_html .= '</tr></thead><tbody>';
            
            foreach ($to_import as $item) {
                $preview_html .= '<tr>';
                $preview_html .= '<td><strong>' . esc_html($item['name']) . '</strong></td>';
                $preview_html .= '<td>' . esc_html($item['slug'] ?: 'Auto-genereret') . '</td>';
                $preview_html .= '<td>' . esc_html($item['description'] ?: '-') . '</td>';
                $preview_html .= '<td>' . esc_html($item['parent'] ?: '-') . '</td>';
                $preview_html .= '</tr>';
            }
            
            $preview_html .= '</tbody></table>';
            $preview_html .= '</div>';
        }
        
        // Duplicates
        if (!empty($duplicates)) {
            $preview_html .= '<div class="rfm-preview-table-wrapper">';
            $preview_html .= '<h3>⚠️ Findes allerede (' . count($duplicates) . '):</h3>';
            $preview_html .= '<table class="wp-list-table widefat fixed striped">';
            $preview_html .= '<thead><tr><th>Navn</th></tr></thead><tbody>';
            
            foreach ($duplicates as $item) {
                $preview_html .= '<tr><td>' . esc_html($item['name']) . '</td></tr>';
            }
            
            $preview_html .= '</tbody></table>';
            $preview_html .= '</div>';
        }
        
        $preview_html .= '</div>';
        
        // Stats
        $stats_html = '<div class="rfm-import-stats">';
        $stats_html .= '<div class="stat-box stat-new">';
        $stats_html .= '<div class="stat-number">' . count($to_import) . '</div>';
        $stats_html .= '<div class="stat-label">Nye</div>';
        $stats_html .= '</div>';
        $stats_html .= '<div class="stat-box stat-duplicate">';
        $stats_html .= '<div class="stat-number">' . count($duplicates) . '</div>';
        $stats_html .= '<div class="stat-label">Duplikater</div>';
        $stats_html .= '</div>';
        $stats_html .= '<div class="stat-box stat-total">';
        $stats_html .= '<div class="stat-number">' . count($data) . '</div>';
        $stats_html .= '<div class="stat-label">Total</div>';
        $stats_html .= '</div>';
        $stats_html .= '</div>';
        
        wp_send_json_success(array(
            'preview_html' => $preview_html,
            'stats_html' => $stats_html,
            'to_import' => $to_import,
            'duplicates' => $duplicates,
            'total' => count($data),
            'missing_parents' => $missing_parents,
        ));
    }
    
    /**
     * Handle the actual import
     */
    public static function handle_import() {
        error_log('RFM: handle_import() called');
        error_log('RFM: POST data: ' . print_r($_POST, true));
        error_log('RFM: FILES data: ' . print_r($_FILES, true));
        
        // Check nonce
        if (!isset($_POST['rfm_bulk_import_nonce']) || 
            !wp_verify_nonce($_POST['rfm_bulk_import_nonce'], 'rfm_bulk_import_action')) {
            error_log('RFM: Security check failed');
            wp_die('Sikkerhedsfejl');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            error_log('RFM: Permission check failed');
            wp_die('Ingen adgang');
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['import_file'])) {
            error_log('RFM: No file uploaded');
            wp_die('Ingen fil uploaded');
        }
        
        $file = $_FILES['import_file'];
        $skip_duplicates = isset($_POST['skip_duplicates']);
        $create_parent = isset($_POST['create_parent']);
        
        error_log("RFM: skip_duplicates=" . ($skip_duplicates ? 'true' : 'false'));
        error_log("RFM: create_parent=" . ($create_parent ? 'true' : 'false'));
        error_log("RFM: File: " . $file['name'] . " (" . $file['size'] . " bytes)");
        
        // Parse the file
        $data = self::parse_file($file['tmp_name'], $file['name']);
        
        if (is_wp_error($data)) {
            error_log('RFM: Parse error: ' . $data->get_error_message());
            wp_die($data->get_error_message());
        }
        
        error_log('RFM: Parsed ' . count($data) . ' items from file');
        
        // Import the terms
        $results = self::import_terms($data, $skip_duplicates, $create_parent);
        
        // Prepare redirect URL with results
        $args = array(
            'page' => 'rfm-bulk-import',
            'imported' => $results['imported'],
            'skipped' => $results['skipped'],
            'errors' => $results['errors'],
        );
        
        // Add warnings if there are parent errors
        if (!empty($results['parent_errors'])) {
            $args['parent_warnings'] = base64_encode(json_encode($results['parent_errors']));
        }
        
        // Add error messages if there are any
        if (!empty($results['error_messages'])) {
            $args['error_details'] = base64_encode(json_encode($results['error_messages']));
        }
        
        $redirect_url = add_query_arg($args, admin_url('admin.php'));
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Parse uploaded file
     */
    private static function parse_file($file_path, $file_name) {
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $data = array();
        
        // Handle different file types
        switch ($extension) {
            case 'csv':
                $data = self::parse_csv($file_path);
                break;
                
            case 'xlsx':
            case 'xls':
                $data = self::parse_excel($file_path);
                break;
                
            case 'txt':
                $data = self::parse_txt($file_path);
                break;
                
            default:
                return new WP_Error('invalid_file', 'Ugyldig filtype');
        }
        
        return $data;
    }
    
    /**
     * Parse CSV file
     */
    private static function parse_csv($file_path) {
        $data = array();
        
        if (($handle = fopen($file_path, 'r')) !== false) {
            $headers = fgetcsv($handle, 1000, ',');
            
            // Normalize headers
            $headers = array_map(function($h) {
                $h = strtolower(trim($h));
                // Map Danish headers
                if ($h === 'navn') return 'name';
                if ($h === 'korttitel') return 'slug';
                if ($h === 'beskrivelse') return 'description';
                if ($h === 'forælder' || $h === 'parent') return 'parent';
                return $h;
            }, $headers);
            
            // Check if first row is actually a header
            $has_header = in_array('name', $headers) || in_array('navn', $headers);
            
            if (!$has_header) {
                // First row is data, not header
                $data[] = array(
                    'name' => trim($headers[0]),
                    'slug' => isset($headers[1]) ? trim($headers[1]) : '',
                    'description' => isset($headers[2]) ? trim($headers[2]) : '',
                    'parent' => isset($headers[3]) ? trim($headers[3]) : '',
                );
            }
            
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (empty(array_filter($row))) continue; // Skip empty rows
                
                $item = array();
                
                if ($has_header) {
                    foreach ($headers as $i => $header) {
                        $item[$header] = isset($row[$i]) ? trim($row[$i]) : '';
                    }
                } else {
                    $item['name'] = trim($row[0]);
                    $item['slug'] = isset($row[1]) ? trim($row[1]) : '';
                    $item['description'] = isset($row[2]) ? trim($row[2]) : '';
                    $item['parent'] = isset($row[3]) ? trim($row[3]) : '';
                }
                
                if (!empty($item['name'])) {
                    $data[] = $item;
                }
            }
            
            fclose($handle);
        }
        
        return $data;
    }
    
    /**
     * Parse Excel file
     */
    private static function parse_excel($file_path) {
        // Check if PhpSpreadsheet is available
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            // Fallback: try to use SimpleXLSX if available
            return self::parse_excel_simple($file_path);
        }
        
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            $data = array();
            $headers = array_shift($rows); // Get first row as headers
            
            // Normalize headers
            $headers = array_map(function($h) {
                $h = strtolower(trim($h));
                if ($h === 'navn') return 'name';
                if ($h === 'korttitel') return 'slug';
                if ($h === 'beskrivelse') return 'description';
                if ($h === 'forælder' || $h === 'parent') return 'parent';
                return $h;
            }, $headers);
            
            foreach ($rows as $row) {
                if (empty(array_filter($row))) continue;
                
                $item = array();
                foreach ($headers as $i => $header) {
                    $item[$header] = isset($row[$i]) ? trim($row[$i]) : '';
                }
                
                if (!empty($item['name'])) {
                    $data[] = $item;
                }
            }
            
            return $data;
        } catch (Exception $e) {
            return new WP_Error('excel_error', 'Kunne ikke læse Excel fil: ' . $e->getMessage());
        }
    }
    
    /**
     * Simple Excel parser fallback
     */
    private static function parse_excel_simple($file_path) {
        // For now, just return error - user should convert to CSV
        return new WP_Error('excel_not_supported', 
            'Excel understøttelse kræver PhpSpreadsheet biblioteket. Konverter venligst til CSV format.');
    }
    
    /**
     * Parse TXT file (simple list)
     */
    private static function parse_txt($file_path) {
        $data = array();
        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $data[] = array(
                    'name' => $line,
                    'slug' => '',
                    'description' => '',
                    'parent' => '',
                );
            }
        }
        
        return $data;
    }
    
    /**
     * Import terms into WordPress
     */
    private static function import_terms($data, $skip_duplicates = true, $create_parent = false) {
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $error_messages = array();
        $parent_errors = array();
        
        // DEBUG: Log import start
        error_log('RFM Import Start: ' . count($data) . ' items to process');
        error_log('RFM Import Settings: skip_duplicates=' . ($skip_duplicates ? 'true' : 'false') . ', create_parent=' . ($create_parent ? 'true' : 'false'));
        
        foreach ($data as $index => $item) {
            $name = $item['name'];
            $slug = !empty($item['slug']) ? $item['slug'] : '';
            $description = !empty($item['description']) ? $item['description'] : '';
            $parent_name = !empty($item['parent']) ? $item['parent'] : '';
            
            error_log("RFM Import Item #{$index}: '{$name}' (parent: '{$parent_name}')");
            
            // Check if term exists
            $existing = term_exists($name, 'rfm_specialization');
            if ($existing) {
                if ($skip_duplicates) {
                    $skipped++;
                    error_log("  -> SKIPPED (already exists)");
                    continue;
                }
            }
            
            // Handle parent
            $parent_id = 0;
            if (!empty($parent_name)) {
                $parent_term = get_term_by('name', $parent_name, 'rfm_specialization');
                
                if ($parent_term) {
                    $parent_id = $parent_term->term_id;
                    error_log("  -> Found parent '{$parent_name}' (ID: {$parent_id})");
                } elseif ($create_parent) {
                    // Create parent term
                    error_log("  -> Creating parent '{$parent_name}'...");
                    $parent_result = wp_insert_term($parent_name, 'rfm_specialization');
                    if (!is_wp_error($parent_result)) {
                        $parent_id = $parent_result['term_id'];
                        error_log("  -> Parent created successfully (ID: {$parent_id})");
                    } else {
                        $error_msg = $parent_result->get_error_message();
                        $parent_errors[] = "Kunne ikke oprette forælder '{$parent_name}': " . $error_msg;
                        error_log("  -> ERROR creating parent: " . $error_msg);
                    }
                } else {
                    // Parent doesn't exist and create_parent is false
                    $parent_errors[] = "Forælder '{$parent_name}' eksisterer ikke for '{$name}' (aktivér 'Opret forældre automatisk')";
                    error_log("  -> WARNING: Parent '{$parent_name}' not found and create_parent=false");
                    // Continue anyway but without parent
                }
            }
            
            // Insert term
            $args = array(
                'description' => $description,
            );
            
            if (!empty($slug)) {
                $args['slug'] = $slug;
            }
            
            if ($parent_id > 0) {
                $args['parent'] = $parent_id;
            }
            
            error_log("  -> Inserting term with args: " . json_encode($args));
            $result = wp_insert_term($name, 'rfm_specialization', $args);
            
            if (is_wp_error($result)) {
                $errors++;
                $error_msg = $result->get_error_message();
                $error_messages[] = "Fejl ved import af '{$name}': " . $error_msg;
                error_log("  -> ERROR: " . $error_msg);
            } else {
                $imported++;
                error_log("  -> SUCCESS (ID: " . $result['term_id'] . ")");
            }
        }
        
        error_log("RFM Import Complete: imported={$imported}, skipped={$skipped}, errors={$errors}");
        
        return array(
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'error_messages' => $error_messages,
            'parent_errors' => array_unique($parent_errors),
        );
    }
}
