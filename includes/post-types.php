<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once plugin_dir_path( __FILE__ ) . 'helpers.php';

// Create custom post type for Cities
function create_city_post_type() {
    register_post_type( 'city',
        array(
            'labels' => array(
                'name' => __( 'Cities' ),
                'singular_name' => __( 'City' )
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array( 'title', 'thumbnail' ),
            'show_in_rest' => true,
            'show_in_menu' => 'osm-main-menu',
        )
    );
}
add_action( 'init', 'create_city_post_type' );

// Create custom post type for Signs
function create_sign_post_type() {
    register_post_type( 'sign',
        array(
            'labels' => array(
                'name' => __( 'Signs' ),
                'singular_name' => __( 'Sign' )
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array( 'title', 'thumbnail' ),
            'show_in_rest' => true,
            'show_in_menu' => 'osm-main-menu',
        )
    );
}
add_action( 'init', 'create_sign_post_type' );

// Add custom fields for Cities
function city_custom_fields() {
    add_meta_box(
        'city_fields',
        'City Details',
        'city_fields_callback',
        'city',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'city_custom_fields' );

// function city_signs_meta_box() { ... } // REMOVED - merged into main panel
// function city_signs_meta_box_callback( $post ) { ... } // REMOVED - merged into main panel
// function add_new_sign_link_meta_box() { ... } // REMOVED - merged into main panel
// function add_new_sign_link_callback($post) { ... } // REMOVED - merged into main panel

function city_fields_callback( $post ) {
    wp_nonce_field( 'city_save_meta_box_data', 'city_meta_box_nonce' );

    $lat = get_post_meta( $post->ID, '_city_lat', true );
    $lng = get_post_meta( $post->ID, '_city_lng', true );
    $count = get_post_meta( $post->ID, '_city_count', true );
    $venue = get_post_meta( $post->ID, '_city_venue', true );
    // Handle legacy/alias value
    if ( 'Billboard' === $venue ) {
        $venue = 'default';
    }

    // Visual Pin Selector for Venue
    $pins = osm_get_pins();
    
    // Actions Links
    $add_new_sign_url = admin_url('post-new.php?post_type=sign&city_id=' . $post->ID);
    $view_signs_url = admin_url('edit.php?post_type=sign'); // We can't easily filter by meta in standard list without custom query var, but this links to list.
    // NOTE: To properly filter "Signs in this City", we'd need a custom admin filter. 
    // For now linking to the general list. A more advanced implementation would require `pre_get_posts`.
    ?>
    
    <div class="osm-panel">
        <div class="osm-panel-header">
            <h3>City Settings</h3>
            <div class="osm-btn-group">
                <a href="<?php echo esc_url($add_new_sign_url); ?>" class="osm-btn osm-btn-primary">
                    <span class="dashicons dashicons-plus-alt2"></span> Add New Sign
                </a>
            </div>
        </div>

        <ul class="osm-tabs">
            <li><a href="#osm-city-tab-details" class="active">City Details</a></li>
            <li><a href="#osm-city-tab-venue">Venue Pin</a></li>
            <li><a href="#osm-city-tab-signs">Signs</a></li>
        </ul>

        <div class="osm-tab-content">
            <!-- TAB 1: DETAILS -->
            <div id="osm-city-tab-details" class="osm-tab-pane active">
                <div style="display: flex; gap: 20px;">
                    <div class="osm-form-group" style="flex: 1;">
                        <label for="city_lat">Latitude</label>
                        <input type="text" id="city_lat" name="city_lat" value="<?php echo esc_attr( $lat ); ?>" placeholder="e.g. 40.7128" />
                    </div>
                    <div class="osm-form-group" style="flex: 1;">
                        <label for="city_lng">Longitude</label>
                        <input type="text" id="city_lng" name="city_lng" value="<?php echo esc_attr( $lng ); ?>" placeholder="e.g. -74.0060" />
                    </div>
                </div>

                <div class="osm-form-group">
                    <label for="city_count">Display Count <span class="description">(Number shown on cluster)</span></label>
                    <input type="text" id="city_count" name="city_count" value="<?php echo esc_attr( $count ); ?>" placeholder="e.g. 5" />
                </div>
            </div>

            <!-- TAB 2: VENUE -->
            <div id="osm-city-tab-venue" class="osm-tab-pane">
                <div class="osm-form-group">
                    <label>Venue Pin</label>
                    <div class="pin-selector-wrap">
                        <?php foreach ($pins as $pin) : ?>
                            <div class="pin-selector-item">
                                <input type="radio" id="city_venue_<?php echo esc_attr($pin['venue']); ?>" name="city_venue" value="<?php echo esc_attr($pin['venue']); ?>" <?php checked($venue, $pin['venue']); ?>>
                                <label for="city_venue_<?php echo esc_attr($pin['venue']); ?>">
                                    <img src="<?php echo esc_url($pin['url']); ?>" alt="<?php echo esc_attr($pin['venue']); ?>">
                                    <span><?php echo esc_html(ucfirst(str_replace('-', ' ', $pin['venue']))); ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 3: SIGNS LIST -->
            <div id="osm-city-tab-signs" class="osm-tab-pane">
                <div class="osm-form-group">
                    <label>Signs in this City</label>
                    <?php
                    $args = array(
                        'post_type' => 'sign',
                        'posts_per_page' => -1,
                        'meta_key' => '_sign_city_id',
                        'meta_value' => $post->ID,
                    );
                    $signs = get_posts( $args );
                    if ( $signs ) {
                        echo '<ul style="margin-top: 10px; list-style: disc; margin-left: 20px;">';
                        foreach ( $signs as $sign ) {
                            $edit_link = get_edit_post_link( $sign->ID );
                            echo '<li><a href="' . esc_url( $edit_link ) . '" style="text-decoration: none; font-weight: 500;">' . esc_html( $sign->post_title ) . '</a></li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<p class="description">No signs found for this city.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function city_save_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['city_meta_box_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['city_meta_box_nonce'], 'city_save_meta_box_data' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['city_lat'] ) ) {
        update_post_meta( $post_id, '_city_lat', sanitize_text_field( $_POST['city_lat'] ) );
    }
    if ( isset( $_POST['city_lng'] ) ) {
        update_post_meta( $post_id, '_city_lng', sanitize_text_field( $_POST['city_lng'] ) );
    }
    if ( isset( $_POST['city_count'] ) ) {
        update_post_meta( $post_id, '_city_count', sanitize_text_field( $_POST['city_count'] ) );
    }
    if ( isset( $_POST['city_venue'] ) ) {
        update_post_meta( $post_id, '_city_venue', sanitize_text_field( $_POST['city_venue'] ) );
    }
}
add_action( 'save_post', 'city_save_meta_box_data' );

// Add custom fields for Signs
function sign_custom_fields() {
    add_meta_box(
        'sign_fields',
        'Sign Details',
        'sign_fields_callback',
        'sign',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'sign_custom_fields' );



function sign_fields_callback( $post ) {
    wp_nonce_field( 'sign_save_meta_box_data', 'sign_meta_box_nonce' );

    // --- DATA PREP ---
    $lat = get_post_meta( $post->ID, '_sign_lat', true );
    $lng = get_post_meta( $post->ID, '_sign_lng', true );
    $venue = get_post_meta( $post->ID, '_sign_venue', true );
    // Handle legacy/alias value
    if ( 'Billboard' === $venue ) {
        $venue = 'default';
    }
    $selected_city_id = get_post_meta( $post->ID, '_sign_city_id', true );
    $sign_image_url = get_post_meta( $post->ID, '_sign_image_url', true );
    
    if (isset($_GET['city_id'])) {
        $selected_city_id = intval($_GET['city_id']);
    }

    $cta_behavior = get_post_meta( $post->ID, '_sign_cta_behavior', true ) ?: 'default';
    $cta_url = get_post_meta( $post->ID, '_sign_cta_url', true );

    // Visual Pin Selector for Venue
    $pins = osm_get_pins();
    ?>
    
    <div class="osm-panel">
        <ul class="osm-tabs">
            <li><a href="#osm-sign-tab-location" class="active">Map Details</a></li>
            <li><a href="#osm-sign-tab-venue">Venue Pin</a></li>
            <li><a href="#osm-sign-tab-media">Media</a></li>
            <li><a href="#osm-sign-tab-action">Action Button</a></li>
        </ul>

        <div class="osm-tab-content">
            <!-- TAB 1: LOCATION -->
            <div id="osm-sign-tab-location" class="osm-tab-pane active">
                <div class="osm-form-group">
                    <label for="sign_city_id">City</label>
                    <select id="sign_city_id" name="sign_city_id">
                        <option value="">Select a City</option>
                        <?php
                        $cities = get_posts( array(
                            'post_type' => 'city',
                            'posts_per_page' => -1,
                            'orderby' => 'title',
                            'order' => 'ASC',
                        ) );
                        foreach ( $cities as $city_post ) {
                            $selected = selected( $selected_city_id, $city_post->ID, false );
                            echo '<option value="' . esc_attr( $city_post->ID ) . '" ' . $selected . '>' . esc_html( $city_post->post_title ) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div style="display: flex; gap: 20px;">
                    <div class="osm-form-group" style="flex: 1;">
                        <label for="sign_lat">Latitude</label>
                        <input type="text" id="sign_lat" name="sign_lat" value="<?php echo esc_attr( $lat ); ?>" placeholder="e.g. 40.7128" />
                    </div>
                    <div class="osm-form-group" style="flex: 1;">
                        <label for="sign_lng">Longitude</label>
                        <input type="text" id="sign_lng" name="sign_lng" value="<?php echo esc_attr( $lng ); ?>" placeholder="e.g. -74.0060" />
                    </div>
                </div>
            </div>

            <!-- TAB 2: VENUE PIN -->
            <div id="osm-sign-tab-venue" class="osm-tab-pane">
                <div class="osm-form-group">
                    <label>Venue Pin</label>
                    <div class="pin-selector-wrap">
                        <?php foreach ($pins as $pin) : ?>
                            <div class="pin-selector-item">
                                <input type="radio" id="venue_<?php echo esc_attr($pin['venue']); ?>" name="sign_venue" value="<?php echo esc_attr($pin['venue']); ?>" <?php checked($venue, $pin['venue']); ?>>
                                <label for="venue_<?php echo esc_attr($pin['venue']); ?>">
                                    <img src="<?php echo esc_url($pin['url']); ?>" alt="<?php echo esc_attr($pin['venue']); ?>">
                                    <span><?php echo esc_html(ucfirst(str_replace('-', ' ', $pin['venue']))); ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 3: MEDIA -->
            <div id="osm-sign-tab-media" class="osm-tab-pane">
                <div class="osm-form-group">
                    <label for="sign_image_url">External Image URL <span class="description">(Optional)</span></label>
                    <input type="text" id="sign_image_url" name="sign_image_url" value="<?php echo esc_attr( $sign_image_url ); ?>" placeholder="https://..." />
                    <p class="osm-helper-text">If provided, this URL will be prioritized over the Featured Image based on global settings.</p>
                </div>
            </div>

            <!-- TAB 4: ACTION BUTTON -->
            <div id="osm-sign-tab-action" class="osm-tab-pane">
                <div class="osm-form-group">
                    <label>CTA Button Behavior</label>
                    <div class="osm-radio-group">
                        <label>
                            <input type="radio" name="sign_cta_behavior" value="default" <?php checked($cta_behavior, 'default'); ?>> 
                            <span>Default (Global)</span>
                        </label>
                        <label>
                            <input type="radio" name="sign_cta_behavior" value="custom" <?php checked($cta_behavior, 'custom'); ?>> 
                            <span>Enable & Override</span>
                        </label>
                        <label>
                            <input type="radio" name="sign_cta_behavior" value="disable" <?php checked($cta_behavior, 'disable'); ?>> 
                            <span>Disable Button</span>
                        </label>
                    </div>
                </div>

                <div class="osm-form-group" id="sign_cta_url_wrapper" style="<?php echo ($cta_behavior === 'custom') ? '' : 'display:none;'; ?>">
                    <label for="sign_cta_url">Custom CTA URL</label>
                    <input type="text" id="sign_cta_url" name="sign_cta_url" value="<?php echo esc_attr( $cta_url ); ?>" placeholder="https://example.com" />
                </div>
            </div>
        </div>
    </div>
    <?php
}

function sign_save_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['sign_meta_box_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['sign_meta_box_nonce'], 'sign_save_meta_box_data' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['sign_lat'] ) ) {
        update_post_meta( $post_id, '_sign_lat', sanitize_text_field( $_POST['sign_lat'] ) );
    }
    if ( isset( $_POST['sign_lng'] ) ) {
        update_post_meta( $post_id, '_sign_lng', sanitize_text_field( $_POST['sign_lng'] ) );
    }
    if ( isset( $_POST['sign_venue'] ) ) {
        update_post_meta( $post_id, '_sign_venue', sanitize_text_field( $_POST['sign_venue'] ) );
    }
    if ( isset( $_POST['sign_image_url'] ) ) {
        update_post_meta( $post_id, '_sign_image_url', sanitize_text_field( $_POST['sign_image_url'] ) );
    }
    if ( isset( $_POST['sign_city_id'] ) ) {
        update_post_meta( $post_id, '_sign_city_id', sanitize_text_field( $_POST['sign_city_id'] ) );
        // Also save the city name for easier access in the REST API
        $city_post = get_post( sanitize_text_field( $_POST['sign_city_id'] ) );
        if ( $city_post ) {
            update_post_meta( $post_id, '_sign_city', $city_post->post_title );
        }
    }

    if ( isset( $_POST['sign_cta_behavior'] ) ) {
        update_post_meta( $post_id, '_sign_cta_behavior', sanitize_text_field( $_POST['sign_cta_behavior'] ) );
    }
    if ( isset( $_POST['sign_cta_url'] ) ) {
        update_post_meta( $post_id, '_sign_cta_url', sanitize_text_field( $_POST['sign_cta_url'] ) );
    }
}
add_action( 'save_post', 'sign_save_meta_box_data' );

// Add custom column to Signs list
function add_city_column_to_signs_list( $columns ) {
    $new_columns = array();
    foreach ( $columns as $key => $value ) {
        $new_columns[ $key ] = $value;
        if ( 'title' === $key ) {
            $new_columns['sign_city'] = __( 'City', 'open-street-map' );
        }
    }
    return $new_columns;
}
add_filter( 'manage_sign_posts_columns', 'add_city_column_to_signs_list' );

// Populate custom column for Signs
// Custom Column Population
function populate_city_column_for_signs( $column, $post_id ) {
    if ( 'sign_city' === $column ) {
        $city_id = get_post_meta( $post_id, '_sign_city_id', true );
        $city_name = get_post_meta( $post_id, '_sign_city', true );
        
        if ( $city_id ) {
            // If we have an ID, try to get the current title in case it changed
            $city_post = get_post($city_id);
            if ($city_post) {
                $city_name = $city_post->post_title;
                $city_edit_link = get_edit_post_link( $city_id );
                echo '<a href="' . esc_url( $city_edit_link ) . '">' . esc_html( $city_name ) . '</a>';
            } else {
                echo esc_html( $city_name ); // Fallback to saved name
            }
        } else {
             echo '—';
        }
    }
}
add_action( 'manage_sign_posts_custom_column', 'populate_city_column_for_signs', 10, 2 );

// Remove "Slug" Meta Box from City and Sign screens
function osm_remove_slug_meta_box() {
    remove_meta_box( 'slugdiv', 'city', 'normal' );
    remove_meta_box( 'slugdiv', 'sign', 'normal' );
}
add_action( 'admin_menu', 'osm_remove_slug_meta_box' );
