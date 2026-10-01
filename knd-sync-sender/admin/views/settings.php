<?php
/**
 * Sender settings view.
 *
 * @package KND_Sync_Sender
 * @var array<string, mixed> $settings
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hosts = implode( "\n", (array) ( $settings['source_hosts'] ?? array() ) );
$https_ok = ! empty( $settings['target_url'] ) && str_starts_with( strtolower( (string) $settings['target_url'] ), 'https://' );
?>
<div class="wrap knd-sync-wrap">
	<h1><?php echo esc_html__( 'KND Sync', 'knd-sync' ); ?></h1>
	<?php settings_errors( 'knd_sync_sender' ); ?>

	<div class="knd-sync-status-card">
		<p><strong><?php echo esc_html__( 'Automatic Sync', 'knd-sync' ); ?>:</strong>
			<?php echo ! empty( $settings['enabled'] ) ? 'ON' : 'OFF'; ?>
		</p>
		<p><strong><?php echo esc_html__( 'Target', 'knd-sync' ); ?>:</strong>
			<code><?php echo esc_html( (string) $settings['target_url'] ); ?></code>
		</p>
		<p><strong><?php echo esc_html__( 'Internal Link Rewrite', 'knd-sync' ); ?>:</strong>
			<?php echo ! empty( $settings['rewrite_internal_links'] ) ? 'ON' : 'OFF'; ?>
		</p>
		<p><strong><?php echo esc_html__( 'Media Sync', 'knd-sync' ); ?>:</strong>
			<?php echo ( ! empty( $settings['sync_featured_image'] ) || ! empty( $settings['sync_content_images'] ) ) ? 'ON' : 'OFF'; ?>
		</p>
		<p><strong><?php echo esc_html__( 'TEST MODE', 'knd-sync' ); ?>:</strong>
			<?php echo ! empty( $settings['test_mode'] ) ? 'ON' : 'OFF'; ?>
		</p>
		<?php if ( ! $https_ok ) : ?>
			<p class="knd-sync-warning"><?php echo esc_html__( 'Warning: Target URL is not HTTPS. Sensitive data must not be sent over HTTP in production.', 'knd-sync' ); ?></p>
		<?php endif; ?>
		<p>
			<button type="button" class="button button-secondary" id="knd-sync-test-connection"><?php echo esc_html__( 'Test Connection', 'knd-sync' ); ?></button>
			<span id="knd-sync-test-result" aria-live="polite"></span>
		</p>
	</div>

	<form method="post">
		<?php wp_nonce_field( 'knd_sync_sender_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Enable Sync', 'knd-sync' ); ?></th>
				<td><label><input type="checkbox" name="knd_sync_sender[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> /> <?php echo esc_html__( 'Enable automatic synchronization', 'knd-sync' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Target URL', 'knd-sync' ); ?></th>
				<td><input type="url" class="regular-text code" name="knd_sync_sender[target_url]" value="<?php echo esc_attr( (string) $settings['target_url'] ); ?>" placeholder="https://knd-home.com" required /></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'API Key', 'knd-sync' ); ?></th>
				<td><input type="text" class="large-text code" name="knd_sync_sender[api_key]" value="<?php echo esc_attr( (string) $settings['api_key'] ); ?>" autocomplete="off" /></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'HMAC', 'knd-sync' ); ?></th>
				<td>
					<label><input type="checkbox" name="knd_sync_sender[hmac_enabled]" value="1" <?php checked( ! empty( $settings['hmac_enabled'] ) ); ?> /> <?php echo esc_html__( 'Sign requests with HMAC', 'knd-sync' ); ?></label><br />
					<input type="text" class="large-text code" name="knd_sync_sender[hmac_secret]" value="<?php echo esc_attr( (string) $settings['hmac_secret'] ); ?>" placeholder="<?php echo esc_attr__( 'HMAC secret (must match receiver)', 'knd-sync' ); ?>" autocomplete="off" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Triggers', 'knd-sync' ); ?></th>
				<td>
					<label><input type="checkbox" name="knd_sync_sender[sync_on_publish]" value="1" <?php checked( ! empty( $settings['sync_on_publish'] ) ); ?> /> <?php echo esc_html__( 'Sync on Publish', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_sender[sync_on_update]" value="1" <?php checked( ! empty( $settings['sync_on_update'] ) ); ?> /> <?php echo esc_html__( 'Sync on Update', 'knd-sync' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Content Options', 'knd-sync' ); ?></th>
				<td>
					<label><input type="checkbox" name="knd_sync_sender[sync_featured_image]" value="1" <?php checked( ! empty( $settings['sync_featured_image'] ) ); ?> /> <?php echo esc_html__( 'Sync Featured Image', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_sender[sync_content_images]" value="1" <?php checked( ! empty( $settings['sync_content_images'] ) ); ?> /> <?php echo esc_html__( 'Sync Content Images', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_sender[sync_categories]" value="1" <?php checked( ! empty( $settings['sync_categories'] ) ); ?> /> <?php echo esc_html__( 'Sync Categories', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_sender[sync_tags]" value="1" <?php checked( ! empty( $settings['sync_tags'] ) ); ?> /> <?php echo esc_html__( 'Sync Tags', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_sender[rewrite_internal_links]" value="1" <?php checked( ! empty( $settings['rewrite_internal_links'] ) ); ?> /> <?php echo esc_html__( 'Rewrite Internal Links', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_sender[sync_seo_meta]" value="1" <?php checked( ! empty( $settings['sync_seo_meta'] ) ); ?> /> <?php echo esc_html__( 'Sync Rank Math SEO Meta', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_sender[sync_seo_canonical]" value="1" <?php checked( ! empty( $settings['sync_seo_canonical'] ) ); ?> /> <?php echo esc_html__( 'Sync Canonical URL (off by default)', 'knd-sync' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Source Hosts', 'knd-sync' ); ?></th>
				<td><textarea name="knd_sync_sender[source_hosts]" rows="3" class="large-text code"><?php echo esc_textarea( $hosts ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Target Domain', 'knd-sync' ); ?></th>
				<td><input type="text" class="regular-text code" name="knd_sync_sender[target_host]" value="<?php echo esc_attr( (string) $settings['target_host'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Retry Count', 'knd-sync' ); ?></th>
				<td><input type="number" min="1" max="10" name="knd_sync_sender[retry_count]" value="<?php echo esc_attr( (string) (int) $settings['retry_count'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Safety', 'knd-sync' ); ?></th>
				<td>
					<label><input type="checkbox" name="knd_sync_sender[test_mode]" value="1" <?php checked( ! empty( $settings['test_mode'] ) ); ?> /> <?php echo esc_html__( 'TEST MODE (forces draft status on target)', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_sender[sync_as_draft]" value="1" <?php checked( ! empty( $settings['sync_as_draft'] ) ); ?> /> <?php echo esc_html__( 'Sync as Draft', 'knd-sync' ); ?></label><br />
					<label><input type="checkbox" name="knd_sync_sender[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> /> <?php echo esc_html__( 'Delete Sync Data on Uninstall (default OFF)', 'knd-sync' ); ?></label>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save Settings', 'knd-sync' ), 'primary', 'knd_sync_sender_save' ); ?>
	</form>
</div>
