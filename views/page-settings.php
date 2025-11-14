<?php
/**
 * Admin view for the Settings page.
 *
 * @package WP-Autoplugin
 * @since 1.0.0
 * @version 1.0.5
 * @link https://wp-autoplugin.com
 * @license GPL-2.0+
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace WP_Autoplugin;

use WP_Autoplugin\Admin\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap">
	<h1><?php esc_html_e( 'WP-Autoplugin Settings', 'wp-autoplugin' ); ?></h1>
	<?php settings_errors(); ?>
	<form method="post" action="options.php">
		<?php
		settings_fields( 'wp_autoplugin_settings' );
		do_settings_sections( 'wp_autoplugin_settings' );
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'OpenAI API Key', 'wp-autoplugin' ); ?></th>
				<td><input type="password" name="wp_autoplugin_openai_api_key" value="<?php echo esc_attr( get_option( 'wp_autoplugin_openai_api_key' ) ); ?>" class="large-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Anthropic API Key', 'wp-autoplugin' ); ?></th>
				<td><input type="password" name="wp_autoplugin_anthropic_api_key" value="<?php echo esc_attr( get_option( 'wp_autoplugin_anthropic_api_key' ) ); ?>" class="large-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Google Gemini API Key', 'wp-autoplugin' ); ?></th>
				<td><input type="password" name="wp_autoplugin_google_api_key" value="<?php echo esc_attr( get_option( 'wp_autoplugin_google_api_key' ) ); ?>" class="large-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'xAI API Key', 'wp-autoplugin' ); ?></th>
				<td><input type="password" name="wp_autoplugin_xai_api_key" value="<?php echo esc_attr( get_option( 'wp_autoplugin_xai_api_key' ) ); ?>" class="large-text" /></td>
			</tr>
<?php
function render_model_dropdown( $name, $selected_value ) {
	$models        = Admin::get_models();
	$custom_models = get_option( 'wp_autoplugin_custom_models', [] );

	echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
	echo '<option value="" ' . selected( $selected_value, '', false ) . '>' . esc_html__( 'Use Default Model', 'wp-autoplugin' ) . '</option>';

	foreach ( $models as $provider => $model ) {
		echo '<optgroup label="' . esc_attr( $provider ) . '">';
		foreach ( $model as $key => $value ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $selected_value, $key, false ) . '>' . esc_html( $value ) . '</option>';
		}
		echo '</optgroup>';
	}

	if ( ! empty( $custom_models ) ) {
		echo '<optgroup label="' . esc_attr__( 'Custom Models', 'wp-autoplugin' ) . '">';
		foreach ( $custom_models as $model ) {
			echo '<option value="' . esc_attr( $model['name'] ) . '" ' . selected( $selected_value, $model['name'], false ) . '>' . esc_html( $model['name'] ) . '</option>';
		}
		echo '</optgroup>';
	}

	echo '</select>';
}
?>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Default Model', 'wp-autoplugin' ); ?></th>
				<td>
					<select name="wp_autoplugin_model" id="wp_autoplugin_model">
						<?php
						$models = Admin::get_models();
						foreach ( $models as $provider => $model ) {
							echo '<optgroup label="' . esc_attr( $provider ) . '">';
							foreach ( $model as $key => $value ) {
								echo '<option value="' . esc_attr( $key ) . '" ' . selected( get_option( 'wp_autoplugin_model' ), $key ) . '>' . esc_html( $value ) . '</option>';
							}
							echo '</optgroup>';
						}
						?>
						<optgroup label="<?php esc_attr_e( 'Custom Models', 'wp-autoplugin' ); ?>" id="custom-models">
							<?php
							$custom_models = get_option( 'wp_autoplugin_custom_models', [] );
							foreach ( $custom_models as $model ) {
								echo '<option value="' . esc_attr( $model['name'] ) . '" ' . selected( get_option( 'wp_autoplugin_model' ), $model['name'] ) . '>' . esc_html( $model['name'] ) . '</option>';
							}
							?>
						</optgroup>
					</select>
					<p>
						<button type="button" id="toggle-specialized-models" class="button-link"><?php esc_html_e( 'Show specialized model settings', 'wp-autoplugin' ); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></button>
					</p>
				</td>
			</tr>
		</table>
		
		<?php
		// Check if any specialized model is set.
		$planner_model          = get_option( 'wp_autoplugin_planner_model' );
		$coder_model            = get_option( 'wp_autoplugin_coder_model' );
		$reviewer_model         = get_option( 'wp_autoplugin_reviewer_model' );
		$has_specialized_models = ! empty( $planner_model ) || ! empty( $coder_model ) || ! empty( $reviewer_model );
		?>
		<div class="wp-autoplugin-per-step-models" style="<?php echo $has_specialized_models ? '' : 'display: none;'; ?>">
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Planner Model', 'wp-autoplugin' ); ?></th>
					<td>
						<?php render_model_dropdown( 'wp_autoplugin_planner_model', get_option( 'wp_autoplugin_planner_model' ) ); ?>
						<p class="description"><?php esc_html_e( 'Used for planning plugin extensions and analyzing hooks. Falls back to Default Model if not set.', 'wp-autoplugin' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Coder Model', 'wp-autoplugin' ); ?></th>
					<td>
						<?php render_model_dropdown( 'wp_autoplugin_coder_model', get_option( 'wp_autoplugin_coder_model' ) ); ?>
						<p class="description"><?php esc_html_e( 'Used for generating and fixing code. Falls back to Default Model if not set.', 'wp-autoplugin' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Reviewer Model', 'wp-autoplugin' ); ?></th>
					<td>
						<?php render_model_dropdown( 'wp_autoplugin_reviewer_model', get_option( 'wp_autoplugin_reviewer_model' ) ); ?>
						<p class="description"><?php esc_html_e( 'Used for explaining code and reviewing generated plugins. Falls back to Default Model if not set.', 'wp-autoplugin' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Generate Plugins', 'wp-autoplugin' ); ?></th>
				<td>
					<select name="wp_autoplugin_plugin_mode" id="wp_autoplugin_plugin_mode">
						<?php $current_mode = get_option( 'wp_autoplugin_plugin_mode', 'simple' ); ?>
						<option value="simple" <?php selected( $current_mode, 'simple' ); ?>><?php esc_html_e( 'Simple plugin mode (single-file plugins)', 'wp-autoplugin' ); ?></option>
						<option value="complex" <?php selected( $current_mode, 'complex' ); ?>><?php esc_html_e( 'Complex plugin mode (multi-file plugins)', 'wp-autoplugin' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Complex mode uses more tokens. For best results, use capable models.', 'wp-autoplugin' ); ?>
					</p>
					<p>
						<button type="button" id="toggle-advanced-params" class="button-link"><?php esc_html_e( 'Show advanced AI parameters', 'wp-autoplugin' ); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></button>
					</p>
				</td>
			</tr>
		</table>

		<?php
		// Check if any advanced parameter is set.
		$max_tokens       = get_option( 'wp_autoplugin_max_tokens', 0 );
		$temperature      = get_option( 'wp_autoplugin_temperature', 0 );
		$top_p            = get_option( 'wp_autoplugin_top_p', 0 );
		$seed             = get_option( 'wp_autoplugin_seed', '' );
		$stop_sequences   = get_option( 'wp_autoplugin_stop_sequences', '' );
		$response_format  = get_option( 'wp_autoplugin_response_format', '' );
		$has_advanced     = ! empty( $max_tokens ) || ! empty( $temperature ) || ! empty( $top_p ) || ! empty( $seed ) || ! empty( $stop_sequences ) || ! empty( $response_format );
		?>
		<div class="wp-autoplugin-advanced-params" style="<?php echo $has_advanced ? '' : 'display: none;'; ?>">
			<h3><?php esc_html_e( 'Advanced AI Parameters', 'wp-autoplugin' ); ?></h3>
			<p class="description" style="margin-bottom: 20px;">
				<?php esc_html_e( 'These parameters override the default model settings. Leave empty (or 0) to use the model defaults. Changing these values may affect the quality and consistency of generated code.', 'wp-autoplugin' ); ?>
			</p>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<?php esc_html_e( 'Max Tokens', 'wp-autoplugin' ); ?>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'Maximum number of tokens to generate. Higher values allow longer code outputs. Set to 0 to use model default.', 'wp-autoplugin' ); ?>"></span>
					</th>
					<td>
						<input type="number" name="wp_autoplugin_max_tokens" value="<?php echo esc_attr( $max_tokens ); ?>" min="0" max="200000" step="1" class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'Recommended: 16384 or higher for complex plugins. 0 = use model default. Higher values prevent code truncation but increase API costs.', 'wp-autoplugin' ); ?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php esc_html_e( 'Temperature', 'wp-autoplugin' ); ?>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'Controls randomness. Lower values (0.0-0.5) make output more deterministic, higher values (0.5-2.0) make it more creative.', 'wp-autoplugin' ); ?>"></span>
					</th>
					<td>
						<input type="number" name="wp_autoplugin_temperature" value="<?php echo esc_attr( $temperature ); ?>" min="0" max="2" step="0.1" class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'Range: 0.0 to 2.0. Recommended: 0.2 for code generation. 0 = use model default.', 'wp-autoplugin' ); ?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php esc_html_e( 'Top P', 'wp-autoplugin' ); ?>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'Nucleus sampling. Controls diversity of output by limiting token choices to top probability mass.', 'wp-autoplugin' ); ?>"></span>
					</th>
					<td>
						<input type="number" name="wp_autoplugin_top_p" value="<?php echo esc_attr( $top_p ); ?>" min="0" max="1" step="0.05" class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'Range: 0.0 to 1.0. Recommended: 0.9-1.0. 0 = use model default. Not recommended to use with Temperature.', 'wp-autoplugin' ); ?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php esc_html_e( 'Seed', 'wp-autoplugin' ); ?>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'Random seed for reproducible outputs. Same seed with same inputs will produce similar outputs.', 'wp-autoplugin' ); ?>"></span>
					</th>
					<td>
						<input type="number" name="wp_autoplugin_seed" value="<?php echo esc_attr( $seed ); ?>" min="0" step="1" class="regular-text" />
						<p class="description">
							<?php esc_html_e( 'Any positive integer. Leave empty for random generation. Useful for testing and debugging.', 'wp-autoplugin' ); ?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php esc_html_e( 'Stop Sequences', 'wp-autoplugin' ); ?>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'Sequences where the API will stop generating further tokens.', 'wp-autoplugin' ); ?>"></span>
					</th>
					<td>
						<textarea name="wp_autoplugin_stop_sequences" rows="3" class="large-text"><?php echo esc_textarea( $stop_sequences ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'One stop sequence per line. Up to 4 sequences. Example: "```" to stop at code blocks.', 'wp-autoplugin' ); ?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php esc_html_e( 'Response Format', 'wp-autoplugin' ); ?>
						<span class="dashicons dashicons-info" title="<?php esc_attr_e( 'Force structured output format. Only works with compatible models (GPT-4o, GPT-4-turbo, etc.).', 'wp-autoplugin' ); ?>"></span>
					</th>
					<td>
						<select name="wp_autoplugin_response_format" class="regular-text">
							<option value="" <?php selected( $response_format, '' ); ?>><?php esc_html_e( 'Default (text)', 'wp-autoplugin' ); ?></option>
							<option value="json_object" <?php selected( $response_format, 'json_object' ); ?>><?php esc_html_e( 'JSON Object', 'wp-autoplugin' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'JSON Object mode ensures the model returns valid JSON. Only use when planning mode is enabled.', 'wp-autoplugin' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Custom Models', 'wp-autoplugin' ); ?></th>
				<td>
					<div id="custom-models-list">
						<!-- List will be populated via JS -->
						<div class="custom-models-items"></div>
					</div>

					<div id="add-custom-model-form">
						<input type="text" id="custom-model-name" placeholder="<?php esc_attr_e( 'Model Name (User-defined Label)', 'wp-autoplugin' ); ?>" class="large-text">
						<input type="url" id="custom-model-url" placeholder="<?php esc_attr_e( 'API Endpoint URL', 'wp-autoplugin' ); ?>" class="large-text">
						<input type="text" id="custom-model-parameter" placeholder="<?php esc_attr_e( '"model" Parameter Value', 'wp-autoplugin' ); ?>" class="large-text">
						<input type="password" id="custom-model-api-key" placeholder="<?php esc_attr_e( 'API Key', 'wp-autoplugin' ); ?>" class="large-text">
						<textarea id="custom-model-headers" placeholder="<?php esc_attr_e( 'Additional Headers (one per line, name=value)', 'wp-autoplugin' ); ?>" rows="4" class="large-text"></textarea>
						<button type="button" id="add-custom-model" class="button"><?php esc_html_e( 'Add Custom Model', 'wp-autoplugin' ); ?></button>
					</div>

					<input type="hidden" name="wp_autoplugin_custom_models" id="wp_autoplugin_custom_models" value="<?php echo esc_attr( wp_json_encode( get_option( 'wp_autoplugin_custom_models', [] ) ) ); ?>">
					<input type="hidden" id="wp_autoplugin_settings_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp_autoplugin_nonce' ) ); ?>">

					<p class="description"><?php esc_html_e( 'Add any custom models you want to use with WP-Autoplugin. These models will be available in the model selection dropdown. The API must be compatible with the OpenAI API.', 'wp-autoplugin' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
<script>
	jQuery(document).ready(function($) {
		let customModels = JSON.parse($('#wp_autoplugin_custom_models').val() || '[]');
		const nonce = $('#wp_autoplugin_settings_nonce').val();
		
		// Toggle specialized models section
		$('#toggle-specialized-models').on('click', function() {
			const $section = $('.wp-autoplugin-per-step-models');
			const $button = $(this);
			const $icon = $button.find('.dashicons');

			if ($section.is(':visible')) {
				$section.hide();
				$button.html('<?php esc_html_e( 'Show specialized model settings', 'wp-autoplugin' ); ?> <span class="dashicons dashicons-arrow-down-alt2"></span>');
			} else {
				$section.show();
				$button.html('<?php esc_html_e( 'Hide specialized model settings', 'wp-autoplugin' ); ?> <span class="dashicons dashicons-arrow-up-alt2"></span>');
			}
		});

		// Toggle advanced parameters section
		$('#toggle-advanced-params').on('click', function() {
			const $section = $('.wp-autoplugin-advanced-params');
			const $button = $(this);

			if ($section.is(':visible')) {
				$section.hide();
				$button.html('<?php esc_html_e( 'Show advanced AI parameters', 'wp-autoplugin' ); ?> <span class="dashicons dashicons-arrow-down-alt2"></span>');
			} else {
				$section.show();
				$button.html('<?php esc_html_e( 'Hide advanced AI parameters', 'wp-autoplugin' ); ?> <span class="dashicons dashicons-arrow-up-alt2"></span>');
			}
		});
		
		// Update toggle button text based on initial visibility
		if ($('.wp-autoplugin-per-step-models').is(':visible')) {
			$('#toggle-specialized-models').html('<?php esc_html_e( 'Hide specialized model settings', 'wp-autoplugin' ); ?> <span class="dashicons dashicons-arrow-up-alt2"></span>');
		}
		if ($('.wp-autoplugin-advanced-params').is(':visible')) {
			$('#toggle-advanced-params').html('<?php esc_html_e( 'Hide advanced AI parameters', 'wp-autoplugin' ); ?> <span class="dashicons dashicons-arrow-up-alt2"></span>');
		}
		
		// Later this may be moved to a wp_localize_script call
		const wp_autoplugin_i18n = {
			details: '<?php echo esc_js( __( 'Details', 'wp-autoplugin' ) ); ?>',
			url: '<?php echo esc_js( __( 'URL', 'wp-autoplugin' ) ); ?>',
			modelParameter: '<?php echo esc_js( __( 'Model Parameter', 'wp-autoplugin' ) ); ?>',
			apiKey: '<?php echo esc_js( __( 'API Key', 'wp-autoplugin' ) ); ?>',
			headers: '<?php echo esc_js( __( 'Headers', 'wp-autoplugin' ) ); ?>',
			remove: '<?php echo esc_js( __( 'Remove', 'wp-autoplugin' ) ); ?>',
			fillOutFields: '<?php echo esc_js( __( 'Please fill out all required fields.', 'wp-autoplugin' ) ); ?>',
			removeModel: '<?php echo esc_js( __( 'Are you sure you want to remove this model?', 'wp-autoplugin' ) ); ?>',
			errorSavingModel: '<?php echo esc_js( __( 'Error saving model', 'wp-autoplugin' ) ); ?>',
		};

		function updateCustomModelsList() {
			const selectedDefault = $('#wp_autoplugin_model').val();
			const selectedPlanner = $('#wp_autoplugin_planner_model').val();
			const selectedCoder = $('#wp_autoplugin_coder_model').val();
			const selectedReviewer = $('#wp_autoplugin_reviewer_model').val();
			
			const $list = $('.custom-models-items').empty();
			const $optgroup = $('#custom-models').empty();
			
			// Clear custom model options from all dropdowns
			$('#wp_autoplugin_planner_model optgroup[label="Custom Models"]').remove();
			$('#wp_autoplugin_coder_model optgroup[label="Custom Models"]').remove();
			$('#wp_autoplugin_reviewer_model optgroup[label="Custom Models"]').remove();
			
			customModels.forEach((model, index) => {
				const $item = $('<div class="custom-model-item">')
					.append(`<strong>${model.name}</strong>`)
					.append(`<details><summary>${wp_autoplugin_i18n.details}</summary><p><strong>${wp_autoplugin_i18n.url}:</strong> ${model.url}</p><p><strong>${wp_autoplugin_i18n.modelParameter}:</strong> ${model.modelParameter}</p><p><strong>${wp_autoplugin_i18n.apiKey}:</strong> ***${model.apiKey.substr(-3)}</p><p><strong>${wp_autoplugin_i18n.headers}:</strong> ${model.headers.join(', ')}</p></details>`)
					.append(`<button type="button" class="button remove-model" data-index="${index}">${wp_autoplugin_i18n.remove}</button>`);
				$list.append($item);

				$optgroup.append(`<option value="${model.name}">${model.name}</option>`);
			});
			
			// Add custom models to specialized dropdowns if any exist
			if (customModels.length > 0) {
				const customOptgroup = `<optgroup label="<?php esc_attr_e( 'Custom Models', 'wp-autoplugin' ); ?>">
					${customModels.map(model => `<option value="${model.name}">${model.name}</option>`).join('')}
				</optgroup>`;
				
				$('#wp_autoplugin_planner_model').append(customOptgroup);
				$('#wp_autoplugin_coder_model').append(customOptgroup);
				$('#wp_autoplugin_reviewer_model').append(customOptgroup);
			}
			
			$('#wp_autoplugin_custom_models').val(JSON.stringify(customModels));
			
			// Restore selected values
			$('#wp_autoplugin_model').val(selectedDefault);
			$('#wp_autoplugin_planner_model').val(selectedPlanner);
			$('#wp_autoplugin_coder_model').val(selectedCoder);
			$('#wp_autoplugin_reviewer_model').val(selectedReviewer);
		}

		$('#add-custom-model').on('click', function() {
			const name = $('#custom-model-name').val();
			const url = $('#custom-model-url').val();
			const modelParameter = $('#custom-model-parameter').val();
			const apiKey = $('#custom-model-api-key').val();
			const headers = $('#custom-model-headers').val();

			if (!name || !url || !apiKey) {
				alert(wp_autoplugin_i18n.fillOutFields);
				return;
			}

			const model = {
				name: name,
				url: url,
				modelParameter: modelParameter,
				apiKey: apiKey,
				headers: headers.split('\n').filter(h => h.trim())
			};

			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'wp_autoplugin_add_model',
					model: model,
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						customModels = response.data.models;
						updateCustomModelsList();
						$('#custom-model-name, #custom-model-url, #custom-model-param, #custom-model-api-key, #custom-model-headers').val('');
					} else {
						alert(response.data.message || 'Error saving model');
					}
				}
			});
		});

		$(document).on('click', '.remove-model', function() {
			const index = $(this).data('index');
			if (confirm(wp_autoplugin_i18n.removeModel)) {
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'wp_autoplugin_remove_model',
						index: index,
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							customModels = response.data.models;
							updateCustomModelsList();
						} else {
							alert(response.data.message || wp_autoplugin_i18n.errorSavingModel);
						}
					}
				});
			}
		});

		updateCustomModelsList();
	});
</script>
<style>
	/* Specialized Models Toggle */
	#toggle-specialized-models {
		margin-top: 8px;
		display: block;
		color: #2271b1;
		text-decoration: none;
		padding: 0;
	}
	
	#toggle-specialized-models:focus {
		box-shadow: none;
		outline: none;
	}
	
	#toggle-specialized-models .dashicons {
		font-size: 16px;
		line-height: 1.5;
		vertical-align: middle;
	}
	
	.wp-autoplugin-per-step-models h3 {
		margin-top: 0;
		padding-top: 0;
	}
	
	/* Custom Models Section Styling */
	#custom-models-list {
		margin-bottom: 20px;
	}

	.custom-model-item {
		background: #fff;
		border: 1px solid #ccd0d4;
		border-radius: 4px;
		padding: 15px 15px 5px;
		margin-bottom: 15px;
		position: relative;
	}

	.custom-model-item strong {
		font-size: 14px;
		color: #1d2327;
	}

	.custom-model-item details {
		margin: 10px 0;
	}

	.custom-model-item summary {
		cursor: pointer;
		color: #2271b1;
		padding: 5px 0;
	}

	.custom-model-item summary:hover {
		color: #135e96;
	}

	.custom-model-item p {
		margin: 8px 0;
		color: #50575e;
	}

	.custom-model-item .remove-model {
		position: absolute;
		right: 15px;
		top: 12px;
		color: #b32d2e;
		border-color: #b32d2e;
	}

	.custom-model-item .remove-model:hover {
		background: #b32d2e;
		color: #fff;
	}

	/* Add Custom Model Form */
	#add-custom-model-form {
		background: #f6f7f7;
		border: 1px solid #c3c4c7;
		border-radius: 4px;
		padding: 20px;
		margin-bottom: 15px;
	}

	#add-custom-model-form input,
	#add-custom-model-form textarea {
		margin-bottom: 15px;
	}

	#add-custom-model-form .button {
		margin-top: 5px;
	}

	/* Description Text */
	.description {
		color: #646970;
		font-style: italic;
		margin-top: 15px;
	}

	/* Input Focus States */
	#add-custom-model-form input:focus,
	#add-custom-model-form textarea:focus {
		border-color: #2271b1;
		box-shadow: 0 0 0 1px #2271b1;
		outline: 2px solid transparent;
	}

	/* make it a box */
	.wp-autoplugin-per-step-models {
		background: #fff;
		border: 1px solid #ccd0d4;
		border-radius: 4px;
		padding: 0 2rem;
		margin-bottom: 15px;
	}

	/* Advanced Parameters Section */
	.wp-autoplugin-advanced-params {
		background: #fff;
		border: 1px solid #ccd0d4;
		border-radius: 4px;
		padding: 0 2rem;
		margin-bottom: 15px;
		margin-top: 15px;
	}

	.wp-autoplugin-advanced-params h3 {
		margin-top: 10px;
		padding-top: 10px;
		color: #1d2327;
	}

	.wp-autoplugin-advanced-params .dashicons-info {
		color: #2271b1;
		cursor: help;
		font-size: 18px;
		vertical-align: middle;
	}

	#toggle-advanced-params {
		margin-top: 8px;
		display: block;
		color: #2271b1;
		text-decoration: none;
		padding: 0;
	}

	#toggle-advanced-params:focus {
		box-shadow: none;
		outline: none;
	}

	#toggle-advanced-params .dashicons {
		font-size: 16px;
		line-height: 1.5;
		vertical-align: middle;
	}
</style>
