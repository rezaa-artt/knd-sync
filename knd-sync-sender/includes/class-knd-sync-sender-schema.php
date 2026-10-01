<?php
/**
 * Sender DB schema.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jobs + logs tables.
 */
final class KND_Sync_Sender_Schema {

	public const DB_VERSION = '1.0.0';

	/**
	 * Install tables.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$jobs    = $wpdb->prefix . 'knd_sync_jobs';
		$logs    = $wpdb->prefix . 'knd_sync_logs';

		$sql_jobs = "CREATE TABLE {$jobs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(32) NOT NULL DEFAULT 'sync_post',
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			max_attempts tinyint(3) unsigned NOT NULL DEFAULT 3,
			request_id varchar(64) NOT NULL DEFAULT '',
			payload longtext NULL,
			last_error text NULL,
			available_at datetime NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status_available (status, available_at),
			KEY post_id (post_id)
		) {$charset};";

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

		dbDelta( $sql_jobs );
		dbDelta( $sql_logs );
		update_option( 'knd_sync_sender_db_version', self::DB_VERSION, false );
	}
}
