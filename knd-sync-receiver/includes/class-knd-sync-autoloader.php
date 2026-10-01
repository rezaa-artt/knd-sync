<?php
/**
 * Simple class autoloader for the receiver plugin.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps KND_Sync_Receiver_* class names to files.
 */
final class KND_Sync_Receiver_Autoloader {

	/**
	 * Register autoloader for includes directory.
	 *
	 * @param string $includes_path Absolute path to includes/.
	 */
	public static function register( string $includes_path ): void {
		spl_autoload_register(
			static function ( string $class ) use ( $includes_path ): void {
				if ( ! str_starts_with( $class, 'KND_Sync_Receiver_' ) ) {
					return;
				}

				$relative = strtolower( str_replace( '_', '-', $class ) );
				$file     = $includes_path . 'class-' . $relative . '.php';

				// Admin classes live under admin/.
				if ( str_contains( $class, '_Admin' ) ) {
					$admin_file = dirname( $includes_path ) . '/admin/class-' . $relative . '.php';
					if ( is_readable( $admin_file ) ) {
						require_once $admin_file;
						return;
					}
				}

				// SEO adapters.
				if ( str_contains( $class, '_SEO_' ) ) {
					$seo_file = $includes_path . 'seo/class-' . $relative . '.php';
					if ( is_readable( $seo_file ) ) {
						require_once $seo_file;
						return;
					}
				}

				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}
