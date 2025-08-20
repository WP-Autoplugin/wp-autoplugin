<?php
/**
 * WP-Autoplugin AJAX Model class.
 *
 * @package WP-Autoplugin
 */

namespace WP_Autoplugin\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles AJAX requests for models.
 */
class Model {
	/**
	 * The Admin object for accessing specialized model APIs.
	 *
	 * @var \WP_Autoplugin\Admin\Admin
	 */
	private $admin;

	/**
	 * Constructor.
	 *
	 * @param \WP_Autoplugin\Admin\Admin $admin The admin instance.
	 */
	public function __construct( $admin ) {
		$this->admin = $admin;
	}

	/**
	 * AJAX handler for adding a custom model.
	 *
	 * @return void
	 */
	public function add_model() {
		if ( ! check_ajax_referer( 'wp_autoplugin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', 'wp-autoplugin' ) ] );
		}

		$model = isset( $_POST['model'] ) && is_array( $_POST['model'] ) ? wp_unslash( $_POST['model'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitization is done later, see below.
		if ( ! $model || ! isset( $model['name'] ) || ! isset( $model['url'] ) || ! isset( $model['apiKey'] ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid model data.', 'wp-autoplugin' ) ] );
		}

		$new_model = [
			'name'           => sanitize_text_field( $model['name'] ),
			'url'            => esc_url_raw( $model['url'] ),
			'modelParameter' => isset( $model['modelParameter'] ) ? sanitize_text_field( $model['modelParameter'] ) : '',
			'apiKey'         => sanitize_text_field( $model['apiKey'] ),
			'headers'        => ( isset( $model['headers'] ) && is_array( $model['headers'] ) )
				? array_map( 'sanitize_text_field', $model['headers'] )
				: [],
		];

		$models = get_option( 'wp_autoplugin_custom_models', [] );
		if ( ! is_array( $models ) ) {
			$models = [];
		}

		$models[] = $new_model;
		update_option( 'wp_autoplugin_custom_models', $models );

		wp_send_json_success(
			[
				'models'  => $models,
				'message' => esc_html__( 'Model added successfully.', 'wp-autoplugin' ),
			]
		);
	}

	/**
	 * AJAX handler for removing a custom model.
	 *
	 * @return void
	 */
	public function remove_model() {
		if ( ! check_ajax_referer( 'wp_autoplugin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', 'wp-autoplugin' ) ] );
		}

		$models = get_option( 'wp_autoplugin_custom_models', [] );
		if ( ! is_array( $models ) ) {
			$models = [];
		}

		$index = isset( $_POST['index'] ) ? intval( wp_unslash( $_POST['index'] ) ) : null;
		if ( ! is_int( $index ) || $index >= count( $models ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid model index.', 'wp-autoplugin' ) ] );
		}

		if ( isset( $models[ $index ] ) ) {
			unset( $models[ $index ] );
		}

		update_option( 'wp_autoplugin_custom_models', $models );

		wp_send_json_success(
			[
				'models'  => $models,
				'message' => esc_html__( 'Model removed successfully.', 'wp-autoplugin' ),
			]
		);
	}

	/**
	 * AJAX handler for updating a custom model.
	 *
	 * @return void
	 */
	public function change_model() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You are not allowed to access this page.', 'wp-autoplugin' ) ] );
		}

		if ( ! check_ajax_referer( 'wp_autoplugin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', 'wp-autoplugin' ) ] );
		}

		$model = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
		if ( empty( $model ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'No model specified.', 'wp-autoplugin' ) ] );
		}

		// Validate the model exists in one of the providers or custom models.
		$valid_model = false;

		// Check built-in models.
		foreach ( \WP_Autoplugin\Admin\Admin::get_models() as $provider => $models ) {
			if ( array_key_exists( $model, $models ) ) {
				$valid_model = true;
				break;
			}
		}

		// Check custom models.
		if ( ! $valid_model ) {
			$custom_models = get_option( 'wp_autoplugin_custom_models', [] );
			foreach ( $custom_models as $custom_model ) {
				if ( $custom_model['name'] === $model ) {
					$valid_model = true;
					break;
				}
			}
		}

		if ( ! $valid_model ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid model specified.', 'wp-autoplugin' ) ] );
		}

		// Update the model setting.
		update_option( 'wp_autoplugin_model', $model );

		wp_send_json_success( [ 'message' => esc_html__( 'Model changed successfully.', 'wp-autoplugin' ) ] );
	}

	/**
	 * AJAX handler for changing models used in different contexts.
	 *
	 * @return void
	 */
	public function change_models() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'You are not allowed to access this page.', 'wp-autoplugin' ) ] );
		}

		if ( ! check_ajax_referer( 'wp_autoplugin_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', 'wp-autoplugin' ) ] );
		}

		$default_model  = isset( $_POST['default_model'] ) ? sanitize_text_field( wp_unslash( $_POST['default_model'] ) ) : '';
		$planner_model  = isset( $_POST['planner_model'] ) ? sanitize_text_field( wp_unslash( $_POST['planner_model'] ) ) : '';
		$coder_model    = isset( $_POST['coder_model'] ) ? sanitize_text_field( wp_unslash( $_POST['coder_model'] ) ) : '';
		$reviewer_model = isset( $_POST['reviewer_model'] ) ? sanitize_text_field( wp_unslash( $_POST['reviewer_model'] ) ) : '';

		if ( empty( $default_model ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Default model is required.', 'wp-autoplugin' ) ] );
		}

		// Validate all non-empty models.
		$models_to_validate = array_filter( [ $default_model, $planner_model, $coder_model, $reviewer_model ] );
		foreach ( $models_to_validate as $model ) {
			$valid_model = false;

			// Check built-in models.
			foreach ( \WP_Autoplugin\Admin\Admin::get_models() as $provider => $models ) {
				if ( array_key_exists( $model, $models ) ) {
					$valid_model = true;
					break;
				}
			}

			// Check custom models.
			if ( ! $valid_model ) {
				$custom_models = get_option( 'wp_autoplugin_custom_models', [] );
				foreach ( $custom_models as $custom_model ) {
					if ( $custom_model['name'] === $model ) {
						$valid_model = true;
						break;
					}
				}
			}

			if ( ! $valid_model ) {
				// Translators: %s: model name.
				wp_send_json_error( [ 'message' => sprintf( esc_html__( 'Invalid model specified: %s', 'wp-autoplugin' ), $model ) ] );
			}
		}

		// Update all model settings.
		update_option( 'wp_autoplugin_model', $default_model );
		update_option( 'wp_autoplugin_planner_model', $planner_model );
		update_option( 'wp_autoplugin_coder_model', $coder_model );
		update_option( 'wp_autoplugin_reviewer_model', $reviewer_model );

		wp_send_json_success( [ 'message' => esc_html__( 'Models updated successfully.', 'wp-autoplugin' ) ] );
	}
}
