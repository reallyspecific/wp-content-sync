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
	public const REQUIRED_CAPABILITY = 'edit_others_posts';

	public function __construct( Util\Plugin $plugin ) {
		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		}
		if ( current_user_can( self::REQUIRED_CAPABILITY ) ) {
			add_action( 'rest_api_init', [ $this, 'register_routes' ] );
			add_filter( 'page_row_actions', [ $this, 'add_sync_link' ], 10, 2 );
		}

		add_action( 'content_sync_image_replacements', [ static::class, 'add_image_id_replacements' ], 10, 3 );

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
				'callback' => [ $this, 'import' ],
				'permission_callback' => [ $this, 'check_import_permissions' ],
			]
		);
		register_rest_route(
			'content-sync/v1',
			'/import/image',
			[
				'methods' => 'POST',
				'callback' => [ $this, 'import_image' ],
				'permission_callback' => [ $this, 'check_import_permissions' ],
			]
		);
	}

	function admin_menu() {
		$this->hook = add_submenu_page(
			'options.php',
			__( 'Sync Post', $this->plugin->i18n_domain ),
			__( 'Sync Post', $this->plugin->i18n_domain ),
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

		$settings = get_post_meta( $post_id, '_content_sync_import_settings', true ) ?: [];

		?>
		<div id="page">
			<h1 class="wp-heading-inline">
				<?php _e( 'Synchronize Post', $this->plugin->i18n_domain ); ?>
			</h1>
			<hr class="wp-header-end">
			<p>Copying: <?php echo $post->post_title; ?>;
				<strong>ID: <?php echo $post->ID; ?></strong>;
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
				data-rest-auth="<?php echo esc_attr( $auth ); ?>"
				data-post-id="<?php echo esc_attr( $post->ID ); ?>">
				<input type="hidden" name="post_id" value="<?php echo $post->ID; ?>">
				<?php wp_nonce_field( 'content-sync-post', '_content_sync_nonce' ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th>
								<label for="post_id_name"><?php _e( 'Post ID or slug', $this->plugin->i18n_domain ); ?></label>
							</th>
							<td>
								<input type="text" id="post_id_name" name="post_id_name" value="<?php echo $settings['post_id_name'] ?? $post->ID; ?>">
							</td>
						</tr>
						<tr <?php if ( ! wp_revisions_enabled( $post ) ) echo 'class="is-style-disabled"'; ?>>
							<th>
								<label for="create_revision"><?php _e( 'Create revision', $this->plugin->i18n_domain ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="create_revision" name="create_revision" <?php
									if ( wp_revisions_enabled( $post ) ) {
										echo ( $settings['create_revision'] ?? true ) ? 'checked' : '';
									} else {
										echo 'disabled';
									}
								?>>
								<label for="create_revision"><?php _e( 'If checked, the existing post will be saved as a revision', $this->plugin->i18n_domain ); ?></label>
							</td>
						</tr>
						<tr>
							<th>
								<label for="import_media"><?php _e( 'Import new media', $this->plugin->i18n_domain ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="import_media" name="import_media" <?php checked( $settings['import_media'] ?? true ); ?>>
								<label><?php _e( 'Download images attached to the source post into the local media library.', $this->plugin->i18n_domain ); ?></label>
							</td>
						</tr>
						<tr>
							<th>
								<label for="replace_media"><?php _e( 'Replace existing media', $this->plugin->i18n_domain ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="replace_media" name="replace_media" <?php checked( $settings['replace_media'] ?? false ); ?>>
								<label><?php _e( 'When matching file paths are found, replace the local media with the source media', $this->plugin->i18n_domain ); ?></label>
							</td>
						</tr>
						<tr>
							<th>
								<label for="replace_media"><?php _e( 'Match media objects', $this->plugin->i18n_domain ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="replace_media_ids" name="replace_media_ids" <?php checked( $settings['replace_media_ids'] ?? false ); ?>>
								<label><?php _e( 'Post IDs of imported media will replace source IDs from imported page. (Use with caution!)', $this->plugin->i18n_domain ); ?></label>
							</td>
						</tr>
						<tr>
							<th>
								<label for="replace_meta"><?php _e( 'Replace meta', $this->plugin->i18n_domain ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="replace_meta" name="replace_meta" <?php checked( $settings['replace_meta'] ?? true ); ?>><label for="replace_meta"><?php _e( 'Destroy meta keys that do not exist on the source post', $this->plugin->i18n_domain ); ?></label>
								<p><em><?php _e( 'If unchecked, existing meta keys will still be replaced with values from the source post, but keys that do not exist on the source post will be left in tact.', $this->plugin->i18n_domain ); ?></em></p>
							</td>
						</tr>
						<tr class="is-style-disabled">
							<th>
								<label for="import_terms"><?php _e( 'Import taxonomy relationships', $this->plugin->i18n_domain ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="import_terms" name="import_terms" disabled>
								<label for="import_terms"><?php _e( 'Import terms from the source post', $this->plugin->i18n_domain ); ?></label>
								<p><em><?php _e( 'If checked, the existing post relationships will be destroyed and replaced with the terms found on the source post. Terms that do not exist locally will be created.', $this->plugin->i18n_domain ); ?></em></p>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<input data-action="download" type="button" class="button button-primary" value="Download">
					<input data-action="preview" type="button" class="button button-primary" value="Preview" disabled>
					<input data-action="edit" type="button" class="button button-primary" value="Edit draft/Publish" disabled>
					<!--input data-action="publish" type="button" class="button button-primary" value="Publish" disabled --><br>
					<label for="save-for-later"><input name="save_settings" type="checkbox" id="save-for-later"> <?php _e( 'Save import settings for the next time (saves on \'Download\')', $this->plugin->i18n_domain ); ?></label>
				</p>
			</form>
			<script src="<?php echo $this->plugin->get_url( 'assets/js/sync.js' ); ?>"></script>
			<link rel="stylesheet" type="text/css" href="<?php echo $this->plugin->get_url( 'assets/css/sync.css' ); ?>"></link>
			<?php endif; ?>

			<div class="content-sync-status-area">
				<div id="cs-status-text" class="content-sync-status-area__status-text"></div>
				<div id="cs-images" class="content-sync-status-area__images"></div>
			</div>
		</div>

		<?php

	}

	public function check_import_permissions() {
		return current_user_can( self::REQUIRED_CAPABILITY );
	}


	public function import( \WP_REST_Request $request ) {
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
		$remote_url = $this->plugin->get_setting( key: 'import_url' ) ?: $request->get_param('source_url');
		if ( ! $remote_url ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'No import URL set' ] );
		}
		$remote_url = $this->normalize_url( $this->plugin->get_setting( key: 'import_url' ) ?: $request->get_param('source_url') );
		$this_url   = $this->normalize_url( get_bloginfo( 'url' ) );

		$content = json_decode( $request->get_param( 'content' ), true );
		if ( ! $content || empty( $content['post'] ) ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'No content to publish' ] );
		}

		$images = json_decode( $request->get_param( 'images' ), true );

		$new_post = $content['post'];
		$new_post['post_ID'] = $post['ID'];
		unset( $new_post['ID'] );
		unset( $new_post['post_parent'] );
		unset( $new_post['guid'] );

		if ( $new_post['post_type'] !== $post['post_type'] && empty( $request->get_param( 'ignore_post_type' ) ) ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'The post type of the new post does not match the source content. If this is intentional, you can enable the \'Ignore post type\' setting.' ] );
		}

		$import_settings = [];
		$import_settings['post_id_name'] = $request->get_param( 'post_id_name' );
		$import_settings['create_revision'] = filter_var( $request->get_param( 'create_revision' ), FILTER_VALIDATE_BOOLEAN );
		$import_settings['import_media'] = filter_var( $request->get_param( 'import_media' ), FILTER_VALIDATE_BOOLEAN );
		$import_settings['replace_media'] = filter_var( $request->get_param( 'replace_media' ), FILTER_VALIDATE_BOOLEAN );
		$import_settings['replace_meta'] = filter_var( $request->get_param( 'replace_meta' ), FILTER_VALIDATE_BOOLEAN );
		$import_settings['import_terms'] = filter_var( $request->get_param( 'import_terms' ), FILTER_VALIDATE_BOOLEAN );

		// ok time to move forward
		if ( $request->get_param( 'save_settings' ) ) {
			update_post_meta( $post['ID'], '_content_sync_import_settings', $import_settings );
		}
		$import_settings = apply_filters( 'content_sync_import_settings', $import_settings, $request, $post );

		foreach( $images as $image ) {
			if ( ! empty( $image['replaceWith'] ) ) {
				$replacements[] = [ 'find' => $image['match'], 'replace' => $image['replaceWith'], ];
			}
		}
		$replacements[] = [ 'find' => $remote_url, 'replace' => $this_url, ];
		$replacements   = apply_filters( 'content_sync_image_replacements', $replacements, $request, $post );

		// wp_create_post_autosave runs wp_unslash on the content and messes up stuff in gutenberg meta. wp_slash rescues us.
		$new_post['post_content'] = wp_slash( $this->recursive_find_replace( $new_post['post_content'], $replacements ) );

		include_once ABSPATH . "/wp-admin/includes/post.php";

		$preview_post = \wp_create_post_autosave( $new_post );
		if ( is_wp_error( $preview_post ) ) {
			return rest_ensure_response( [ 'success' => false, 'message' => $preview_post->get_error_message() ] );
		}

		$this->import_meta( $preview_post, $content['meta'], $replacements, $import_settings['replace_meta'] );
		
		if ( $request->get_param( 'save_settings' ) ) {
			update_post_meta( $preview_post, '_content_sync_import_settings', $import_settings );
		}

		if ( $import_settings['import_terms'] ) {

		}

		$preview_url = get_preview_post_link( $post['ID'] );
		$preview_url = add_query_arg( 'preview_id', $preview_post, $preview_url );
		$preview_url = add_query_arg( 'preview_nonce', wp_create_nonce( 'post_preview_' . $preview_post ), $preview_url );

		return [ 
			'status' => 'ok',
			'preview_url' => $preview_url,
			'preview_id' => $preview_post,
			'edit_url' => get_edit_post_link( $preview_post ),
		];
	}

	private function import_meta( $post_id, $new_meta, $replacements, $replace_meta = true ) {
		
		if ( empty( $new_meta ) ) {
			return;
		}

		$old_meta = get_post_meta( $post_id );

		$meta = [];
		foreach( $new_meta as $key => $values ) {
			$new_values = [];
			foreach( $values as $value ) {
				$new_values[] = $this->recursive_find_replace( $value, $replacements );
			}
			$meta[ $key ] = $new_values;
		}
		// loop through original meta and replace or remove as needed
		foreach( $old_meta as $key => $values ) {
			if ( $replace_meta || isset( $meta[ $key ] ) ) {
				delete_metadata( 'post', $post_id, $key );
			}
		}
		foreach( $meta as $key => $values ) {
			foreach( $values as $value ) {
				add_metadata( 'post', $post_id, $key, $value );
			}
		}

	}

	public function import_image( \WP_REST_Request $request ) {
		/*$post = $request->get_param( 'preview_id' );
		if ( ! $post ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'Post ID not sent' ] );
		}
		$post = get_post( absint( $post ), ARRAY_A, 'raw' );
		if ( ! $post ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'Post preview not found' ] );
		}*/

		$post   = $request->get_param( 'post' );
		$files  = $request->get_param( 'media' );
		$params = $request->get_param( 'params' );

		$parent           = $params['attachmentParent'] ?? 0;
		$replace_existing = $params['replaceExisting'] ?? false;

		$remote_url = $this->normalize_url( $this->plugin->get_setting( key: 'import_url' ) ?: $request->get_param('source_url') );
		$this_url   = $this->normalize_url( get_bloginfo( 'url' ) );

		if ( ! empty( $post['path'] ) ) {
			$existing_media = get_posts( [
				'post_type'   => 'attachment',
				'numberposts' => -1,
				'meta_query' => [
					[
						'key' => '_wp_attached_file',
						'value' => $post['path']
					]
				]
			] );
		}

		$rel_path = trailingslashit( dirname( $post['path'] ) );

		if ( empty( $existing_media ) ) {
			// insert attachment
			$new_post = [
				'post_date'         => $post['post']['post_date'],
				'post_date_gmt'     => $post['post']['post_date_gmt'],
				'post_modified'     => $post['post']['post_modified'],
				'post_modified_gmt' => $post['post']['post_modified_gmt'],
				'post_title'        => $post['post']['post_title'],
				'post_name'         => $post['post']['post_name'],
				'post_content'      => $post['post']['post_content'],
				'post_status'       => $post['post']['post_status'],
				'post_mime_type'    => $post['post']['post_mime_type'],
				'post_type'         => 'attachment',
				'post_parent'       => $parent,
			];
			$attachment_id = wp_insert_attachment( $new_post, false, $parent );
			update_post_meta( $attachment_id, '_wp_attached_file', $post['path'] );
		} else {
			$attachment_id = $existing_media[0]->ID;
		}

		if ( empty( $existing_media ) || $replace_existing ) {
			$replacements = $remote_url ? [[ 'find' => $remote_url, 'replace' => $this_url, ]] : [];
			$this->import_meta( $attachment_id, $post['meta'] ?? [], $replacements, false );
		}

		$upload_dir = wp_upload_dir();
		if ( ! is_dir( $upload_dir['basedir'] ) ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'Upload directory not found' ] );
		}

		$upload_path = trailingslashit( dirname( trailingslashit( $upload_dir['basedir'] ) . $post['path'] ) );
		$path_exists = Util\Filesystem\recursive_mk_dir( $upload_path );
		if ( ! $path_exists ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'Could not create upload directory.' ] );
		}

		// todo: ok now upload the files sent
		$uploaded = [];
		foreach( $files as $thumbnail_size => $file ) {
			$filename  = basename( $file['url'] );
			$decoded   = base64_decode( $file['data'] );
			$new_path  = $upload_path . $filename;
			$preexists = (bool) file_exists( $new_path );
			$uploaded[$thumbnail_size] = [
				'filename' => $filename,
				'replaced' => $preexists && $replace_existing,
				'relpath'  => $rel_path . $filename,
				'absurl'   => trailingslashit( $upload_dir['baseurl'] ) . $rel_path . $filename,
			];
			if ( ! $preexists || $replace_existing ) {
				if ( file_put_contents( $new_path, $decoded ) ) {
					$uploaded[$thumbnail_size]['status'] = 'ok';
				} else {
					$uploaded[$thumbnail_size]['status'] = 'failed';
				}
			}
			
		}

		return [ 'status' => 'ok', 'uploaded' => $uploaded, 'attachmentId' => $attachment_id ];
	}

	private function recursive_find_replace( $value, $replacements ) {
		$json = json_decode( $value );
		$is_json = json_last_error() === JSON_ERROR_NONE;
		if ( ! $is_json ) {
			$value = maybe_unserialize( $value );
		}
		foreach( $replacements as $match ) {
			if ( is_array( $value ) ) {
				array_walk_recursive( $value, function( &$node ) use ( $match ) {
					$node = str_replace( $match['find'], $match['replace'], $node );
				} );
			} else {
				$value = str_replace( $match['find'], $match['replace'], $value );
			}
		}
		if ( $is_json ) {
			$value = json_encode( $json, true );
		}
		return $value;
	}

	private function normalize_url( $url ) {

		$url = str_replace( [ 'http://', 'https://' ], '//', $url );
		if ( substr( $url , 0, 2 ) !== '//' ) {
			$url = '//' . $url;
		}
		$url = untrailingslashit( $url );
		return $url;

	}

	/**
	 * Adds a 'sync' link to the list of post row actions if the post type is allowed.
	 * Implemented as a filter of page_row_actions
	 *
	 * @param array $actions The list of post row actions.
	 * @param \WP_Post $post The post object.
	 * @return array The modified list of post row actions.
	 */
	public function add_sync_link( $actions, $post = null ) {
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
			__( 'Sync', $this->plugin->i18n_domain )
		);
		return $actions;
	}

	public static function add_image_id_replacements( array $replacements, \WP_REST_Request $request ) {

		$images = json_decode( $request->get_param( 'images' ), true );
		if ( ! $images ) {
			return $replacements;
		}
		foreach( $images as $image ) {
			$find    = $image['sourcePost'] ?? null;
			$replace = $image['localPost'] ?? null;
			if ( ! $find || ! $replace ) {
				continue;
			}
			$replacements[] = [
				'find'    => "wp-image-$find ",
				'replace' => "wp-image-$replace ",
			];
			$replacements[] = [
				'find'    => "wp-image-$find\"",
				'replace' => "wp-image-$replace\"",
			];
			$replacements[] = [
				'find'    => "\"id\":$find,",
				'replace' => "\"id\":$replace,",
			];
			$replacements[] = [
				'find'    => "\"id\":$find}",
				'replace' => "\"id\":$replace}",
			];
			$replacements[] = [
				'find'    => "\"id\":\"$find\"",
				'replace' => "\"id\":\"$replace\"",
			];
			$replacements[] = [
				'find'    => "\"image\":$find,",
				'replace' => "\"image\":$replace,",
			];
			$replacements[] = [
				'find'    => "\"image\":$find}",
				'replace' => "\"image\":$replace}",
			];
			$replacements[] = [
				'find'    => "\"image\":\"$find\"",
				'replace' => "\"image\":\"$replace\"",
			];
		}
		return $replacements;

	}

}
