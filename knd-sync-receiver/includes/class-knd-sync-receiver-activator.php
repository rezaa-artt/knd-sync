<?php
/**
 * Activation / deactivation routines.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles schema install and default options.
 */
final class KND_Sync_Receiver_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		KND_Sync_Receiver_Schema::install();
		KND_Sync_Receiver_Settings::ensure_defaults();
		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'knd_sync_receiver_prune_logs' );
		flush_rewrite_rules();
	}
}
