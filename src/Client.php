<?php

namespace ReallySpecific\WP_ContentSync;

use ReallySpecific\WP_Util as Util;
use ReallySpecific\WP_ContentSync\Integration;
use ReallySpecific\WP_ContentSync\Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Client {

	private $plugin = null;

	private $hook = null;

	// todo: change edit_posts to sync_posts
	public const REQUIRED_CAPABILITY = 'edit_posts';

	public function __construct( Util\Plugin $plugin ) {
		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		}
		if ( current_user_can( self::REQUIRED_CAPABILITY ) ) {
			add_action( 'rest_api_init', [ $this, 'register_routes' ] );
			add_filter( 'page_row_actions', [ $this, 'add_sync_link' ], 10, 2 );
		}
		$this->plugin = &$plugin;
	}

	/**
	 * Registers the REST routes for the content sync API.
	 *
	 * @return void
	 */
	function register_routes() {
		register_rest_route(
			'content-sync/v1',
			'/import',
			[
				'methods' => 'POST',
				'callback' => __NAMESPACE__ . 'import',
				'permission_callback' => __NAMESPACE__ . 'check_import_permissions',
			]
		);
	}

	function admin_menu() {
		$this->hook = add_submenu_page(
			null,
			__( 'Sync Post', 'content-sync' ),
			__( 'Sync Post', 'content-sync' ),
			static::REQUIRED_CAPABILITY,
			'content-sync-post',
			[ $this, 'page' ],
		);
	}

	/**
	 * Returns the URL of the WP Rest API endpoint on the source server
	 *
	 * @return string
	 */
	public function get_remote_endpoint() {
		$source = $this->plugin->get_setting( key: 'import_url' );
		if ( ! $source ) {
			return false;
		}
		return untrailingslashit( $source ) . '/wp-json/content-sync/v1/export';
	}

	function page() {

		$post_id = filter_input( INPUT_GET, 'post_id', FILTER_VALIDATE_INT );
		if ( ! $post_id ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$auth = $this->plugin->get_setting( key: 'import_token' );
		$endpoint = $this->get_remote_endpoint();

		?>
		<div id="page">
			<h1 class="wp-heading-inline">
				<?php _e( 'Synchronize Post', 'content-sync' ); ?>
			</h1>
			<hr class="wp-header-end">
			<p>Copying: <?php echo $post->post_title; ?> 
				<strong>ID: <?php echo $post->ID; ?></strong>
				<strong>slug: <?php echo $post->post_name; ?></strong>
			</p>

			<?php if ( ! $auth || ! $endpoint ) : ?>
			<p>Synchronization has not been configured. Enable it in <a href="<?php admin_url( 'admin.php?page=content-sync-settings' ); ?>">Settings</a>.</p>
			<?php else : ?>
			<h2>Import Settings</h2>
			<form method="get" action="admin.php?page=content-sync-post" id="sync-post-form" 
				data-rest-local="<?php echo esc_url( rest_url( 'content-sync/v1/import' ) ); ?>"
				data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
				data-rest-source="<?php echo esc_url( $endpoint ); ?>"
				data-rest-auth="<?php echo esc_attr( $auth ); ?>">
				<input type="hidden" name="post_id" value="<?php echo $post->ID; ?>">
				<?php wp_nonce_field( 'content-sync-post' ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th>
								<label for="post_id_name"><?php _e( 'Post ID or slug', 'content-sync' ); ?></label>
							</th>
							<td>
								<input type="text" id="post_id_name" name="post_id_name" value="<?php echo $post->ID; ?>">
							</td>
						</tr>
						<tr>
							<th>
								<label for="create_revision"><?php _e( 'Create revision', 'content-sync' ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="create_revision" name="create_revision" <?php
									if ( wp_revisions_enabled( $post ) ) {
										echo 'checked';
									} else {
										echo 'disabled';
									}
								?>>
								<label for="create_revision">If checked, the existing post will be saved as a revision</label>
							</td>
						</tr>
						<tr>
							<th>
								<label for="import_media"><?php _e( 'Import new media', 'content-sync' ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="import_media" name="import_media" checked>
								<label><?php _e( 'Download images attached to the source post into the local media library', 'content-sync' ); ?></label>
							</td>
						</tr>
						<tr>
							<th>
								<label for="replace_media"><?php _e( 'Replace existing media', 'content-sync' ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="replace_media" name="replace_media">
								<label><?php _e( 'When matching file paths are found, replace the local media with the source media', 'content-sync' ); ?></label>
							</td>
						</tr>
						<tr>
							<th>
								<label for="replace_meta"><?php _e( 'Replace meta', 'content-sync' ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="replace_meta" name="replace_meta" checked><label for="replace_meta">Destroy meta keys that do not exist on the source post</label>
								<p><em>If unchecked, existing meta keys will still be replaced with values from the source post, but keys that do not exist on the source post will be left in tact.</em></p>
							</td>
						</tr>
						<tr>
							<th>
								<label for="import_terms"><?php _e( 'Import taxonomy relationships', 'content-sync' ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="import_terms" name="import_terms" checked>
								<label for="import_terms"><?php _e( 'Import terms from the source post', 'content-sync' ); ?></label>
								<p><em>If checked, the existing post relationships will be destroyed and replaced with the terms found on the source post. Terms that do not exist locally will be created.</em></p>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<input data-action="download" type="button" class="button button-primary" value="Download">
					<input data-action="preview" type="button" class="button button-primary" value="Preview" disabled>
					<input data-action="edit" type="button" class="button button-primary" value="Edit draft" disabled>
					<input data-action="publish" type="button" class="button button-primary" value="Publish" disabled>
				</p>
			</form>
			<script src="<?php echo $this->plugin->get_url( 'assets/js/sync.js' ); ?>"></s></script>
			<?php endif; ?>
		</div>
		
		<?php

	}

	function check_import_permissions() {
		return current_user_can( 'manage_options' );
	}


	function import( \WP_REST_Request $request ) {
		$post = $request->get_param( 'post_id' );
		if ( ! $post ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'Post not found' ] );
		}
		if ( is_numeric( $post ) ) {
			$post = get_post( absint( $post ), ARRAY_A );
		}
		if ( ! $post ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'Post not found' ] );
		}
		$remote_url = untrailingslashit( $this->plugin->get_setting( key: 'import_url' ) ?: $request->get_param('source_url') );
		if ( ! $remote_url ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'No import URL set' ] );
		}
		$this_url = untrailingslashit( get_bloginfo( 'url' ) );

		$content = json_decode( $request->get_param( 'content' ), ARRAY_A );
		if ( ! $content ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'No content to publish' ] );
		}
		$preview_post = wp_create_post_autosave( [
			'ID'           => $post['ID'],
			'post_content' => $content['post']['post_content'],
			'post_title'   => $content['post']['post_title'],
			'post_name'    => $content['post']['post_name'],
			'post_excerpt' => $content['post']['post_excerpt'],
		] );
		$replace_meta = filter_var( $request->get_param( 'replace_meta' ), FILTER_VALIDATE_BOOLEAN );
		$original_meta = get_post_meta( $post['ID'] );

		foreach( $content['meta'] as $key => $values ) {
			$new_values = [];
			foreach( $values as $value ) {
				// find/replace source url with local url
				$json = json_encode( $value );
				$is_json = json_last_error() === JSON_ERROR_NONE;
				if ( ! $is_json ) {
					$value = maybe_unserialize( $value );
				}
				if ( is_array( $value ) ) {
					array_walk_recursive( $value, function( &$node ) use ( $this_url, $remote_url ) {
						$node = str_replace( $remote_url, $this_url, $node );
					} );
				} else {
					$value = str_replace( $remote_url, $this_url, $value );
				}
				if ( $is_json ) {
					$value = json_encode( $json, true );
				}
				$new_values[] = $value;
			}
			$meta[ $key ] = $new_values;
		}
		// loop through original meta and replace or remove as needed
		foreach( $original_meta as $key => $values ) {

		}

		$preview_url = get_permalink( $preview_post );

		return [ 'status' => 'ok', 'preview_url' => $preview_url, 'preview_id' => $preview_post ];
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
		if ( ! in_array( $post->post_type, Integration\allowed_post_types() ) ) {
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

}
