<?php
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
				<th scope="row"><?php _e( 'Model', 'wp-autoplugin' ); ?></th>
				<td>
					<select name="wp_autoplugin_model">
						<option value="gpt-4o" <?php selected( get_option( 'wp_autoplugin_model' ), 'gpt-4o' ); ?>>GPT-4o</option>
						<option value="gpt-4-turbo" <?php selected( get_option( 'wp_autoplugin_model' ), 'gpt-4-turbo' ); ?>>GPT-4 Turbo</option>
						<option value="gpt-3.5-turbo" <?php selected( get_option( 'wp_autoplugin_model' ), 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo</option>
						<option value="claude-3-5-sonnet-20240620" <?php selected( get_option( 'wp_autoplugin_model' ), 'claude-3-5-sonnet-20240620' ); ?>>Claude 3.5 Sonnet</option>
						<option value="claude-3-opus-20240229" <?php selected( get_option( 'wp_autoplugin_model' ), 'claude-3-opus-20240229' ); ?>>Claude 3 Opus</option>
						<option value="claude-3-sonnet-20240229" <?php selected( get_option( 'wp_autoplugin_model' ), 'claude-3-sonnet-20240229' ); ?>>Claude 3 Sonnet</option>
						<option value="claude-3-haiku-20240307" <?php selected( get_option( 'wp_autoplugin_model' ), 'claude-3-haiku-20240307' ); ?>>Claude 3 Haiku</option>
					</select>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
