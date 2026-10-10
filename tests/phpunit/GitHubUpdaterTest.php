<?php

use WP_Autoplugin\V2\Infrastructure\Update\GitHub_Updater;

/** WordPress update-contract and package-normalization coverage. */
final class GitHubUpdaterTest extends WP_UnitTestCase {
	private const SHA = '0123456789abcdef0123456789abcdef01234567';

	/** @var array<int, array{url:string,args:array<string,mixed>}> */
	private array $requests = [];

	private string $remote_version = '2.1.0';

	public function test_real_plugin_header_declares_release_and_update_metadata(): void {
		$plugin = get_plugin_data( WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php', false, false );

		$this->assertSame( '2.0.2', $plugin['Version'] );
		$this->assertSame( '6.6', $plugin['RequiresWP'] );
		$this->assertSame( '8.0', $plugin['RequiresPHP'] );
		$this->assertSame( 'https://wp-autoplugin.com/updates/wp-autoplugin', $plugin['UpdateURI'] );
	}

	public function set_up(): void {
		parent::set_up();
		add_filter( 'pre_http_request', [ $this, 'mock_github' ], 10, 3 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'mock_github' ], 10 );
		parent::tear_down();
	}

	public function test_update_payload_uses_remote_headers_and_an_immutable_commit_package(): void {
		$updater = $this->updater();
		$update  = $updater->filter_update(
			false,
			[
				'Version'   => '2.0.1',
				'UpdateURI' => 'https://wp-autoplugin.com/updates/wp-autoplugin',
			],
			plugin_basename( WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php' )
		);

		$this->assertIsArray( $update );
		$this->assertSame( '2.1.0', $update['version'] );
		$this->assertSame( '6.6', $update['requires'] );
		$this->assertSame( '7.0', $update['tested'] );
		$this->assertSame( '8.0', $update['requires_php'] );
		$this->assertSame( 'https://github.com/WP-Autoplugin/wp-autoplugin/archive/' . self::SHA . '.zip', $update['package'] );
		$this->assertCount( 4, $this->requests );
		$this->assertStringEndsWith( '/releases/latest', $this->requests[0]['url'] );
		$this->assertStringEndsWith( '/commits/2.1.0', $this->requests[1]['url'] );
		$this->assertStringContainsString( '/' . self::SHA . '/wp-autoplugin.php', $this->requests[2]['url'] );
		$this->assertSame( 3, $this->requests[0]['args']['redirection'] );
		$this->assertSame( 10, $this->requests[0]['args']['timeout'] );
		$this->assertTrue( $this->requests[0]['args']['sslverify'] );
	}

	public function test_update_check_is_scoped_and_fails_closed_for_invalid_versions(): void {
		$updater = $this->updater();
		$this->assertSame(
			[ 'existing' => true ],
			$updater->filter_update(
				[ 'existing' => true ],
				[
					'Version'   => '1.0.0',
					'UpdateURI' => 'https://example.com/another-plugin',
				],
				'another-plugin/plugin.php'
			)
		);
		$this->assertSame( [], $this->requests );

		$this->remote_version = 'not a version';
		$this->assertFalse(
			$this->updater()->filter_update(
				false,
				[
					'Version'   => '2.0.1',
					'UpdateURI' => 'https://wp-autoplugin.com/updates/wp-autoplugin',
				],
				plugin_basename( WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php' )
			)
		);

		$this->remote_version = '1.9.0';
		$metadata             = $this->updater()->filter_update(
			false,
			[
				'Version'   => '2.0.1',
				'UpdateURI' => 'https://wp-autoplugin.com/updates/wp-autoplugin',
			],
			plugin_basename( WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php' )
		);
		$this->assertSame( '1.9.0', $metadata['version'] );
	}

	public function test_plugin_information_uses_sanitized_readme_sections(): void {
		$info = $this->updater()->filter_plugin_information(
			false,
			'plugin_information',
			(object) [ 'slug' => dirname( plugin_basename( WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php' ) ) ]
		);

		$this->assertIsObject( $info );
		$this->assertSame( '2.1.0', $info->version );
		$this->assertStringContainsString( '<strong>Remote</strong>', $info->sections['description'] );
		$this->assertStringContainsString( '<ul><li>One change.</li></ul>', $info->sections['changelog'] );
		$this->assertStringNotContainsString( '<script', $info->sections['description'] );
	}

	public function test_package_source_normalization_only_moves_this_plugin_update(): void {
		$updater = $this->updater();
		$this->assertSame(
			'/tmp/unrelated/',
			$updater->normalize_package_source(
				'/tmp/unrelated/',
				'/tmp/package/',
				null,
				[
					'type'   => 'plugin',
					'action' => 'update',
					'plugin' => 'another-plugin/plugin.php',
				]
			)
		);

		$root        = wp_normalize_path( sys_get_temp_dir() . '/wp-autoplugin-updater-' . wp_generate_password( 16, false, false ) );
		$source      = $root . '/wp-autoplugin-' . self::SHA;
		$destination = $root . '/' . dirname( plugin_basename( WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php' ) );
		wp_mkdir_p( $source );
		file_put_contents( $source . '/wp-autoplugin.php', "<?php\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test-only package fixture.

		global $wp_filesystem;
		$previous_filesystem = $wp_filesystem;
		$wp_filesystem       = new class() {
			public function exists( string $path ): bool {
				return file_exists( $path );
			}

			public function move( string $source, string $destination ): bool {
				return rename( $source, $destination );
			}
		};

		try {
			$result = $updater->normalize_package_source(
				trailingslashit( $source ),
				trailingslashit( $root ),
				null,
				[
					'type'   => 'plugin',
					'action' => 'update',
					'plugin' => plugin_basename( WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php' ),
				]
			);
			$this->assertSame( trailingslashit( $destination ), $result );
			$this->assertFileExists( $destination . '/wp-autoplugin.php' );
			$this->assertDirectoryDoesNotExist( $source );
		} finally {
			$wp_filesystem = $previous_filesystem;
			if ( is_file( $destination . '/wp-autoplugin.php' ) ) {
				wp_delete_file( $destination . '/wp-autoplugin.php' );
			}
			if ( is_dir( $destination ) ) {
				rmdir( $destination );
			}
			if ( is_dir( $source ) ) {
				rmdir( $source );
			}
			if ( is_dir( $root ) ) {
				rmdir( $root );
			}
		}
	}

	/**
	 * @param mixed                $response Short-circuit response.
	 * @param array<string, mixed> $args     HTTP request arguments.
	 * @return array<string, mixed>
	 */
	public function mock_github( $response, array $args, string $url ): array {
		unset( $response );
		$this->requests[] = [ 'url' => $url, 'args' => $args ];

		if ( str_ends_with( $url, '/releases/latest' ) ) {
			$body = wp_json_encode(
				[
					'tag_name'     => $this->remote_version,
					'draft'        => false,
					'prerelease'   => false,
					'published_at' => '2026-07-28T12:00:00Z',
				]
			);
		} elseif ( str_contains( $url, '/commits/' ) ) {
			$body = wp_json_encode( [ 'sha' => self::SHA ] );
		} elseif ( str_ends_with( $url, '/wp-autoplugin.php' ) ) {
			$body = "<?php\n/**\n * Plugin Name: WP-Autoplugin\n * Version: {$this->remote_version}\n * Requires at least: 6.6\n * Requires PHP: 8.0\n */";
		} else {
			$body = "=== WP-Autoplugin ===\nTested up to: 7.0\n\n== Description ==\n\n**Remote** description. <script>alert(1)</script>\n\n== Changelog ==\n\n= 2.1.0 =\n\n* One change.\n";
		}

		return [
			'headers'  => [],
			'body'     => $body,
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'cookies'  => [],
			'filename' => null,
		];
	}

	private function updater(): GitHub_Updater {
		return new GitHub_Updater(
			WP_AUTOPLUGIN_DIR . 'wp-autoplugin.php',
			'WP-Autoplugin/wp-autoplugin',
			'https://wp-autoplugin.com/updates/wp-autoplugin'
		);
	}
}
