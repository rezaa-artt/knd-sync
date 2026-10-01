<?php
/**
 * Plugin Name:       KND Sync Receiver
 * Plugin URI:        https://knd-home.com
 * Description:       Receives synchronized WordPress posts from KND Decor via a secure REST API.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            KND
 * License:           GPL-2.0-or-later
 * Text Domain:       knd-sync
 * Domain Path:       /languages
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KND_SYNC_RECEIVER_VERSION', '1.0.0' );
define( 'KND_SYNC_RECEIVER_FILE', __FILE__ );
define( 'KND_SYNC_RECEIVER_PATH', plugin_dir_path( __FILE__ ) );
define( 'KND_SYNC_RECEIVER_URL', plugin_dir_url( __FILE__ ) );
define( 'KND_SYNC_RECEIVER_BASENAME', plugin_basename( __FILE__ ) );

require_once KND_SYNC_RECEIVER_PATH . 'includes/class-knd-sync-autoloader.php';
KND_Sync_Receiver_Autoloader::register( KND_SYNC_RECEIVER_PATH . 'includes/' );

/**
 * Boot the receiver plugin.
 */
function knd_sync_receiver_bootstrap(): void {
	$plugin = KND_Sync_Receiver_Plugin::instance();
	$plugin->init();
}
add_action( 'plugins_loaded', 'knd_sync_receiver_bootstrap' );

register_activation_hook(
	__FILE__,
	static function (): void {
		require_once KND_SYNC_RECEIVER_PATH . 'includes/class-knd-sync-autoloader.php';
		KND_Sync_Receiver_Autoloader::register( KND_SYNC_RECEIVER_PATH . 'includes/' );
		KND_Sync_Receiver_Activator::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		KND_Sync_Receiver_Activator::deactivate();
	}
);
