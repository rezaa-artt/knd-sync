<?php
/**
 * Rank Math SEO meta adapter (receiver).
 *
 * Meta keys are taken from Rank Math's public post meta API surface.
 * No keys are guessed beyond this allowlist.
 *
 * @package KND_Sync_Receiver
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies Rank Math meta when the plugin is active.
 */
final class KND_Sync_Receiver_SEO_RankMath {

	/**
	 * Confirmed Rank Math post meta keys (allowlist).
	 *
	 * @var string[]
	 */
	private const KEYS = array(
		'rank_math_title',
		'rank_math_description',
		'rank_math_focus_keyword',
		'rank_math_robots',
		'rank_math_advanced_robots',
		'rank_math_facebook_title',
		'rank_math_facebook_description',
		'rank_math_facebook_image',
		'rank_math_facebook_enable_image_overlay',
		'rank_math_facebook_image_overlay',
		'rank_math_twitter_use_facebook',
		'rank_math_twitter_title',
		'rank_math_twitter_description',
		'rank_math_twitter_image',
		'rank_math_twitter_card_type',
		'rank_math_breadcrumb_title',
		'rank_math_pillar_content',
	);

	/**
	 * Canonical is opt-in separately.
	 */
	private const CANONICAL_KEY = 'rank_math_canonical_url';

	/**
	 * @var KND_Sync_Receiver_Settings
	 */
	private KND_Sync_Receiver_Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param KND_Sync_Receiver_Settings $settings Settings.
	 */
	public function __construct( KND_Sync_Receiver_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Whether Rank Math appears active.
	 */
	public function is_available(): bool {
		return defined( 'RANK_MATH_VERSION' )
			|| class_exists( 'RankMath' )
			|| function_exists( 'rank_math' );
	}

	/**
	 * Apply SEO meta.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $meta Meta bag from payload.
	 * @param string               $request_id Request id.
	 */
	public function apply( int $post_id, array $meta, string $request_id ): void {
		$seo = isset( $meta['seo'] ) && is_array( $meta['seo'] ) ? $meta['seo'] : array();
		if ( ! $seo ) {
			// Also accept flat rank_math_* under meta.seo_flat.
			$seo = isset( $meta['seo_flat'] ) && is_array( $meta['seo_flat'] ) ? $meta['seo_flat'] : array();
		}

		foreach ( self::KEYS as $key ) {
			if ( ! array_key_exists( $key, $seo ) ) {
				continue;
			}
			$value = $seo[ $key ];
			if ( is_array( $value ) || is_object( $value ) ) {
				update_post_meta( $post_id, $key, $value );
			} else {
				update_post_meta( $post_id, $key, is_string( $value ) ? wp_kses_post( $value ) : $value );
			}
		}

		if ( (int) $this->settings->get( 'sync_seo_canonical', 0 ) && array_key_exists( self::CANONICAL_KEY, $seo ) ) {
			update_post_meta(
				$post_id,
				self::CANONICAL_KEY,
				esc_url_raw( (string) $seo[ self::CANONICAL_KEY ] )
			);
		}

		unset( $request_id ); // reserved for future logging hooks.
	}

	/**
	 * Keys exported by sender for Rank Math.
	 *
	 * @return string[]
	 */
	public static function export_keys(): array {
		$keys = self::KEYS;
		$keys[] = self::CANONICAL_KEY;
		return $keys;
	}
}
