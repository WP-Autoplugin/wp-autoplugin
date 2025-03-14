// step-1-fix, step-2-plan, step-3-done
(function () {
    'use strict';

    // ----- DOM References -----
    const stepGeneratePlan = document.querySelector('.step-1-fix');
    const stepReviewPlan   = document.querySelector('.step-2-plan');
    const stepReviewCode   = document.querySelector('.step-3-done');

    const generatePlanForm = document.getElementById('fix-plugin-form');
    const generateCodeForm = document.getElementById('fix-code-form');
    const createPluginForm = document.getElementById('fixed-plugin-form');

    const messageGeneratePlan = document.getElementById('fix-plugin-message');
    const messageReviewPlan   = document.getElementById('fix-code-message');
    const messageReviewCode   = document.getElementById('fixed-plugin-message');

    const pluginPlanContainer = document.getElementById('plugin_plan_container');
    const pluginCodeTextarea  = document.getElementById('fixed_plugin_code');

    // ----- State Variables -----
    let editorInstance    = null;
    let currentState      = 'generatePlan';
    let pluginCode        = '';
    let issueDescription  = '';
    let pluginPlan        = {};

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
            pluginPlanContainer.value = pluginPlan;
        },
        reviewCode: () => {
            pluginCodeTextarea.value = pluginCode;
            editorInstance = wpAutoPluginCommon.updateCodeEditor(editorInstance, pluginCodeTextarea, pluginCode);
        }
    };

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
        formData.append('action', 'wp_autoplugin_generate_fix_plan');
        formData.append('plugin_issue', issueDescription);
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        formData.append('check_other_issues', document.getElementById('check_other_issues').checked ? 1 : 0);
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            generatePlanForm.parentElement.classList.remove('loading');

            if (!response.success) {
                messageGeneratePlan.innerHTML = wp_autoplugin.messages.plan_generation_error + ' <pre>' + response.data + '</pre>';
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
        formData.append('action', 'wp_autoplugin_generate_fix_code');
        formData.append('plugin_issue', issueDescription);
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        formData.append('plugin_plan', pluginPlanContainer.value);
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
        formData.append('action', 'wp_autoplugin_fix_plugin');
        formData.append('plugin_issue', issueDescription);
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        formData.append('plugin_code', editorInstance.codemirror.getValue());
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            createPluginForm.parentElement.classList.remove('loading');

            if (!response.success) {
                messageReviewCode.innerHTML = wp_autoplugin.messages.plugin_creation_error + ' <pre>' + response.data + '</pre>';
                return;
            }

            let activateBtnHtml = `<div style="margin-top: 10px;"><a href="${wp_autoplugin.activate_url}&plugin=${response.data}" class="button button-primary">${wp_autoplugin.messages.activate}</a></div>`;
            if (wp_autoplugin.is_plugin_active) {
                activateBtnHtml = '';
            }
            messageReviewCode.innerHTML = wp_autoplugin.messages.code_updated + activateBtnHtml;

            // Hide the form
            createPluginForm.style.display = 'none';
        } catch (error) {
            loader.stop();
            createPluginForm.parentElement.classList.remove('loading');
            messageReviewCode.innerHTML = wp_autoplugin.messages.plugin_creation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    // ----- Attach Event Listeners -----
    generatePlanForm.addEventListener('submit', handleGeneratePlanSubmit);
    generateCodeForm.addEventListener('submit', handleGenerateCodeSubmit);
    createPluginForm.addEventListener('submit', handleCreatePluginSubmit);

    // "Edit" buttons
    document.getElementById('edit-issue').addEventListener('click', function() {
        currentState = 'generatePlan';
        wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);
    });

    document.getElementById('edit-plan').addEventListener('click', function() {
        currentState = 'reviewPlan';
        wpAutoPluginCommon.handleStepChange(steps, 'reviewPlan', onShowStep);
    });

    // Handle back button in browser
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
