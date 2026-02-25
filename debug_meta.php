<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load WordPress environment
require_once( 'c:\xampp\htdocs\wordpress\wp-load.php' );

$output = "";

$output .= "--- Debugging Post 27841 ---\n";
$post_id = 27841;
$post = get_post($post_id);
if ($post) {
    $output .= "ID: " . $post->ID . "\n";
    $output .= "Title: " . $post->post_title . "\n";
    $output .= "Type: " . $post->post_type . "\n";
    
    // Check meta
    $city_id = get_post_meta( $post->ID, '_sign_city_id', true );
    $output .= "_sign_city_id: " . var_export($city_id, true) . "\n";
    
    if ($city_id) {
        $city = get_post($city_id);
        if ($city) {
            $output .= "Linked City: " . $city->post_title . " (ID: $city->ID)\n";
        } else {
            $output .= "Linked City ID $city_id NOT FOUND in DB.\n";
        }
    }
} else {
    $output .= "Post 27841 not found.\n";
}

$output .= "\n--- Searching for 'UT-02303' ---\n";
global $wpdb;
$results = $wpdb->get_results("SELECT ID, post_title, post_type FROM $wpdb->posts WHERE post_title = 'UT-02303' AND post_type = 'sign'");
if ($results) {
    foreach ($results as $p) {
        $output .= "Found Post by Title SQL:\n";
        $output .= "ID: " . $p->ID . "\n";
        $output .= "Title: " . $p->post_title . "\n";
        
        $city_id = get_post_meta( $p->ID, '_sign_city_id', true );
        $output .= "_sign_city_id: " . var_export($city_id, true) . "\n";
        
        if ($city_id) {
             $city = get_post($city_id);
             if ($city) {
                 $output .= "Linked City: " . $city->post_title . " (ID: $city->ID)\n";
             } else {
                 $output .= "Linked City ID $city_id NOT FOUND in DB.\n";
             }
        } else {
            $output .= "No City ID in meta.\n";
        }
        $output .= "----------------\n";
    }
} else {
    $output .= "Post 'UT-02303' not found by title SQL.\n";
}


file_put_contents('c:\xampp\htdocs\wordpress\wp-content\plugins\Open-Street-Map-WordPress-Plugin\debug_output.txt', $output);
echo "Debug output written to debug_output.txt\n";
