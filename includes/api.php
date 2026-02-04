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
    $args = array(
        'post_type' => 'city',
        'posts_per_page' => -1,
    );
    $posts = get_posts( $args );
    $data = array();
    foreach ( $posts as $post ) {
        $data[] = array(
            'name' => $post->post_title,
            'coords' => [
                (float) get_post_meta( $post->ID, '_city_lng', true ),
                (float) get_post_meta( $post->ID, '_city_lat', true )
            ],
            'count' => (int) get_post_meta( $post->ID, '_city_count', true ),
            'venue' => get_post_meta( $post->ID, '_city_venue', true ),
            'img' => get_the_post_thumbnail_url( $post->ID, 'full' ),
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
    $args = array(
        'post_type' => 'sign',
        'posts_per_page' => -1,
    );
    $posts = get_posts( $args );
    $data = array();
    $image_priority = get_option('osm_image_priority', 'featured');

    foreach ( $posts as $post ) {
        $external_image_url = get_post_meta( $post->ID, '_sign_image_url', true );
        $featured_image_url = get_the_post_thumbnail_url( $post->ID, 'full' );
        $image_url = '';

        if ($image_priority === 'external') {
            $image_url = !empty($external_image_url) ? $external_image_url : $featured_image_url;
        } else {
            $image_url = !empty($featured_image_url) ? $featured_image_url : $external_image_url;
        }

        $data[] = array(
            'city' => get_post_meta( $post->ID, '_sign_city', true ),
            'title' => $post->post_title,
            'venue' => get_post_meta( $post->ID, '_sign_venue', true ),
            'coords' => [
                (float) get_post_meta( $post->ID, '_sign_lat', true ),
                (float) get_post_meta( $post->ID, '_sign_lng', true )
            ],
            'img' => $image_url,
            'href' => '#',
        );
    }
    return $data;
}
