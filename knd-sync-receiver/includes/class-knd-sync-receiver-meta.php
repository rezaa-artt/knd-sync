<?php
/**
 * Post meta + SEO adapter orchestration.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies allowlisted meta and SEO fields.
 */
final class KND_Sync_Receiver_Meta {

	/**
	 * Generic allowlist for non-SEO meta (extensible).
	 *
	 * @var string[]
	 */
	private const GENERIC_ALLOWLIST = array(
		'_thumbnail_id', // handled separately usually; keep blocked from blind copy.
	);

	/**
	 * @var KND_Sync_Receiver_Settings
	 */
	private KND_Sync_Receiver_Settings $settings;

	/**
	 * @var KND_Sync_Receiver_Logger
	 */
	private KND_Sync_Receiver_Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param KND_Sync_Receiver_Settings $settings Settings.
	 * @param KND_Sync_Receiver_Logger   $logger Logger.
	 */
	public function __construct( KND_Sync_Receiver_Settings $settings, KND_Sync_Receiver_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Apply meta from payload.
	 *
	 * @param int                  $post_id Target post ID.
	 * @param array<string, mixed> $payload Full payload.
	 * @param string               $request_id Request id.
	 */
	public function apply( int $post_id, array $payload, string $request_id ): void {
		$meta = isset( $payload['meta'] ) && is_array( $payload['meta'] ) ? $payload['meta'] : array();

		// Sync identity meta always.
		$source_site = esc_url_raw( (string) ( $payload['source']['site'] ?? '' ) );
		$source_id   = (int) ( $payload['source']['post_id'] ?? 0 );

		update_post_meta( $post_id, '_knd_sync_source_site', $source_site );
		update_post_meta( $post_id, '_knd_sync_source_post_id', $source_id );
		update_post_meta( $post_id, '_knd_sync_last_sync', current_time( 'mysql', true ) );
		update_post_meta( $post_id, '_knd_sync_origin', 'knddecor' );
		update_post_meta( $post_id, '_knd_sync_schema_version', sanitize_text_field( (string) ( $payload['schema_version'] ?? '1.0' ) ) );
		update_post_meta( $post_id, '_knd_sync_request_id', sanitize_text_field( $request_id ) );

		if ( (int) $this->settings->get( 'sync_seo_meta', 1 ) ) {
			$adapter = new KND_Sync_Receiver_SEO_RankMath( $this->settings );
			if ( $adapter->is_available() ) {
				$adapter->apply( $post_id, $meta, $request_id );
			} else {
				$this->logger->warning(
					'seo_adapter_unavailable',
					__( 'Rank Math not active; SEO meta skipped.', 'knd-sync' ),
					array( 'request_id' => $request_id, 'target_post_id' => $post_id )
				);
			}
		}

		// Future: generic allowlisted meta (explicit keys only).
		$extra = isset( $meta['custom'] ) && is_array( $meta['custom'] ) ? $meta['custom'] : array();
		$allowed = apply_filters( 'knd_sync_receiver_allowed_meta_keys', array() );
		if ( is_array( $allowed ) && $allowed ) {
			foreach ( $allowed as $key ) {
				$key = (string) $key;
				if ( $key === '' || ! array_key_exists( $key, $extra ) ) {
					continue;
				}
				if ( in_array( $key, self::GENERIC_ALLOWLIST, true ) ) {
					continue;
				}
				update_post_meta( $post_id, $key, $extra[ $key ] );
			}
		}

		// Elementor guard: never wipe Elementor data unless explicitly enabled.
		if ( ! (int) $this->settings->get( 'sync_elementor', 0 ) ) {
			return;
		}

		$elementor = isset( $meta['elementor'] ) && is_array( $meta['elementor'] ) ? $meta['elementor'] : array();
		if ( ! $elementor ) {
			return;
		}

		$adapter = new KND_Sync_Receiver_Elementor_Adapter();
		$adapter->apply( $post_id, $elementor );
	}
}
