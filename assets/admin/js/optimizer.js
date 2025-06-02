document.addEventListener('DOMContentLoaded', function () {
    const {
        ajax_url,
        nonce,
        get_plugin_content_action,
        get_plan_action,
        apply_action,
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

    let currentPluginFile = '';
    let currentPluginCode = '';
    let currentAiPlan = '';

    // Initialize UI
    if (planDisplayContainer) planDisplayContainer.style.display = 'none';
    if (applyButtonContainer) applyButtonContainer.style.display = 'none';
    if (planDisplay) planDisplay.value = '';
    if (messagesDiv) messagesDiv.innerHTML = '';

    function showMessage(type, text) {
        if (messagesDiv) {
            // Prefer utils.js if available, otherwise simple fallback
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
        if (isLoading) {
            button.dataset.originalText = button.textContent;
            button.textContent = loadingText;
        } else {
            if (button.dataset.originalText) {
                button.textContent = button.dataset.originalText;
            }
        }
    }

    if (getPlanButton) {
        getPlanButton.addEventListener('click', function () {
            currentPluginFile = selectElement.value;
            if (!currentPluginFile) {
                showMessage('warning', error_messages.no_plugin_selected);
                return;
            }

            showMessage('info', loading_messages.getting_content);
            setLoadingState(getPlanButton, true, loading_messages.getting_content);
            if (planDisplayContainer) planDisplayContainer.style.display = 'none';
            if (applyButtonContainer) applyButtonContainer.style.display = 'none';
            if (planDisplay) planDisplay.value = '';


            // Step 1: Get Plugin Content
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
                setLoadingState(getPlanButton, true, loading_messages.getting_plan);

                // Step 2: Get Optimization Plan
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
                console.error('Error in get plan process:', error);
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
                // Reset UI after successful application
                if (planDisplay) planDisplay.value = '';
                if (planDisplayContainer) planDisplayContainer.style.display = 'none';
                if (applyButtonContainer) applyButtonContainer.style.display = 'none';
                currentPluginCode = ''; // Clear stored code so it has to be fetched again
                currentAiPlan = '';
            })
            .catch(error => {
                showMessage('error', error.message);
                console.error('Error applying optimization:', error);
            })
            .finally(() => {
                setLoadingState(applyButton, false);
            });
        });
    }
});
