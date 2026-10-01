<?php
/**
 * Database schema for receiver.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates custom tables via dbDelta (never drops data).
 */
final class KND_Sync_Receiver_Schema {

	public const DB_VERSION = '1.0.0';

	/**
	 * Install or upgrade tables.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$logs    = $wpdb->prefix . 'knd_sync_logs';
		$media   = $wpdb->prefix . 'knd_sync_media';

		$sql_logs = "CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			level varchar(20) NOT NULL DEFAULT 'INFO',
			event varchar(100) NOT NULL DEFAULT '',
			request_id varchar(64) NOT NULL DEFAULT '',
			source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			target_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			response_code smallint(5) unsigned NOT NULL DEFAULT 0,
			message text NULL,
			error_detail longtext NULL,
			context longtext NULL,
			PRIMARY KEY  (id),
			KEY level (level),
			KEY created_at (created_at),
			KEY request_id (request_id),
			KEY source_post_id (source_post_id)
		) {$charset};";

		$sql_media = "CREATE TABLE {$media} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_site varchar(255) NOT NULL DEFAULT '',
			source_media_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_url text NOT NULL,
			source_url_hash char(64) NOT NULL DEFAULT '',
			target_media_id bigint(20) unsigned NOT NULL DEFAULT 0,
			target_url text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_map (source_site(191), source_url_hash),
			KEY source_media_id (source_media_id),
			KEY target_media_id (target_media_id)
		) {$charset};";

		dbDelta( $sql_logs );
		dbDelta( $sql_media );

		update_option( 'knd_sync_receiver_db_version', self::DB_VERSION, false );
	}
}
