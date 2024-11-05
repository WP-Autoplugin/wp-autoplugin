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
				<th scope="row"><?php _e( 'Model', 'wp-autoplugin' ); ?></th>
				<td>
					<select name="wp_autoplugin_model">
						<optgroup label="OpenAI">
							<option value="chatgpt-4o-latest" <?php selected( get_option( 'wp_autoplugin_model' ), 'chatgpt-4o-latest' ); ?>>ChatGPT-4o-latest</option>
							<option value="gpt-4o" <?php selected( get_option( 'wp_autoplugin_model' ), 'gpt-4o' ); ?>>GPT-4o</option>
							<option value="gpt-4o-mini" <?php selected( get_option( 'wp_autoplugin_model' ), 'gpt-4o-mini' ); ?>>GPT-4o mini</option>
							<option value="gpt-4-turbo" <?php selected( get_option( 'wp_autoplugin_model' ), 'gpt-4-turbo' ); ?>>GPT-4 Turbo</option>
							<option value="gpt-3.5-turbo" <?php selected( get_option( 'wp_autoplugin_model' ), 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo</option>
						</optgroup>
						<optgroup label="Anthropic">
							<option value="claude-3-5-sonnet-latest" <?php selected( get_option( 'wp_autoplugin_model' ), 'claude-3-5-sonnet-latest' ); ?>>Claude 3.5 Sonnet-latest</option>
							<option value="claude-3-5-sonnet-20240620" <?php selected( get_option( 'wp_autoplugin_model' ), 'claude-3-5-sonnet-20240620' ); ?>>Claude 3.5 Sonnet-20240620</option>
							<option value="claude-3-opus-20240229" <?php selected( get_option( 'wp_autoplugin_model' ), 'claude-3-opus-20240229' ); ?>>Claude 3 Opus</option>
							<option value="claude-3-sonnet-20240229" <?php selected( get_option( 'wp_autoplugin_model' ), 'claude-3-sonnet-20240229' ); ?>>Claude 3 Sonnet</option>
							<option value="claude-3-haiku-20240307" <?php selected( get_option( 'wp_autoplugin_model' ), 'claude-3-haiku-20240307' ); ?>>Claude 3 Haiku</option>
						</optgroup>
						<optgroup label="Google Gemini">
							<option value="gemini-1.5-flash" <?php selected( get_option( 'wp_autoplugin_model' ), 'gemini-1.5-flash' ); ?>>Gemini 1.5 Flash</option>
							<option value="gemini-1.5-pro" <?php selected( get_option( 'wp_autoplugin_model' ), 'gemini-1.5-pro' ); ?>>Gemini 1.5 Pro</option>
							<option value="gemini-1.0-pro" <?php selected( get_option( 'wp_autoplugin_model' ), 'gemini-1.0-pro' ); ?>>Gemini 1.0 Pro</option>
						</optgroup>
					</select>
					
					<p class="description"><?php _e( '<code>chatgpt-4o-latest</code> and <code>claude-3-5-sonnet-latest</code> continuously point to the latest model version from OpenAI and Anthropic, respectively.', 'wp-autoplugin' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
