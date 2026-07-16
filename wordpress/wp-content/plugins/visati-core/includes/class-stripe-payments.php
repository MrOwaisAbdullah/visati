<?php
/**
 * Stripe payments — dependency-free (raw REST via wp_remote_post + HMAC webhook verify).
 * No Stripe PHP SDK required.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Stripe_Payments {

	const API_BASE = 'https://api.stripe.com/v1/';

	/**
	 * Whether Stripe is configured.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		return Visati_Core_Settings::stripe_is_configured();
	}

	/**
	 * Create a Stripe Checkout Session for a visa application.
	 *
	 * @param int    $amount_aed  Total in AED (whole units; Express tier already included).
	 * @param string $app_id      Application ID (VIS-YYYY-XXXXXX).
	 * @param string $visa_name   Visa type name (line-item label).
	 * @param string $success_url Redirect URL on success.
	 * @param string $cancel_url  Redirect URL on cancel.
	 * @param string $currency    Currency code (default aed).
	 * @return array{id:string,url:string}|WP_Error
	 */
	public static function create_checkout_session( int $amount_aed, string $app_id, string $visa_name, string $success_url, string $cancel_url, string $currency = 'aed' ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'stripe_not_configured', __( 'Stripe is not configured.', 'visati-core' ) );
		}

		$body = array(
			'mode'                             => 'payment',
			'success_url'                      => $success_url,
			'cancel_url'                       => $cancel_url,
			'client_reference_id'              => $app_id,
			'metadata[application_id]'         => $app_id,
			'line_items[0][quantity]'          => '1',
			'line_items[0][price_data][currency]'         => $currency,
			'line_items[0][price_data][unit_amount]'      => (string) ( $amount_aed * 100 ),
			'line_items[0][price_data][product_data][name]'        => trim( $visa_name . ' — ' . $app_id ),
			'line_items[0][price_data][product_data][description]' => 'UAE Visa Application ' . $app_id,
		);

		$response = wp_remote_post(
			self::API_BASE . 'checkout/sessions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . Visati_Core_Settings::stripe_secret_key(),
				),
				'body'    => $body,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || empty( $json['url'] ) || empty( $json['id'] ) ) {
			return new WP_Error(
				'stripe_session_failed',
				/* translators: %d: HTTP status code */
				sprintf( __( 'Stripe Checkout failed (HTTP %d).', 'visati-core' ), $code ),
				array( 'status' => $code, 'body' => $json )
			);
		}

		return array(
			'id'  => $json['id'],
			'url' => $json['url'],
		);
	}

	/**
	 * Verify a Stripe webhook signature and return the parsed event.
	 *
	 * @param string $raw_body   Raw request body (php://input).
	 * @param string $sig_header Stripe-Signature header value.
	 * @return array|WP_Error Parsed event array, or WP_Error on any failure.
	 */
	public static function verify_webhook( string $raw_body, string $sig_header ) {
		$secret = Visati_Core_Settings::stripe_webhook_secret();
		if ( ! $secret ) {
			return new WP_Error( 'stripe_webhook_not_configured', __( 'Stripe webhook secret not configured.', 'visati-core' ) );
		}

		// Parse "t=...,v1=...,v0=...".
		$parts = array();
		foreach ( explode( ',', $sig_header ) as $pair ) {
			$pair = trim( $pair );
			$eq   = strpos( $pair, '=' );
			if ( false !== $eq ) {
				$parts[ substr( $pair, 0, $eq ) ] = substr( $pair, $eq + 1 );
			}
		}
		if ( empty( $parts['t'] ) || empty( $parts['v1'] ) ) {
			return new WP_Error( 'stripe_bad_signature', __( 'Malformed Stripe-Signature header.', 'visati-core' ) );
		}

		// Replay protection: 5-minute tolerance.
		if ( abs( time() - (int) $parts['t'] ) > 300 ) {
			return new WP_Error( 'stripe_stale_signature', __( 'Webhook timestamp outside tolerance.', 'visati-core' ) );
		}

		$expected = hash_hmac( 'sha256', $parts['t'] . '.' . $raw_body, $secret );
		if ( ! hash_equals( $expected, $parts['v1'] ) ) {
			return new WP_Error( 'stripe_invalid_signature', __( 'Invalid webhook signature.', 'visati-core' ) );
		}

		$event = json_decode( $raw_body, true );
		if ( ! is_array( $event ) || empty( $event['type'] ) ) {
			return new WP_Error( 'stripe_bad_payload', __( 'Invalid webhook payload.', 'visati-core' ) );
		}

		return $event;
	}
}
