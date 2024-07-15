<?php

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin_file = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );
$plugin_file = str_replace( '../', '', $plugin_file );
$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
$plugin_data = get_plugin_data( $plugin_path );

$value = '';
if ( ! empty( $_GET['error_message'] ) ) {
	$value = sprintf(
		__( 'Error while activating the plugin: %s', 'wp-autoplugin' ),
		wp_unslash( $_GET['error_message'] )
	);
}

$is_plugin_active = false;
if ( isset( $_GET['plugin'] ) ) {
	$plugin_file = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );
	$plugin_file = str_replace( '../', '', $plugin_file );
	$is_plugin_active = is_plugin_active( $plugin_file );
}
?>
<div class="wp-autoplugin-admin-container">
	<div class="wrap wp-autoplugin step-1-extend">
		<h1><?php printf( __( 'Extend This Plugin: %s', 'wp-autoplugin' ), esc_html( $plugin_data['Name'] ) ); ?></h1>
		<form method="post" action="" id="extend-plugin-form">
			<?php wp_nonce_field( 'extend_plugin', 'extend_plugin_nonce' ); ?>
			<p><?php _e( 'Describe the changes you would like to make to the plugin. Include as much detail as possible:', 'wp-autoplugin' ); ?></p>
			<textarea name="plugin_issue" id="plugin_issue" rows="10" cols="100"><?php echo esc_textarea( $value ); ?></textarea>
			<?php submit_button( __( 'Extend Plugin', 'wp-autoplugin' ), 'primary', 'extend_plugin' ); ?>
			<input type="hidden" name="plugin_file" value="<?php echo esc_attr( $plugin_file ); ?>" id="plugin_file" />
		</form>
		<div id="extend-plugin-message" class="autoplugin-message"></div>
	</div>
	<div class="wrap wp-autoplugin step-2-plan" style="display: none;">
		<h1><?php _e( 'Generated Plan', 'wp-autoplugin' ); ?></h1>
		<form method="post" action="" id="extend-code-form">
			<?php wp_nonce_field( 'extend_code', 'extend_code_nonce' ); ?>
			<p><?php _e( 'Review or edit the generated plan:', 'wp-autoplugin' ); ?></p>
			<textarea name="plugin_plan_container" id="plugin_plan_container" rows="20" cols="100"></textarea>
			<div class="autoplugin-actions">
				<button type="button" id="edit-issue" class="button"><?php _e( '&laquo; Edit Issue', 'wp-autoplugin' ); ?></button>
				<?php submit_button( __( 'Generate Plugin Code', 'wp-autoplugin' ), 'primary', 'extend_code' ); ?>
			</div>
		</form>
		<div id="extend-code-message" class="autoplugin-message"></div>
	</div>
	<div class="wrap wp-autoplugin step-3-done" style="display: none;">
		<h1><?php printf( __( 'Extended Plugin: %s', 'wp-autoplugin' ), esc_html( $plugin_data['Name'] ) ); ?></h1>
		<form method="post" action="" id="extended-plugin-form">
			<?php wp_nonce_field( 'extended_plugin', 'extended_plugin_nonce' ); ?>
			<p><?php _e( 'The plugin has been extended successfully. You can review the changes before activating it:', 'wp-autoplugin' ); ?></p>
			<textarea name="extended_plugin_code" id="extended_plugin_code" rows="20" cols="100"></textarea>
			
			<?php if ( $is_plugin_active ) : ?>
				<div class="autoplugin-code-warning">
					<strong><?php _e( 'Warning:', 'wp-autoplugin' ); ?></strong> <?php _e( 'This plugin is active, changes will take effect immediately.', 'wp-autoplugin' ); ?>
				</div>
			<?php endif; ?>

			<div class="autoplugin-actions">
				<button type="button" id="edit-plan" class="button"><?php _e( '&laquo; Edit Plan', 'wp-autoplugin' ); ?></button>
				<?php submit_button( __( 'Save Changes', 'wp-autoplugin' ), 'primary', 'extended_plugin' ); ?>
			</div>
		</form>
		<div id="extended-plugin-message" class="autoplugin-message"></div>
	</div>
	<?php $this->output_admin_footer(); ?>
</div>