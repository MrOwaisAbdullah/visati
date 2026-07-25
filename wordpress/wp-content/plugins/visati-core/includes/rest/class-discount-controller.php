<?php
/**
 * REST endpoint: POST /visati/v1/discount/validate — check a discount code
 * before checkout and return the computed discount, without applying it.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Discount_Controller {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'visati/v1',
			'/discount/validate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_validate' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'code'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'price_aed' => array(
						'required'          => true,
						'type'              => 'number',
						// Not bare 'floatval' — WP core calls sanitize_callback with 3 args
						// (value, request, param); floatval() is a strict internal function
						// that throws ArgumentCountError on PHP 8.1+ when given extra args.
						'sanitize_callback' => function ( $value ) {
							return (float) $value;
						},
					),
				),
			)
		);
	}

	public static function handle_validate( WP_REST_Request $request ) {
		if ( ! visati_rate_limit( 'discount_validate', 20, 600 ) ) {
			return new WP_Error( 'rate_limited', __( 'Too many attempts. Please try again in a few minutes.', 'visati-core' ), array( 'status' => 429 ) );
		}

		$code      = (string) $request->get_param( 'code' );
		$price_aed = (float) $request->get_param( 'price_aed' );

		$result = Visati_Core_Discount::validate( $code, $price_aed );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}
}
