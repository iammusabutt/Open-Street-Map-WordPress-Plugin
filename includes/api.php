<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// REST API endpoint for import progress
function osm_import_progress_endpoint() {
    register_rest_route('osm/v1', '/import-progress/(?P<job_id>[a-zA-Z0-9_]+)', array(
        'methods' => 'GET',
        'callback' => 'osm_get_import_progress',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ));
}
add_action('rest_api_init', 'osm_import_progress_endpoint');

function osm_get_import_progress($data) {
    $job_id = $data['job_id'];
    $job = get_option($job_id);
    if (!$job) {
        return new WP_Error('not_found', 'Import job not found.', array('status' => 404));
    }
    return new WP_REST_Response($job, 200);
}

// REST API endpoint for cities
add_action( 'rest_api_init', function () {
    register_rest_route( 'osm/v1', '/cities', array(
        'methods' => 'GET',
        'callback' => 'get_cities_data',
        'permission_callback' => '__return_true'
    ) );
} );

function get_cities_data() {
    global $wpdb;

    $posts = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'city' AND post_status = 'publish'");

    $meta_keys = "'_city_lng', '_city_lat', '_city_count', '_city_venue', '_thumbnail_id'";
    $raw_meta = $wpdb->get_results("
        SELECT post_id, meta_key, meta_value 
        FROM {$wpdb->postmeta} 
        WHERE post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'city' AND post_status = 'publish')
        AND meta_key IN ($meta_keys)
    ");

    $meta_map = array();
    foreach ($raw_meta as $row) {
        $meta_map[$row->post_id][$row->meta_key] = $row->meta_value;
    }

    $data = array();
    foreach ($posts as $post) {
        $post_id = $post->ID;
        $m = isset($meta_map[$post_id]) ? $meta_map[$post_id] : array();
        
        $thumb_id = isset($m['_thumbnail_id']) ? $m['_thumbnail_id'] : '';
        $featured_image_url = '';
        if ($thumb_id) {
            $featured_image_url = wp_get_attachment_image_url($thumb_id, 'full');
        }

        $data[] = array(
            'name' => $post->post_title,
            'coords' => [
                (float) (isset($m['_city_lng']) ? $m['_city_lng'] : 0),
                (float) (isset($m['_city_lat']) ? $m['_city_lat'] : 0)
            ],
            'count' => (int) (isset($m['_city_count']) ? $m['_city_count'] : 0),
            'venue' => isset($m['_city_venue']) ? $m['_city_venue'] : '',
            'img' => $featured_image_url,
            'link' => get_permalink($post_id),
        );
    }
    return $data;
}

// REST API endpoint for signs
add_action( 'rest_api_init', function () {
    register_rest_route( 'osm/v1', '/signs', array(
        'methods' => 'GET',
        'callback' => 'get_signs_data',
        'permission_callback' => '__return_true'
    ) );
} );

function get_signs_data() {
    global $wpdb;
    $image_priority = get_option('osm_image_priority', 'featured');

    $posts = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'sign' AND post_status = 'publish'");

    $meta_keys = "'_sign_city', '_sign_venue', '_sign_lat', '_sign_lng', '_sign_image_url', '_thumbnail_id', '_sign_cta_behavior', '_sign_cta_url'";
    $raw_meta = $wpdb->get_results("
        SELECT post_id, meta_key, meta_value 
        FROM {$wpdb->postmeta} 
        WHERE post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'sign' AND post_status = 'publish')
        AND meta_key IN ($meta_keys)
    ");

    $meta_map = array();
    foreach ($raw_meta as $row) {
        $meta_map[$row->post_id][$row->meta_key] = $row->meta_value;
    }

    $data = array();
    foreach ($posts as $post) {
        $post_id = $post->ID;
        $m = isset($meta_map[$post_id]) ? $meta_map[$post_id] : array();
        
        $external_image_url = isset($m['_sign_image_url']) ? $m['_sign_image_url'] : '';
        $thumb_id = isset($m['_thumbnail_id']) ? $m['_thumbnail_id'] : '';
        $featured_image_url = '';
        if ($thumb_id) {
            $featured_image_url = wp_get_attachment_image_url($thumb_id, 'full');
        }

        if ($image_priority === 'external') {
            $image_url = !empty($external_image_url) ? $external_image_url : $featured_image_url;
        } else {
            $image_url = !empty($featured_image_url) ? $featured_image_url : $external_image_url;
        }

        $data[] = array(
            'city' => isset($m['_sign_city']) ? $m['_sign_city'] : '',
            'title' => $post->post_title,
            'venue' => isset($m['_sign_venue']) ? $m['_sign_venue'] : '',
            'coords' => [
                (float) (isset($m['_sign_lat']) ? $m['_sign_lat'] : 0),
                (float) (isset($m['_sign_lng']) ? $m['_sign_lng'] : 0)
            ],
            'img' => $image_url,
            'href' => '#',
            'cta_behavior' => isset($m['_sign_cta_behavior']) && $m['_sign_cta_behavior'] ? $m['_sign_cta_behavior'] : 'default',
            'cta_url' => isset($m['_sign_cta_url']) ? $m['_sign_cta_url'] : '',
            'link' => get_permalink($post_id)
        );
    }
    return $data;
}

// REST API endpoint for proxy search (Fixes CORS)
add_action( 'rest_api_init', function () {
    register_rest_route( 'osm/v1', '/proxy-search', array(
        'methods' => 'GET',
        'callback' => 'osm_proxy_search_callback',
        'permission_callback' => '__return_true'
    ) );
} );

function osm_proxy_search_callback( $request ) {
    $search_query = $request->get_param( 'q' );
    
    if ( empty( $search_query ) ) {
        return new WP_Error( 'missing_term', 'Search term is missing', array( 'status' => 400 ) );
    }

    $url = 'https://nominatim.openstreetmap.org/search?format=json&q=' . urlencode( $search_query ) . '&limit=1';
    
    $args = array(
        'headers' => array(
             // User-Agent is REQUIRED by Nominatim Usage Policy
            'User-Agent' => 'WordPress OpenStreetMap Plugin/1.0; ' . home_url()
        )
    );

    $response = wp_remote_get( $url, $args );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body );

    return new WP_REST_Response( $data, 200 );
}

