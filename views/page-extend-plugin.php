<?php
/**
 * Admin view for the Extend Autoplugin page.
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

if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'wp-autoplugin-extend-plugin' ) ) {
	wp_die( esc_html__( 'Nonce verification failed. Please try again.', 'wp-autoplugin' ) );
}

$plugin_file      = '';
$is_plugin_active = false;
if ( isset( $_GET['plugin'] ) ) {
	$plugin_file      = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );
	$plugin_file      = str_replace( '../', '', $plugin_file );
	$is_plugin_active = is_plugin_active( $plugin_file );
}
$plugin_path = WP_CONTENT_DIR . '/plugins/' . $plugin_file;
$plugin_data = get_plugin_data( $plugin_path );

$value = '';
if ( isset( $_GET['error_message'] ) && check_admin_referer( 'extend-plugin', 'extend_plugin_nonce' ) ) {
	$value = sprintf(
		// translators: %s: error message.
		esc_html__( 'Error while activating the plugin: %s', 'wp-autoplugin' ),
		sanitize_text_field( wp_unslash( $_GET['error_message'] ) )
	);
}

?>
<div class="wp-autoplugin-admin-container">
	<div class="wrap wp-autoplugin step-1-extend">
		<?php /* translators: %s: plugin name. */ ?>
		<h1><?php printf( esc_html__( 'Extend This Plugin: %s', 'wp-autoplugin' ), esc_html( $plugin_data['Name'] ) ); ?></h1>
		<form method="post" action="" id="extend-plugin-form">
			<?php wp_nonce_field( 'extend_plugin', 'extend_plugin_nonce' ); ?>
			<p><?php esc_html_e( 'Describe the changes you would like to make to the plugin. Include as much detail as possible:', 'wp-autoplugin' ); ?></p>
			<textarea name="plugin_issue" id="plugin_issue" rows="10" cols="100"><?php echo esc_textarea( $value ); ?></textarea>
			<?php submit_button( esc_html__( 'Extend Plugin', 'wp-autoplugin' ), 'primary', 'extend_plugin' ); ?>
			<input type="hidden" name="plugin_file" value="<?php echo esc_attr( $plugin_file ); ?>" id="plugin_file" />
		</form>
		<div id="extend-plugin-message" class="autoplugin-message"></div>
	</div>
	<div class="wrap wp-autoplugin step-2-plan" style="display: none;">
		<h1><?php esc_html_e( 'Generated Plan', 'wp-autoplugin' ); ?></h1>
		<form method="post" action="" id="extend-code-form">
			<?php wp_nonce_field( 'extend_code', 'extend_code_nonce' ); ?>
			<p><?php esc_html_e( 'Review or edit the generated plan:', 'wp-autoplugin' ); ?></p>
			<div id="plugin_plan_container"></div>
			<div class="autoplugin-actions">
				<button type="button" id="edit-issue" class="button"><?php esc_html_e( '&laquo; Edit Issue', 'wp-autoplugin' ); ?></button>
				<?php submit_button( esc_html__( 'Generate Plugin Code', 'wp-autoplugin' ), 'primary', 'extend_code' ); ?>
			</div>
		</form>
		<div id="extend-code-message" class="autoplugin-message"></div>
	</div>
	<div class="wrap wp-autoplugin step-3-done" style="display: none;">
		<?php /* translators: %s: plugin name. */ ?>
		<h1><?php printf( esc_html__( 'Extended Plugin: %s', 'wp-autoplugin' ), esc_html( $plugin_data['Name'] ) ); ?></h1>
		<form method="post" action="" id="extended-plugin-form">
			<?php wp_nonce_field( 'extended_plugin', 'extended_plugin_nonce' ); ?>
			<p><?php esc_html_e( 'The plugin has been extended successfully. You can review the changes before activating it:', 'wp-autoplugin' ); ?></p>

			<!-- Generation progress (complex flow) -->
			<div class="generation-progress" style="display: none;">
				<div class="progress-bar-container">
					<div class="progress-bar" id="file-generation-progress"></div>
				</div>
				<span class="progress-text" id="progress-text"><?php esc_html_e( 'Generating files...', 'wp-autoplugin' ); ?></span>
			</div>

			<!-- Multi-file editor UI -->
			<div class="generated-files-container" id="extended-files-container">
				<div class="files-tabs" id="files-tabs">
					<!-- Tabs populated by JS -->
				</div>
				<div class="file-content" id="file-content">
					<!-- Editors populated by JS -->
				</div>
			</div>

			<!-- Fallback textarea kept (hidden) for backward compatibility -->
			<textarea name="extended_plugin_code" id="extended_plugin_code" rows="20" cols="100" style="display:none"></textarea>
			
			<?php if ( $is_plugin_active ) : ?>
				<div class="autoplugin-code-warning">
					<strong><?php esc_html_e( 'Warning:', 'wp-autoplugin' ); ?></strong> <?php esc_html_e( 'This plugin is active, changes will take effect immediately.', 'wp-autoplugin' ); ?>
				</div>
			<?php endif; ?>

			<div class="autoplugin-actions">
				<button type="button" id="edit-plan" class="button"><?php esc_html_e( '&laquo; Edit Plan', 'wp-autoplugin' ); ?></button>
				<?php submit_button( esc_html__( 'Save Changes', 'wp-autoplugin' ), 'primary', 'extended_plugin' ); ?>
			</div>
		</form>
		<div id="extended-plugin-message" class="autoplugin-message"></div>
	</div>
	<?php $this->output_admin_footer(); ?>
</div>
