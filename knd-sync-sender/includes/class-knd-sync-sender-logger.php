<?php
/**
 * Sender logger.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structured logger with secret redaction.
 */
final class KND_Sync_Sender_Logger {

	public const LEVEL_INFO    = 'INFO';
	public const LEVEL_SUCCESS = 'SUCCESS';
	public const LEVEL_WARNING = 'WARNING';
	public const LEVEL_ERROR   = 'ERROR';

	/**
	 * Log event.
	 *
	 * @param string               $level Level.
	 * @param string               $event Event.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public function log( string $level, string $event, string $message, array $context = array() ): void {
		global $wpdb;

		$safe  = $this->redact( $context );
		$table = $wpdb->prefix . 'knd_sync_logs';

		$wpdb->insert(
			$table,
			array(
				'created_at'     => current_time( 'mysql', true ),
				'level'          => sanitize_text_field( $level ),
				'event'          => sanitize_text_field( $event ),
				'request_id'     => sanitize_text_field( (string) ( $safe['request_id'] ?? '' ) ),
				'source_post_id' => (int) ( $safe['source_post_id'] ?? 0 ),
				'target_post_id' => (int) ( $safe['target_post_id'] ?? 0 ),
				'response_code'  => (int) ( $safe['response_code'] ?? 0 ),
				'message'        => wp_kses_post( $message ),
				'error_detail'   => isset( $safe['error'] ) ? wp_json_encode( $safe['error'] ) : null,
				'context'        => wp_json_encode( $safe ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/** @param string $event Event. @param string $message Message. @param array<string,mixed> $context Context. */
	public function info( string $event, string $message, array $context = array() ): void {
		$this->log( self::LEVEL_INFO, $event, $message, $context );
	}

	/** @param string $event Event. @param string $message Message. @param array<string,mixed> $context Context. */
	public function success( string $event, string $message, array $context = array() ): void {
		$this->log( self::LEVEL_SUCCESS, $event, $message, $context );
	}

	/** @param string $event Event. @param string $message Message. @param array<string,mixed> $context Context. */
	public function warning( string $event, string $message, array $context = array() ): void {
		$this->log( self::LEVEL_WARNING, $event, $message, $context );
	}

	/** @param string $event Event. @param string $message Message. @param array<string,mixed> $context Context. */
	public function error( string $event, string $message, array $context = array() ): void {
		$this->log( self::LEVEL_ERROR, $event, $message, $context );
	}

	/**
	 * Query logs.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return array{items: array<int, object>, total: int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$table    = $wpdb->prefix . 'knd_sync_logs';
		$level    = isset( $args['level'] ) ? sanitize_text_field( (string) $args['level'] ) : '';
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		if ( $level !== '' && strtoupper( $level ) !== 'ALL' ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE level = %s", strtoupper( $level ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE level = %s ORDER BY id DESC LIMIT %d OFFSET %d", strtoupper( $level ), $per_page, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL
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
	 * Prune old logs.
	 */
	public function prune(): void {
		global $wpdb;
		$settings = new KND_Sync_Sender_Settings();
		$days     = max( 1, (int) $settings->get( 'log_retention_days', 30 ) );
		$table    = $wpdb->prefix . 'knd_sync_logs';
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Clear all logs.
	 */
	public function clear_all(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'knd_sync_logs';
		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Redact secrets.
	 *
	 * @param mixed $data Data.
	 * @return mixed
	 */
	private function redact( mixed $data ): mixed {
		if ( ! is_array( $data ) ) {
			return is_string( $data ) && strlen( $data ) > 2000 ? substr( $data, 0, 2000 ) . '…' : $data;
		}
		$out = array();
		foreach ( $data as $key => $value ) {
			$key_str = (string) $key;
			if ( preg_match( '/api[_-]?key|secret|signature|authorization|x-knd-sync-key/i', $key_str ) ) {
				$out[ $key_str ] = '[REDACTED]';
			} else {
				$out[ $key_str ] = $this->redact( $value );
			}
		}
		return $out;
	}
}
