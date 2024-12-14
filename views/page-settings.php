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
	<h1><?php _e( 'WP-Autoplugin Settings', 'wp-autoplugin' ); ?></h1>
	<?php settings_errors(); ?>
	<form method="post" action="options.php">
		<?php
		settings_fields( 'wp_autoplugin_settings' );
		do_settings_sections( 'wp_autoplugin_settings' );
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e( 'OpenAI API Key', 'wp-autoplugin' ); ?></th>
				<td><input type="password" name="wp_autoplugin_openai_api_key" value="<?php echo esc_attr( get_option( 'wp_autoplugin_openai_api_key' ) ); ?>" class="large-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Anthropic API Key', 'wp-autoplugin' ); ?></th>
				<td><input type="password" name="wp_autoplugin_anthropic_api_key" value="<?php echo esc_attr( get_option( 'wp_autoplugin_anthropic_api_key' ) ); ?>" class="large-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Google Gemini API Key', 'wp-autoplugin' ); ?></th>
				<td><input type="password" name="wp_autoplugin_google_api_key" value="<?php echo esc_attr( get_option( 'wp_autoplugin_google_api_key' ) ); ?>" class="large-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'xAI API Key', 'wp-autoplugin' ); ?></th>
				<td><input type="password" name="wp_autoplugin_xai_api_key" value="<?php echo esc_attr( get_option( 'wp_autoplugin_xai_api_key' ) ); ?>" class="large-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Model', 'wp-autoplugin' ); ?></th>
				<td>
					<select name="wp_autoplugin_model">
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
					</select>
					
					<p class="description"><?php _e( '<code>chatgpt-4o-latest</code> and <code>claude-3-5-sonnet-latest</code> continuously point to the latest model version from OpenAI and Anthropic, respectively.', 'wp-autoplugin' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
