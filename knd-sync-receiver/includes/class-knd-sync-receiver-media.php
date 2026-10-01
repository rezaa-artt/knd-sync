<?php
/**
 * Media import and mapping for receiver.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Downloads and sideloads images with deduplication.
 */
final class KND_Sync_Receiver_Media {

	/**
	 * Allowed MIME types.
	 *
	 * @var string[]
	 */
	private const ALLOWED_MIME = array(
		'image/jpeg',
		'image/png',
		'image/webp',
		'image/gif',
	);

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
	 * Ensure media exists on target; return attachment ID and URL.
	 *
	 * @param array<string, mixed> $image Image descriptor.
	 * @param string               $source_site Source site URL/host.
	 * @param int                  $parent_post_id Parent post.
	 * @param string               $request_id Request id.
	 * @return array{id:int,url:string}|WP_Error
	 */
	public function ensure( array $image, string $source_site, int $parent_post_id, string $request_id ): array|WP_Error {
		$source_url = esc_url_raw( (string) ( $image['url'] ?? '' ) );
		if ( $source_url === '' || ! wp_http_validate_url( $source_url ) ) {
			return new WP_Error( 'MEDIA_INVALID_URL', __( 'Invalid media URL.', 'knd-sync' ), array( 'status' => 422 ) );
		}

		if ( ! $this->is_https_or_local( $source_url ) ) {
			return new WP_Error( 'MEDIA_INSECURE', __( 'Media URL must use HTTPS.', 'knd-sync' ), array( 'status' => 422 ) );
		}

		$source_media_id = (int) ( $image['id'] ?? 0 );
		$existing        = $this->find_mapping( $source_site, $source_url, $source_media_id );

		if ( $existing && ! empty( $existing->target_media_id ) ) {
			$att_id = (int) $existing->target_media_id;
			if ( get_post( $att_id ) ) {
				$url = wp_get_attachment_url( $att_id );
				return array(
					'id'  => $att_id,
					'url' => $url ? $url : (string) $existing->target_url,
				);
			}
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $source_url, 60 );
		if ( is_wp_error( $tmp ) ) {
			return new WP_Error( 'MEDIA_DOWNLOAD_FAILED', $tmp->get_error_message(), array( 'status' => 502 ) );
		}

		$max_bytes = (int) $this->settings->get( 'max_image_bytes', 8 * MB_IN_BYTES );
		$size      = filesize( $tmp );
		if ( false === $size || $size <= 0 || $size > $max_bytes ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return new WP_Error( 'MEDIA_TOO_LARGE', __( 'Media file exceeds size limit or is empty.', 'knd-sync' ), array( 'status' => 422 ) );
		}

		$filetype = wp_check_filetype_and_ext( $tmp, $this->guess_filename( $source_url, $image ) );
		$mime     = (string) ( $filetype['type'] ?? '' );
		if ( $mime === '' || ! in_array( $mime, self::ALLOWED_MIME, true ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return new WP_Error( 'MEDIA_MIME_DENIED', __( 'Media MIME type is not allowed.', 'knd-sync' ), array( 'status' => 422 ) );
		}

		// Block PHP-like extensions explicitly.
		$ext = strtolower( (string) ( $filetype['ext'] ?? '' ) );
		if ( in_array( $ext, array( 'php', 'phtml', 'phar', 'php5', 'php7', 'php8' ), true ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return new WP_Error( 'MEDIA_EXT_DENIED', __( 'Dangerous file extension blocked.', 'knd-sync' ), array( 'status' => 422 ) );
		}

		$filename = $this->guess_filename( $source_url, $image );
		if ( $ext && ! str_ends_with( strtolower( $filename ), '.' . $ext ) ) {
			$filename = pathinfo( $filename, PATHINFO_FILENAME ) . '.' . $ext;
		}

		$file_array = array(
			'name'     => sanitize_file_name( $filename ),
			'tmp_name' => $tmp,
			'type'     => $mime,
			'error'    => 0,
			'size'     => $size,
		);

		$attachment_id = media_handle_sideload( $file_array, $parent_post_id, null );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return new WP_Error( 'MEDIA_SIDELOAD_FAILED', $attachment_id->get_error_message(), array( 'status' => 500 ) );
		}

		$this->apply_metadata( (int) $attachment_id, $image );
		$target_url = (string) wp_get_attachment_url( (int) $attachment_id );

		$this->store_mapping(
			$source_site,
			$source_media_id,
			$source_url,
			(int) $attachment_id,
			$target_url
		);

		$this->logger->info(
			'media_imported',
			__( 'Media imported.', 'knd-sync' ),
			array(
				'request_id'      => $request_id,
				'source_media_id' => $source_media_id,
				'target_media_id' => (int) $attachment_id,
			)
		);

		return array(
			'id'  => (int) $attachment_id,
			'url' => $target_url,
		);
	}

	/**
	 * Rewrite content image URLs using a map of source=>target.
	 *
	 * @param string                $content Content HTML.
	 * @param array<string, string> $url_map Source URL => target URL.
	 */
	public function rewrite_content_urls( string $content, array $url_map ): string {
		if ( $content === '' || ! $url_map ) {
			return $content;
		}

		// Longest keys first to avoid partial replacements of resized URLs incorrectly.
		uksort(
			$url_map,
			static function ( string $a, string $b ): int {
				return strlen( $b ) <=> strlen( $a );
			}
		);

		foreach ( $url_map as $from => $to ) {
			if ( $from === '' || $to === '' ) {
				continue;
			}
			$content = str_replace( $from, $to, $content );
		}

		return $content;
	}

	/**
	 * Find existing mapping row.
	 *
	 * @param string $source_site Source site.
	 * @param string $source_url Source URL.
	 * @param int    $source_media_id Source media ID.
	 */
	private function find_mapping( string $source_site, string $source_url, int $source_media_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'knd_sync_media';
		$hash  = hash( 'sha256', $source_url );
		$site  = $this->normalize_site( $source_site );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE source_site = %s AND source_url_hash = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
				$site,
				$hash
			)
		);

		if ( $row ) {
			return $row;
		}

		if ( $source_media_id > 0 ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE source_site = %s AND source_media_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
					$site,
					$source_media_id
				)
			);
			return $row ?: null;
		}

		return null;
	}

	/**
	 * Persist mapping.
	 *
	 * @param string $source_site Source site.
	 * @param int    $source_media_id Source media ID.
	 * @param string $source_url Source URL.
	 * @param int    $target_media_id Target attachment ID.
	 * @param string $target_url Target URL.
	 */
	private function store_mapping(
		string $source_site,
		int $source_media_id,
		string $source_url,
		int $target_media_id,
		string $target_url
	): void {
		global $wpdb;

		$table = $wpdb->prefix . 'knd_sync_media';
		$now   = current_time( 'mysql', true );
		$site  = $this->normalize_site( $source_site );
		$hash  = hash( 'sha256', $source_url );

		$existing = $this->find_mapping( $site, $source_url, $source_media_id );
		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'source_media_id' => $source_media_id,
					'source_url'      => $source_url,
					'source_url_hash' => $hash,
					'target_media_id' => $target_media_id,
					'target_url'      => $target_url,
					'updated_at'      => $now,
				),
				array( 'id' => (int) $existing->id ),
				array( '%d', '%s', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'source_site'     => $site,
				'source_media_id' => $source_media_id,
				'source_url'      => $source_url,
				'source_url_hash' => $hash,
				'target_media_id' => $target_media_id,
				'target_url'      => $target_url,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Apply alt/title/caption/description.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param array<string, mixed> $image Image data.
	 */
	private function apply_metadata( int $attachment_id, array $image ): void {
		$alt = isset( $image['alt'] ) ? sanitize_text_field( (string) $image['alt'] ) : '';
		if ( $alt !== '' ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}

		$update = array( 'ID' => $attachment_id );
		if ( ! empty( $image['title'] ) ) {
			$update['post_title'] = sanitize_text_field( (string) $image['title'] );
		}
		if ( ! empty( $image['caption'] ) ) {
			$update['post_excerpt'] = wp_kses_post( (string) $image['caption'] );
		}
		if ( ! empty( $image['description'] ) ) {
			$update['post_content'] = wp_kses_post( (string) $image['description'] );
		}

		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}
	}

	/**
	 * Guess a safe filename.
	 *
	 * @param string               $url URL.
	 * @param array<string, mixed> $image Image.
	 */
	private function guess_filename( string $url, array $image ): string {
		if ( ! empty( $image['filename'] ) ) {
			return sanitize_file_name( (string) $image['filename'] );
		}
		$path = (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? '' );
		$base = $path !== '' ? basename( $path ) : 'knd-sync-image.jpg';
		$base = rawurldecode( $base );
		return sanitize_file_name( $base ) ?: 'knd-sync-image.jpg';
	}

	/**
	 * Normalize site identity for mapping keys.
	 *
	 * @param string $site Site.
	 */
	private function normalize_site( string $site ): string {
		$host = (string) ( wp_parse_url( $site, PHP_URL_HOST ) ?? $site );
		return strtolower( preg_replace( '/^www\./', '', $host ) ?? $host );
	}

	/**
	 * Require HTTPS for remote media.
	 *
	 * @param string $url URL.
	 */
	private function is_https_or_local( string $url ): bool {
		$scheme = strtolower( (string) ( wp_parse_url( $url, PHP_URL_SCHEME ) ?? '' ) );
		return $scheme === 'https';
	}
}
