<?php
/**
 * Single Article Template
 *
 * Displays an expert article with author info, category, and featured image.
 *
 * @package Rigtig_For_Mig
 * @since 3.13.0
 */

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <?php
        while (have_posts()) :
            the_post();

            $article_id = get_the_ID();
            $expert_id = get_post_meta($article_id, '_rfm_expert_id', true);
            $expert = $expert_id ? get_post($expert_id) : null;
            $categories = wp_get_object_terms($article_id, 'rfm_category');
            $category = !empty($categories) ? $categories[0] : null;
            $category_color = $category ? get_term_meta($category->term_id, 'rfm_color', true) : '#666';

            // Expert info
            $expert_name = $expert ? $expert->post_title : __('Ukendt ekspert', 'rigtig-for-mig');
            $expert_url = $expert ? get_permalink($expert_id) : '#';
            $expert_avatar_url = '';
            if ($expert && has_post_thumbnail($expert_id)) {
                $expert_avatar_url = get_the_post_thumbnail_url($expert_id, 'thumbnail');
            }
            ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class('rfm-single-article'); ?>>

                <?php if ($category): ?>
                <div class="rfm-article-category-bar" style="background-color: <?php echo esc_attr($category_color ?: '#666'); ?>;">
                    <a href="<?php echo esc_url(get_term_link($category)); ?>">
                        <?php echo esc_html($category->name); ?>
                    </a>
                </div>
                <?php endif; ?>

                <header class="rfm-article-header">
                    <h1 class="rfm-article-title"><?php the_title(); ?></h1>

                    <div class="rfm-article-meta">
                        <div class="rfm-article-author">
                            <?php if ($expert_avatar_url): ?>
                            <img src="<?php echo esc_url($expert_avatar_url); ?>"
                                 alt="<?php echo esc_attr($expert_name); ?>"
                                 class="rfm-article-author-avatar" />
                            <?php endif; ?>
                            <div class="rfm-article-author-info">
                                <span class="rfm-article-author-label"><?php _e('Skrevet af', 'rigtig-for-mig'); ?></span>
                                <a href="<?php echo esc_url($expert_url); ?>" class="rfm-article-author-name">
                                    <?php echo esc_html($expert_name); ?>
                                </a>
                            </div>
                        </div>
                        <div class="rfm-article-date">
                            <time datetime="<?php echo get_the_date('c'); ?>">
                                <?php echo get_the_date('j. F Y'); ?>
                            </time>
                        </div>
                    </div>
                </header>

                <?php if (has_post_thumbnail()): ?>
                <div class="rfm-article-featured-image">
                    <?php the_post_thumbnail('large'); ?>
                </div>
                <?php endif; ?>

                <div class="rfm-article-content entry-content">
                    <?php the_content(); ?>
                </div>

                <footer class="rfm-article-footer">
                    <div class="rfm-article-expert-card">
                        <h4><?php _e('Om forfatteren', 'rigtig-for-mig'); ?></h4>
                        <div class="rfm-article-expert-card-inner">
                            <?php if ($expert_avatar_url): ?>
                            <img src="<?php echo esc_url($expert_avatar_url); ?>"
                                 alt="<?php echo esc_attr($expert_name); ?>"
                                 class="rfm-article-expert-avatar" />
                            <?php endif; ?>
                            <div class="rfm-article-expert-info">
                                <a href="<?php echo esc_url($expert_url); ?>" class="rfm-article-expert-name">
                                    <?php echo esc_html($expert_name); ?>
                                </a>
                                <?php if ($category): ?>
                                <span class="rfm-article-expert-category" style="color: <?php echo esc_attr($category_color ?: '#666'); ?>;">
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

            </article>

        <?php
        endwhile;
        ?>

    </main>
</div>

<?php
get_sidebar();
get_footer();
