<?php
/**
 * GitHub Updater
 *
 * This is a modified version of the WP_GitHub_Updater class originally created by Joachim Kudish.
 *
 * @version 1.0.1
 * @author WP-Autoplugin
 * @link https://wp-autoplugin.com
 *
 * Based on WP_GitHub_Updater by Joachim Kudish
 * @link https://github.com/jkudish/WP-GitHub-Plugin-Updater
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GitHub Updater class.
 */
class GitHub_Updater {

	const VERSION = 1.7;

	public $config         = [];
	public $missing_config = [];
	private $github_data   = null;

	public function __construct( $config = array() ) {

		$defaults = array(
			'slug'               => plugin_basename( __FILE__ ),
			'proper_folder_name' => dirname( plugin_basename( __FILE__ ) ),
			'sslverify'          => true,
		);

		$this->config = wp_parse_args( $config, $defaults );

		// Sanitize inputs
		$this->config['slug'] = sanitize_text_field( $this->config['slug'] );
		$this->config['proper_folder_name'] = sanitize_text_field( $this->config['proper_folder_name'] );
		$this->config['github_url'] = esc_url_raw( $this->config['github_url'] );
		$this->config['raw_url'] = esc_url_raw( $this->config['raw_url'] );
		$this->config['zip_url'] = esc_url_raw( $this->config['zip_url'] );

		if ( ! $this->has_minimum_config() ) {
			$message = 'The GitHub Updater was initialized without the minimum required configuration, please check the config in your plugin. The following params are missing: ';
			$message .= implode( ',', $this->missing_config );
			_doing_it_wrong( __CLASS__, $message, self::VERSION );
			return;
		}

		$this->set_defaults();

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'api_check' ) );
		add_filter( 'plugins_api', array( $this, 'get_plugin_info' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'upgrader_post_install' ), 10, 3 );
		add_filter( 'http_request_timeout', array( $this, 'http_request_timeout' ) );
		add_filter( 'http_request_args', array( $this, 'http_request_sslverify' ), 10, 2 );
	}

	public function has_minimum_config() {
		$this->missing_config = array();

		$required_config_params = array(
			'api_url',
			'raw_url',
			'github_url',
			'zip_url',
			'requires',
			'tested',
		);

		foreach ( $required_config_params as $required_param ) {
			if ( empty( $this->config[ $required_param ] ) ) {
				$this->missing_config[] = $required_param;
			}
		}

		return ( empty( $this->missing_config ) );
	}

	/**
	 * Check wether or not the transients need to be overruled and API needs to be called for every single page load
	 *
	 * @return bool overrule or not
	 */
	public function overrule_transients() {
		return ( defined( 'WP_GITHUB_FORCE_UPDATE' ) && WP_GITHUB_FORCE_UPDATE );
	}

	public function set_defaults() {
		if ( ! isset( $this->config['new_version'] ) ) {
			$this->config['new_version'] = $this->get_new_version();
		}

		if ( ! isset( $this->config['last_updated'] ) ) {
			$this->config['last_updated'] = $this->get_date();
		}

		if ( ! isset( $this->config['description'] ) ) {
			$this->config['description'] = $this->get_description();
		}

		$plugin_data = $this->get_plugin_data();
		if ( ! isset( $this->config['plugin_name'] ) ) {
			$this->config['plugin_name'] = $plugin_data['Name'];
		}

		if ( ! isset( $this->config['version'] ) ) {
			$this->config['version'] = $plugin_data['Version'];
		}

		if ( ! isset( $this->config['author'] ) ) {
			$this->config['author'] = $plugin_data['Author'];
		}

		if ( ! isset( $this->config['homepage'] ) ) {
			$this->config['homepage'] = $plugin_data['PluginURI'];
		}
	}

	public function http_request_timeout() {
		return apply_filters( 'github_updater_http_timeout', 2 );
	}

	public function http_request_sslverify( $args, $url ) {
		if ( $this->config['zip_url'] == $url ) {
			$args['sslverify'] = $this->config['sslverify'];
		}
		return $args;
	}

	public function get_new_version() {
		$version = get_site_transient( md5( $this->config['slug'] ) . '_new_version' );

		if ( $this->overrule_transients() || ( ! isset( $version ) || ! $version || '' == $version ) ) {
			$raw_response = $this->remote_get( trailingslashit( $this->config['raw_url'] ) . basename( $this->config['slug'] ) );

			if ( is_wp_error( $raw_response ) ) {
				error_log( 'GitHub Updater Error: ' . $raw_response->get_error_message() );
				$version = false;
			}

			if ( is_array( $raw_response ) && ! empty( $raw_response['body'] ) ) {
				preg_match( '/.*Version\:\s*(.*)$/mi', $raw_response['body'], $matches );
			}

			$version = ! empty( $matches[1] ) ? $matches[1] : false;

			// Backward compatibility for README version checking
			if ( false === $version ) {
				$raw_response = $this->remote_get( trailingslashit( $this->config['raw_url'] ) . $this->config['readme'] );

				if ( ! is_wp_error( $raw_response ) && ! empty( $raw_response['body'] ) ) {
					preg_match( '#^\s*`*~Current Version\:\s*([^~]*)~#im', $raw_response['body'], $__version );
					if ( isset( $__version[1] ) && -1 == version_compare( $version, $__version[1] ) ) {
						$version = $__version[1];
					}
				}
			}

			$transient_expiration = apply_filters( 'github_updater_transient_expiration', 6 * HOUR_IN_SECONDS );
			if ( false !== $version ) {
				set_site_transient( md5( $this->config['slug'] ) . '_new_version', $version, $transient_expiration );
			}
		}

		return $version;
	}

	public function remote_get( $query ) {
		$raw_response = wp_remote_get( $query, array(
			'sslverify' => $this->config['sslverify'],
		));

		if ( is_wp_error( $raw_response ) || wp_remote_retrieve_response_code( $raw_response ) != 200 ) {
			error_log( 'GitHub Updater Error: ' . wp_remote_retrieve_response_message( $raw_response ) );
			return false;
		}

		return $raw_response;
	}

	public function get_github_data() {
		if ( isset( $this->github_data ) && ! empty( $this->github_data ) ) {
			return $this->github_data;
		}

		$github_data = get_site_transient( md5($this->config['slug']).'_github_data' );

		if ( $this->overrule_transients() || ! isset( $github_data ) || ! $github_data || '' == $github_data ) {
			$github_data = $this->remote_get( $this->config['api_url'] );

			if ( is_wp_error( $github_data ) ) {
				return false;
			}

			$github_data = json_decode( $github_data['body'] );
			set_site_transient( md5($this->config['slug']).'_github_data', $github_data, 60*60*6 );
		}

		$this->github_data = $github_data;
		return $github_data;
	}

	public function get_date() {
		$_date = $this->get_github_data();
		return ( ! empty( $_date->updated_at ) ) ? date( 'Y-m-d', strtotime( $_date->updated_at ) ) : false;
	}

	public function get_description() {
		$_description = $this->get_github_data();
		return ( ! empty( $_description->description ) ) ? $_description->description : false;
	}

	public function get_plugin_data() {
		include_once ABSPATH . '/wp-admin/includes/plugin.php';
		return get_plugin_data( WP_PLUGIN_DIR . '/' . $this->config['slug'] );
	}

	public function api_check( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$update = version_compare( $this->config['new_version'], $this->config['version'] );

		if ( 1 === $update ) {
			$response              = new \stdClass();
			$response->new_version = $this->config['new_version'];
			$response->slug        = $this->config['proper_folder_name'];
			$response->url         = $this->config['github_url'];
			$response->package     = $this->config['zip_url'];

			if ( false !== $response ) {
				$transient->response[ $this->config['slug'] ] = $response;
			}
		}

		return $transient;
	}

	public function get_plugin_info( $false, $action, $response ) {
		if ( ! isset( $response->slug ) || $response->slug != $this->config['slug'] ) {
			return false;
		}

		$response->slug          = $this->config['slug'];
		$response->plugin_name   = $this->config['plugin_name'];
		$response->version       = $this->config['new_version'];
		$response->author        = $this->config['author'];
		$response->homepage      = $this->config['homepage'];
		$response->requires      = $this->config['requires'];
		$response->tested        = $this->config['tested'];
		$response->download_link = $this->config['zip_url'];
		$response->sections      = array( 'description' => $this->config['description'] );

		return $response;
	}

	public function upgrader_post_install( $true, $hook_extra, $result ) {
		global $wp_filesystem;

		$proper_destination = WP_PLUGIN_DIR . '/' . $this->config['proper_folder_name'];
		$wp_filesystem->move( $result['destination'], $proper_destination );
		$result['destination'] = $proper_destination;
		$activate = activate_plugin( WP_PLUGIN_DIR . '/' . $this->config['slug'] );

		$fail_message = __( 'The plugin has been updated, but could not be reactivated. Please reactivate it manually.', 'wp-autoplugin' );
		$success_message = __( 'Plugin reactivated successfully.', 'wp-autoplugin' );

		echo is_wp_error( $activate ) ? esc_html( $fail_message ) : esc_html( $success_message );
		return $result;
	}
}
