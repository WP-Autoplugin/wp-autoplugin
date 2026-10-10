<?php

namespace WP_Autoplugin\V2\Domain\AI;

/**
 * Owns the built-in model definitions used by the v2 application.
 */
final class Model_Registry {
	/**
	 * Return built-in models grouped by provider label.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function all(): array {
		$models = [
			'OpenAI'    => [
				'gpt-6.1-sol'   => 'GPT-6.1 Sol',
				'gpt-6-astra'   => 'GPT-6 Astra',
				'gpt-6-sol'     => 'GPT-6 Sol',
				'gpt-6-luna'    => 'GPT-6 Luna',
				'gpt-5.6-sol'   => 'GPT-5.6 Sol',
				'gpt-5.6-terra' => 'GPT-5.6 Terra',
				'gpt-5.6-luna'  => 'GPT-5.6 Luna',
				'gpt-5.5'       => 'GPT-5.5',
				'gpt-5.5-pro'   => 'GPT-5.5 Pro',
				'gpt-5.4'       => 'GPT-5.4',
				'gpt-5.4-pro'   => 'GPT-5.4 Pro',
				'gpt-5.4-mini'  => 'GPT-5.4 mini',
				'gpt-5.4-nano'  => 'GPT-5.4 nano',
				'gpt-5-pro'     => 'GPT-5 Pro',
				'gpt-5'         => 'GPT-5',
				'gpt-5-mini'    => 'GPT-5 mini',
				'gpt-5-nano'    => 'GPT-5 nano',
				'gpt-4.1'       => 'GPT-4.1',
				'gpt-4.1-mini'  => 'GPT-4.1 mini',
				'gpt-4o'        => 'GPT-4o',
				'gpt-4o-mini'   => 'GPT-4o mini',
				'o3'            => 'o3',
				'o3-pro'        => 'o3-pro',
			],
			'Anthropic' => [
				'claude-opus-5-5'            => 'Claude Opus 5.5',
				'claude-sonnet-5-5'          => 'Claude Sonnet 5.5',
				'claude-haiku-5-5'           => 'Claude Haiku 5.5',
				'claude-fable-5'             => 'Claude Fable 5',
				'claude-opus-5'              => 'Claude Opus 5',
				'claude-sonnet-5'            => 'Claude Sonnet 5',
				'claude-opus-4-8'            => 'Claude Opus 4.8',
				'claude-opus-4-7'            => 'Claude Opus 4.7',
				'claude-opus-4-6'            => 'Claude Opus 4.6',
				'claude-opus-4-5-20251101'   => 'Claude Opus 4.5',
				'claude-sonnet-4-6'          => 'Claude Sonnet 4.6',
				'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5',
				'claude-haiku-4-5-20251001'  => 'Claude Haiku 4.5',
			],
			'Google'    => [
				'gemini-3.6-flash'       => 'Gemini 3.6 Flash',
				'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro Preview',
				'gemini-3.5-flash'       => 'Gemini 3.5 Flash',
				'gemini-3.5-flash-lite'  => 'Gemini 3.5 Flash-Lite',
				'gemini-3-flash-preview' => 'Gemini 3 Flash Preview',
				'gemini-3.1-flash-lite'  => 'Gemini 3.1 Flash Lite',
				'gemini-2.5-pro'         => 'Gemini 2.5 Pro',
				'gemini-2.5-flash'       => 'Gemini 2.5 Flash',
				'gemini-2.5-flash-lite'  => 'Gemini 2.5 Flash Lite',
			],
			'xAI'       => [
				'grok-4.5'        => 'Grok 4.5',
				'grok-4.3'        => 'Grok 4.3',
				'grok-4.3-latest' => 'Grok 4.3 Latest',
				'grok-latest'     => 'Grok Latest',
				'grok-build-0.1'  => 'Grok Build 0.1',
			],
		];

		/** This is the v2 successor to the existing public model-catalog filter. */
		return (array) apply_filters( 'wp_autoplugin_models', $models );
	}
}
