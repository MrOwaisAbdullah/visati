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

/**
 * Cache-busting version for a plugin asset, based on its file mtime.
 *
 * Returns filemtime() when the file exists (so every edit auto-busts browser +
 * CDN caches), falling back to VISATI_CORE_VERSION for safety.
 *
 * @param string $relative_path Path relative to the plugin root (e.g. 'assets/css/apply.css').
 * @return int|string
 */
function visati_asset_version( string $relative_path ) {
	$path = VISATI_CORE_DIR . ltrim( $relative_path, '/' );
	return file_exists( $path ) ? filemtime( $path ) : VISATI_CORE_VERSION;
}


/**
 * Simple per-IP rate limiter using transients.
 *
 * Counts hits in a rolling window; returns true if the caller is still within
 * the limit, false once exceeded. Protects public write endpoints (applications,
 * documents, inquiries, checkout-session) from spam/abuse before they hit logic.
 *
 * @param string $bucket  Unique bucket key (e.g. 'inquiries').
 * @param int    $limit   Max requests allowed in the window.
 * @param int    $window  Window length in seconds.
 * @return bool True if allowed, false if rate-limited.
 */
function visati_rate_limit( string $bucket, int $limit = 10, int $window = 600 ): bool {
	$ip = '';
	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	// Honor proxy/CDN forwarding (Cloudflare/Hostinger) when present.
	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
	} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$forwarded = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
		$ip = trim( $forwarded[0] );
	}
	if ( '' === $ip ) {
		// No IP detected — allow rather than block (defensive default).
		return true;
	}

	$key       = 'visati_rl_' . $bucket . '_' . md5( $ip );
	$count     = (int) get_transient( $key );
	if ( 0 === $count ) {
		set_transient( $key, 1, $window );
		return true;
	}
	if ( $count >= $limit ) {
		return false;
	}
	set_transient( $key, $count + 1, $window );
	return true;
}

