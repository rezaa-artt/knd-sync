<?php
/**
 * Rank Math SEO exporter for sender.
 *
 * @package KND_Sync_Sender
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exports allowlisted Rank Math keys only.
 */
final class KND_Sync_Sender_SEO_RankMath {

	/**
	 * Allowlisted keys (must match receiver adapter).
	 *
	 * @var string[]
	 */
	private const KEYS = array(
		'rank_math_title',
		'rank_math_description',
		'rank_math_focus_keyword',
		'rank_math_canonical_url',
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
	 * Whether Rank Math is available.
	 */
	public function is_available(): bool {
		return defined( 'RANK_MATH_VERSION' )
			|| class_exists( 'RankMath' )
			|| function_exists( 'rank_math' );
	}

	/**
	 * Export SEO meta for payload.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $include_canonical Include canonical.
	 * @return array<string, mixed>
	 */
	public function export( int $post_id, bool $include_canonical = false ): array {
		if ( ! $this->is_available() ) {
			return array();
		}

		$out = array();
		foreach ( self::KEYS as $key ) {
			if ( ! $include_canonical && $key === 'rank_math_canonical_url' ) {
				continue;
			}
			$value = get_post_meta( $post_id, $key, true );
			if ( $value === '' || $value === false || $value === null ) {
				continue;
			}
			$out[ $key ] = $value;
		}
		return $out;
	}
}
