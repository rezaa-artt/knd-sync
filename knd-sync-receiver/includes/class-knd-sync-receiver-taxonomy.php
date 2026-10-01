<?php
/**
 * Category/tag mapping for receiver.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps taxonomies by slug; optionally creates missing terms.
 */
final class KND_Sync_Receiver_Taxonomy {

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
	 * Resolve term IDs for a taxonomy from payload terms.
	 *
	 * @param string              $taxonomy Taxonomy.
	 * @param array<int, mixed>   $terms Term descriptors.
	 * @param string              $request_id Request id.
	 * @return int[]
	 */
	public function map_terms( string $taxonomy, array $terms, string $request_id ): array {
		$ids = array();

		foreach ( $terms as $term ) {
			if ( ! is_array( $term ) ) {
				continue;
			}

			$slug = sanitize_title( (string) ( $term['slug'] ?? '' ) );
			$name = sanitize_text_field( (string) ( $term['name'] ?? $slug ) );

			if ( $slug === '' && $name === '' ) {
				continue;
			}

			$existing = $slug !== '' ? get_term_by( 'slug', $slug, $taxonomy ) : false;
			if ( $existing && ! is_wp_error( $existing ) ) {
				$ids[] = (int) $existing->term_id;
				continue;
			}

			if ( ! (int) $this->settings->get( 'create_missing_terms', 1 ) ) {
				$this->logger->warning(
					'term_missing',
					sprintf(
						/* translators: 1: taxonomy, 2: slug */
						__( 'Missing term skipped (%1$s:%2$s).', 'knd-sync' ),
						$taxonomy,
						$slug
					),
					array( 'request_id' => $request_id )
				);
				continue;
			}

			$created = wp_insert_term(
				$name !== '' ? $name : $slug,
				$taxonomy,
				array( 'slug' => $slug )
			);

			if ( is_wp_error( $created ) ) {
				// Race: term created between check and insert.
				if ( $created->get_error_code() === 'term_exists' ) {
					$term_id = (int) $created->get_error_data();
					if ( $term_id > 0 ) {
						$ids[] = $term_id;
					}
					continue;
				}

				$this->logger->error(
					'term_create_failed',
					$created->get_error_message(),
					array( 'request_id' => $request_id )
				);
				continue;
			}

			$ids[] = (int) $created['term_id'];
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}
}
