<?php
/**
 * Structured logger for receiver.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes to custom logs table. Never stores secrets.
 */
final class KND_Sync_Receiver_Logger {

	public const LEVEL_INFO    = 'INFO';
	public const LEVEL_SUCCESS = 'SUCCESS';
	public const LEVEL_WARNING = 'WARNING';
	public const LEVEL_ERROR   = 'ERROR';

	/**
	 * @var string[]
	 */
	private array $secret_patterns = array(
		'/api[_-]?key/i',
		'/secret/i',
		'/signature/i',
		'/authorization/i',
		'/x-knd-sync-key/i',
	);

	/**
	 * Log an event.
	 *
	 * @param string               $level Level.
	 * @param string               $event Event name.
	 * @param string               $message Human message.
	 * @param array<string, mixed> $context Extra context.
	 */
	public function log( string $level, string $event, string $message, array $context = array() ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'knd_sync_logs';
		$safe  = $this->redact( $context );

		$wpdb->insert(
			$table,
			array(
				'created_at'      => current_time( 'mysql', true ),
				'level'           => sanitize_text_field( $level ),
				'event'           => sanitize_text_field( $event ),
				'request_id'      => sanitize_text_field( (string) ( $safe['request_id'] ?? '' ) ),
				'source_post_id'  => (int) ( $safe['source_post_id'] ?? 0 ),
				'target_post_id'  => (int) ( $safe['target_post_id'] ?? 0 ),
				'response_code'   => (int) ( $safe['response_code'] ?? 0 ),
				'message'         => wp_kses_post( $message ),
				'error_detail'    => isset( $safe['error'] ) ? wp_json_encode( $safe['error'] ) : null,
				'context'         => wp_json_encode( $safe ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Convenience helpers.
	 *
	 * @param string               $event Event.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public function info( string $event, string $message, array $context = array() ): void {
		$this->log( self::LEVEL_INFO, $event, $message, $context );
	}

	/**
	 * @param string               $event Event.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public function success( string $event, string $message, array $context = array() ): void {
		$this->log( self::LEVEL_SUCCESS, $event, $message, $context );
	}

	/**
	 * @param string               $event Event.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public function warning( string $event, string $message, array $context = array() ): void {
		$this->log( self::LEVEL_WARNING, $event, $message, $context );
	}

	/**
	 * @param string               $event Event.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public function error( string $event, string $message, array $context = array() ): void {
		$this->log( self::LEVEL_ERROR, $event, $message, $context );
	}

	/**
	 * Query logs for admin UI.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array{items: array<int, object>, total: int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$table    = $wpdb->prefix . 'knd_sync_logs';
		$level    = isset( $args['level'] ) ? sanitize_text_field( (string) $args['level'] ) : '';
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where = 'WHERE 1=1';
		$params = array();

		if ( $level !== '' && strtoupper( $level ) !== 'ALL' ) {
			$where   .= ' AND level = %s';
			$params[] = strtoupper( $level );
		}

		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$list_sql  = "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d";

		if ( $params ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL
			$params[] = $per_page;
			$params[] = $offset;
			$items = $wpdb->get_results( $wpdb->prepare( $list_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Delete logs older than retention setting.
	 */
	public function prune(): void {
		global $wpdb;

		$settings = new KND_Sync_Receiver_Settings();
		$days     = max( 1, (int) $settings->get( 'log_retention_days', 30 ) );
		$table    = $wpdb->prefix . 'knd_sync_logs';
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL
				$cutoff
			)
		);
	}

	/**
	 * Delete all logs (admin action).
	 */
	public function clear_all(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'knd_sync_logs';
		// Avoid TRUNCATE; delete in batches is safer for replication/locks.
		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Redact sensitive keys from context recursively.
	 *
	 * @param mixed $data Data.
	 * @return mixed
	 */
	private function redact( mixed $data ): mixed {
		if ( ! is_array( $data ) ) {
			if ( is_string( $data ) && strlen( $data ) > 2000 ) {
				return substr( $data, 0, 2000 ) . '…';
			}
			return $data;
		}

		$out = array();
		foreach ( $data as $key => $value ) {
			$key_str = (string) $key;
			$sensitive = false;
			foreach ( $this->secret_patterns as $pattern ) {
				if ( preg_match( $pattern, $key_str ) ) {
					$sensitive = true;
					break;
				}
			}
			$out[ $key_str ] = $sensitive ? '[REDACTED]' : $this->redact( $value );
		}
		return $out;
	}
}
