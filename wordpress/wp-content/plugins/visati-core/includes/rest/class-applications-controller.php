<?php
/**
 * REST controller: applications.
 *   POST /visati/v1/applications  — create a draft application.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Applications_Controller {

	/**
	 * Hook route registration.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			Visati_Core_REST_API::NAMESPACE,
			'/applications',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Create a draft application.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create( WP_REST_Request $req ) {
		$d = $req->get_json_params();
		if ( ! is_array( $d ) || empty( $d ) ) {
			$d = $req->get_body_params();
		}

		$required = array( 'nationality', 'given_name', 'surname', 'passport_number', 'date_of_birth', 'passport_expiry', 'travel_date', 'visa_type_slug' );
		foreach ( $required as $field ) {
			if ( empty( $d[ $field ] ) ) {
				/* translators: %s: field name */
				return Visati_Core_REST_API::error( 'missing_field', sprintf( __( 'Missing required field: %s', 'visati-core' ), $field ), 400 );
			}
		}

		// Validate application ID format if provided.
		$clean = array(
			'nationality'     => sanitize_text_field( wp_unslash( $d['nationality'] ) ),
			'given_name'      => sanitize_text_field( wp_unslash( $d['given_name'] ) ),
			'surname'         => sanitize_text_field( wp_unslash( $d['surname'] ) ),
			'passport_number' => sanitize_text_field( wp_unslash( $d['passport_number'] ) ),
			'date_of_birth'   => sanitize_text_field( wp_unslash( $d['date_of_birth'] ) ),
			'passport_expiry' => sanitize_text_field( wp_unslash( $d['passport_expiry'] ) ),
			'travel_date'     => sanitize_text_field( wp_unslash( $d['travel_date'] ) ),
			'visa_type_slug'  => sanitize_text_field( wp_unslash( $d['visa_type_slug'] ) ),
			'applicant_email' => sanitize_email( wp_unslash( $d['applicant_email'] ?? '' ) ),
			'processing_tier' => ( 'express' === ( $d['processing_tier'] ?? '' ) ) ? 'express' : 'standard',
		);

		$id = Visati_Core_Application::create( $clean );

		return rest_ensure_response(
			array(
				'id'     => $id,
				'status' => 'draft',
			)
		);
	}
}
