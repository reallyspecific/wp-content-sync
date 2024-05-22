<?php

namespace ReallySpecific\ContentSync;

use ReallySpecific\Util;
use ReallySpecific\ContentSync\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

Util\maybe_load( 'Singleton' );

class Server extends Util\Singleton {

	public function __construct() {
		if ( ! filter_var( Settings\get('export_enabled'), FILTER_VALIDATE_BOOLEAN ) ) {
			return;
		}
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers the REST routes for the content sync API.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'content-sync/v1',
			'/export',
			[
				'methods' => 'GET',
				'callback' => [ $this, 'export' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args' => [
					'post' => [
						'type' => 'string',
						'description' => 'The ID or post name to retrieve',
						'required' => true
					]
				]
			]
		);
	}

	/**
	 * Check if the request is authorized using a bearer token or user application password.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function check_permissions( \WP_REST_Request $request ) {
		$token = Settings\get('export_token');
		$auth = $request->get_header('Authorization');
		if ( $token && $auth && $auth === 'Bearer ' . $token ) {
			return true;
		}

		$user     = $request->get_header('X-WP-User');
		$password = $request->get_header('X-WP-Password');
		if ( $user && $password && $user = wp_authenticate_application_password( null, $user, $password ) ) {
			if ( $user instanceof \WP_User && user_can( $user, 'manage_options' ) ) {
				return true;
			}
		}

		return new \WP_Error( 'unauthorized', 'Unauthorized', [ 'status' => 401 ] );
	}

	public function export( \WP_REST_Request $request ) {

		$post = $request->get_param( 'post' );
		if ( ! $post ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'Post not found' ] );
		}
		if ( is_numeric( $post ) ) {
			$post = get_post( absint( $post ), ARRAY_A );
		}
		if ( is_string( $post ) ) {
			$post = Util\get_post_by_slug( $post );
		}
		if ( ! $post ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'Post not found' ] );
		}
		
		$meta  = get_post_meta( $post['ID'] );
		$terms = wp_get_object_terms( $post['ID'], get_object_taxonomies( $post['post_type'] ) );
		$media_sizes = get_intermediate_image_sizes();
		$media = $this->find_embedded_media( $post['post_content'], $media_sizes, $post['ID'] );
		$meta_string = '';
		foreach( $meta as $values ) {
			$meta_string .= implode( "\n", $values ) . "\n";
		}
		$media += $this->find_embedded_media( $meta_string, $media_sizes, $post['ID'] );
		$media += $this->get_attached_media( $post['ID'], $media_sizes );

		return rest_ensure_response( [
			'post' => $post,
			'meta' => $meta,
			'terms' => $terms,
			'media' => $media,
			'success' => true,
		] );
	}

	/**
	 * Find image file urls embedded in the post content,
	 * see if they exist in the media library, and return
	 * a list of attachment IDs
	 *
	 * @param string $post_content Content to search
	 * @param int $post_id Optional. Post ID. Used for filters.
	 * @return array An array of attachment IDs
	 */
	private function find_embedded_media( string $post_content, ?array $sizes = null, ?int $post_id = null ) {

		// find image file urls
		$allowed_image_filetypes = apply_filters( 'content_sync_allowed_image_types', [ 'webp', 'svg', 'jpg', 'jpeg', 'png', 'gif' ], $post_id );

		$matched = [];
		$url = trailingslashit( get_bloginfo( 'url' ) ) . str_replace( ABSPATH, '', wp_get_upload_dir()['basedir'] );
		$url = preg_quote( $url, '/' );
		$regex = '/' . $url . '\/(([a-zA-Z0-9-_\/]+)\.(' . implode( '|', $allowed_image_filetypes ) . '))/';
		if ( preg_match_all( $regex . 'i', $post_content, $matches ) ) {
			foreach( $matches[1] as $index => $url ) {
				$without_postfix = preg_replace( '/-[0-9]+x[0-9]*/', '', $matches[2][$index] ) . '.' . $matches[3][$index];
				$attachments = get_posts( [
					'post_type'   => 'attachment',
					'numberposts' => -1,
					'meta_query' => [
						'relation' => 'OR',
						[
							'key' => '_wp_attached_file',
							'value' => $url
						],[
							'key' => '_wp_attached_file',
							'value' => $without_postfix
						]
					]
				] );
				foreach( $attachments as $attachment ) {
					$matched[ $attachment->ID ] = [
						'match' => $matches[0][$index],
						'post'  => $attachment->to_array(),
					];
					if ( $sizes ) {
						$matched[ $attachment->ID ]['sizes'] = [];
						foreach( $sizes as $size ) {
							$src = wp_get_attachment_image_src( $attachment->ID, $size );;
							if ( $src && ! in_array( $src[0], $matched[ $attachment->ID ]['sizes'], true ) ) {
								$matched[ $attachment->ID ]['sizes'][] = $src[0];
							}
						}
					}
				}
			}
		}

		return $matched;
	}

	/**
	 * Retrieves the media attachments associated with a given post.
	 *
	 * @param int $post_id The ID of the post.
	 * @param array $sizes Optional. An array of sizes to retrieve.
	 * @return array An array of media attachments, where the key is the attachment ID and the value is the attachment object.
	 */
	private function get_attached_media( int $post_id, ?array $sizes = null ) {
		$media = [];
		$attachments = get_posts( [
			'post_type'   => 'attachment',
			'numberposts' => -1,
			'post_parent' => $post_id
		] );
		foreach( $attachments as $attachment ) {
			$media[ $attachment->ID ] = [
				'post' => $attachment,
			];
			if ( $sizes ) {
				$media[ $attachment->ID ]['sizes'] = [];
				foreach( $sizes as $size ) {
					$src = wp_get_attachment_image_src( $attachment->ID, $size );;
					if ( $src && ! in_array( $src[0], $media[ $attachment->ID ]['sizes'], true ) ) {
						$media[ $attachment->ID ]['sizes'][] = $src[0];
					}
				}
			}
		}
		return $media;
	}

	/**
	 * Returns the URL of the WP Rest API endpoint on the source server
	 *
	 * @return string
	 */
	public static function get_export_endpoint() {
		$source = Settings\get( 'import_url' );
		if ( ! $source ) {
			return false;
		}
		return untrailingslashit( $source ) . '/wp-json/content-sync/v1/export';
	}

}