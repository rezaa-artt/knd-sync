<?php
/**
 * Plugin Name:       KND Sync Sender
 * Plugin URI:        https://knddecor.com
 * Description:       Synchronizes WordPress posts from KND Decor to KND Home via a secure REST API.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            KND
 * License:           GPL-2.0-or-later
 * Text Domain:       knd-sync
 * Domain Path:       /languages
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KND_SYNC_SENDER_VERSION', '1.0.0' );
define( 'KND_SYNC_SENDER_FILE', __FILE__ );
define( 'KND_SYNC_SENDER_PATH', plugin_dir_path( __FILE__ ) );
define( 'KND_SYNC_SENDER_URL', plugin_dir_url( __FILE__ ) );
define( 'KND_SYNC_SENDER_BASENAME', plugin_basename( __FILE__ ) );

require_once KND_SYNC_SENDER_PATH . 'includes/class-knd-sync-autoloader.php';
KND_Sync_Sender_Autoloader::register( KND_SYNC_SENDER_PATH . 'includes/' );

/**
 * Boot the sender plugin.
 */
function knd_sync_sender_bootstrap(): void {
	KND_Sync_Sender_Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'knd_sync_sender_bootstrap' );

register_activation_hook(
	__FILE__,
	static function (): void {
		require_once KND_SYNC_SENDER_PATH . 'includes/class-knd-sync-autoloader.php';
		KND_Sync_Sender_Autoloader::register( KND_SYNC_SENDER_PATH . 'includes/' );
		KND_Sync_Sender_Activator::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		KND_Sync_Sender_Activator::deactivate();
	}
);
