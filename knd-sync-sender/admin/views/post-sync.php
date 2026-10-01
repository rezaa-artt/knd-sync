<?php
/**
 * Post metabox view.
 *
 * @package KND_Sync_Sender
 * @var WP_Post $post
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status   = (string) get_post_meta( $post->ID, '_knd_sync_status', true );
$target   = (int) get_post_meta( $post->ID, '_knd_sync_target_post_id', true );
$last     = (string) get_post_meta( $post->ID, '_knd_sync_last_sync', true );
$error    = (string) get_post_meta( $post->ID, '_knd_sync_last_error', true );
$disabled = (int) get_post_meta( $post->ID, '_knd_sync_disabled', true ) === 1;

if ( $status === '' ) {
	$status = 'not_synced';
}

$labels = array(
	'not_synced' => __( 'Not Synced', 'knd-sync' ),
	'pending'    => __( 'Pending', 'knd-sync' ),
	'syncing'    => __( 'Syncing', 'knd-sync' ),
	'synced'     => __( 'Synced', 'knd-sync' ),
	'failed'     => __( 'Failed', 'knd-sync' ),
	'disabled'   => __( 'Disabled', 'knd-sync' ),
);
?>
<?php wp_nonce_field( 'knd_sync_metabox', 'knd_sync_metabox_nonce' ); ?>
<p>
	<strong><?php echo esc_html__( 'Status', 'knd-sync' ); ?>:</strong>
	<span class="knd-sync-post-status" data-status="<?php echo esc_attr( $status ); ?>">
		<?php echo esc_html( $labels[ $status ] ?? $status ); ?>
	</span>
</p>
<?php if ( $target > 0 ) : ?>
	<p><strong><?php echo esc_html__( 'Target Post ID', 'knd-sync' ); ?>:</strong> <span class="knd-sync-target-id"><?php echo esc_html( (string) $target ); ?></span></p>
<?php endif; ?>
<?php if ( $last !== '' ) : ?>
	<p><strong><?php echo esc_html__( 'Last Sync', 'knd-sync' ); ?>:</strong> <span class="knd-sync-last"><?php echo esc_html( $last ); ?> UTC</span></p>
<?php endif; ?>
<?php if ( $error !== '' ) : ?>
	<p class="knd-sync-warning"><strong><?php echo esc_html__( 'Error', 'knd-sync' ); ?>:</strong> <span class="knd-sync-error"><?php echo esc_html( $error ); ?></span></p>
<?php endif; ?>

<p>
	<label>
		<input type="checkbox" name="knd_sync_disabled" value="1" <?php checked( $disabled ); ?> />
		<?php echo esc_html__( 'Exclude from Sync', 'knd-sync' ); ?>
	</label>
</p>

<p>
	<button type="button" class="button" id="knd-sync-preview" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
		<?php echo esc_html__( 'Preview Changes', 'knd-sync' ); ?>
	</button>
	<button type="button" class="button button-primary" id="knd-sync-now" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
		<?php echo esc_html__( 'Sync Now', 'knd-sync' ); ?>
	</button>
</p>
<pre id="knd-sync-preview-box" class="knd-sync-preview-box" hidden></pre>
