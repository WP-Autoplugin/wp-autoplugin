<?php

use WP_Autoplugin\V2\Domain\AI\Model_Catalog;
use WP_Autoplugin\V2\Domain\AI\Model_Registry;
use WP_Autoplugin\V2\Infrastructure\AI\Agent_Transport_Factory;
use WP_Autoplugin\V2\Infrastructure\AI\Direct_Transport_Factory;
use WP_Autoplugin\V2\Rest\Routes;

/** Focused coverage for the v2 model catalog and global role controls. */
final class ModelCatalogTest extends WP_UnitTestCase {
	/** @var array<int, string> */
	private array $options = [
		'wp_autoplugin_model',
		'wp_autoplugin_default_model_effort',
		'wp_autoplugin_planner_model',
		'wp_autoplugin_planner_model_effort',
		'wp_autoplugin_coder_model',
		'wp_autoplugin_coder_model_effort',
		'wp_autoplugin_reviewer_model',
		'wp_autoplugin_reviewer_model_effort',
		'wp_autoplugin_openai_api_key',
		'wp_autoplugin_anthropic_api_key',
		'wp_autoplugin_google_api_key',
		'wp_autoplugin_xai_api_key',
		'wp_autoplugin_custom_models',
		'wp_autoplugin_v2_planner_model',
		'wp_autoplugin_v2_planner_model_effort',
		'wp_autoplugin_v2_coder_model',
		'wp_autoplugin_v2_coder_model_effort',
		'wp_autoplugin_v2_reviewer_model',
		'wp_autoplugin_v2_reviewer_model_effort',
	];

	/** @var array<string, mixed> */
	private array $previous = [];

	public function set_up(): void {
		parent::set_up();
		foreach ( $this->options as $option ) {
			$this->previous[ $option ] = get_option( $option, null );
		}
		foreach ( [ 'wp_autoplugin_v2_planner_model', 'wp_autoplugin_v2_planner_model_effort', 'wp_autoplugin_v2_coder_model', 'wp_autoplugin_v2_coder_model_effort', 'wp_autoplugin_v2_reviewer_model', 'wp_autoplugin_v2_reviewer_model_effort' ] as $option ) {
			delete_option( $option );
		}
	}

	public function tear_down(): void {
		foreach ( $this->previous as $option => $value ) {
			if ( null === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}
		parent::tear_down();
	}

	public function test_registry_contains_the_latest_requested_provider_models(): void {
		$models = Model_Registry::all();

		$this->assertSame( 'GPT-6.1 Sol', $models['OpenAI']['gpt-6.1-sol'] );
		$this->assertSame( 'GPT-6 Astra', $models['OpenAI']['gpt-6-astra'] );
		$this->assertSame( 'GPT-6 Sol', $models['OpenAI']['gpt-6-sol'] );
		$this->assertSame( 'GPT-6 Luna', $models['OpenAI']['gpt-6-luna'] );
		$this->assertSame( 'Claude Opus 5.5', $models['Anthropic']['claude-opus-5-5'] );
		$this->assertSame( 'Claude Sonnet 5.5', $models['Anthropic']['claude-sonnet-5-5'] );
		$this->assertSame( 'Claude Haiku 5.5', $models['Anthropic']['claude-haiku-5-5'] );
		$this->assertSame( 'GPT-5.6 Sol', $models['OpenAI']['gpt-5.6-sol'] );
		$this->assertSame( 'GPT-5.6 Terra', $models['OpenAI']['gpt-5.6-terra'] );
		$this->assertSame( 'GPT-5.6 Luna', $models['OpenAI']['gpt-5.6-luna'] );
		$this->assertSame( 'Claude Sonnet 5', $models['Anthropic']['claude-sonnet-5'] );
		$this->assertSame( 'Claude Opus 5', $models['Anthropic']['claude-opus-5'] );
		$this->assertSame( 'Claude Fable 5', $models['Anthropic']['claude-fable-5'] );
		$this->assertSame( 'Gemini 3.6 Flash', $models['Google']['gemini-3.6-flash'] );
		$this->assertSame( 'Gemini 3.5 Flash-Lite', $models['Google']['gemini-3.5-flash-lite'] );
		$this->assertSame( 'Grok 4.5', $models['xAI']['grok-4.5'] );
	}

	public function test_new_models_are_available_for_each_role_with_images_and_native_tools(): void {
		update_option( 'wp_autoplugin_openai_api_key', 'test-key' );
		update_option( 'wp_autoplugin_anthropic_api_key', 'test-key' );
		$catalog = new Model_Catalog();
		foreach ( [ 'gpt-6.1-sol', 'gpt-6-astra', 'gpt-6-sol', 'gpt-6-luna', 'claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5' ] as $model ) {
			foreach ( [ 'planner', 'coder', 'reviewer' ] as $role ) {
				$selection = $catalog->update( $role, $model, 'max' );
				$this->assertFalse( is_wp_error( $selection ) );
				$this->assertTrue( $selection['available'] );
				$this->assertTrue( $selection['images'] );
				$this->assertTrue( $selection['direct'] );
				$this->assertTrue( $selection['native_read_tools'] );
				$this->assertSame( 'max', $selection['effort'] );
			}
		}
	}

	public function test_role_selection_inherits_default_and_can_be_detached_or_restored(): void {
		update_option( 'wp_autoplugin_model', 'gpt-5.4' );
		update_option( 'wp_autoplugin_default_model_effort', 'high' );
		update_option( 'wp_autoplugin_planner_model', '' );
		update_option( 'wp_autoplugin_openai_api_key', 'test-key' );
		$catalog = new Model_Catalog();

		$inherited = $catalog->selection( 'planner' );
		$this->assertTrue( $inherited['inherits_default'] );
		$this->assertSame( 'gpt-5.4', $inherited['model'] );
		$this->assertSame( 'high', $inherited['effort'] );

		$explicit = $catalog->update( 'planner', 'gpt-5.4-mini', 'xhigh' );
		$this->assertFalse( is_wp_error( $explicit ) );
		$this->assertFalse( $explicit['inherits_default'] );
		$this->assertSame( 'gpt-5.4-mini', get_option( 'wp_autoplugin_planner_model' ) );
		$this->assertSame( 'xhigh', get_option( 'wp_autoplugin_planner_model_effort' ) );

		$restored = $catalog->update( 'planner', '', 'low' );
		$this->assertFalse( is_wp_error( $restored ) );
		$this->assertTrue( $restored['inherits_default'] );
		$this->assertSame( '', get_option( 'wp_autoplugin_planner_model_effort' ) );
	}

	public function test_rejects_unknown_roles_and_models_and_normalizes_effort(): void {
		$catalog = new Model_Catalog();
		$this->assertWPError( $catalog->update( 'unknown', 'gpt-5.4', 'high' ) );
		$this->assertWPError( $catalog->update( 'coder', 'not-a-real-model', 'high' ) );

		$selection = $catalog->update( 'coder', 'gpt-5.4-mini', 'not-valid' );
		$this->assertFalse( is_wp_error( $selection ) );
		$this->assertSame( 'none', $selection['effort'] );
		$this->assertSame( 'none', get_option( 'wp_autoplugin_coder_model_effort' ) );

		$unsupported = $catalog->update( 'reviewer', 'gpt-4.1', 'high' );
		$this->assertFalse( is_wp_error( $unsupported ) );
		$this->assertSame( '', $unsupported['effort'] );
		$this->assertSame( '', get_option( 'wp_autoplugin_reviewer_model_effort' ) );
	}

	public function test_custom_catalog_entries_never_expose_transport_configuration(): void {
		update_option(
			'wp_autoplugin_custom_models',
			[
				[
					'name'           => 'private-endpoint',
					'url'            => 'https://private.example/v1/chat/completions',
					'apiKey'         => 'super-secret-key',
					'modelParameter' => 'remote-model-name',
					'headers'        => [ 'X-Private=secret-header' ],
				],
			]
		);
		$state   = ( new Model_Catalog() )->state();
		$encoded = wp_json_encode( $state );
		$custom  = array_values( array_filter( $state['catalog'], static fn( array $item ): bool => 'private-endpoint' === $item['id'] ) );

		$this->assertCount( 1, $custom );
		$this->assertTrue( $custom[0]['configured'] );
		$this->assertTrue( $custom[0]['available'] );
		$this->assertSame( 'custom', $custom[0]['provider'] );
		$this->assertArrayNotHasKey( 'transport_model', $custom[0] );
		$this->assertStringNotContainsString( 'super-secret-key', $encoded );
		$this->assertStringNotContainsString( 'private.example', $encoded );
		$this->assertStringNotContainsString( 'secret-header', $encoded );
		$this->assertStringNotContainsString( 'remote-model-name', $encoded );
	}

	public function test_planner_capability_distinguishes_direct_and_native_tool_models(): void {
		update_option( 'wp_autoplugin_model', 'gemini-2.5-pro' );
		update_option( 'wp_autoplugin_planner_model', 'gemini-2.5-pro' );
		update_option( 'wp_autoplugin_google_api_key', 'test-key' );
		$this->assertTrue( ( new Direct_Transport_Factory() )->capability( 'plan' )['available'] );
		$this->assertFalse( ( new Agent_Transport_Factory() )->capability( 'plan' )['available'] );

		update_option( 'wp_autoplugin_planner_model', 'gemini-3.5-flash-lite' );
		$this->assertTrue( ( new Direct_Transport_Factory() )->capability( 'plan' )['available'] );
		$this->assertTrue( ( new Agent_Transport_Factory() )->capability( 'plan' )['available'] );

		update_option( 'wp_autoplugin_planner_model', 'gpt-5.4-mini' );
		update_option( 'wp_autoplugin_openai_api_key', 'test-key' );
		$this->assertTrue( ( new Direct_Transport_Factory() )->capability( 'plan' )['available'] );
		$this->assertTrue( ( new Agent_Transport_Factory() )->capability( 'plan' )['available'] );
	}

	public function test_rest_permissions_and_update_response(): void {
		$routes = new Routes();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertFalse( $routes->can_manage() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertTrue( $routes->can_manage() );
		$request = new WP_REST_Request( 'POST', '/wp-autoplugin/v2/model-settings/coder' );
		$request->set_param( 'role', 'coder' );
		$request->set_param( 'model', 'gpt-5.4-mini' );
		$request->set_param( 'effort', 'high' );
		$response = $routes->update_model_setting( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 'gpt-5.4-mini', $response->get_data()['model'] );
		$this->assertSame( 'high', $response->get_data()['effort'] );
	}

	public function test_job_payloads_snapshot_each_role_before_queueing(): void {
		update_option( 'wp_autoplugin_model', 'gpt-5.4-mini' );
		update_option( 'wp_autoplugin_planner_model', 'gpt-5.4' );
		update_option( 'wp_autoplugin_planner_model_effort', 'high' );
		update_option( 'wp_autoplugin_coder_model', 'gpt-5.4-mini' );
		update_option( 'wp_autoplugin_coder_model_effort', 'low' );
		update_option( 'wp_autoplugin_reviewer_model', 'gpt-5.5' );
		update_option( 'wp_autoplugin_reviewer_model_effort', 'xhigh' );
		update_option( 'wp_autoplugin_openai_api_key', 'test-key' );
		$routes    = new Routes();
		$reflection = new ReflectionMethod( Routes::class, 'snapshot_job_models' );
		$workspace = [ 'target_kind' => 'new_plugin', 'target_metadata' => [ 'kind' => 'new_plugin' ] ];

		$plan = $reflection->invoke( $routes, 'plan', [], $workspace );
		$code = $reflection->invoke( $routes, 'code', [ 'mode' => 'generate' ], $workspace );
		$review = $reflection->invoke( $routes, 'review', [ 'revision_id' => 10 ], $workspace );
		$fix = $reflection->invoke( $routes, 'review_fix', [ 'revision_id' => 10, 'auto_re_review' => true ], $workspace );

		$this->assertSame( [ 'provider' => 'openai', 'model' => 'gpt-5.4', 'effort' => 'high' ], $plan['prompt_model'] );
		$this->assertSame( [ 'provider' => 'openai', 'model' => 'gpt-5.4-mini', 'effort' => 'low' ], $code['prompt_model'] );
		$this->assertSame( [ 'provider' => 'openai', 'model' => 'gpt-5.5', 'effort' => 'xhigh' ], $review['reviewer'] );
		$this->assertSame( $code['prompt_model'], $fix['prompt_model'] );
		$this->assertSame( $review['reviewer'], $fix['reviewer'] );

		update_option( 'wp_autoplugin_coder_model', 'gpt-5.5' );
		$this->assertSame( 'gpt-5.4-mini', $code['prompt_model']['model'], 'An already-created payload must not follow later global changes.' );
	}
}
