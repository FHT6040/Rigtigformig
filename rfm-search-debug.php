<?php
/**
 * RFM Search Debug Tool
 *
 * Upload this file to your WordPress root directory and access it via:
 * https://rigtigformig.dk/rfm-search-debug.php
 *
 * This will show you what's happening with the search functionality.
 */

// Load WordPress
require_once('wp-load.php');

// Security check - only allow admins
if (!current_user_can('manage_options')) {
    die('Access denied. Please log in as administrator first.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>RFM Search Debug Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        pre { background: #f9f9f9; padding: 10px; border-left: 3px solid #4CAF50; overflow-x: auto; }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>🔍 RFM Search Debug Tool</h1>
    <p><strong>Plugin Version:</strong> <?php echo defined('RFM_VERSION') ? RFM_VERSION : 'Not defined'; ?></p>

    <?php

    // 1. Check all Frank* experts
    echo '<div class="section">';
    echo '<h2>1. Search for "Frank" in post_title</h2>';

    $frank_query = new WP_Query(array(
        'post_type' => 'rfm_expert',
        'post_status' => 'publish',
        's' => 'Frank',
        'posts_per_page' => -1
    ));

    echo '<p>Found: <strong>' . $frank_query->found_posts . '</strong> experts</p>';

    if ($frank_query->have_posts()) {
        echo '<table>';
        echo '<tr><th>ID</th><th>Name (post_title)</th><th>Status</th><th>City</th><th>Postal Code</th><th>Has Coordinates</th></tr>';
        while ($frank_query->have_posts()) {
            $frank_query->the_post();
            $id = get_the_ID();
            $city = get_post_meta($id, '_rfm_city', true);
            $postal = get_post_meta($id, '_rfm_postal_code', true);
            $lat = get_post_meta($id, '_rfm_latitude', true);
            $lng = get_post_meta($id, '_rfm_longitude', true);
            $has_coords = (!empty($lat) && !empty($lng)) ? '<span class="success">✓ Yes</span>' : '<span class="error">✗ No</span>';

            echo '<tr>';
            echo '<td>' . $id . '</td>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . get_post_status() . '</td>';
            echo '<td>' . ($city ?: '<em>empty</em>') . '</td>';
            echo '<td>' . ($postal ?: '<em>empty</em>') . '</td>';
            echo '<td>' . $has_coords . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p class="error">No experts found with "Frank" in post_title!</p>';
    }
    wp_reset_postdata();

    echo '</div>';

    // 2. Check experts with Odense postal code or city
    echo '<div class="section">';
    echo '<h2>2. Search for Experts in Odense</h2>';

    // Search by postal code 5240
    $odense_postal_query = new WP_Query(array(
        'post_type' => 'rfm_expert',
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_rfm_postal_code',
                'value' => '5240',
                'compare' => '='
            )
        ),
        'posts_per_page' => -1
    ));

    echo '<h3>By Postal Code (5240)</h3>';
    echo '<p>Found: <strong>' . $odense_postal_query->found_posts . '</strong> experts</p>';

    if ($odense_postal_query->have_posts()) {
        echo '<table>';
        echo '<tr><th>ID</th><th>Name</th><th>City</th><th>Postal Code</th><th>Latitude</th><th>Longitude</th></tr>';
        while ($odense_postal_query->have_posts()) {
            $odense_postal_query->the_post();
            $id = get_the_ID();
            $city = get_post_meta($id, '_rfm_city', true);
            $postal = get_post_meta($id, '_rfm_postal_code', true);
            $lat = get_post_meta($id, '_rfm_latitude', true);
            $lng = get_post_meta($id, '_rfm_longitude', true);

            echo '<tr>';
            echo '<td>' . $id . '</td>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . $city . '</td>';
            echo '<td>' . $postal . '</td>';
            echo '<td>' . $lat . '</td>';
            echo '<td>' . $lng . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p class="error">No experts found with postal code 5240!</p>';
    }
    wp_reset_postdata();

    // Search by city name containing "odense"
    $odense_city_query = new WP_Query(array(
        'post_type' => 'rfm_expert',
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_rfm_city',
                'value' => 'odense',
                'compare' => 'LIKE'
            )
        ),
        'posts_per_page' => -1
    ));

    echo '<h3>By City Name (LIKE "odense")</h3>';
    echo '<p>Found: <strong>' . $odense_city_query->found_posts . '</strong> experts</p>';

    if ($odense_city_query->have_posts()) {
        echo '<table>';
        echo '<tr><th>ID</th><th>Name</th><th>City</th><th>Postal Code</th><th>Coordinates</th></tr>';
        while ($odense_city_query->have_posts()) {
            $odense_city_query->the_post();
            $id = get_the_ID();
            $city = get_post_meta($id, '_rfm_city', true);
            $postal = get_post_meta($id, '_rfm_postal_code', true);
            $lat = get_post_meta($id, '_rfm_latitude', true);
            $lng = get_post_meta($id, '_rfm_longitude', true);
            $coords = (!empty($lat) && !empty($lng)) ? "✓ ({$lat}, {$lng})" : '✗ Missing';

            echo '<tr>';
            echo '<td>' . $id . '</td>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . $city . '</td>';
            echo '<td>' . $postal . '</td>';
            echo '<td>' . $coords . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p class="warning">No experts found with city containing "odense"!</p>';
    }
    wp_reset_postdata();

    echo '</div>';

    // 3. Check postal code database
    echo '<div class="section">';
    echo '<h2>3. Check Postal Code Database</h2>';

    if (class_exists('RFM_Postal_Codes')) {
        echo '<p class="success">✓ RFM_Postal_Codes class exists</p>';

        // Check if 5240 exists
        $coords_5240 = RFM_Postal_Codes::get_coordinates('5240');
        if ($coords_5240) {
            echo '<h3>Postal Code 5240:</h3>';
            echo '<pre>' . print_r($coords_5240, true) . '</pre>';
        } else {
            echo '<p class="error">✗ Postal code 5240 not found in database!</p>';
        }

        // Check city search
        if (method_exists('RFM_Postal_Codes', 'get_coordinates_by_city')) {
            echo '<p class="success">✓ get_coordinates_by_city() method exists</p>';
            $coords_odense = RFM_Postal_Codes::get_coordinates_by_city('odense');
            if ($coords_odense) {
                echo '<h3>City Search "odense":</h3>';
                echo '<pre>' . print_r($coords_odense, true) . '</pre>';
            } else {
                echo '<p class="error">✗ No coordinates found for city "odense"!</p>';
            }
        } else {
            echo '<p class="error">✗ get_coordinates_by_city() method does NOT exist (need to upload v3.9.4!)</p>';
        }
    } else {
        echo '<p class="error">✗ RFM_Postal_Codes class does NOT exist!</p>';
    }

    echo '</div>';

    // 4. Test actual search query
    echo '<div class="section">';
    echo '<h2>4. Test Actual Search Query</h2>';

    echo '<h3>Simulating search for "Frank"</h3>';
    $test_frank = new WP_Query(array(
        'post_type' => 'rfm_expert',
        'post_status' => 'publish',
        's' => 'Frank',
        'posts_per_page' => 10
    ));
    echo '<p>Found: <strong>' . $test_frank->found_posts . '</strong> experts</p>';
    echo '<p>SQL Query:</p><pre>' . $test_frank->request . '</pre>';
    wp_reset_postdata();

    echo '<h3>Simulating location search for "Odense"</h3>';
    $_GET['rfm_location'] = 'Odense';
    $_GET['rfm_radius'] = '25';

    $test_odense = new WP_Query(array(
        'post_type' => 'rfm_expert',
        'post_status' => 'publish',
        'posts_per_page' => 10
    ));

    // The modify_expert_query hook should have modified this query
    echo '<p>Found: <strong>' . $test_odense->found_posts . '</strong> experts</p>';
    echo '<p>Query vars:</p><pre>' . print_r($test_odense->query_vars, true) . '</pre>';
    echo '<p>SQL Query:</p><pre>' . $test_odense->request . '</pre>';
    wp_reset_postdata();

    unset($_GET['rfm_location']);
    unset($_GET['rfm_radius']);

    echo '</div>';

    // 5. Check hooks
    echo '<div class="section">';
    echo '<h2>5. Check Registered Hooks</h2>';

    global $wp_filter;

    if (isset($wp_filter['pre_get_posts'])) {
        echo '<h3>pre_get_posts filters:</h3>';
        echo '<pre>' . print_r($wp_filter['pre_get_posts'], true) . '</pre>';
    }

    if (isset($wp_filter['posts_search'])) {
        echo '<h3>posts_search filters:</h3>';
        echo '<pre>' . print_r($wp_filter['posts_search'], true) . '</pre>';
    }

    echo '</div>';

    // 6. Get specific expert data
    echo '<div class="section">';
    echo '<h2>6. Check Specific Expert (ID 1303)</h2>';

    $expert = get_post(1303);
    if ($expert) {
        echo '<h3>Post Data:</h3>';
        echo '<table>';
        echo '<tr><th>Field</th><th>Value</th></tr>';
        echo '<tr><td>ID</td><td>' . $expert->ID . '</td></tr>';
        echo '<tr><td>Title</td><td>' . $expert->post_title . '</td></tr>';
        echo '<tr><td>Status</td><td>' . $expert->post_status . '</td></tr>';
        echo '<tr><td>Type</td><td>' . $expert->post_type . '</td></tr>';
        echo '</table>';

        echo '<h3>Meta Data:</h3>';
        $meta = get_post_meta(1303);
        echo '<table>';
        echo '<tr><th>Meta Key</th><th>Value</th></tr>';
        foreach ($meta as $key => $values) {
            if (strpos($key, '_rfm') === 0) {
                echo '<tr><td>' . $key . '</td><td>' . print_r($values[0], true) . '</td></tr>';
            }
        }
        echo '</table>';
    } else {
        echo '<p class="error">Expert 1303 not found!</p>';
    }

    echo '</div>';

    ?>

    <div class="section">
        <h2>✅ Next Steps</h2>
        <ol>
            <li>Check if v3.9.4 is uploaded (look for "get_coordinates_by_city()" method)</li>
            <li>Verify experts have correct data (postal code, city, coordinates)</li>
            <li>Check if hooks are registered correctly</li>
            <li>Test if search queries are being modified</li>
        </ol>

        <p><strong>After reviewing this data, delete this file for security!</strong></p>
        <p>Command: <code>rm rfm-search-debug.php</code></p>
    </div>

</body>
</html>
