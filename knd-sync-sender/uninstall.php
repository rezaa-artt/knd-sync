<?php
/**
 * Uninstall for KND Sync Sender.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'knd_sync_sender_settings', array() );
$delete   = is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] );

if ( ! $delete ) {
	return;
}

global $wpdb;

$jobs = $wpdb->prefix . 'knd_sync_jobs';
$logs = $wpdb->prefix . 'knd_sync_logs';

// phpcs:ignore WordPress.DB.PreparedSQL
$wpdb->query( "DROP TABLE IF EXISTS {$jobs}" );
// phpcs:ignore WordPress.DB.PreparedSQL
$wpdb->query( "DROP TABLE IF EXISTS {$logs}" );

delete_option( 'knd_sync_sender_settings' );
delete_option( 'knd_sync_sender_db_version' );
