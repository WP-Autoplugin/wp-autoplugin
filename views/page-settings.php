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
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Model', 'wp-autoplugin' ); ?></th>
				<td>
					<select name="wp_autoplugin_model" id="wp_autoplugin_model">
						<?php
						$models = Admin::$models;
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
				</td>
			</tr>
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
				</td>
			</tr>
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
			const selected = $('#wp_autoplugin_model').val();
			const $list = $('.custom-models-items').empty();
			const $optgroup = $('#custom-models').empty();
			customModels.forEach((model, index) => {
				const $item = $('<div class="custom-model-item">')
					.append(`<strong>${model.name}</strong>`)
					.append(`<details><summary>${wp_autoplugin_i18n.details}</summary><p><strong>${wp_autoplugin_i18n.url}:</strong> ${model.url}</p><p><strong>${wp_autoplugin_i18n.modelParameter}:</strong> ${model.modelParameter}</p><p><strong>${wp_autoplugin_i18n.apiKey}:</strong> ***${model.apiKey.substr(-3)}</p><p><strong>${wp_autoplugin_i18n.headers}:</strong> ${model.headers.join(', ')}</p></details>`)
					.append(`<button type="button" class="button remove-model" data-index="${index}">${wp_autoplugin_i18n.remove}</button>`);
				$list.append($item);

				$optgroup.append(`<option value="${model.name}">${model.name}</option>`);
			});
			$('#wp_autoplugin_custom_models').val(JSON.stringify(customModels));
			// Select the right model after updating the list
			$('#wp_autoplugin_model').val(selected);
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
</style>
