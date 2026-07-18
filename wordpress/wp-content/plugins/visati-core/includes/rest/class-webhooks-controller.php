<?php
/**
 * Stripe webhook REST endpoint — handles checkout.session.completed, payment_intent.payment_failed.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Visati_Core_Webhooks_Controller {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route( 'visati/v1', '/webhooks/stripe', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_webhook' ),
			'permission_callback' => '__return_true', // Stripe calls us — verified by signature.
		) );

		// Endpoint for the frontend to create a checkout session.
		register_rest_route( 'visati/v1', '/checkout-session', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'create_checkout' ),
			'permission_callback' => array( 'Visati_Core_REST_API', 'permission_public' ),
		) );
	}

	/**
	 * Create a Stripe Checkout Session for the frontend to redirect to.
	 */
	public static function create_checkout( WP_REST_Request $request ) {
		$app_id = sanitize_text_field( $request->get_param( 'application_id' ) );
		if ( ! $app_id ) {
			return new WP_Error( 'missing_app_id', 'Application ID required.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'visati_applications';
		$app   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $app_id ) );

		if ( ! $app ) {
			return new WP_Error( 'app_not_found', 'Application not found.', array( 'status' => 404 ) );
		}

		// Get visa type price.
		$visa_type = get_page_by_path( $app->visa_type_slug, OBJECT, 'visati_visa_type' );
		$price_aed = $visa_type ? (int) get_post_meta( $visa_type->ID, '_visati_price_aed', true ) : 500;
		$visa_name = $visa_type ? $visa_type->post_title : ucfirst( $app->visa_type_slug );

		// Add express surcharge.
		$express = ( 'express' === $app->processing_tier );
		if ( $express ) {
			$surcharge = (int) Visati_Core_Settings::get( 'express_surcharge_aed', 99 );
			$price_aed += $surcharge;
		}

		$success_url = add_query_arg( array( 'success' => '1', 'id' => $app_id ), home_url( '/apply/' ) );
		$cancel_url  = add_query_arg( 'cancelled', '1', home_url( '/apply/' ) );

		$result = Visati_Core_Stripe_Payments::create_checkout_session(
			$price_aed,
			$app_id,
			$visa_name,
			$success_url,
			$cancel_url
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle incoming Stripe webhook.
	 */
	public static function handle_webhook( WP_REST_Request $request ) {
		$raw_body  = $request->get_body();
		$sig_header = $request->get_header( 'Stripe-Signature' );

		if ( ! $sig_header ) {
			return new WP_Error( 'missing_signature', 'Missing Stripe-Signature header.', array( 'status' => 400 ) );
		}

		$event = Visati_Core_Stripe_Payments::verify_webhook( $raw_body, $sig_header );

		if ( is_wp_error( $event ) ) {
			return $event;
		}

		$type = $event['type'];

		switch ( $type ) {
			case 'checkout.session.completed':
				self::handle_checkout_completed( $event );
				break;

			case 'payment_intent.payment_failed':
				self::handle_payment_failed( $event );
				break;

			default:
				// Unhandled event type — acknowledge but do nothing.
				break;
		}

		return rest_ensure_response( array( 'received' => true ) );
	}

	/**
	 * checkout.session.completed — mark application as submitted, record payment, send emails.
	 */
	private static function handle_checkout_completed( array $event ): void {
		$session = $event['data']['object'] ?? null;
		if ( ! $session || empty( $session['metadata']['application_id'] ) ) {
			return;
		}

		$app_id = $session['metadata']['application_id'];

		// Idempotency: skip if already submitted.
		global $wpdb;
		$app_table = $wpdb->prefix . 'visati_applications';
		$app       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$app_table} WHERE application_id = %s", $app_id ) );

		if ( ! $app || 'submitted' === $app->status ) {
			return;
		}

		$now = current_time( 'mysql' );

		// Update status → submitted.
		$wpdb->update( $app_table,
			array( 'status' => 'submitted', 'updated_at' => $now ),
			array( 'application_id' => $app_id ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		// Append status history.
		$wpdb->insert( $wpdb->prefix . 'visati_status_history', array(
			'application_id' => $app_id,
			'status'         => 'submitted',
			'note'           => 'Payment received via Stripe.',
		), array( '%s', '%s', '%s' ) );

		// Record payment.
		$amount = (int) ( $session['amount_total'] ?? 0 );
		$wpdb->insert( $wpdb->prefix . 'visati_payments', array(
			'application_id'    => $app_id,
			'stripe_session_id' => $session['id'] ?? '',
			'stripe_payment_id' => $session['payment_intent'] ?? '',
			'amount_cents'      => $amount,
			'currency'          => strtoupper( $session['currency'] ?? 'aed' ),
			'status'            => 'completed',
		), array( '%s', '%s', '%s', '%d', '%s', '%s' ) );

		// Send confirmation email.
		if ( class_exists( 'Visati_Core_Email' ) ) {
			$app->status = 'submitted';
			Visati_Core_Email::send_confirmation( $app );
		}
	}

	/**
	 * payment_intent.payment_failed — keep status as draft, log the failure.
	 */
	private static function handle_payment_failed( array $event ): void {
		$pi = $event['data']['object'] ?? null;
		if ( ! $pi ) {
			return;
		}

		// Try to find the application by payment_intent ID.
		global $wpdb;
		$payments_table = $wpdb->prefix . 'visati_payments';
		$payment = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$payments_table} WHERE stripe_payment_id = %s", $pi['id'] ?? '' )
		);

		if ( ! $payment ) {
			return;
		}

		// Update payment status.
		$wpdb->update( $payments_table,
			array( 'status' => 'failed' ),
			array( 'id' => $payment->id ),
			array( '%s' ),
			array( '%d' )
		);
		// Application status stays 'draft' — user can retry.
	}
}
