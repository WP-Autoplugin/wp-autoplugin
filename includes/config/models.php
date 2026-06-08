<?php
/**
 * Default model configuration for WP-Autoplugin.
 *
 * @package WP-Autoplugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'wp_autoplugin_models',
	[
		'OpenAI'    => [
			'gpt-5.5'      => 'GPT-5.5',
			'gpt-5.5-pro'  => 'GPT-5.5 Pro',
			'gpt-5.4'      => 'GPT-5.4',
			'gpt-5.4-pro'  => 'GPT-5.4 Pro',
			'gpt-5.4-mini' => 'GPT-5.4 mini',
			'gpt-5.4-nano' => 'GPT-5.4 nano',
			'gpt-5-pro'    => 'GPT-5 Pro',
			'gpt-5'        => 'GPT-5',
			'gpt-5-mini'   => 'GPT-5 mini',
			'gpt-5-nano'   => 'GPT-5 nano',
			'gpt-4.1'      => 'GPT-4.1',
			'gpt-4.1-mini' => 'GPT-4.1 mini',
			'gpt-4o'       => 'GPT-4o',
			'gpt-4o-mini'  => 'GPT-4o mini',
			'o3'           => 'o3',
			'o3-pro'       => 'o3-pro',
		],
		'Anthropic' => [
			'claude-opus-4-8'            => 'Claude Opus 4.8',
			'claude-opus-4-7'            => 'Claude Opus 4.7',
			'claude-opus-4-6'            => 'Claude Opus 4.6',
			'claude-opus-4-5-20251101'   => 'Claude Opus 4.5',
			'claude-sonnet-4-6'          => 'Claude Sonnet 4.6',
			'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5',
			'claude-haiku-4-5-20251001'  => 'Claude Haiku 4.5',
		],
		'Google'    => [
			'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro Preview',
			'gemini-3.5-flash'       => 'Gemini 3.5 Flash',
			'gemini-3-flash-preview' => 'Gemini 3 Flash Preview',
			'gemini-3.1-flash-lite'  => 'Gemini 3.1 Flash Lite',
			'gemini-2.5-pro'         => 'Gemini 2.5 Pro',
			'gemini-2.5-flash'       => 'Gemini 2.5 Flash',
			'gemini-2.5-flash-lite'  => 'Gemini 2.5 Flash Lite',
		],
		'xAI'       => [
			'grok-4.3'        => 'Grok 4.3',
			'grok-4.3-latest' => 'Grok 4.3 Latest',
			'grok-latest'     => 'Grok Latest',
			'grok-build-0.1'  => 'Grok Build 0.1',
		],
	]
);
