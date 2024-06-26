<?php

namespace ReallySpecific\WP_ContentSync;

use ReallySpecific\WP_Util as Util;
use ReallySpecific\WP_ContentSync\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Server {

	private $plugin = null;

	public function __construct( Util\Plugin &$plugin ) {
		if ( ! filter_var( $plugin->get_setting( key: 'export_enabled' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return;
		}
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		$this->plugin = &$plugin;
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
		register_rest_route(
			'content-sync/v1',
			'/export/image/(?P<id>\d+)',
			[
				'methods' => 'GET',
				'callback' => [ $this, 'export_image_files' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);
	}

	public function get_allowed_referrers() {

		$allowed = wp_cache_get( 'allowed_referrers', $this->plugin->i18n_domain );
		if ( false !== $allowed ) {
			return $allowed;
		}

		$referrers = $this->plugin->get_setting( key: 'export_referrer_whitelist' ) ?: '';
		if ( empty( trim( $referrers ) ) ) {
			return [];
		}
		$referrers = array_map( 'trim', explode( "\n", $referrers ) );
		if ( in_array( '*', $referrers, true ) ) {
			$referrers = '*';
		} else {
			foreach( $referrers as $index => $referrer ) {
				$url = parse_url( $referrer );
				$referrers[ $index ] = $url['host'] ?? $referrer;
			}
		}

		wp_cache_set( 'allowed_referrers', $referrers, $this->plugin->i18n_domain );

		return $referrers;
	}

	/** todo: refactor the following four functions to the util library */
	public function verify_referrer( \WP_REST_Request $request ) {
		$allowed_hosts = $this->get_allowed_referrers();
		if ( $allowed_hosts === '*' ) {
			return true;
		}
		$referrer = $request->get_header( 'Referer' ) ?: $_SERVER['HTTP_REFERER'] ?: null;
		if ( empty( $referrer ) ) {
			return false;
		}
		$url      = parse_url( $referrer );
		$referrer = $url['host'] ?? $referrer;

		return in_array( $referrer, $allowed_hosts, true );
	}

	public function get_allowed_ips() {

		$allowed = wp_cache_get( 'allowed_ips', $this->plugin->i18n_domain );
		if ( false !== $allowed ) {
			return $allowed;
		}

		$referrers = $this->plugin->get_setting( key: 'export_ip_whitelist' ) ?: '';
		if ( empty( trim( $referrers ) ) ) {
			return '*';
		}
		$referrers = array_map( 'trim', explode( "\n", $referrers ) );
		if ( in_array( '*', $referrers, true ) ) {
			$referrers = '*';
		}

		wp_cache_set( 'allowed_ips', $referrers, $this->plugin->i18n_domain );

		return $referrers;
	}

	public function verify_remote_ip( \WP_REST_Request $request ) {
		$allowed_sources = $this->get_allowed_ips();
		if ( $allowed_sources === '*' ) {
			return true;
		}
		$remote_ip = $_SERVER['REMOTE_ADDR'] ?? null;
		if ( empty( $remote_ip ) ) {
			return false;
		}
		return in_array( $remote_ip, $allowed_sources, true );
	}

	/**
	 * Check if the request is authorized using a bearer token or user application password.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function check_permissions( \WP_REST_Request $request ) {

		if ( ! $this->verify_referrer( $request ) || ! $this->verify_remote_ip( $request ) ) {
			return false;
		}

		$token = $this->plugin->get_setting( key: 'export_token' );
		$auth = $request->get_header('authorization');
		if ( $token && $auth && $auth === 'Bearer ' . $token ) {
			return true;
		}

		$auth = $request->get_header('x-wp-authorization');
		if ( $auth ) {
			$auth = base64_decode( $auth );
		}
		list( $user, $password ) = array_merge( explode( ':', $auth, 2 ), [ null, null ] );
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
			return rest_ensure_response( [ 'success' => false, 'message' => 'Post not found on source server' ] );
		}
		if ( is_numeric( $post ) ) {
			$post = get_post( absint( $post ), ARRAY_A );
		}
		if ( is_string( $post ) ) {
			$post = Util\get_post_by_slug( $post );
		}
		if ( ! $post ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'Post not found on source server' ] );
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
						'path'  => get_post_meta( $attachment->ID, '_wp_attached_file', true ),
						'full'  => wp_get_attachment_image_src( $attachment->ID, 'full' ),
						'post'  => $attachment->to_array(),
					];
					if ( $sizes ) {
						$matched[ $attachment->ID ]['sizes'] = [];
						foreach( $sizes as $size ) {
							$src = wp_get_attachment_image_src( $attachment->ID, $size );;
							if ( $src && ! in_array( $src[0], $matched[ $attachment->ID ]['sizes'], true ) ) {
								$matched[ $attachment->ID ]['sizes'][$size] = $src;
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
				'path' => get_post_meta( $attachment->ID, '_wp_attached_file', true ),
				'full'  => wp_get_attachment_image_src( $attachment->ID, 'full' ),
			];
			if ( $sizes ) {
				$media[ $attachment->ID ]['sizes'] = [];
				foreach( $sizes as $size ) {
					$src = wp_get_attachment_image_src( $attachment->ID, $size );;
					if ( $src && ! in_array( $src[0], $media[ $attachment->ID ]['sizes'], true ) ) {
						$media[ $attachment->ID ]['sizes'][$size] = $src;
					}
				}
			}
		}
		return $media;
	}

	public function export_image_files( \WP_REST_Request $request ) {

		$post = $request->get_param( 'id' );
		if ( ! $post ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'Image not found on source server' ] );
		}
		if ( is_numeric( $post ) ) {
			$post = get_post( absint( $post ), ARRAY_A );
		}
		if ( ! $post || $post['post_type'] !== 'attachment' ) {
			return rest_ensure_response( [ 'success' => false, 'message' => 'Post not found on source server' ] );
		}
		
		$media_sizes = get_intermediate_image_sizes();
		$media       = [];
		$full_path   = get_attached_file( $post['ID'] );
		$file_dir    = dirname( $full_path );
		$details     = wp_get_attachment_image_src( $post['ID'] );
		try {
			$contents = @file_get_contents( $full_path );
		} catch( \Exception $e ) {
			return rest_ensure_response( [ 'success' => false,
				'message' => 'There was an error retrieving the attachment source file.',
				'error'   => ( $this->plugin->debug_mode() ? $e->getMessage() : truee )
			] );
		}
		$media['full'] = [
			'data'   => base64_encode( $contents ),
			'url'    => $details[0],
			'width'  => $details[1],
			'height' => $details[2],
			'type'   => mime_content_type( $full_path ),
		];
		if ( $media['full']['type'] === 'image/svg+xml' ) {
			return rest_ensure_response( [ 'success' => true, 'media' => $media ] );
		}

		foreach( $media_sizes as $size ) {
			$details = wp_get_attachment_image_src( $post['ID'], $size );
			if ( ! $details ) {
				continue;
			}
			$file_name = basename( $details[0] );
			$file_path = $file_dir . '/' . $file_name;
			try {
				$contents = @file_get_contents( $file_path );
			} catch( \Exception $e ) {
				$media[ $size ] = 'ERROR' . ( $this->plugin->debug_mode() ? ' ' . $e->getMessage() : '' );
				continue;
			}
			$media[ $size ] = [
				'data'   => base64_encode( $contents ),
				'url'    => $details[0],
				'width'  => $details[1],
				'height' => $details[2],
				'type'   => mime_content_type( $file_path ),
			];
		}

		return rest_ensure_response( [ 'success' => true, 'media' => $media ] );
	}
}