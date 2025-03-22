(function () {
    'use strict';

    // ----- DOM References -----
    const stepGeneratePlan = document.querySelector('.step-1-generation');
    const stepReviewPlan   = document.querySelector('.step-2-plan');
    const stepReviewCode   = document.querySelector('.step-3-code');

    const generatePlanForm = document.getElementById('generate-plan-form');
    const generateCodeForm = document.getElementById('generate-code-form');
    const createPluginForm = document.getElementById('create-plugin-form');

    const pluginPlanContainer = document.getElementById('plugin_plan_container');
    const pluginCodeTextarea  = document.getElementById('plugin_code');

    // ----- State Variables -----
    let currentState       = 'generatePlan';
    let pluginDescription  = '';
    let pluginPlan         = {};
    let pluginCode         = '';
    let editorInstance     = null;

    // Step mapping
    const steps = {
        generatePlan: stepGeneratePlan,
        reviewPlan:   stepReviewPlan,
        reviewCode:   stepReviewCode
    };

    // Logic to run on step show
    const onShowStep = {
        generatePlan: () => {
            document.getElementById('plugin_description').value = pluginDescription;
        },
        reviewPlan: () => {
            const accordion = buildAccordion(pluginPlan);
            pluginPlanContainer.innerHTML = accordion;
            attachAccordionListeners();
        },
        reviewCode: () => {
            pluginCodeTextarea.value = pluginCode;
            editorInstance = wpAutoPluginCommon.updateCodeEditor(editorInstance, pluginCodeTextarea, pluginCode);
        }
    };

    // ----- Event Handlers -----

    async function handleGeneratePlanSubmit(event) {
        event.preventDefault();

        const descField = document.getElementById('plugin_description');
        if (descField.value.trim() === '') {
            document.getElementById('generate-plan-message').innerHTML = wp_autoplugin.messages.empty_description;
            return;
        }

        generatePlanForm.parentElement.classList.add('loading');
        pluginDescription = descField.value;

        const loader = loadingIndicator(document.getElementById('generate-plan-message'), wp_autoplugin.messages.generating_plan);
        loader.start();

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_generate_plan');
        formData.append('plugin_description', pluginDescription);
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            generatePlanForm.parentElement.classList.remove('loading');

            if (!response.success) {
                document.getElementById('generate-plan-message').innerHTML =
                    wp_autoplugin.messages.plan_generation_error + ' <pre>' + response.data + '</pre>';
                return;
            }

            pluginPlan = response.data;
            currentState = 'reviewPlan';
            wpAutoPluginCommon.handleStepChange(steps, 'reviewPlan', onShowStep);
        } catch (error) {
            loader.stop();
            generatePlanForm.parentElement.classList.remove('loading');
            document.getElementById('generate-plan-message').innerHTML =
                wp_autoplugin.messages.plan_generation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    async function handleGenerateCodeSubmit(event) {
        event.preventDefault();

        generateCodeForm.parentElement.classList.add('loading');
        const loader = loadingIndicator(document.getElementById('generate-code-message'), wp_autoplugin.messages.generating_code);
        loader.start();

        // Rebuild plugin plan from the accordion textareas/inputs
        const plan = {};
        const accordions = document.querySelectorAll('.autoplugin-accordion');
        accordions.forEach((accordion) => {
            const heading    = accordion.querySelector('.autoplugin-accordion-heading .title');
            const part       = heading.textContent;
            const contentEl  = accordion.querySelector('.autoplugin-accordion-content textarea')
                               || accordion.querySelector('.autoplugin-accordion-content input');
            const content    = contentEl ? contentEl.value : '';
            plan[part.toLowerCase().replace(/ /g, '_')] = content;
        });
        pluginPlan = plan;

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_generate_code');
        formData.append('plugin_description', pluginDescription);
        formData.append('plugin_plan', JSON.stringify(plan));
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            generateCodeForm.parentElement.classList.remove('loading');

            if (!response.success) {
                document.getElementById('generate-code-message').innerHTML =
                    wp_autoplugin.messages.code_generation_error + ' <pre>' + response.data + '</pre>';
                return;
            }

            pluginCode = response.data;
            currentState = 'reviewCode';
            wpAutoPluginCommon.handleStepChange(steps, 'reviewCode', onShowStep);
        } catch (error) {
            loader.stop();
            generateCodeForm.parentElement.classList.remove('loading');
            document.getElementById('generate-code-message').innerHTML =
                wp_autoplugin.messages.code_generation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    async function handleCreatePluginSubmit(event) {
        event.preventDefault();

        createPluginForm.parentElement.classList.add('loading');
        const loader = loadingIndicator(document.getElementById('create-plugin-message'), wp_autoplugin.messages.creating_plugin);
        loader.start();

        const pluginName = document.getElementById('plugin_name').value;

        const formData = new FormData();
        formData.append('action', 'wp_autoplugin_create_plugin');
        formData.append('plugin_code', editorInstance.codemirror.getValue());
        formData.append('plugin_name', pluginName);
        formData.append('security', wp_autoplugin.nonce);

        try {
            const response = await wpAutoPluginCommon.sendRequest(formData);
            loader.stop();
            createPluginForm.parentElement.classList.remove('loading');

            if (response.success) {
                // Show success message
                document.getElementById('create-plugin-message').innerHTML = wp_autoplugin.messages.plugin_created;
                document
                    .getElementById('create-plugin-message')
                    .insertAdjacentHTML(
                        'beforeend',
                        `<h2>${wp_autoplugin.messages.how_to_test}</h2>
                         <pre class="autoplugin-testing-plan">${nl2br(wp_autoplugin.testing_plan)}</pre>
                         <p>${wp_autoplugin.messages.use_fixer}</p>`
                    );

                document
                    .getElementById('create-plugin-message')
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
            loader.stop();
            createPluginForm.parentElement.classList.remove('loading');
            document.getElementById('create-plugin-message').innerHTML =
                wp_autoplugin.messages.plugin_creation_error + ' <pre>' + error.message + '</pre>';
        }
    }

    // ----- Attach Event Listeners -----
    generatePlanForm.addEventListener('submit', handleGeneratePlanSubmit);
    generateCodeForm.addEventListener('submit', handleGenerateCodeSubmit);
    createPluginForm.addEventListener('submit', handleCreatePluginSubmit);

    document.getElementById('edit-description').addEventListener('click', () => {
        currentState = 'generatePlan';
        wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);
    });

    document.getElementById('edit-plan').addEventListener('click', () => {
        currentState = 'reviewPlan';
        wpAutoPluginCommon.handleStepChange(steps, 'reviewPlan', onShowStep);
    });

    // Handle back button
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.state) {
            wpAutoPluginCommon.handleStepChange(steps, event.state.state, onShowStep);
        } else {
            wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);
        }
    });

    // Initialize step
    wpAutoPluginCommon.handleStepChange(steps, 'generatePlan', onShowStep);

    // Placeholder typing effect for plugin_description
    let messages = wp_autoplugin.plugin_examples;
    messages.sort(() => Math.random() - 0.5);
    typingPlaceholder(document.getElementById('plugin_description'), messages);

    // Initialize the submit handlers
    attachFormSubmitListeners();
})();
