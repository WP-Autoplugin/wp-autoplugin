<?php

namespace WP_Autoplugin\V2\Domain\AI;

use WP_Autoplugin\V2\Infrastructure\AI\ChatGPT_Config;
use WP_Autoplugin\V2\Infrastructure\AI\ChatGPT_Model_Service;

/**
 * Defines provider effort controls without changing the existing model catalog.
 */
final class Model_Effort {
	private const ROLE_OPTIONS = [
		'default'  => 'wp_autoplugin_default_model_effort',
		'planner'  => 'wp_autoplugin_planner_model_effort',
		'coder'    => 'wp_autoplugin_coder_model_effort',
		'reviewer' => 'wp_autoplugin_reviewer_model_effort',
	];

	/**
	 * Return effort capabilities keyed by exact provider model ID.
	 *
	 * @return array<string, array{provider:string,levels:array<int,string>,default:string}>
	 */
	public static function capabilities(): array {
		$openai_standard  = [ 'none', 'low', 'medium', 'high', 'xhigh' ];
		$openai_gpt_5_6   = [ 'none', 'low', 'medium', 'high', 'xhigh', 'max' ];
		$openai_reasoning = [ 'low', 'medium', 'high', 'xhigh', 'max' ];
		$openai_gpt_5     = [ 'minimal', 'low', 'medium', 'high' ];
		$anthropic        = [ 'low', 'medium', 'high', 'max' ];
		$anthropic_5      = [ 'low', 'medium', 'high', 'xhigh', 'max' ];

		$capabilities = [
			'gpt-6.1-sol'              => [
				'provider' => 'openai',
				'levels'   => $openai_reasoning,
				'default'  => 'medium',
			],
			'gpt-6-astra'              => [
				'provider' => 'openai',
				'levels'   => $openai_reasoning,
				'default'  => 'medium',
			],
			'gpt-6-sol'                => [
				'provider' => 'openai',
				'levels'   => $openai_gpt_5_6,
				'default'  => 'medium',
			],
			'gpt-6-luna'               => [
				'provider' => 'openai',
				'levels'   => $openai_gpt_5_6,
				'default'  => 'medium',
			],
			'gpt-5.6-sol'              => [
				'provider' => 'openai',
				'levels'   => $openai_gpt_5_6,
				'default'  => 'medium',
			],
			'gpt-5.6-terra'            => [
				'provider' => 'openai',
				'levels'   => $openai_gpt_5_6,
				'default'  => 'medium',
			],
			'gpt-5.6-luna'             => [
				'provider' => 'openai',
				'levels'   => $openai_gpt_5_6,
				'default'  => 'medium',
			],
			'gpt-5.5'                  => [
				'provider' => 'openai',
				'levels'   => $openai_standard,
				'default'  => 'medium',
			],
			'gpt-5.5-pro'              => [
				'provider' => 'openai',
				'levels'   => [ 'medium', 'high', 'xhigh' ],
				'default'  => 'high',
			],
			'gpt-5.4'                  => [
				'provider' => 'openai',
				'levels'   => $openai_standard,
				'default'  => 'none',
			],
			'gpt-5.4-pro'              => [
				'provider' => 'openai',
				'levels'   => [ 'medium', 'high', 'xhigh' ],
				'default'  => 'medium',
			],
			'gpt-5.4-mini'             => [
				'provider' => 'openai',
				'levels'   => $openai_standard,
				'default'  => 'none',
			],
			'gpt-5.4-nano'             => [
				'provider' => 'openai',
				'levels'   => $openai_standard,
				'default'  => 'none',
			],
			'gpt-5'                    => [
				'provider' => 'openai',
				'levels'   => $openai_gpt_5,
				'default'  => 'medium',
			],
			'gpt-5-mini'               => [
				'provider' => 'openai',
				'levels'   => $openai_gpt_5,
				'default'  => 'medium',
			],
			'gpt-5-nano'               => [
				'provider' => 'openai',
				'levels'   => $openai_gpt_5,
				'default'  => 'medium',
			],
			'o3'                       => [
				'provider' => 'openai',
				'levels'   => [ 'low', 'medium', 'high' ],
				'default'  => 'medium',
			],
			'claude-fable-5'           => [
				'provider' => 'anthropic',
				'levels'   => $anthropic_5,
				'default'  => 'high',
			],
			'claude-opus-5-5'          => [
				'provider' => 'anthropic',
				'levels'   => $anthropic_5,
				'default'  => 'medium',
			],
			'claude-haiku-5-5'         => [
				'provider' => 'anthropic',
				'levels'   => $anthropic_5,
				'default'  => 'medium',
			],
			'claude-sonnet-5-5'        => [
				'provider' => 'anthropic',
				'levels'   => $anthropic_5,
				'default'  => 'high',
			],
			'claude-opus-5'            => [
				'provider' => 'anthropic',
				'levels'   => $anthropic_5,
				'default'  => 'high',
			],
			'claude-sonnet-5'          => [
				'provider' => 'anthropic',
				'levels'   => $anthropic_5,
				'default'  => 'high',
			],
			'claude-opus-4-8'          => [
				'provider' => 'anthropic',
				'levels'   => [ 'low', 'medium', 'high', 'xhigh', 'max' ],
				'default'  => 'high',
			],
			'claude-opus-4-7'          => [
				'provider' => 'anthropic',
				'levels'   => [ 'low', 'medium', 'high', 'xhigh', 'max' ],
				'default'  => 'high',
			],
			'claude-opus-4-6'          => [
				'provider' => 'anthropic',
				'levels'   => $anthropic,
				'default'  => 'high',
			],
			'claude-sonnet-4-6'        => [
				'provider' => 'anthropic',
				'levels'   => $anthropic,
				'default'  => 'high',
			],
			'claude-opus-4-5-20251101' => [
				'provider' => 'anthropic',
				'levels'   => [ 'low', 'medium', 'high' ],
				'default'  => 'high',
			],
		];

		foreach ( ( new ChatGPT_Model_Service() )->verified_models() as $slug => $metadata ) {
			if ( ! isset( ChatGPT_Config::models()[ $slug ] ) ) {
				continue;
			}
			$fallback = ChatGPT_Config::models()[ $slug ];
			$levels   = array_values( (array) ( $metadata['levels'] ?? [] ) ) ?: $fallback['levels'];
			$default  = in_array( (string) ( $metadata['default'] ?? '' ), $levels, true ) ? (string) $metadata['default'] : $fallback['default'];
			$capabilities[ ChatGPT_Config::catalog_id( $slug ) ] = [
				'provider' => 'chatgpt',
				'levels'   => $levels,
				'default'  => $default,
			];
		}

		return $capabilities;
	}

	public static function option_name( string $role ): string {
		return self::ROLE_OPTIONS[ $role ] ?? '';
	}

	/** @return array<string, string> */
	public static function option_names(): array {
		return self::ROLE_OPTIONS;
	}

	public static function selected_model( string $role ): string {
		$default = (string) get_option( 'wp_autoplugin_model' );
		if ( 'default' === $role ) {
			return $default;
		}

		$option = match ( $role ) {
			'planner' => 'wp_autoplugin_planner_model',
			'coder' => 'wp_autoplugin_coder_model',
			'reviewer' => 'wp_autoplugin_reviewer_model',
			default => '',
		};
		$model = $option ? (string) get_option( $option ) : '';

		return '' !== $model ? $model : $default;
	}

	/**
	 * Return the role's effective effort, including specialized-model fallback.
	 */
	public static function for_role( string $role ): string {
		$model = self::selected_model( $role );
		if ( 'default' !== $role ) {
			$model_option = (string) get_option( 'wp_autoplugin_' . $role . '_model' );
			if ( '' === $model_option ) {
				$role = 'default';
			}
		}

		$option = self::option_name( $role );
		$value  = $option ? (string) get_option( $option, '' ) : '';

		return self::normalize( $model, $value );
	}

	public static function normalize( string $model, string $effort ): string {
		$capability = self::capabilities()[ $model ] ?? null;
		if ( ! $capability ) {
			return '';
		}

		$effort = self::sanitize( $effort );
		return in_array( $effort, $capability['levels'], true ) ? $effort : $capability['default'];
	}

	public static function sanitize( $effort ): string {
		$effort = sanitize_key( (string) $effort );
		return in_array( $effort, [ 'none', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max', 'ultra' ], true ) ? $effort : '';
	}
}
