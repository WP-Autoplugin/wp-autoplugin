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
			document.getElementById('generate-plan-message').innerHTML = 'Please enter a plugin description.';
			return;
		}

		// Add loading class for form parent container.
		generatePlanForm.parentElement.classList.add('loading');

		pluginDescription = document.getElementById('plugin_description').value;
		var loader = loadingIndicator(document.getElementById('generate-plan-message'), 'Generating a plan for your plugin');
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
				document.getElementById('generate-plan-message').innerHTML = 'Error generating the plugin plan. <pre>' + response.data + '</pre>';
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

	  var loader = loadingIndicator(document.getElementById('generate-code-message'), 'Generating code');
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
			document.getElementById('generate-code-message').innerHTML = 'Error generating the plugin code. <pre>' + response.data + '</pre>';
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
  
	  var loader = loadingIndicator(document.getElementById('create-plugin-message'), 'Installing the plugin');
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
			document.getElementById('create-plugin-message').innerHTML = 'Plugin successfully installed.';
			document.getElementById('create-plugin-message').insertAdjacentHTML('beforeend', '<h2>How to test it?</h2><pre class="autoplugin-testing-plan">' + nl2br(wp_autoplugin.testing_plan) + '</pre><p>If you notice any issues, use the Fix button in the Autoplugins list.</p>');
			document.getElementById('create-plugin-message').insertAdjacentHTML('beforeend', '<a href="' + wp_autoplugin.activate_url + '&plugin=' + response.data + '" class="button button-primary">Activate Plugin</a>');
			
			// Hide #create-plugin-form.
			createPluginForm.style.display = 'none';
			
		  } else {
			document.getElementById('create-plugin-message').innerHTML = 'Error installing the plugin: <pre>' + response.data + '</pre>';
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
	let messages = [
		'A simple contact form with honeypot spam protection',
		'A custom post type for testimonials',
		'A widget that displays recent posts',
		'A shortcode that shows a random quote',
		'A user profile widget displaying avatar, bio, and website link',
		'A custom post type for managing FAQs',
		'A post views counter that tracks and displays view counts',
		'Maintenance mode with a countdown timer to site return',
		'An admin quick links widget for the dashboard',
		'Hide the admin bar for non-admin users',
		'Hide specific menu items in the admin area',
		'A social media share buttons plugin for posts',
		'A custom footer credit remover',
		'A plugin to add custom CSS to the WordPress login page',
		'A related posts display below single post content',
		'A custom excerpt length controller',
		'A "Back to Top" button for long pages',
		'A plugin to disable comments on specific post types',
		'A simple Google Analytics integration',
		'An author box display below posts',
		'A custom breadcrumb generator',
		'A plugin to add nofollow to external links',
		'A simple cookie consent banner',
		'A post expiration date setter',
		'A basic XML sitemap generator',
		'A custom login URL creator for added security',
		'A simple contact information display shortcode',
		'A plugin to add estimated reading time to posts',
		'A custom RSS feed footer',
		'A simple post duplication tool',
		'A basic schema markup generator',
		'A plugin to add custom admin footer text',
		'A plugin to add custom taxonomies easily',
		'A simple email obfuscator to prevent spam',
		'A basic redirection manager',
		'A plugin to add custom fields to user profiles',
		'A simple image compression tool',
	];
	messages.sort(() => Math.random() - 0.5);
	typingPlaceholder(document.getElementById('plugin_description'), messages);
  })();