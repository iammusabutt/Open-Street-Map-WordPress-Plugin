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