<?php
/**
 * Post upsert logic for receiver.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates or updates posts idempotently by source identity.
 */
final class KND_Sync_Receiver_Post {

	/**
	 * @var KND_Sync_Receiver_Settings
	 */
	private KND_Sync_Receiver_Settings $settings;

	/**
	 * @var KND_Sync_Receiver_Logger
	 */
	private KND_Sync_Receiver_Logger $logger;

	/**
	 * @var KND_Sync_Receiver_Media
	 */
	private KND_Sync_Receiver_Media $media;

	/**
	 * @var KND_Sync_Receiver_Taxonomy
	 */
	private KND_Sync_Receiver_Taxonomy $taxonomy;

	/**
	 * @var KND_Sync_Receiver_Meta
	 */
	private KND_Sync_Receiver_Meta $meta;

	/**
	 * Constructor.
	 *
	 * @param KND_Sync_Receiver_Settings $settings Settings.
	 * @param KND_Sync_Receiver_Logger   $logger Logger.
	 * @param KND_Sync_Receiver_Media    $media Media.
	 * @param KND_Sync_Receiver_Taxonomy $taxonomy Taxonomy.
	 * @param KND_Sync_Receiver_Meta     $meta Meta.
	 */
	public function __construct(
		KND_Sync_Receiver_Settings $settings,
		KND_Sync_Receiver_Logger $logger,
		KND_Sync_Receiver_Media $media,
		KND_Sync_Receiver_Taxonomy $taxonomy,
		KND_Sync_Receiver_Meta $meta
	) {
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->media    = $media;
		$this->taxonomy = $taxonomy;
		$this->meta     = $meta;
	}

	/**
	 * Upsert or preview a payload.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @param string               $request_id Request id.
	 * @param bool                 $dry_run Preview only.
	 * @return array<string, mixed>|WP_Error
	 */
	public function upsert( array $payload, string $request_id, bool $dry_run = false ): array|WP_Error {
		$validation = $this->validate_payload( $payload );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$source_site    = esc_url_raw( (string) $payload['source']['site'] );
		$source_post_id = (int) $payload['source']['post_id'];
		$post_data      = $payload['post'];
		$slug           = sanitize_title( (string) ( $post_data['slug'] ?? '' ) );
		$title          = sanitize_text_field( (string) ( $post_data['title'] ?? '' ) );

		$existing_id = $this->find_by_source( $source_site, $source_post_id );
		$created     = ( 0 === $existing_id );

		if ( $slug !== '' ) {
			$conflict = $this->slug_conflict( $slug, $existing_id );
			if ( is_wp_error( $conflict ) ) {
				return $conflict;
			}
		}

		$images_count = isset( $payload['images'] ) && is_array( $payload['images'] ) ? count( $payload['images'] ) : 0;
		$cats_count   = isset( $payload['categories'] ) && is_array( $payload['categories'] ) ? count( $payload['categories'] ) : 0;
		$tags_count   = isset( $payload['tags'] ) && is_array( $payload['tags'] ) ? count( $payload['tags'] ) : 0;

		if ( $dry_run ) {
			return array(
				'source_post_id' => $source_post_id,
				'target_post_id' => $existing_id,
				'status'         => $existing_id ? 'would_update' : 'would_create',
				'created'        => $created,
				'title'          => $title,
				'slug'           => $slug,
				'images'         => $images_count,
				'categories'     => $cats_count,
				'tags'           => $tags_count,
				'has_featured'   => ! empty( $payload['featured_image']['url'] ),
			);
		}

		if ( ! defined( 'KND_SYNC_RECEIVING' ) ) {
			define( 'KND_SYNC_RECEIVING', true );
		}

		$content = (string) ( $post_data['content'] ?? '' );
		$url_map = array();

		// Import content images first so we can rewrite URLs before save.
		if ( (int) $this->settings->get( 'sync_content_images', 1 ) && ! empty( $payload['images'] ) && is_array( $payload['images'] ) ) {
			foreach ( $payload['images'] as $image ) {
				if ( ! is_array( $image ) ) {
					continue;
				}
				$ensured = $this->media->ensure( $image, $source_site, $existing_id, $request_id );
				if ( is_wp_error( $ensured ) ) {
					$this->logger->warning(
						'content_image_failed',
						$ensured->get_error_message(),
						array(
							'request_id'     => $request_id,
							'source_post_id' => $source_post_id,
							'target_post_id' => $existing_id,
						)
					);
					continue;
				}
				$src = (string) ( $image['url'] ?? '' );
				if ( $src !== '' ) {
					$url_map[ $src ] = $ensured['url'];
				}
			}
			$content = $this->media->rewrite_content_urls( $content, $url_map );
		}

		$status = sanitize_key( (string) ( $post_data['status'] ?? 'draft' ) );
		if ( (int) $this->settings->get( 'force_draft', 0 ) ) {
			$status = 'draft';
		}
		if ( ! in_array( $status, array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) ) {
			$status = 'draft';
		}

		$author = $this->resolve_author( $payload );

		$postarr = array(
			'post_type'    => 'post',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_excerpt' => wp_kses_post( (string) ( $post_data['excerpt'] ?? '' ) ),
			'post_status'  => $status,
			'post_author'  => $author,
		);

		if ( (int) $this->settings->get( 'preserve_dates', 1 ) ) {
			if ( ! empty( $post_data['date_gmt'] ) ) {
				$postarr['post_date_gmt'] = $this->normalize_gmt_datetime( (string) $post_data['date_gmt'] );
				$postarr['post_date']     = get_date_from_gmt( $postarr['post_date_gmt'] );
			} elseif ( ! empty( $post_data['date'] ) ) {
				$postarr['post_date'] = sanitize_text_field( (string) $post_data['date'] );
			}
			if ( ! empty( $post_data['modified_gmt'] ) ) {
				$postarr['post_modified_gmt'] = $this->normalize_gmt_datetime( (string) $post_data['modified_gmt'] );
				$postarr['post_modified']     = get_date_from_gmt( $postarr['post_modified_gmt'] );
			}
		}

		if ( $existing_id > 0 ) {
			// Guard Elementor overwrite.
			if ( ! (int) $this->settings->get( 'sync_elementor', 0 ) ) {
				$edit_mode = get_post_meta( $existing_id, '_elementor_edit_mode', true );
				if ( $edit_mode === 'builder' ) {
					return new WP_Error(
						'ELEMENTOR_PROTECTED',
						__( 'Target post is Elementor-built; enable Elementor sync to overwrite.', 'knd-sync' ),
						array( 'status' => 409 )
					);
				}
			}
			$postarr['ID'] = $existing_id;
			$target_id     = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$target_id = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $target_id ) ) {
			return new WP_Error( 'POST_SAVE_FAILED', $target_id->get_error_message(), array( 'status' => 500 ) );
		}

		$target_id = (int) $target_id;

		if ( (int) $this->settings->get( 'sync_categories', 1 ) && ! empty( $payload['categories'] ) && is_array( $payload['categories'] ) ) {
			$cat_ids = $this->taxonomy->map_terms( 'category', $payload['categories'], $request_id );
			wp_set_post_terms( $target_id, $cat_ids, 'category', false );
		}

		if ( (int) $this->settings->get( 'sync_tags', 1 ) && ! empty( $payload['tags'] ) && is_array( $payload['tags'] ) ) {
			$tag_ids = $this->taxonomy->map_terms( 'post_tag', $payload['tags'], $request_id );
			wp_set_post_terms( $target_id, $tag_ids, 'post_tag', false );
		}

		if ( (int) $this->settings->get( 'sync_featured_image', 1 ) && ! empty( $payload['featured_image'] ) && is_array( $payload['featured_image'] ) ) {
			$featured = $this->media->ensure( $payload['featured_image'], $source_site, $target_id, $request_id );
			if ( ! is_wp_error( $featured ) ) {
				set_post_thumbnail( $target_id, $featured['id'] );
			} else {
				$this->logger->warning(
					'featured_image_failed',
					$featured->get_error_message(),
					array(
						'request_id'     => $request_id,
						'source_post_id' => $source_post_id,
						'target_post_id' => $target_id,
					)
				);
			}
		}

		$this->meta->apply( $target_id, $payload, $request_id );

		return array(
			'source_post_id' => $source_post_id,
			'target_post_id' => $target_id,
			'status'         => 'synced',
			'created'        => $created,
		);
	}

	/**
	 * Validate payload schema basics.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return true|WP_Error
	 */
	private function validate_payload( array $payload ): true|WP_Error {
		if ( empty( $payload['schema_version'] ) ) {
			return new WP_Error( 'INVALID_PAYLOAD', __( 'schema_version is required.', 'knd-sync' ), array( 'status' => 400 ) );
		}
		if ( empty( $payload['source']['site'] ) || empty( $payload['source']['post_id'] ) ) {
			return new WP_Error( 'INVALID_PAYLOAD', __( 'source.site and source.post_id are required.', 'knd-sync' ), array( 'status' => 400 ) );
		}
		if ( empty( $payload['post'] ) || ! is_array( $payload['post'] ) ) {
			return new WP_Error( 'INVALID_PAYLOAD', __( 'post object is required.', 'knd-sync' ), array( 'status' => 400 ) );
		}
		if ( empty( $payload['post']['title'] ) ) {
			return new WP_Error( 'INVALID_PAYLOAD', __( 'post.title is required.', 'knd-sync' ), array( 'status' => 422 ) );
		}

		$source_host = strtolower( (string) ( wp_parse_url( (string) $payload['source']['site'], PHP_URL_HOST ) ?? '' ) );
		$allowed     = (array) $this->settings->get( 'allowed_source_hosts', array() );
		$allowed_norm = array_map(
			static function ( $h ) {
				return strtolower( (string) $h );
			},
			$allowed
		);

		if ( $allowed_norm && $source_host && ! in_array( $source_host, $allowed_norm, true ) ) {
			return new WP_Error( 'SOURCE_NOT_ALLOWED', __( 'Source site host is not allowed.', 'knd-sync' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Find target post by source identity meta.
	 *
	 * @param string $source_site Source site.
	 * @param int    $source_post_id Source post ID.
	 */
	private function find_by_source( string $source_site, int $source_post_id ): int {
		$query = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_knd_sync_source_post_id',
						'value' => $source_post_id,
						'type'  => 'NUMERIC',
					),
					array(
						'key'   => '_knd_sync_source_site',
						'value' => $source_site,
					),
				),
			)
		);

		if ( ! empty( $query->posts[0] ) ) {
			return (int) $query->posts[0];
		}

		// Fallback: match by source_post_id only if unique.
		$query2 = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'any',
				'posts_per_page'         => 2,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_knd_sync_source_post_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => $source_post_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		if ( count( $query2->posts ) === 1 ) {
			return (int) $query2->posts[0];
		}

		return 0;
	}

	/**
	 * Detect slug conflict with unrelated posts.
	 *
	 * @param string $slug Slug.
	 * @param int    $existing_id Current synced post ID (0 if new).
	 * @return true|WP_Error
	 */
	private function slug_conflict( string $slug, int $existing_id ): true|WP_Error {
		$found = get_page_by_path( $slug, OBJECT, 'post' );
		if ( ! $found ) {
			return true;
		}
		if ( $existing_id > 0 && (int) $found->ID === $existing_id ) {
			return true;
		}

		// If the conflicting post is itself a synced post for another source, still conflict.
		return new WP_Error(
			'SLUG_CONFLICT',
			sprintf(
				/* translators: %s: post slug */
				__( 'Slug "%s" already exists on a different post.', 'knd-sync' ),
				$slug
			),
			array( 'status' => 409 )
		);
	}

	/**
	 * Resolve author with mapping/fallback.
	 *
	 * @param array<string, mixed> $payload Payload.
	 */
	private function resolve_author( array $payload ): int {
		$fallback = (int) $this->settings->get( 'fallback_author', 0 );
		if ( $fallback <= 0 ) {
			$fallback = 1;
		}

		$map = apply_filters( 'knd_sync_receiver_author_map', array() );
		$source_author_id = (int) ( $payload['author']['id'] ?? 0 );

		if ( $source_author_id > 0 && is_array( $map ) && isset( $map[ $source_author_id ] ) ) {
			$mapped = (int) $map[ $source_author_id ];
			if ( $mapped > 0 && get_user_by( 'id', $mapped ) ) {
				return $mapped;
			}
		}

		if ( ! empty( $payload['author']['login'] ) ) {
			$user = get_user_by( 'login', sanitize_user( (string) $payload['author']['login'] ) );
			if ( $user ) {
				return (int) $user->ID;
			}
		}

		return get_user_by( 'id', $fallback ) ? $fallback : 1;
	}

	/**
	 * Normalize GMT datetime strings to MySQL format.
	 *
	 * @param string $value Datetime.
	 */
	private function normalize_gmt_datetime( string $value ): string {
		$ts = strtotime( $value );
		if ( false === $ts ) {
			return current_time( 'mysql', true );
		}
		return gmdate( 'Y-m-d H:i:s', $ts );
	}
}
