<?php
/**
 * Autoloader for sender plugin.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps KND_Sync_Sender_* classes to files.
 */
final class KND_Sync_Sender_Autoloader {

	/**
	 * Register autoloader.
	 *
	 * @param string $includes_path Includes path.
	 */
	public static function register( string $includes_path ): void {
		spl_autoload_register(
			static function ( string $class ) use ( $includes_path ): void {
				if ( ! str_starts_with( $class, 'KND_Sync_Sender_' ) ) {
					return;
				}

				$relative = strtolower( str_replace( '_', '-', $class ) );
				$file     = $includes_path . 'class-' . $relative . '.php';

				if ( str_contains( $class, '_Admin' ) ) {
					$admin_file = dirname( $includes_path ) . '/admin/class-' . $relative . '.php';
					if ( is_readable( $admin_file ) ) {
						require_once $admin_file;
						return;
					}
				}

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
