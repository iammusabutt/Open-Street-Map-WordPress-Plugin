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

function osm_enqueue_admin_scripts($hook) {
    if ($hook !== 'openstreetmap_page_osm-settings') {
        return;
    }
    wp_enqueue_style( 'osm-admin-style', plugin_dir_url( __FILE__ ) . 'css/admin-style.css' );
}
add_action( 'admin_enqueue_scripts', 'osm_enqueue_admin_scripts' );

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

function osm_get_pins() {
    $pins_dir = plugin_dir_path( __FILE__ ) . 'assets/pins/';
    $pins_url = plugin_dir_url( __FILE__ ) . 'assets/pins/';
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

function osm_allow_svg_upload_mimes($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}

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

    $target_dir = plugin_dir_path( __FILE__ ) . 'assets/pins/';
    $filename = sanitize_file_name($file['name']);
    $new_file_path = $target_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $new_file_path)) {
        wp_send_json_success(array(
            'message' => 'Pin uploaded successfully.',
            'pin' => array(
                'name' => $filename,
                'venue' => pathinfo($filename, PATHINFO_FILENAME),
                'url' => plugin_dir_url( __FILE__ ) . 'assets/pins/' . $filename
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
    $file_path = plugin_dir_path( __FILE__ ) . 'assets/pins/' . $filename;

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
    </style>
    <div class="wrap osm-admin-wrapper">
        <div class="osm-admin-header">
            <h1><span class="dashicons dashicons-location-alt" style="font-size: 30px; margin-right: 10px; vertical-align: middle;"></span>OpenStreetMap</h1>
            <div>
                <button class="button-primary">Save Changes</button>
                <button class="button-secondary">Reset All</button>
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
                </ul>
            </div>
                <div id="settings" class="osm-admin-tab-pane active">
                    <h2 class="osm-tab-title">General Settings</h2>
                    <p>This is where the general settings will go.</p>
                </div>
                <div id="import" class="osm-admin-tab-pane">
                    <div id="osm-import-panel">
                        <div id="osm-upload-step">
                            <h2 class="osm-tab-title">Import Tool</h2>
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
                                    <label>CSV File:</label>
                                    <div class="custom-file-upload-wrapper">
                                        <label for="csv_file" class="custom-file-upload-label">Choose CSV File</label>
                                        <input type="file" name="csv_file" id="csv_file" class="custom-file-upload-input" accept=".csv">
                                        <span class="custom-file-upload-filename">No file chosen</span>
                                    </div>
                                </p>
                                <p class="submit">
                                    <button type="submit" class="button-primary">Upload File</button>
                                    <span class="spinner"></span>
                                </p>
                            </form>
                        </div>
                        <div id="osm-progress-step" style="display:none;">
                            <h2 class="osm-tab-title">Import Status</h2>
                            <p>File: <span id="osm-file-name"></span></p>
                            <p>Status: <strong id="osm-import-status">Uploaded</strong></p>
                            <button id="osm-run-import" class="button-primary">Run Import</button>
                            <div id="import-progress-wrapper" style="display: none; margin-top: 20px;">
                                <progress id="import-progress" value="0" max="100"></progress>
                                <p><span id="progress-text">0</span>% complete</p>
                                <p><span id="processed-count">0</span> of <span id="total-count">0</span> records processed.</p>
                            </div>
                            <div id="import-errors" style="display: none;">
                                <h3 class="osm-tab-title">Import Errors:</h3>
                                <ul id="error-list"></ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="colors" class="osm-admin-tab-pane">
                    <h2 class="osm-tab-title">Color Settings</h2>
                    <p>This is where the color settings will go.</p>
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching
        const tabs = document.querySelectorAll('.osm-admin-tabs a');
        const panes = document.querySelectorAll('.osm-admin-tab-pane');

        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                tabs.forEach(t => t.classList.remove('nav-tab-active'));
                this.classList.add('nav-tab-active');

                panes.forEach(pane => {
                    if (pane.id === this.hash.substring(1)) {
                        pane.classList.add('active');
                    } else {
                        pane.classList.remove('active');
                    }
                });
            });
        });

        // Custom file input handler
        function setupCustomFileInput(inputId) {
            const fileInput = document.getElementById(inputId);
            if (!fileInput) return;

            const filenameSpan = fileInput.closest('.custom-file-upload-wrapper').querySelector('.custom-file-upload-filename');
            
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    filenameSpan.textContent = this.files[0].name;
                } else {
                    filenameSpan.textContent = 'No file chosen';
                }
            });
        }

        setupCustomFileInput('csv_file');
        setupCustomFileInput('pin_file');


        // CSV import script
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
        const ajaxNonce = '<?php echo wp_create_nonce("osm_ajax_nonce"); ?>';

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
            } else if (job.status === 'failed') {
                clearInterval(progressInterval);
                statusText.textContent = 'Failed';
                panel.classList.remove('importing');
                panel.classList.add('uploaded');
                runImportBtn.style.display = 'inline-block';
            }
        }

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

        if (uploadForm) {
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
                formData.append('nonce', ajaxNonce);

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
                formData.append('nonce', ajaxNonce);
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
        }

        // Toaster notification function
        function showToast(message, type = 'success') {
            const container = document.getElementById('osm-toaster-container');
            const toast = document.createElement('div');
            toast.className = `osm-toast osm-toast-${type}`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }

        // Pin management script
        const pinUploadForm = document.getElementById('osm-upload-pin-form');
        if (pinUploadForm) {
            pinUploadForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const spinner = this.querySelector('.spinner');
                spinner.style.visibility = 'visible';

                const formData = new FormData(this);
                formData.append('action', 'osm_upload_pin');
                formData.append('nonce', ajaxNonce);

                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    spinner.style.visibility = 'hidden';
                    if (data.success) {
                        showToast(data.data.message, 'success');
                        // Add new pin to the list
                        const pinList = document.getElementById('osm-pin-list');
                        const newPin = data.data.pin;
                        const pinItem = document.createElement('div');
                        pinItem.classList.add('pin-item');
                        pinItem.dataset.pinName = newPin.name;
                        pinItem.innerHTML = `
                            <img src="${newPin.url}" alt="${newPin.venue}">
                            <span class="pin-name">${newPin.name}</span>
                            <span class="pin-venue">Venue: "${newPin.venue}"</span>
                            <button class="button-secondary delete-pin">Delete</button>
                        `;
                        pinList.appendChild(pinItem);
                        pinUploadForm.reset();
                    } else {
                        showToast('Upload failed: ' + data.data, 'error');
                    }
                })
                .catch(error => {
                    spinner.style.visibility = 'hidden';
                    console.error('Upload fetch error:', error);
                    showToast('An unexpected error occurred during upload.', 'error');
                });
            });
        }

        const pinListContainer = document.getElementById('osm-pin-list');
        if (pinListContainer) {
            pinListContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('delete-pin')) {
                    if (!confirm('Are you sure you want to delete this pin?')) {
                        return;
                    }

                    const pinItem = e.target.closest('.pin-item');
                    const pinName = pinItem.dataset.pinName;

                    const formData = new FormData();
                    formData.append('action', 'osm_delete_pin');
                    formData.append('nonce', ajaxNonce);
                    formData.append('pin_name', pinName);

                    fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.data, 'success');
                            pinItem.remove();
                        } else {
                            showToast('Deletion failed: ' + data.data, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Delete fetch error:', error);
                        showToast('An unexpected error occurred during deletion.', 'error');
                    });
                }
            });
        }
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



