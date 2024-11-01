// step-1-extend, step-2-plan, step-3-done
(function () {
    'use strict';
    
    // ----- DOM References -----
    const stepGeneratePlan = document.querySelector('.step-1-extend');
    const stepReviewPlan = document.querySelector('.step-2-plan');
    const stepReviewCode = document.querySelector('.step-3-done');

    const generatePlanForm = document.getElementById('extend-plugin-form');
    const generateCodeForm = document.getElementById('extend-code-form');
    const createPluginForm = document.getElementById('extended-plugin-form');

    const messageGeneratePlan = document.getElementById('extend-plugin-message');
    const messageReviewPlan = document.getElementById('extend-code-message');
    const messageReviewCode = document.getElementById('extended-plugin-message');

    const pluginPlanContainer = document.getElementById('plugin_plan_container');
    const pluginCodeTextarea = document.getElementById('extended_plugin_code');

    // ----- State Variables -----
    let editorInstance;
    let currentState = 'generatePlan';
    let pluginCode = '';
    let issueDescription = '';
    let pluginPlan = {};

    function showStep(step) {
        stepGeneratePlan.style.display = 'none';
        stepReviewPlan.style.display = 'none';
        stepReviewCode.style.display = 'none';

        step.style.display = 'block';
    }

    function showError(message, element) {
        element.innerHTML = `<div class="notice notice-error"><p>${message}</p></div>`;
    }

    function showStep(step) {
        stepGeneratePlan.style.display = 'none';
        stepReviewPlan.style.display = 'none';
        stepReviewCode.style.display = 'none';
    
        switch (step) {
            case 'generatePlan':
            stepGeneratePlan.style.display = 'block';
            if ( issueDescription !== '' ) {
                document.getElementById('plugin_issue').value = issueDescription;
            }
            break;
            case 'reviewPlan':
            stepReviewPlan.style.display = 'block';
            // pluginPlanContainer is a textarea here.
            pluginPlanContainer.value = pluginPlan;
            break;
            case 'reviewCode':
            stepReviewCode.style.display = 'block';
            pluginCodeTextarea.value = pluginCode;
            updateCodeEditor();
            break;
        }
    
        updateHistory(step);
    }

    function updateHistory(state) {
        history.pushState({ state: state }, null, null);
    }

    function updateCodeEditor() {
        if (typeof editorInstance === 'undefined') {
            editorInstance = wp.codeEditor.initialize(pluginCodeTextarea, {
                lineNumbers: true,
                matchBrackets: true,
                indentUnit: 4,
                indentWithTabs: true,
                tabSize: 4,
                mode: 'application/x-httpd-php'
            });
        } else {
            console.log('Setting value: ' + pluginCode);
            editorInstance.codemirror.setValue(pluginCode);
            editorInstance.codemirror.refresh();
        }
    }

    function handleGeneratePlanSubmit(event) {
		event.preventDefault();

		// If the field is empty, show an error message.
		if (document.getElementById('plugin_issue').value.trim() === '') {
			messageGeneratePlan.innerHTML = wp_autoplugin.messages.empty_description;
			return;
		}

		// Add loading class for form parent container.
		generatePlanForm.parentElement.classList.add('loading');

		issueDescription = document.getElementById('plugin_issue').value;
		var loader = loadingIndicator(messageGeneratePlan, wp_autoplugin.messages.generating_plan);
		loader.start();
	
		var formData = new FormData();
		formData.append('action', 'wp_autoplugin_generate_extend_plan');
		formData.append('plugin_issue', issueDescription);
        formData.append('plugin_file', document.getElementById('plugin_file').value);
		formData.append('security', wp_autoplugin.nonce);
	
		var xhr = new XMLHttpRequest();
		xhr.open('POST', wp_autoplugin.ajax_url, true);
		xhr.onreadystatechange = function() {
			if (xhr.readyState === 4 && xhr.status === 200) {
                loader.stop();
                var response = JSON.parse(xhr.responseText);

                // Check if success=false
                if ( ! response.success ) {
                    messageGeneratePlan.innerHTML = wp_autoplugin.messages.plan_generation_error + ' <pre>' + response.data + '</pre>';
                    return;
                }

                pluginPlan = response.data;
                currentState = 'reviewPlan';
                showStep('reviewPlan');
			}

			// Remove loading class for form parent container.
			generatePlanForm.parentElement.classList.remove('loading');
		};
		xhr.send(formData);
	}

    function handleGenerateCodeSubmit(event) {
        event.preventDefault();
        
        // Add loading class for form parent container.
        generateCodeForm.parentElement.classList.add('loading');

        var loader = loadingIndicator(messageReviewPlan, wp_autoplugin.messages.generating_code);
        loader.start();

        var formData = new FormData();
        formData.append('action', 'wp_autoplugin_generate_extend_code');
        formData.append('plugin_issue', issueDescription);
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        formData.append('plugin_plan', pluginPlanContainer.value);
        formData.append('security', wp_autoplugin.nonce);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', wp_autoplugin.ajax_url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                loader.stop();
                var response = JSON.parse(xhr.responseText);

                // Check if success=false
                if ( ! response.success ) {
                    messageReviewPlan.innerHTML = wp_autoplugin.messages.code_generation_error + ' <pre>' + response.data + '</pre>';

                    // Remove loading class for form parent container.
                    generateCodeForm.parentElement.classList.remove('loading');
                    return;
                }

                pluginCode = response.data;
                currentState = 'reviewCode';
                showStep('reviewCode');
            }
        };
        xhr.send(formData);
    }

    function handleCreatePluginSubmit(event) {
        event.preventDefault();

        // Add loading class for form parent container.
        createPluginForm.parentElement.classList.add('loading');

        var loader = loadingIndicator(messageReviewCode, wp_autoplugin.messages.creating_plugin);
        loader.start();

        var formData = new FormData();
        formData.append('action', 'wp_autoplugin_extend_plugin');
        formData.append('plugin_issue', issueDescription);
        formData.append('plugin_file', document.getElementById('plugin_file').value);
        formData.append('plugin_code', editorInstance.codemirror.getValue());
        formData.append('security', wp_autoplugin.nonce);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', wp_autoplugin.ajax_url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                loader.stop();
                var response = JSON.parse(xhr.responseText);

                // Check if success=false
                if ( ! response.success ) {
                    messageReviewCode.innerHTML = wp_autoplugin.messages.plugin_creation_error + ' <pre>' + response.data + '</pre>';

                    // Remove loading class for form parent container.
                    createPluginForm.parentElement.classList.remove('loading');
                    return;
                }

                var activateBtnHtml = '<div style="margin-top: 10px;"><a href="' + wp_autoplugin.activate_url + '&plugin=' + response.data + '" class="button button-primary">' + wp_autoplugin.messages.activate + '</a></div>';
                if ( wp_autoplugin.is_plugin_active ) {
                    activateBtnHtml = '';
                }
                messageReviewCode.innerHTML = wp_autoplugin.messages.code_updated + activateBtnHtml;

                // Hide the form and show the success message.
                createPluginForm.style.display = 'none';
            }

        };
        xhr.send(formData);
    }

    generatePlanForm.addEventListener('submit', handleGeneratePlanSubmit);
    generateCodeForm.addEventListener('submit', handleGenerateCodeSubmit);
    createPluginForm.addEventListener('submit', handleCreatePluginSubmit);

    document.getElementById('edit-issue').addEventListener('click', function() {
        currentState = 'generatePlan';
        showStep('generatePlan');
    });

    document.getElementById('edit-plan').addEventListener('click', function() {
        currentState = 'reviewPlan';
        showStep('reviewPlan');
    });

    window.addEventListener('popstate', function(event) {
        if (event.state && event.state.state) {
            showStep(event.state.state);
        } else {
            showStep('generatePlan');
        }
    });

    showStep('generatePlan');
})();