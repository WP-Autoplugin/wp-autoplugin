<?php

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wp-autoplugin-admin-container">
	<div class="wrap wp-autoplugin step-1-generation">
		<h1><?php _e( 'Generate Plugin', 'wp-autoplugin' ); ?></h1>
		<form method="post" action="" id="generate-plan-form">
			<?php wp_nonce_field( 'generate_plan', 'generate_plan_nonce' ); ?>
			<p><?php _e( 'Enter a description of the plugin you want to generate:', 'wp-autoplugin' ); ?></p>
			<input name="plugin_description" id="plugin_description" type="text" size="100" />
			<?php submit_button( __( 'Generate Plan', 'wp-autoplugin' ), 'primary button-hero', 'generate_plan', false ); ?>
		</form>
		<div id="generate-plan-message" class="autoplugin-message"></div>
	</div>
	<div class="wrap wp-autoplugin step-2-plan" style="display: none;">
		<h1><?php _e( 'Generated Plan', 'wp-autoplugin' ); ?></h1>
		<form method="post" action="" id="generate-code-form">
			<?php wp_nonce_field( 'generate_code', 'generate_code_nonce' ); ?>
			<p><?php _e( 'Review or edit the generated plan:', 'wp-autoplugin' ); ?></p>
			<!-- The plan contains the following parts: plugin_name, design_and_architecture, detailed_feature_description, user_interface, security_considerations, testing_plan -->
			<!-- A part can contain multiple lines of text or another nested part -->
			<!-- We will display it as an accordion with each part in a separate section -->
			<div id="plugin_plan_container"></div>
			<div class="autoplugin-actions">
				<button type="button" id="edit-description" class="button"><?php _e( '&laquo; Edit Description', 'wp-autoplugin' ); ?></button>
				<?php submit_button( __( 'Generate Plugin Code', 'wp-autoplugin' ), 'primary', 'generate_code', false ); ?>
			</div>
		</form>
		<div id="generate-code-message" class="autoplugin-message"></div>
	</div>
	<div class="wrap wp-autoplugin step-3-code" style="display: none;">
		<h1><?php _e( 'Generated Plugin Code', 'wp-autoplugin' ); ?></h1>
		<form method="post" action="" id="create-plugin-form">
			<?php wp_nonce_field( 'create_plugin', 'create_plugin_nonce' ); ?>
			<p><?php _e( 'The plugin code has been generated successfully. You can review and edit the plugin file before activating it:', 'wp-autoplugin' ); ?></p>
			<textarea name="plugin_code" id="plugin_code" rows="20" cols="100"></textarea>
			
			<div class="autoplugin-code-warning">
				<strong><?php _e( 'Warning:', 'wp-autoplugin' ); ?></strong> <?php _e( 'AI-generated code may be unstable or insecure; use only after careful review and testing.', 'wp-autoplugin' ); ?>
			</div>

			<div class="autoplugin-actions">
				<button type="button" id="edit-plan" class="button"><?php _e( '&laquo; Edit Plan', 'wp-autoplugin' ); ?></button>
				<?php submit_button( __( 'Install Plugin', 'wp-autoplugin' ), 'primary', 'create_plugin', false ); ?>
			</div>
		</form>
		<div id="create-plugin-message" class="autoplugin-message"></div>
	</div>
<?php $this->output_admin_footer(); ?>
</div>