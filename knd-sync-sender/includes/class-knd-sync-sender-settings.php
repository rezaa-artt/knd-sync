<?php
/**
 * Sender settings.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Options wrapper.
 */
final class KND_Sync_Sender_Settings {

	public const OPTION_KEY = 'knd_sync_sender_settings';

	/**
	 * Defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'                  => 0,
			'target_url'               => 'https://knd-home.com',
			'api_key'                  => '',
			'hmac_secret'              => '',
			'hmac_enabled'             => 0,
			'sync_on_publish'          => 1,
			'sync_on_update'           => 1,
			'sync_featured_image'      => 1,
			'sync_content_images'      => 1,
			'sync_categories'          => 1,
			'sync_tags'                => 1,
			'sync_seo_meta'            => 1,
			'sync_seo_canonical'       => 0,
			'rewrite_internal_links'   => 1,
			'source_hosts'             => array( 'knddecor.com', 'www.knddecor.com' ),
			'target_host'              => 'knd-home.com',
			'retry_count'              => 3,
			'test_mode'                => 1,
			'sync_as_draft'            => 1,
			'log_retention_days'       => 30,
			'delete_data_on_uninstall' => 0,
		);
	}

	/**
	 * Ensure defaults exist.
	 */
	public static function ensure_defaults(): void {
		$current = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $current ) ) {
			add_option( self::OPTION_KEY, self::defaults(), '', false );
			return;
		}
		update_option( self::OPTION_KEY, array_merge( self::defaults(), $current ), false );
	}

	/**
	 * All settings.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Get one.
	 *
	 * @param string $key Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Update.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	public function update( array $settings ): void {
		update_option( self::OPTION_KEY, array_merge( $this->all(), $settings ), false );
	}

	/**
	 * Sanitize admin input. Bool keys missing from POST become 0 when $form_post is true.
	 *
	 * @param array<string, mixed> $input Input.
	 * @param bool                 $form_post Full form submit.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input, bool $form_post = false ): array {
		$out = $this->all();

		$bool_keys = array(
			'enabled',
			'hmac_enabled',
			'sync_on_publish',
			'sync_on_update',
			'sync_featured_image',
			'sync_content_images',
			'sync_categories',
			'sync_tags',
			'sync_seo_meta',
			'sync_seo_canonical',
			'rewrite_internal_links',
			'test_mode',
			'sync_as_draft',
			'delete_data_on_uninstall',
		);

		foreach ( $bool_keys as $key ) {
			if ( $form_post ) {
				$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			} elseif ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			}
		}

		if ( isset( $input['target_url'] ) ) {
			$out['target_url'] = esc_url_raw( untrailingslashit( (string) $input['target_url'] ) );
		}
		if ( isset( $input['api_key'] ) ) {
			$out['api_key'] = sanitize_text_field( (string) $input['api_key'] );
		}
		if ( isset( $input['hmac_secret'] ) ) {
			$out['hmac_secret'] = sanitize_text_field( (string) $input['hmac_secret'] );
		}
		if ( isset( $input['target_host'] ) ) {
			$host = strtolower( sanitize_text_field( (string) $input['target_host'] ) );
			$host = preg_replace( '#^https?://#', '', $host ) ?? $host;
			$out['target_host'] = rtrim( $host, '/' );
		}
		if ( isset( $input['retry_count'] ) ) {
			$out['retry_count'] = max( 1, min( 10, (int) $input['retry_count'] ) );
		}
		if ( isset( $input['log_retention_days'] ) ) {
			$out['log_retention_days'] = max( 1, min( 365, (int) $input['log_retention_days'] ) );
		}
		if ( isset( $input['source_hosts'] ) ) {
			$hosts = is_array( $input['source_hosts'] )
				? $input['source_hosts']
				: preg_split( '/[\s,]+/', (string) $input['source_hosts'] );
			$clean = array();
			foreach ( (array) $hosts as $host ) {
				$host = strtolower( sanitize_text_field( (string) $host ) );
				$host = preg_replace( '#^https?://#', '', $host ) ?? $host;
				$host = rtrim( $host, '/' );
				if ( $host !== '' && preg_match( '/^[a-z0-9.-]+$/', $host ) ) {
					$clean[] = $host;
				}
			}
			$out['source_hosts'] = array_values( array_unique( $clean ) );
		}

		return $out;
	}

	/**
	 * Whether target URL uses HTTPS.
	 */
	public function target_is_https(): bool {
		$url = (string) $this->get( 'target_url', '' );
		return str_starts_with( strtolower( $url ), 'https://' );
	}
}
