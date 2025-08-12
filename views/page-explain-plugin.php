<?php
/**
 * Admin view for the Explain Autoplugin page.
 *
 * @package WP-Autoplugin
 * @since 1.3
 * @version 1.3
 * @link https://wp-autoplugin.com
 * @license GPL-2.0+
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'wp-autoplugin-explain-plugin' ) ) {
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

?>
<div class="wp-autoplugin-admin-container">
	<div class="wrap wp-autoplugin step-1-explain">
		<?php /* translators: %s: plugin name. */ ?>
		<h1><?php printf( esc_html__( 'Explain This Plugin: %s', 'wp-autoplugin' ), esc_html( $plugin_data['Name'] ) ); ?></h1>
		<form method="post" action="" id="explain-plugin-form">
			<?php wp_nonce_field( 'explain_plugin', 'explain_plugin_nonce' ); ?>
			<p><?php esc_html_e( 'Enter a specific question about this plugin or leave blank for a general explanation:', 'wp-autoplugin' ); ?></p>
			<textarea name="plugin_question" id="plugin_question" rows="5" cols="100" placeholder="<?php esc_attr_e( 'E.g., How does this plugin store its data? | Leave empty for a complete explanation.', 'wp-autoplugin' ); ?>"></textarea>
			
			<div class="explain-options">
				<h3><?php esc_html_e( 'Explanation Focus (Optional):', 'wp-autoplugin' ); ?></h3>
				<label>
					<input type="radio" name="explain_focus" value="general" checked> 
					<?php esc_html_e( 'General Overview', 'wp-autoplugin' ); ?>
				</label>
				<label>
					<input type="radio" name="explain_focus" value="security"> 
					<?php esc_html_e( 'Security Analysis', 'wp-autoplugin' ); ?>
				</label>
				<label>
					<input type="radio" name="explain_focus" value="performance"> 
					<?php esc_html_e( 'Performance Review', 'wp-autoplugin' ); ?>
				</label>
				<label>
					<input type="radio" name="explain_focus" value="code-quality"> 
					<?php esc_html_e( 'Code Quality', 'wp-autoplugin' ); ?>
				</label>
				<label>
					<input type="radio" name="explain_focus" value="usage"> 
					<?php esc_html_e( 'Usage Instructions', 'wp-autoplugin' ); ?>
				</label>
			</div>
			
			<?php submit_button( esc_html__( 'Explain Plugin', 'wp-autoplugin' ), 'primary', 'explain_plugin' ); ?>
			<input type="hidden" name="plugin_file" value="<?php echo esc_attr( $plugin_file ); ?>" id="plugin_file" />
		</form>
		<div id="explain-plugin-message" class="autoplugin-message"></div>
	</div>
	<div class="wrap wp-autoplugin step-2-explanation" style="display: none;">
		<h1><?php esc_html_e( 'Plugin Explanation', 'wp-autoplugin' ); ?></h1>
		<div class="explanation-container">
			<div class="explanation-header">
				<?php /* translators: %s: plugin name. */ ?>
				<h2><?php printf( esc_html__( 'Explanation for: %s', 'wp-autoplugin' ), esc_html( $plugin_data['Name'] ) ); ?></h2>
				<div class="question-asked">
					<strong><?php esc_html_e( 'Question/Focus:', 'wp-autoplugin' ); ?></strong>
					<span id="question-display"><?php esc_html_e( 'General explanation', 'wp-autoplugin' ); ?></span>
				</div>
			</div>
			<div class="explanation-content" id="plugin_explanation_container"></div>
			
			<div class="autoplugin-actions">
				<button type="button" id="new-question" class="button"><?php esc_html_e( 'Ask Another Question', 'wp-autoplugin' ); ?></button>
				<button type="button" id="copy-explanation" class="button button-secondary"><?php esc_html_e( 'Copy to Clipboard', 'wp-autoplugin' ); ?></button>
				<button type="button" id="download-explanation" class="button button-secondary"><?php esc_html_e( 'Download as Text', 'wp-autoplugin' ); ?></button>
			</div>
		</div>
		<div id="explanation-message" class="autoplugin-message"></div>
	</div>
	<?php $this->admin->output_admin_footer(); ?>
</div>
