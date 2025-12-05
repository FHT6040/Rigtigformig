<?php
/**
 * FLUSH PERMALINKS & FIX QUERY
 * 
 * Upload this file to: /wp-content/plugins/rigtig-for-mig-plugin/
 * Then visit: yourdomain.com/wp-content/plugins/rigtig-for-mig-plugin/flush-fix.php
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Must be admin
if (!current_user_can('manage_options')) {
    die('Du skal være administrator!');
}

echo '<h1>🔧 Flush Permalinks & Fix Query</h1>';

// 1. Flush permalinks
flush_rewrite_rules(true);
echo '<p>✅ Permalinks flushed!</p>';

// 2. Test query
echo '<h2>🔍 Test Query:</h2>';

$args = array(
    'post_type' => 'rfm_expert',
    'posts_per_page' => -1,
    'post_status' => 'publish'
);

$query = new WP_Query($args);

echo '<p><strong>Found posts:</strong> ' . $query->found_posts . '</p>';
echo '<p><strong>Post count:</strong> ' . $query->post_count . '</p>';

if ($query->have_posts()) {
    echo '<ul>';
    while ($query->have_posts()) {
        $query->the_post();
        echo '<li>' . get_the_title() . ' (ID: ' . get_the_ID() . ', Status: ' . get_post_status() . ')</li>';
    }
    wp_reset_postdata();
    echo '</ul>';
} else {
    echo '<p style="color: red;">❌ INGEN posts fundet!</p>';
    
    // Check all posts
    $all = new WP_Query(array('post_type' => 'rfm_expert', 'post_status' => 'any', 'posts_per_page' => -1));
    echo '<p><strong>Total rfm_expert posts (any status):</strong> ' . $all->found_posts . '</p>';
    
    if ($all->have_posts()) {
        echo '<ul>';
        while ($all->have_posts()) {
            $all->the_post();
            echo '<li>' . get_the_title() . ' - Status: ' . get_post_status() . ' (ID: ' . get_the_ID() . ')</li>';
        }
        wp_reset_postdata();
        echo '</ul>';
    }
}

// 3. Check post type registration
echo '<h2>📋 Post Type Info:</h2>';
$post_type_obj = get_post_type_object('rfm_expert');
if ($post_type_obj) {
    echo '<p>✅ Post type "rfm_expert" is registered!</p>';
    echo '<pre>' . print_r($post_type_obj, true) . '</pre>';
} else {
    echo '<p style="color: red;">❌ Post type "rfm_expert" NOT registered!</p>';
}

echo '<hr>';
echo '<p><a href="' . admin_url() . '">← Back to admin</a></p>';
echo '<p><strong>Gå nu til /eksperter/ siden og se om det virker!</strong></p>';
