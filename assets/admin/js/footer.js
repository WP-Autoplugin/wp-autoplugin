jQuery(document).ready(function($) {
	// Store model information for dynamic updates
	window.wpAutopluginModels = wpAutopluginFooter.models;
	
	// Global token tracking
	window.wpAutopluginTokens = {
		total: { input_tokens: 0, output_tokens: 0 },
		steps: [],
		lastStepTime: new Date()
	};
	
	// Set initial step and model
	var defaultStep = wpAutopluginFooter.default_step;
	document.body.setAttribute('data-current-step', defaultStep);
	if (window.getModelForStep) {
		var initialModelType = window.getModelForStep(defaultStep);
		window.updateFooterModel(initialModelType);
	}

	// Function to update footer model display
	window.updateFooterModel = function(modelType) {
		var modelToShow = window.wpAutopluginModels[modelType] || window.wpAutopluginModels.default;
		$('#model-display code').text(modelToShow);
	};

	// Function to get model type for current workflow step
	window.getModelForStep = function(step) {
		switch(step) {
			case 'generatePlan':
				return 'planner';

			case 'reviewPlan':
				return 'coder';

			case 'reviewCode':
				return 'reviewer';

			case 'showExplanation':
			case 'askQuestion':
				return 'reviewer';
			
			// Default fallback
			default:
				return 'default';
		}
	};

	// Function to add token usage for a specific step
	window.addTokenUsage = function(stepName, modelUsed, inputTokens, outputTokens) {
		if (inputTokens !== undefined && outputTokens !== undefined && inputTokens >= 0 && outputTokens >= 0) {
			// Ensure global object exists
			if (!window.wpAutopluginTokens) {
				window.wpAutopluginTokens = {
					total: { input_tokens: 0, output_tokens: 0 },
					steps: [],
					lastStepTime: new Date()
				};
			}
			
			// Calculate duration since last step
			var now = new Date();
			var duration = Math.round((now - window.wpAutopluginTokens.lastStepTime) / 1000); // Duration in seconds
			
			// Add to total
			window.wpAutopluginTokens.total.input_tokens += inputTokens;
			window.wpAutopluginTokens.total.output_tokens += outputTokens;
			
			// Record step data
			window.wpAutopluginTokens.steps.push({
				step: stepName || 'Unknown Step',
				model: modelUsed || 'Unknown Model',
				input_tokens: inputTokens,
				output_tokens: outputTokens,
				timestamp: now.toISOString(),
				duration: duration > 0 ? duration : 1 // At least 1 second, avoid 0
			});
			
			// Update last step time for next calculation
			window.wpAutopluginTokens.lastStepTime = now;
			
			// Update display
			updateTokenDisplayFromGlobal();
		}
	};
	
	// Function to update footer display from global totals
	function updateTokenDisplayFromGlobal() {
		var total = window.wpAutopluginTokens.total;
		$('#token-input').text(total.input_tokens.toLocaleString());
		$('#token-output').text(total.output_tokens.toLocaleString());
		$('#token-display').show();
	}

	// Function to update token counts in footer (legacy compatibility)
	window.updateTokenDisplay = function(inputTokens, outputTokens) {
		if (inputTokens !== undefined && outputTokens !== undefined) {
			$('#token-input').text(inputTokens.toLocaleString());
			$('#token-output').text(outputTokens.toLocaleString());
			$('#token-display').show();
		}
	};

	// Function to hide token display
	window.hideTokenDisplay = function() {
		$('#token-display').hide();
	};
	
	// Function to reset all token tracking (only when starting completely over)
	window.resetTokenTracking = function() {
		window.wpAutopluginTokens = {
			total: { input_tokens: 0, output_tokens: 0 },
			steps: [],
			lastStepTime: new Date()
		};
		$('#token-display').hide();
	};

	// Show model modal when change link is clicked
	$('#change-model-link').on('click', function(e) {
		e.preventDefault();
		$('#model-selection-modal').show();
	});
	
	// Show token breakdown modal when token display is clicked
	$('#token-display').on('click', function(e) {
		e.preventDefault();
		showTokenBreakdown();
		$('#token-breakdown-modal').show();
	});
	
	// Close model modal when X is clicked
	$('#close-modal').on('click', function() {
		$('#model-selection-modal').hide();
	});
	
	// Close token modal when X is clicked
	$('#close-token-modal, #close-token-breakdown').on('click', function() {
		$('#token-breakdown-modal').hide();
	});
	
	// Close modal when cancel is clicked
	$('#cancel-models').on('click', function() {
		$('#model-selection-modal').hide();
	});
	
	// Close modals when clicking outside
	$(window).on('click', function(e) {
		if (e.target.id === 'model-selection-modal') {
			$('#model-selection-modal').hide();
		}
		if (e.target.id === 'token-breakdown-modal') {
			$('#token-breakdown-modal').hide();
		}
	});
	
	// Function to format duration in seconds to human readable format
	function formatDuration(seconds) {
		if (seconds < 60) {
			return seconds + 's';
		} else if (seconds < 3600) {
			var mins = Math.floor(seconds / 60);
			var secs = seconds % 60;
			return mins + 'm ' + secs + 's';
		} else {
			var hours = Math.floor(seconds / 3600);
			var mins = Math.floor((seconds % 3600) / 60);
			var secs = seconds % 60;
			return hours + 'h ' + mins + 'm ' + secs + 's';
		}
	}
	
	// Function to show token breakdown
	function showTokenBreakdown() {
		var content = $('#token-breakdown-content');
		var steps = window.wpAutopluginTokens ? window.wpAutopluginTokens.steps : [];
		var total = window.wpAutopluginTokens ? window.wpAutopluginTokens.total : {input_tokens: 0, output_tokens: 0};
		
		if (!steps || steps.length === 0) {
			content.html('<p style="text-align: center; color: #666; font-style: italic;">' + wpAutopluginFooter.no_token_data + '</p>');
			return;
		}
		
		var html = '<div style="margin-bottom: 15px;">';
		html += '<h4 style="margin: 0 0 10px 0; color: #23282d;">' + wpAutopluginFooter.total_usage + '</h4>';
		html += '<div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 14px; font-weight: 600;">';
		html += '<span style="color: #007cba;">' + total.input_tokens.toLocaleString() + '</span> input tokens + ';
		html += '<span style="color: #d63384;">' + total.output_tokens.toLocaleString() + '</span> output tokens';
		html += '</div></div>';
		
		html += '<h4 style="margin: 15px 0 10px 0; color: #23282d;">' + wpAutopluginFooter.step_breakdown + '</h4>';
		html += '<div style="max-height: 300px; overflow-y: auto;">';
		
		steps.forEach(function(step, index) {
			var step_label = step.step || 'Step ' + (index + 1);
			if ( step.step === 'reviewPlan' ) {
				step_label = 'Generate Code';
			} else if ( step.step === 'generatePlan' ) {
				step_label = 'Generate Plan';
			}

			html += '<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 12px; margin-bottom: 8px;">';
			html += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">';
			html += '<div>';
			html += '<span style="font-weight: 600; color: #23282d;">' + step_label + '</span>';
			html += '<span style="margin-left: 10px; font-size: 12px; color: #666;">Model: ' + step.model + '</span>';
			html += '</div>';
			html += '<div style="font-family: monospace; font-size: 12px; color: #666;">';
			html += formatDuration(step.duration);
			html += '</div>';
			html += '</div>';
			html += '<div style="font-family: monospace; font-size: 13px;">';
			html += '<span style="color: #007cba;">' + step.input_tokens.toLocaleString() + '</span> IN | ';
			html += '<span style="color: #d63384;">' + step.output_tokens.toLocaleString() + '</span> OUT';
			html += '</div>';
			html += '</div>';
		});
		
		html += '</div>';
		content.html(html);
	}
	
	// Save models
	$('#save-models').on('click', function(e) {
		e.preventDefault();
		
		var defaultModel = $('#modal-default-model').val();
		var plannerModel = $('#modal-planner-model').val();
		var coderModel = $('#modal-coder-model').val();
		var reviewerModel = $('#modal-reviewer-model').val();
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wp_autoplugin_change_models',
				nonce: wpAutopluginFooter.nonce,
				default_model: defaultModel,
				planner_model: plannerModel,
				coder_model: coderModel,
				reviewer_model: reviewerModel
			},
			success: function(response) {
				if (response.success) {
					// Update stored models and refresh display
					window.wpAutopluginModels.default = defaultModel;
					window.wpAutopluginModels.planner = plannerModel || defaultModel;
					window.wpAutopluginModels.coder = coderModel || defaultModel;
					window.wpAutopluginModels.reviewer = reviewerModel || defaultModel;
					
					// Hide modal
					$('#model-selection-modal').hide();
					
					// Update footer display based on current step
					var currentStep = $('body').attr('data-current-step') || 'default';
					var modelType = window.getModelForStep(currentStep);
					window.updateFooterModel(modelType);
				} else {
					alert(response.data.message || wpAutopluginFooter.error_saving_models);
				}
			},
			error: function() {
				alert(wpAutopluginFooter.error_saving_models_ajax);
			}
		});
	});
});
