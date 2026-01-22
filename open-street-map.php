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

function open_street_map_enqueue_scripts() {
    // Enqueue MapLibre GL JS
    wp_enqueue_style( 'maplibre-gl-css', 'https://unpkg.com/maplibre-gl@1.15.2/dist/maplibre-gl.css' );
    wp_enqueue_script( 'maplibre-gl-js', 'https://unpkg.com/maplibre-gl@1.15.2/dist/maplibre-gl.js', array(), '1.15.2', true );

    // Enqueue Plugin Styles and Scripts
    wp_enqueue_style( 'open-street-map-style', plugin_dir_url( __FILE__ ) . 'css/style.css' );
    wp_enqueue_style( 'google-fonts-material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200', array(), null );
    wp_enqueue_script( 'open-street-map-main', plugin_dir_url( __FILE__ ) . 'js/main.js', array( 'maplibre-gl-js' ), '1.0', true );

    // Pass data to JavaScript
    wp_localize_script( 'open-street-map-main', 'plugin_vars', array(
        'asset_path' => plugin_dir_url( __FILE__ ) . 'assets/',
        'cities_url' => get_rest_url( null, 'osm/v1/cities' ),
        'signs_url' => get_rest_url( null, 'osm/v1/signs' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'open_street_map_enqueue_scripts' );

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

// AJAX handler to start import
function osm_ajax_start_import() {
    check_ajax_referer('osm_ajax_nonce', 'nonce');

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    $file_path = sanitize_text_field($_POST['file_path']);
    $import_type = sanitize_text_field($_POST['import_type']);

    if (!file_exists($file_path)) {
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

    wp_schedule_single_event(time(), 'osm_process_import_batch_action', array($job_id));

    wp_send_json_success(array('job_id' => $job_id));
}
add_action('wp_ajax_osm_start_import', 'osm_ajax_start_import');





add_action('osm_process_import_batch_action', 'osm_process_import_batch', 10, 1);
function osm_process_import_batch($job_id) {
    error_log("OSM Import Batch: Starting for job_id: " . $job_id);

    $job = get_option($job_id);
    if (!$job) {
        error_log("OSM Import Batch: Job not found for job_id: " . $job_id);
        return;
    }

    // Set status to in_progress if it's pending
    if ($job['status'] === 'pending') {
        $job['status'] = 'in_progress';
        update_option($job_id, $job);
        error_log("OSM Import Batch: Job " . $job_id . " status set to in_progress.");
    }

    $batch_size = 100;
    $file_path = $job['file_path'];
    $import_type = $job['import_type'];
    $processed_rows = $job['processed_rows'];
    $errors = $job['errors'];

    error_log("OSM Import Batch: Job " . $job_id . " - Processed: " . $processed_rows . ", Total: " . $job['total_rows']);

    $handle = fopen($file_path, "r");
    if ($handle === FALSE) {
        $errors[] = 'Could not open CSV file for processing.';
        $job['errors'] = $errors;
        $job['status'] = 'failed'; // Update status
        update_option($job_id, $job);
        error_log("OSM Import Batch: Job " . $job_id . " failed: Could not open CSV file.");
        return;
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
                error_log("OSM Import Batch: Job " . $job_id . " - Skipped empty city name at row " . ($processed_rows + $current_batch + 1));
                continue;
            }
            $new_post = ['post_title' => $post_title, 'post_type' => 'city', 'post_status' => 'publish'];
            $post_id = wp_insert_post($new_post);

            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, '_city_lat', sanitize_text_field($data[1]));
                update_post_meta($post_id, '_city_lng', sanitize_text_field($data[2]));
                update_post_meta($post_id, '_city_count', sanitize_text_field($data[3]));
                update_post_meta($post_id, '_city_venue', sanitize_text_field($data[4]));
            } else {
                $errors[] = "Failed to import city: " . $post_title;
                error_log("OSM Import Batch: Job " . $job_id . " - Failed to import city: " . $post_title);
            }
        } elseif ($import_type === 'signs') {
            $post_title = sanitize_text_field($data[0]);
             if (empty($post_title)) {
                $errors[] = "Skipped row " . ($processed_rows + $current_batch + 1) . ": empty sign title.";
                error_log("OSM Import Batch: Job " . $job_id . " - Skipped empty sign title at row " . ($processed_rows + $current_batch + 1));
                continue;
            }
            $new_post = ['post_title' => $post_title, 'post_type' => 'sign', 'post_status' => 'publish'];
            $post_id = wp_insert_post($new_post);

            if ($post_id && !is_wp_error($post_id)) {
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
                    error_log("OSM Import Batch: Job " . $job_id . " - City not found for sign: " . $post_title);
                }
            } else {
                 $errors[] = "Failed to import sign: " . $post_title;
                 error_log("OSM Import Batch: Job " . $job_id . " - Failed to import sign: " . $post_title);
            }
        }
        $current_batch++;
    }
    fclose($handle);

    $job['processed_rows'] = $processed_rows + $current_batch;
    $job['errors'] = $errors;
    update_option($job_id, $job);
    error_log("OSM Import Batch: Job " . $job_id . " - After batch. Processed: " . $job['processed_rows'] . ", Total: " . $job['total_rows']);


    if ($job['processed_rows'] < $job['total_rows']) {
        wp_schedule_single_event(time(), 'osm_process_import_batch_action', array($job_id));
        error_log("OSM Import Batch: Job " . $job_id . " - Scheduling next batch.");
    } else {
        // Clean up after import is complete
        unlink($job['file_path']);
        delete_transient('osm_current_import_job_id');
        $job['status'] = 'complete'; // Update status
        update_option($job_id, $job);
        error_log("OSM Import Batch: Job " . $job_id . " - Complete. Final Processed: " . $job['processed_rows'] . ", Total: " . $job['total_rows']);
    }
}

function osm_settings_page_html() {
    ?>
    <style>
        #osm-import-panel {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            border-radius: 4px;
        }
        #osm-upload-step, #osm-progress-step {
            display: none;
        }
        #osm-import-panel.initial #osm-upload-step {
            display: block;
        }
        #osm-import-panel.uploading #osm-upload-step .spinner {
            display: inline-block;
            visibility: visible;
        }
        #osm-import-panel.uploaded #osm-progress-step {
            display: block;
        }
         #osm-import-panel.importing #osm-progress-step {
            display: block;
        }
        #osm-file-name {
            font-weight: bold;
        }
        #import-progress {
            width: 100%;
        }
    </style>
    <div class="wrap">
        <h1>OpenStreetMap Settings</h1>
        <div id="osm-import-panel" class="initial">
            <!-- Step 1: Upload -->
            <div id="osm-upload-step">
                <h2>Import Tool</h2>
                <p>For Cities, the CSV column order should be: <strong>Name, Latitude, Longitude, Count, Venue</strong></p>
                <p>For Signs, the CSV column order should be: <strong>Title, Latitude, Longitude, Venue, City Name</strong></p>
                <form id="osm-upload-form" method="post" enctype="multipart/form-data">
                    <p>
                        <label for="import_type">Import Type:</label>
                        <select name="import_type" id="import_type">
                            <option value="cities">Cities</option>
                            <option value="signs">Signs</option>
                        </select>
                    </p>
                    <p>
                        <label for="csv_file">CSV File:</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv">
                    </p>
                    <p class="submit">
                        <button type="submit" class="button-primary">Upload File</button>
                        <span class="spinner"></span>
                    </p>
                </form>
            </div>

            <!-- Step 2: Progress -->
            <div id="osm-progress-step">
                <h2>Import Status</h2>
                <p>File: <span id="osm-file-name"></span></p>
                <p>Status: <strong id="osm-import-status">Uploaded</strong></p>
                <button id="osm-run-import" class="button-primary">Run Import</button>
                <div id="import-progress-wrapper" style="display: none; margin-top: 20px;">
                    <progress id="import-progress" value="0" max="100"></progress>
                    <p><span id="progress-text">0</span>% complete</p>
                    <p><span id="processed-count">0</span> of <span id="total-count">0</span> records processed.</p>
                </div>
                 <div id="import-errors" style="display: none;">
                    <h3>Import Errors:</h3>
                    <ul id="error-list"></ul>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const panel = document.getElementById('osm-import-panel');
        const uploadForm = document.getElementById('osm-upload-form');
        const runImportBtn = document.getElementById('osm-run-import');
        const statusText = document.getElementById('osm-import-status');
        const progressWrapper = document.getElementById('import-progress-wrapper');
        const progressBar = document.getElementById('import-progress');
        const progressText = document.getElementById('progress-text');
        const processedCount = document.getElementById('processed-count');
        const totalCount = document.getElementById('total-count');
        const importErrors = document.getElementById('import-errors');
        const errorList = document.getElementById('error-list');

        let uploadedFileData = {};
        let currentJobId = null;
        let progressInterval = null;
        const restApiNonce = '<?php echo wp_create_nonce('wp_rest'); ?>';

        // Function to update UI based on job status
        function updateUIForJobStatus(job) {
            if (!job) {
                panel.classList.remove('uploaded', 'importing');
                panel.classList.add('initial');
                progressWrapper.style.display = 'none';
                return;
            }

            totalCount.textContent = job.total_rows;
            processedCount.textContent = job.processed_rows;
            const percentage = job.total_rows > 0 ? (job.processed_rows / job.total_rows) * 100 : 0;
            progressBar.value = percentage;
            progressText.textContent = Math.round(percentage);

            if (job.errors && job.errors.length > 0) {
                importErrors.style.display = 'block';
                errorList.innerHTML = '';
                job.errors.forEach(error => {
                    const li = document.createElement('li');
                    li.textContent = error;
                    errorList.appendChild(li);
                });
            } else {
                importErrors.style.display = 'none';
            }

            if (job.status === 'pending' || job.status === 'in_progress') {
                panel.classList.remove('initial', 'uploaded');
                panel.classList.add('importing');
                statusText.textContent = 'In Progress (' + job.status + ')';
                runImportBtn.style.display = 'none';
                progressWrapper.style.display = 'block';
            } else if (job.status === 'complete') {
                clearInterval(progressInterval);
                statusText.textContent = 'Complete';
                progressText.textContent = '100';
                panel.classList.remove('importing');
                panel.classList.add('uploaded');
                runImportBtn.style.display = 'none';
                // You might want to remove the file after completion from the server
            } else if (job.status === 'failed') {
                clearInterval(progressInterval);
                statusText.textContent = 'Failed';
                panel.classList.remove('importing');
                panel.classList.add('uploaded');
                runImportBtn.style.display = 'inline-block'; // Allow re-attempt if failed
            }
        }

        // Monitor progress function
        function monitorProgress(job_id) {
            currentJobId = job_id;
            progressInterval = setInterval(function() {
                fetch('<?php echo get_rest_url(null, "osm/v1/import-progress/"); ?>' + job_id, {
                    headers: {
                        'X-WP-Nonce': restApiNonce
                    }
                })
                    .then(response => response.json())
                    .then(job => {
                        if (job) {
                            updateUIForJobStatus(job);
                            if (job.status === 'complete' || job.status === 'failed') {
                                clearInterval(progressInterval);
                            }
                        } else {
                            // Job disappeared, clear interval
                            clearInterval(progressInterval);
                            updateUIForJobStatus(null);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching import progress:', error);
                        clearInterval(progressInterval);
                        statusText.textContent = 'Error monitoring progress.';
                        panel.classList.remove('importing');
                        panel.classList.add('initial');
                    });
            }, 3000);
        }

        // Check for existing job on page load
        const initial_job_id = '<?php echo get_transient("osm_current_import_job_id"); ?>';
        if (initial_job_id) {
            fetch('<?php echo get_rest_url(null, "osm/v1/import-progress/"); ?>' + initial_job_id, {
                headers: {
                    'X-WP-Nonce': restApiNonce
                }
            })
                .then(response => response.json())
                .then(job => {
                    if (job && (job.status === 'pending' || job.status === 'in_progress')) {
                        document.getElementById('osm-file-name').textContent = job.file_path.split('/').pop();
                        uploadedFileData = { filePath: job.file_path, importType: job.import_type };
                        monitorProgress(initial_job_id);
                    } else {
                        updateUIForJobStatus(null);
                    }
                })
                .catch(error => {
                    console.error('Error checking initial job status:', error);
                    updateUIForJobStatus(null);
                });
        }

        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            panel.classList.remove('initial', 'uploaded', 'importing');
            panel.classList.add('uploading');
            runImportBtn.style.display = 'none';

            const formData = new FormData(this);
            formData.append('action', 'osm_upload_csv');
            formData.append('nonce', '<?php echo wp_create_nonce("osm_ajax_nonce"); ?>');

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                panel.classList.remove('uploading');
                if (data.success) {
                    panel.classList.add('uploaded');
                    document.getElementById('osm-file-name').textContent = data.data.fileName;
                    statusText.textContent = 'Uploaded';
                    uploadedFileData = {
                        filePath: data.data.filePath,
                        importType: document.getElementById('import_type').value
                    };
                    runImportBtn.style.display = 'inline-block';
                    progressWrapper.style.display = 'none';
                    errorList.innerHTML = '';
                    importErrors.style.display = 'none';

                } else {
                    alert('Upload failed: ' + data.data);
                    panel.classList.add('initial');
                }
            })
            .catch(error => {
                console.error('Upload fetch error:', error);
                alert('An unexpected error occurred during upload.');
                panel.classList.remove('uploading');
                panel.classList.add('initial');
            });
        });

        runImportBtn.addEventListener('click', function() {
            if (!uploadedFileData.filePath) {
                alert('Please upload a CSV file first.');
                return;
            }

            statusText.textContent = 'Starting Import...';
            panel.classList.remove('uploaded');
            panel.classList.add('importing');
            runImportBtn.style.display = 'none';
            progressWrapper.style.display = 'block';

            const formData = new FormData();
            formData.append('action', 'osm_start_import');
            formData.append('nonce', '<?php echo wp_create_nonce("osm_ajax_nonce"); ?>');
            formData.append('file_path', uploadedFileData.filePath);
            formData.append('import_type', uploadedFileData.importType);

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    monitorProgress(data.data.job_id);
                } else {
                    alert('Failed to start import: ' + data.data);
                    panel.classList.remove('importing');
                    panel.classList.add('uploaded');
                    runImportBtn.style.display = 'inline-block';
                }
            })
            .catch(error => {
                console.error('Start import fetch error:', error);
                alert('An unexpected error occurred when starting import.');
                panel.classList.remove('importing');
                panel.classList.add('uploaded');
                runImportBtn.style.display = 'inline-block';
            });
        });
    });
    </script>
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

    echo '<p><label for="city_venue">Venue: </label>';
    echo '<input type="text" id="city_venue" name="city_venue" value="' . esc_attr( $venue ) . '" size="25" /></p>';
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

    if (isset($_GET['city_id'])) {
        $selected_city_id = intval($_GET['city_id']);
    }

    echo '<p><label for="sign_lat">Latitude: </label>';
    echo '<input type="text" id="sign_lat" name="sign_lat" value="' . esc_attr( $lat ) . '" size="25" /></p>';

    echo '<p><label for="sign_lng">Longitude: </label>';
    echo '<input type="text" id="sign_lng" name="sign_lng" value="' . esc_attr( $lng ) . '" size="25" /></p>';

    echo '<p><label for="sign_venue">Venue: </label>';
    echo '<input type="text" id="sign_venue" name="sign_venue" value="' . esc_attr( $venue ) . '" size="25" /></p>';
    
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

function osm_import_progress_endpoint() {
    register_rest_route('osm/v1', '/import-progress/(?P<job_id>[a-zA-Z0-9_]+)', array(
        'methods' => 'GET',
        'callback' => 'osm_get_import_progress',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ));
}
add_action('rest_api_init', 'osm_import_progress_endpoint');

function osm_get_import_progress($data) {
    $job_id = $data['job_id'];
    $job = get_option($job_id);
    if (!$job) {
        return new WP_Error('not_found', 'Import job not found.', array('status' => 404));
    }
    return new WP_REST_Response($job, 200);
}

// REST API endpoint for cities
add_action( 'rest_api_init', function () {
    register_rest_route( 'osm/v1', '/cities', array(
        'methods' => 'GET',
        'callback' => 'get_cities_data',
        'permission_callback' => '__return_true'
    ) );
} );

function get_cities_data() {
    $args = array(
        'post_type' => 'city',
        'posts_per_page' => -1,
    );
    $posts = get_posts( $args );
    $data = array();
    foreach ( $posts as $post ) {
        $data[] = array(
            'name' => $post->post_title,
            'coords' => [
                (float) get_post_meta( $post->ID, '_city_lng', true ),
                (float) get_post_meta( $post->ID, '_city_lat', true )
            ],
            'count' => (int) get_post_meta( $post->ID, '_city_count', true ),
            'venue' => get_post_meta( $post->ID, '_city_venue', true ),
            'img' => get_the_post_thumbnail_url( $post->ID, 'full' ),
        );
    }
    return $data;
}

// REST API endpoint for signs
add_action( 'rest_api_init', function () {
    register_rest_route( 'osm/v1', '/signs', array(
        'methods' => 'GET',
        'callback' => 'get_signs_data',
        'permission_callback' => '__return_true'
    ) );
} );

function get_signs_data() {
    $args = array(
        'post_type' => 'sign',
        'posts_per_page' => -1,
    );
    $posts = get_posts( $args );
    $data = array();
    foreach ( $posts as $post ) {
        $data[] = array(
            'city' => get_post_meta( $post->ID, '_sign_city', true ),
            'title' => $post->post_title,
            'venue' => get_post_meta( $post->ID, '_sign_venue', true ),
            'coords' => [
                (float) get_post_meta( $post->ID, '_sign_lat', true ),
                (float) get_post_meta( $post->ID, '_sign_lng', true )
            ],
            'img' => get_the_post_thumbnail_url( $post->ID, 'full' ),
            'href' => '#',
        );
    }
    return $data;
}



