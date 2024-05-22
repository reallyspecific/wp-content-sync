<?php

namespace ReallySpecific\ContentSync\Settings;

function install() {

	add_submenu_page(
		'tools.php',
		__( 'Content Sync', 'content-sync' ),
		__( 'Content Sync', 'content-sync' ),
		'manage_options',
		'content-sync-settings',
		 __NAMESPACE__ . '\render',
	);

	add_action( 'admin_init', __NAMESPACE__ . '\save' );

}

function save() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ( $_GET['page'] ?? '' ) !== 'content-sync-settings' ){
		return;
	}

	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'content-sync-settings' ) ) {
		return;
	}

	$settings = [
		'import_url' => sanitize_text_field( $_POST['source-url'] ?? '' ),
		'import_token' => sanitize_text_field( $_POST['source-token'] ?? '' ),
		'export_whitelist' => sanitize_text_field( $_POST['destination-whitelist'] ?? '' ),
		'export_token' => sanitize_text_field( $_POST['destination-token'] ?? '' ),
		'export_enabled' => isset( $_POST['destination-enabled'] ) ? 'true' : '',
	];

	update_option( 'content-sync-settings', $settings, false );

}

function get( ?string $key = null ) {
	$settings = wp_cache_get( 'content-sync-settings' ) ?: get_option( 'content-sync-settings' ) ?: [];
	return $settings[ $key ] ?? null;
}

function render() {

	$settings = get_option( 'content-sync-settings' );

	?>
	<div class="wrap">
		<h2><?php _e( 'Content Sync Settings', 'content-sync' ); ?></h2>
		<form method="post" action="admin.php?page=content-sync-settings">
			<?php wp_nonce_field( 'content-sync-settings' ); ?>
			<h3><?php _e( 'Content import settings', 'content-sync' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="source-url"><?php _e( 'Source site', 'content-sync' ); ?></label>
					</th>
					<td>
						<input type="text" name="source-url" id="source-url" value="<?php echo esc_attr( $settings['import_url'] ?? '' ); ?>" class="regular-text" placeholder="https://staging-site.website.example">
						<p>Synchronization happens via client-side AJAX, so development URLs are allowed.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="source-token"><?php _e( 'Access Token', 'content-sync' ); ?></label>
					</th>
					<td>
						<input type="text" name="source-token" id="source-token" value="<?php echo esc_attr( $settings['import_token'] ?? '' ); ?>" class="regular-text">
						<p>Use either a WordPress user application password in the format <code>username:password</code>, or a global access token created on the source site.</p>
					</td>
				</tr>
			</table>
			<hr>
			<h3><?php _e( 'Source server / export settings', 'content-sync' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="destination-url"><?php _e( 'Enable export', 'content-sync' ); ?></label>
					</th>
					<td>
						<input type="checkbox" <?php checked( $settings['export_enabled'] ?? '', 'true' ); ?> name="destination-enabled" id="destination-enabled">
						<label for="destination-enabled"><?php _e( 'Enable export to other sites', 'content-sync' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="destination-whitelist"><?php _e( 'Allowed websites', 'content-sync' ); ?></label>
					</th>
					<td>
						<textarea rows="4" name="destination-whitelist" id="destination-whitelist" value="<?php echo esc_attr( $settings['export_whitelist'] ?? '' ); ?>" class="regular-text">

						</textarea>
						<p>One per line, enter <code>*</code> to allow from all. (Not recommended.)</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="destination-token"><?php _e( 'Global Access Token', 'content-sync' ); ?></label>
					</th>
					<td>
						<input style="font-family:monospace" size="30" type="text" name="destination-token" id="destination-token" value="<?php echo esc_attr( $settings['export_token'] ?? '' ); ?>" class="regular-text"> <button type="button" class="button" id="refresh-access-token">Regenerate</button>
						<p>Leave blank to only allow exports from registered users with appropriate access tokens.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<script>
			document.getElementById('refresh-access-token').addEventListener('click', function() {
				var token = Array.from(Array(30), () => Math.floor(Math.random() * 36).toString(36)).join('');
				document.getElementById('destination-token').value = token;
			});
		</script>
	</div>
	<?php
}