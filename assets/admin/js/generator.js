(function () {
    'use strict';

    // ----- DOM References -----
    const stepGeneratePlan = document.querySelector('.step-1-generation');
    const stepReviewPlan = document.querySelector('.step-2-plan');
    const stepReviewCode = document.querySelector('.step-3-code');

    const generatePlanForm = document.getElementById('generate-plan-form');
    const generateCodeForm = document.getElementById('generate-code-form');
    const createPluginForm = document.getElementById('create-plugin-form');

    const pluginPlanContainer = document.getElementById('plugin_plan_container');
    const pluginCodeTextarea = document.getElementById('plugin_code');

    // ----- State Variables -----
    let currentState = 'generatePlan';
    let pluginDescription = '';
    let pluginPlan = {};
	let pluginCode = '';
	let editorInstance;
  
	function showStep(step) {
	  stepGeneratePlan.style.display = 'none';
	  stepReviewPlan.style.display = 'none';
	  stepReviewCode.style.display = 'none';
  
	  switch (step) {
		case 'generatePlan':
		  stepGeneratePlan.style.display = 'block';
		  document.getElementById('plugin_description').value = pluginDescription;
		  break;
		case 'reviewPlan':
		  stepReviewPlan.style.display = 'block';
		  var accordion = buildAccordion(pluginPlan);
		  pluginPlanContainer.innerHTML = accordion;
		  attachAccordionListeners();
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
		if (document.getElementById('plugin_description').value.trim() === '') {
			document.getElementById('generate-plan-message').innerHTML = wp_autoplugin.messages.empty_description;
			return;
		}

		// Add loading class for form parent container.
		generatePlanForm.parentElement.classList.add('loading');

		pluginDescription = document.getElementById('plugin_description').value;
		var loader = loadingIndicator(document.getElementById('generate-plan-message'), wp_autoplugin.messages.generating_plan);
		loader.start();
	
		var formData = new FormData();
		formData.append('action', 'wp_autoplugin_generate_plan');
		formData.append('plugin_description', pluginDescription);
		formData.append('security', wp_autoplugin.nonce);
	
		var xhr = new XMLHttpRequest();
		xhr.open('POST', wp_autoplugin.ajax_url, true);
		xhr.onreadystatechange = function() {
			if (xhr.readyState === 4 && xhr.status === 200) {
			loader.stop();
			var response = JSON.parse(xhr.responseText);

			// Check if success=false
			if ( ! response.success ) {
				document.getElementById('generate-plan-message').innerHTML = wp_autoplugin.messages.plan_generation_error + ' <pre>' + response.data + '</pre>';
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

	  var loader = loadingIndicator(document.getElementById('generate-code-message'), wp_autoplugin.messages.generating_code);
	  loader.start();
  
	  var formData = new FormData();
	  formData.append('action', 'wp_autoplugin_generate_code');
	  formData.append('plugin_description', pluginDescription);
	  // Rebuild the plugin plan from the accordion textareas & inputs.
	  var plan = {};
	  var accordions = document.querySelectorAll('.autoplugin-accordion');
	  accordions.forEach(function(accordion) {
		var heading = accordion.querySelector('.autoplugin-accordion-heading');
		var part = heading.querySelector('.title').textContent;
		var textarea = accordion.querySelector('.autoplugin-accordion-content textarea');
		var content = textarea ? textarea.value : accordion.querySelector('.autoplugin-accordion-content input').value;
		plan[part.toLowerCase().replace(/ /g, '_')] = content;
	  });
	  formData.append('plugin_plan', JSON.stringify(plan));
	  formData.append('security', wp_autoplugin.nonce);

	  // Let's also update the plugin plan in the UI
	  pluginPlan = plan;
  
	  var xhr = new XMLHttpRequest();
	  xhr.open('POST', wp_autoplugin.ajax_url, true);
	  xhr.onreadystatechange = function() {
		if (xhr.readyState === 4 && xhr.status === 200) {
		  loader.stop();
		  var response = JSON.parse(xhr.responseText);
		  if ( ! response.success ) {
			document.getElementById('generate-code-message').innerHTML = wp_autoplugin.code_generation_error + ' <pre>' + response.data + '</pre>';
			return;
		  }

		  pluginCode = response.data;
		
		  currentState = 'reviewCode';
		  showStep('reviewCode');
		}

		// Remove loading class for form parent container.
		generateCodeForm.parentElement.classList.remove('loading');
	  };
	  xhr.send(formData);
	}
  
	function handleCreatePluginSubmit(event) {
	  event.preventDefault();

	  // Add loading class for form parent container.
	  createPluginForm.parentElement.classList.add('loading');

	  pluginCode = pluginCodeTextarea.value;
	  var pluginName = document.getElementById('plugin_name').value;
  
	  var loader = loadingIndicator(document.getElementById('create-plugin-message'), wp_autoplugin.messages.creating_plugin);
	  loader.start();
  
	  var formData = new FormData();
	  formData.append('action', 'wp_autoplugin_create_plugin');
	  formData.append('plugin_code', pluginCode);
	  formData.append('plugin_name', pluginName);
	  formData.append('security', wp_autoplugin.nonce);
  
	  var xhr = new XMLHttpRequest();
	  xhr.open('POST', wp_autoplugin.ajax_url, true);
	  xhr.onreadystatechange = function() {
		if (xhr.readyState === 4 && xhr.status === 200) {
		  loader.stop();
		  var response = JSON.parse(xhr.responseText);
		  if (response.success) {
			document.getElementById('create-plugin-message').innerHTML = wp_autoplugin.messages.plugin_created;
			document.getElementById('create-plugin-message').insertAdjacentHTML('beforeend', '<h2>' + wp_autoplugin.messages.how_to_test + '</h2><pre class="autoplugin-testing-plan">' + nl2br(wp_autoplugin.testing_plan) + '</pre><p>' + wp_autoplugin.messages.use_fixer + '</p>');
			document.getElementById('create-plugin-message').insertAdjacentHTML('beforeend', '<a href="' + wp_autoplugin.activate_url + '&plugin=' + response.data + '" class="button button-primary">' + wp_autoplugin.messages.activate + '</a>');
			
			// Hide #create-plugin-form.
			createPluginForm.style.display = 'none';
			
		  } else {
			document.getElementById('create-plugin-message').innerHTML = wp_autoplugin.messages.plugin_creation_error + ' <pre>' + response.data + '</pre>';
		  }
		}

		// Remove loading class for form parent container.
		createPluginForm.parentElement.classList.remove('loading');
	  };
	  xhr.send(formData);
	}
  
	generatePlanForm.addEventListener('submit', handleGeneratePlanSubmit);
	generateCodeForm.addEventListener('submit', handleGenerateCodeSubmit);
	createPluginForm.addEventListener('submit', handleCreatePluginSubmit);
  
	document.getElementById('edit-description').addEventListener('click', function() {
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

	// Typing placeholder for #plugin_description
	let messages = wp_autoplugin.plugin_examples;
	messages.sort(() => Math.random() - 0.5);
	typingPlaceholder(document.getElementById('plugin_description'), messages);
  })();