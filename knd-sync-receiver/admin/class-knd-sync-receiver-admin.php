<?php
/**
 * Receiver admin UI.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings and logs screens.
 */
final class KND_Sync_Receiver_Admin {

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
	 * Register admin hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Add settings pages.
	 */
	public function menu(): void {
		add_options_page(
			__( 'KND Sync Receiver', 'knd-sync' ),
			__( 'KND Sync', 'knd-sync' ),
			'manage_options',
			'knd-sync-receiver',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'options-general.php',
			__( 'KND Sync Logs', 'knd-sync' ),
			__( 'KND Sync Logs', 'knd-sync' ),
			'manage_options',
			'knd-sync-receiver-logs',
			array( $this, 'render_logs' )
		);
	}

	/**
	 * Enqueue assets on plugin pages.
	 *
	 * @param string $hook Hook.
	 */
	public function assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'settings_page_knd-sync-receiver', 'settings_page_knd-sync-receiver-logs' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'knd-sync-receiver-admin',
			KND_SYNC_RECEIVER_URL . 'admin/assets/admin.css',
			array(),
			KND_SYNC_RECEIVER_VERSION
		);
		wp_enqueue_script(
			'knd-sync-receiver-admin',
			KND_SYNC_RECEIVER_URL . 'admin/assets/admin.js',
			array(),
			KND_SYNC_RECEIVER_VERSION,
			true
		);
	}

	/**
	 * Handle settings save / regenerate / clear logs.
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['knd_sync_receiver_save'] ) ) {
			check_admin_referer( 'knd_sync_receiver_settings' );
			$input = isset( $_POST['knd_sync_receiver'] ) && is_array( $_POST['knd_sync_receiver'] )
				? wp_unslash( $_POST['knd_sync_receiver'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				: array();
			$this->settings->update( $this->settings->sanitize( $input, true ) );
			add_settings_error( 'knd_sync_receiver', 'saved', __( 'Settings saved.', 'knd-sync' ), 'updated' );
		}

		if ( isset( $_POST['knd_sync_receiver_regen_key'] ) ) {
			check_admin_referer( 'knd_sync_receiver_settings' );
			$this->settings->update(
				array(
					'api_key' => wp_generate_password( 48, false, false ),
				)
			);
			add_settings_error( 'knd_sync_receiver', 'key', __( 'API key regenerated.', 'knd-sync' ), 'updated' );
		}

		if ( isset( $_POST['knd_sync_receiver_regen_hmac'] ) ) {
			check_admin_referer( 'knd_sync_receiver_settings' );
			$this->settings->update(
				array(
					'hmac_secret' => wp_generate_password( 64, false, false ),
				)
			);
			add_settings_error( 'knd_sync_receiver', 'hmac', __( 'HMAC secret regenerated.', 'knd-sync' ), 'updated' );
		}

		if ( isset( $_POST['knd_sync_receiver_clear_logs'] ) ) {
			check_admin_referer( 'knd_sync_receiver_logs' );
			$this->logger->clear_all();
			add_settings_error( 'knd_sync_receiver', 'logs', __( 'Logs cleared.', 'knd-sync' ), 'updated' );
		}
	}

	/**
	 * Settings view.
	 */
	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = $this->settings->all();
		include KND_SYNC_RECEIVER_PATH . 'admin/views/settings.php';
	}

	/**
	 * Logs view.
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
		include KND_SYNC_RECEIVER_PATH . 'admin/views/logs.php';
	}
}
