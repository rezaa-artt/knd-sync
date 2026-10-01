<?php
/**
 * Sender activation.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activator.
 */
final class KND_Sync_Sender_Activator {

	/**
	 * Activate.
	 */
	public static function activate(): void {
		KND_Sync_Sender_Schema::install();
		KND_Sync_Sender_Settings::ensure_defaults();

		// Register custom schedule before first cron event (filter may not be loaded yet).
		add_filter(
			'cron_schedules',
			static function ( array $schedules ): array {
				$schedules['knd_sync_every_minute'] = array(
					'interval' => 60,
					'display'  => 'Every Minute (KND Sync)',
				);
				return $schedules;
			}
		);

		if ( ! wp_next_scheduled( 'knd_sync_sender_process_queue' ) ) {
			wp_schedule_event( time() + 60, 'knd_sync_every_minute', 'knd_sync_sender_process_queue' );
		}
	}

	/**
	 * Deactivate.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'knd_sync_sender_process_queue' );
		wp_clear_scheduled_hook( 'knd_sync_sender_prune_logs' );
	}
}
