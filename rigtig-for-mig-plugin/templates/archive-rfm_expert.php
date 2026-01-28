<?php
/**
 * Archive Template for RFM Experts
 *
 * This template is used for:
 * - /eksperter/ archive page
 * - Search results for rfm_expert post type
 *
 * @package Rigtig_For_Mig
 */

get_header();
?>

<div class="rfm-expert-archive">
    <div class="rfm-container">

        <?php
        // Show search form
        echo do_shortcode('[rfm_expert_search]');
        ?>

        <div class="rfm-expert-results">
            <?php
            // Check if this is a search or filter request
            $is_search = (isset($_GET['s']) && !empty($_GET['s'])) ||
                         (isset($_GET['rfm_category']) && !empty($_GET['rfm_category'])) ||
                         (isset($_GET['rfm_location']) && !empty($_GET['rfm_location']));

            if ($is_search) {
                // Show filtered results using shortcode
                // The shortcode will handle all URL parameters automatically
                echo do_shortcode('[rfm_expert_list columns="3"]');
            } else {
                // Show default expert list (limited to 12)
                echo do_shortcode('[rfm_expert_list limit="12" columns="3"]');
            }
            ?>
        </div>

    </div>
</div>

<?php
get_footer();
