<?php
/**
 * GitHub Updater
 *
 * This is a modified version of the WP_GitHub_Updater class originally created by Joachim Kudish.
 *
 * @version 1.8
 * @author WP-Autoplugin
 * @link https://wp-autoplugin.com
 * @package WP-Autoplugin
 *
 * Based on WP_GitHub_Updater by Joachim Kudish
 * @link https://github.com/jkudish/WP-GitHub-Plugin-Updater
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

namespace WP_Autoplugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GitHub Updater class.
 */
class GitHub_Updater {

	/**
	 * Updater version.
	 *
	 * @var string
	 */
	const VERSION = '1.8';

	/**
	 * Configuration parameters.
	 *
	 * @var array
	 */
	public $config = [];

	/**
	 * Missing configuration.
	 *
	 * @var array
	 */
	public $missing_config = [];

	/**
	 * GitHub data.
	 *
	 * @var object|null
	 */
	private $github_data = null;

	/**
	 * Set up the updater.
	 *
	 * @param array $config Configuration parameters.
	 */
	public function __construct( $config = [] ) {

		$defaults = [
			'slug'               => plugin_basename( __FILE__ ),
			'proper_folder_name' => dirname( plugin_basename( __FILE__ ) ),
			'sslverify'          => true,
		];

		$this->config = wp_parse_args( $config, $defaults );

		// Sanitize inputs.
		$this->config['slug']               = sanitize_text_field( $this->config['slug'] );
		$this->config['proper_folder_name'] = sanitize_text_field( $this->config['proper_folder_name'] );
		$this->config['github_url']         = isset( $this->config['github_url'] ) ? esc_url_raw( $this->config['github_url'] ) : '';
		$this->config['raw_url']            = isset( $this->config['raw_url'] ) ? esc_url_raw( $this->config['raw_url'] ) : '';
		$this->config['zip_url']            = isset( $this->config['zip_url'] ) ? esc_url_raw( $this->config['zip_url'] ) : '';

		if ( ! $this->has_minimum_config() ) {
			$message  = 'The GitHub Updater was initialized without the minimum required configuration, please check the config in your plugin. The following params are missing: ';
			$message .= implode( ',', $this->missing_config );
			_doing_it_wrong( __CLASS__, esc_html( $message ), esc_html( self::VERSION ) );
			return;
		}

		$this->set_defaults();

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'api_check' ] );
		add_filter( 'plugins_api', [ $this, 'get_plugin_info' ], 10, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'upgrader_post_install' ], 10, 3 );
		add_filter( 'http_request_timeout', [ $this, 'http_request_timeout' ] );
		add_filter( 'http_request_args', [ $this, 'http_request_sslverify' ], 10, 2 );

		// Optionally override the plugin details modal with a simple message.
		if ( ! empty( $this->config['override_modal_with_message'] ) ) {
			add_action( 'install_plugins_pre_plugin-information', [ $this, 'pre_plugin_information' ] );
		}
	}

	/**
	 * Check if the required configuration parameters are set.
	 *
	 * @return bool
	 */
	public function has_minimum_config() {
		$this->missing_config = [];

		$required_config_params = [
			'api_url',
			'raw_url',
			'github_url',
			'zip_url',
			'requires',
			'tested',
		];

		foreach ( $required_config_params as $required_param ) {
			if ( empty( $this->config[ $required_param ] ) ) {
				$this->missing_config[] = $required_param;
			}
		}

		return ( empty( $this->missing_config ) );
	}

	/**
	 * Whether to overrule transients and call API on every page load.
	 *
	 * @return bool
	 */
	public function overrule_transients() {
		return ( defined( 'WP_GITHUB_FORCE_UPDATE' ) && WP_GITHUB_FORCE_UPDATE );
	}

	/**
	 * Set default values for the configuration parameters.
	 *
	 * @return void
	 */
	public function set_defaults() {
		if ( ! isset( $this->config['new_version'] ) ) {
			$this->config['new_version'] = $this->get_new_version();
		}

		if ( ! isset( $this->config['last_updated'] ) ) {
			$this->config['last_updated'] = $this->get_date();
		}

		// Keep a short text fallback from GitHub API for description.
		if ( ! isset( $this->config['description'] ) ) {
			$this->config['description'] = $this->get_description_text_from_github();
		}

		// Default readme file to probe when fetching sections.
		if ( empty( $this->config['readme'] ) ) {
			$this->config['readme'] = 'readme.txt';
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

	/**
	 * Get the HTTP request timeout.
	 *
	 * @return int
	 */
	public function http_request_timeout() {
		return (int) apply_filters( 'github_updater_http_timeout', 5 );
	}

	/**
	 * Set the SSL verification for the HTTP request.
	 *
	 * @param array  $args HTTP request arguments.
	 * @param string $url  URL.
	 *
	 * @return array
	 */
	public function http_request_sslverify( $args, $url ) {
		if ( isset( $this->config['zip_url'] ) && $this->config['zip_url'] === $url ) {
			$args['sslverify'] = (bool) $this->config['sslverify'];
		}
		return $args;
	}

	/**
	 * Get the new version number for the plugin.
	 *
	 * @return string|bool
	 */
	public function get_new_version() {
		$version = get_site_transient( md5( $this->config['slug'] ) . '_new_version' );

		if ( $this->overrule_transients() || ( ! isset( $version ) || ! $version || '' === $version ) ) {
			$raw_response = $this->remote_get( trailingslashit( $this->config['raw_url'] ) . basename( $this->config['slug'] ) );

			if ( is_wp_error( $raw_response ) ) {
				$version = false;
			}

			if ( is_array( $raw_response ) && ! empty( $raw_response['body'] ) ) {
				preg_match( '/.*Version\:\s*(.*)$/mi', $raw_response['body'], $matches );
			}

			$version = ! empty( $matches[1] ) ? trim( $matches[1] ) : false;

			// Backward compatibility for README version checking.
			if ( false === $version ) {
				$raw_response = $this->remote_get( trailingslashit( $this->config['raw_url'] ) . ltrim( $this->config['readme'], '/' ) );

				if ( ! is_wp_error( $raw_response ) && ! empty( $raw_response['body'] ) ) {
					preg_match( '#^\s*`*~Current Version\:\s*([^~]*)~#im', $raw_response['body'], $__version );
					if ( isset( $__version[1] ) && -1 === version_compare( $version, $__version[1] ) ) {
						$version = trim( $__version[1] );
					}
				}
			}

			$transient_expiration = (int) apply_filters( 'github_updater_transient_expiration', 6 * HOUR_IN_SECONDS );
			if ( false !== $version ) {
				set_site_transient( md5( $this->config['slug'] ) . '_new_version', $version, $transient_expiration );
			}
		}

		return $version;
	}

	/**
	 * Perform a remote GET request.
	 *
	 * @param string $query URL to query.
	 *
	 * @return bool|array
	 */
	public function remote_get( $query ) {
		$raw_response = wp_remote_get(
			$query,
			[
				'sslverify' => (bool) $this->config['sslverify'],
				'timeout'   => $this->http_request_timeout(),
			]
		);

		if ( is_wp_error( $raw_response ) || (int) wp_remote_retrieve_response_code( $raw_response ) !== 200 ) {
			return false;
		}

		return $raw_response;
	}

	/**
	 * Fetch the GitHub repo data via API.
	 *
	 * @return object|false
	 */
	public function get_github_data() {
		if ( isset( $this->github_data ) && ! empty( $this->github_data ) ) {
			return $this->github_data;
		}

		$github_data = get_site_transient( md5( $this->config['slug'] ) . '_github_data' );

		if ( $this->overrule_transients() || ! isset( $github_data ) || ! $github_data || '' === $github_data ) {
			$github_data = $this->remote_get( $this->config['api_url'] );

			if ( is_wp_error( $github_data ) || false === $github_data ) {
				return false;
			}

			$github_data = json_decode( $github_data['body'] );
			set_site_transient( md5( $this->config['slug'] ) . '_github_data', $github_data, 6 * HOUR_IN_SECONDS );
		}

		$this->github_data = $github_data;
		return $github_data;
	}

	/**
	 * Get the date the plugin was last updated.
	 *
	 * @return string|bool
	 */
	public function get_date() {
		$_date = $this->get_github_data();
		return ( ! empty( $_date->updated_at ) ) ? gmdate( 'Y-m-d', strtotime( $_date->updated_at ) ) : false;
	}

	/**
	 * Fallback short description from GitHub API description (plain text).
	 *
	 * @return string|bool
	 */
	public function get_description_text_from_github() {
		$_description = $this->get_github_data();
		return ( ! empty( $_description->description ) ) ? (string) $_description->description : false;
	}

	/**
	 * Get the plugin data.
	 *
	 * @return array
	 */
	public function get_plugin_data() {
		include_once ABSPATH . '/wp-admin/includes/plugin.php';
		return get_plugin_data( WP_PLUGIN_DIR . '/' . $this->config['slug'] );
	}

	/**
	 * Check for updates and inject response for this plugin.
	 *
	 * @param object $transient The plugin data transient.
	 *
	 * @return object
	 */
	public function api_check( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$update = version_compare( $this->config['new_version'], $this->config['version'] );

		if ( 1 === (int) $update ) {
			$response              = new \stdClass();
			$response->new_version = $this->config['new_version'];
			$response->id          = $this->config['slug'];
			$response->slug        = $this->config['proper_folder_name']; // folder slug for modal
			$response->plugin      = $this->config['slug']; // plugin basename (folder/main.php)
			$response->url         = $this->config['github_url'];
			$response->package     = $this->config['zip_url'];

			$transient->response[ $this->config['slug'] ] = $response;
		}

		return $transient;
	}

	/**
	 * Provide data for the plugin details modal (native ThickBox) via plugins_api.
	 *
	 * @param mixed  $result  Default false or data from other filters.
	 * @param string $action  Action name.
	 * @param object $args    Request args (expects ->slug = folder slug).
	 *
	 * @return mixed
	 */
	public function get_plugin_info( $result, $action, $args ) { // phpcs:ignore
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || $args->slug !== $this->config['proper_folder_name'] ) {
			return $result; // Not our plugin.
		}

		$resp = new \stdClass();

		$resp->name          = $this->config['plugin_name'];
		$resp->slug          = $this->config['proper_folder_name']; // folder slug
		$resp->version       = $this->config['new_version'];
		$resp->author        = $this->config['author'];
		$resp->homepage      = $this->config['homepage'];
		$resp->requires      = $this->config['requires'];
		$resp->tested        = $this->config['tested'];
		$resp->download_link = $this->config['zip_url'];

		$sections = [
			'description' => $this->get_description_html_from_readme_or_fallback(),
		];

		$changelog_html = $this->get_changelog_html();
		if ( '' !== $changelog_html ) {
			$sections['changelog'] = $changelog_html;
		}

		$resp->sections = $sections;

		return $resp;
	}

	/**
	 * Optional quick override: show a simple message instead of native modal.
	 * Requires $this->config['override_modal_with_message'] = true.
	 */
	public function pre_plugin_information() {
		$plugin = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : '';

		if ( $plugin !== $this->config['proper_folder_name'] ) {
			return; // Not our plugin; let WP proceed.
		}

		$github = ! empty( $this->config['github_url'] ) ? esc_url( $this->config['github_url'] ) : '#';

		echo '<div class="wrap">';
		echo '<h2>' . esc_html__( 'Plugin Information', 'wp-autoplugin' ) . '</h2>';
		echo '<p>' . esc_html__( 'See the GitHub page for details.', 'wp-autoplugin' ) . '</p>';
		echo '<p><a href="' . $github . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'GitHub Repository', 'wp-autoplugin' ) . '</a></p>';
		echo '</div>';

		exit; // Stop Core from loading the default (wp.org) screen.
	}

	/**
	 * Fetch raw README contents from GitHub (tries configured file, then common fallbacks).
	 *
	 * @return string Empty string on failure.
	 */
	private function get_readme_body() {
		$cache_key = md5( $this->config['slug'] ) . '_readme_body';
		$cached    = get_site_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$candidates = [];
		// Respect configured file first.
		if ( ! empty( $this->config['readme'] ) ) {
			$candidates[] = ltrim( $this->config['readme'], '/' );
		}
		// Fallbacks (common names / case variants).
		$candidates = array_unique(
			array_merge(
				$candidates,
				[
					'readme.txt',
					'README.txt',
					'README.md',
					'readme.md',
				]
			)
		);

		$body = '';
		foreach ( $candidates as $file ) {
			$resp = $this->remote_get( trailingslashit( $this->config['raw_url'] ) . $file );
			if ( is_array( $resp ) && ! empty( $resp['body'] ) ) {
				$body = (string) $resp['body'];
				break;
			}
		}

		set_site_transient( $cache_key, $body, 6 * HOUR_IN_SECONDS );
		return $body;
	}

	/**
	 * Extract Description section as HTML from README (supports WP readme.txt & Markdown).
	 * Falls back to GitHub API short description when no README section is found.
	 *
	 * @return string HTML
	 */
	private function get_description_html_from_readme_or_fallback() {
		$readme = $this->get_readme_body();
		if ( '' !== $readme ) {
			// Try WordPress readme.txt: == Description == ... until next == Heading ==
			if ( preg_match( '/^==\s*Description\s*==\s*(.+?)(?=^\s*==\s*[^=]+==|\z)/ims', $readme, $m ) ) {
				return $this->simple_markdownish_to_html( trim( $m[1] ) );
			}

			// Try Markdown: ## Description ... until next ##
			if ( preg_match( '/^##+\s*Description\s*$([\s\S]*?)(?=^\s*##+\s+\S|\z)/im', $readme, $m ) ) {
				return $this->simple_markdownish_to_html( trim( $m[1] ) );
			}

			// Fallback for Markdown: content after H1 (# Title) until next H2 (## ...)
			if ( preg_match( '/^#\s+.*\n([\s\S]*?)(?=^\s*##\s+\S|\z)/m', $readme, $m ) ) {
				$chunk = trim( $m[1] );
				if ( '' !== $chunk ) {
					return $this->simple_markdownish_to_html( $chunk );
				}
			}

			// Or: everything until the first major heading (## Installation/Usage/Changelog)
			if ( preg_match( '/^([\s\S]*?)(?=^\s*##\s+(?:Installation|Usage|Changelog|FAQ|Screenshots)\b|\z)/im', $readme, $m ) ) {
				$chunk = trim( $m[1] );
				if ( '' !== $chunk ) {
					return $this->simple_markdownish_to_html( $chunk );
				}
			}
		}

		// Last resort: short description from GitHub API, escaped and wrapped.
		$fallback = $this->get_description_text_from_github();
		$fallback = $fallback ? wpautop( esc_html( $fallback ) ) : '';
		return $fallback;
	}

	/**
	 * Extract a "Changelog" section from README and return sanitized HTML.
	 * Supports WP readme.txt and Markdown formats.
	 *
	 * @return string
	 */
	private function get_changelog_html() {
		$readme = $this->get_readme_body();
		if ( '' === $readme ) {
			return '';
		}

		$changelog = '';

		// 1) WordPress readme.txt style: "== Changelog ==" ... until next heading
		if ( preg_match( '/^==\s*Changelog\s*==\s*(.+?)(?=^\s*==\s*[^=]+==|\z)/ims', $readme, $m ) ) {
			$changelog = trim( $m[1] );
		}

		// 2) Markdown style: "## Changelog" ... until next H2
		if ( '' === $changelog && preg_match( '/^##+\s*Changelog\s*$([\s\S]*?)(?=^\s*##+\s+\S|\z)/im', $readme, $m ) ) {
			$changelog = trim( $m[1] );
		}

		if ( '' === $changelog ) {
			return '';
		}

		return $this->simple_markdownish_to_html( $changelog );
	}

	/**
	 * Very light markdown-ish -> HTML converter with sanitization.
	 * - Preserves paragraphs
	 * - Turns lines starting with `-` or `*` into <ul><li>
	 * - Converts "= Heading =" lines to HTML headings
	 * - Converts **bold** text to <strong> tags
	 * - Converts Markdown links [text](url) to HTML links
	 *
	 * @param string $text
	 * @return string HTML
	 */
	private function simple_markdownish_to_html( $text ) {
		$text = preg_replace( "/\r\n|\r/", "\n", (string) $text );

		$lines = preg_split( '/\n/', $text );
		$buf   = [];
		$in_ul = false;
		foreach ( $lines as $line ) {
			// Handle heading lines: = Heading =, == Heading ==, etc.
			if ( preg_match( '/^(\s*)(=+)\s*(.+?)\s*\2\s*$/', $line, $hm ) ) {
				if ( $in_ul ) {
					$buf[] = '</ul>';
					$in_ul = false;
				}
				$level        = min( strlen( $hm[2] ), 6 ); // H1-H6 max
				$heading_text = $this->process_inline_markdown( trim( $hm[3] ) );
				$buf[]        = '<h' . $level . '>' . $heading_text . '</h' . $level . '>';
			} elseif ( preg_match( '/^\s*[-*]\s+(.+)$/', $line, $mm ) ) {
				// Handle list items
				if ( ! $in_ul ) {
					$buf[] = '<ul>';
					$in_ul = true;
				}
				$list_content = $this->process_inline_markdown( rtrim( $mm[1] ) );
				$buf[]        = '<li>' . $list_content . '</li>';
			} else {
				if ( $in_ul ) {
					$buf[] = '</ul>';
					$in_ul = false;
				}
				$trimmed = trim( $line );
				if ( '' !== $trimmed ) {
					$paragraph_content = $this->process_inline_markdown( $trimmed );
					$buf[]             = '<p>' . $paragraph_content . '</p>';
				}
			}
		}
		if ( $in_ul ) {
			$buf[] = '</ul>';
		}

		$html = implode( "\n", $buf );
		return wp_kses_post( $html );
	}

	/**
	 * Process inline Markdown elements like **bold** and [text](url) links.
	 *
	 * @param string $text
	 * @return string HTML with inline elements processed
	 */
	private function process_inline_markdown( $text ) {
		// Escape any HTML in the text first
		$text = esc_html( $text );

		// Convert **bold** to <strong>
		$text = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text );

		// Convert [text](url) to <a> tags
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\(([^)]+)\)/',
			function ( $matches ) {
				$link_text = $matches[1]; // Already escaped above
				$url       = esc_url( html_entity_decode( $matches[2] ) );
				return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $link_text . '</a>';
			},
			$text
		);

		// Un-escape our processed HTML tags
		$text = str_replace(
			[ '&lt;strong&gt;', '&lt;/strong&gt;', '&lt;a href=&quot;', '&quot; target=&quot;_blank&quot; rel=&quot;noopener noreferrer&quot;&gt;', '&lt;/a&gt;' ],
			[ '<strong>', '</strong>', '<a href="', '" target="_blank" rel="noopener noreferrer">', '</a>' ],
			$text
		);

		return $text;
	}

	/**
	 * Post-installation hook.
	 *
	 * @param bool   $true       True.
	 * @param array  $hook_extra Hook extra.
	 * @param object $result     Result.
	 *
	 * @return object
	 */
	public function upgrader_post_install( $true, $hook_extra, $result ) { // phpcs:ignore
		global $wp_filesystem;

		$proper_destination = WP_PLUGIN_DIR . '/' . $this->config['proper_folder_name'];
		$wp_filesystem->move( $result['destination'], $proper_destination );
		$result['destination'] = $proper_destination;
		$activate              = activate_plugin( WP_PLUGIN_DIR . '/' . $this->config['slug'] );

		$fail_message    = __( 'The plugin has been updated, but could not be reactivated. Please reactivate it manually.', 'wp-autoplugin' );
		$success_message = __( 'Plugin reactivated successfully.', 'wp-autoplugin' );

		echo is_wp_error( $activate ) ? esc_html( $fail_message ) : esc_html( $success_message );
		return $result;
	}
}
