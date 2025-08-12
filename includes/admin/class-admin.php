<?php
/**
 * WP-Autoplugin Admin class.
 *
 * @package WP-Autoplugin
 * @since 1.0.0
 * @version 1.4.3
 * @link https://wp-autoplugin.com
 * @license GPL-2.0+
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Admin class that brings all admin functionalities together.
 */
class Admin {

	/**
	 * The API handler instance.
	 *
	 * @var Admin\Api_Handler
	 */
	public $api_handler;

	/**
	 * Constructor: set up API, instantiate sub-classes.
	 *
	 * @return void
	 */
	public function __construct() {
		// Instantiate other admin components (each handles its own hooks).
		new Admin\Menu( $this );
		$this->api_handler = new Admin\Api_Handler();
		new Admin\Action_Links();
		new Admin\Updater();
		new Admin\Settings();
		new Admin\Scripts();
		new Admin\Ajax( $this->api_handler );
		new Admin\Bulk_Actions();
		new Admin\Notices();
	}

	/**
	 * The built-in models.
	 *
	 * @var array
	 */
	public static function get_models() {
		return include WP_AUTOPLUGIN_DIR . 'includes/config/models.php';
	}

	/**
	 * Output a simple admin footer for WP-Autoplugin pages.
	 *
	 * @return void
	 */
	public function output_admin_footer() {
		$screen = get_current_screen();
		$default_step = 'default';

		// Set default step based on page context.
		if ( $screen ) {
			switch ( $screen->id ) {
				case 'wp-autoplugin_page_wp-autoplugin-generate':
					$default_step = 'generatePlan';
					break;
				case 'admin_page_wp-autoplugin-fix':
					$default_step = 'generatePlan';
					break;
				case 'admin_page_wp-autoplugin-extend':
					$default_step = 'generatePlan';
					break;
				case 'admin_page_wp-autoplugin-extend-hooks':
					$default_step = 'generatePlan';
					break;
				case 'admin_page_wp-autoplugin-extend-theme':
					$default_step = 'generatePlan';
					break;
				case 'admin_page_wp-autoplugin-explain':
					$default_step = 'askQuestion';
					break;
			}
		}

		// Get the API handler to fetch the next task model.
		$next_task_model = $this->api_handler->get_next_task_model();
		?>
		<div id="wp-autoplugin-footer">
			<div class="footer-left">
				<span class="credits">
					<?php
					printf(
						// translators: %s: version number.
						esc_html__( 'WP-Autoplugin v%s', 'wp-autoplugin' ),
						esc_html( WP_AUTOPLUGIN_VERSION )
					);
					?>
				</span>
				<span class="separator">|</span>
				<span class="model">
					<span id="model-display">
						<?php
						$translated_model_string = wp_kses(
							// translators: %s: model name.
							__( 'Model: %s', 'wp-autoplugin' ),
							[ 'code' => [] ]
						);
						printf(
							$translated_model_string, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- It's escaped just above.
							'<code>' . esc_html( $next_task_model ) . '</code>'
						);
						?>
						<a href="#" id="change-model-link" style="text-decoration: none;"><?php esc_html_e( '(Change)', 'wp-autoplugin' ); ?></a>
					</span>
				</span>
			</div>
			<div class="footer-right">
				<span id="token-display" style="display: none; cursor: pointer;" title="<?php esc_attr_e( 'Click for token usage breakdown', 'wp-autoplugin' ); ?>">
					<span id="token-input">0</span> IN | <span id="token-output">0</span> OUT
				</span>
			</div>
		</div>

		<?php
		include WP_AUTOPLUGIN_DIR . 'views/footer-modal.php';
	}
}
