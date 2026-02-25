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

    // Determine version for cache busting
    $disable_cache = get_option('osm_disable_asset_cache') === 'yes';
    $version = $disable_cache ? time() : '1.0.2';

    // Enqueue Plugin Styles and Scripts
    wp_enqueue_style( 'open-street-map-style', plugin_dir_url( __FILE__ ) . '../css/style.css', array(), $version );
    wp_enqueue_style( 'google-fonts-material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200', array(), null );
    wp_enqueue_script( 'open-street-map-main', plugin_dir_url( __FILE__ ) . '../js/main.js', array( 'maplibre-gl-js', 'fuse-js' ), $version, true );

    // Pass data to JavaScript
    wp_localize_script( 'open-street-map-main', 'plugin_vars', array(
        'asset_path' => plugin_dir_url( __FILE__ ) . '../assets/',
        'cities_url' => get_rest_url( null, 'osm/v1/cities' ),
        'signs_url' => get_rest_url( null, 'osm/v1/signs' ),
        'proxy_url' => get_rest_url( null, 'osm/v1/proxy-search' ),
        'colors' => array(
            'popup_bg' => get_option('osm_popup_bg_color', '#ffffff'),
            'popup_btn_bg' => get_option('osm_popup_btn_bg_color', '#007bff'),
            'popup_btn_text' => get_option('osm_popup_btn_text_color', '#ffffff'),
            'popup_text' => get_option('osm_popup_text_color', '#1a1a1a'),
            'bubble_color' => get_option('osm_bubble_color', '#ff3e86'),
        ),
        'cta' => array(
            'default_url' => get_option('osm_default_cta_url', ''),
            'global_disable' => get_option('osm_disable_cta_button', 'no'),
            'button_text' => get_option('osm_popup_button_text', 'Log in to get started'),
        ),
        'map_layer' => get_option('osm_map_layer', 'standard'),
        'enable_image_lightbox' => get_option('osm_enable_image_lightbox', 'yes'),
        'enable_title_link' => get_option('osm_enable_title_link', 'yes'),
        'zoom_speed' => get_option('osm_zoom_speed', '12'),
        'sign_zoom_threshold' => get_option('osm_sign_zoom_threshold', '4.5'),
    ) );
}
add_action( 'wp_enqueue_scripts', 'open_street_map_enqueue_scripts' );

function osm_enqueue_admin_scripts($hook) {
    $screen = get_current_screen();
    
    // Check if we are on the OSM settings page OR on the 'sign' or 'city' post edit page
    $is_settings_page = ($hook === 'openstreetmap_page_osm-settings');
    $is_post_edit_page = ($screen && ($screen->post_type === 'sign' || $screen->post_type === 'city') && ($hook === 'post.php' || $hook === 'post-new.php'));

    if ( ! $is_settings_page && ! $is_post_edit_page ) {
        return;
    }
    wp_enqueue_style( 'osm-admin-style', plugin_dir_url( __FILE__ ) . '../css/admin-style.css' );
    wp_enqueue_style( 'wp-color-picker' );
    
    $admin_js_path = plugin_dir_path( __FILE__ ) . '../admin/js/admin.js';
    
    // Determine version for cache busting (same as frontend)
    $disable_cache = get_option('osm_disable_asset_cache') === 'yes';
    
    // Force version 1.0.8 or allow disable cache
    if ($disable_cache) {
        $admin_js_ver = time();
    } else {
        $admin_js_ver = '1.0.8'; // Bumping version manually to force update
    }
    
    wp_enqueue_script( 'osm-admin-script', plugin_dir_url( __FILE__ ) . '../admin/js/admin.js', array( 'jquery', 'wp-color-picker' ), $admin_js_ver, true );
    wp_localize_script( 'osm-admin-script', 'osm_admin_vars', array(
        'nonce' => wp_create_nonce( 'osm_ajax_nonce' )
    ) );
}
add_action( 'admin_enqueue_scripts', 'osm_enqueue_admin_scripts' );
