<?php
/**
 * Receiver settings view.
 *
 * @package KND_Sync_Receiver
 * @var array<string, mixed> $settings
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hosts = implode( "\n", (array) ( $settings['allowed_source_hosts'] ?? array() ) );
$users = get_users( array( 'fields' => array( 'ID', 'display_name', 'user_login' ) ) );
?>
<div class="wrap knd-sync-wrap">
	<h1><?php echo esc_html__( 'KND Sync Receiver', 'knd-sync' ); ?></h1>
	<?php settings_errors( 'knd_sync_receiver' ); ?>

	<div class="knd-sync-status-card">
		<p><strong><?php echo esc_html__( 'Endpoint', 'knd-sync' ); ?>:</strong>
			<code><?php echo esc_html( rest_url( 'knd-sync/v1/status' ) ); ?></code>
		</p>
		<p><strong><?php echo esc_html__( 'Receiver', 'knd-sync' ); ?>:</strong>
			<?php echo ! empty( $settings['enabled'] ) ? esc_html__( 'Enabled', 'knd-sync' ) : esc_html__( 'Disabled', 'knd-sync' ); ?>
		</p>
		<p><strong><?php echo esc_html__( 'Force Draft', 'knd-sync' ); ?>:</strong>
			<?php echo ! empty( $settings['force_draft'] ) ? 'ON' : 'OFF'; ?>
		</p>
	</div>

	<form method="post">
		<?php wp_nonce_field( 'knd_sync_receiver_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Enable Receiver', 'knd-sync' ); ?></th>
				<td><label><input type="checkbox" name="knd_sync_receiver[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> /> <?php echo esc_html__( 'Accept sync requests', 'knd-sync' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'API Key', 'knd-sync' ); ?></th>
				<td>
					<input type="text" class="large-text code" name="knd_sync_receiver[api_key]" value="<?php echo esc_attr( (string) $settings['api_key'] ); ?>" autocomplete="off" />
					<p class="description"><?php echo esc_html__( 'Send via X-KND-SYNC-KEY header only. Never put this in the URL.', 'knd-sync' ); ?></p>
					<?php submit_button( __( 'Regenerate API Key', 'knd-sync' ), 'secondary', 'knd_sync_receiver_regen_key', false ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'HMAC Secret', 'knd-sync' ); ?></th>
				<td>
					<input type="text" class="large-text code" name="knd_sync_receiver[hmac_secret]" value="<?php echo esc_attr( (string) $settings['hmac_secret'] ); ?>" autocomplete="off" />
					<label><input type="checkbox" name="knd_sync_receiver[hmac_required]" value="1" <?php checked( ! empty( $settings['hmac_required'] ) ); ?> /> <?php echo esc_html__( 'Require HMAC signature', 'knd-sync' ); ?></label>
					<?php submit_button( __( 'Regenerate HMAC Secret', 'knd-sync' ), 'secondary', 'knd_sync_receiver_regen_hmac', false ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Allowed Source Hosts', 'knd-sync' ); ?></th>
				<td>
					<textarea name="knd_sync_receiver[allowed_source_hosts]" rows="4" class="large-text code"><?php echo esc_textarea( $hosts ); ?></textarea>
					<p class="description"><?php echo esc_html__( 'One host per line, e.g. knddecor.com', 'knd-sync' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Taxonomies', 'knd-sync' ); ?></th>
				<td>
					<label><input type="checkbox" name="knd_sync_receiver[sync_categories]" value="1" <?php checked( ! empty( $settings['sync_categories'] ) ); ?> /> <?php echo esc_html__( 'Sync categories', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_receiver[sync_tags]" value="1" <?php checked( ! empty( $settings['sync_tags'] ) ); ?> /> <?php echo esc_html__( 'Sync tags', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_receiver[create_missing_terms]" value="1" <?php checked( ! empty( $settings['create_missing_terms'] ) ); ?> /> <?php echo esc_html__( 'Create missing terms', 'knd-sync' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Media', 'knd-sync' ); ?></th>
				<td>
					<label><input type="checkbox" name="knd_sync_receiver[sync_featured_image]" value="1" <?php checked( ! empty( $settings['sync_featured_image'] ) ); ?> /> <?php echo esc_html__( 'Featured image', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_receiver[sync_content_images]" value="1" <?php checked( ! empty( $settings['sync_content_images'] ) ); ?> /> <?php echo esc_html__( 'Content images', 'knd-sync' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'SEO (Rank Math)', 'knd-sync' ); ?></th>
				<td>
					<label><input type="checkbox" name="knd_sync_receiver[sync_seo_meta]" value="1" <?php checked( ! empty( $settings['sync_seo_meta'] ) ); ?> /> <?php echo esc_html__( 'Sync SEO meta', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_receiver[sync_seo_canonical]" value="1" <?php checked( ! empty( $settings['sync_seo_canonical'] ) ); ?> /> <?php echo esc_html__( 'Sync canonical URL (off by default)', 'knd-sync' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Elementor', 'knd-sync' ); ?></th>
				<td>
					<label><input type="checkbox" name="knd_sync_receiver[sync_elementor]" value="1" <?php checked( ! empty( $settings['sync_elementor'] ) ); ?> /> <?php echo esc_html__( 'Allow Elementor data overwrite (dangerous)', 'knd-sync' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Publishing', 'knd-sync' ); ?></th>
				<td>
					<label><input type="checkbox" name="knd_sync_receiver[force_draft]" value="1" <?php checked( ! empty( $settings['force_draft'] ) ); ?> /> <?php echo esc_html__( 'Force incoming posts to Draft', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_receiver[preserve_dates]" value="1" <?php checked( ! empty( $settings['preserve_dates'] ) ); ?> /> <?php echo esc_html__( 'Preserve source publish dates', 'knd-sync' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Fallback Author', 'knd-sync' ); ?></th>
				<td>
					<select name="knd_sync_receiver[fallback_author]">
						<?php foreach ( $users as $user ) : ?>
							<option value="<?php echo esc_attr( (string) $user->ID ); ?>" <?php selected( (int) $settings['fallback_author'], (int) $user->ID ); ?>>
								<?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Uninstall', 'knd-sync' ); ?></th>
				<td>
					<label><input type="checkbox" name="knd_sync_receiver[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> /> <?php echo esc_html__( 'Delete sync data on uninstall (default OFF)', 'knd-sync' ); ?></label>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save Settings', 'knd-sync' ), 'primary', 'knd_sync_receiver_save' ); ?>
	</form>
</div>
