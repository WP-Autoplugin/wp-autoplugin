<?php
/**
 * Admin view for the Extend Plugin with Hooks page.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'wp-autoplugin-extend-hooks' ) ) {
	wp_die( esc_html__( 'Nonce verification failed. Please try again.', 'wp-autoplugin' ) );
}

$is_plugin_active = is_plugin_active( $plugin_file );

?>
<div class="wp-autoplugin-admin-container">
	<div class="wrap wp-autoplugin step-1-extend">
		<?php /* translators: %s: plugin name. */ ?>
		<h1><?php printf( esc_html__( 'Extend This Plugin with Hooks: %s', 'wp-autoplugin' ), esc_html( $plugin_data['Name'] ) ); ?></h1>
		<!-- Loading message, visible by default -->
		<div id="hooks-loading" style="display: block;">
			<p><?php esc_html_e( 'Extracting plugin hooks, please wait...', 'wp-autoplugin' ); ?></p>
		</div>
		<!-- Hooks list and form, hidden by default -->
		<div id="hooks-content" style="display: none;">
			<details id="hooks-list">
				<summary id="hooks-summary"></summary>
				<ul id="hooks-ul"></ul>
				<p class="copy-hooks-description">
					<button type="button" id="copy-hooks" class="button button-small button-secondary">
						<?php esc_html_e( 'Copy Hooks', 'wp-autoplugin' ); ?>
					</button>
					<?php esc_html_e( 'Copy all hooks to clipboard, along with relevant context, to use with your LLM of choice.', 'wp-autoplugin' ); ?>
				</p>
			</details>
			<form method="post" action="" id="extend-hooks-form">
				<p><?php esc_html_e( 'Describe the extension you would like to make to the plugin using its hooks:', 'wp-autoplugin' ); ?></p>
				<textarea name="plugin_issue" id="plugin_issue" rows="10" cols="100"></textarea>
				<?php submit_button( esc_html__( 'Generate Extension Plan', 'wp-autoplugin' ), 'primary', 'generate_plan' ); ?>
				<input type="hidden" name="plugin_file" value="<?php echo esc_attr( $plugin_file ); ?>" id="plugin_file" />
			</form>
		</div>
		<div id="extend-hooks-message" class="autoplugin-message"></div>
	</div>
	<div class="wrap wp-autoplugin step-2-plan" style="display: none;">
		<h1><?php esc_html_e( 'Generated Extension Plan', 'wp-autoplugin' ); ?></h1>
		<form method="post" action="" id="extend-hooks-code-form">
			<p><?php esc_html_e( 'Review or edit the generated plan for the extension plugin:', 'wp-autoplugin' ); ?></p>
			<textarea name="plugin_plan_container" id="plugin_plan_container" rows="20" cols="100"></textarea>
			<p>
				<label for="plugin_name"><?php esc_html_e( 'Plugin Name:', 'wp-autoplugin' ); ?></label><br />
				<input type="text" name="plugin_name" id="plugin_name" value="" style="width: 50%;" />
			</p>
			<div class="autoplugin-actions">
				<button type="button" id="edit-issue" class="button"><?php esc_html_e( '« Edit Description', 'wp-autoplugin' ); ?></button>
				<?php submit_button( esc_html__( 'Generate Extension Code', 'wp-autoplugin' ), 'primary', 'extend_code' ); ?>
			</div>
		</form>
		<div id="extend-hooks-code-message" class="autoplugin-message"></div>
	</div>
	<div class="wrap wp-autoplugin step-3-done" style="display: none;">
		<?php /* translators: %s: plugin name. */ ?>
		<h1><?php printf( esc_html__( 'Extension Plugin for: %s', 'wp-autoplugin' ), esc_html( $plugin_data['Name'] ) ); ?></h1>
		<form method="post" action="" id="extended-hooks-plugin-form">
			<p><?php esc_html_e( 'The extension plugin code has been generated. Review it before saving:', 'wp-autoplugin' ); ?></p>
			<textarea name="extended_plugin_code" id="extended_plugin_code" rows="20" cols="100"></textarea>

			<div class="autoplugin-code-warning">
				<strong><?php esc_html_e( 'Warning:', 'wp-autoplugin' ); ?></strong> <?php esc_html_e( 'AI-generated code may be unstable or insecure; use only after careful review and testing.', 'wp-autoplugin' ); ?>
			</div>

			<div class="autoplugin-actions">
				<button type="button" id="edit-plan" class="button"><?php esc_html_e( '« Edit Plan', 'wp-autoplugin' ); ?></button>
				<?php submit_button( esc_html__( 'Save Extension Plugin', 'wp-autoplugin' ), 'primary', 'extended_plugin' ); ?>
			</div>
		</form>
		<div id="extended-hooks-plugin-message" class="autoplugin-message"></div>
	</div>
	<?php $this->output_admin_footer(); ?>
</div>