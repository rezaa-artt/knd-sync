<?php
/**
 * Outbound auth header builder.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds API key / HMAC headers.
 */
final class KND_Sync_Sender_Auth {

	/**
	 * @var KND_Sync_Sender_Settings
	 */
	private KND_Sync_Sender_Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param KND_Sync_Sender_Settings $settings Settings.
	 */
	public function __construct( KND_Sync_Sender_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Build request headers.
	 *
	 * @param string $body JSON body (empty for GET).
	 * @param string $request_id Request id.
	 * @return array<string, string>
	 */
	public function headers( string $body, string $request_id ): array {
		$headers = array(
			'Content-Type'         => 'application/json',
			'Accept'               => 'application/json',
			'X-KND-SYNC-KEY'       => (string) $this->settings->get( 'api_key', '' ),
			'X-KND-SYNC-REQUEST-ID'=> $request_id,
		);

		if ( (int) $this->settings->get( 'hmac_enabled', 0 ) ) {
			$secret = (string) $this->settings->get( 'hmac_secret', '' );
			$ts     = time();
			$headers['X-KND-SYNC-TIMESTAMP'] = (string) $ts;
			$headers['X-KND-SYNC-SIGNATURE'] = hash_hmac( 'sha256', $ts . '.' . $body, $secret );
		}

		return $headers;
	}
}
