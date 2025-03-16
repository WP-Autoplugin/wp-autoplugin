(function () {
    'use strict';

    // ----- DOM References -----
    const stepGeneratePlan = document.querySelector('.step-1-extend');
    const stepReviewPlan   = document.querySelector('.step-2-plan');
    const stepReviewCode   = document.querySelector('.step-3-done');

    const generatePlanForm = document.getElementById('extend-hooks-form');
    const generateCodeForm = document.getElementById('extend-hooks-code-form');
    const createPluginForm = document.getElementById('extended-hooks-plugin-form');

    const messageGeneratePlan = document.getElementById('extend-hooks-message');
    const messageReviewPlan   = document.getElementById('extend-hooks-code-message');
    const messageReviewCode   = document.getElementById('extended-hooks-plugin-message');

    const pluginPlanContainer = document.getElementById('plugin_plan_container');
    const pluginCodeTextarea  = document.getElementById('extended_plugin_code');

    const hooksLoading = document.getElementById('hooks-loading');
    const hooksContent = document.getElementById('hooks-content');
    const hooksSummary = document.getElementById('hooks-summary');
    const hooksUl = document.getElementById('hooks-ul');
    const pluginFileInput = document.getElementById('plugin_file');

    // ----- State Variables -----
    let editorInstance    = null;
    let currentState      = 'generatePlan';
    let pluginCode        = '';
    let issueDescription  = '';
    let pluginPlan        = {};
    let pluginName        = '';

    // Step mapping
    const steps = {
        generatePlan: stepGeneratePlan,
        reviewPlan:   stepReviewPlan,
        reviewCode:   stepReviewCode
    };

    // Logic to run on step show
    const onShowStep = {
        generatePlan: () => {
            if (issueDescription !== '') {
                document.getElementById('plugin_issue').value = issueDescription;
            }
        },
        reviewPlan: () => {
            // Attempt to parse the plan JSON and fill plugin_name if present
            try {
                pluginPlanContainer.value = pluginPlan.plan;
                if (pluginPlan.plugin_name) {
                    document.getElementById('plugin_name').value = pluginPlan.plugin_name;
                }
            } catch (err) {
                console.error('Failed to parse plugin plan:', err);
            }
        },
        reviewCode: () => {
            pluginCodeTextarea.value = pluginCode;
            editorInstance = wpAutoPluginCommon.updateCodeEditor(editorInstance, pluginCodeTextarea, pluginCode);
        }
    };

    // ----- Extract Hooks on Page Load -----

    const pluginFile = pluginFileInput.value;
    if (pluginFile) {
        const loader = loadingIndicator(hooksLoading, wp_autoplugin.messages.extracting_hooks || 'Extracting plugin hooks, please wait...');
        loader.start();

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_extract_hooks');
        formData.append('plugin_file', pluginFile);
        formData.append('security', wp_autoplugin.nonce);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            loader.stop();
            hooksLoading.style.display = 'none';
            if (data.success) {
                const hooks = data.data;
                if (hooks.length > 0) {
                    hooksSummary.textContent = `${hooks.length} hooks found in the plugin code`;
                    hooksUl.innerHTML = hooks.map(hook => `<li>${hook.name} (${hook.type})</li>`).join('');
                    hooksContent.style.display = 'block';
                } else {
                    messageGeneratePlan.innerHTML = wp_autoplugin.messages.no_hooks_found || 'No hooks found in the plugin code. Cannot proceed with extension.';
                }
            } else {
                messageGeneratePlan.innerHTML = 'Error extracting hooks: ' + (data.data || 'Unknown error');
            }
        })
        .catch(error => {
            loader.stop();
            hooksLoading.style.display = 'none';
            messageGeneratePlan.innerHTML = 'Error extracting hooks: ' + error.message;
        });
    }

    // ----- Event Handlers -----

    async function handleGeneratePlanSubmit(event) {
        event.preventDefault();

        const issueField = document.getElementById('plugin_issue');
        if (issueField.value.trim() === '') {
            messageGeneratePlan.innerHTML = wp_autoplugin.messages.empty_description;
            return;
        }

        generatePlanForm.parentElement.classList.add('loading');
        issueDescription = issueField.value;

        const loader = loadingIndicator(messageGeneratePlan, wp_autoplugin.messages.generating_plan);
        loader.start();

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_generate_extend_hooks_plan');
        formData.append('plugin_issue', issueDescription);
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            generatePlanForm.parentElement.classList.remove('loading');

            if (!response.success) {
                messageGeneratePlan.innerHTML = wp_autoplugin.messages.plan_generation_error + ' <pre>' + response.data.plan + '</pre>';
                return;
            }

            pluginPlan = response.data;
            currentState = 'reviewPlan';
            wpAutoPluginCommon.handleStepChange(steps, 'reviewPlan', onShowStep);
        } catch (error) {
            loader.stop();
            generatePlanForm.parentElement.classList.remove('loading');
            messageGeneratePlan.innerHTML = wp_autoplugin.messages.plan_generation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    async function handleGenerateCodeSubmit(event) {
        event.preventDefault();

        generateCodeForm.parentElement.classList.add('loading');
        const loader = loadingIndicator(messageReviewPlan, wp_autoplugin.messages.generating_code);
        loader.start();

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_generate_extend_hooks_code');
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        const planText = pluginPlanContainer.value;
        let planHooks = [];
        let planPluginName = document.getElementById('plugin_name').value.trim();
        try {
            const planObj = JSON.parse(planText);
            if (Array.isArray(planObj.hooks)) {
                planHooks = planObj.hooks; // Only the hooks used in the plan
            }
            // If user didn't override the plugin_name input, set from plan:
            if (!planPluginName && planObj.plugin_name) {
                planPluginName = planObj.plugin_name;
            }
        } catch (e) {
            // ignore parse error
        }

        formData.append('plugin_plan', planText);
        formData.append('plugin_name', planPluginName);
        formData.append('hooks', JSON.stringify(planHooks));
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            generateCodeForm.parentElement.classList.remove('loading');

            if (!response.success) {
                messageReviewPlan.innerHTML = wp_autoplugin.messages.code_generation_error + ' <pre>' + response.data + '</pre>';
                return;
            }

            pluginCode = response.data;
            currentState = 'reviewCode';
            wpAutoPluginCommon.handleStepChange(steps, 'reviewCode', onShowStep);
        } catch (error) {
            loader.stop();
            generateCodeForm.parentElement.classList.remove('loading');
            messageReviewPlan.innerHTML = wp_autoplugin.messages.code_generation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    async function handleCreatePluginSubmit(event) {
        event.preventDefault();

        createPluginForm.parentElement.classList.add('loading');
        const loader = loadingIndicator(messageReviewCode, wp_autoplugin.messages.creating_plugin);
        loader.start();

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_create_plugin'); // Reuse existing action
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        formData.append('plugin_code', editorInstance.codemirror.getValue());
        formData.append('plugin_name', document.getElementById('plugin_name').value.trim());
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            createPluginForm.parentElement.classList.remove('loading');

            if (response.success) {
                // Show success message
                messageReviewCode.innerHTML = wp_autoplugin.messages.plugin_created;

                messageReviewCode
                    .insertAdjacentHTML(
                        'beforeend',
                        `<a href="${wp_autoplugin.activate_url}&plugin=${response.data}"
                           class="button button-primary">
                           ${wp_autoplugin.messages.activate}
                         </a>`
                    );

                // Hide form
                createPluginForm.style.display = 'none';
            } else {
                document.getElementById('create-plugin-message').innerHTML =
                    wp_autoplugin.messages.plugin_creation_error + ' <pre>' + response.data + '</pre>';
            }
        } catch (error) {
            console.error(error);
            loader.stop();
            createPluginForm.parentElement.classList.remove('loading');
            messageReviewCode.innerHTML = wp_autoplugin.messages.plugin_creation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    // ----- Attach Event Listeners -----
    generatePlanForm.addEventListener('submit', handleGeneratePlanSubmit);
    generateCodeForm.addEventListener('submit', handleGenerateCodeSubmit);
    createPluginForm.addEventListener('submit', handleCreatePluginSubmit);

    document.getElementById('edit-issue').addEventListener('click', function() {
        currentState = 'generatePlan';
        wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);
    });

    document.getElementById('edit-plan').addEventListener('click', function() {
        currentState = 'reviewPlan';
        wpAutoPluginCommon.handleStepChange(steps, 'reviewPlan', onShowStep);
    });

    window.addEventListener('popstate', function(event) {
        if (event.state && event.state.state) {
            wpAutoPluginCommon.handleStepChange(steps, event.state.state, onShowStep);
        } else {
            wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);
        }
    });

    // Initialize the first step
    wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);
})();