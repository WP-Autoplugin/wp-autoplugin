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
			'gpt-5.2'                 => 'GPT-5.2',
			'gpt-5.2-pro'             => 'GPT-5.2 Pro',
			'gpt-5.2-codex'           => 'GPT-5.2 Codex',
			'gpt-5.1-instant'         => 'GPT-5.1 Instant',
			'gpt-5.1-thinking'        => 'GPT-5.1 Thinking',
			'gpt-5'                   => 'GPT-5',
			'gpt-5-mini'              => 'GPT-5 mini',
			'gpt-5-nano'              => 'GPT-5 nano',
			'gpt-4.1-2025-04-14'      => 'GPT-4.1',
			'gpt-4.1-mini-2025-04-14' => 'GPT-4.1 mini',
			'gpt-4.1-nano-2025-04-14' => 'GPT-4.1 nano',
			'gpt-4o'                  => 'GPT-4o',
			'gpt-4o-mini'             => 'GPT-4o mini',
			'gpt-4o-latest'           => 'GPT-4o-latest',
			'chatgpt-4o-latest'       => 'ChatGPT-4o-latest',
			'o3'                      => 'o3',
			'o3-pro'                  => 'o3-pro',
			'o3-mini'                 => 'o3-mini',
			'o4-mini'                 => 'o4-mini',
			'o1'                      => 'o1',
			'gpt-4-turbo'             => 'GPT-4 Turbo',
			'gpt-3.5-turbo'           => 'GPT-3.5 Turbo',
		],
		'Anthropic' => [
			'claude-opus-4-5-20251101'   => 'Claude Opus 4.5',
			'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5',
			'claude-haiku-4-5-20251001'  => 'Claude Haiku 4.5',
			'claude-opus-4-1-20250805'   => 'Claude Opus 4.1',
			'claude-opus-4-20250514'     => 'Claude Opus 4',
			'claude-sonnet-4-20250514'   => 'Claude Sonnet 4',
			'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (2024-10-22)',
			'claude-3-5-sonnet-20240620' => 'Claude 3.5 Sonnet (2024-06-20)',
			'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku',
			'claude-3-haiku-20240307'    => 'Claude 3 Haiku',
		],
		'Google'    => [
			'gemini-3-pro-preview'   => 'Gemini 3 Pro Preview',
			'gemini-3-flash-preview' => 'Gemini 3 Flash Preview',
			'gemini-2.5-pro'         => 'Gemini 2.5 Pro',
			'gemini-2.5-flash'       => 'Gemini 2.5 Flash',
			'gemini-2.5-flash-lite'  => 'Gemini 2.5 Flash Lite',
			'gemini-2.0-flash'       => 'Gemini 2.0 Flash',
			'gemini-2.0-flash-lite'  => 'Gemini 2.0 Flash Lite',
			'gemma-3-27b-it'         => 'Gemma 3 27B',
		],
		'xAI'       => [
			'grok-4-1-fast-reasoning'     => 'Grok 4.1 Fast Reasoning',
			'grok-4-1-fast-non-reasoning' => 'Grok 4.1 Fast Non-Reasoning',
			'grok-code-fast-1'            => 'Grok Code Fast 1',
			'grok-4'                      => 'Grok 4',
			'grok-4-latest'               => 'Grok 4 Latest',
			'grok-3'                      => 'Grok 3',
			'grok-3-mini'                 => 'Grok 3 Mini',
			'grok-2-1212'                 => 'Grok 2',
		],
	]
);
