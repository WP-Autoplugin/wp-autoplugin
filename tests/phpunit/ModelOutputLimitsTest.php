<?php

use WP_Autoplugin\V2\Domain\AI\Model_Output_Limits;

/** Covers provider-documented output ceilings for built-in v2 models. */
final class ModelOutputLimitsTest extends WP_UnitTestCase {
	public function test_known_model_families_use_their_native_output_maximums(): void {
		foreach ( [ 'gpt-6-astra', 'gpt-6-sol', 'gpt-6-luna', 'gpt-6.1-sol' ] as $model ) {
			$this->assertSame( 128000, Model_Output_Limits::maximum( 'openai', $model ) );
		}
		foreach ( [ 'claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5' ] as $model ) {
			$this->assertSame( 128000, Model_Output_Limits::maximum( 'anthropic', $model ) );
		}
		$this->assertSame( 128000, Model_Output_Limits::maximum( 'openai', 'gpt-5.4-mini' ) );
		$this->assertSame( 32768, Model_Output_Limits::maximum( 'openai', 'gpt-4.1' ) );
		$this->assertSame( 16384, Model_Output_Limits::maximum( 'openai', 'gpt-4o-mini' ) );
		$this->assertSame( 100000, Model_Output_Limits::maximum( 'openai', 'o3-pro' ) );
		$this->assertSame( 128000, Model_Output_Limits::maximum( 'anthropic', 'claude-opus-4-8' ) );
		$this->assertSame( 64000, Model_Output_Limits::maximum( 'anthropic', 'claude-sonnet-4-6' ) );
		$this->assertSame( 65536, Model_Output_Limits::maximum( 'google', 'gemini-3.6-flash' ) );
	}

	public function test_known_models_default_to_the_native_maximum_and_respect_smaller_explicit_caps(): void {
		$options = [ 'max_output_tokens' => 8192 ];
		$this->assertSame( 128000, Model_Output_Limits::request_limit( 'openai', 'gpt-5.4-mini', [], 4096 ) );
		$this->assertSame( 8192, Model_Output_Limits::request_limit( 'openai', 'gpt-5.4-mini', $options, 4096 ) );
		$this->assertSame( 8192, Model_Output_Limits::request_limit( 'custom', 'private-model', $options, 4096 ) );
		$this->assertSame( 4096, Model_Output_Limits::request_limit( 'openai', 'future-model', [], 4096 ) );
	}

	public function test_limit_can_be_overridden_for_filtered_catalog_models(): void {
		$filter = static fn( $limit, string $provider, string $model ) => 'custom' === $provider && 'private-model' === $model ? 48000 : $limit;
		add_filter( 'wp_autoplugin_v2_model_output_limit', $filter, 10, 3 );
		try {
			$this->assertSame( 48000, Model_Output_Limits::maximum( 'custom', 'private-model' ) );
		} finally {
			remove_filter( 'wp_autoplugin_v2_model_output_limit', $filter, 10 );
		}
	}
}
