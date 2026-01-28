<?php
/**
 * One-time admin tool to assign categories to specializations
 *
 * Add to WordPress admin via admin_menu hook
 *
 * @package Rigtig_For_Mig
 * @since 3.9.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Assign_Categories_Tool {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=rfm_expert',
            'Tildel Kategorier til Specialiseringer',
            'Tildel Kategorier',
            'manage_options',
            'rfm-assign-categories',
            array(__CLASS__, 'render_page')
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Du har ikke tilladelse til at se denne side.');
        }

        // Process assignment if form submitted
        if (isset($_POST['rfm_assign_categories_nonce']) && wp_verify_nonce($_POST['rfm_assign_categories_nonce'], 'rfm_assign_categories')) {
            self::process_assignment();
        }

        // Process deletion if delete button clicked
        if (isset($_POST['rfm_delete_category_specs_nonce']) && wp_verify_nonce($_POST['rfm_delete_category_specs_nonce'], 'rfm_delete_category_specs')) {
            self::delete_category_specializations();
        }

        ?>
        <div class="wrap">
            <h1>Tildel Kategorier til Specialiseringer</h1>

            <div class="notice notice-info">
                <p><strong>Om dette værktøj:</strong></p>
                <p>Dette værktøj tildeler automatisk kategorier til dine eksisterende specialiseringer baseret på deres navne.</p>
                <p>Specialiseringer uden match vil vises i ALLE kategorier (backwards compatible).</p>
                <p>Du kan altid manuelt justere kategorierne efterfølgende under <a href="<?php echo admin_url('edit-tags.php?taxonomy=rfm_specialization&post_type=rfm_expert'); ?>">Specialiseringer</a>.</p>
            </div>

            <form method="post" style="margin-top: 20px;">
                <?php wp_nonce_field('rfm_assign_categories', 'rfm_assign_categories_nonce'); ?>
                <p>
                    <button type="submit" class="button button-primary button-large">
                        🔄 Tildel Kategorier til Alle Specialiseringer
                    </button>
                </p>
            </form>

            <hr style="margin: 30px 0;">

            <h2>Slet Kategori-Specialiseringer</h2>
            <div class="notice notice-warning">
                <p><strong>Bemærk:</strong> Følgende specialiseringer er faktisk kategori-navne og bør slettes:</p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li>Hjerne & Psyke</li>
                    <li>Krop & Bevægelse</li>
                    <li>Mad & Sundhed</li>
                    <li>Sjæl & Mening</li>
                </ul>
                <p>Disse er kategorier, ikke specialiseringer. Klik nedenfor for at slette dem.</p>
            </div>

            <form method="post" style="margin-top: 20px;">
                <?php wp_nonce_field('rfm_delete_category_specs', 'rfm_delete_category_specs_nonce'); ?>
                <p>
                    <button type="submit" class="button button-secondary" onclick="return confirm('Er du sikker på at du vil slette disse 4 specialiseringer?');">
                        🗑️ Slet Kategori-Specialiseringer
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    private static function process_assignment() {
        // Get all categories
        $categories = get_terms(array(
            'taxonomy' => 'rfm_category',
            'hide_empty' => false
        ));

        $category_map = array();
        foreach ($categories as $cat) {
            $category_map[strtolower($cat->name)] = $cat->term_id;
        }

        // Mapping: specialization name patterns => category names
        // IMPORTANT: Matches are case-insensitive and use partial matching (stripos)
        // Based on actual specializations in the database
        $mappings = array(
            // Hjerne & Psyke - Mental sundhed, coaching, terapi, psykologi
            'hjerne & psyke' => array(
                // Direkte matches fra listen
                'afhængighed', 'angst', 'børnepsykolog', 'business coaching',
                'coaching', 'depression', 'EMDR', 'erhvervscoach',
                'executive', 'familie', 'karriere', 'kognitiv adfærd',
                'kropsterapi', 'ledelsescoach', 'life coach', 'livscoach',
                'menopause', 'mentor', 'mindfulness', 'NLP',
                'parterapi', 'performance', 'personlig udvikling',
                'samtale', 'selvværd', 'sexolog', 'sorgbehandling',
                'søvnterapi', 'stress', 'startup', 'teamcoach',
                'traumer', 'terapi', 'psykolog', 'coach',
                // Også generiske patterns
                'mental', 'relation', 'vækst', 'udvikling'
            ),

            // Krop & Bevægelse - Fysisk træning, terapi, behandling
            'krop & bevægelse' => array(
                // Direkte matches fra listen
                'akupunktur', 'bækken', 'boxing', 'crossfit',
                'dans', 'fysio', 'gravid træning', 'kiroprak',
                'kropsterapi', 'løbetræning', 'massage', 'mobility',
                'naprapati', 'osteopat', 'personlig træning',
                'pilates', 'rygtræning', 'senior', 'shiatsu',
                'spinning', 'styrke', 'TRX', 'yoga',
                // Også generiske patterns
                'træning', 'træner', 'bevægelse', 'fysisk',
                'kropslig', 'krop', 'motion', 'fitness'
            ),

            // Mad & Sundhed - Ernæring, kost, sundhed, diæt
            'mad & sundhed' => array(
                // Direkte matches fra listen
                'allergi', 'anti-inflammation', 'detox', 'diabetes',
                'ernæring', 'fertilitet', 'fordøjelse', 'glutenfri',
                'hormon', 'ketogen', 'klinisk diæt', 'laktosefri',
                'menopause', 'plantebaseret', 'sport', 'vægt',
                'vegan', 'diæt', 'kost',
                // Også generiske patterns
                'mad', 'føde', 'nutrition', 'sundhed', 'health'
            ),

            // Sjæl & Mening - Spiritualitet, healing, energi, astrologi
            'sjæl & mening' => array(
                // Direkte matches fra listen
                'astrologi', 'chakra', 'clairvoyance', 'energi',
                'englekort', 'healing', 'krystal', 'meditation',
                'mindfulness', 'reiki', 'shamansk', 'spirituel',
                'tarot',
                // Også generiske patterns
                'åndelig', 'sjæl', 'soul', 'mening', 'bevidsthed',
                'transcendent', 'mystisk', 'esoterisk'
            )
        );

        // Get all specializations
        $specializations = get_terms(array(
            'taxonomy' => 'rfm_specialization',
            'hide_empty' => false
        ));

        $results = array(
            'updated' => array(),
            'skipped' => array()
        );

        foreach ($specializations as $spec) {
            $spec_name_lower = strtolower($spec->name);
            $assigned_categories = array();

            // Check which categories this specialization should belong to
            foreach ($mappings as $category_name => $patterns) {
                if (!isset($category_map[$category_name])) {
                    continue;
                }

                // Check if specialization name matches any pattern
                foreach ($patterns as $pattern) {
                    if (stripos($spec_name_lower, strtolower($pattern)) !== false) {
                        $assigned_categories[] = $category_map[$category_name];
                        break; // Don't add same category multiple times
                    }
                }
            }

            // Remove duplicates
            $assigned_categories = array_unique($assigned_categories);

            if (!empty($assigned_categories)) {
                update_term_meta($spec->term_id, 'rfm_categories', $assigned_categories);

                $category_names = array();
                foreach ($assigned_categories as $cat_id) {
                    foreach ($category_map as $name => $id) {
                        if ($id === $cat_id) {
                            $category_names[] = ucfirst($name);
                            break;
                        }
                    }
                }

                $results['updated'][] = array(
                    'name' => $spec->name,
                    'categories' => $category_names
                );
            } else {
                $results['skipped'][] = $spec->name;
            }
        }

        // Display results
        ?>
        <div class="notice notice-success is-dismissible">
            <h2>✅ Tildeling Fuldført!</h2>

            <h3>Opdateret: <?php echo count($results['updated']); ?> specialiseringer</h3>
            <?php if (!empty($results['updated'])): ?>
                <ul style="list-style: disc; margin-left: 20px;">
                    <?php foreach ($results['updated'] as $item): ?>
                        <li>
                            <strong><?php echo esc_html($item['name']); ?></strong>
                            → <?php echo esc_html(implode(', ', $item['categories'])); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($results['skipped'])): ?>
                <h3>Sprunget over: <?php echo count($results['skipped']); ?> specialiseringer</h3>
                <p><em>Disse vises i ALLE kategorier (ingen match fundet):</em></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <?php foreach ($results['skipped'] as $name): ?>
                        <li><?php echo esc_html($name); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p style="margin-top: 20px;">
                <a href="<?php echo admin_url('edit-tags.php?taxonomy=rfm_specialization&post_type=rfm_expert'); ?>" class="button button-primary">
                    Gå til Specialiseringer for at verificere
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Delete category-named specializations
     */
    private static function delete_category_specializations() {
        $category_names = array(
            'Hjerne & Psyke',
            'Krop & Bevægelse',
            'Mad & Sundhed',
            'Sjæl & Mening'
        );

        $deleted = array();
        $not_found = array();

        foreach ($category_names as $name) {
            $term = get_term_by('name', $name, 'rfm_specialization');

            if ($term) {
                $result = wp_delete_term($term->term_id, 'rfm_specialization');

                if (!is_wp_error($result) && $result) {
                    $deleted[] = $name;
                } else {
                    $not_found[] = $name . ' (kunne ikke slettes)';
                }
            } else {
                $not_found[] = $name . ' (ikke fundet)';
            }
        }

        // Display results
        ?>
        <div class="notice notice-success is-dismissible">
            <h2>🗑️ Sletning Fuldført!</h2>

            <?php if (!empty($deleted)): ?>
                <h3>✅ Slettet: <?php echo count($deleted); ?> specialiseringer</h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <?php foreach ($deleted as $name): ?>
                        <li><?php echo esc_html($name); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($not_found)): ?>
                <h3>⚠️ Ikke fundet/slettet: <?php echo count($not_found); ?></h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <?php foreach ($not_found as $name): ?>
                        <li><?php echo esc_html($name); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p style="margin-top: 20px;">
                <a href="<?php echo admin_url('edit-tags.php?taxonomy=rfm_specialization&post_type=rfm_expert'); ?>" class="button button-primary">
                    Gå til Specialiseringer for at verificere
                </a>
            </p>
        </div>
        <?php
    }
}

RFM_Assign_Categories_Tool::init();
