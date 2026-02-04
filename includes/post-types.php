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

function city_signs_meta_box() {
    add_meta_box(
        'city_signs_list',
        'Signs in this City',
        'city_signs_meta_box_callback',
        'city',
        'normal',
        'default'
    );
}
add_action( 'add_meta_boxes', 'city_signs_meta_box' );

function city_signs_meta_box_callback( $post ) {
    $args = array(
        'post_type' => 'sign',
        'posts_per_page' => -1,
        'meta_key' => '_sign_city_id',
        'meta_value' => $post->ID,
    );

    $signs = get_posts( $args );

    if ( $signs ) {
        echo '<ul>';
        foreach ( $signs as $sign ) {
            $edit_link = get_edit_post_link( $sign->ID );
            echo '<li><a href="' . esc_url( $edit_link ) . '">' . esc_html( $sign->post_title ) . '</a></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No signs found for this city.</p>';
    }
}

function add_new_sign_link_meta_box() {
    add_meta_box(
        'add_new_sign_link',
        'Add New Sign',
        'add_new_sign_link_callback',
        'city',
        'side',
        'low'
    );
}
add_action('add_meta_boxes', 'add_new_sign_link_meta_box');

function add_new_sign_link_callback($post) {
    $add_new_sign_url = admin_url('post-new.php?post_type=sign&city_id=' . $post->ID);
    echo '<a href="' . esc_url($add_new_sign_url) . '" class="button">Add New Sign for this City</a>';
}

function city_fields_callback( $post ) {
    wp_nonce_field( 'city_save_meta_box_data', 'city_meta_box_nonce' );

    $lat = get_post_meta( $post->ID, '_city_lat', true );
    $lng = get_post_meta( $post->ID, '_city_lng', true );
    $count = get_post_meta( $post->ID, '_city_count', true );
    $venue = get_post_meta( $post->ID, '_city_venue', true );

    echo '<p><label for="city_lat">Latitude: </label>';
    echo '<input type="text" id="city_lat" name="city_lat" value="' . esc_attr( $lat ) . '" size="25" /></p>';

    echo '<p><label for="city_lng">Longitude: </label>';
    echo '<input type="text" id="city_lng" name="city_lng" value="' . esc_attr( $lng ) . '" size="25" /></p>';

    echo '<p><label for="city_count">Count: </label>';
    echo '<input type="text" id="city_count" name="city_count" value="' . esc_attr( $count ) . '" size="25" /></p>';

    // Visual Pin Selector for Venue
    $pins = osm_get_pins();
    ?>
    <style>
        .pin-selector-wrap {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); /* Increased min-width */
            gap: 10px;
        }
        .pin-selector-item input[type="radio"] {
            display: none;
        }
        .pin-selector-item label {
            display: block;
            cursor: pointer;
            text-align: center;
            padding: 10px 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }
        .pin-selector-item label img {
            width: 32px;
            height: 32px;
            display: block;
            margin: 0 auto 5px;
        }
        .pin-selector-item input[type="radio"]:checked + label {
            background-color: #f0f0f0;
            border-color: #0073aa;
            box-shadow: 0 0 5px rgba(0,115,170,0.5);
        }
    </style>
    <p><strong>Venue Pin:</strong></p>
    <div class="pin-selector-wrap">
        <?php foreach ($pins as $pin) : ?>
            <div class="pin-selector-item">
                <input type="radio" id="venue_<?php echo esc_attr($pin['venue']); ?>" name="city_venue" value="<?php echo esc_attr($pin['venue']); ?>" <?php checked($venue, $pin['venue']); ?>>
                <label for="venue_<?php echo esc_attr($pin['venue']); ?>">
                    <img src="<?php echo esc_url($pin['url']); ?>" alt="<?php echo esc_attr($pin['venue']); ?>">
                    <span><?php echo esc_html(ucfirst(str_replace('-', ' ', $pin['venue']))); ?></span>
                </label>
            </div>
        <?php endforeach; ?>
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

    $lat = get_post_meta( $post->ID, '_sign_lat', true );
    $lng = get_post_meta( $post->ID, '_sign_lng', true );
    $venue = get_post_meta( $post->ID, '_sign_venue', true );
    $selected_city_id = get_post_meta( $post->ID, '_sign_city_id', true ); // Get the stored city ID
    $sign_image_url = get_post_meta( $post->ID, '_sign_image_url', true );

    if (isset($_GET['city_id'])) {
        $selected_city_id = intval($_GET['city_id']);
    }

    echo '<p><label for="sign_lat">Latitude: </label>';
    echo '<input type="text" id="sign_lat" name="sign_lat" value="' . esc_attr( $lat ) . '" size="25" /></p>';

    echo '<p><label for="sign_lng">Longitude: </label>';
    echo '<input type="text" id="sign_lng" name="sign_lng" value="' . esc_attr( $lng ) . '" size="25" /></p>';

    echo '<p><label for="sign_image_url">External Image URL: </label>';
    echo '<input type="text" id="sign_image_url" name="sign_image_url" value="' . esc_attr( $sign_image_url ) . '" size="25" /></p>';
    echo '<p><em>If you provide an external image URL, it will be used instead of the featured image.</em></p>';

    
    echo '<p><label for="sign_city_id">City: </label>';
    echo '<select id="sign_city_id" name="sign_city_id">';
    echo '<option value="">Select a City</option>';

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
    echo '</select></p>';

    // Visual Pin Selector for Venue
    $pins = osm_get_pins();
    ?>
    <style>
        .pin-selector-wrap {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); /* Increased min-width */
            gap: 10px;
        }
        .pin-selector-item input[type="radio"] {
            display: none;
        }
        .pin-selector-item label {
            display: block;
            cursor: pointer;
            text-align: center;
            padding: 10px 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }
        .pin-selector-item label img {
            width: 32px;
            height: 32px;
            display: block;
            margin: 0 auto 5px;
        }
        .pin-selector-item input[type="radio"]:checked + label {
            background-color: #f0f0f0;
            border-color: #0073aa;
            box-shadow: 0 0 5px rgba(0,115,170,0.5);
        }
    </style>
    <p><strong>Venue Pin:</strong></p>
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
function populate_city_column_for_signs( $column, $post_id ) {
    if ( 'sign_city' === $column ) {
        $city_id = get_post_meta( $post_id, '_sign_city_id', true );
        $city_name = get_post_meta( $post_id, '_sign_city', true );

        if ( $city_id && $city_name ) {
            $city_edit_link = get_edit_post_link( $city_id );
            echo '<a href="' . esc_url( $city_edit_link ) . '">' . esc_html( $city_name ) . '</a>';
        } else {
            echo esc_html( $city_name );
        }
    }
}
add_action( 'manage_sign_posts_custom_column', 'populate_city_column_for_signs', 10, 2 );
