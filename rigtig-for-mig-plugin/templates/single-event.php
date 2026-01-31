<?php
/**
 * Single Event Template
 *
 * Displays a course/event with details, match sections, file download,
 * expert info, and social sharing buttons.
 *
 * @package Rigtig_For_Mig
 * @since 3.14.0
 */

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <?php
        while (have_posts()) :
            the_post();

            $event_id   = get_the_ID();
            $date       = get_post_meta($event_id, '_rfm_event_date', true);
            $time_start = get_post_meta($event_id, '_rfm_event_time_start', true);
            $time_end   = get_post_meta($event_id, '_rfm_event_time_end', true);
            $location   = get_post_meta($event_id, '_rfm_event_location', true);
            $price      = get_post_meta($event_id, '_rfm_event_price', true);
            $event_url  = get_post_meta($event_id, '_rfm_event_url', true);
            $format     = get_post_meta($event_id, '_rfm_event_format', true);
            $expert_id  = get_post_meta($event_id, '_rfm_event_expert_id', true);
            $file_id    = get_post_meta($event_id, '_rfm_event_file_id', true);

            // Taxonomies
            $categories    = wp_get_object_terms($event_id, 'rfm_category');
            $category      = !empty($categories) ? $categories[0] : null;
            $cat_color     = $category ? get_term_meta($category->term_id, 'rfm_color', true) : '#666';
            $event_types   = wp_get_object_terms($event_id, 'rfm_event_type');
            $event_type    = !empty($event_types) ? $event_types[0] : null;
            $audiences     = wp_get_object_terms($event_id, 'rfm_event_audience');
            $audience      = !empty($audiences) ? $audiences[0] : null;

            // Match sections
            $what_you_get = get_post_meta($event_id, '_rfm_event_what_you_get', true);
            $who_for      = get_post_meta($event_id, '_rfm_event_who_for', true);
            $who_not_for  = get_post_meta($event_id, '_rfm_event_who_not_for', true);

            // Expert info
            $expert      = $expert_id ? get_post($expert_id) : null;
            $expert_name = $expert ? $expert->post_title : '';
            $expert_url  = $expert ? get_permalink($expert_id) : '';
            $expert_avatar_url = '';
            if ($expert && has_post_thumbnail($expert_id)) {
                $expert_avatar_url = get_the_post_thumbnail_url($expert_id, 'thumbnail');
            }

            // Formatted date
            $date_formatted = '';
            if ($date) {
                $date_formatted = date_i18n('l \d. j. F Y', strtotime($date));
            }

            // Time string
            $time_str = '';
            if ($time_start) {
                $time_str = 'kl. ' . $time_start;
                if ($time_end) {
                    $time_str .= ' – ' . $time_end;
                }
            }

            // Format label
            $format_labels = array(
                'fysisk' => __('Fysisk', 'rigtig-for-mig'),
                'online' => __('Online', 'rigtig-for-mig'),
                'hybrid' => __('Hybrid', 'rigtig-for-mig'),
            );
            $format_label = isset($format_labels[$format]) ? $format_labels[$format] : '';

            // Is past?
            $is_past = ($date && $date < current_time('Y-m-d'));

            // File info
            $file_url  = $file_id ? wp_get_attachment_url($file_id) : '';
            $file_name = $file_id ? basename(get_attached_file($file_id)) : '';
            ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class('rfm-single-event'); ?>>

                <?php if ($category): ?>
                <div class="rfm-event-category-bar" style="background-color: <?php echo esc_attr($cat_color ?: '#666'); ?>;">
                    <a href="<?php echo esc_url(get_term_link($category)); ?>">
                        <?php echo esc_html($category->name); ?>
                    </a>
                </div>
                <?php endif; ?>

                <header class="rfm-event-header">
                    <h1 class="rfm-event-title"><?php the_title(); ?></h1>

                    <?php if ($event_type || $audience || $format_label): ?>
                    <div class="rfm-event-tags">
                        <?php if ($event_type): ?>
                        <span class="rfm-event-tag rfm-event-tag-type"><?php echo esc_html($event_type->name); ?></span>
                        <?php endif; ?>
                        <?php if ($format_label): ?>
                        <span class="rfm-event-tag rfm-event-tag-format"><?php echo esc_html($format_label); ?></span>
                        <?php endif; ?>
                        <?php if ($audience): ?>
                        <span class="rfm-event-tag rfm-event-tag-audience"><?php echo esc_html($audience->name); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($is_past): ?>
                    <div class="rfm-event-past-notice">
                        <?php _e('Dette event er afholdt.', 'rigtig-for-mig'); ?>
                    </div>
                    <?php endif; ?>
                </header>

                <!-- Event Details Box -->
                <div class="rfm-event-details-box">
                    <?php if ($date_formatted): ?>
                    <div class="rfm-event-detail">
                        <span class="rfm-event-detail-icon dashicons dashicons-calendar"></span>
                        <div class="rfm-event-detail-text">
                            <strong><?php _e('Dato', 'rigtig-for-mig'); ?></strong>
                            <span><?php echo esc_html($date_formatted); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($time_str): ?>
                    <div class="rfm-event-detail">
                        <span class="rfm-event-detail-icon dashicons dashicons-clock"></span>
                        <div class="rfm-event-detail-text">
                            <strong><?php _e('Tidspunkt', 'rigtig-for-mig'); ?></strong>
                            <span><?php echo esc_html($time_str); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($location): ?>
                    <div class="rfm-event-detail">
                        <span class="rfm-event-detail-icon dashicons dashicons-location"></span>
                        <div class="rfm-event-detail-text">
                            <strong><?php _e('Lokation', 'rigtig-for-mig'); ?></strong>
                            <span><?php echo esc_html($location); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($format_label): ?>
                    <div class="rfm-event-detail">
                        <span class="rfm-event-detail-icon dashicons dashicons-desktop"></span>
                        <div class="rfm-event-detail-text">
                            <strong><?php _e('Format', 'rigtig-for-mig'); ?></strong>
                            <span><?php echo esc_html($format_label); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($price): ?>
                    <div class="rfm-event-detail">
                        <span class="rfm-event-detail-icon dashicons dashicons-tag"></span>
                        <div class="rfm-event-detail-text">
                            <strong><?php _e('Pris', 'rigtig-for-mig'); ?></strong>
                            <span><?php echo esc_html($price); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($expert_name): ?>
                    <div class="rfm-event-detail">
                        <span class="rfm-event-detail-icon dashicons dashicons-admin-users"></span>
                        <div class="rfm-event-detail-text">
                            <strong><?php _e('Instruktør', 'rigtig-for-mig'); ?></strong>
                            <a href="<?php echo esc_url($expert_url); ?>"><?php echo esc_html($expert_name); ?></a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($event_url && !$is_past): ?>
                    <div class="rfm-event-detail rfm-event-detail-cta">
                        <a href="<?php echo esc_url($event_url); ?>" class="rfm-btn rfm-btn-primary" target="_blank" rel="noopener noreferrer">
                            <?php _e('Tilmeld dig', 'rigtig-for-mig'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (has_post_thumbnail()): ?>
                <div class="rfm-event-featured-image">
                    <?php the_post_thumbnail('large'); ?>
                </div>
                <?php endif; ?>

                <div class="rfm-event-content entry-content">
                    <?php the_content(); ?>
                </div>

                <!-- Match Sections -->
                <?php if ($what_you_get): ?>
                <div class="rfm-event-match-section rfm-event-match-get">
                    <h3><?php _e('Hvad får du ud af det?', 'rigtig-for-mig'); ?></h3>
                    <div class="rfm-event-match-content">
                        <?php echo wp_kses_post(wpautop($what_you_get)); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($who_for || $who_not_for): ?>
                <div class="rfm-event-match-row">
                    <?php if ($who_for): ?>
                    <div class="rfm-event-match-section rfm-event-match-for">
                        <h4><?php _e('Typisk relevant hvis du...', 'rigtig-for-mig'); ?></h4>
                        <div class="rfm-event-match-content">
                            <?php echo wp_kses_post(wpautop($who_for)); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($who_not_for): ?>
                    <div class="rfm-event-match-section rfm-event-match-not-for">
                        <h4><?php _e('Måske ikke relevant hvis du...', 'rigtig-for-mig'); ?></h4>
                        <div class="rfm-event-match-content">
                            <?php echo wp_kses_post(wpautop($who_not_for)); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- File Download -->
                <?php if ($file_url && $file_name): ?>
                <div class="rfm-event-file-download">
                    <span class="dashicons dashicons-media-document"></span>
                    <a href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener noreferrer" class="rfm-btn rfm-btn-secondary">
                        <?php echo esc_html($file_name); ?>
                    </a>
                    <small><?php _e('Download pjece / fil', 'rigtig-for-mig'); ?></small>
                </div>
                <?php endif; ?>

                <!-- Social Sharing -->
                <?php RFM_Events::render_share_buttons($event_id); ?>

                <?php if ($expert): ?>
                <footer class="rfm-event-footer">
                    <div class="rfm-event-expert-card">
                        <h4><?php _e('Om instruktøren', 'rigtig-for-mig'); ?></h4>
                        <div class="rfm-event-expert-card-inner">
                            <?php if ($expert_avatar_url): ?>
                            <img src="<?php echo esc_url($expert_avatar_url); ?>"
                                 alt="<?php echo esc_attr($expert_name); ?>"
                                 class="rfm-event-expert-avatar" />
                            <?php endif; ?>
                            <div class="rfm-event-expert-info">
                                <a href="<?php echo esc_url($expert_url); ?>" class="rfm-event-expert-name">
                                    <?php echo esc_html($expert_name); ?>
                                </a>
                                <?php if ($category): ?>
                                <span class="rfm-event-expert-category" style="color: <?php echo esc_attr($cat_color ?: '#666'); ?>;">
                                    <?php echo esc_html($category->name); ?>
                                </span>
                                <?php endif; ?>
                                <a href="<?php echo esc_url($expert_url); ?>" class="rfm-btn rfm-btn-primary rfm-btn-small">
                                    <?php _e('Se profil', 'rigtig-for-mig'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </footer>
                <?php endif; ?>

            </article>

        <?php
        endwhile;
        ?>

    </main>
</div>

<?php
get_sidebar();
get_footer();
