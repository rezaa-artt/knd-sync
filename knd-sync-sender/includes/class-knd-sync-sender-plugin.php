<?php
/**
 * Main sender plugin.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires hooks and services.
 */
final class KND_Sync_Sender_Plugin {

	/**
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * @var KND_Sync_Sender_Queue|null
	 */
	private ?KND_Sync_Sender_Queue $queue = null;

	/**
	 * Singleton.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Init.
	 */
	public function init(): void {
		load_plugin_textdomain( 'knd-sync', false, dirname( KND_SYNC_SENDER_BASENAME ) . '/languages' );

		if ( (string) get_option( 'knd_sync_sender_db_version', '' ) !== KND_Sync_Sender_Schema::DB_VERSION ) {
			KND_Sync_Sender_Schema::install();
		}

		$settings = new KND_Sync_Sender_Settings();
		$logger   = new KND_Sync_Sender_Logger();
		$auth     = new KND_Sync_Sender_Auth( $settings );
		$client   = new KND_Sync_Sender_API_Client( $settings, $auth, $logger );
		$media    = new KND_Sync_Sender_Media();
		$taxonomy = new KND_Sync_Sender_Taxonomy();
		$post     = new KND_Sync_Sender_Post( $settings, $logger, $media, $taxonomy );
		$queue    = new KND_Sync_Sender_Queue( $settings, $logger, $post, $client );
		$queue->register();
		$this->queue = $queue;

		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		add_action( 'save_post_post', array( $this, 'on_save' ), 20, 3 );

		add_action( 'knd_sync_sender_prune_logs', array( $logger, 'prune' ) );
		if ( ! wp_next_scheduled( 'knd_sync_sender_prune_logs' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'knd_sync_sender_prune_logs' );
		}

		if ( ! wp_next_scheduled( 'knd_sync_sender_process_queue' ) ) {
			wp_schedule_event( time() + 60, 'knd_sync_every_minute', 'knd_sync_sender_process_queue' );
		}

		if ( is_admin() ) {
			$admin = new KND_Sync_Sender_Admin( $settings, $logger, $post, $queue, $client );
			$admin->register();
		}
	}

	/**
	 * Publish transition → enqueue.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post Post.
	 */
	public function on_transition( string $new_status, string $old_status, WP_Post $post ): void {
		if ( $post->post_type !== 'post' ) {
			return;
		}
		if ( defined( 'KND_SYNC_RECEIVING' ) && KND_SYNC_RECEIVING ) {
			return;
		}

		$settings = new KND_Sync_Sender_Settings();
		if ( ! (int) $settings->get( 'enabled', 0 ) ) {
			return;
		}

		if ( $new_status === 'publish' && $old_status !== 'publish' && (int) $settings->get( 'sync_on_publish', 1 ) ) {
			$this->safe_enqueue( (int) $post->ID );
		}
	}

	/**
	 * Update of already-published post → enqueue.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post.
	 * @param bool    $update Update flag.
	 */
	public function on_save( int $post_id, WP_Post $post, bool $update ): void {
		if ( ! $update || $post->post_type !== 'post' || $post->post_status !== 'publish' ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( defined( 'KND_SYNC_RECEIVING' ) && KND_SYNC_RECEIVING ) {
			return;
		}

		$settings = new KND_Sync_Sender_Settings();
		if ( ! (int) $settings->get( 'enabled', 0 ) || ! (int) $settings->get( 'sync_on_update', 1 ) ) {
			return;
		}

		// Avoid double-enqueue when transition_post_status already handled first publish.
		// Only sync updates when already synced or previously attempted.
		$status = (string) get_post_meta( $post_id, '_knd_sync_status', true );
		if ( in_array( $status, array( '', 'not_synced' ), true ) ) {
			// First publish is handled by transition; skip here.
			return;
		}

		$this->safe_enqueue( $post_id );
	}

	/**
	 * Enqueue without throwing.
	 *
	 * @param int $post_id Post ID.
	 */
	private function safe_enqueue( int $post_id ): void {
		if ( ! $this->queue ) {
			return;
		}
		try {
			$this->queue->enqueue( $post_id );
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			$logger = new KND_Sync_Sender_Logger();
			$logger->error(
				'enqueue_exception',
				$e->getMessage(),
				array( 'source_post_id' => $post_id )
			);
		}
	}
}
