<?php
/**
 * Plugin Name: Open Street Map
 * Description: A plugin to display an interactive map on the homepage.
 * Version: 1.0
 * Author: Musa Naveed
 * Author URI: mailto:iammusabutt@gmail.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include files
require_once plugin_dir_path( __FILE__ ) . 'includes/enqueue.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/post-types.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/api.php';
require_once plugin_dir_path( __FILE__ ) . 'public/shortcodes.php';

if ( is_admin() ) {
    require_once plugin_dir_path( __FILE__ ) . 'admin/admin.php';
    require_once plugin_dir_path( __FILE__ ) . 'admin/ajax.php';
}

function osm_create_tables() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'osm_searches';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        search_query varchar(255) NOT NULL,
        found_status varchar(50) NOT NULL,
        source varchar(50) NOT NULL,
        ip_address varchar(100) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    update_option('osm_db_version', '1.1');
}

register_activation_hook( __FILE__, 'osm_create_tables' );

function osm_check_version() {
    if (get_option('osm_db_version') !== '1.1') {
        osm_create_tables();
    }
}
add_action('plugins_loaded', 'osm_check_version');