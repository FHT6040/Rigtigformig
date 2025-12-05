<?php
/**
 * Single Expert Profile Template
 *
 * @package Rigtig_For_Mig
 */

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        
        <?php
        while (have_posts()) :
            the_post();
            ?>
            
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                
                <?php
                // Do NOT display the featured image here - it will be handled by our plugin
                // This prevents the theme from automatically showing the featured image
                ?>
                
                <div class="entry-content">
                    <?php
                    // The content will be modified by our class-rfm-expert-profile.php filter
                    the_content();
                    ?>
                </div>
                
            </article>
            
        <?php
        endwhile;
        ?>
        
    </main>
</div>

<?php
get_sidebar();
get_footer();
