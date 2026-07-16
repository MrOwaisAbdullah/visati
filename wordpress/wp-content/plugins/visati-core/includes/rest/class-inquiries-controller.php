<?php
/**
 * REST endpoint: POST /visati/v1/inquiries — saves contact form submissions.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Inquiries_Controller {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'visati/v1',
			'/inquiries',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_submit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'name'    => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'email'   => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_email', 'validate_callback' => 'is_email' ),
					'phone'   => array( 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'subject' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'message' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
				),
			)
		);
	}

	public static function handle_submit( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$table = $wpdb->prefix . 'visati_inquiries';

		$inserted = $wpdb->insert(
			$table,
			array(
				'name'    => $request->get_param( 'name' ),
				'email'   => $request->get_param( 'email' ),
				'phone'   => $request->get_param( 'phone' ),
				'subject' => $request->get_param( 'subject' ),
				'message' => $request->get_param( 'message' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_REST_Response( array( 'message' => 'Failed to save inquiry.' ), 500 );
		}

		$inquiry_id = $wpdb->insert_id;

		// Send admin notification email.
		if ( class_exists( 'Visati_Core_Email' ) ) {
			Visati_Core_Email::send_inquiry_notification( $inquiry_id, $request->get_params() );
		}

		return new WP_REST_Response( array(
			'id'      => $inquiry_id,
			'message' => 'Inquiry submitted successfully.',
		), 201 );
	}
}
