<?php
/**
 * Shared helper functions.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate a unique Application ID in the format VIS-YYYY-XXXXXX.
 * Uses an unambiguous alphabet (no I, O, 0, 1) and checks for collisions.
 *
 * @return string
 */
function visati_generate_application_id(): string {
	global $wpdb;
	$table = $wpdb->prefix . 'visati_applications';
	$year  = gmdate( 'Y' );
	// Unambiguous characters: no I, O, 0, 1.
	$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	$last  = strlen( $chars ) - 1;

	for ( $attempt = 0; $attempt < 10; $attempt++ ) {
		$suffix = '';
		for ( $i = 0; $i < 6; $i++ ) {
			$suffix .= $chars[ random_int( 0, $last ) ];
		}
		$id     = "VIS-{$year}-{$suffix}";
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id = %s", $id ) );
		if ( 0 === $exists ) {
			return $id;
		}
	}

	// Extremely unlikely fallback after 10 collisions.
	return 'VIS-' . $year . '-' . strtoupper( bin2hex( random_bytes( 3 ) ) );
}

/**
 * Validate an Application ID against the canonical format.
 *
 * @param string $id Raw input.
 * @return string The cleaned ID if valid, empty string otherwise.
 */
function visati_sanitize_application_id( string $id ): string {
	$id = strtoupper( trim( $id ) );
	return ( 1 === preg_match( '/^VIS-\d{4}-[A-Z2-9]{6}$/', $id ) ) ? $id : '';
}

/**
 * Format an AED amount with thousands separators.
 *
 * @param float|int $amount Amount in AED.
 * @return string
 */
function visati_format_aed( $amount ): string {
	return 'AED ' . number_format( (float) $amount, 0, '.', ',' );
}
