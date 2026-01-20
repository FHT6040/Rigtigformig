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
        $mappings = array(
            // Hjerne & Psyke
            'hjerne & psyke' => array(
                'angst', 'depression', 'stress', 'traumer', 'parterapi', 'coaching',
                'erhvervscoaching', 'livscoaching', 'business coaching', 'career coaching',
                'psykolog', 'psykoterapi', 'terapi', 'mental', 'adhd', 'add', 'ocd',
                'krisehåndtering', 'sorgbehandling', 'følelsesregulering',
                'angstbehandling', 'panikangst', 'social angst', 'GAD',
                'mindfulness', 'meditation', 'kognitiv', 'ACT', 'KBT',
                'familierådgivning', 'parforhold', 'skilsmisse', 'relationsrådgivning',
                'selvværd', 'selvtillid', 'personlig udvikling', 'vækst'
            ),

            // Krop & Bevægelse
            'krop & bevægelse' => array(
                'fysioterapi', 'yoga', 'pilates', 'personlig træning', 'træning',
                'kiropraktik', 'massage', 'kropsterapi', 'bevægelse',
                'fitness', 'styrketræning', 'kondition', 'løb', 'cykling',
                'body & mind', 'kropsholdning', 'rygbehandling',
                'smertebehandling', 'sportsmassage', 'afspænding',
                'akupunktur', 'zoneterapi', 'osteopati', 'manuel terapi',
                'rehabilitering', 'skadeforebyggelse', 'mobility', 'stretching'
            ),

            // Mad & Sundhed
            'mad & sundhed' => array(
                'ernæring', 'vægtreduktion', 'allergi', 'vegan', 'vegetar',
                'sporternæring', 'detox', 'diætist', 'kosttilskud',
                'slankekur', 'kost', 'kostplanlægning', 'madplan',
                'diabetes', 'colitis', 'crohn', 'ibs', 'mave',
                'glutenfri', 'laktosefri', 'fodmap', 'allergitest',
                'vitamin', 'mineral', 'helsekost', 'økologi',
                'vægttab', 'fedtprocent', 'BMI', 'kalorier'
            ),

            // Sjæl & Mening
            'sjæl & mening' => array(
                'spirituel', 'healing', 'tarot', 'astrologi', 'clairvoyance',
                'sjæl', 'mening', 'bevidsthed', 'åndelig', 'transcendental',
                'chakra', 'energi', 'krystal', 'naturmedicin',
                'shamanic', 'shamanisme', 'ritual', 'ceremony',
                'mindfulness', 'meditation', 'mantra', 'åndedræt',
                'hypnose', 'hypnoterapi', 'regression', 'past life',
                'clairvoyant', 'synsk', 'medium', 'åndelig vejledning',
                'reiki', 'prænic healing', 'lysterapi', 'lydhealing'
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
}

RFM_Assign_Categories_Tool::init();
