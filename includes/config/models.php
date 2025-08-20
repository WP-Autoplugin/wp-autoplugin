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
			'gpt-5'             => 'GPT-5',
			'gpt-5-mini'        => 'GPT-5 mini',
			'gpt-5-nano'        => 'GPT-5 nano',
			'gpt-5-chat-latest' => 'ChatGPT-5-latest',
			'gpt-4.5-preview'   => 'GPT-4.5 Preview',
			'gpt-4.1'           => 'GPT-4.1',
			'gpt-4.1-mini'      => 'GPT-4.1 mini',
			'gpt-4.1-nano'      => 'GPT-4.1 nano',
			'gpt-4o'            => 'GPT-4o',
			'gpt-4o-mini'       => 'GPT-4o mini',
			'chatgpt-4o-latest' => 'ChatGPT-4o-latest',
			'o1'                => 'o1',
			'o1-preview'        => 'o1-preview',
			'o3-mini-low'       => 'o3-mini-low',
			'o3-mini-medium'    => 'o3-mini-medium',
			'o3-mini-high'      => 'o3-mini-high',
			'o3-low'            => 'o3-low',
			'o3-medium'         => 'o3-medium',
			'o3-high'           => 'o3-high',
			'o4-mini-low'       => 'o4-mini-low',
			'o4-mini-medium'    => 'o4-mini-medium',
			'o4-mini-high'      => 'o4-mini-high',
			'gpt-4-turbo'       => 'GPT-4 Turbo',
			'gpt-3.5-turbo'     => 'GPT-3.5 Turbo',
		],
		'Anthropic' => [
			'claude-opus-4-1-20250805'   => 'Claude Opus 4.1-20250805',
			'claude-opus-4-20250514'     => 'Claude Opus 4-20250514',
			'claude-sonnet-4-20250514'   => 'Claude Sonnet 4-20250514',
			'claude-3-7-sonnet-latest'   => 'Claude 3.7 Sonnet-latest',
			'claude-3-7-sonnet-20250219' => 'Claude 3.7 Sonnet-20250219',
			'claude-3-7-sonnet-thinking' => 'Claude 3.7 Sonnet Thinking',
			'claude-3-5-sonnet-latest'   => 'Claude 3.5 Sonnet-latest',
			'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet-20241022',
			'claude-3-5-sonnet-20240620' => 'Claude 3.5 Sonnet-20240620',
			'claude-3-5-haiku-latest'    => 'Claude 3.5 Haiku-latest',
			'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku-20241022',
			'claude-3-opus-20240229'     => 'Claude 3 Opus-20240229',
			'claude-3-sonnet-20240229'   => 'Claude 3 Sonnet-20240229',
			'claude-3-haiku-20240307'    => 'Claude 3 Haiku-20240307',
		],
		'Google'    => [
			'gemini-2.5-pro'        => 'Gemini 2.5 Pro',
			'gemini-2.5-flash'      => 'Gemini 2.5 Flash',
			'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite',
			'gemini-2.0-flash'      => 'Gemini 2.0 Flash',
			'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite',
			'gemini-1.5-flash'      => 'Gemini 1.5 Flash',
			'gemma-3-27b-it'        => 'Gemma 3 27B',
		],
		'xAI'       => [
			'grok-4'      => 'Grok 4 (Latest)',
			'grok-4-0709' => 'Grok 4-0709',
			'grok-3'      => 'Grok 3',
			'grok-3-mini' => 'Grok 3 Mini',
			'grok-2-1212' => 'Grok 2-1212',
		],
	]
);
