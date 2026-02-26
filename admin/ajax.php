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
            $existing_city = get_page_by_title($post_title, OBJECT, 'city');
            if ($existing_city) {
                 $errors[] = "Skipped row " . ($processed_rows + $current_batch + 1) . ": duplicate city '" . $post_title . "'.";
                 error_log('OSM Import: Skipped duplicate city at row ' . ($processed_rows + $current_batch + 1));
                 $current_batch++;
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
            $existing_sign = get_page_by_title($post_title, OBJECT, 'sign');
            if ($existing_sign) {
                 $errors[] = "Skipped row " . ($processed_rows + $current_batch + 1) . ": duplicate sign '" . $post_title . "'.";
                 error_log('OSM Import: Skipped duplicate sign at row ' . ($processed_rows + $current_batch + 1));
                 $current_batch++;
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

    $settings_group = isset($_POST['osm_settings_group']) ? sanitize_text_field($_POST['osm_settings_group']) : 'all';

    // Save General Settings
    if ($settings_group === 'general' || $settings_group === 'all') {
        if (isset($_POST['osm_image_priority'])) {
            update_option('osm_image_priority', sanitize_text_field($_POST['osm_image_priority']));
        } else {
            update_option('osm_image_priority', 'featured');
        }



        if (isset($_POST['osm_developer_mode'])) {
            update_option('osm_developer_mode', 'yes');
        } else {
            update_option('osm_developer_mode', 'no');
        }

        if (isset($_POST['osm_disable_asset_cache'])) {
            update_option('osm_disable_asset_cache', 'yes');
        } else {
            update_option('osm_disable_asset_cache', 'no');
        }

        if (isset($_POST['osm_zoom_speed'])) {
            update_option('osm_zoom_speed', intval($_POST['osm_zoom_speed']));
        }
        
        if (isset($_POST['osm_sign_zoom_threshold'])) {
            // Need to convert string to float for the threshold, since JS maps map.getZoom()
            update_option('osm_sign_zoom_threshold', floatval($_POST['osm_sign_zoom_threshold']));
        }
    }

    // Save Color Settings
    if ($settings_group === 'colors' || $settings_group === 'all') {
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
    }

    // Save Map Box Settings
    if ($settings_group === 'mapbox' || $settings_group === 'all') {
        if (isset($_POST['osm_default_cta_url'])) {
            update_option('osm_default_cta_url', sanitize_text_field($_POST['osm_default_cta_url']));
        }
        
        if (isset($_POST['osm_popup_button_text'])) {
            update_option('osm_popup_button_text', sanitize_text_field($_POST['osm_popup_button_text']));
        }
        
        if (isset($_POST['osm_disable_cta_button'])) {
            update_option('osm_disable_cta_button', 'yes');
        } else {
            update_option('osm_disable_cta_button', 'no');
        }
        
        if (isset($_POST['osm_enable_image_lightbox'])) {
            update_option('osm_enable_image_lightbox', 'yes');
        } else {
            update_option('osm_enable_image_lightbox', 'no');
        }
        
        if (isset($_POST['osm_enable_title_link'])) {
            update_option('osm_enable_title_link', 'yes');
        } else {
            update_option('osm_enable_title_link', 'no');
        }
    }

    // Save Map Layer
    if ($settings_group === 'layers' || $settings_group === 'all') {
        if (isset($_POST['osm_map_layer'])) {
            update_option('osm_map_layer', sanitize_text_field($_POST['osm_map_layer']));
        }
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

function osm_ajax_remove_duplicates() {
    check_ajax_referer('osm_ajax_nonce', 'nonce');

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    global $wpdb;
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';

    
    $logs = [];
    $found_duplicates = false;

    // Helper function to process duplicates
    $process_duplicates = function($post_type) use ($wpdb, $dry_run, &$logs, &$found_duplicates) {
        // Get ONE duplicate group (LIMIT 1)
        $duplicate_group = $wpdb->get_row($wpdb->prepare("
            SELECT post_title, MIN(ID) as min_id, COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = %s AND post_status = 'publish'
            GROUP BY post_title
            HAVING COUNT(*) > 1
            LIMIT 1
        ", $post_type));

        if ($duplicate_group) {
            $found_duplicates = true;
            $logs[] = "Processing duplicate group: '{$duplicate_group->post_title}' (Found {$duplicate_group->count} records)";
            $winner_id = $duplicate_group->min_id;

            $ids_to_delete = $wpdb->get_col($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = %s AND post_status = 'publish'
                AND post_title = %s AND ID != %d
            ", $post_type, $duplicate_group->post_title, $winner_id));

            foreach ($ids_to_delete as $loser_id) {
                // If we are deleting a CITY, we must reassign its signs to the WINNER city
                if ($post_type === 'city') {
                    // Find signs linked to the loser city
                    $linked_signs = $wpdb->get_results($wpdb->prepare("
                        SELECT post_id FROM {$wpdb->postmeta}
                        WHERE meta_key = '_sign_city_id' AND meta_value = %d
                    ", $loser_id));

                    if ($linked_signs) {
                        foreach ($linked_signs as $sign) {
                            if ($dry_run) {
                                $logs[] = "[Dry Run] Would reassign Sign ID {$sign->post_id} from City ID $loser_id to City ID $winner_id";
                            } else {
                                update_post_meta($sign->post_id, '_sign_city_id', $winner_id);
                                // Also update the textual city name for API/List View consistency
                                update_post_meta($sign->post_id, '_sign_city', $duplicate_group->post_title);
                                $logs[] = "Reassigned Sign ID {$sign->post_id} to City ID $winner_id";
                            }
                        }
                    }
                }

                if ($dry_run) {
                    $logs[] = "[Dry Run] Would delete ID: $loser_id";
                } else {
                    $start_time = microtime(true);
                    wp_delete_post($loser_id, true);
                    $end_time = microtime(true);
                    $duration = round($end_time - $start_time, 4);
                    $logs[] = "Deleted ID: $loser_id ({$duration} sec)";
                }
            }
        }
    };

    if ($type === 'city') {
        $process_duplicates('city');
    } elseif ($type === 'sign') {
        $process_duplicates('sign');
    }

    if ($found_duplicates) {
        wp_send_json_success(array(
            'status' => 'continue',
            'logs' => $logs
        ));
    } else {
        wp_send_json_success(array(
            'status' => 'complete',
            'logs' => ["No more duplicates found."]
        ));
    }
}
add_action('wp_ajax_osm_remove_duplicates', 'osm_ajax_remove_duplicates');

function osm_ajax_bulk_delete() {
    check_ajax_referer('osm_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions.');
    }

    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    $logs = [];

    // Increase time limit
    set_time_limit(0); 

    if ($type === 'all_signs') {
        // Get signs
        $args = array(
            'post_type' => 'sign',
            'posts_per_page' => 50, // Batch size
            'fields' => 'ids'
        );
        $posts = get_posts($args);

        if (empty($posts)) {
             $logs[] = "No signs found to delete.";
             wp_send_json_success(['status' => 'complete', 'logs' => $logs]);
        }

        $count = 0;
        foreach ($posts as $post_id) {
            // DOUBLE CHECK: Ensure we are only deleting signs
            if (get_post_type($post_id) === 'sign') {
                wp_delete_post($post_id, true); // Force delete
                $count++;
            }
        }
        $logs[] = "Deleted $count signs in this batch.";

        // Check if more remain (simple check)
        $remaining_args = array(
            'post_type' => 'sign',
            'posts_per_page' => 1,
            'fields' => 'ids'
        );
        $remaining = get_posts($remaining_args);
        
        if (!empty($remaining)) {
            wp_send_json_success(['status' => 'continue', 'logs' => $logs]);
        } else {
            $logs[] = "All signs deleted.";
            wp_send_json_success(['status' => 'complete', 'logs' => $logs]);
        }

    } elseif ($type === 'orphaned_cities') {
        global $wpdb;
        
        // SQL to efficiently find cities that are NOT referenced in _sign_city_id meta
        $sql = "
            SELECT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON (pm.meta_key = '_sign_city_id' AND pm.meta_value = p.ID)
            WHERE p.post_type = 'city' 
            AND p.post_status = 'publish'
            AND pm.post_id IS NULL
            LIMIT 50
        ";
        
        $orphaned_city_ids = $wpdb->get_col($sql);

        if (empty($orphaned_city_ids)) {
            $logs[] = "No orphaned cities found.";
            wp_send_json_success(['status' => 'complete', 'logs' => $logs]);
        }

        $count = 0;
        foreach ($orphaned_city_ids as $city_id) {
            // DOUBLE CHECK: Ensure we are only deleting cities
            if (get_post_type($city_id) === 'city') {
                wp_delete_post($city_id, true);
                $count++;
            }
        }
        
        $logs[] = "Deleted $count orphaned cities.";

        // Check if more remain
         $remaining_check = $wpdb->get_var("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON (pm.meta_key = '_sign_city_id' AND pm.meta_value = p.ID)
            WHERE p.post_type = 'city' 
            AND p.post_status = 'publish'
            AND pm.post_id IS NULL
            LIMIT 1
        ");

        if ($remaining_check) {
             wp_send_json_success(['status' => 'continue', 'logs' => $logs]);
        } else {
             $logs[] = "All orphaned cities deleted.";
             wp_send_json_success(['status' => 'complete', 'logs' => $logs]);
        }
    } else {
        wp_send_json_error('Invalid action type.');
    }
}
add_action('wp_ajax_osm_bulk_delete', 'osm_ajax_bulk_delete');

function osm_ajax_bubble_sync() {
    check_ajax_referer('osm_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions.');
    }

    // Increase time limit
    set_time_limit(0);

    global $wpdb;

    // Get all city IDs
    $sql = "
        SELECT ID 
        FROM {$wpdb->posts} 
        WHERE post_type = 'city' AND post_status = 'publish'
    ";
    
    $city_ids = $wpdb->get_col($sql);

    if (empty($city_ids)) {
        wp_send_json_success(['message' => 'No cities found to sync.']);
    }

    $updated_count = 0;

    foreach ($city_ids as $city_id) {
        // Count signs for this city
        $count_sql = $wpdb->prepare("
            SELECT COUNT(p.ID) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id)
            WHERE p.post_type = 'sign' 
            AND p.post_status = 'publish' 
            AND pm.meta_key = '_sign_city_id' 
            AND pm.meta_value = %d
        ", $city_id);
        
        $sign_count = (int) $wpdb->get_var($count_sql);

        // Update the display_count / _city_count
        update_post_meta($city_id, '_city_count', $sign_count);
        $updated_count++;
    }

    wp_send_json_success(['message' => "Successfully synced bubble counts for {$updated_count} cities."]);
}
add_action('wp_ajax_osm_bubble_sync', 'osm_ajax_bubble_sync');


function osm_ajax_get_dashboard_stats() {
    check_ajax_referer('osm_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'osm_searches';

    // Check if table exists
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        wp_send_json_error('Search logs table missing.');
    }

    $date_filter = isset($_POST['date_filter']) ? sanitize_text_field($_POST['date_filter']) : 'all_time';

    $where_clause = "WHERE 1=1";
    switch ($date_filter) {
        case 'today':
            $where_clause .= " AND DATE(time) = CURDATE()";
            break;
        case 'yesterday':
            $where_clause .= " AND DATE(time) = CURDATE() - INTERVAL 1 DAY";
            break;
        case 'this_week':
            $where_clause .= " AND YEARWEEK(time, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'last_week':
            $where_clause .= " AND YEARWEEK(time, 1) = YEARWEEK(CURDATE() - INTERVAL 1 WEEK, 1)";
            break;
        case 'this_month':
            $where_clause .= " AND YEAR(time) = YEAR(CURDATE()) AND MONTH(time) = MONTH(CURDATE())";
            break;
        case 'last_month':
            $where_clause .= " AND YEAR(time) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(time) = MONTH(CURDATE() - INTERVAL 1 MONTH)";
            break;
        case 'last_30_days':
            $where_clause .= " AND DATE(time) >= CURDATE() - INTERVAL 30 DAY";
            break;
        case 'this_year':
            $where_clause .= " AND YEAR(time) = YEAR(CURDATE())";
            break;
        case 'last_year':
            $where_clause .= " AND YEAR(time) = YEAR(CURDATE()) - 1";
            break;
    }

    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause");
    $found = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause AND found_status = 'found'");
    
    $recent = $wpdb->get_results("
        SELECT 
            LOWER(search_query) as search_query, 
            MAX(time) as latest_time, 
            COUNT(*) as search_count, 
            found_status, 
            source 
        FROM $table_name 
        $where_clause
        GROUP BY LOWER(search_query), found_status, source 
        ORDER BY latest_time DESC 
        LIMIT 50
    ");

    $timeline = $wpdb->get_results("
        SELECT DATE(time) as date, COUNT(*) as count 
        FROM $table_name 
        $where_clause 
        GROUP BY DATE(time) 
        ORDER BY date ASC
    ");

    $status_chart = $wpdb->get_results("
        SELECT found_status, COUNT(*) as count 
        FROM $table_name 
        $where_clause 
        GROUP BY found_status
    ");

    $sources_chart = $wpdb->get_results("
        SELECT source, COUNT(*) as count 
        FROM $table_name 
        $where_clause 
        GROUP BY source
    ");

    wp_send_json_success([
        'total' => $total,
        'found' => $found,
        'recent' => $recent,
        'charts' => [
            'timeline' => $timeline,
            'status' => $status_chart,
            'sources' => $sources_chart
        ]
    ]);
}
add_action('wp_ajax_osm_get_dashboard_stats', 'osm_ajax_get_dashboard_stats');
