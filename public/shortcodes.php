<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function open_street_map_shortcode() {
    ob_start();
    ?>
    <div class="map-container">
        <div id="map"></div>
        <div class="search-box">
            <div class="search-bar">
                <input id="search" type="text" placeholder="Enter a city (e.g. New York, Chicago)" autocomplete="off">
                <button class="search-btn" onclick="searchCity()"><span class="material-symbols-outlined">search</span></button>
            </div>
            <ul id="suggestions"></ul>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'open_street_map', 'open_street_map_shortcode' );
