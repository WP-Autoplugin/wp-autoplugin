<?php
/**
 * Plugin Validator class.
 *
 * Handles validation and safety checks for plugin generation.
 *
 * @package WP-Autoplugin
 * @since 1.5.0
 * @version 1.0.0
 * @link https://wp-autoplugin.com
 * @license GPL-2.0+
 * @license https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace WP_Autoplugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Validator class.
 */
class Plugin_Validator {

	/**
	 * Dangerous functions that should be avoided in generated plugins.
	 *
	 * @var array
	 */
	private $dangerous_functions = [
		'eval',
		'exec',
		'system',
		'shell_exec',
		'passthru',
		'proc_open',
		'popen',
		'assert',
		'create_function',
		'include_once',
		'require_once',
		'file_get_contents',
		'file_put_contents',
		'fopen',
		'fwrite',
		'unlink',
		'rmdir',
		'chmod',
		'chown',
	];

	/**
	 * Required WordPress security practices.
	 *
	 * @var array
	 */
	private $security_checks = [
		'nonce_verification' => '/wp_verify_nonce|check_admin_referer|check_ajax_referer/',
		'capability_checks'  => '/current_user_can|user_can/',
		'data_sanitization'  => '/sanitize_\w+|esc_\w+|wp_kses/',
		'sql_preparation'    => '/\$wpdb->prepare/',
	];

	/**
	 * WordPress coding standards patterns.
	 *
	 * @var array
	 */
	private $coding_standards = [
		'proper_hooks'     => '/(add|remove)_(action|filter)\s*\(/i',
		'text_domain'      => '/__\s*\(|_e\s*\(|_n\s*\(|_x\s*\(/',
		'prefix_functions' => '/function\s+[a-z_]+_[a-z_]+\s*\(/i',
		'wp_enqueue'       => '/wp_enqueue_(script|style)\s*\(/i',
	];

	/**
	 * Validate plugin requirements before generation.
	 *
	 * @param string $requirements The plugin requirements from user.
	 * @return array Validation result with status and messages.
	 */
	public function validate_requirements( $requirements ) {
		$result = [
			'is_valid' => true,
			'warnings' => [],
			'errors'   => [],
			'suggestions' => [],
		];

		// Check for empty requirements.
		if ( empty( trim( $requirements ) ) ) {
			$result['is_valid'] = false;
			$result['errors'][] = __( 'Plugin requirements cannot be empty.', 'wp-autoplugin' );
			return $result;
		}

		// Check for minimum length.
		if ( strlen( $requirements ) < 20 ) {
			$result['warnings'][] = __( 'Requirements seem too short. Please provide more detailed specifications for better results.', 'wp-autoplugin' );
		}

		// Check for dangerous keywords in requirements.
		$dangerous_keywords = [
			'delete all',
			'drop table',
			'remove database',
			'hack',
			'backdoor',
			'malware',
			'virus',
		];

		foreach ( $dangerous_keywords as $keyword ) {
			if ( stripos( $requirements, $keyword ) !== false ) {
				$result['is_valid'] = false;
				$result['errors'][] = sprintf(
					__( 'Potentially dangerous requirement detected: "%s". Please revise your requirements.', 'wp-autoplugin' ),
					$keyword
				);
			}
		}

		// Check for complexity indicators.
		$complexity_indicators = [
			'database' => __( 'Consider using WordPress database API ($wpdb) for database operations.', 'wp-autoplugin' ),
			'ajax'     => __( 'Remember to implement proper AJAX security with nonces.', 'wp-autoplugin' ),
			'api'      => __( 'Ensure proper authentication and rate limiting for API endpoints.', 'wp-autoplugin' ),
			'payment'  => __( 'Payment functionality requires extra security measures and PCI compliance.', 'wp-autoplugin' ),
			'user'     => __( 'User management features need proper capability checks.', 'wp-autoplugin' ),
			'file'     => __( 'File operations should use WordPress filesystem API.', 'wp-autoplugin' ),
		];

		foreach ( $complexity_indicators as $indicator => $suggestion ) {
			if ( stripos( $requirements, $indicator ) !== false ) {
				$result['suggestions'][] = $suggestion;
			}
		}

		// Check for plugin conflicts.
		$result = $this->check_potential_conflicts( $requirements, $result );

		return $result;
	}

	/**
	 * Validate generated plugin code.
	 *
	 * @param string $code The generated plugin code.
	 * @return array Validation result with status and messages.
	 */
	public function validate_code( $code ) {
		$result = [
			'is_valid' => true,
			'warnings' => [],
			'errors'   => [],
			'security_score' => 100,
			'quality_score' => 100,
		];

		// Check for PHP syntax errors.
		$syntax_check = $this->check_php_syntax( $code );
		if ( ! $syntax_check['valid'] ) {
			$result['is_valid'] = false;
			$result['errors'][] = sprintf(
				__( 'PHP syntax error: %s', 'wp-autoplugin' ),
				$syntax_check['error']
			);
			return $result;
		}

		// Check for dangerous functions.
		foreach ( $this->dangerous_functions as $function ) {
			if ( preg_match( '/\b' . preg_quote( $function, '/' ) . '\s*\(/i', $code ) ) {
				$result['warnings'][] = sprintf(
					__( 'Potentially dangerous function "%s" detected. This may pose security risks.', 'wp-autoplugin' ),
					$function
				);
				$result['security_score'] -= 10;
			}
		}

		// Check for WordPress security practices.
		$security_implementations = $this->check_security_practices( $code );
		foreach ( $security_implementations as $practice => $status ) {
			if ( ! $status['found'] && $status['required'] ) {
				$result['warnings'][] = $status['message'];
				$result['security_score'] -= 5;
			}
		}

		// Check WordPress coding standards.
		$standards_check = $this->check_coding_standards( $code );
		foreach ( $standards_check as $standard => $status ) {
			if ( ! $status['compliant'] ) {
				$result['warnings'][] = $status['message'];
				$result['quality_score'] -= 5;
			}
		}

		// Check for plugin header.
		if ( ! preg_match( '/\*\s*Plugin Name:/i', $code ) ) {
			$result['is_valid'] = false;
			$result['errors'][] = __( 'Missing required WordPress plugin header.', 'wp-autoplugin' );
		}

		// Check for proper WordPress hooks usage.
		if ( ! preg_match( '/(add|remove)_(action|filter)/i', $code ) ) {
			$result['warnings'][] = __( 'No WordPress hooks detected. Plugin may not integrate properly with WordPress.', 'wp-autoplugin' );
			$result['quality_score'] -= 10;
		}

		// Ensure scores don't go below 0.
		$result['security_score'] = max( 0, $result['security_score'] );
		$result['quality_score'] = max( 0, $result['quality_score'] );

		return $result;
	}

	/**
	 * Check PHP syntax of code.
	 *
	 * @param string $code The PHP code to check.
	 * @return array Result with valid status and error message.
	 */
	private function check_php_syntax( $code ) {
		// Create a temporary file for syntax checking.
		$temp_file = wp_tempnam( 'wp_autoplugin_syntax_' );
		file_put_contents( $temp_file, $code );

		// Use PHP's built-in syntax checker.
		$output = shell_exec( sprintf( 'php -l %s 2>&1', escapeshellarg( $temp_file ) ) );
		unlink( $temp_file );

		if ( strpos( $output, 'No syntax errors' ) !== false ) {
			return [ 'valid' => true ];
		}

		// Extract error message.
		preg_match( '/Parse error: (.+) in/', $output, $matches );
		$error = isset( $matches[1] ) ? $matches[1] : __( 'Unknown syntax error', 'wp-autoplugin' );

		return [
			'valid' => false,
			'error' => $error,
		];
	}

	/**
	 * Check for WordPress security practices in code.
	 *
	 * @param string $code The code to check.
	 * @return array Security practice check results.
	 */
	private function check_security_practices( $code ) {
		$results = [];

		// Check if code has forms or AJAX.
		$has_forms = preg_match( '/<form|admin_post_|admin-ajax\.php/i', $code );
		$has_ajax = preg_match( '/wp_ajax_|admin-ajax\.php/i', $code );
		$has_database = preg_match( '/\$wpdb->/i', $code );

		// Nonce verification.
		$results['nonce_verification'] = [
			'found' => preg_match( $this->security_checks['nonce_verification'], $code ),
			'required' => $has_forms || $has_ajax,
			'message' => __( 'Forms and AJAX requests should use nonce verification for security.', 'wp-autoplugin' ),
		];

		// Capability checks.
		$results['capability_checks'] = [
			'found' => preg_match( $this->security_checks['capability_checks'], $code ),
			'required' => preg_match( '/add_menu_page|add_submenu_page|admin_/i', $code ),
			'message' => __( 'Admin functionality should include capability checks.', 'wp-autoplugin' ),
		];

		// Data sanitization.
		$results['data_sanitization'] = [
			'found' => preg_match( $this->security_checks['data_sanitization'], $code ),
			'required' => preg_match( '/\$_POST|\$_GET|\$_REQUEST/i', $code ),
			'message' => __( 'User input should be properly sanitized and escaped.', 'wp-autoplugin' ),
		];

		// SQL preparation.
		$results['sql_preparation'] = [
			'found' => preg_match( $this->security_checks['sql_preparation'], $code ),
			'required' => $has_database && preg_match( '/\$wpdb->query|\$wpdb->get_/i', $code ),
			'message' => __( 'Database queries should use $wpdb->prepare() to prevent SQL injection.', 'wp-autoplugin' ),
		];

		return $results;
	}

	/**
	 * Check WordPress coding standards compliance.
	 *
	 * @param string $code The code to check.
	 * @return array Coding standards check results.
	 */
	private function check_coding_standards( $code ) {
		$results = [];

		// Check for proper hooks usage.
		$results['proper_hooks'] = [
			'compliant' => preg_match( $this->coding_standards['proper_hooks'], $code ),
			'message' => __( 'Plugin should use WordPress hooks (add_action/add_filter) for integration.', 'wp-autoplugin' ),
		];

		// Check for text domain usage (i18n).
		$has_output = preg_match( '/echo|print|printf/i', $code );
		$results['text_domain'] = [
			'compliant' => ! $has_output || preg_match( $this->coding_standards['text_domain'], $code ),
			'message' => __( 'User-facing strings should be translatable using WordPress i18n functions.', 'wp-autoplugin' ),
		];

		// Check for function prefixing.
		$has_functions = preg_match( '/function\s+\w+\s*\(/i', $code );
		$results['prefix_functions'] = [
			'compliant' => ! $has_functions || preg_match( $this->coding_standards['prefix_functions'], $code ),
			'message' => __( 'Functions should be prefixed to avoid naming conflicts.', 'wp-autoplugin' ),
		];

		// Check for proper script/style enqueuing.
		$has_scripts = preg_match( '/<script|<style/i', $code );
		$results['wp_enqueue'] = [
			'compliant' => ! $has_scripts || preg_match( $this->coding_standards['wp_enqueue'], $code ),
			'message' => __( 'Scripts and styles should be enqueued using wp_enqueue_script/wp_enqueue_style.', 'wp-autoplugin' ),
		];

		return $results;
	}

	/**
	 * Check for potential plugin conflicts.
	 *
	 * @param string $requirements The plugin requirements.
	 * @param array  $result Current validation result.
	 * @return array Updated validation result.
	 */
	private function check_potential_conflicts( $requirements, $result ) {
		// Get active plugins.
		$active_plugins = get_option( 'active_plugins', [] );
		
		// Define known plugin categories and potential conflicts.
		$plugin_categories = [
			'seo' => [
				'keywords' => [ 'seo', 'meta', 'sitemap', 'schema' ],
				'plugins' => [ 'wordpress-seo', 'all-in-one-seo-pack', 'seo-by-rank-math' ],
				'message' => __( 'Multiple SEO plugins may conflict. Consider the existing SEO plugin functionality.', 'wp-autoplugin' ),
			],
			'cache' => [
				'keywords' => [ 'cache', 'performance', 'optimize' ],
				'plugins' => [ 'w3-total-cache', 'wp-super-cache', 'wp-rocket' ],
				'message' => __( 'Caching plugins may conflict. Ensure compatibility with existing cache solutions.', 'wp-autoplugin' ),
			],
			'security' => [
				'keywords' => [ 'security', 'firewall', 'protection' ],
				'plugins' => [ 'wordfence', 'sucuri-scanner', 'better-wp-security' ],
				'message' => __( 'Security plugins may have overlapping features. Check compatibility.', 'wp-autoplugin' ),
			],
			'ecommerce' => [
				'keywords' => [ 'shop', 'cart', 'product', 'payment', 'checkout' ],
				'plugins' => [ 'woocommerce', 'easy-digital-downloads' ],
				'message' => __( 'E-commerce functionality may conflict with existing shop plugins.', 'wp-autoplugin' ),
			],
		];

		foreach ( $plugin_categories as $category => $config ) {
			// Check if requirements match this category.
			$matches_category = false;
			foreach ( $config['keywords'] as $keyword ) {
				if ( stripos( $requirements, $keyword ) !== false ) {
					$matches_category = true;
					break;
				}
			}

			if ( $matches_category ) {
				// Check if any related plugins are active.
				foreach ( $active_plugins as $plugin ) {
					foreach ( $config['plugins'] as $known_plugin ) {
						if ( strpos( $plugin, $known_plugin ) !== false ) {
							$result['warnings'][] = $config['message'];
							break 2;
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Validate plugin plan before code generation.
	 *
	 * @param array $plan The plugin plan.
	 * @return array Validation result.
	 */
	public function validate_plan( $plan ) {
		$result = [
			'is_valid' => true,
			'warnings' => [],
			'errors'   => [],
		];

		// Check required plan sections.
		$required_sections = [
			'plugin_name',
			'design_and_architecture',
			'detailed_feature_description',
			'user_interface',
			'security_considerations',
			'testing_plan',
		];

		foreach ( $required_sections as $section ) {
			if ( ! isset( $plan[ $section ] ) || empty( $plan[ $section ] ) ) {
				$result['is_valid'] = false;
				$result['errors'][] = sprintf(
					__( 'Missing required plan section: %s', 'wp-autoplugin' ),
					$section
				);
			}
		}

		// Validate plugin name.
		if ( isset( $plan['plugin_name'] ) ) {
			if ( strlen( $plan['plugin_name'] ) < 3 ) {
				$result['warnings'][] = __( 'Plugin name seems too short.', 'wp-autoplugin' );
			}
			if ( strlen( $plan['plugin_name'] ) > 50 ) {
				$result['warnings'][] = __( 'Plugin name seems too long.', 'wp-autoplugin' );
			}
		}

		// Check for security considerations.
		if ( isset( $plan['security_considerations'] ) ) {
			$security_keywords = [ 'nonce', 'sanitize', 'escape', 'capability', 'permission' ];
			$has_security = false;
			foreach ( $security_keywords as $keyword ) {
				if ( stripos( $plan['security_considerations'], $keyword ) !== false ) {
					$has_security = true;
					break;
				}
			}
			if ( ! $has_security ) {
				$result['warnings'][] = __( 'Security considerations seem incomplete. Ensure proper security measures are included.', 'wp-autoplugin' );
			}
		}

		return $result;
	}
}