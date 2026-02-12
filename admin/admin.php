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
                        <a href="#settings" class="nav-tab nav-tab-active">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <span>General Settings</span>
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
                        <a href="#import" class="nav-tab">
                            <span class="dashicons dashicons-upload"></span>
                            <span>Import Tool</span>
                        </a>
                    </li>
                    <li>
                        <a href="#layers" class="nav-tab">
                            <span class="dashicons dashicons-images-alt2"></span>
                            <span>Map Layers</span>
                        </a>
                    </li>
                </ul>
            </div>
                <div id="settings" class="osm-admin-tab-pane active">
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

                        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">

                        <!-- Map Box Settings -->
                        <h3>Map Box Settings</h3>
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
                        </table>
                        
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
