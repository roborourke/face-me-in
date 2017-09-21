<?php
/**
 * Plugin Name: Face Me In
 * Description: Log in with your face
 */

namespace FaceMeIn;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_User_Query;
use WP_Error;

const VERSION = '1.0';

function get_api_key() {
	return get_site_option( 'facemein_api_key', defined( 'FACEMEIN_API_KEY' )
		? FACEMEIN_API_KEY
		: false );
}

function get_secret() {
	return get_site_option( 'facemein_secret', defined( 'FACEMEIN_SECRET' )
		? FACEMEIN_SECRET
		: false );
}

function capture_permission() {
	if ( ! is_user_logged_in() ) {
		return new WP_Error(
			'rest_forbidden',
			esc_html__( 'You must be logged in to capture your face.', 'facemein' ),
			[
				'status' => 401,
			]
		);
	}

	return true;
}

add_action( 'rest_api_init', function ( WP_REST_Server $server ) {

	// Captures and stores a base64 encoded image.
	register_rest_route( 'facemein/v1', '/capture', [
		[
			'methods'             => $server::CREATABLE,
			'permission_callback' => __NAMESPACE__ . '\capture_permission',
			'callback'            => function ( WP_REST_Request $request ) {
				// Get base64 encoded image.
				$image = $request->get_param( 'image' );

				if ( empty( $image ) || ! base64_decode( str_replace( 'data:image/png;base64,', '', $image ), true ) ) {
					return new WP_REST_Response( [
						'error'   => 'bad_image',
						'message' => esc_html__( 'The image value is not usable. Try again!', 'facemein' ),
					], 400 );
				}

				// Store the image for auth.
				add_user_meta( get_current_user_id(), 'facemein_image', $image, false );

				return new WP_REST_Response( [
					'success' => true,
				], 200 );
			},
		],
		[
			'methods'             => $server::DELETABLE,
			'permission_callback' => __NAMESPACE__ . '\capture_permission',
			'callback'            => function ( WP_REST_Request $request ) {
				// Remove all stored images for auth.
				delete_user_meta( get_current_user_id(), 'facemein_image' );

				return new WP_REST_Response( [
					'success' => true,
				], 200 );
			},
		],
	] );

	// Authenticates an image against the stored one.
	register_rest_route( 'facemein/v1', '/auth', [
		'methods'  => 'POST',
		'callback' => function ( WP_REST_Request $request ) {
			if ( is_user_logged_in() ) {
				return new WP_REST_Response( [
					'error'   => 'logged_in',
					'message' => __( 'You are already logged in!' ),
				], 400 );
			}

			// Get the images to compare.
			$stored    = $request->get_param( 'stored' );
			$challenge = $request->get_param( 'challenge' );

			if ( empty( $stored ) || empty( $challenge ) ) {
				return new WP_REST_Response( [
					'error'   => 'missing_params',
					'message' => __( 'Missing parameter, you must provide a stored image and a challenge image.' ),
				], 400 );
			}

			// Call Face++
			$api_call = wp_remote_post( 'https://api-us.faceplusplus.com/facepp/v3/compare', [
				'timeout' => 10,
				'body'    => [
					'api_key'        => get_api_key(),
					'api_secret'     => get_secret(),
					'image_base64_1' => str_replace( 'data:image/png;base64,', '', $stored ),
					'image_base64_2' => str_replace( 'data:image/png;base64,', '', $challenge ),
				],
			] );

			if ( is_wp_error( $api_call ) ) {
				return new WP_REST_Response( [
					'error'   => 'request',
					'message' => $api_call->get_error_message(),
				], 400 );
			}

			$response = json_decode( wp_remote_retrieve_body( $api_call ) );

			if ( wp_remote_retrieve_response_code( $api_call ) !== 200 ) {
				return new WP_REST_Response( [
					'error'   => 'api',
					'message' => $response->error_message,
				], wp_remote_retrieve_response_code( $api_call ) );
			}

			// High enough confidence?
			if ( $response->confidence > 90 ) {
				$users = new WP_User_Query( [
					'meta_key'   => 'facemein_image',
					'meta_value' => $stored,
				] );

				if ( $users->get_total() ) {
					$user = $users->get_results()[0];

					// Log in.
					wp_set_auth_cookie( $user->ID );

					return new WP_REST_Response( [
						'success'  => true,
						'user'     => [
							'name' => $user->get( 'display_name' ),
							'ID'   => $user->ID,
						],
						'redirect' => admin_url(),
					], 200 );
				}
			}

			// Low confidence.
			return new WP_REST_Response( [
				'error'   => 'auth',
				'message' => sprintf( __( 'Confidence score too low: %s', 'facemein' ), floatval( $response['confidence'] ) ),
			], 400 );
		},
	] );
} );

add_action( 'admin_enqueue_scripts', function () {
	wp_enqueue_script( 'facemein-admin', plugins_url( 'js/face-me-in-admin.js', __FILE__ ), [ 'jquery' ], VERSION, true );
	wp_localize_script( 'facemein-admin', 'FaceMeIn', [
		'l10n'     => [
			'capture' => esc_html__( 'Click the video to capture your face', 'facemein' ),
			'enable'  => esc_html__( 'Enable face recognition', 'facemein' ),
			'disable' => esc_html__( 'Disable face recognition', 'facemein' ),
		],
		'faces'    => get_user_meta( get_current_user_id(), 'facemein_image' ),
		'endpoint' => get_rest_url( null, 'facemein/v1/capture' ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
	] );
} );

add_action( 'login_enqueue_scripts', function () {
	wp_enqueue_script( 'facemein-login', plugins_url( 'js/face-me-in-login.js', __FILE__ ), [ 'jquery' ], VERSION );
	wp_localize_script( 'facemein-login', 'FaceMeIn', [
		'l10n'     => [
			'login'  => esc_html__( 'Login with your face', 'facemein' ),
			'gotcha' => esc_html__( 'Found you! Logging in...', 'facemein' ),
			'cancel' => esc_html__( 'Click to cancel and login normally', 'facemein' ),
		],
		'endpoint' => get_rest_url( null, 'facemein/v1/auth' ),
	] );
} );
