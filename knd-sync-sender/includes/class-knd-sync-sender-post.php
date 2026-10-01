<?php
/**
 * Payload builder and sync orchestration for a single post.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds versioned payloads and applies local status meta.
 */
final class KND_Sync_Sender_Post {

	/**
	 * @var KND_Sync_Sender_Settings
	 */
	private KND_Sync_Sender_Settings $settings;

	/**
	 * @var KND_Sync_Sender_Logger
	 */
	private KND_Sync_Sender_Logger $logger;

	/**
	 * @var KND_Sync_Sender_Media
	 */
	private KND_Sync_Sender_Media $media;

	/**
	 * @var KND_Sync_Sender_Taxonomy
	 */
	private KND_Sync_Sender_Taxonomy $taxonomy;

	/**
	 * Constructor.
	 *
	 * @param KND_Sync_Sender_Settings $settings Settings.
	 * @param KND_Sync_Sender_Logger   $logger Logger.
	 * @param KND_Sync_Sender_Media    $media Media.
	 * @param KND_Sync_Sender_Taxonomy $taxonomy Taxonomy.
	 */
	public function __construct(
		KND_Sync_Sender_Settings $settings,
		KND_Sync_Sender_Logger $logger,
		KND_Sync_Sender_Media $media,
		KND_Sync_Sender_Taxonomy $taxonomy
	) {
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->media    = $media;
		$this->taxonomy = $taxonomy;
	}

	/**
	 * Whether a post is eligible for sync.
	 *
	 * @param int $post_id Post ID.
	 */
	public function is_eligible( int $post_id ): bool|WP_Error {
		if ( defined( 'KND_SYNC_RECEIVING' ) && KND_SYNC_RECEIVING ) {
			return new WP_Error( 'LOOP_PREVENTION', __( 'Skipped: currently receiving a sync.', 'knd-sync' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'post' ) {
			return new WP_Error( 'UNSUPPORTED_TYPE', __( 'Only WordPress posts are supported in v1.', 'knd-sync' ) );
		}

		if ( (int) get_post_meta( $post_id, '_knd_sync_disabled', true ) === 1 ) {
			return new WP_Error( 'DISABLED', __( 'Post is excluded from sync.', 'knd-sync' ) );
		}

		if ( get_post_meta( $post_id, '_knd_sync_origin', true ) ) {
			return new WP_Error( 'LOOP_PREVENTION', __( 'Post originated from sync; outbound sync skipped.', 'knd-sync' ) );
		}

		return true;
	}

	/**
	 * Build payload.
	 *
	 * @param int $post_id Post ID.
	 * @return array{payload: array<string, mixed>, preview: array<string, mixed>}|WP_Error
	 */
	public function build_payload( int $post_id ): array|WP_Error {
		$eligible = $this->is_eligible( $post_id );
		if ( is_wp_error( $eligible ) ) {
			return $eligible;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'NOT_FOUND', __( 'Post not found.', 'knd-sync' ) );
		}

		$content = (string) $post->post_content;
		$link_stats = array(
			'found'     => 0,
			'rewritten' => 0,
			'skipped'   => 0,
		);

		if ( (int) $this->settings->get( 'rewrite_internal_links', 1 ) ) {
			$rewriter = new KND_Sync_Sender_Link_Rewriter(
				(array) $this->settings->get( 'source_hosts', array() ),
				(string) $this->settings->get( 'target_host', 'knd-home.com' ),
				'https'
			);
			$content    = $rewriter->rewrite_html( $content );
			$link_stats = $rewriter->get_stats();
		}

		$status = (string) $post->post_status;
		if ( (int) $this->settings->get( 'sync_as_draft', 0 ) || (int) $this->settings->get( 'test_mode', 0 ) ) {
			$status = 'draft';
		}

		$featured = array();
		if ( (int) $this->settings->get( 'sync_featured_image', 1 ) ) {
			$featured = $this->media->featured_image( $post_id );
		}

		$images = array();
		if ( (int) $this->settings->get( 'sync_content_images', 1 ) ) {
			$images = $this->media->content_images( $post_id, (string) $post->post_content );
		}

		$categories = (int) $this->settings->get( 'sync_categories', 1 )
			? $this->taxonomy->export( $post_id, 'category' )
			: array();
		$tags       = (int) $this->settings->get( 'sync_tags', 1 )
			? $this->taxonomy->export( $post_id, 'post_tag' )
			: array();

		$author = get_user_by( 'id', (int) $post->post_author );
		$seo    = array();
		if ( (int) $this->settings->get( 'sync_seo_meta', 1 ) ) {
			$adapter = new KND_Sync_Sender_SEO_RankMath();
			$seo     = $adapter->export(
				$post_id,
				(bool) (int) $this->settings->get( 'sync_seo_canonical', 0 )
			);
		}

		$payload = array(
			'schema_version' => '1.0',
			'source'         => array(
				'site'    => home_url( '/' ),
				'post_id' => $post_id,
				'url'     => get_permalink( $post_id ),
			),
			'post'           => array(
				'title'        => (string) $post->post_title,
				'slug'         => (string) $post->post_name,
				'content'      => $content,
				'excerpt'      => (string) $post->post_excerpt,
				'status'       => $status,
				'date'         => (string) $post->post_date,
				'date_gmt'     => (string) $post->post_date_gmt,
				'modified'     => (string) $post->post_modified,
				'modified_gmt' => (string) $post->post_modified_gmt,
			),
			'author'         => array(
				'id'   => (int) $post->post_author,
				'name' => $author ? (string) $author->display_name : '',
				'login'=> $author ? (string) $author->user_login : '',
			),
			'categories'     => $categories,
			'tags'           => $tags,
			'featured_image' => $featured,
			'images'         => $images,
			'meta'           => array(
				'seo' => $seo,
			),
		);

		$preview = array(
			'source'          => get_permalink( $post_id ),
			'target'          => trailingslashit( (string) $this->settings->get( 'target_url', '' ) ) . (string) $post->post_name . '/',
			'title'           => (string) $post->post_title,
			'images'          => count( $images ),
			'has_featured'    => ! empty( $featured ),
			'categories'      => count( $categories ),
			'tags'            => count( $tags ),
			'internal_links'  => $link_stats,
			'status_to_send'  => $status,
			'target_post_id'  => (int) get_post_meta( $post_id, '_knd_sync_target_post_id', true ),
			'would'           => (int) get_post_meta( $post_id, '_knd_sync_target_post_id', true ) > 0 ? 'update' : 'create',
		);

		return array(
			'payload' => $payload,
			'preview' => $preview,
		);
	}

	/**
	 * Mark local sync status.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $status Status.
	 * @param array<string, mixed> $extra Extra meta.
	 */
	public function set_status( int $post_id, string $status, array $extra = array() ): void {
		update_post_meta( $post_id, '_knd_sync_status', sanitize_key( $status ) );
		if ( isset( $extra['target_post_id'] ) ) {
			update_post_meta( $post_id, '_knd_sync_target_post_id', (int) $extra['target_post_id'] );
		}
		if ( isset( $extra['request_id'] ) ) {
			update_post_meta( $post_id, '_knd_sync_request_id', sanitize_text_field( (string) $extra['request_id'] ) );
		}
		if ( isset( $extra['error'] ) ) {
			update_post_meta( $post_id, '_knd_sync_last_error', sanitize_text_field( (string) $extra['error'] ) );
		} elseif ( $status === 'synced' ) {
			delete_post_meta( $post_id, '_knd_sync_last_error' );
		}
		if ( $status === 'synced' ) {
			update_post_meta( $post_id, '_knd_sync_last_sync', current_time( 'mysql', true ) );
		}
	}
}
