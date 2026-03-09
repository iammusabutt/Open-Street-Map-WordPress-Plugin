<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once plugin_dir_path( __FILE__ ) . '../includes/helpers.php';

// Create Admin Menu
function osm_admin_menu() {
    add_menu_page(
        'OpenStreetMap',
        'OpenStreetMap',
        'manage_options',
        'osm-main-menu',
        'osm_main_page',
        'dashicons-location-alt',
        20
    );
}
add_action( 'admin_menu', 'osm_admin_menu' );

function osm_admin_submenu() {
    add_submenu_page(
        'osm-main-menu',
        'Settings',
        'Settings',
        'manage_options',
        'osm-settings',
        'osm_settings_page_html'
    );
}
add_action('admin_menu', 'osm_admin_submenu');



function osm_allow_svg_upload_mimes($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}

function osm_settings_page_html() {
    ?>
    <div id="osm-toaster-container"></div>
    <style>
        #osm-pins-panel .pin-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        #osm-pins-panel .pin-item img {
            width: 32px;
            height: 32px;
            margin-right: 15px;
        }
        #osm-pins-panel .pin-item .pin-name {
            font-weight: bold;
            flex-grow: 1;
        }
        #osm-pins-panel .pin-item .pin-venue {
            font-style: italic;
            color: #777;
            margin-right: 15px;
        }
        #osm-pins-panel .pin-item .pin-venue {
            font-style: italic;
            color: #777;
            margin-right: 15px;
        }
        /* Map Layer Swatches */
        .layer-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .layer-option {
            border: 2px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .layer-option:hover {
            border-color: #2271b1;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .layer-option.selected {
            border-color: #2271b1;
            box-shadow: 0 0 0 2px #2271b1;
        }
        .layer-option img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }
        .layer-option .layer-name {
            padding: 10px;
            text-align: center;
            font-weight: 600;
            background: #fff;
            border-top: 1px solid #eee;
        }
        .layer-option input[type="radio"] {
            display: none;
        }
        /* Modern Range Slider Input */
        .osm-range-slider-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
            max-width: 300px;
        }
        .osm-range-slider {
            -webkit-appearance: none;
            width: 100%;
            height: 6px;
            background: #e1e1e1;
            border-radius: 5px;
            outline: none;
            padding: 0;
            margin: 0;
        }
        .osm-range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #2271b1;
            cursor: pointer;
            transition: background .15s ease-in-out;
        }
        .osm-range-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border: 0;
            border-radius: 50%;
            background: #2271b1;
            cursor: pointer;
            transition: background .15s ease-in-out;
        }
        .osm-range-slider::-webkit-slider-thumb:hover {
            background: #135e96;
        }
        .osm-range-slider::-moz-range-thumb:hover {
            background: #135e96;
        }
        .osm-range-value-display {
            display: inline-block;
            min-width: 30px;
            text-align: center;
            font-weight: bold;
            color: #2271b1;
            background: #f0f0f1;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
    </style>
    <div class="wrap osm-admin-wrapper">
        <div class="osm-admin-header">
            <h1><span class="dashicons dashicons-location-alt" style="font-size: 30px; margin-right: 10px; vertical-align: middle;"></span>OpenStreetMap</h1>
            <div>
                <span class="version-text" style="color: #666; font-weight: 500;">Version 1.0.7</span>
            </div>
        </div>
        <div class="osm-admin-content-wrap">
            <div class="osm-admin-tabs">
                <ul>
                    <li>
                        <a href="#dashboard" class="nav-tab nav-tab-active">
                            <span class="dashicons dashicons-dashboard"></span>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="#settings" class="nav-tab">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <span>General Settings</span>
                        </a>
                    </li>
                    <li>
                        <a href="#shortcodes" class="nav-tab">
                            <span class="dashicons dashicons-editor-code"></span>
                            <span>Shortcodes</span>
                        </a>
                    </li>
                    <li>
                        <a href="#search" class="nav-tab">
                            <span class="dashicons dashicons-search"></span>
                            <span>Search</span>
                        </a>
                    </li>
                    <li>
                        <a href="#pins" class="nav-tab">
                            <span class="dashicons dashicons-location"></span>
                            <span>Pins</span>
                        </a>
                    </li>
                    <li>
                        <a href="#colors" class="nav-tab">
                            <span class="dashicons dashicons-art"></span>
                            <span>Colors</span>
                        </a>
                    </li>
                    <li>
                        <a href="#mapbox" class="nav-tab">
                            <span class="dashicons dashicons-location-alt"></span>
                            <span>Map Box</span>
                        </a>
                    </li>
                    <li>
                        <a href="#layers" class="nav-tab">
                            <span class="dashicons dashicons-images-alt2"></span>
                            <span>Map Layers</span>
                        </a>
                    </li>
                    <li>
                        <a href="#import" class="nav-tab">
                            <span class="dashicons dashicons-upload"></span>
                            <span>Import Tool</span>
                        </a>
                    </li>
                    <?php if (get_option('osm_developer_mode') === 'yes') : ?>
                    <li>
                        <a href="#developer" class="nav-tab">
                            <span class="dashicons dashicons-hammer"></span>
                            <span>Developer</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
                <div id="dashboard" class="osm-admin-tab-pane active">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 class="osm-tab-title" style="margin-bottom: 0;">Analytics Dashboard</h2>
                        <select id="osm-dashboard-date-filter">
                            <option value="all_time">All Time</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="this_week">This Week</option>
                            <option value="last_week">Last Week</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="last_30_days">Last 30 Days</option>
                            <option value="this_year">This Year</option>
                            <option value="last_year">Last Year</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                            <h3 style="margin-top: 0;">Total Searches</h3>
                            <p id="osm-stat-total" style="font-size: 24px; font-weight: bold; margin: 0; color: #2271b1;">...</p>
                        </div>
                        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                            <h3 style="margin-top: 0;">Found Rate</h3>
                            <p id="osm-stat-found" style="font-size: 24px; font-weight: bold; margin: 0; color: #46b450;">...</p>
                        </div>
                    </div>

                    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-bottom: 20px;">
                        <h3 style="margin-top: 0;">Searches Over Time</h3>
                        <div style="height: 300px; width: 100%;">
                            <canvas id="osm-chart-timeline"></canvas>
                        </div>
                    </div>

                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                            <h3 style="margin-top: 0;">Search Statuses</h3>
                            <div style="height: 250px; width: 100%; position: relative;">
                                <canvas id="osm-chart-status"></canvas>
                            </div>
                        </div>
                        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; flex: 1; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                            <h3 style="margin-top: 0;">Data Sources</h3>
                            <div style="height: 250px; width: 100%; position: relative;">
                                <canvas id="osm-chart-sources"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <h3 class="title">Recent Searches</h3>
                    <table class="wp-list-table widefat fixed striped" style="margin-bottom: 40px;">
                        <thead>
                            <tr>
                                <th>Query</th>
                                <th>Count</th>
                                <th>Last Searched</th>
                                <th>Status</th>
                                <th>Source</th>
                            </tr>
                        </thead>
                        <tbody id="osm-recent-searches">
                            <tr><td colspan="5">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div id="settings" class="osm-admin-tab-pane">
                    <h2 class="osm-tab-title">General Settings</h2>
                    <form id="osm-settings-form">
                        <input type="hidden" name="osm_settings_group" value="general">
                        
                        <!-- Image Settings -->
                        <h3>Image Settings</h3>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Use External Image</th>
                                <td>
                                    <div class="switchery-wraper">
                                        <span>No</span>
                                        <label class="switchery">
                                            <input type="checkbox" id="osm_image_priority" name="osm_image_priority" value="external" <?php checked( get_option('osm_image_priority'), 'external' ); ?>>
                                            <span class="switchery-slider round"></span>
                                        </label>
                                        <span>Yes</span>
                                    </div>
                                    <p class="description">If this option is switched to "Yes," the external image URL field will be prioritized over the featured image for signs.</p>
                                </td>
                            </tr>
                        </table>

                        <!-- Map Behavior Settings -->
                        <h3>Map Behavior Settings</h3>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Map Zoom Speed</th>
                                <td>
                                    <div class="osm-range-slider-wrapper">
                                        <input type="range" class="osm-range-slider" id="osm_zoom_speed_slider" min="1" max="50" step="1" value="<?php echo esc_attr( get_option('osm_zoom_speed', '12') ); ?>">
                                        <span class="osm-range-value-display" id="osm_zoom_speed_display"><?php echo esc_attr( get_option('osm_zoom_speed', '12') ); ?></span>
                                        <input type="hidden" id="osm_zoom_speed" name="osm_zoom_speed" value="<?php echo esc_attr( get_option('osm_zoom_speed', '12') ); ?>">
                                    </div>
                                    <p class="description">Slide to set the scroll-wheel zoom speed multiplier (1 = slow, 12 = fast, 50 = very fast).</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Sign Visibility Zoom Threshold</th>
                                <td>
                                    <div class="osm-range-slider-wrapper">
                                        <input type="range" class="osm-range-slider" id="osm_sign_zoom_threshold_slider" min="1" max="15" step="0.5" value="<?php echo esc_attr( get_option('osm_sign_zoom_threshold', '4.5') ); ?>">
                                        <span class="osm-range-value-display" id="osm_sign_zoom_threshold_display"><?php echo esc_attr( get_option('osm_sign_zoom_threshold', '4.5') ); ?></span>
                                        <input type="hidden" id="osm_sign_zoom_threshold" name="osm_sign_zoom_threshold" value="<?php echo esc_attr( get_option('osm_sign_zoom_threshold', '4.5') ); ?>">
                                    </div>
                                    <p class="description">Slide to set the map zoom level where city bubbles disappear and individual signs appear (lower number = signs appear earlier).</p>
                                </td>
                            </tr>
                        </table>

                        <!-- Developer Settings -->
                        <h3>Developer Settings</h3>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Enable Developer Options</th>
                                <td>
                                    <div class="switchery-wraper">
                                        <span>No</span>
                                        <label class="switchery">
                                            <input type="checkbox" id="osm_developer_mode" name="osm_developer_mode" value="yes" <?php checked( get_option('osm_developer_mode'), 'yes' ); ?>>
                                            <span class="switchery-slider round"></span>
                                        </label>
                                        <span>Yes</span>
                                    </div>
                                    <p class="description">Enable to see the Developer tab and advanced options.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Disable Asset Caching</th>
                                <td>
                                    <div class="switchery-wraper">
                                        <span>No</span>
                                        <label class="switchery">
                                            <input type="checkbox" id="osm_disable_asset_cache" name="osm_disable_asset_cache" value="yes" <?php checked( get_option('osm_disable_asset_cache'), 'yes' ); ?>>
                                            <span class="switchery-slider round"></span>
                                        </label>
                                        <span>Yes</span>
                                    </div>
                                    <p class="description">If enabled, a timestamp will be appended to script and style URLs to prevent browser caching. Useful for development.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button-primary">Save Changes</button>
                        </p>
                    </form>
                </div>

                <div id="shortcodes" class="osm-admin-tab-pane">
                    <h2 class="osm-tab-title">Shortcodes</h2>
                    <p class="description" style="margin-bottom: 20px; font-size: 14px;">Use the following shortcodes to display the map on your site's posts, pages, or widgets.</p>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">
                                <label style="font-weight: 600;">Main Map Shortcode</label>
                            </th>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input type="text" readonly value="[open_street_map]" style="font-family: monospace; font-size: 14px; padding: 5px 10px; background: #f0f0f1; border: 1px solid #ddd; width: 250px; color: #d63638;" id="osm_main_shortcode_input">
                                    <button type="button" class="button button-secondary" onclick="navigator.clipboard.writeText('[open_street_map]'); var btn = this; var oldHtml = btn.innerHTML; btn.innerHTML = '<span class=\'dashicons dashicons-yes\' style=\'margin-top: 4px;\'></span> Copied!'; setTimeout(function(){ btn.innerHTML = oldHtml; }, 2000);" title="Copy to clipboard" style="display: flex; align-items: center; gap: 4px; padding: 0 10px;">
                                        <span class="dashicons dashicons-clipboard" style="margin-top: 4px;"></span> Copy
                                    </button>
                                </div>
                                <p class="description">Copy and paste this shortcode onto any page where you want the map to appear.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="search" class="osm-admin-tab-pane">
                    <h2 class="osm-tab-title">Search Settings</h2>
                    <form id="osm-settings-form-search">
                        <input type="hidden" name="osm_settings_group" value="search">
                        
                        <!-- Popular Search Settings -->
                        <h3>Popular Searches Widget</h3>
                        <p class="description" style="margin-bottom: 20px; font-size: 14px;">Configure the popular searches list to be shown as soon as users start typing in the map autocomplete.</p>
                        
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Enable Popular Searches</th>
                                <td>
                                    <div class="switchery-wraper">
                                        <span>No</span>
                                        <label class="switchery">
                                            <input type="checkbox" id="osm_enable_popular_search" name="osm_enable_popular_search" value="yes" <?php checked( get_option('osm_enable_popular_search', 'yes'), 'yes' ); ?>>
                                            <span class="switchery-slider round"></span>
                                        </label>
                                        <span>Yes</span>
                                    </div>
                                    <p class="description">If enabled, the top popular searches will be shown automatically during typing.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Number of Popular Searches</th>
                                <td>
                                    <input type="number" name="osm_popular_searches_count" class="regular-text" style="max-width: 100px; text-align: center; border-radius: 4px;" value="<?php echo esc_attr( get_option('osm_popular_searches_count', 3) ); ?>" min="1" max="20" />
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Based On Timeframe</th>
                                <td>
                                    <select name="osm_popular_search_timeframe" style="padding: 4px 8px; border-radius: 4px;">
                                        <?php 
                                        $tf = get_option('osm_popular_search_timeframe', 'this_month'); 
                                        $options = array(
                                            'this_week' => 'This Week',
                                            'last_week' => 'Last Week',
                                            'this_month' => 'This Month',
                                            'last_month' => 'Last Month',
                                            'this_year' => 'This Year',
                                            'last_year' => 'Last Year',
                                        );
                                        foreach ($options as $val => $label) {
                                            $selected = ($tf === $val) ? 'selected' : '';
                                            echo "<option value='{$val}' {$selected}>{$label}</option>";
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Criteria Section</th>
                                <td>
                                    <?php
                                    global $wpdb;
                                    $table_name = $wpdb->prefix . 'osm_searches';
                                    
                                    // Make sure table exists
                                    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                                        $statuses = $wpdb->get_col("SELECT DISTINCT found_status FROM $table_name WHERE found_status != ''");
                                        $sources  = $wpdb->get_col("SELECT DISTINCT source FROM $table_name WHERE source != ''");
                                    } else {
                                        $statuses = array('found', 'not_found');
                                        $sources  = array('form', 'map_search');
                                    }
                                    if (empty($statuses)) $statuses = array('found', 'not_found'); // defaults
                                    if (empty($sources)) $sources = array('form', 'map_search');
                                    
                                    $saved_statuses = get_option('osm_popular_search_statuses', array('found'));
                                    $saved_sources  = get_option('osm_popular_search_sources', array());
                                    ?>
                                    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                                        <p style="font-weight:600; margin-top: 0; margin-bottom:10px;">Qualifying Statuses:</p>
                                        <div style="display:flex; gap:15px; flex-wrap:wrap; margin-bottom:20px;">
                                            <?php foreach ($statuses as $st): ?>
                                                <?php if (strtolower($st) === 'none') continue; ?>
                                                <label style="display:inline-flex; align-items:center; gap:8px; cursor: pointer;">
                                                    <input type="checkbox" name="osm_popular_search_statuses[]" value="<?php echo esc_attr($st); ?>" <?php echo in_array($st, $saved_statuses) ? 'checked' : ''; ?>>
                                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $st))); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>

                                        <p style="font-weight:600; margin-bottom:10px; padding-top: 15px; border-top: 1px solid #eee;">Qualifying Sources:</p>
                                        <div style="display:flex; gap:15px; flex-wrap:wrap;">
                                            <?php foreach ($sources as $sr): ?>
                                                <?php if (strtolower($sr) === 'none') continue; ?>
                                                <label style="display:inline-flex; align-items:center; gap:8px; cursor: pointer;">
                                                    <input type="checkbox" name="osm_popular_search_sources[]" value="<?php echo esc_attr($sr); ?>" <?php echo in_array($sr, $saved_sources) ? 'checked' : ''; ?>>
                                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $sr))); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <p class="description" style="margin-top: 10px;">If no source is selected, searches from all sources will be considered. Only selected statuses will be included.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button-primary">Save Search Settings</button>
                        </p>
                    </form>
                </div>


                <div id="colors" class="osm-admin-tab-pane">
                    <h2 class="osm-tab-title">Color Settings</h2>
                    <form id="osm-settings-form-colors">
                        <input type="hidden" name="osm_settings_group" value="colors">
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Popup Background Color</th>
                                <td>
                                    <input type="text" name="osm_popup_bg_color" class="osm-color-field" value="<?php echo esc_attr( get_option('osm_popup_bg_color', '#ffffff') ); ?>" data-default-color="#ffffff" />
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Popup Text Color</th>
                                <td>
                                    <input type="text" name="osm_popup_text_color" class="osm-color-field" value="<?php echo esc_attr( get_option('osm_popup_text_color', '#1a1a1a') ); ?>" data-default-color="#1a1a1a" />
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Button Background Color</th>
                                <td>
                                    <input type="text" name="osm_popup_btn_bg_color" class="osm-color-field" value="<?php echo esc_attr( get_option('osm_popup_btn_bg_color', '#007bff') ); ?>" data-default-color="#007bff" />
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Button Text Color</th>
                                <td>
                                    <input type="text" name="osm_popup_btn_text_color" class="osm-color-field" value="<?php echo esc_attr( get_option('osm_popup_btn_text_color', '#ffffff') ); ?>" data-default-color="#ffffff" />
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Map Bubble Color</th>
                                <td>
                                    <input type="text" name="osm_bubble_color" class="osm-color-field" value="<?php echo esc_attr( get_option('osm_bubble_color', '#ff3e86') ); ?>" data-default-color="#ff3e86" />
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button-primary">Save Colors</button>
                        </p>
                    </form>
                </div>

                <div id="mapbox" class="osm-admin-tab-pane">
                    <h2 class="osm-tab-title">Map Box Settings</h2>
                    <form id="osm-settings-form-mapbox">
                        <input type="hidden" name="osm_settings_group" value="mapbox">
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Default CTA URL</th>
                                <td>
                                    <input type="text" name="osm_default_cta_url" class="regular-text" value="<?php echo esc_attr( get_option('osm_default_cta_url', '') ); ?>" placeholder="https://example.com" />
                                    <p class="description">This URL will be used for buttons on the map popups by default.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Popup Button Text</th>
                                <td>
                                    <input type="text" name="osm_popup_button_text" class="regular-text" value="<?php echo esc_attr( get_option('osm_popup_button_text', '') ); ?>" placeholder="Log in to get started" />
                                    <p class="description">Text to display on the popup button. Leave empty for default ("Log in to get started").</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Disable Popup Button</th>
                                <td>
                                    <div class="switchery-wraper">
                                        <span>No</span>
                                        <label class="switchery">
                                            <input type="checkbox" id="osm_disable_cta_button" name="osm_disable_cta_button" value="yes" <?php checked( get_option('osm_disable_cta_button'), 'yes' ); ?>>
                                            <span class="switchery-slider round"></span>
                                        </label>
                                        <span>Yes</span>
                                    </div>
                                    <p class="description">If switched to "Yes," the CTA button will be hidden by default on all popups unless overridden by a specific sign.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Enable Image Lightbox</th>
                                <td>
                                    <div class="switchery-wraper">
                                        <span>No</span>
                                        <label class="switchery">
                                            <input type="checkbox" id="osm_enable_image_lightbox" name="osm_enable_image_lightbox" value="yes" <?php checked( get_option('osm_enable_image_lightbox', 'yes'), 'yes' ); ?>>
                                            <span class="switchery-slider round"></span>
                                        </label>
                                        <span>Yes</span>
                                    </div>
                                    <p class="description">If switched to "Yes," clicking on the image in the map popup will open it in a lightbox.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Enable Title Permalink</th>
                                <td>
                                    <div class="switchery-wraper">
                                        <span>No</span>
                                        <label class="switchery">
                                            <input type="checkbox" id="osm_enable_title_link" name="osm_enable_title_link" value="yes" <?php checked( get_option('osm_enable_title_link', 'yes'), 'yes' ); ?>>
                                            <span class="switchery-slider round"></span>
                                        </label>
                                        <span>Yes</span>
                                    </div>
                                    <p class="description">If switched to "Yes," the title in the map popup will be a clickable link to the City or Sign page.</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button-primary">Save Map Box Settings</button>
                        </p>
                    </form>
                </div>
                <!-- Map Layers Tab -->
                <div id="layers" class="osm-admin-tab-pane">
                    <h2 class="osm-tab-title">Map Layers</h2>
                    <p>Select the visual style for the map tiles.</p>
                    <form id="osm-settings-form-layers">
                        <input type="hidden" name="osm_settings_group" value="layers">
                        <div class="layer-selector">
                            <!-- Standard OSM -->
                            <label class="layer-option <?php echo (get_option('osm_map_layer', 'standard') === 'standard') ? 'selected' : ''; ?>">
                                <input type="radio" name="osm_map_layer" value="standard" <?php checked(get_option('osm_map_layer', 'standard'), 'standard'); ?>>
                                <img src="https://a.tile.openstreetmap.org/12/2073/1409.png" alt="Standard">
                                <div class="layer-name">Standard (OSM)</div>
                            </label>

                            <!-- CartoDB Positron (Light) -->
                            <label class="layer-option <?php echo (get_option('osm_map_layer') === 'cartodb_positron') ? 'selected' : ''; ?>">
                                <input type="radio" name="osm_map_layer" value="cartodb_positron" <?php checked(get_option('osm_map_layer'), 'cartodb_positron'); ?>>
                                <img src="https://a.basemaps.cartocdn.com/light_all/12/2073/1409.png" alt="CartoDB Positron">
                                <div class="layer-name">Light (Clean)</div>
                            </label>

                            <!-- CartoDB Dark Matter -->
                            <label class="layer-option <?php echo (get_option('osm_map_layer') === 'cartodb_dark') ? 'selected' : ''; ?>">
                                <input type="radio" name="osm_map_layer" value="cartodb_dark" <?php checked(get_option('osm_map_layer'), 'cartodb_dark'); ?>>
                                <img src="https://a.basemaps.cartocdn.com/dark_all/12/2073/1409.png" alt="CartoDB Dark">
                                <div class="layer-name">Dark Mode</div>
                            </label>

                            <!-- Humanitarian -->
                            <label class="layer-option <?php echo (get_option('osm_map_layer') === 'humanitarian') ? 'selected' : ''; ?>">
                                <input type="radio" name="osm_map_layer" value="humanitarian" <?php checked(get_option('osm_map_layer'), 'humanitarian'); ?>>
                                <img src="https://a.tile.openstreetmap.fr/hot/12/2073/1409.png" alt="Humanitarian">
                                <div class="layer-name">Humanitarian</div>
                            </label>

                            <!-- CyclOSM -->
                            <label class="layer-option <?php echo (get_option('osm_map_layer') === 'cyclosm') ? 'selected' : ''; ?>">
                                <input type="radio" name="osm_map_layer" value="cyclosm" <?php checked(get_option('osm_map_layer'), 'cyclosm'); ?>>
                                <img src="https://a.tile-cyclosm.openstreetmap.fr/cyclosm/12/2073/1409.png" alt="CyclOSM">
                                <div class="layer-name">CyclOSM (Bicycle)</div>
                            </label>

                            <!-- OpenTopoMap -->
                            <label class="layer-option <?php echo (get_option('osm_map_layer') === 'opentopomap') ? 'selected' : ''; ?>">
                                <input type="radio" name="osm_map_layer" value="opentopomap" <?php checked(get_option('osm_map_layer'), 'opentopomap'); ?>>
                                <img src="https://a.tile.opentopomap.org/12/2073/1409.png" alt="OpenTopoMap">
                                <div class="layer-name">OpenTopoMap</div>
                            </label>

                            <!-- CartoDB Voyager -->
                            <label class="layer-option <?php echo (get_option('osm_map_layer') === 'cartodb_voyager') ? 'selected' : ''; ?>">
                                <input type="radio" name="osm_map_layer" value="cartodb_voyager" <?php checked(get_option('osm_map_layer'), 'cartodb_voyager'); ?>>
                                <img src="https://a.basemaps.cartocdn.com/rastertiles/voyager/12/2073/1409.png" alt="Voyager">
                                <div class="layer-name">Voyager (Clean)</div>
                            </label>

                            <!-- Public Transport (ÖPNV Karte) -->
                            <label class="layer-option <?php echo (get_option('osm_map_layer') === 'public_transport') ? 'selected' : ''; ?>">
                                <input type="radio" name="osm_map_layer" value="public_transport" <?php checked(get_option('osm_map_layer'), 'public_transport'); ?>>
                                <img src="https://tile.memomaps.de/tilegen/12/2073/1409.png" alt="Public Transport">
                                <div class="layer-name">Public Transport</div>
                            </label>

                            <!-- Esri World Imagery (Satellite) -->
                            <label class="layer-option <?php echo (get_option('osm_map_layer') === 'esri_world_imagery') ? 'selected' : ''; ?>">
                                <input type="radio" name="osm_map_layer" value="esri_world_imagery" <?php checked(get_option('osm_map_layer'), 'esri_world_imagery'); ?>>
                                <img src="https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/12/1409/2073" alt="Satellite">
                                <div class="layer-name">Satellite (Esri)</div>
                            </label>

                            <!-- Esri World Street Map -->
                            <label class="layer-option <?php echo (get_option('osm_map_layer') === 'esri_world_street_map') ? 'selected' : ''; ?>">
                                <input type="radio" name="osm_map_layer" value="esri_world_street_map" <?php checked(get_option('osm_map_layer'), 'esri_world_street_map'); ?>>
                                <img src="https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/12/1409/2073" alt="Esri Street">
                                <div class="layer-name">Esri Street Map</div>
                            </label>
                        </div>
                        <p class="submit">
                            <button type="submit" class="button-primary">Save Changes</button>
                        </p>
                    </form>
                </div>
                <div id="import" class="osm-admin-tab-pane">
                    <div id="osm-import-panel">
                        <h2 class="osm-tab-title">Import Tool</h2>

                        <div class="import-section">
                            <h3>Import Cities</h3>
                            <p>CSV column order: <strong>Name, Latitude, Longitude, Count, Venue</strong></p>
                            <form class="osm-upload-form" method="post" enctype="multipart/form-data" data-import-type="cities">
                                <input type="hidden" name="import_type" value="cities">
                                <p>
                                    <label>CSV File:</label>
                                    <div class="custom-file-upload-wrapper">
                                        <label for="csv_file_cities" class="custom-file-upload-label">Choose CSV File</label>
                                        <input type="file" name="csv_file" id="csv_file_cities" class="custom-file-upload-input" accept=".csv">
                                        <span class="custom-file-upload-filename">No file chosen</span>
                                    </div>
                                </p>
                                <p class="submit">
                                    <button type="submit" class="button-primary osm-upload-button" disabled>Upload Cities CSV</button>
                                    <span class="spinner"></span>
                                </p>
                            </form>
                            <div class="osm-uploaded-files-list"></div>
                        </div>

                        <hr>

                        <div class="import-section">
                            <h3>Import Signs</h3>
                            <p>CSV column order: <strong>Title, Latitude, Longitude, Venue, City Name, Image URL</strong></p>
                            <form class="osm-upload-form" method="post" enctype="multipart/form-data" data-import-type="signs">
                                <input type="hidden" name="import_type" value="signs">
                                <p>
                                    <label>CSV File:</label>
                                    <div class="custom-file-upload-wrapper">
                                        <label for="csv_file_signs" class="custom-file-upload-label">Choose CSV File</label>
                                        <input type="file" name="csv_file" id="csv_file_signs" class="custom-file-upload-input" accept=".csv">
                                        <span class="custom-file-upload-filename">No file chosen</span>
                                    </div>
                                </p>
                                <p class="submit">
                                    <button type="submit" class="button-primary osm-upload-button" disabled>Upload Signs CSV</button>
                                    <span class="spinner"></span>
                                </p>
                            </form>
                            <div class="osm-uploaded-files-list"></div>
                        </div>
                    </div>
                </div>
                <!-- Developer Tab -->
                <?php if (get_option('osm_developer_mode') === 'yes') : ?>
                <div id="developer" class="osm-admin-tab-pane">
                    <h2 class="osm-tab-title">Developer Tools</h2>
                    <p class="description">Advanced maintenance and bulk operations. <strong style="color: #b32d2e;">Use with caution as these actions modify the database directly.</strong></p>
                    
                    <!-- TABLE 1: DELETE ALL -->
                    <h3 style="margin-top: 30px;">Bulk Delete</h3>
                    <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Tool</th>
                                <th>Description</th>
                                <th style="width: 25%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Delete All Signs</strong></td>
                                <td>Permanently delete ALL Sign posts from the database. This cannot be undone.</td>
                                <td>
                                    <button type="button" id="osm-delete-all-signs-btn" class="button button-action" style="color: #b32d2e; border-color: #b32d2e;">Execute</button>
                                    <span class="spinner" id="osm-delete-all-signs-spinner" style="float: none; margin: 4px 10px 0;"></span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Delete All Cities</strong></td>
                                <td>Permanently delete ALL City posts from the database. This cannot be undone.</td>
                                <td>
                                    <button type="button" id="osm-delete-all-cities-btn" class="button button-action" style="color: #b32d2e; border-color: #b32d2e;">Execute</button>
                                    <span class="spinner" id="osm-delete-all-cities-spinner" style="float: none; margin: 4px 10px 0;"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div id="osm-bulk-action-log" style="margin-top: 10px; max-width: 1000px; max-height: 200px; overflow-y: auto; background: #fff; border: 1px solid #ccd0d4; padding: 10px; display: none; font-family: monospace; white-space: pre-wrap;"></div>

                    <!-- TABLE 2: REMOVE DUPLICATES -->
                    <h3 style="margin-top: 30px;">Remove Duplicates</h3>
                    <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Tool</th>
                                <th>Description</th>
                                <th style="width: 25%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Remove Duplicate Cities</strong></td>
                                <td>Remove duplicate cities with the same name. Keeps the oldest post and reassigns signs to the preserved city.</td>
                                <td>
                                    <button type="button" id="osm-remove-cities-btn" class="button button-secondary">Execute</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Remove Duplicate Signs</strong></td>
                                <td>Remove duplicate signs with the same title. Keeps the oldest record and deletes the newer ones.</td>
                                <td>
                                    <button type="button" id="osm-remove-signs-btn" class="button button-secondary">Execute</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Simulate Duplicates Removal</strong></td>
                                <td>Check this box to do a "Dry Run" of the duplicate removal tools above. It simulates the database queries without actually deleting the records.</td>
                                <td>
                                    <label style="display: flex; align-items: center; gap: 8px;">
                                        <input type="checkbox" id="osm-dry-run">
                                        <span>Enable Dry Run</span>
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div style="margin-top: 10px; max-width: 1000px; display: flex; align-items: center; justify-content: space-between;">
                         <span class="spinner" id="osm-duplicates-spinner" style="float: none; display: none;"></span>
                         <button type="button" id="osm-clear-log-btn" class="button-link" style="display: none;">Clear Logs</button>
                    </div>
                    <div id="osm-log-container" style="background: #f0f0f1; border: 1px solid #ccc; max-width: 1000px; padding: 10px; height: 300px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; display: none;"></div>

                    <!-- TABLE 3: BUBBLE SYNC -->
                    <h3 style="margin-top: 30px;">Bubble Sync</h3>
                    <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Tool</th>
                                <th>Description</th>
                                <th style="width: 25%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Bubble Sync</strong></td>
                                <td>Calculate the number of signs assigned to each city and update its Display Count field.</td>
                                <td>
                                    <button type="button" id="osm-bubble-sync-btn" class="button button-action" style="color: #2271b1; border-color: #2271b1;">Execute</button>
                                    <span class="spinner" id="osm-bubble-sync-spinner" style="float: none; margin: 4px 10px 0;"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div id="osm-bubble-sync-log" style="margin-top: 10px; max-width: 1000px; max-height: 200px; overflow-y: auto; background: #fff; border: 1px solid #ccd0d4; padding: 10px; display: none; font-family: monospace; white-space: pre-wrap;"></div>

                    <div class="import-section" style="margin: 30px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
                        <h3 style="margin-top: 0;">Debug Info</h3>
                        <p style="margin-bottom: 5px;"><strong>Plugin Version:</strong> 1.0.7</p>
                        <p style="margin-bottom: 0;"><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                <div id="pins" class="osm-admin-tab-pane">
                    <h2 class="osm-tab-title">Manage Pins</h2>
                    <div id="osm-pins-panel">
                        <div id="osm-pin-upload-section">
                            <h3 class="osm-tab-title">Upload New Pin</h3>
                            <form id="osm-upload-pin-form" method="post" enctype="multipart/form-data">
                                <p>
                                    <label>Pin File (must be .svg):</label>
                                    <div class="custom-file-upload-wrapper">
                                        <label for="pin_file" class="custom-file-upload-label">Choose SVG Pin</label>
                                        <input type="file" name="pin_file" id="pin_file" class="custom-file-upload-input" accept=".svg">
                                        <span class="custom-file-upload-filename">No file chosen</span>
                                    </div>
                                </p>
                                <p class="submit">
                                    <button type="submit" class="button-primary">Upload Pin</button>
                                    <span class="spinner"></span>
                                </p>
                            </form>
                        </div>
                        <div id="osm-pin-list-section">
                            <h3 class="osm-tab-title">Existing Pins</h3>
                            <div id="osm-pin-list">
                                <?php
                                $pins = osm_get_pins();
                                if (!empty($pins)) {
                                    foreach ($pins as $pin) {
                                        ?>
                                        <div class="pin-item" data-pin-name="<?php echo esc_attr($pin['name']); ?>">
                                            <img src="<?php echo esc_url($pin['url']); ?>" alt="<?php echo esc_attr($pin['venue']); ?>">
                                            <span class="pin-name"><?php echo esc_html($pin['name']); ?></span>
                                            <span class="pin-venue">Venue: "<?php echo esc_html($pin['venue']); ?>"</span>
                                            <button class="button-secondary delete-pin">Delete</button>
                                        </div>
                                        <?php
                                    }
                                } else {
                                    echo '<p>No pins found.</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
}

function osm_main_page() {
    ?>
    <div class="wrap">
        <h1>OpenStreetMap</h1>
        <p>Welcome to the OpenStreetMap plugin. You can manage your cities and signs from the sidebar menu.</p>
    </div>
    <?php
}
