<?php
/**
 * Main receiver plugin bootstrap.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires components together.
 */
final class KND_Sync_Receiver_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize hooks.
	 */
	public function init(): void {
		load_plugin_textdomain( 'knd-sync', false, dirname( KND_SYNC_RECEIVER_BASENAME ) . '/languages' );

		if ( (string) get_option( 'knd_sync_receiver_db_version', '' ) !== KND_Sync_Receiver_Schema::DB_VERSION ) {
			KND_Sync_Receiver_Schema::install();
		}

		$settings = new KND_Sync_Receiver_Settings();
		$logger   = new KND_Sync_Receiver_Logger();
		$auth     = new KND_Sync_Receiver_Auth( $settings );
		$media    = new KND_Sync_Receiver_Media( $settings, $logger );
		$taxonomy = new KND_Sync_Receiver_Taxonomy( $settings, $logger );
		$meta     = new KND_Sync_Receiver_Meta( $settings, $logger );
		$post     = new KND_Sync_Receiver_Post( $settings, $logger, $media, $taxonomy, $meta );
		$rest     = new KND_Sync_Receiver_REST( $settings, $logger, $auth, $post );

		$rest->register();

		if ( is_admin() ) {
			$admin = new KND_Sync_Receiver_Admin( $settings, $logger );
			$admin->register();
		}

		add_action( 'knd_sync_receiver_prune_logs', array( $logger, 'prune' ) );
		if ( ! wp_next_scheduled( 'knd_sync_receiver_prune_logs' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'knd_sync_receiver_prune_logs' );
		}
	}
}
