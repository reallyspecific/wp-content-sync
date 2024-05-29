<?php

namespace ReallySpecific\ContentSync\Integration;

use ReallySpecific\ContentSync\Settings;

function install() {

	if ( ! Settings\get( 'import_url' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	add_filter( 'page_row_actions', __NAMESPACE__ . '\add_sync_link', 10, 2 );

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