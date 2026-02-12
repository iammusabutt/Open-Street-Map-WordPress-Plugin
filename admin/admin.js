document.addEventListener('DOMContentLoaded', function () {
    // Tab switching
    const tabs = document.querySelectorAll('.osm-admin-tabs a');
    const panes = document.querySelectorAll('.osm-admin-tab-pane');

    tabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
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

        fileInput.addEventListener('change', function () {
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
    const ajaxNonce = (window.osm_admin_vars && window.osm_admin_vars.nonce) ? window.osm_admin_vars.nonce : '';

    const uploadForms = document.querySelectorAll('.osm-upload-form');

    uploadForms.forEach(form => {
        form.addEventListener('submit', function (e) {
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

            fetch(ajaxurl, {
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

    const importElement = document.getElementById('import');
    if (importElement) {
        importElement.addEventListener('click', function (e) {
            const target = e.target;
            const fileItem = target.closest('.uploaded-file-item');
            if (!fileItem) return;

            const filePath = fileItem.dataset.filePath;
            const importType = fileItem.dataset.importType;

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

                fetch(ajaxurl, {
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

                fetch(ajaxurl, {
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
    }

    function processBatch(jobId, progressBar, progressText, importButton) {
        const processData = new FormData();
        processData.append('action', 'osm_process_batch');
        processData.append('nonce', ajaxNonce);
        processData.append('job_id', jobId);

        fetch(ajaxurl, {
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
        pinUploadForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const spinner = this.querySelector('.spinner');
            spinner.style.visibility = 'visible';

            const formData = new FormData(this);
            formData.append('action', 'osm_upload_pin');
            formData.append('nonce', ajaxNonce);

            fetch(ajaxurl, {
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
        pinListContainer.addEventListener('click', function (e) {
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

                fetch(ajaxurl, {
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

    // Save settings (General)
    const settingsForm = document.getElementById('osm-settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function (e) {
            e.preventDefault();
            saveSettings(this);
        });
    }

    // Save settings (Colors)
    const colorsForm = document.getElementById('osm-settings-form-colors');
    if (colorsForm) {
        colorsForm.addEventListener('submit', function (e) {
            e.preventDefault();
            saveSettings(this);
        });
    }

    // Save settings (Layers)
    const layersForm = document.getElementById('osm-settings-form-layers');
    const layerOptions = document.querySelectorAll('.layer-option');

    // Visual selection logic for layers
    if (layerOptions.length > 0) {
        layerOptions.forEach(option => {
            option.addEventListener('click', function () {
                // Remove selected class from all
                layerOptions.forEach(opt => opt.classList.remove('selected'));
                // Add to clicked
                this.classList.add('selected');
                // Check the radio button inside
                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        });
    }

    if (layersForm) {
        layersForm.addEventListener('submit', function (e) {
            e.preventDefault();
            saveSettings(this);
        });
    }

    function saveSettings(form) {
        const formData = new FormData(form);
        formData.append('action', 'osm_save_settings');
        formData.append('nonce', ajaxNonce);

        fetch(ajaxurl, {
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
    }

    // Color Picker Initialization
    if (typeof jQuery !== 'undefined') {
        jQuery('.osm-color-field').wpColorPicker();
    }

    // Modern Options Panel Tabs (Signs & Cities)
    const panelTabs = document.querySelectorAll('.osm-tabs a');
    const panelPanes = document.querySelectorAll('.osm-tab-content .osm-tab-pane');

    if (panelTabs.length > 0) {
        panelTabs.forEach(tab => {
            tab.addEventListener('click', function (e) {
                e.preventDefault();

                // Find the parent panel to scope this interaction
                const panel = this.closest('.osm-panel');
                const tabs = panel.querySelectorAll('.osm-tabs a');
                const panes = panel.querySelectorAll('.osm-tab-content .osm-tab-pane');

                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                const targetId = this.getAttribute('href').substring(1);
                panes.forEach(pane => {
                    if (pane.id === targetId) {
                        pane.classList.add('active');
                    } else {
                        pane.classList.remove('active');
                    }
                });
            });
        });
    }

    // Sign CTA Behavior Logic
    const ctaRadios = document.querySelectorAll('input[name="sign_cta_behavior"]');
    const ctaUrlWrapper = document.getElementById('sign_cta_url_wrapper');

    if (ctaRadios.length > 0 && ctaUrlWrapper) {
        ctaRadios.forEach(radio => {
            radio.addEventListener('change', function () {
                if (this.value === 'custom') {
                    ctaUrlWrapper.style.display = 'block';
                } else {
                    ctaUrlWrapper.style.display = 'none';
                }
            });
        });
    }
});
