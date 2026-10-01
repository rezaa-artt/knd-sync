<?php
/**
 * Receiver logs view.
 *
 * @package KND_Sync_Receiver
 * @var array{items: array<int, object>, total: int} $query
 * @var string $level
 * @var int $page
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$levels = array( 'ALL', 'SUCCESS', 'INFO', 'WARNING', 'ERROR' );
?>
<div class="wrap knd-sync-wrap">
	<h1><?php echo esc_html__( 'KND Sync Logs', 'knd-sync' ); ?></h1>
	<?php settings_errors( 'knd_sync_receiver' ); ?>

	<form method="get">
		<input type="hidden" name="page" value="knd-sync-receiver-logs" />
		<select name="level">
			<?php foreach ( $levels as $lvl ) : ?>
				<option value="<?php echo esc_attr( $lvl ); ?>" <?php selected( $level, $lvl ); ?>><?php echo esc_html( $lvl ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php submit_button( __( 'Filter', 'knd-sync' ), 'secondary', '', false ); ?>
	</form>

	<form method="post" style="margin-top:1em;">
		<?php wp_nonce_field( 'knd_sync_receiver_logs' ); ?>
		<?php submit_button( __( 'Clear All Logs', 'knd-sync' ), 'delete', 'knd_sync_receiver_clear_logs', false ); ?>
	</form>

	<table class="widefat striped" style="margin-top:1em;">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Time (UTC)', 'knd-sync' ); ?></th>
				<th><?php echo esc_html__( 'Level', 'knd-sync' ); ?></th>
				<th><?php echo esc_html__( 'Event', 'knd-sync' ); ?></th>
				<th><?php echo esc_html__( 'Source', 'knd-sync' ); ?></th>
				<th><?php echo esc_html__( 'Target', 'knd-sync' ); ?></th>
				<th><?php echo esc_html__( 'Code', 'knd-sync' ); ?></th>
				<th><?php echo esc_html__( 'Message', 'knd-sync' ); ?></th>
				<th><?php echo esc_html__( 'Request ID', 'knd-sync' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $query['items'] ) ) : ?>
			<tr><td colspan="8"><?php echo esc_html__( 'No logs yet.', 'knd-sync' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $query['items'] as $row ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $row->created_at ); ?></td>
					<td><span class="knd-level knd-level-<?php echo esc_attr( strtolower( (string) $row->level ) ); ?>"><?php echo esc_html( (string) $row->level ); ?></span></td>
					<td><?php echo esc_html( (string) $row->event ); ?></td>
					<td><?php echo esc_html( (string) $row->source_post_id ); ?></td>
					<td><?php echo esc_html( (string) $row->target_post_id ); ?></td>
					<td><?php echo esc_html( (string) $row->response_code ); ?></td>
					<td><?php echo esc_html( (string) $row->message ); ?></td>
					<td><code><?php echo esc_html( (string) $row->request_id ); ?></code></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
	<p><?php echo esc_html( sprintf( /* translators: %d: total logs */ __( 'Total: %d', 'knd-sync' ), (int) $query['total'] ) ); ?></p>
</div>
