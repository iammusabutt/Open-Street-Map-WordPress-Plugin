<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// AJAX handler for file upload
function osm_ajax_upload_csv() {
    check_ajax_referer('osm_ajax_nonce', 'nonce');

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    if ( ! isset( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error('Error uploading file.');
    }

    $file_info = wp_check_filetype(basename($_FILES['csv_file']['name']));
    if ($file_info['ext'] !== 'csv') {
        wp_send_json_error('Please upload a valid .csv file.');
    }

    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/osm-imports';
    if (!file_exists($target_dir)) {
        wp_mkdir_p($target_dir);
    }

    $filename = uniqid() . '-' . sanitize_file_name($_FILES['csv_file']['name']);
    $new_file_path = $target_dir . '/' . $filename;

    if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $new_file_path)) {
        wp_send_json_success(array(
            'filePath' => $new_file_path,
            'fileName' => basename($_FILES['csv_file']['name'])
        ));
    } else {
        wp_send_json_error('Failed to move uploaded file.');
    }
}
add_action('wp_ajax_osm_upload_csv', 'osm_ajax_upload_csv');
add_action('wp_ajax_nopriv_osm_upload_csv', 'osm_ajax_upload_csv');

function osm_ajax_start_import() {
    check_ajax_referer('osm_ajax_nonce', 'nonce');
    error_log('OSM Import: Starting import process...');

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    $file_path = sanitize_text_field($_POST['file_path']);
    $import_type = sanitize_text_field($_POST['import_type']);

    if (!file_exists($file_path)) {
        error_log('OSM Import: Uploaded file not found at ' . $file_path);
        wp_send_json_error('Uploaded file not found.');
    }

    $job_id = 'osm_import_' . uniqid();
    // Assuming the first row is a header, so we subtract 1
    $total_rows = count(file($file_path)) - 1; 

    update_option($job_id, array(
        'file_path' => $file_path,
        'import_type' => $import_type,
        'total_rows' => $total_rows,
        'processed_rows' => 0,
        'errors' => [],
        'status' => 'pending', 
    ));

    error_log('OSM Import: Job created with ID: ' . $job_id);
    wp_send_json_success(array('job_id' => $job_id));
}
add_action('wp_ajax_osm_start_import', 'osm_ajax_start_import');

function osm_ajax_process_batch() {
    check_ajax_referer('osm_ajax_nonce', 'nonce');
    error_log('OSM Import: Processing batch...');

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    $job_id = sanitize_text_field($_POST['job_id']);
    $job = get_option($job_id);

    if (!$job) {
        error_log('OSM Import: Job not found for ID: ' . $job_id);
        wp_send_json_error('Import job not found.');
    }

    error_log('OSM Import: import_type value is ' . $job['import_type']);

    if ($job['status'] === 'pending') {
        $job['status'] = 'in_progress';
        error_log('OSM Import: Job status updated to in_progress for job ID: ' . $job_id);
    }

    $batch_size = 100;
    $file_path = $job['file_path'];
    $import_type = $job['import_type'];
    $processed_rows = $job['processed_rows'];
    $errors = $job['errors'];

    // Validate import_type
    if (!in_array($import_type, array('cities', 'signs'))) {
        error_log('OSM Import: Invalid import_type: ' . $import_type . ' for job ID: ' . $job_id);
        wp_send_json_error('Invalid import type.');
    }

    error_log('OSM Import: Processing batch for job ID: ' . $job_id . '. Import type: ' . $import_type . '. Processed rows: ' . $processed_rows . '. Total rows: ' . $job['total_rows']);

    $handle = fopen($file_path, "r");
    if ($handle === FALSE) {
        $errors[] = 'Could not open CSV file for processing.';
        $job['errors'] = $errors;
        $job['status'] = 'failed';
        update_option($job_id, $job);
        error_log('OSM Import: Failed to open CSV file for job ID: ' . $job_id);
        wp_send_json_error($job);
    }

    // Move file pointer to the correct position
    for ($i = 0; $i <= $processed_rows; $i++) {
        fgetcsv($handle); // Includes header
    }

    $current_batch = 0;
    while (($data = fgetcsv($handle)) !== FALSE && $current_batch < $batch_size) {
        if ($import_type === 'cities') {
             $post_title = sanitize_text_field($data[0]);
            if (empty($post_title)) {
                $errors[] = "Skipped row " . ($processed_rows + $current_batch + 1) . ": empty city name.";
                error_log('OSM Import: Skipped empty city name at row ' . ($processed_rows + $current_batch + 1));
                continue;
            }
            $new_post = ['post_title' => $post_title, 'post_type' => 'city', 'post_status' => 'publish'];
            error_log('OSM Import: Creating city post with title: ' . $post_title . ' and post_type: city');
            $post_id = wp_insert_post($new_post);

            if ($post_id && !is_wp_error($post_id)) {
                error_log('OSM Import: Successfully created city post with ID: ' . $post_id);
                update_post_meta($post_id, '_city_lat', sanitize_text_field($data[1]));
                update_post_meta($post_id, '_city_lng', sanitize_text_field($data[2]));
                update_post_meta($post_id, '_city_count', sanitize_text_field($data[3]));
                update_post_meta($post_id, '_city_venue', sanitize_text_field($data[4]));
            } else {
                $errors[] = "Failed to import city: " . $post_title;
                error_log('OSM Import: Failed to import city: ' . $post_title);
            }
        } elseif ($import_type === 'signs') {
            $post_title = sanitize_text_field($data[0]);
             if (empty($post_title)) {
                $errors[] = "Skipped row " . ($processed_rows + $current_batch + 1) . ": empty sign title.";
                error_log('OSM Import: Skipped empty sign title at row ' . ($processed_rows + $current_batch + 1));
                continue;
            }
            $new_post = ['post_title' => $post_title, 'post_type' => 'sign', 'post_status' => 'publish'];
            error_log('OSM Import: Creating sign post with title: ' . $post_title . ' and post_type: sign');
            $post_id = wp_insert_post($new_post);

            if ($post_id && !is_wp_error($post_id)) {
                error_log('OSM Import: Successfully created sign post with ID: ' . $post_id);
                update_post_meta($post_id, '_sign_lat', sanitize_text_field($data[1]));
                update_post_meta($post_id, '_sign_lng', sanitize_text_field($data[2]));
                update_post_meta($post_id, '_sign_venue', sanitize_text_field($data[3]));
                $city_name = sanitize_text_field($data[4]);
                $city = get_page_by_title($city_name, OBJECT, 'city');
                if ($city) {
                    update_post_meta($post_id, '_sign_city_id', $city->ID);
                    update_post_meta($post_id, '_sign_city', $city->post_title);
                } else {
                    $errors[] = "City not found for sign: " . $post_title;
                    error_log('OSM Import: City not found for sign: ' . $post_title);
                }
                if (isset($data[5])) {
                    update_post_meta($post_id, '_sign_image_url', sanitize_text_field($data[5]));
                }
            } else {
                 $errors[] = "Failed to import sign: " . $post_title;
                 error_log('OSM Import: Failed to import sign: ' . $post_title);
            }
        }
        $current_batch++;
    }
    fclose($handle);

    $job['processed_rows'] = $processed_rows + $current_batch;
    $job['errors'] = $errors;

    if ($job['processed_rows'] >= $job['total_rows']) {
        $job['status'] = 'complete';
        unlink($job['file_path']);
        error_log('OSM Import: Import complete for job ID: ' . $job_id);
    }

    update_option($job_id, $job);
    wp_send_json_success($job);
}
add_action('wp_ajax_osm_process_batch', 'osm_ajax_process_batch');

function osm_ajax_upload_pin() {
    check_ajax_referer('osm_ajax_nonce', 'nonce');

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    if ( ! isset( $_FILES['pin_file'] ) || $_FILES['pin_file']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error('Error uploading file.');
    }

    // Temporarily allow SVG uploads
    add_filter('upload_mimes', 'osm_allow_svg_upload_mimes');

    $file = $_FILES['pin_file'];
    $file_info = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    
    if ($file_info['ext'] !== 'svg') {
        remove_filter('upload_mimes', 'osm_allow_svg_upload_mimes');
        wp_send_json_error('Please upload a valid .svg file.');
    }

    $target_dir = plugin_dir_path( __FILE__ ) . '../assets/pins/';
    $filename = sanitize_file_name($file['name']);
    $new_file_path = $target_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $new_file_path)) {
        wp_send_json_success(array(
            'message' => 'Pin uploaded successfully.',
            'pin' => array(
                'name' => $filename,
                'venue' => pathinfo($filename, PATHINFO_FILENAME),
                'url' => plugin_dir_url( __FILE__ ) . '../assets/pins/' . $filename
            )
        ));
    } else {
        wp_send_json_error('Failed to move uploaded file.');
    }

    // Clean up the filter
    remove_filter('upload_mimes', 'osm_allow_svg_upload_mimes');
}
add_action('wp_ajax_osm_upload_pin', 'osm_ajax_upload_pin');

function osm_ajax_delete_pin() {
    check_ajax_referer('osm_ajax_nonce', 'nonce');

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    $filename = sanitize_file_name($_POST['pin_name']);
    $file_path = plugin_dir_path( __FILE__ ) . '../assets/pins/' . $filename;

    if (file_exists($file_path)) {
        if (unlink($file_path)) {
            wp_send_json_success('Pin deleted successfully.');
        } else {
            wp_send_json_error('Failed to delete pin.');
        }
    } else {
        wp_send_json_error('Pin not found.');
    }
}
add_action('wp_ajax_osm_delete_pin', 'osm_ajax_delete_pin');

function osm_ajax_save_settings() {
    check_ajax_referer('osm_ajax_nonce', 'nonce');

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    if (isset($_POST['osm_image_priority'])) {
        update_option('osm_image_priority', sanitize_text_field($_POST['osm_image_priority']));
    } else {
        update_option('osm_image_priority', 'featured');
    }

    if (isset($_POST['osm_default_cta_url'])) {
        update_option('osm_default_cta_url', sanitize_text_field($_POST['osm_default_cta_url']));
    }
    
    if (isset($_POST['osm_disable_cta_button'])) {
        update_option('osm_disable_cta_button', 'yes');
    } else {
        update_option('osm_disable_cta_button', 'no');
    }

    // Save Color Settings
    if (isset($_POST['osm_popup_bg_color'])) {
        update_option('osm_popup_bg_color', sanitize_hex_color($_POST['osm_popup_bg_color']));
    }
    if (isset($_POST['osm_popup_btn_bg_color'])) {
        update_option('osm_popup_btn_bg_color', sanitize_hex_color($_POST['osm_popup_btn_bg_color']));
    }
    if (isset($_POST['osm_popup_btn_text_color'])) {
        update_option('osm_popup_btn_text_color', sanitize_hex_color($_POST['osm_popup_btn_text_color']));
    }
    if (isset($_POST['osm_popup_text_color'])) {
        update_option('osm_popup_text_color', sanitize_hex_color($_POST['osm_popup_text_color']));
    }
    if (isset($_POST['osm_bubble_color'])) {
        update_option('osm_bubble_color', sanitize_hex_color($_POST['osm_bubble_color']));
    }

    wp_send_json_success();
}
add_action('wp_ajax_osm_save_settings', 'osm_ajax_save_settings');

function osm_ajax_delete_csv() {
    check_ajax_referer('osm_ajax_nonce', 'nonce');

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    $file_path = sanitize_text_field($_POST['file_path']);

    if (file_exists($file_path)) {
        if (unlink($file_path)) {
            wp_send_json_success('File deleted successfully.');
        } else {
            wp_send_json_error('Failed to delete file.');
        }
    } else {
        wp_send_json_error('File not found.');
    }
}
add_action('wp_ajax_osm_delete_csv', 'osm_ajax_delete_csv');
