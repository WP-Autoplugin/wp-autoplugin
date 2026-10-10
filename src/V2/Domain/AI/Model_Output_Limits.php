<?php

namespace WP_Autoplugin\V2\Domain\AI;

/**
 * Provider-documented output ceilings for built-in v2 models.
 *
 * These are request ceilings, not generation targets. Providers charge for
 * tokens actually generated. Unknown and custom models retain the caller's
 * explicit budget instead of receiving a guessed limit.
 */
final class Model_Output_Limits {
	public static function maximum( string $provider, string $model ): ?int {
		$limit = match ( sanitize_key( $provider ) ) {
			'openai' => self::openai( $model ),
			'anthropic' => self::anthropic( $model ),
			'google' => self::google( $model ),
			default => null,
		};

		/**
		 * Filter the documented maximum output-token limit for a model.
		 *
		 * Return null when the provider should retain the caller's explicit
		 * budget. This is especially useful for custom model catalogs.
		 *
		 * @param int|null $limit    Output-token maximum.
		 * @param string   $provider Provider key.
		 * @param string   $model    Provider model ID.
		 */
		$limit = apply_filters( 'wp_autoplugin_v2_model_output_limit', $limit, $provider, $model );
		if ( null === $limit || (int) $limit < 1 ) {
			return null;
		}

		return (int) $limit;
	}

	public static function request_limit( string $provider, string $model, array $options, int $fallback ): int {
		$maximum   = self::maximum( $provider, $model );
		$requested = max( 1, (int) ( $options['max_output_tokens'] ?? $maximum ?? $fallback ) );
		if ( null !== $maximum ) {
			return min( $maximum, $requested );
		}

		return $requested;
	}

	private static function openai( string $model ): ?int {
		if ( str_starts_with( $model, 'gpt-5' ) || str_starts_with( $model, 'gpt-6' ) ) {
			return 128000;
		}
		if ( str_starts_with( $model, 'gpt-4.1' ) ) {
			return 32768;
		}
		if ( str_starts_with( $model, 'gpt-4o' ) ) {
			return 16384;
		}
		if ( str_starts_with( $model, 'o3' ) ) {
			return 100000;
		}

		return null;
	}

	private static function anthropic( string $model ): ?int {
		if (
			str_starts_with( $model, 'claude-fable-5' )
			|| str_starts_with( $model, 'claude-opus-5' )
			|| str_starts_with( $model, 'claude-sonnet-5' )
			|| str_starts_with( $model, 'claude-haiku-5-5' )
			|| str_starts_with( $model, 'claude-opus-4-8' )
			|| str_starts_with( $model, 'claude-opus-4-7' )
			|| str_starts_with( $model, 'claude-opus-4-6' )
		) {
			return 128000;
		}
		if (
			str_starts_with( $model, 'claude-sonnet-4-6' )
			|| str_starts_with( $model, 'claude-opus-4-5' )
			|| str_starts_with( $model, 'claude-sonnet-4-5' )
			|| str_starts_with( $model, 'claude-haiku-4-5' )
		) {
			return 64000;
		}

		return null;
	}

	private static function google( string $model ): ?int {
		return str_starts_with( $model, 'gemini-2.5-' ) || str_starts_with( $model, 'gemini-3' ) ? 65536 : null;
	}
}
