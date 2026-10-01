<?php
/**
 * Uninstall routine for KND Sync Receiver.
 *
 * Deletes data only when explicitly enabled in settings.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'knd_sync_receiver_settings', array() );
$delete   = is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] );

if ( ! $delete ) {
	return;
}

global $wpdb;

$logs  = $wpdb->prefix . 'knd_sync_logs';
$media = $wpdb->prefix . 'knd_sync_media';

// phpcs:ignore WordPress.DB.PreparedSQL
$wpdb->query( "DROP TABLE IF EXISTS {$logs}" );
// phpcs:ignore WordPress.DB.PreparedSQL
$wpdb->query( "DROP TABLE IF EXISTS {$media}" );

delete_option( 'knd_sync_receiver_settings' );
delete_option( 'knd_sync_receiver_db_version' );
