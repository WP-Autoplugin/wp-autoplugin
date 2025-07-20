<?php
/**
 * Admin view for the Generate Autoplugin page.
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
<div class="wp-autoplugin-admin-container">
	<div class="wrap wp-autoplugin step-1-generation">
		<h1><?php esc_html_e( 'Generate Plugin', 'wp-autoplugin' ); ?></h1>
		<form method="post" action="" id="generate-plan-form">
			<?php wp_nonce_field( 'generate_plan', 'generate_plan_nonce' ); ?>
			<p><?php esc_html_e( 'Enter a description of the plugin you want to generate:', 'wp-autoplugin' ); ?></p>
			<input name="plugin_description" id="plugin_description" type="text" size="100" />
			<?php submit_button( __( 'Generate Plan', 'wp-autoplugin' ), 'primary button-hero', 'generate_plan', false ); ?>
		</form>
		<div id="generate-plan-message" class="autoplugin-message"></div>
	</div>
	<div class="wrap wp-autoplugin step-2-plan" style="display: none;">
		<h1><?php esc_html_e( 'Generated Plan', 'wp-autoplugin' ); ?></h1>
		<form method="post" action="" id="generate-code-form">
			<?php wp_nonce_field( 'generate_code', 'generate_code_nonce' ); ?>
			<p><?php esc_html_e( 'Review or edit the generated plan:', 'wp-autoplugin' ); ?></p>
			<!-- The plan contains the following parts: plugin_name, design_and_architecture, detailed_feature_description, user_interface, security_considerations, testing_plan -->
			<!-- A part can contain multiple lines of text or another nested part -->
			<!-- We will display it as an accordion with each part in a separate section -->
			<div id="plugin_plan_container"></div>
			<div class="autoplugin-actions">
				<button type="button" id="edit-description" class="button"><?php esc_html_e( '&laquo; Edit Description', 'wp-autoplugin' ); ?></button>
				<?php submit_button( __( 'Generate Plugin Code', 'wp-autoplugin' ), 'primary', 'generate_code', false ); ?>
			</div>
		</form>
		<div id="generate-code-message" class="autoplugin-message"></div>
	</div>
	<div class="wrap wp-autoplugin step-3-code" style="display: none;">
		<h1><?php esc_html_e( 'Generated Plugin Code', 'wp-autoplugin' ); ?></h1>
		<form method="post" action="" id="create-plugin-form">
			<?php wp_nonce_field( 'create_plugin', 'create_plugin_nonce' ); ?>
			
			<!-- Simple plugin mode -->
			<div id="simple-plugin-content">
				<p><?php esc_html_e( 'The plugin code has been generated successfully. You can review and edit the plugin file before activating it:', 'wp-autoplugin' ); ?></p>
				<textarea name="plugin_code" id="plugin_code" rows="20" cols="100"></textarea>
			</div>
			
			<!-- Complex plugin mode -->
			<div id="complex-plugin-content" style="display: none;">
				<p><?php esc_html_e( 'You can review and edit the generated code before installing:', 'wp-autoplugin' ); ?></p>
				
				<div class="generation-progress" style="display: none;">
					<div class="progress-bar-container">
						<div class="progress-bar" id="file-generation-progress"></div>
					</div>
					<span class="progress-text" id="progress-text"><?php esc_html_e( 'Generating files...', 'wp-autoplugin' ); ?></span>
				</div>

				<div class="token-usage-display" id="token-usage-display">
					<span class="token-info"><?php esc_html_e( 'Tokens used:', 'wp-autoplugin' ); ?> <span id="token-count">0 input + 0 output</span></span>
				</div>
				
				<div class="code-review-section" id="code-review-section" style="display: none;">
					<div class="review-progress">
						<div class="progress-text" id="review-progress-text"><?php esc_html_e( 'AI is reviewing the complete codebase...', 'wp-autoplugin' ); ?></div>
					</div>
					
					<div class="review-results" id="review-results" style="display: none;">
						<h4><?php esc_html_e( 'Code Review Results', 'wp-autoplugin' ); ?></h4>
						<div class="review-summary" id="review-summary"></div>
						
						<div class="review-suggestions" id="review-suggestions" style="display: none;">
							<h5><?php esc_html_e( 'Suggested Improvements', 'wp-autoplugin' ); ?></h5>
							<div class="suggestions-list" id="suggestions-list"></div>
							
							<div class="review-actions" style="margin-top: 15px;">
								<button type="button" id="apply-suggestions" class="button button-primary">
									<?php esc_html_e( 'Apply Suggestions', 'wp-autoplugin' ); ?>
								</button>
								<button type="button" id="skip-review" class="button">
									<?php esc_html_e( 'Skip Review', 'wp-autoplugin' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
				
				<div class="generated-files-container">
					<div class="files-tabs" id="files-tabs">
						<!-- File tabs will be populated by JavaScript -->
					</div>
					
					<div class="file-content" id="file-content">
						<!-- File editors will be populated by JavaScript -->
					</div>
				</div>
			</div>
			
			<div class="autoplugin-code-warning">
				<strong><?php esc_html_e( 'Warning:', 'wp-autoplugin' ); ?></strong> <?php esc_html_e( 'AI-generated code may be unstable or insecure; use only after careful review and testing.', 'wp-autoplugin' ); ?>
			</div>

			<div class="autoplugin-actions">
				<button type="button" id="edit-plan" class="button"><?php esc_html_e( '&laquo; Edit Plan', 'wp-autoplugin' ); ?></button>
				<?php submit_button( __( 'Install Plugin', 'wp-autoplugin' ), 'primary', 'create_plugin', false ); ?>
			</div>
		</form>
		<div id="create-plugin-message" class="autoplugin-message"></div>
	</div>
<?php $this->output_admin_footer(); ?>
</div>