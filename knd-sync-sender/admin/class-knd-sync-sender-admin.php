<?php
/**
 * Sender admin UI + metabox.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin pages and AJAX.
 */
final class KND_Sync_Sender_Admin {

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
	 * @var KND_Sync_Sender_Queue
	 */
	private KND_Sync_Sender_Queue $queue;

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
	 * @param KND_Sync_Sender_Queue      $queue Queue.
	 * @param KND_Sync_Sender_API_Client $client Client.
	 */
	public function __construct(
		KND_Sync_Sender_Settings $settings,
		KND_Sync_Sender_Logger $logger,
		KND_Sync_Sender_Post $post,
		KND_Sync_Sender_Queue $queue,
		KND_Sync_Sender_API_Client $client
	) {
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->post     = $post;
		$this->queue    = $queue;
		$this->client   = $client;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'metabox' ) );
		add_action( 'save_post_post', array( $this, 'save_metabox' ), 10, 2 );
		add_action( 'wp_ajax_knd_sync_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_knd_sync_manual_sync', array( $this, 'ajax_manual_sync' ) );
		add_action( 'wp_ajax_knd_sync_preview', array( $this, 'ajax_preview' ) );
	}

	/**
	 * Menus.
	 */
	public function menu(): void {
		add_options_page(
			__( 'KND Sync', 'knd-sync' ),
			__( 'KND Sync', 'knd-sync' ),
			'manage_options',
			'knd-sync-sender',
			array( $this, 'render_settings' )
		);
		add_submenu_page(
			'options-general.php',
			__( 'KND Sync Logs', 'knd-sync' ),
			__( 'KND Sync Logs', 'knd-sync' ),
			'manage_options',
			'knd-sync-sender-logs',
			array( $this, 'render_logs' )
		);
	}

	/**
	 * Assets.
	 *
	 * @param string $hook Hook.
	 */
	public function assets( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$load   = in_array( $hook, array( 'settings_page_knd-sync-sender', 'settings_page_knd-sync-sender-logs' ), true )
			|| ( $screen && $screen->base === 'post' && $screen->post_type === 'post' );

		if ( ! $load ) {
			return;
		}

		wp_enqueue_style( 'knd-sync-sender-admin', KND_SYNC_SENDER_URL . 'admin/assets/admin.css', array(), KND_SYNC_SENDER_VERSION );
		wp_enqueue_script( 'knd-sync-sender-admin', KND_SYNC_SENDER_URL . 'admin/assets/admin.js', array( 'jquery' ), KND_SYNC_SENDER_VERSION, true );
		wp_localize_script(
			'knd-sync-sender-admin',
			'kndSyncSender',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'knd_sync_sender_ajax' ),
				'i18n'    => array(
					'connected' => __( 'Connected ✓', 'knd-sync' ),
					'failed'    => __( 'Connection Failed ✕', 'knd-sync' ),
					'syncing'   => __( 'Syncing…', 'knd-sync' ),
				),
			)
		);
	}

	/**
	 * Form actions.
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['knd_sync_sender_save'] ) ) {
			check_admin_referer( 'knd_sync_sender_settings' );
			$input = isset( $_POST['knd_sync_sender'] ) && is_array( $_POST['knd_sync_sender'] )
				? wp_unslash( $_POST['knd_sync_sender'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				: array();
			$this->settings->update( $this->settings->sanitize( $input, true ) );
			add_settings_error( 'knd_sync_sender', 'saved', __( 'Settings saved.', 'knd-sync' ), 'updated' );
		}

		if ( isset( $_POST['knd_sync_sender_clear_logs'] ) ) {
			check_admin_referer( 'knd_sync_sender_logs' );
			$this->logger->clear_all();
			add_settings_error( 'knd_sync_sender', 'logs', __( 'Logs cleared.', 'knd-sync' ), 'updated' );
		}
	}

	/**
	 * Settings page.
	 */
	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = $this->settings->all();
		include KND_SYNC_SENDER_PATH . 'admin/views/settings.php';
	}

	/**
	 * Logs page.
	 */
	public function render_logs(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$level = isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['level'] ) ) : 'ALL'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query = $this->logger->query(
			array(
				'level' => $level,
				'page'  => $page,
			)
		);
		include KND_SYNC_SENDER_PATH . 'admin/views/logs.php';
	}

	/**
	 * Metabox.
	 */
	public function metabox(): void {
		add_meta_box(
			'knd_sync_sender_box',
			__( 'KND Sync', 'knd-sync' ),
			array( $this, 'render_metabox' ),
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Metabox HTML.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_metabox( WP_Post $post ): void {
		include KND_SYNC_SENDER_PATH . 'admin/views/post-sync.php';
	}

	/**
	 * Save exclude checkbox.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post.
	 */
	public function save_metabox( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['knd_sync_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['knd_sync_metabox_nonce'] ) ), 'knd_sync_metabox' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$disabled = isset( $_POST['knd_sync_disabled'] ) ? 1 : 0;
		update_post_meta( $post_id, '_knd_sync_disabled', $disabled );
		if ( $disabled ) {
			update_post_meta( $post_id, '_knd_sync_status', 'disabled' );
		} elseif ( (string) get_post_meta( $post_id, '_knd_sync_status', true ) === 'disabled' ) {
			update_post_meta( $post_id, '_knd_sync_status', 'not_synced' );
		}
		unset( $post );
	}

	/**
	 * AJAX: test connection.
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'knd_sync_sender_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'knd-sync' ) ), 403 );
		}

		$result = $this->client->status();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Connected ✓', 'knd-sync' ),
				'data'    => $result,
			)
		);
	}

	/**
	 * AJAX: manual sync.
	 */
	public function ajax_manual_sync(): void {
		check_ajax_referer( 'knd_sync_sender_ajax', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'knd-sync' ) ), 403 );
		}

		$job = $this->queue->enqueue( $post_id );
		if ( is_wp_error( $job ) ) {
			wp_send_json_error( array( 'message' => $job->get_error_message() ) );
		}

		// Attempt immediate processing for better UX.
		$this->queue->run_job( (int) $job );

		wp_send_json_success(
			array(
				'message' => __( 'Sync job processed.', 'knd-sync' ),
				'status'  => (string) get_post_meta( $post_id, '_knd_sync_status', true ),
				'target'  => (int) get_post_meta( $post_id, '_knd_sync_target_post_id', true ),
				'last'    => (string) get_post_meta( $post_id, '_knd_sync_last_sync', true ),
				'error'   => (string) get_post_meta( $post_id, '_knd_sync_last_error', true ),
			)
		);
	}

	/**
	 * AJAX: dry-run preview.
	 */
	public function ajax_preview(): void {
		check_ajax_referer( 'knd_sync_sender_ajax', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'knd-sync' ) ), 403 );
		}

		$built = $this->post->build_payload( $post_id );
		if ( is_wp_error( $built ) ) {
			wp_send_json_error( array( 'message' => $built->get_error_message() ) );
		}

		wp_send_json_success( array( 'preview' => $built['preview'] ) );
	}
}
