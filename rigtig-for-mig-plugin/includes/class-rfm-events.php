<?php
/**
 * Events System (Kurser & Events)
 *
 * Handles event post type management, admin meta boxes, admin columns,
 * single template loading, shortcodes, and social sharing.
 *
 * @package Rigtig_For_Mig
 * @since 3.14.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RFM_Events {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Admin meta boxes
        add_action('add_meta_boxes', array($this, 'add_event_meta_boxes'));
        add_action('save_post_rfm_event', array($this, 'save_event_meta'), 10, 2);

        // Admin columns
        add_filter('manage_rfm_event_posts_columns', array($this, 'add_event_columns'));
        add_action('manage_rfm_event_posts_custom_column', array($this, 'render_event_columns'), 10, 2);
        add_filter('manage_edit-rfm_event_sortable_columns', array($this, 'sortable_event_columns'));
        add_action('pre_get_posts', array($this, 'sort_events_by_date'));

        // Single template
        add_filter('single_template', array($this, 'event_template'));

        // Shortcodes
        add_shortcode('rfm_events_page', array($this, 'events_page_shortcode'));
    }

    // =========================================================================
    // ADMIN META BOXES
    // =========================================================================

    /**
     * Add meta boxes to the event editor.
     */
    public function add_event_meta_boxes() {
        add_meta_box(
            'rfm_event_details',
            __('Event Detaljer', 'rigtig-for-mig'),
            array($this, 'render_event_details_meta_box'),
            'rfm_event',
            'normal',
            'high'
        );

        add_meta_box(
            'rfm_event_expert',
            __('Ekspert / Instruktør', 'rigtig-for-mig'),
            array($this, 'render_event_expert_meta_box'),
            'rfm_event',
            'side',
            'default'
        );
    }

    /**
     * Render event details meta box.
     */
    public function render_event_details_meta_box($post) {
        wp_nonce_field('rfm_save_event_meta', 'rfm_event_meta_nonce');

        $date       = get_post_meta($post->ID, '_rfm_event_date', true);
        $time_start = get_post_meta($post->ID, '_rfm_event_time_start', true);
        $time_end   = get_post_meta($post->ID, '_rfm_event_time_end', true);
        $location   = get_post_meta($post->ID, '_rfm_event_location', true);
        $price      = get_post_meta($post->ID, '_rfm_event_price', true);
        $event_url  = get_post_meta($post->ID, '_rfm_event_url', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="rfm-event-date"><?php _e('Dato', 'rigtig-for-mig'); ?></label></th>
                <td><input type="date" id="rfm-event-date" name="rfm_event_date" value="<?php echo esc_attr($date); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="rfm-event-time-start"><?php _e('Starttid', 'rigtig-for-mig'); ?></label></th>
                <td><input type="time" id="rfm-event-time-start" name="rfm_event_time_start" value="<?php echo esc_attr($time_start); ?>" /></td>
            </tr>
            <tr>
                <th><label for="rfm-event-time-end"><?php _e('Sluttid', 'rigtig-for-mig'); ?></label></th>
                <td><input type="time" id="rfm-event-time-end" name="rfm_event_time_end" value="<?php echo esc_attr($time_end); ?>" /></td>
            </tr>
            <tr>
                <th><label for="rfm-event-location"><?php _e('Lokation', 'rigtig-for-mig'); ?></label></th>
                <td><input type="text" id="rfm-event-location" name="rfm_event_location" value="<?php echo esc_attr($location); ?>" class="regular-text" placeholder="<?php esc_attr_e('F.eks. København, Online, etc.', 'rigtig-for-mig'); ?>" /></td>
            </tr>
            <tr>
                <th><label for="rfm-event-price"><?php _e('Pris', 'rigtig-for-mig'); ?></label></th>
                <td><input type="text" id="rfm-event-price" name="rfm_event_price" value="<?php echo esc_attr($price); ?>" class="regular-text" placeholder="<?php esc_attr_e('F.eks. Gratis, 500 kr, Fra 1.200 kr', 'rigtig-for-mig'); ?>" /></td>
            </tr>
            <tr>
                <th><label for="rfm-event-url"><?php _e('Tilmeldingslink', 'rigtig-for-mig'); ?></label></th>
                <td><input type="url" id="rfm-event-url" name="rfm_event_url" value="<?php echo esc_url($event_url); ?>" class="regular-text" placeholder="https://" /></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render expert selection meta box.
     */
    public function render_event_expert_meta_box($post) {
        $expert_id = get_post_meta($post->ID, '_rfm_event_expert_id', true);

        $experts = get_posts(array(
            'post_type'      => 'rfm_expert',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
        ?>
        <p>
            <select name="rfm_event_expert_id" id="rfm-event-expert-id" style="width:100%;">
                <option value=""><?php _e('– Ingen ekspert –', 'rigtig-for-mig'); ?></option>
                <?php foreach ($experts as $expert): ?>
                <option value="<?php echo esc_attr($expert->ID); ?>" <?php selected($expert_id, $expert->ID); ?>>
                    <?php echo esc_html($expert->post_title); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description"><?php _e('Valgfrit: Knyt en ekspert til dette event.', 'rigtig-for-mig'); ?></p>
        <?php
    }

    /**
     * Save event meta data.
     */
    public function save_event_meta($post_id, $post) {
        if (!isset($_POST['rfm_event_meta_nonce']) || !wp_verify_nonce($_POST['rfm_event_meta_nonce'], 'rfm_save_event_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            'rfm_event_date'       => '_rfm_event_date',
            'rfm_event_time_start' => '_rfm_event_time_start',
            'rfm_event_time_end'   => '_rfm_event_time_end',
            'rfm_event_location'   => '_rfm_event_location',
            'rfm_event_price'      => '_rfm_event_price',
        );

        foreach ($fields as $form_key => $meta_key) {
            if (isset($_POST[$form_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$form_key]));
            }
        }

        // URL field
        if (isset($_POST['rfm_event_url'])) {
            update_post_meta($post_id, '_rfm_event_url', esc_url_raw($_POST['rfm_event_url']));
        }

        // Expert ID
        if (isset($_POST['rfm_event_expert_id'])) {
            $expert_id = intval($_POST['rfm_event_expert_id']);
            if ($expert_id > 0) {
                update_post_meta($post_id, '_rfm_event_expert_id', $expert_id);
            } else {
                delete_post_meta($post_id, '_rfm_event_expert_id');
            }
        }
    }

    // =========================================================================
    // ADMIN COLUMNS
    // =========================================================================

    public function add_event_columns($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['rfm_event_date']     = __('Dato', 'rigtig-for-mig');
                $new['rfm_event_location'] = __('Lokation', 'rigtig-for-mig');
                $new['rfm_event_price']    = __('Pris', 'rigtig-for-mig');
                $new['rfm_event_expert']   = __('Ekspert', 'rigtig-for-mig');
            }
        }
        return $new;
    }

    public function render_event_columns($column, $post_id) {
        switch ($column) {
            case 'rfm_event_date':
                $date = get_post_meta($post_id, '_rfm_event_date', true);
                if ($date) {
                    $timestamp = strtotime($date);
                    echo esc_html(date_i18n('j. F Y', $timestamp));
                    $time_start = get_post_meta($post_id, '_rfm_event_time_start', true);
                    if ($time_start) {
                        echo '<br><small>' . esc_html($time_start);
                        $time_end = get_post_meta($post_id, '_rfm_event_time_end', true);
                        if ($time_end) {
                            echo ' – ' . esc_html($time_end);
                        }
                        echo '</small>';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'rfm_event_location':
                echo esc_html(get_post_meta($post_id, '_rfm_event_location', true) ?: '—');
                break;

            case 'rfm_event_price':
                echo esc_html(get_post_meta($post_id, '_rfm_event_price', true) ?: '—');
                break;

            case 'rfm_event_expert':
                $expert_id = get_post_meta($post_id, '_rfm_event_expert_id', true);
                if ($expert_id && get_post($expert_id)) {
                    echo '<a href="' . esc_url(get_edit_post_link($expert_id)) . '">' . esc_html(get_the_title($expert_id)) . '</a>';
                } else {
                    echo '—';
                }
                break;
        }
    }

    public function sortable_event_columns($columns) {
        $columns['rfm_event_date'] = 'rfm_event_date';
        return $columns;
    }

    public function sort_events_by_date($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        if ($query->get('post_type') !== 'rfm_event') {
            return;
        }
        if ($query->get('orderby') === 'rfm_event_date') {
            $query->set('meta_key', '_rfm_event_date');
            $query->set('orderby', 'meta_value');
        }
    }

    // =========================================================================
    // SINGLE TEMPLATE
    // =========================================================================

    public function event_template($template) {
        global $post;
        if ($post && $post->post_type === 'rfm_event') {
            $custom = RFM_PLUGIN_DIR . 'templates/single-event.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }
        return $template;
    }

    // =========================================================================
    // SHORTCODE: [rfm_events_page]
    // =========================================================================

    /**
     * Render the events listing page.
     *
     * Usage: [rfm_events_page limit="12" columns="3" category="" show_past="false"]
     */
    public function events_page_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit'     => 12,
            'columns'   => 3,
            'category'  => '',
            'show_past' => 'false',
        ), $atts);

        $today = current_time('Y-m-d');

        // Category filter from URL
        $selected_category = '';
        if (isset($_GET['rfm_category']) && !empty($_GET['rfm_category'])) {
            $selected_category = sanitize_text_field($_GET['rfm_category']);
        } elseif (!empty($atts['category'])) {
            $selected_category = $atts['category'];
        }

        // Build query
        $args = array(
            'post_type'      => 'rfm_event',
            'posts_per_page' => intval($atts['limit']),
            'post_status'    => 'publish',
            'meta_key'       => '_rfm_event_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        );

        // Hide past events by default
        if ($atts['show_past'] !== 'true') {
            $args['meta_query'] = array(
                array(
                    'key'     => '_rfm_event_date',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            );
        }

        // Category filter
        if ($selected_category) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'rfm_category',
                    'field'    => 'slug',
                    'terms'    => $selected_category,
                ),
            );
        }

        $events = new WP_Query($args);

        // Get categories for filter dropdown
        $categories = get_terms(array(
            'taxonomy'   => 'rfm_category',
            'hide_empty' => false,
        ));

        ob_start();
        ?>
        <div class="rfm-events-page">

            <!-- Filter Bar -->
            <div class="rfm-events-filter-bar">
                <form method="get" class="rfm-events-filter-form">
                    <div class="rfm-events-filters">
                        <select name="rfm_category" class="rfm-events-category-filter">
                            <option value=""><?php _e('Alle kategorier', 'rigtig-for-mig'); ?></option>
                            <?php if (!is_wp_error($categories)): ?>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->slug); ?>" <?php selected($selected_category, $cat->slug); ?>>
                                    <?php echo esc_html($cat->name); ?>
                                </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <button type="submit" class="rfm-btn rfm-btn-primary"><?php _e('Filtrer', 'rigtig-for-mig'); ?></button>
                    </div>
                </form>
            </div>

            <?php if ($events->have_posts()): ?>
            <!-- Events Grid -->
            <div class="rfm-events-grid rfm-columns-<?php echo intval($atts['columns']); ?>">
                <?php while ($events->have_posts()): $events->the_post(); ?>
                    <?php $this->render_event_card(get_the_ID(), $today); ?>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="rfm-events-empty">
                <p><?php _e('Der er ingen kommende kurser eller events lige nu. Kom tilbage snart!', 'rigtig-for-mig'); ?></p>
            </div>
            <?php endif; ?>

            <?php wp_reset_postdata(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single event card in the listing grid.
     */
    private function render_event_card($event_id, $today = '') {
        if (!$today) {
            $today = current_time('Y-m-d');
        }

        $date       = get_post_meta($event_id, '_rfm_event_date', true);
        $time_start = get_post_meta($event_id, '_rfm_event_time_start', true);
        $time_end   = get_post_meta($event_id, '_rfm_event_time_end', true);
        $location   = get_post_meta($event_id, '_rfm_event_location', true);
        $price      = get_post_meta($event_id, '_rfm_event_price', true);
        $expert_id  = get_post_meta($event_id, '_rfm_event_expert_id', true);
        $categories = wp_get_object_terms($event_id, 'rfm_category');
        $category   = !empty($categories) ? $categories[0] : null;
        $cat_color  = $category ? get_term_meta($category->term_id, 'rfm_color', true) : '';
        $is_past    = ($date && $date < $today);

        // Format date in Danish
        $date_formatted = '';
        if ($date) {
            $timestamp = strtotime($date);
            $date_formatted = date_i18n('j. F Y', $timestamp);
        }

        // Time string
        $time_str = '';
        if ($time_start) {
            $time_str = $time_start;
            if ($time_end) {
                $time_str .= ' – ' . $time_end;
            }
        }

        // Expert name
        $expert_name = '';
        $expert_url  = '';
        if ($expert_id) {
            $expert_post = get_post($expert_id);
            if ($expert_post) {
                $expert_name = $expert_post->post_title;
                $expert_url  = get_permalink($expert_id);
            }
        }

        ?>
        <div class="rfm-event-card <?php echo $is_past ? 'rfm-event-past' : ''; ?>">
            <a href="<?php echo esc_url(get_permalink($event_id)); ?>" class="rfm-event-card-link">

                <div class="rfm-event-card-image">
                    <?php if (has_post_thumbnail($event_id)): ?>
                        <?php echo get_the_post_thumbnail($event_id, 'medium_large'); ?>
                    <?php else: ?>
                        <div class="rfm-event-card-placeholder">
                            <span class="dashicons dashicons-calendar-alt"></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($date_formatted): ?>
                    <div class="rfm-event-card-date-badge">
                        <span class="rfm-event-badge-day"><?php echo esc_html(date_i18n('j', strtotime($date))); ?></span>
                        <span class="rfm-event-badge-month"><?php echo esc_html(date_i18n('M', strtotime($date))); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($is_past): ?>
                    <div class="rfm-event-card-past-overlay">
                        <span><?php _e('Afholdt', 'rigtig-for-mig'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="rfm-event-card-content">
                    <?php if ($category): ?>
                    <span class="rfm-event-card-category" style="color: <?php echo esc_attr($cat_color ?: '#666'); ?>;">
                        <?php echo esc_html($category->name); ?>
                    </span>
                    <?php endif; ?>

                    <h3 class="rfm-event-card-title"><?php echo esc_html(get_the_title($event_id)); ?></h3>

                    <div class="rfm-event-card-meta">
                        <?php if ($date_formatted): ?>
                        <div class="rfm-event-card-meta-item">
                            <span class="dashicons dashicons-calendar"></span>
                            <span><?php echo esc_html($date_formatted); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($time_str): ?>
                        <div class="rfm-event-card-meta-item">
                            <span class="dashicons dashicons-clock"></span>
                            <span><?php echo esc_html('kl. ' . $time_str); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($location): ?>
                        <div class="rfm-event-card-meta-item">
                            <span class="dashicons dashicons-location"></span>
                            <span><?php echo esc_html($location); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($expert_name): ?>
                        <div class="rfm-event-card-meta-item">
                            <span class="dashicons dashicons-admin-users"></span>
                            <span><?php echo esc_html($expert_name); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($price): ?>
                    <div class="rfm-event-card-price">
                        <?php echo esc_html($price); ?>
                    </div>
                    <?php endif; ?>
                </div>

            </a>
        </div>
        <?php
    }

    // =========================================================================
    // SOCIAL SHARING HELPER
    // =========================================================================

    /**
     * Render social sharing buttons for an event.
     *
     * @param int $event_id Post ID.
     */
    public static function render_share_buttons($event_id) {
        $url   = urlencode(get_permalink($event_id));
        $title = urlencode(get_the_title($event_id));
        $raw_url = get_permalink($event_id);

        ?>
        <div class="rfm-event-share">
            <span class="rfm-share-label"><?php _e('Del dette event:', 'rigtig-for-mig'); ?></span>

            <div class="rfm-share-buttons">
                <!-- Facebook -->
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $url; ?>"
                   class="rfm-share-btn rfm-share-facebook"
                   target="_blank" rel="noopener noreferrer"
                   title="<?php esc_attr_e('Del på Facebook', 'rigtig-for-mig'); ?>"
                   onclick="window.open(this.href,'facebook-share','width=580,height=400,toolbar=0,menubar=0');return false;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>

                <!-- LinkedIn -->
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $url; ?>"
                   class="rfm-share-btn rfm-share-linkedin"
                   target="_blank" rel="noopener noreferrer"
                   title="<?php esc_attr_e('Del på LinkedIn', 'rigtig-for-mig'); ?>"
                   onclick="window.open(this.href,'linkedin-share','width=580,height=400,toolbar=0,menubar=0');return false;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                </a>

                <!-- Email -->
                <a href="mailto:?subject=<?php echo $title; ?>&body=<?php echo __('Se dette event:', 'rigtig-for-mig') . '%20' . $url; ?>"
                   class="rfm-share-btn rfm-share-email"
                   title="<?php esc_attr_e('Del via email', 'rigtig-for-mig'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                </a>

                <!-- Copy Link -->
                <button type="button"
                        class="rfm-share-btn rfm-share-copy"
                        data-url="<?php echo esc_attr($raw_url); ?>"
                        title="<?php esc_attr_e('Kopiér link', 'rigtig-for-mig'); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>
                    <span class="rfm-share-copy-feedback"><?php _e('Kopieret!', 'rigtig-for-mig'); ?></span>
                </button>
            </div>
        </div>

        <script>
        (function() {
            document.querySelectorAll('.rfm-share-copy').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var url = this.getAttribute('data-url');
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).then(function() {
                            btn.classList.add('rfm-copied');
                            setTimeout(function() { btn.classList.remove('rfm-copied'); }, 2000);
                        });
                    } else {
                        var input = document.createElement('input');
                        input.value = url;
                        document.body.appendChild(input);
                        input.select();
                        document.execCommand('copy');
                        document.body.removeChild(input);
                        btn.classList.add('rfm-copied');
                        setTimeout(function() { btn.classList.remove('rfm-copied'); }, 2000);
                    }
                });
            });
        })();
        </script>
        <?php
    }
}
