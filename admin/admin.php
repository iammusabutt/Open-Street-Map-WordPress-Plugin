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
                    <form id="osm-settings-form">
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
            const form = fileInput.closest('form');
            const uploadButton = form.querySelector('.osm-upload-button');
            
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    filenameSpan.textContent = this.files[0].name;
                    uploadButton.disabled = false;
                } else {
                    filenameSpan.textContent = 'No file chosen';
                    uploadButton.disabled = true;
                }
            });
        }

        setupCustomFileInput('csv_file_cities');
        setupCustomFileInput('csv_file_signs');
        setupCustomFileInput('pin_file');


        // CSV import script
        const ajaxNonce = '<?php echo wp_create_nonce("osm_ajax_nonce"); ?>';
        const uploadForms = document.querySelectorAll('.osm-upload-form');

        uploadForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const uploadButton = this.querySelector('.osm-upload-button');
                const spinner = this.querySelector('.spinner');
                const uploadedFilesList = this.nextElementSibling;
                const importType = this.dataset.importType;

                uploadButton.disabled = true;
                spinner.style.visibility = 'visible';

                const formData = new FormData(this);
                formData.append('action', 'osm_upload_csv');
                formData.append('nonce', ajaxNonce);

                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    spinner.style.visibility = 'hidden';
                    if (data.success) {
                        showToast('File uploaded successfully.', 'success');
                        const file = data.data;
                        const fileItem = document.createElement('div');
                        fileItem.classList.add('uploaded-file-item');
                        fileItem.dataset.filePath = file.filePath;
                        fileItem.dataset.importType = importType; // Store import type
                        fileItem.innerHTML = `
                            <span class="file-name">${file.fileName}</span>
                            <div class="file-actions">
                                <button class="button-primary import-file">Import</button>
                                <button class="button-secondary delete-file">Delete</button>
                            </div>
                            <div class="import-progress-wrapper" style="display: none;">
                                <progress class="import-progress" value="0" max="100"></progress>
                                <p><span class="progress-text">0</span>% complete</p>
                            </div>
                        `;
                        uploadedFilesList.appendChild(fileItem);
                        form.reset();
                        // Reset file name display and disable button
                        const filenameSpan = form.querySelector('.custom-file-upload-filename');
                        if (filenameSpan) {
                            filenameSpan.textContent = 'No file chosen';
                        }
                        uploadButton.disabled = true;
                    } else {
                        showToast('Upload failed: ' + data.data, 'error');
                        uploadButton.disabled = false;
                    }
                })
                .catch(error => {
                    spinner.style.visibility = 'hidden';
                    console.error('Upload fetch error:', error);
                    showToast('An unexpected error occurred during upload.', 'error');
                    uploadButton.disabled = false;
                });
            });
        });

        document.getElementById('import').addEventListener('click', function(e) {
            const target = e.target;
            const fileItem = target.closest('.uploaded-file-item');
            if (!fileItem) return;

            const filePath = fileItem.dataset.filePath;
            const importType = fileItem.dataset.importType; // Retrieve import type

            if (target.classList.contains('import-file')) {
                target.disabled = true;
                const progressWrapper = fileItem.querySelector('.import-progress-wrapper');
                progressWrapper.style.display = 'block';
                const progressBar = fileItem.querySelector('.import-progress');
                const progressText = fileItem.querySelector('.progress-text');

                const startImportData = new FormData();
                startImportData.append('action', 'osm_start_import');
                startImportData.append('nonce', ajaxNonce);
                startImportData.append('file_path', filePath);
                startImportData.append('import_type', importType);

                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    body: startImportData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Import started.', 'success');
                        const jobId = data.data.job_id;
                        processBatch(jobId, progressBar, progressText, target);
                    } else {
                        showToast('Failed to start import: ' + data.data, 'error');
                        target.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Start import fetch error:', error);
                    showToast('An unexpected error occurred when starting import.', 'error');
                    target.disabled = false;
                });
            }

            if (target.classList.contains('delete-file')) {
                if (!confirm('Are you sure you want to delete this file?')) return;

                const formData = new FormData();
                formData.append('action', 'osm_delete_csv');
                formData.append('nonce', ajaxNonce);
                formData.append('file_path', filePath);

                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('File deleted successfully.', 'success');
                        fileItem.remove();
                    } else {
                        showToast('Failed to delete file: ' + data.data, 'error');
                    }
                })
                .catch(error => {
                    console.error('Delete file fetch error:', error);
                    showToast('An unexpected error occurred while deleting the file.', 'error');
                });
            }
        });

        function processBatch(jobId, progressBar, progressText, importButton) {
            const processData = new FormData();
            processData.append('action', 'osm_process_batch');
            processData.append('nonce', ajaxNonce);
            processData.append('job_id', jobId);

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: processData
            })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    const job = response.data;
                    const percentage = job.total_rows > 0 ? (job.processed_rows / job.total_rows) * 100 : 0;
                    progressBar.value = percentage;
                    progressText.textContent = Math.round(percentage);

                    if (job.status === 'complete') {
                        showToast('Import complete.', 'success');
                        importButton.closest('.uploaded-file-item').remove();
                    } else if (job.status === 'failed') {
                        showToast('Import failed.', 'error');
                        importButton.disabled = false;
                    } else {
                        processBatch(jobId, progressBar, progressText, importButton);
                    }
                } else {
                    showToast('An error occurred during import.', 'error');
                    importButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Process batch fetch error:', error);
                showToast('An unexpected error occurred during import.', 'error');
                importButton.disabled = false;
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

        // Save settings
        const settingsForm = document.getElementById('osm-settings-form');
        if (settingsForm) {
            settingsForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'osm_save_settings');
                formData.append('nonce', ajaxNonce);

                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Settings saved successfully.', 'success');
                    } else {
                        showToast('Failed to save settings: ' + data.data, 'error');
                    }
                })
                .catch(error => {
                    console.error('Save settings fetch error:', error);
                    showToast('An unexpected error occurred while saving settings.', 'error');
                });
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
