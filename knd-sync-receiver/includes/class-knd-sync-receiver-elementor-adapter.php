<?php
/**
 * Elementor data adapter (opt-in, conservative).
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies Elementor meta only when explicitly enabled by settings.
 */
final class KND_Sync_Receiver_Elementor_Adapter {

	/**
	 * Known Elementor meta keys.
	 *
	 * @var string[]
	 */
	private const KEYS = array(
		'_elementor_edit_mode',
		'_elementor_template_type',
		'_elementor_version',
		'_elementor_pro_version',
		'_elementor_data',
		'_elementor_page_settings',
		'_elementor_controls_usage',
		'_elementor_css',
	);

	/**
	 * Apply Elementor meta bag.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $elementor Elementor meta.
	 */
	public function apply( int $post_id, array $elementor ): void {
		foreach ( self::KEYS as $key ) {
			if ( ! array_key_exists( $key, $elementor ) ) {
				continue;
			}
			update_post_meta( $post_id, $key, $elementor[ $key ] );
		}
	}
}
