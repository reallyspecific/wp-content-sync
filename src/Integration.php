<?php

namespace ReallySpecific\WP_ContentSync\Integration;

use ReallySpecific\WP_Util as Util;
use ReallySpecific\WP_Util\Settings;

Util\class_loader('Settings');

function install( Util\Plugin $plugin ) {

	if ( current_user_can( 'manage_options' ) ) {
		if ( $plugin->get_setting( key: 'import_url' ) ) {
			add_filter( 'page_row_actions', __NAMESPACE__ . '\add_sync_link', 10, 2 );
		}
	}

	add_global_settings_page( $plugin );
}

function add_global_settings_page( Util\Plugin $plugin ) {

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

/**
 * Adds a 'sync' link to the list of post row actions if the post type is allowed.
 * Implemented as a filter of page_row_actions
 *
 * @param array $actions The list of post row actions.
 * @param \WP_Post $post The post object.
 * @return array The modified list of post row actions.
 */
function add_sync_link( $actions, $post = null ) {
	if ( ! $post ) {
		return $actions;
	}
	if ( ! in_array( $post->post_type, allowed_post_types() ) ) {
		return $actions;
	}
	$actions['sync'] = sprintf(
		'<a href="%s" data-content-sync="%d">%s</a>',
		admin_url( 'admin.php?page=content-sync-post&post_id=' . $post->ID ),
		$post->ID,
		__( 'Sync', 'content-sync' )
	);
	return $actions;
}

function allowed_post_types() {
	return [ 'post', 'page' ];
}