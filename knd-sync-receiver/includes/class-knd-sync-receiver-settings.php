<?php
/**
 * Receiver settings storage.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Options API wrapper with defaults and sanitization helpers.
 */
final class KND_Sync_Receiver_Settings {

	public const OPTION_KEY = 'knd_sync_receiver_settings';

	/**
	 * Default option values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'                 => 1,
			'api_key'                 => '',
			'hmac_secret'             => '',
			'hmac_required'           => 0,
			'timestamp_skew'          => 300,
			'create_missing_terms'    => 1,
			'sync_categories'         => 1,
			'sync_tags'               => 1,
			'sync_featured_image'     => 1,
			'sync_content_images'     => 1,
			'sync_seo_meta'           => 1,
			'sync_seo_canonical'      => 0,
			'sync_elementor'          => 0,
			'preserve_dates'          => 1,
			'force_draft'             => 0,
			'fallback_author'         => 0,
			'max_image_bytes'         => 8 * MB_IN_BYTES,
			'rate_limit_per_minute'   => 60,
			'log_retention_days'      => 30,
			'delete_data_on_uninstall'=> 0,
			'allowed_source_hosts'    => array( 'knddecor.com', 'www.knddecor.com' ),
		);
	}

	/**
	 * Ensure option exists with defaults and generated secrets.
	 */
	public static function ensure_defaults(): void {
		$current = get_option( self::OPTION_KEY, null );
		$defaults = self::defaults();

		if ( ! is_array( $current ) ) {
			$defaults['api_key']     = wp_generate_password( 48, false, false );
			$defaults['hmac_secret'] = wp_generate_password( 64, false, false );
			$defaults['fallback_author'] = (int) get_current_user_id() ?: 1;
			add_option( self::OPTION_KEY, $defaults, '', false );
			return;
		}

		$merged = array_merge( $defaults, $current );
		if ( empty( $merged['api_key'] ) ) {
			$merged['api_key'] = wp_generate_password( 48, false, false );
		}
		if ( empty( $merged['hmac_secret'] ) ) {
			$merged['hmac_secret'] = wp_generate_password( 64, false, false );
		}
		update_option( self::OPTION_KEY, $merged, false );
	}

	/**
	 * Get all settings.
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
	 * Get one setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	public function update( array $settings ): void {
		$merged = array_merge( $this->all(), $settings );
		update_option( self::OPTION_KEY, $merged, false );
	}

	/**
	 * Sanitize incoming settings from admin form.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @param bool                 $form_post Full form submit (missing checkboxes => 0).
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input, bool $form_post = false ): array {
		$current = $this->all();
		$out     = $current;

		$bool_keys = array(
			'enabled',
			'hmac_required',
			'create_missing_terms',
			'sync_categories',
			'sync_tags',
			'sync_featured_image',
			'sync_content_images',
			'sync_seo_meta',
			'sync_seo_canonical',
			'sync_elementor',
			'preserve_dates',
			'force_draft',
			'delete_data_on_uninstall',
		);

		foreach ( $bool_keys as $key ) {
			if ( $form_post ) {
				$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			} elseif ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			}
		}

		if ( isset( $input['api_key'] ) ) {
			$out['api_key'] = sanitize_text_field( (string) $input['api_key'] );
		}
		if ( isset( $input['hmac_secret'] ) ) {
			$out['hmac_secret'] = sanitize_text_field( (string) $input['hmac_secret'] );
		}
		if ( isset( $input['timestamp_skew'] ) ) {
			$out['timestamp_skew'] = max( 60, min( 3600, (int) $input['timestamp_skew'] ) );
		}
		if ( isset( $input['fallback_author'] ) ) {
			$out['fallback_author'] = max( 0, (int) $input['fallback_author'] );
		}
		if ( isset( $input['max_image_bytes'] ) ) {
			$out['max_image_bytes'] = max( MB_IN_BYTES, (int) $input['max_image_bytes'] );
		}
		if ( isset( $input['rate_limit_per_minute'] ) ) {
			$out['rate_limit_per_minute'] = max( 1, min( 600, (int) $input['rate_limit_per_minute'] ) );
		}
		if ( isset( $input['log_retention_days'] ) ) {
			$out['log_retention_days'] = max( 1, min( 365, (int) $input['log_retention_days'] ) );
		}
		if ( isset( $input['allowed_source_hosts'] ) ) {
			$hosts = is_array( $input['allowed_source_hosts'] )
				? $input['allowed_source_hosts']
				: preg_split( '/[\s,]+/', (string) $input['allowed_source_hosts'] );
			$clean = array();
			foreach ( (array) $hosts as $host ) {
				$host = strtolower( sanitize_text_field( (string) $host ) );
				$host = preg_replace( '#^https?://#', '', $host ) ?? $host;
				$host = rtrim( $host, '/' );
				if ( $host !== '' && preg_match( '/^[a-z0-9.-]+$/', $host ) ) {
					$clean[] = $host;
				}
			}
			$out['allowed_source_hosts'] = array_values( array_unique( $clean ) );
		}

		return $out;
	}
}
