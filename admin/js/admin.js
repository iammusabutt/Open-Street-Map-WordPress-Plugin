document.addEventListener('DOMContentLoaded', function () {
    console.log('OSM JS Loaded - Debug Version ' + new Date().toISOString());

    // Bulk Delete Action Helper (Delete All Signs / Delete Orphaned Cities)
    const ajaxNonce = (window.osm_admin_vars && window.osm_admin_vars.nonce) ? window.osm_admin_vars.nonce : '';

    function setupBulkDeleteAction(btnId, type, spinnerId) {
        const btn = document.getElementById(btnId);
        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();

                const actionText = type === 'all_signs' ? 'DELETE ALL SIGNS' : 'DELETE ORPHANED CITIES';

                if (!confirm(`WARNING: Are you sure you want to ${actionText}? This action cannot be undone.`)) {
                    return;
                }

                if (!confirm(`Double check: Really ${actionText}?`)) {
                    return;
                }

                const logContainer = document.getElementById('osm-bulk-action-log');
                const spinner = document.getElementById(spinnerId);

                // Reset UI
                if (logContainer) {
                    logContainer.style.display = 'block';
                    logContainer.innerHTML = ''; // Start fresh
                    // Add timestamp
                    const now = new Date().toISOString().replace('T', ' ').split('.')[0];
                    logContainer.innerHTML += `<div>${now} Starting ${actionText}...</div>`;
                }

                // Disable buttons
                btn.disabled = true;
                if (spinner) spinner.style.visibility = 'visible';

                function processBatch() {
                    const formData = new FormData();
                    formData.append('action', 'osm_bulk_delete');
                    formData.append('nonce', ajaxNonce);
                    formData.append('type', type);

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Append logs
                                if (data.data.logs && logContainer) {
                                    const now = new Date().toISOString().replace('T', ' ').split('.')[0];
                                    data.data.logs.forEach(msg => {
                                        logContainer.innerHTML += `<div>${now} ${msg}</div>`;
                                    });
                                    logContainer.scrollTop = logContainer.scrollHeight;
                                }

                                if (data.data.status === 'continue') {
                                    // Recursive call for next batch
                                    processBatch();
                                } else {
                                    // Complete
                                    if (spinner) spinner.style.visibility = 'hidden';
                                    btn.disabled = false;
                                    if (logContainer) logContainer.innerHTML += `<div>Process complete.</div>`;
                                    logContainer.scrollTop = logContainer.scrollHeight;
                                    showToast('Bulk action complete.', 'success');
                                }
                            } else {
                                // Error
                                if (spinner) spinner.style.visibility = 'hidden';
                                btn.disabled = false;
                                if (logContainer) logContainer.innerHTML += `<div style="color:red">Error: ${data.data}</div>`;
                                showToast('Error during bulk action', 'error');
                            }
                        })
                        .catch(error => {
                            if (spinner) spinner.style.visibility = 'hidden';
                            btn.disabled = false;
                            console.error('Fetch error:', error);
                            if (logContainer) logContainer.innerHTML += `<div style="color:red">Network Error: ${error}</div>`;
                        });
                }

                // Start the process
                processBatch();
            });
        }
    }

    setupBulkDeleteAction('osm-delete-all-signs-btn', 'all_signs', 'osm-delete-all-signs-spinner');
    setupBulkDeleteAction('osm-delete-orphaned-cities-btn', 'orphaned_cities', 'osm-delete-orphaned-cities-spinner');

    // Tab switching
    const tabs = document.querySelectorAll('.osm-admin-tabs a');
    const panes = document.querySelectorAll('.osm-admin-tab-pane');

    // Restore active tab from localStorage
    const savedTab = localStorage.getItem('osm_admin_active_tab');
    if (savedTab) {
        const targetTab = Array.from(tabs).find(t => t.hash === savedTab);
        if (targetTab) {
            tabs.forEach(t => t.classList.remove('nav-tab-active'));
            targetTab.classList.add('nav-tab-active');

            panes.forEach(pane => pane.classList.remove('active'));
            const targetPane = document.getElementById(savedTab.substring(1));
            if (targetPane) {
                targetPane.classList.add('active');
            }
        }
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
            e.preventDefault();

            tabs.forEach(t => t.classList.remove('nav-tab-active'));
            this.classList.add('nav-tab-active');

            localStorage.setItem('osm_admin_active_tab', this.hash);

            panes.forEach(pane => {
                if (pane.id === this.hash.substring(1)) {
                    pane.classList.add('active');
                    if (pane.id === 'dashboard') loadDashboardStats();
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

    // Modern Range Slider Input logic
    const rangeSliderWrappers = document.querySelectorAll('.osm-range-slider-wrapper');
    if (rangeSliderWrappers.length > 0) {
        rangeSliderWrappers.forEach(wrapper => {
            const slider = wrapper.querySelector('.osm-range-slider');
            const display = wrapper.querySelector('.osm-range-value-display');
            const hiddenInput = wrapper.querySelector('input[type="hidden"]');

            if (slider && display && hiddenInput) {
                slider.addEventListener('input', function () {
                    display.textContent = this.value;
                    hiddenInput.value = this.value;
                });
            }
        });
    }

    // CSV import script
    // const ajaxNonce is already defined at the top

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




    // Remove Duplicates Helper
    function setupRemoveDuplicates(btnId, type) {
        const btn = document.getElementById(btnId);
        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const typeLabel = type === 'city' ? 'cities' : 'signs';
                const dryRunEl = document.getElementById('osm-dry-run');
                const isDryRun = dryRunEl ? dryRunEl.checked : false;
                const actionText = isDryRun ? 'simulate removing' : 'remove';

                if (!confirm(`Are you sure you want to ${actionText} duplicate ${typeLabel}? ${isDryRun ? '' : 'This action cannot be undone.'}`)) {
                    return;
                }

                const logContainer = document.getElementById('osm-log-container');
                const clearLogBtn = document.getElementById('osm-clear-log-btn');
                const spinner = document.getElementById('osm-duplicates-spinner');

                // Reset UI
                if (logContainer) {
                    logContainer.style.display = 'block';
                    logContainer.innerHTML = ''; // Start fresh
                    clearLogBtn.style.display = 'inline-block';
                    // Add timestamp
                    const now = new Date().toISOString().replace('T', ' ').split('.')[0];
                    logContainer.innerHTML += `<div>${now} Starting process for ${type}...</div>`;
                }

                // Disable buttons
                const allBtns = document.querySelectorAll('#osm-remove-cities-btn, #osm-remove-signs-btn');
                allBtns.forEach(b => b.disabled = true);
                spinner.style.visibility = 'visible';

                function processBatch() {
                    const formData = new FormData();
                    formData.append('action', 'osm_remove_duplicates');
                    formData.append('nonce', ajaxNonce);
                    formData.append('type', type);
                    formData.append('dry_run', isDryRun ? '1' : '0');

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Append logs
                                if (data.data.logs && logContainer) {
                                    const now = new Date().toISOString().replace('T', ' ').split('.')[0];
                                    data.data.logs.forEach(msg => {
                                        logContainer.innerHTML += `<div>${now} ${msg}</div>`;
                                    });
                                    logContainer.scrollTop = logContainer.scrollHeight;
                                }

                                if (data.data.status === 'continue') {
                                    // Recursive call for next batch
                                    processBatch();
                                } else {
                                    // Complete
                                    spinner.style.visibility = 'hidden';
                                    allBtns.forEach(b => b.disabled = false);
                                    logContainer.innerHTML += `<div>Process complete.</div>`;
                                    logContainer.scrollTop = logContainer.scrollHeight;
                                }
                            } else {
                                // Error
                                spinner.style.visibility = 'hidden';
                                allBtns.forEach(b => b.disabled = false);
                                if (logContainer) logContainer.innerHTML += `<div style="color:red">Error: ${data.data}</div>`;
                                showToast('Error removing duplicates', 'error');
                            }
                        })
                        .catch(error => {
                            spinner.style.visibility = 'hidden';
                            allBtns.forEach(b => b.disabled = false);
                            console.error('Fetch error:', error);
                            if (logContainer) logContainer.innerHTML += `<div style="color:red">Network Error: ${error}</div>`;
                        });
                }

                // Start the process
                processBatch();
            });
        }
    }

    setupRemoveDuplicates('osm-remove-cities-btn', 'city');
    setupRemoveDuplicates('osm-remove-signs-btn', 'sign');

    // Clear log button
    const clearLogBtn = document.getElementById('osm-clear-log-btn');
    if (clearLogBtn) {
        clearLogBtn.addEventListener('click', function () {
            const logContainer = document.getElementById('osm-log-container');
            if (logContainer) logContainer.innerHTML = '';
        });
    }

    // Bubble Sync
    const bubbleSyncBtn = document.getElementById('osm-bubble-sync-btn');
    if (bubbleSyncBtn) {
        bubbleSyncBtn.addEventListener('click', function (e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to run Bubble Sync? This will update the display count for all cities based on their currently assigned signs.')) {
                return;
            }

            const logContainer = document.getElementById('osm-bubble-sync-log');
            const spinner = document.getElementById('osm-bubble-sync-spinner');

            if (logContainer) {
                logContainer.style.display = 'block';
                logContainer.innerHTML = '';
                const now = new Date().toISOString().replace('T', ' ').split('.')[0];
                logContainer.innerHTML += `<div>${now} Starting Bubble Sync...</div>`;
            }

            bubbleSyncBtn.disabled = true;
            if (spinner) spinner.style.visibility = 'visible';

            const formData = new FormData();
            formData.append('action', 'osm_bubble_sync');
            formData.append('nonce', ajaxNonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (spinner) spinner.style.visibility = 'hidden';
                    bubbleSyncBtn.disabled = false;

                    if (data.success) {
                        if (logContainer) {
                            const now = new Date().toISOString().replace('T', ' ').split('.')[0];
                            logContainer.innerHTML += `<div>${now} ${data.data.message}</div>`;
                            logContainer.scrollTop = logContainer.scrollHeight;
                        }
                        if (typeof showToast === 'function') {
                            showToast('Bubble sync complete.', 'success');
                        }
                    } else {
                        if (logContainer) logContainer.innerHTML += `<div style="color:red">Error: ${data.data}</div>`;
                        if (typeof showToast === 'function') {
                            showToast('Error during bubble sync', 'error');
                        }
                    }
                })
                .catch(error => {
                    if (spinner) spinner.style.visibility = 'hidden';
                    bubbleSyncBtn.disabled = false;
                    console.error('Fetch error:', error);
                    if (logContainer) logContainer.innerHTML += `<div style="color:red">Network Error: ${error}</div>`;
                    if (typeof showToast === 'function') {
                        showToast('Network error during bubble sync', 'error');
                    }
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

    // Save settings (Search)
    const searchForm = document.getElementById('osm-settings-form-search');
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            saveSettings(this);
        });
    }

    // Save settings (Map Box)
    const mapboxForm = document.getElementById('osm-settings-form-mapbox');
    if (mapboxForm) {
        mapboxForm.addEventListener('submit', function (e) {
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
        try {
            if (jQuery.fn.wpColorPicker) {
                jQuery('.osm-color-field').wpColorPicker();
            } else {
                console.warn('wpColorPicker not available in jQuery.fn');
            }
        } catch (e) {
            console.error('Error initializing color picker:', e);
        }
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

    let chartTimeline = null;
    let chartStatus = null;
    let chartSources = null;

    function loadDashboardStats() {
        const tbody = document.getElementById('osm-recent-searches');
        const totalEl = document.getElementById('osm-stat-total');
        const foundEl = document.getElementById('osm-stat-found');
        if (!tbody) return;

        const dateFilter = document.getElementById('osm-dashboard-date-filter');
        const filterValue = dateFilter ? dateFilter.value : 'all_time';

        const formData = new FormData();
        formData.append('action', 'osm_get_dashboard_stats');
        formData.append('nonce', ajaxNonce);
        formData.append('date_filter', filterValue);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const total = parseInt(data.data.total) || 0;
                    const found = parseInt(data.data.found) || 0;
                    const recent = data.data.recent || [];

                    totalEl.textContent = total;
                    foundEl.textContent = total > 0 ? Math.round((found / total) * 100) + '%' : '0%';

                    tbody.innerHTML = '';
                    if (recent.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5">No searches yet.</td></tr>';
                    } else {
                        recent.forEach(row => {
                            const tr = document.createElement('tr');

                            let statusColor = row.found_status === 'found' ? '#46b450' : '#dc3232';
                            let sourceBadge = '';
                            if (row.source === 'local') sourceBadge = '<span style="background:#e5f5fa;color:#007cba;padding:2px 6px;border-radius:3px;font-size:11px;">Local DB</span>';
                            else if (row.source === 'nominatim') sourceBadge = '<span style="background:#f0f0f1;color:#50575e;padding:2px 6px;border-radius:3px;font-size:11px;">Nominatim</span>';
                            else if (row.source === 'fuse') sourceBadge = '<span style="background:#fcf0f1;color:#d63638;padding:2px 6px;border-radius:3px;font-size:11px;">Fuzzy</span>';
                            else sourceBadge = '<span style="color:#999">-</span>';

                            tr.innerHTML = `
                            <td><strong>${row.search_query}</strong></td>
                            <td><span style="display:inline-block;background:#f0f0f1;padding:2px 8px;border-radius:10px;font-weight:bold;font-size:12px;color:#3c434a;">${row.search_count}</span></td>
                            <td>${new Date(row.latest_time).toLocaleString()}</td>
                            <td style="color:${statusColor};font-weight:600;">${row.found_status.toUpperCase()}</td>
                            <td>${sourceBadge}</td>
                        `;
                            tbody.appendChild(tr);
                        });
                    }

                    const chartsObj = data.data.charts || {};
                    // --- Timeline Chart ---
                    const tLabels = chartsObj.timeline ? chartsObj.timeline.map(r => r.date) : [];
                    const tData = chartsObj.timeline ? chartsObj.timeline.map(r => r.count) : [];
                    if (chartTimeline) {
                        chartTimeline.data.labels = tLabels;
                        chartTimeline.data.datasets[0].data = tData;
                        chartTimeline.update();
                    } else if (document.getElementById('osm-chart-timeline')) {
                        chartTimeline = new Chart(document.getElementById('osm-chart-timeline'), {
                            type: 'line',
                            data: {
                                labels: tLabels,
                                datasets: [{
                                    label: 'Total Searches',
                                    data: tData,
                                    borderColor: '#2271b1',
                                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                                    tension: 0.3,
                                    fill: true
                                }]
                            },
                            options: { responsive: true, maintainAspectRatio: false }
                        });
                    }

                    // --- Status Chart ---
                    const sLabels = chartsObj.status ? chartsObj.status.map(r => r.found_status.toUpperCase()) : [];
                    const sData = chartsObj.status ? chartsObj.status.map(r => r.count) : [];
                    if (chartStatus) {
                        chartStatus.data.labels = sLabels;
                        chartStatus.data.datasets[0].data = sData;
                        chartStatus.update();
                    } else if (document.getElementById('osm-chart-status')) {
                        chartStatus = new Chart(document.getElementById('osm-chart-status'), {
                            type: 'doughnut',
                            data: {
                                labels: sLabels,
                                datasets: [{
                                    data: sData,
                                    backgroundColor: ['#46b450', '#dc3232', '#f0b840']
                                }]
                            },
                            options: { responsive: true, maintainAspectRatio: false }
                        });
                    }

                    // --- Sources Chart ---
                    const srcLabels = chartsObj.sources ? chartsObj.sources.map(r => r.source.toUpperCase()) : [];
                    const srcData = chartsObj.sources ? chartsObj.sources.map(r => r.count) : [];
                    if (chartSources) {
                        chartSources.data.labels = srcLabels;
                        chartSources.data.datasets[0].data = srcData;
                        chartSources.update();
                    } else if (document.getElementById('osm-chart-sources')) {
                        chartSources = new Chart(document.getElementById('osm-chart-sources'), {
                            type: 'bar',
                            data: {
                                labels: srcLabels,
                                datasets: [{
                                    label: 'Source',
                                    data: srcData,
                                    backgroundColor: ['#007cba', '#50575e', '#d63638', '#999999']
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } }
                            }
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Dashboard fetch error:', error);
                tbody.innerHTML = '<tr><td colspan="5" style="color:red;">Error loading data</td></tr>';
            });
    }

    // Load initial dashboard stats if it exists
    const dashboardPane = document.getElementById('dashboard');
    if (dashboardPane && dashboardPane.classList.contains('active')) {
        loadDashboardStats();
    }

    const dateFilterDropdown = document.getElementById('osm-dashboard-date-filter');
    if (dateFilterDropdown) {
        dateFilterDropdown.addEventListener('change', loadDashboardStats);
    }

});
