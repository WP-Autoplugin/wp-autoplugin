<?php
/**
 * xAI API class. Their API is compatible with OpenAI's, so this class extends OpenAI_API.
 *
 * @package WP-Autoplugin
 * @since 1.1
 * @version 1.1
 * @link https://wp-autoplugin.com
 * @license GPL-2.0+
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class XAI_API extends OpenAI_API {

	/**
	 * Selected model.
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.x.ai/v1/chat/completions';

	/**
	 * Max tokens parameter.
	 *
	 * @var int
	 */
	protected $max_tokens = 8192;

	/**
	 * A more simple model setter.
	 *
	 * @param string $model The model.
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );
	}

	protected function get_allowed_parameters() {
		return array(
			'model',
			'temperature',
			'max_tokens',
			'messages',
		);
	}
}
