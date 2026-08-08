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
			'permission_callback' => function () {
				// Require REST nonce — sent by apply form JS via X-WP-Nonce header.
				if ( ! isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
					return false;
				}
				return wp_verify_nonce( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ), 'wp_rest' );
			},
		) );
	}

	/**
	 * Create a Stripe Checkout Session for the frontend to redirect to.
	 */
	public static function create_checkout( WP_REST_Request $request ) {
		if ( ! visati_rate_limit( 'checkout_session', 10, 600 ) ) {
			return new WP_Error( 'rate_limited', 'Too many requests. Please try again in a few minutes.', array( 'status' => 429 ) );
		}

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

		// Get visa type price — checkout charges in USD (see migration-log.md,
		// "Switch actual Stripe checkout currency AED→USD"). _visati_price_aed is
		// reference-only display now, never used to compute what's charged.
		$visa_type = get_page_by_path( $app->visa_type_slug, OBJECT, 'visati_visa_type' );
		$price_usd = $visa_type ? (float) get_post_meta( $visa_type->ID, '_visati_price_usd', true ) : 137;
		$visa_name = $visa_type ? $visa_type->post_title : ucfirst( $app->visa_type_slug );

		// Each additional traveler is a separate visa application — never trust
		// the client's computed total, re-derive the traveler count from the
		// application's own stored travelers_data (server-authoritative). The
		// per-traveler price is set per visa type (0 bundles extras for free,
		// an explicit admin choice) — see _visati_extra_traveler_price_usd.
		$extra_travelers = array();
		if ( ! empty( $app->travelers_data ) ) {
			$decoded = json_decode( $app->travelers_data, true );
			if ( is_array( $decoded ) ) {
				$extra_travelers = $decoded;
			}
		}
		$extra_traveler_count = count( $extra_travelers );
		$total_travelers      = 1 + $extra_traveler_count;

		if ( $extra_traveler_count > 0 && $visa_type ) {
			$extra_traveler_price = (float) get_post_meta( $visa_type->ID, '_visati_extra_traveler_price_usd', true );
			$pricing_mode         = get_post_meta( $visa_type->ID, '_visati_extra_traveler_pricing_mode', true ) ?: 'per_traveler';
			$price_usd           += ( 'flat' === $pricing_mode ) ? $extra_traveler_price : ( $extra_traveler_count * $extra_traveler_price );
		}

		// Add express surcharge — a per-person service, so it scales with the
		// total number of travelers on the application, not charged once.
		$express = ( 'express' === $app->processing_tier );
		if ( $express ) {
			$surcharge = (float) Visati_Core_Settings::get( 'express_surcharge_usd', 27 );
			$price_usd += $surcharge * $total_travelers;
		}

		// Optional discount code — re-validated server-side, never trust a client-computed amount.
		$discount_code   = sanitize_text_field( (string) $request->get_param( 'discount_code' ) );
		$discount_amount = 0.0;
		if ( '' !== $discount_code ) {
			$discount = Visati_Core_Discount::validate( $discount_code, $price_usd );
			if ( is_wp_error( $discount ) ) {
				return $discount;
			}
			$discount_amount = (float) $discount['discount_amount'];
			$price_usd       = (float) $discount['final_price'];
			$discount_code   = $discount['code'];

			// discount_amount_aed keeps its historical column name (see
			// class-database.php) but now holds a USD value for rows where
			// `currency` = 'usd'.
			$wpdb->update( $table,
				array( 'discount_code' => $discount_code, 'discount_amount_aed' => $discount_amount, 'currency' => 'usd' ),
				array( 'id' => $app_id ),
				array( '%s', '%f', '%s' ),
				array( '%s' )
			);
		}

		$success_url = add_query_arg( array( 'success' => '1', 'id' => $app_id ), home_url( '/apply/' ) );
		$cancel_url  = add_query_arg( 'cancelled', '1', home_url( '/apply/' ) );

		$result = Visati_Core_Stripe_Payments::create_checkout_session(
			(int) round( $price_usd ),
			$app_id,
			$visa_name,
			$success_url,
			$cancel_url,
			'usd'
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
		$app       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$app_table} WHERE id = %s", $app_id ) );

		if ( ! $app || 'submitted' === $app->status ) {
			return;
		}

		$now = current_time( 'mysql' );

		// Update status → submitted.
		// currency='usd' here (not just in the discount branch of create_checkout())
		// so every successful checkout is correctly marked, even when no discount
		// code was used.
		$wpdb->update( $app_table,
			array( 'status' => 'submitted', 'updated_at' => $now, 'currency' => 'usd' ),
			array( 'id' => $app->id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Append status history.
		$wpdb->insert( $wpdb->prefix . 'visati_status_history', array(
			'application_id' => $app_id,
			'status'         => 'submitted',
			'note'           => 'Payment received via Stripe.',
		), array( '%s', '%s', '%s' ) );

		// Record payment. Column is still named amount_aed for historical reasons
		// (see class-database.php) but holds the real charged amount — USD here,
		// paired with the currency column below.
		$amount_usd = ( (int) ( $session['amount_total'] ?? 0 ) ) / 100; // Stripe sends cents.
		$wpdb->insert( $wpdb->prefix . 'visati_payments', array(
			'application_id'          => $app_id,
			'stripe_payment_intent_id' => $session['payment_intent'] ?? '',
			'amount_aed'              => $amount_usd,
			'currency'                => 'usd',
			'status'                  => 'succeeded',
		), array( '%s', '%s', '%f', '%s', '%s' ) );

		// Record discount code usage, if one was applied at checkout.
		if ( ! empty( $app->discount_code ) && class_exists( 'Visati_Core_Discount' ) ) {
			Visati_Core_Discount::record_usage( $app->discount_code );
		}

		// Send confirmation email.
		if ( class_exists( 'Visati_Core_Email' ) ) {
			$visa_type_obj = get_page_by_path( $app->visa_type_slug, OBJECT, 'visati_visa_type' );
			$visa_name     = $visa_type_obj ? $visa_type_obj->post_title : '';
			$app_name      = trim( ( $app->given_name ?? '' ) . ' ' . ( $app->surname ?? '' ) );

			Visati_Core_Email::send_confirmation( $app_id, $app->applicant_email, $visa_name );
			Visati_Core_Email::send_admin_alert( $app_id, $visa_name, $app_name );
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
			$wpdb->prepare( "SELECT * FROM {$payments_table} WHERE stripe_payment_intent_id = %s", $pi['id'] ?? '' )
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
