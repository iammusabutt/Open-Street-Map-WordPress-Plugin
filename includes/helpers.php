<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function osm_get_pins() {
    $pins_dir = plugin_dir_path( __FILE__ ) . '../assets/pins/';
    $pins_url = plugin_dir_url( __FILE__ ) . '../assets/pins/';
    $files = scandir($pins_dir);
    $pins = array();

    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'svg') {
            $pins[] = array(
                'name' => $file,
                'venue' => pathinfo($file, PATHINFO_FILENAME),
                'url' => $pins_url . $file
            );
        }
    }
    return $pins;
}
