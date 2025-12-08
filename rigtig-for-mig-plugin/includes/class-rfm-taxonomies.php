<?php
/**
 * Custom Taxonomies
 *
 * @package Rigtig_For_Mig
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Taxonomies {
    
    /**
     * Register custom taxonomies
     */
    public static function register() {
        self::register_category_taxonomy();
        self::register_specialization_taxonomy();
    }
    
    /**
     * Register main category taxonomy (de 4 hovedkategorier)
     */
    private static function register_category_taxonomy() {
        $labels = array(
            'name'                       => _x('Kategorier', 'taxonomy general name', 'rigtig-for-mig'),
            'singular_name'              => _x('Kategori', 'taxonomy singular name', 'rigtig-for-mig'),
            'search_items'               => __('Søg Kategorier', 'rigtig-for-mig'),
            'popular_items'              => __('Populære Kategorier', 'rigtig-for-mig'),
            'all_items'                  => __('Alle Kategorier', 'rigtig-for-mig'),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __('Rediger Kategori', 'rigtig-for-mig'),
            'update_item'                => __('Opdater Kategori', 'rigtig-for-mig'),
            'add_new_item'               => __('Tilføj Ny Kategori', 'rigtig-for-mig'),
            'new_item_name'              => __('Nyt Kategori Navn', 'rigtig-for-mig'),
            'separate_items_with_commas' => __('Adskil kategorier med kommaer', 'rigtig-for-mig'),
            'add_or_remove_items'        => __('Tilføj eller fjern kategorier', 'rigtig-for-mig'),
            'choose_from_most_used'      => __('Vælg fra de mest brugte kategorier', 'rigtig-for-mig'),
            'not_found'                  => __('Ingen kategorier fundet.', 'rigtig-for-mig'),
            'menu_name'                  => __('Kategorier', 'rigtig-for-mig'),
        );
        
        $args = array(
            'hierarchical'          => false,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var'             => true,
            'rewrite'               => array('slug' => 'kategori'),
            'show_in_rest'          => true, // Required for Elementor
        );
        
        register_taxonomy('rfm_category', array('rfm_expert'), $args);
        
        // Insert default categories on activation
        add_action('init', array(__CLASS__, 'insert_default_categories'), 11);
    }
    
    /**
     * Register specialization taxonomy (underkategorier/tags)
     */
    private static function register_specialization_taxonomy() {
        $labels = array(
            'name'                       => _x('Specialiseringer', 'taxonomy general name', 'rigtig-for-mig'),
            'singular_name'              => _x('Specialisering', 'taxonomy singular name', 'rigtig-for-mig'),
            'search_items'               => __('Søg Specialiseringer', 'rigtig-for-mig'),
            'popular_items'              => __('Populære Specialiseringer', 'rigtig-for-mig'),
            'all_items'                  => __('Alle Specialiseringer', 'rigtig-for-mig'),
            'parent_item'                => __('Forældre Specialisering', 'rigtig-for-mig'),
            'parent_item_colon'          => __('Forældre Specialisering:', 'rigtig-for-mig'),
            'edit_item'                  => __('Rediger Specialisering', 'rigtig-for-mig'),
            'update_item'                => __('Opdater Specialisering', 'rigtig-for-mig'),
            'add_new_item'               => __('Tilføj Ny Specialisering', 'rigtig-for-mig'),
            'new_item_name'              => __('Nyt Specialisering Navn', 'rigtig-for-mig'),
            'separate_items_with_commas' => __('Adskil specialiseringer med kommaer', 'rigtig-for-mig'),
            'add_or_remove_items'        => __('Tilføj eller fjern specialiseringer', 'rigtig-for-mig'),
            'choose_from_most_used'      => __('Vælg fra de mest brugte specialiseringer', 'rigtig-for-mig'),
            'not_found'                  => __('Ingen specialiseringer fundet.', 'rigtig-for-mig'),
            'menu_name'                  => __('Specialiseringer', 'rigtig-for-mig'),
        );
        
        $args = array(
            'hierarchical'          => true, // Allows subcategories
            'labels'                => $labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var'             => true,
            'rewrite'               => array('slug' => 'specialisering'),
            'show_in_rest'          => true, // Required for Elementor
        );
        
        register_taxonomy('rfm_specialization', array('rfm_expert'), $args);
    }
    
    /**
     * Insert default categories
     */
    public static function insert_default_categories() {
        // Check if already inserted
        if (get_option('rfm_default_categories_inserted')) {
            return;
        }
        
        $categories = array(
            array(
                'name' => 'Hjerne & Psyke',
                'slug' => 'hjerne-psyke',
                'description' => 'Psykologer, psykoterapeuter, coaching og mentalt velvære',
                'color' => '#00CED1', // Cyan/blue
                'icon' => 'brain'
            ),
            array(
                'name' => 'Krop & Bevægelse',
                'slug' => 'krop-bevaegelse',
                'description' => 'Fysioterapeuter, personlige trænere, yoga instruktører og kropslig sundhed',
                'color' => '#90EE90', // Light green
                'icon' => 'running'
            ),
            array(
                'name' => 'Mad & Sundhed',
                'slug' => 'mad-sundhed',
                'description' => 'Diætister, ernæringsrådgivere og holistisk sundhed',
                'color' => '#FFA500', // Orange
                'icon' => 'apple'
            ),
            array(
                'name' => 'Sjæl & Mening',
                'slug' => 'sjael-mening',
                'description' => 'Spirituelle vejledere, mindfulness instruktører og livscoaching',
                'color' => '#BA55D3', // Purple
                'icon' => 'lotus'
            )
        );
        
        foreach ($categories as $category) {
            // Check if term already exists
            if (!term_exists($category['slug'], 'rfm_category')) {
                $term = wp_insert_term(
                    $category['name'],
                    'rfm_category',
                    array(
                        'slug' => $category['slug'],
                        'description' => $category['description']
                    )
                );
                
                if (!is_wp_error($term)) {
                    // Save color and icon as term meta
                    add_term_meta($term['term_id'], 'color', $category['color']);
                    add_term_meta($term['term_id'], 'icon', $category['icon']);
                }
            }
        }
        
        // Mark as inserted
        update_option('rfm_default_categories_inserted', true);
        
        // Insert some default specializations
        self::insert_default_specializations();
    }
    
    /**
     * Insert default specializations
     */
    private static function insert_default_specializations() {
        $specializations = array(
            // Hjerne & Psyke
            'Angst behandling',
            'Depression',
            'Stresshåndtering',
            'Traumer',
            'Parterapi',
            'Coaching',
            'Mindfulness',
            
            // Krop & Bevægelse
            'Fysioterapi',
            'Yoga',
            'Pilates',
            'Personlig træning',
            'Kiropraktik',
            'Massage',
            'Kropsterapi',
            
            // Mad & Sundhed
            'Ernæringsrådgivning',
            'Vægtreduktion',
            'Allergi rådgivning',
            'Vegansk kostplanlægning',
            'Sporternæring',
            'Detox',
            
            // Sjæl & Mening
            'Meditation',
            'Spirituel vejledning',
            'Livscoaching',
            'Healing',
            'Tarot',
            'Astrologi',
            'Mindfulness'
        );
        
        foreach ($specializations as $spec) {
            if (!term_exists($spec, 'rfm_specialization')) {
                wp_insert_term($spec, 'rfm_specialization');
            }
        }
    }
    
    /**
     * Get category color
     */
    public static function get_category_color($term_id) {
        $color = get_term_meta($term_id, 'color', true);
        return $color ? $color : '#cccccc';
    }
    
    /**
     * Get category icon
     */
    public static function get_category_icon($term_id) {
        $icon = get_term_meta($term_id, 'icon', true);
        return $icon ? $icon : 'default';
    }
}
