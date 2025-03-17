<?php
/**
 * Main API class.
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

/**
 * API class.
 */
class API {

	/**
	 * API key.
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * Set the API key.
	 *
	 * @param string $api_key The API key.
	 */
	public function set_api_key( $api_key ) {
		$this->api_key = sanitize_text_field( $api_key );
	}
}
