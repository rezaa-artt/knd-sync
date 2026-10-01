<?php
/**
 * Safe internal link rewriter.
 *
 * Never uses blind string replace on domain substrings.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrites absolute URLs whose host matches configured source hosts.
 */
final class KND_Sync_Sender_Link_Rewriter {

	/**
	 * @var string[]
	 */
	private array $source_hosts;

	/**
	 * @var string
	 */
	private string $target_host;

	/**
	 * @var string
	 */
	private string $target_scheme;

	/**
	 * Stats from last rewrite.
	 *
	 * @var array<string, int>
	 */
	private array $stats = array(
		'found'     => 0,
		'rewritten' => 0,
		'skipped'   => 0,
	);

	/**
	 * Constructor.
	 *
	 * @param string[] $source_hosts Source hosts.
	 * @param string   $target_host Target host.
	 * @param string   $target_scheme https|http.
	 */
	public function __construct( array $source_hosts, string $target_host, string $target_scheme = 'https' ) {
		$this->source_hosts  = array_values(
			array_unique(
				array_map(
					static function ( string $host ): string {
						return strtolower( $host );
					},
					$source_hosts
				)
			)
		);
		$this->target_host   = strtolower( $target_host );
		$this->target_scheme = $target_scheme === 'http' ? 'http' : 'https';
	}

	/**
	 * Get stats.
	 *
	 * @return array<string, int>
	 */
	public function get_stats(): array {
		return $this->stats;
	}

	/**
	 * Rewrite HTML content attributes (href, src, srcset) and bare URLs carefully.
	 *
	 * @param string $content HTML.
	 */
	public function rewrite_html( string $content ): string {
		$this->stats = array(
			'found'     => 0,
			'rewritten' => 0,
			'skipped'   => 0,
		);

		if ( $content === '' || ! $this->source_hosts || $this->target_host === '' ) {
			return $content;
		}

		// href / src attributes.
		$content = preg_replace_callback(
			'/\b(href|src)\s*=\s*(["\'])(.*?)\2/iu',
			function ( array $m ): string {
				$attr  = $m[1];
				$quote = $m[2];
				$url   = html_entity_decode( $m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$new   = $this->rewrite_url( $url );
				$encoded = htmlspecialchars( $new, ENT_QUOTES | ENT_HTML5, 'UTF-8', false );
				return $attr . '=' . $quote . $encoded . $quote;
			},
			$content
		) ?? $content;

		// srcset attribute (comma-separated URL descriptors).
		$content = preg_replace_callback(
			'/\bsrcset\s*=\s*(["\'])(.*?)\1/iu',
			function ( array $m ): string {
				$quote  = $m[1];
				$srcset = html_entity_decode( $m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$parts  = array_map( 'trim', explode( ',', $srcset ) );
				$out    = array();
				foreach ( $parts as $part ) {
					if ( $part === '' ) {
						continue;
					}
					if ( preg_match( '/^(\S+)(\s+.+)?$/', $part, $pm ) ) {
						$url      = $this->rewrite_url( $pm[1] );
						$out[]    = $url . ( $pm[2] ?? '' );
					} else {
						$out[] = $part;
					}
				}
				$joined = implode( ', ', $out );
				return 'srcset=' . $quote . htmlspecialchars( $joined, ENT_QUOTES | ENT_HTML5, 'UTF-8', false ) . $quote;
			},
			$content
		) ?? $content;

		return $content;
	}

	/**
	 * Rewrite a single URL string.
	 *
	 * Relative URLs and external hosts are left unchanged.
	 *
	 * @param string $url URL.
	 */
	public function rewrite_url( string $url ): string {
		$url = trim( $url );
		if ( $url === '' || str_starts_with( $url, '#' ) || str_starts_with( $url, 'mailto:' ) || str_starts_with( $url, 'tel:' ) || str_starts_with( $url, 'data:' ) ) {
			return $url;
		}

		// Protocol-relative.
		if ( str_starts_with( $url, '//' ) ) {
			$parsed = $this->parse_url( 'https:' . $url );
			if ( ! $parsed || empty( $parsed['host'] ) ) {
				return $url;
			}
			if ( ! $this->is_source_host( (string) $parsed['host'] ) ) {
				++$this->stats['skipped'];
				return $url;
			}
			++$this->stats['found'];
			++$this->stats['rewritten'];
			return $this->build_url( $parsed );
		}

		// Relative path — must not rewrite.
		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) ) {
			++$this->stats['skipped'];
			return $url;
		}

		$parsed = $this->parse_url( $url );
		if ( ! $parsed || empty( $parsed['host'] ) ) {
			++$this->stats['skipped'];
			return $url;
		}

		if ( ! $this->is_source_host( (string) $parsed['host'] ) ) {
			++$this->stats['skipped'];
			return $url;
		}

		++$this->stats['found'];
		++$this->stats['rewritten'];
		return $this->build_url( $parsed );
	}

	/**
	 * Exact host match against allowlist (no substring tricks).
	 *
	 * @param string $host Host.
	 */
	private function is_source_host( string $host ): bool {
		$host = strtolower( $host );
		return in_array( $host, $this->source_hosts, true );
	}

	/**
	 * Parse URL using WP helper when available.
	 *
	 * @param string $url URL.
	 * @return array<string, mixed>|false
	 */
	private function parse_url( string $url ): array|false {
		if ( function_exists( 'wp_parse_url' ) ) {
			$parsed = wp_parse_url( $url );
			return is_array( $parsed ) ? $parsed : false;
		}
		$parsed = parse_url( $url );
		return is_array( $parsed ) ? $parsed : false;
	}

	/**
	 * Rebuild URL with target host, preserving path/query/fragment.
	 *
	 * @param array<string, mixed> $parsed Parsed parts.
	 */
	private function build_url( array $parsed ): string {
		$path     = isset( $parsed['path'] ) ? (string) $parsed['path'] : '';
		$query    = isset( $parsed['query'] ) ? '?' . (string) $parsed['query'] : '';
		$fragment = isset( $parsed['fragment'] ) ? '#' . (string) $parsed['fragment'] : '';
		return $this->target_scheme . '://' . $this->target_host . $path . $query . $fragment;
	}
}
