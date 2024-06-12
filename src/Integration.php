<?php

namespace ReallySpecific\WP_ContentSync\Integration;

use ReallySpecific\WP_Util as Util;
use ReallySpecific\WP_Util\Settings;

Util\class_loader('Settings');

function install( Util\Plugin $plugin ) {

	add_global_settings( $plugin );

}

function add_global_settings( Util\Plugin $plugin ) {

	$plugin->add_new_settings(
		menu_title: __( 'Content Sync', $plugin->i18n_domain ),
		props:      [ 'parent' => 'tools.php' ]
	);
	$plugin->settings()->add_section(
		'import',
		[
			'title'  => __( 'Destination / import settings', $plugin->i18n_domain ),
			'fields' => [
				[
					'name'        => 'import_url',
					'label'       => __( 'Source site', $plugin->i18n_domain ),
					'description' => __( 'Synchronization happens via client-side AJAX, so development URLs are allowed.', $plugin->i18n_domain ),
				],[
					'name'        => 'import_token',
					'label'       => __( 'Access Token', $plugin->i18n_domain ),
					'description' => __( 'Use either a WordPress user application password in the format <code>username:password</code>, or a global access token created on the source site.', $plugin->i18n_domain ),
				],
			],
		]
	);
	$plugin->settings()->add_section(
		 'export',
		[
			'title' => __( 'Source / export settings', $plugin->i18n_domain ),
			'fields' => [
				[
					'name'        => 'export_enabled',
					'type'        => 'checkbox',
					'label'       => __( 'Enable export', $plugin->i18n_domain ),
					'description' => __( 'Enable export to other sites', $plugin->i18n_domain ),
				],[
					'name'        => 'export_referrer_whitelist',
					'type'        => 'textarea',
					'label'       => __( 'Allowed referring websites', $plugin->i18n_domain ),
					'description' => 
						__( 'One per line, enter <code>*</code> to allow from all.', $plugin->i18n_domain ) . 
						' <em><strong>' .
						__( 'This can be easily spoofed by a malicious party. A safer alternative is to use the below IP filter instead.', $plugin->i18n_domain ) .
						'</strong></em>',
				],[
					'name'        => 'export_ip_whitelist',
					'type'        => 'textarea',
					'label'       => __( 'Allowed incoming IPs', $plugin->i18n_domain ),
					'description' =>
						__( 'Leave blank to allow from all. (Not recommended.)', $plugin->i18n_domain ) . 
						'<br>' .
						__( '', $plugin->i18n_domain ),
				],[
					'name'        => 'export_token',
					'label'       => __( 'Access Token', $plugin->i18n_domain ),
					'description' => __( 'Use either a WordPress user application password in the format <code>username:password</code>, or a global access token created on the source site.', $plugin->i18n_domain ),
				],
			],
		]
	);
	$plugin->settings()->add_filter( 'rs_util_settings_render_field_export_token', function( $rendered ) use ( $plugin ) {
		return $rendered
		   .  '<button type="button" class="button" id="refresh-access-token">'
		   . __( 'Refresh Token', $plugin->i18n_domain )
		   . '</button>';
	} );
	$plugin->settings()->add_action( 'rs_util_settings_render_form_afterend', function() {
		?>
		<script>
			document.getElementById('refresh-access-token').addEventListener( 'click', e => {
				var token = Array.from(Array(30), () => Math.floor(Math.random() * 36).toString(36)).join('');
				e.target.closest('td').querySelector('input').value = token;
			});
		</script>
		<?php
	} );
}

function allowed_post_types() {
	return [ 'post', 'page' ];
}