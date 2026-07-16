<?php
/**
 * Cloudflare R2 storage (S3-compatible) — dependency-free AWS SigV4 signer.
 *
 * Provides server-side upload (signed Authorization header) and presigned GET URLs
 * (admin document viewing, 15-min default). No AWS SDK / composer dependency.
 *
 * Spec references: AWS SigV4; R2 endpoint https://<account>.r2.cloudflarestorage.com,
 * region "auto". S3 path-style is used. Tested signature compatibility may need a live
 * R2 credential check (see migration log) — SigV4 edge cases can only be fully verified
 * against the real service.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_R2_Storage {

	const SERVICE = 's3';
	const REGION  = 'auto';
	const ALGO    = 'AWS4-HMAC-SHA256';

	/**
	 * Whether R2 is configured.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		return Visati_Core_Settings::r2_is_configured();
	}

	/**
	 * Upload raw bytes to R2 under $key.
	 *
	 * @param string $key          Object key (e.g. 2026/VIS-2026-XXXXXX/passport.pdf).
	 * @param string $data         Raw file bytes.
	 * @param string $content_type MIME type.
	 * @return string|WP_Error The key on success, WP_Error on failure.
	 */
	public static function upload( string $key, string $data, string $content_type = 'application/octet-stream' ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'r2_not_configured', __( 'Cloudflare R2 is not configured.', 'visati-core' ) );
		}

		$signed = self::sign_header_request( 'PUT', $key, $data );

		$response = wp_remote_request(
			$signed['url'],
			array(
				'method'  => 'PUT',
				'headers' => $signed['headers'],
				'body'    => $data,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'r2_upload_failed',
				/* translators: %d: HTTP status code */
				sprintf( __( 'R2 upload failed (HTTP %d).', 'visati-core' ), $code ),
				array( 'status' => $code, 'body' => wp_remote_retrieve_body( $response ) )
			);
		}

		return $key;
	}

	/**
	 * Generate a presigned GET URL for an object (admin document viewing).
	 *
	 * @param string $key     Object key.
	 * @param int    $expires Lifetime in seconds (default 900 = 15 min).
	 * @return string Empty string if not configured.
	 */
	public static function presigned_url( string $key, int $expires = 900 ): string {
		if ( ! self::is_configured() ) {
			return '';
		}

		$creds     = self::creds();
		$host_only = wp_parse_url( $creds['endpoint'], PHP_URL_HOST );
		$path      = '/' . rawurlencode( $creds['bucket'] ) . '/' . self::encode_key( $key );

		$now        = time();
		$amz_date   = gmdate( 'Ymd\THis\Z', $now );
		$date_short = gmdate( 'Ymd', $now );
		$scope      = $date_short . '/' . self::REGION . '/' . self::SERVICE . '/aws4_request';

		$query = array(
			'X-Amz-Algorithm'       => self::ALGO,
			'X-Amz-Credential'      => $creds['key'] . '/' . $scope,
			'X-Amz-Date'            => $amz_date,
			'X-Amz-Expires'         => (string) $expires,
			'X-Amz-SignedHeaders'   => 'host',
		);

		$canonical_query    = self::canonical_query( $query );
		$canonical_request  = "GET\n{$path}\n{$canonical_query}\nhost:{$host_only}\n\nhost\nUNSIGNED-PAYLOAD";
		$string_to_sign     = self::ALGO . "\n{$amz_date}\n{$scope}\n" . hash( 'sha256', $canonical_request );
		$signature          = hash_hmac( 'sha256', $string_to_sign, self::signing_key( $creds['secret'], $date_short ) );

		return $creds['endpoint'] . $path . '?' . $canonical_query . '&X-Amz-Signature=' . $signature;
	}

	/**
	 * Delete an object.
	 *
	 * @param string $key Object key.
	 * @return true|WP_Error
	 */
	public static function delete( string $key ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'r2_not_configured', __( 'Cloudflare R2 is not configured.', 'visati-core' ) );
		}
		$signed   = self::sign_header_request( 'DELETE', $key, '' );
		$response = wp_remote_request(
			$signed['url'],
			array(
				'method'  => 'DELETE',
				'headers' => $signed['headers'],
				'timeout' => 30,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'r2_delete_failed', sprintf( __( 'R2 delete failed (HTTP %d).', 'visati-core' ), $code ), array( 'status' => $code ) );
		}
		return true;
	}

	/* ── SigV4 internals ────────────────────────────────────────────── */

	/**
	 * Build a SigV4 Authorization-header signed request (for server-side PUT/GET/DELETE).
	 *
	 * @param string $method HTTP method.
	 * @param string $key    Object key.
	 * @param string $body   Request body (for payload hash).
	 * @return array{url:string,headers:array<string,string>}
	 */
	protected static function sign_header_request( string $method, string $key, string $body ): array {
		$creds        = self::creds();
		$host_only    = wp_parse_url( $creds['endpoint'], PHP_URL_HOST );
		$path         = '/' . rawurlencode( $creds['bucket'] ) . '/' . self::encode_key( $key );
		$now          = time();
		$amz_date     = gmdate( 'Ymd\THis\Z', $now );
		$date_short   = gmdate( 'Ymd', $now );
		$scope        = $date_short . '/' . self::REGION . '/' . self::SERVICE . '/aws4_request';
		$payload_hash = hash( 'sha256', $body );

		$canonical_headers = "host:{$host_only}\nx-amz-content-sha256:{$payload_hash}\nx-amz-date:{$amz_date}\n";
		$signed_headers    = 'host;x-amz-content-sha256;x-amz-date';
		$canonical_request = "{$method}\n{$path}\n\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";

		$string_to_sign = self::ALGO . "\n{$amz_date}\n{$scope}\n" . hash( 'sha256', $canonical_request );
		$signature      = hash_hmac( 'sha256', $string_to_sign, self::signing_key( $creds['secret'], $date_short ) );

		$authorization = self::ALGO . ' Credential=' . $creds['key'] . '/' . $scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $signature;

		return array(
			'url'     => $creds['endpoint'] . $path,
			'headers' => array(
				'x-amz-content-sha256' => $payload_hash,
				'x-amz-date'           => $amz_date,
				'Authorization'        => $authorization,
			),
		);
	}

	/**
	 * Derive the SigV4 signing key.
	 *
	 * @param string $secret     R2 secret access key.
	 * @param string $date_short Short date (YYYYMMDD).
	 * @return string Binary signing key.
	 */
	protected static function signing_key( string $secret, string $date_short ): string {
		$k_date    = hash_hmac( 'sha256', $date_short, 'AWS4' . $secret, true );
		$k_region  = hash_hmac( 'sha256', self::REGION, $k_date, true );
		$k_service = hash_hmac( 'sha256', self::SERVICE, $k_region, true );
		return hash_hmac( 'sha256', 'aws4_request', $k_service, true );
	}

	/**
	 * Encode an object key: URI-encode each path segment, preserve slashes (S3 rule).
	 *
	 * @param string $key Object key.
	 * @return string
	 */
	protected static function encode_key( string $key ): string {
		$parts = explode( '/', $key );
		$parts = array_map( 'rawurlencode', $parts );
		return implode( '/', $parts );
	}

	/**
	 * Canonical query string: sorted by key, RFC 3986 encoded.
	 *
	 * @param array<string,string> $query Query params.
	 * @return string
	 */
	protected static function canonical_query( array $query ): string {
		uksort( $query, 'strnatcmp' );
		$pairs = array();
		foreach ( $query as $k => $v ) {
			$pairs[] = rawurlencode( $k ) . '=' . rawurlencode( $v );
		}
		return implode( '&', $pairs );
	}

	/**
	 * Resolved credentials + endpoint.
	 *
	 * @return array{key:string,secret:string,bucket:string,endpoint:string}
	 */
	protected static function creds(): array {
		return array(
			'key'      => Visati_Core_Settings::r2_access_key(),
			'secret'   => Visati_Core_Settings::r2_secret_key(),
			'bucket'   => Visati_Core_Settings::r2_bucket(),
			'endpoint' => rtrim( Visati_Core_Settings::r2_endpoint(), '/' ),
		);
	}
}
