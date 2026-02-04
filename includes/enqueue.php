<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function open_street_map_enqueue_scripts() {
    // Enqueue MapLibre GL JS
    wp_enqueue_style( 'maplibre-gl-css', 'https://unpkg.com/maplibre-gl@1.15.2/dist/maplibre-gl.css' );
    wp_enqueue_script( 'maplibre-gl-js', 'https://unpkg.com/maplibre-gl@1.15.2/dist/maplibre-gl.js', array(), '1.15.2', true );
    
    // Enqueue Fuse.js for fuzzy search
    wp_enqueue_script( 'fuse-js', 'https://cdn.jsdelivr.net/npm/fuse.js@6.6.2', array(), '6.6.2', true );

    // Enqueue Plugin Styles and Scripts
    wp_enqueue_style( 'open-street-map-style', plugin_dir_url( __FILE__ ) . '../css/style.css' );
    wp_enqueue_style( 'google-fonts-material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200', array(), null );
    wp_enqueue_script( 'open-street-map-main', plugin_dir_url( __FILE__ ) . '../js/main.js', array( 'maplibre-gl-js', 'fuse-js' ), '1.0', true );

    // Pass data to JavaScript
    wp_localize_script( 'open-street-map-main', 'plugin_vars', array(
        'asset_path' => plugin_dir_url( __FILE__ ) . '../assets/',
        'cities_url' => get_rest_url( null, 'osm/v1/cities' ),
        'signs_url' => get_rest_url( null, 'osm/v1/signs' ),
        'proxy_url' => get_rest_url( null, 'osm/v1/proxy-search' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'open_street_map_enqueue_scripts' );

function osm_enqueue_admin_scripts($hook) {
    if ($hook !== 'openstreetmap_page_osm-settings') {
        return;
    }
    wp_enqueue_style( 'osm-admin-style', plugin_dir_url( __FILE__ ) . '../css/admin-style.css' );
}
add_action( 'admin_enqueue_scripts', 'osm_enqueue_admin_scripts' );
