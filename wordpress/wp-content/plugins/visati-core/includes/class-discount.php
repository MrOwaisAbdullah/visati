<?php
/**
 * Discount code validation and usage tracking.
 *
 * A discount code is a `visati_discount` post whose title IS the code the
 * customer types in (matched case-insensitively). Fields live in post meta —
 * see Visati_Core_Meta_Boxes::render_discount_details().
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Discount {

	/**
	 * Validate a discount code against a given price and compute the discount.
	 *
	 * @param string $code      Code as typed by the customer.
	 * @param float  $price_aed Price before discount, in AED.
	 * @return array|WP_Error {
	 *     @type string $code                 Normalized code (uppercased).
	 *     @type string $discount_type        'percentage'|'fixed'.
	 *     @type int    $discount_value       Raw configured value.
	 *     @type float  $discount_amount_aed  Amount actually deducted (capped at price).
	 *     @type float  $final_price_aed      Price after discount.
	 * }
	 */
	public static function validate( string $code, float $price_aed ) {
		$code = trim( $code );
		if ( '' === $code ) {
			return new WP_Error( 'discount_missing_code', __( 'Enter a discount code.', 'visati-core' ), array( 'status' => 400 ) );
		}

		$post = self::find_by_code( $code );
		if ( ! $post ) {
			return new WP_Error( 'discount_not_found', __( 'This discount code is not valid.', 'visati-core' ), array( 'status' => 404 ) );
		}

		if ( 'publish' !== $post->post_status ) {
			return new WP_Error( 'discount_inactive', __( 'This discount code is no longer active.', 'visati-core' ), array( 'status' => 410 ) );
		}

		$active = (int) get_post_meta( $post->ID, '_visati_discount_active', true );
		if ( ! $active ) {
			return new WP_Error( 'discount_inactive', __( 'This discount code is no longer active.', 'visati-core' ), array( 'status' => 410 ) );
		}

		$expiry = get_post_meta( $post->ID, '_visati_discount_expiry', true );
		if ( $expiry && strtotime( $expiry ) < time() ) {
			return new WP_Error( 'discount_expired', __( 'This discount code has expired.', 'visati-core' ), array( 'status' => 410 ) );
		}

		$max_uses   = (int) get_post_meta( $post->ID, '_visati_discount_max_uses', true );
		$used_count = (int) get_post_meta( $post->ID, '_visati_discount_used_count', true );
		if ( $max_uses > 0 && $used_count >= $max_uses ) {
			return new WP_Error( 'discount_exhausted', __( 'This discount code has reached its usage limit.', 'visati-core' ), array( 'status' => 410 ) );
		}

		$type  = get_post_meta( $post->ID, '_visati_discount_type', true ) ?: 'percentage';
		$value = (int) get_post_meta( $post->ID, '_visati_discount_value', true );

		$amount = 'percentage' === $type
			? round( $price_aed * ( $value / 100 ), 2 )
			: (float) $value;

		// Never discount below zero.
		$amount = min( $amount, $price_aed );

		return array(
			'code'                => strtoupper( $post->post_title ),
			'discount_type'       => $type,
			'discount_value'      => $value,
			'discount_amount_aed' => $amount,
			'final_price_aed'     => round( $price_aed - $amount, 2 ),
		);
	}

	/**
	 * Increment a code's usage counter after a successful payment.
	 *
	 * @param string $code Code (case-insensitive).
	 * @return void
	 */
	public static function record_usage( string $code ): void {
		$post = self::find_by_code( $code );
		if ( ! $post ) {
			return;
		}
		$used_count = (int) get_post_meta( $post->ID, '_visati_discount_used_count', true );
		update_post_meta( $post->ID, '_visati_discount_used_count', $used_count + 1 );
	}

	/**
	 * Look up a discount post by its code (post title, case-insensitive).
	 *
	 * @param string $code Code as typed by the customer.
	 * @return WP_Post|null
	 */
	private static function find_by_code( string $code ): ?WP_Post {
		$posts = get_posts( array(
			'post_type'      => 'visati_discount',
			'post_status'    => array( 'publish' ),
			'title'          => trim( $code ),
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		) );

		if ( ! empty( $posts ) ) {
			return $posts[0];
		}

		// WP_Query's 'title' arg is an exact match but case-sensitive in some DB
		// collations — fall back to a manual case-insensitive scan for safety.
		global $wpdb;
		$post_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'visati_discount' AND post_status = 'publish' AND LOWER(post_title) = LOWER(%s) LIMIT 1",
			trim( $code )
		) );

		return $post_id ? get_post( (int) $post_id ) : null;
	}
}
