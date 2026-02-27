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


// REST API endpoint for logging searches
add_action( 'rest_api_init', function () {
    register_rest_route( 'osm/v1', '/log-search', array(
        'methods' => 'POST',
        'callback' => 'osm_log_search_callback',
        'permission_callback' => '__return_true'
    ) );
} );

function osm_log_search_callback( $request ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'osm_searches';
    
    // Check if table exists
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return new WP_Error( 'missing_table', 'Searches table does not exist', array('status' => 500) );
    }

    $query = sanitize_text_field( $request->get_param('query') );
    $status = sanitize_text_field( $request->get_param('status') );
    $source = sanitize_text_field( $request->get_param('source') );
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    
    if ( empty($query) ) {
        return new WP_Error( 'missing_param', 'Query is required', array('status' => 400) );
    }
    
    $wpdb->insert(
        $table_name,
        array(
            'time' => current_time('mysql'),
            'search_query' => $query,
            'found_status' => $status,
            'source' => $source,
            'ip_address' => $ip
        )
    );
    
    return new WP_REST_Response( array('success' => true), 200 );
}

// REST API endpoint for popular searches
add_action( 'rest_api_init', function () {
    register_rest_route( 'osm/v1', '/popular-searches', array(
        'methods' => 'GET',
        'callback' => 'osm_get_popular_searches_callback',
        'permission_callback' => '__return_true'
    ) );
} );

function osm_get_popular_searches_callback( $request ) {
    global $wpdb;

    $enable_popular_search = get_option('osm_enable_popular_search', 'yes');
    if ($enable_popular_search !== 'yes') {
        return new WP_REST_Response( array(), 200 );
    }

    $limit = (int) get_option('osm_popular_searches_count', 3);
    $timeframe = get_option('osm_popular_search_timeframe', 'this_month');
    $statuses = get_option('osm_popular_search_statuses', array('found'));
    $sources = get_option('osm_popular_search_sources', array()); // empty means all

    $table_name = $wpdb->prefix . 'osm_searches';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return new WP_REST_Response( array(), 200 );
    }

    $where_clause = "WHERE 1=1";

    // Timeframe filter
    switch ($timeframe) {
        case 'this_week':
            $where_clause .= " AND YEARWEEK(time, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'last_week':
            $where_clause .= " AND YEARWEEK(time, 1) = YEARWEEK(CURDATE() - INTERVAL 1 WEEK, 1)";
            break;
        case 'this_month':
            $where_clause .= " AND YEAR(time) = YEAR(CURDATE()) AND MONTH(time) = MONTH(CURDATE())";
            break;
        case 'last_month':
            $where_clause .= " AND YEAR(time) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(time) = MONTH(CURDATE() - INTERVAL 1 MONTH)";
            break;
        case 'this_year':
            $where_clause .= " AND YEAR(time) = YEAR(CURDATE())";
            break;
        case 'last_year':
            $where_clause .= " AND YEAR(time) = YEAR(CURDATE()) - 1";
            break;
        default:
            $where_clause .= " AND YEAR(time) = YEAR(CURDATE()) AND MONTH(time) = MONTH(CURDATE())"; // default this_month
            break;
    }

    // Status filter
    if (!empty($statuses) && is_array($statuses)) {
        $escaped_statuses = array_map('esc_sql', $statuses);
        $status_list = "'" . implode("','", $escaped_statuses) . "'";
        $where_clause .= " AND found_status IN ($status_list)";
    }

    // Source filter
    if (!empty($sources) && is_array($sources)) {
        $escaped_sources = array_map('esc_sql', $sources);
        $source_list = "'" . implode("','", $escaped_sources) . "'";
        $where_clause .= " AND source IN ($source_list)";
    }

    $sql = $wpdb->prepare("
        SELECT LOWER(search_query) as query, COUNT(*) as count 
        FROM $table_name 
        $where_clause 
        GROUP BY LOWER(search_query) 
        ORDER BY count DESC 
        LIMIT %d
    ", $limit);

    // Error log for debugging
    // error_log('OSM Popular Searches SQL: ' . $sql);

    $results = $wpdb->get_results($sql);

    $popular_searches = array();
    foreach ($results as $row) {
        if (!empty($row->query)) {
            $popular_searches[] = $row->query;
        }
    }

    return new WP_REST_Response( $popular_searches, 200 );
}

