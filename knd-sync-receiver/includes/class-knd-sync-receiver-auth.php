<?php
/**
 * REST authentication for receiver.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API key (+ optional HMAC) authentication.
 */
final class KND_Sync_Receiver_Auth {

	/**
	 * @var KND_Sync_Receiver_Settings
	 */
	private KND_Sync_Receiver_Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param KND_Sync_Receiver_Settings $settings Settings.
	 */
	public function __construct( KND_Sync_Receiver_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * permission_callback for private routes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_callback( WP_REST_Request $request ): true|WP_Error {
		if ( ! (int) $this->settings->get( 'enabled', 1 ) ) {
			return new WP_Error( 'knd_sync_disabled', __( 'KND Sync receiver is disabled.', 'knd-sync' ), array( 'status' => 403 ) );
		}

		$provided = (string) $request->get_header( 'x-knd-sync-key' );
		$expected = (string) $this->settings->get( 'api_key', '' );

		if ( $expected === '' || $provided === '' || ! hash_equals( $expected, $provided ) ) {
			return new WP_Error( 'AUTH_FAILED', __( 'Authentication failed.', 'knd-sync' ), array( 'status' => 401 ) );
		}

		// Reject API key if mistakenly passed in query string.
		if ( $request->get_param( 'api_key' ) || $request->get_param( 'key' ) ) {
			return new WP_Error( 'AUTH_INSECURE', __( 'API key must be sent via header only.', 'knd-sync' ), array( 'status' => 400 ) );
		}

		$hmac_required = (int) $this->settings->get( 'hmac_required', 0 );
		$signature     = (string) $request->get_header( 'x-knd-sync-signature' );
		$timestamp     = (string) $request->get_header( 'x-knd-sync-timestamp' );

		if ( $hmac_required || ( $signature !== '' && $timestamp !== '' ) ) {
			$hmac = $this->validate_hmac( $request, $signature, $timestamp );
			if ( is_wp_error( $hmac ) ) {
				return $hmac;
			}
		}

		if ( ! $this->allow_rate( $request ) ) {
			return new WP_Error( 'RATE_LIMITED', __( 'Too many requests.', 'knd-sync' ), array( 'status' => 429 ) );
		}

		return true;
	}

	/**
	 * Validate HMAC signature.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $signature Header signature.
	 * @param string          $timestamp Header timestamp.
	 * @return true|WP_Error
	 */
	private function validate_hmac( WP_REST_Request $request, string $signature, string $timestamp ): true|WP_Error {
		if ( $signature === '' || $timestamp === '' || ! ctype_digit( $timestamp ) ) {
			return new WP_Error( 'HMAC_MISSING', __( 'HMAC signature headers are required.', 'knd-sync' ), array( 'status' => 401 ) );
		}

		$skew = max( 60, (int) $this->settings->get( 'timestamp_skew', 300 ) );
		$now  = time();
		$ts   = (int) $timestamp;

		if ( abs( $now - $ts ) > $skew ) {
			return new WP_Error( 'HMAC_EXPIRED', __( 'Request timestamp is outside the allowed window.', 'knd-sync' ), array( 'status' => 401 ) );
		}

		$secret = (string) $this->settings->get( 'hmac_secret', '' );
		if ( $secret === '' ) {
			return new WP_Error( 'HMAC_NOT_CONFIGURED', __( 'HMAC secret is not configured.', 'knd-sync' ), array( 'status' => 500 ) );
		}

		$body      = $request->get_body();
		$base      = $timestamp . '.' . $body;
		$expected  = hash_hmac( 'sha256', $base, $secret );

		if ( ! hash_equals( $expected, $signature ) ) {
			return new WP_Error( 'HMAC_INVALID', __( 'Invalid HMAC signature.', 'knd-sync' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Simple per-minute rate limit keyed by API key hash.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private function allow_rate( WP_REST_Request $request ): bool {
		$limit = (int) $this->settings->get( 'rate_limit_per_minute', 60 );
		if ( $limit <= 0 ) {
			return true;
		}

		$key  = 'knd_sync_rl_' . md5( (string) $request->get_header( 'x-knd-sync-key' ) );
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			$data = array( 'count' => 0, 'start' => time() );
		}

		if ( ( time() - (int) $data['start'] ) >= 60 ) {
			$data = array( 'count' => 0, 'start' => time() );
		}

		$data['count'] = (int) $data['count'] + 1;
		set_transient( $key, $data, 120 );

		return $data['count'] <= $limit;
	}

	/**
	 * Build HMAC signature for outbound use / tests.
	 *
	 * @param string $body Body.
	 * @param int    $timestamp Unix timestamp.
	 * @param string $secret Secret.
	 */
	public static function sign( string $body, int $timestamp, string $secret ): string {
		return hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	}
}
