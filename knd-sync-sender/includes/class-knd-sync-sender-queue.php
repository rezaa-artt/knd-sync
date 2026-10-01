<?php
/**
 * Background queue with retry/backoff.
 *
 * Prefers Action Scheduler when available; always persists jobs in custom table.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job queue.
 */
final class KND_Sync_Sender_Queue {

	/**
	 * @var KND_Sync_Sender_Settings
	 */
	private KND_Sync_Sender_Settings $settings;

	/**
	 * @var KND_Sync_Sender_Logger
	 */
	private KND_Sync_Sender_Logger $logger;

	/**
	 * @var KND_Sync_Sender_Post
	 */
	private KND_Sync_Sender_Post $post;

	/**
	 * @var KND_Sync_Sender_API_Client
	 */
	private KND_Sync_Sender_API_Client $client;

	/**
	 * Constructor.
	 *
	 * @param KND_Sync_Sender_Settings   $settings Settings.
	 * @param KND_Sync_Sender_Logger     $logger Logger.
	 * @param KND_Sync_Sender_Post       $post Post.
	 * @param KND_Sync_Sender_API_Client $client Client.
	 */
	public function __construct(
		KND_Sync_Sender_Settings $settings,
		KND_Sync_Sender_Logger $logger,
		KND_Sync_Sender_Post $post,
		KND_Sync_Sender_API_Client $client
	) {
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->post     = $post;
		$this->client   = $client;
	}

	/**
	 * Register cron/AS handlers.
	 */
	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'knd_sync_sender_process_queue', array( $this, 'process_due' ) );
		add_action( 'knd_sync_sender_run_job', array( $this, 'run_job' ), 10, 1 );
	}

	/**
	 * Add one-minute schedule.
	 *
	 * @param array<string, array<string, mixed>> $schedules Schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['knd_sync_every_minute'] ) ) {
			$schedules['knd_sync_every_minute'] = array(
				'interval' => 60,
				'display'  => __( 'Every Minute (KND Sync)', 'knd-sync' ),
			);
		}
		return $schedules;
	}

	/**
	 * Enqueue a sync job for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int|WP_Error Job ID.
	 */
	public function enqueue( int $post_id ): int|WP_Error {
		$eligible = $this->post->is_eligible( $post_id );
		if ( is_wp_error( $eligible ) ) {
			if ( $eligible->get_error_code() === 'DISABLED' ) {
				$this->post->set_status( $post_id, 'disabled' );
			}
			return $eligible;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'knd_sync_jobs';

		// Avoid duplicate pending jobs for same post.
		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE post_id = %d AND status IN ('pending','processing') ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
				$post_id
			)
		);
		if ( $existing > 0 ) {
			return $existing;
		}

		$request_id = wp_generate_uuid4();
		$now        = current_time( 'mysql', true );
		$max        = max( 1, (int) $this->settings->get( 'retry_count', 3 ) );

		$wpdb->insert(
			$table,
			array(
				'post_id'       => $post_id,
				'action'        => 'sync_post',
				'status'        => 'pending',
				'attempts'      => 0,
				'max_attempts'  => $max,
				'request_id'    => $request_id,
				'payload'       => null,
				'last_error'    => null,
				'available_at'  => $now,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$job_id = (int) $wpdb->insert_id;
		$this->post->set_status( $post_id, 'pending', array( 'request_id' => $request_id ) );

		$this->logger->info(
			'job_enqueued',
			__( 'Sync job enqueued.', 'knd-sync' ),
			array(
				'request_id'     => $request_id,
				'source_post_id' => $post_id,
			)
		);

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'knd_sync_sender_run_job', array( $job_id ), 'knd-sync' );
		} else {
			// Process soon via WP-Cron.
			wp_schedule_single_event( time() + 5, 'knd_sync_sender_process_queue' );
		}

		return $job_id;
	}

	/**
	 * Process due jobs (cron).
	 */
	public function process_due(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'knd_sync_jobs';
		$now   = current_time( 'mysql', true );

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE status = 'pending' AND available_at <= %s ORDER BY id ASC LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL
				$now
			)
		);

		foreach ( (array) $ids as $id ) {
			$this->run_job( (int) $id );
		}
	}

	/**
	 * Run one job.
	 *
	 * @param int $job_id Job ID.
	 */
	public function run_job( int $job_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'knd_sync_jobs';

		$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $job_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( ! $job || ! in_array( (string) $job->status, array( 'pending', 'processing' ), true ) ) {
			return;
		}

		$post_id    = (int) $job->post_id;
		$request_id = (string) $job->request_id;

		$wpdb->update(
			$table,
			array(
				'status'     => 'processing',
				'attempts'   => (int) $job->attempts + 1,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $job_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		$this->post->set_status( $post_id, 'syncing', array( 'request_id' => $request_id ) );

		if ( (int) $this->settings->get( 'test_mode', 0 ) === 1 && (string) $this->settings->get( 'api_key', '' ) === '' ) {
			// Dry local test mode without remote: mark as failed with guidance.
			$this->fail_job( $job_id, $post_id, $request_id, (int) $job->attempts + 1, (int) $job->max_attempts, __( 'Test mode: configure API key / target to send.', 'knd-sync' ) );
			return;
		}

		$built = $this->post->build_payload( $post_id );
		if ( is_wp_error( $built ) ) {
			$this->fail_job( $job_id, $post_id, $request_id, (int) $job->attempts + 1, (int) $job->max_attempts, $built->get_error_message(), false );
			return;
		}

		$result = $this->client->sync_post( $built['payload'], $request_id );
		if ( is_wp_error( $result ) ) {
			$retryable = $this->is_retryable( $result );
			$this->fail_job(
				$job_id,
				$post_id,
				$request_id,
				(int) $job->attempts + 1,
				(int) $job->max_attempts,
				$result->get_error_message(),
				$retryable
			);
			return;
		}

		$target_id = (int) ( $result['target_post_id'] ?? 0 );
		$wpdb->update(
			$table,
			array(
				'status'     => 'completed',
				'updated_at' => current_time( 'mysql', true ),
				'last_error' => null,
			),
			array( 'id' => $job_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		$this->post->set_status(
			$post_id,
			'synced',
			array(
				'target_post_id' => $target_id,
				'request_id'     => $request_id,
			)
		);

		$this->logger->success(
			'sync_success',
			__( 'Post synchronized.', 'knd-sync' ),
			array(
				'request_id'     => $request_id,
				'source_post_id' => $post_id,
				'target_post_id' => $target_id,
				'response_code'  => (int) ( $result['response_code'] ?? 200 ),
			)
		);
	}

	/**
	 * Mark failure / schedule retry.
	 *
	 * @param int    $job_id Job ID.
	 * @param int    $post_id Post ID.
	 * @param string $request_id Request id.
	 * @param int    $attempts Attempts.
	 * @param int    $max_attempts Max.
	 * @param string $error Error.
	 * @param bool   $retryable Retryable.
	 */
	private function fail_job(
		int $job_id,
		int $post_id,
		string $request_id,
		int $attempts,
		int $max_attempts,
		string $error,
		bool $retryable = true
	): void {
		global $wpdb;
		$table = $wpdb->prefix . 'knd_sync_jobs';

		if ( $retryable && $attempts < $max_attempts ) {
			$delay = 60 * ( $attempts * $attempts ); // 60, 240, 540...
			$available = gmdate( 'Y-m-d H:i:s', time() + $delay );
			$wpdb->update(
				$table,
				array(
					'status'       => 'pending',
					'last_error'   => $error,
					'available_at' => $available,
					'updated_at'   => current_time( 'mysql', true ),
				),
				array( 'id' => $job_id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			$this->post->set_status( $post_id, 'pending', array( 'error' => $error, 'request_id' => $request_id ) );
			$this->logger->warning(
				'sync_retry_scheduled',
				$error,
				array(
					'request_id'     => $request_id,
					'source_post_id' => $post_id,
					'error'          => $error,
				)
			);
			return;
		}

		$wpdb->update(
			$table,
			array(
				'status'     => 'failed',
				'last_error' => $error,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $job_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		$this->post->set_status( $post_id, 'failed', array( 'error' => $error, 'request_id' => $request_id ) );
		$this->logger->error(
			'sync_failed',
			$error,
			array(
				'request_id'     => $request_id,
				'source_post_id' => $post_id,
				'error'          => $error,
			)
		);
	}

	/**
	 * Whether an error should be retried.
	 *
	 * @param WP_Error $error Error.
	 */
	private function is_retryable( WP_Error $error ): bool {
		$code = $error->get_error_code();
		if ( in_array( $code, array( 'TARGET_UNAVAILABLE', 'http_request_failed' ), true ) ) {
			return true;
		}
		$data = $error->get_error_data();
		$status = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
		return in_array( $status, array( 408, 429, 500, 502, 503, 504 ), true );
	}

	/**
	 * Re-queue a failed job by post id.
	 *
	 * @param int $post_id Post ID.
	 * @return int|WP_Error
	 */
	public function retry_post( int $post_id ): int|WP_Error {
		return $this->enqueue( $post_id );
	}
}
