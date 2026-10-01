<?php
/**
 * HTTP client for target REST API.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to receiver endpoints via WP HTTP API.
 */
final class KND_Sync_Sender_API_Client {

	/**
	 * @var KND_Sync_Sender_Settings
	 */
	private KND_Sync_Sender_Settings $settings;

	/**
	 * @var KND_Sync_Sender_Auth
	 */
	private KND_Sync_Sender_Auth $auth;

	/**
	 * @var KND_Sync_Sender_Logger
	 */
	private KND_Sync_Sender_Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param KND_Sync_Sender_Settings $settings Settings.
	 * @param KND_Sync_Sender_Auth     $auth Auth.
	 * @param KND_Sync_Sender_Logger   $logger Logger.
	 */
	public function __construct(
		KND_Sync_Sender_Settings $settings,
		KND_Sync_Sender_Auth $auth,
		KND_Sync_Sender_Logger $logger
	) {
		$this->settings = $settings;
		$this->auth     = $auth;
		$this->logger   = $logger;
	}

	/**
	 * Test connection.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function status(): array|WP_Error {
		return $this->request( 'GET', '/wp-json/knd-sync/v1/status', null, wp_generate_uuid4() );
	}

	/**
	 * Upsert post payload.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @param string               $request_id Request id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function sync_post( array $payload, string $request_id ): array|WP_Error {
		return $this->request( 'POST', '/wp-json/knd-sync/v1/post', $payload, $request_id );
	}

	/**
	 * Preview on remote (optional).
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @param string               $request_id Request id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function preview_post( array $payload, string $request_id ): array|WP_Error {
		return $this->request( 'POST', '/wp-json/knd-sync/v1/preview', $payload, $request_id );
	}

	/**
	 * Perform HTTP request.
	 *
	 * @param string                    $method Method.
	 * @param string                    $path Path.
	 * @param array<string, mixed>|null $body Body.
	 * @param string                    $request_id Request id.
	 * @return array<string, mixed>|WP_Error
	 */
	private function request( string $method, string $path, ?array $body, string $request_id ): array|WP_Error {
		$base = untrailingslashit( (string) $this->settings->get( 'target_url', '' ) );
		if ( $base === '' ) {
			return new WP_Error( 'TARGET_MISSING', __( 'Target URL is not configured.', 'knd-sync' ) );
		}

		if ( ! $this->settings->target_is_https() ) {
			$this->logger->warning(
				'insecure_target',
				__( 'Target URL is not HTTPS.', 'knd-sync' ),
				array( 'request_id' => $request_id )
			);
			if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
				return new WP_Error( 'TARGET_INSECURE', __( 'Production sync requires an HTTPS target URL.', 'knd-sync' ) );
			}
		}

		$url      = $base . $path;
		$json     = null === $body ? '' : (string) wp_json_encode( $body );
		$headers  = $this->auth->headers( $json, $request_id );
		$args     = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 45,
		);
		if ( $json !== '' ) {
			$args['body'] = $json;
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'TARGET_UNAVAILABLE', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array( 'raw' => substr( $raw, 0, 500 ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = (string) ( $data['message'] ?? $data['error_code'] ?? __( 'Remote API error.', 'knd-sync' ) );
			$error   = new WP_Error(
				(string) ( $data['code'] ?? $data['error_code'] ?? 'API_ERROR' ),
				$message,
				array(
					'status'        => $code,
					'response_body' => $data,
				)
			);
			return $error;
		}

		$data['response_code'] = $code;
		return $data;
	}
}
