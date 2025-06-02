document.addEventListener('DOMContentLoaded', function () {
    const {
        ajax_url,
        nonce,
        get_plugin_content_action,
        get_plan_action,
        apply_action,
        revert_action, // New action
        optimizer_backups, // New data
        loading_messages,
        error_messages
    } = wpAutopluginOptimizer;

    const selectElement = document.getElementById('optimize-plugin-select');
    const getPlanButton = document.getElementById('get-optimization-plan-btn');
    const planDisplayContainer = document.getElementById('optimization-plan-display-container');
    const planDisplay = document.getElementById('optimization-plan-display');
    const applyButtonContainer = document.getElementById('apply-optimization-btn-container');
    const applyButton = document.getElementById('apply-optimization-btn');
    const messagesDiv = document.getElementById('optimizer-messages');

    // Revert UI elements
    const revertInfoContainer = document.getElementById('revert-info-container');
    const backupTimestampDisplay = document.getElementById('backup-timestamp-display');
    const revertButton = document.getElementById('revert-plugin-btn');

    let currentPluginFile = '';
    let currentPluginCode = '';
    let currentAiPlan = '';

    // Initialize UI
    if (planDisplayContainer) planDisplayContainer.style.display = 'none';
    if (applyButtonContainer) applyButtonContainer.style.display = 'none';
    if (revertInfoContainer) revertInfoContainer.style.display = 'none';
    if (planDisplay) planDisplay.value = '';
    if (messagesDiv) messagesDiv.innerHTML = '';
    if (backupTimestampDisplay) backupTimestampDisplay.textContent = '';


    function showMessage(type, text) {
        if (messagesDiv) {
            if (typeof wpAutoplugin !== 'undefined' && typeof wpAutoplugin.showUtilsMessage === 'function') {
                wpAutoplugin.showUtilsMessage(text, type, messagesDiv);
            } else {
                messagesDiv.innerHTML = `<div class="notice notice-${type} is-dismissible"><p>${text}</p></div>`;
            }
        }
        console.log(`Optimizer Message (${type}): ${text}`);
    }

    function setLoadingState(button, isLoading, loadingText = 'Loading...') {
        if (!button) return;
        button.disabled = isLoading;
        const textContent = button.textContent; // Store current text before changing
        if (isLoading) {
            if (!button.dataset.originalText) { // Check if originalText isn't already set
                 button.dataset.originalText = textContent;
            }
            button.textContent = loadingText;
        } else {
            if (button.dataset.originalText) {
                button.textContent = button.dataset.originalText;
                delete button.dataset.originalText; // Clean up
            }
        }
    }

    function updateRevertUI(pluginFile) {
        if (!revertInfoContainer || !backupTimestampDisplay) return;

        const backupInfo = optimizer_backups[pluginFile];

        if (backupInfo && backupInfo.timestamp) {
            const backupDate = new Date(backupInfo.timestamp * 1000).toLocaleString();
            backupTimestampDisplay.textContent = `Backup available from: ${backupDate}`;
            revertInfoContainer.style.display = 'block';
        } else {
            revertInfoContainer.style.display = 'none';
            backupTimestampDisplay.textContent = '';
        }
    }

    if (selectElement) {
        selectElement.addEventListener('change', function() {
            currentPluginFile = this.value;
            // Reset UI elements when selection changes
            if (planDisplay) planDisplay.value = '';
            if (planDisplayContainer) planDisplayContainer.style.display = 'none';
            if (applyButtonContainer) applyButtonContainer.style.display = 'none';
            if (messagesDiv) messagesDiv.innerHTML = '';
            currentPluginCode = '';
            currentAiPlan = '';
            if (currentPluginFile) {
                updateRevertUI(currentPluginFile);
            } else {
                 if (revertInfoContainer) revertInfoContainer.style.display = 'none';
            }
        });
    }


    if (getPlanButton) {
        getPlanButton.addEventListener('click', function () {
            // currentPluginFile is already set by the selectElement change listener
            if (!currentPluginFile) {
                showMessage('warning', error_messages.no_plugin_selected);
                return;
            }

            showMessage('info', loading_messages.getting_content);
            setLoadingState(getPlanButton, true, loading_messages.getting_content);
            if (planDisplayContainer) planDisplayContainer.style.display = 'none';
            if (applyButtonContainer) applyButtonContainer.style.display = 'none';
            if (planDisplay) planDisplay.value = '';

            const getContentData = new URLSearchParams();
            getContentData.append('action', get_plugin_content_action);
            getContentData.append('_ajax_nonce', nonce);
            getContentData.append('plugin_file', currentPluginFile);

            fetch(ajax_url, {
                method: 'POST',
                body: getContentData
            })
            .then(response => response.json())
            .then(contentResponse => {
                if (!contentResponse.success) {
                    throw new Error(contentResponse.data.message || 'Failed to get plugin content.');
                }
                currentPluginCode = contentResponse.data.content;
                showMessage('info', loading_messages.getting_plan);
                setLoadingState(getPlanButton, true, loading_messages.getting_plan); // Keep loading state for next step

                const getPlanData = new URLSearchParams();
                getPlanData.append('action', get_plan_action);
                getPlanData.append('_ajax_nonce', nonce);
                getPlanData.append('plugin_file', currentPluginFile);
                getPlanData.append('plugin_code', currentPluginCode);

                return fetch(ajax_url, {
                    method: 'POST',
                    body: getPlanData
                });
            })
            .then(response => response.json())
            .then(planResponse => {
                if (!planResponse.success) {
                    throw new Error(planResponse.data.message || 'Failed to get optimization plan.');
                }
                currentAiPlan = planResponse.data.plan;
                if (planDisplay) planDisplay.value = currentAiPlan;
                if (planDisplayContainer) planDisplayContainer.style.display = 'block';
                if (applyButtonContainer) applyButtonContainer.style.display = 'block';
                showMessage('success', 'Optimization plan received.');
            })
            .catch(error => {
                showMessage('error', error.message);
            })
            .finally(() => {
                setLoadingState(getPlanButton, false);
            });
        });
    }

    if (applyButton) {
        applyButton.addEventListener('click', function () {
            if (!currentPluginFile || !currentPluginCode || !currentAiPlan) {
                showMessage('error', 'Missing plugin information or plan. Please get a plan first.');
                return;
            }

            showMessage('info', loading_messages.applying_plan);
            setLoadingState(applyButton, true, loading_messages.applying_plan);

            const applyData = new URLSearchParams();
            applyData.append('action', apply_action);
            applyData.append('_ajax_nonce', nonce);
            applyData.append('plugin_file', currentPluginFile);
            applyData.append('plugin_code', currentPluginCode);
            applyData.append('ai_plan', currentAiPlan);

            fetch(ajax_url, {
                method: 'POST',
                body: applyData
            })
            .then(response => response.json())
            .then(applyResponse => {
                if (!applyResponse.success) {
                    throw new Error(applyResponse.data.message || 'Failed to apply optimization.');
                }
                showMessage('success', applyResponse.data.message);

                // The PHP side updated the option. We need to reflect this in JS.
                // A simple way is to "fake" the update if the server call was successful.
                // A more robust way would be for PHP to return the new backup_info.
                // For now, we assume it was created with a new timestamp.
                // The actual timestamp would be different, but this shows the logic.
                if (optimizer_backups[currentPluginFile]) {
                     optimizer_backups[currentPluginFile].timestamp = Math.floor(Date.now() / 1000);
                } else {
                    // If no previous backup, create a placeholder. This is not ideal as slug isn't here.
                    // Best to have PHP return new backup_info.
                     optimizer_backups[currentPluginFile] = { timestamp: Math.floor(Date.now() / 1000), plugin_file: currentPluginFile, backup_path: 'unknown', plugin_slug: 'unknown' };
                }

                updateRevertUI(currentPluginFile);

                if (planDisplay) planDisplay.value = '';
                if (planDisplayContainer) planDisplayContainer.style.display = 'none';
                if (applyButtonContainer) applyButtonContainer.style.display = 'none';
                currentPluginCode = '';
                currentAiPlan = '';
            })
            .catch(error => {
                showMessage('error', error.message);
            })
            .finally(() => {
                setLoadingState(applyButton, false);
            });
        });
    }

    if (revertButton) {
        revertButton.addEventListener('click', function() {
            if (!currentPluginFile) {
                showMessage('error', error_messages.no_plugin_file_revert || 'Plugin file not identified for revert.');
                return;
            }

            if (!confirm('Are you sure you want to revert this plugin to its backup? This will overwrite the current plugin code.')) {
                return;
            }

            showMessage('info', loading_messages.reverting_plugin || 'Reverting plugin...');
            setLoadingState(revertButton, true, loading_messages.reverting_plugin || 'Reverting...');

            const revertData = new URLSearchParams();
            revertData.append('action', revert_action);
            revertData.append('_ajax_nonce', nonce); // Ensure this is the correct nonce variable for check_ajax_referer
            revertData.append('plugin_file', currentPluginFile);


            fetch(ajax_url, {
                method: 'POST',
                body: revertData
            })
            .then(response => response.json())
            .then(revertResponse => {
                if (!revertResponse.success) {
                    throw new Error(revertResponse.data.message || 'Failed to revert plugin.');
                }
                showMessage('success', revertResponse.data.message);
                delete optimizer_backups[currentPluginFile]; // Update local cache
                updateRevertUI(currentPluginFile); // This will hide the revert section

                // Optionally clear plan display
                if (planDisplay) planDisplay.value = '';
                if (planDisplayContainer) planDisplayContainer.style.display = 'none';
                if (applyButtonContainer) applyButtonContainer.style.display = 'none';
                currentAiPlan = '';
            })
            .catch(error => {
                showMessage('error', error.message);
            })
            .finally(() => {
                setLoadingState(revertButton, false);
            });
        });
    }
     // Initial check in case a plugin is pre-selected (e.g. by browser back button)
    if (selectElement && selectElement.value) {
        currentPluginFile = selectElement.value;
        updateRevertUI(currentPluginFile);
    }
});
