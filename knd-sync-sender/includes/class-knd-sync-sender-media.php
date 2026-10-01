<?php
/**
 * Collect media descriptors from a post for the payload.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Featured + content image extraction.
 */
final class KND_Sync_Sender_Media {

	/**
	 * Build featured image descriptor.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function featured_image( int $post_id ): array {
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		if ( $thumb_id <= 0 ) {
			return array();
		}
		return $this->attachment_descriptor( $thumb_id );
	}

	/**
	 * Extract content images (attachment IDs and absolute URLs).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Content HTML.
	 * @return array<int, array<string, mixed>>
	 */
	public function content_images( int $post_id, string $content ): array {
		$found = array();
		$seen  = array();

		if ( preg_match_all( '/\b(?:src|href)\s*=\s*["\']([^"\']+)["\']/iu', $content, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$url = html_entity_decode( (string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				if ( ! $this->looks_like_image_url( $url ) ) {
					continue;
				}
				$abs = $this->absolutize( $url );
				if ( $abs === '' || isset( $seen[ $abs ] ) ) {
					continue;
				}
				$seen[ $abs ] = true;
				$att_id       = attachment_url_to_postid( $abs );
				if ( $att_id > 0 ) {
					$found[] = $this->attachment_descriptor( $att_id );
				} else {
					$found[] = array(
						'id'       => 0,
						'url'      => esc_url_raw( $abs ),
						'filename' => basename( (string) ( wp_parse_url( $abs, PHP_URL_PATH ) ?? 'image.jpg' ) ),
					);
				}
			}
		}

		// wp-image-{id} class hints.
		if ( preg_match_all( '/wp-image-(\d+)/', $content, $ids ) ) {
			foreach ( $ids[1] as $id ) {
				$id = (int) $id;
				if ( $id <= 0 || isset( $seen[ 'id:' . $id ] ) ) {
					continue;
				}
				$seen[ 'id:' . $id ] = true;
				$desc = $this->attachment_descriptor( $id );
				if ( $desc ) {
					$found[] = $desc;
				}
			}
		}

		unset( $post_id );
		return $found;
	}

	/**
	 * Attachment descriptor.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public function attachment_descriptor( int $attachment_id ): array {
		$post = get_post( $attachment_id );
		if ( ! $post || $post->post_type !== 'attachment' ) {
			return array();
		}

		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			return array();
		}

		$file = get_attached_file( $attachment_id );
		$alt  = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		return array(
			'id'          => $attachment_id,
			'url'         => esc_url_raw( $url ),
			'filename'    => $file ? basename( $file ) : basename( (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? 'image.jpg' ) ),
			'title'       => (string) $post->post_title,
			'caption'     => (string) $post->post_excerpt,
			'description' => (string) $post->post_content,
			'alt'         => $alt,
			'mime'        => (string) $post->post_mime_type,
		);
	}

	/**
	 * Heuristic for image URLs.
	 *
	 * @param string $url URL.
	 */
	private function looks_like_image_url( string $url ): bool {
		$path = (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? $url );
		return (bool) preg_match( '/\.(jpe?g|png|gif|webp)(\?|$)/i', $path );
	}

	/**
	 * Make absolute URL for site-relative paths.
	 *
	 * @param string $url URL.
	 */
	private function absolutize( string $url ): string {
		if ( preg_match( '#^https?://#i', $url ) ) {
			return $url;
		}
		if ( str_starts_with( $url, '//' ) ) {
			return 'https:' . $url;
		}
		if ( str_starts_with( $url, '/' ) ) {
			return home_url( $url );
		}
		return '';
	}
}
