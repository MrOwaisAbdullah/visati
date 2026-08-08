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
		if ( ! visati_rate_limit( 'applications', 10, 600 ) ) {
			return Visati_Core_REST_API::error( 'rate_limited', __( 'Too many submissions. Please try again in a few minutes.', 'visati-core' ), 429 );
		}

		$d = $req->get_json_params();
		if ( ! is_array( $d ) || empty( $d ) ) {
			$d = $req->get_body_params();
		}

		$required = array( 'nationality', 'given_name', 'surname', 'passport_number', 'date_of_birth', 'place_of_birth', 'profession', 'address', 'passport_expiry', 'travel_date', 'visa_type_slug' );
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
			'place_of_birth'  => sanitize_text_field( wp_unslash( $d['place_of_birth'] ) ),
			'profession'      => sanitize_text_field( wp_unslash( $d['profession'] ) ),
			'address'         => sanitize_text_field( wp_unslash( $d['address'] ) ),
			'passport_expiry' => sanitize_text_field( wp_unslash( $d['passport_expiry'] ) ),
			'travel_date'     => sanitize_text_field( wp_unslash( $d['travel_date'] ) ),
			'visa_type_slug'  => sanitize_text_field( wp_unslash( $d['visa_type_slug'] ) ),
			'applicant_email' => sanitize_email( wp_unslash( $d['applicant_email'] ?? '' ) ),
			'applicant_phone' => sanitize_text_field( wp_unslash( $d['applicant_phone'] ?? '' ) ),
			'processing_tier' => ( 'express' === ( $d['processing_tier'] ?? '' ) ) ? 'express' : 'standard',
		);

		/* Travelers array → JSON */
		if ( ! empty( $d['travelers'] ) && is_array( $d['travelers'] ) ) {
			$travelers = array();
			foreach ( $d['travelers'] as $t ) {
				if ( ! is_array( $t ) ) {
					continue;
				}
				$travelers[] = array(
					'relationship' => sanitize_text_field( wp_unslash( $t['relationship'] ?? '' ) ),
					'name'         => sanitize_text_field( wp_unslash( $t['name'] ?? '' ) ),
					'passport'     => sanitize_text_field( wp_unslash( $t['passport'] ?? '' ) ),
					'dob'          => sanitize_text_field( wp_unslash( $t['dob'] ?? '' ) ),
				);
			}
			$clean['travelers_data'] = wp_json_encode( $travelers );
		}

		$id = Visati_Core_Application::create( $clean );

		if ( is_wp_error( $id ) ) {
			return Visati_Core_REST_API::error( 'create_failed', $id->get_error_message(), 500 );
		}

		return rest_ensure_response(
			array(
				'id'     => $id,
				'status' => 'draft',
			)
		);
	}
}
