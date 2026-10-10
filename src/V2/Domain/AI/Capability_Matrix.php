<?php

namespace WP_Autoplugin\V2\Domain\AI;

/**
 * Declares orchestration features separately from provider transports.
 */
final class Capability_Matrix {
	private const IMAGE_MODEL_PATTERNS = [
		'openai'    => '/^(?:gpt-(?:4\.1|4o|[56](?:\.\d+)?)|o3)(?:$|-)/',
		'anthropic' => '/^claude-/',
		'google'    => '/^gemini-/',
		'xai'       => '/^grok-/',
		'chatgpt'   => '/^chatgpt:gpt-/',
	];

	/**
	 * Return conservative capabilities for the currently bundled transports.
	 *
	 * @return array<string, bool|int|string>
	 */
	public function for_model( string $provider, string $model ): array {
		$provider      = sanitize_key( $provider );
		$image_pattern = self::IMAGE_MODEL_PATTERNS[ $provider ] ?? '';
		$native_tools  = in_array( $provider, [ 'openai', 'anthropic', 'chatgpt' ], true )
			|| ( 'google' === $provider && 1 === preg_match( '/^gemini-3(?:$|[.-])/', $model ) );
		$capabilities  = [
			'provider'            => $provider,
			'model'               => sanitize_text_field( $model ),
			'direct_mode'         => true,
			'native_read_tools'   => $native_tools,
			'unified_patch'       => false,
			'images'              => '' !== $image_pattern && 1 === preg_match( $image_pattern, $model ),
			'max_tool_iterations' => $native_tools ? 8 : 0,
		];

		/**
		 * Filter model capabilities when a transport implements bounded tool use.
		 * Writers and arbitrary command tools are never valid v2 capabilities.
		 *
		 * @param array  $capabilities Capability declaration.
		 * @param string $provider     Provider identifier.
		 * @param string $model        Model identifier.
		 */
		return (array) apply_filters( 'wp_autoplugin_v2_model_capabilities', $capabilities, $provider, $model );
	}
}
