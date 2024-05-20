<?php

namespace ReallySpecific\ContentSync;

class Client {

	public static function attach_menu() {
		add_submenu_page(
			null,
			__( 'Sync Post', 'content-sync' ),
			__( 'Sync Post', 'content-sync' ),
			'manage_options',
			'content-sync-post',
			[ static::class, 'page' ],
		);
		//add_action( 'admin_init', __NAMESPACE__ . '\save' );
	}

	public static function page() {

		$post_id = filter_input( INPUT_GET, 'post_id', FILTER_VALIDATE_INT );
		if ( ! $post_id ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

	}

}