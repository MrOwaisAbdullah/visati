<?php
/**
 * REST controller: document uploads.
 *   POST /visati/v1/documents  — validate + upload a file to Cloudflare R2.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Documents_Controller {

	const MAX_BYTES = 5242880; // 5 MB.

	/**
	 * Allowed MIME types mapped to a short extension + storage content-type.
	 *
	 * @var array<string,array{ext:string,ct:string}>
	 */
	const ALLOWED = array(
		'application/pdf' => array( 'ext' => 'pdf', 'ct' => 'application/pdf' ),
		'image/jpeg'       => array( 'ext' => 'jpg', 'ct' => 'image/jpeg' ),
		'image/png'        => array( 'ext' => 'png', 'ct' => 'image/png' ),
	);

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
			'/documents',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'upload' ),
					'permission_callback' => function () {
					// Require REST nonce — sent by apply form JS via X-WP-Nonce header.
					if ( ! isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
						return false;
					}
					return wp_verify_nonce( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ), 'wp_rest' );
				},
				),
			)
		);
	}

	/**
	 * Handle a document upload.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function upload( WP_REST_Request $req ) {
		// 60/10min — raised from 20 now that a multi-traveler application uploads
		// one file per person per document type (5 doc types × several travelers
		// can legitimately exceed 20 uploads in one sitting).
		if ( ! visati_rate_limit( 'documents', 60, 600 ) ) {
			return Visati_Core_REST_API::error( 'rate_limited', __( 'Too many uploads. Please slow down.', 'visati-core' ), 429 );
		}

		$app_id = visati_sanitize_application_id( (string) ( $req->get_param( 'application_id' ) ?? '' ) );
		if ( '' === $app_id || ! Visati_Core_Application::exists( $app_id ) ) {
			return Visati_Core_REST_API::error( 'invalid_application', __( 'Invalid application ID.', 'visati-core' ), 404 );
		}

		$document_type = sanitize_text_field( wp_unslash( $req->get_param( 'document_type' ) ?? 'document' ) );

		$files = $req->get_file_params();
		if ( empty( $files['document'] ) || UPLOAD_ERR_OK !== $files['document']['error'] ) {
			return Visati_Core_REST_API::error( 'no_file', __( 'No file uploaded.', 'visati-core' ), 400 );
		}

		$f = $files['document'];
		if ( $f['size'] > self::MAX_BYTES ) {
			return Visati_Core_REST_API::error( 'too_large', __( 'File exceeds the 5 MB limit.', 'visati-core' ), 413 );
		}

		$mime = $f['type'];
		if ( ! isset( self::ALLOWED[ $mime ] ) ) {
			return Visati_Core_REST_API::error( 'bad_type', __( 'Only PDF, JPG, and PNG are accepted.', 'visati-core' ), 415 );
		}

		$data = file_get_contents( $f['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- temp upload.
		if ( false === $data ) {
			return Visati_Core_REST_API::error( 'read_failed', __( 'Could not read uploaded file.', 'visati-core' ), 500 );
		}

		$allowed  = self::ALLOWED[ $mime ];
		$year     = gmdate( 'Y' );
		$key      = $year . '/' . $app_id . '/' . bin2hex( random_bytes( 8 ) ) . '.' . $allowed['ext'];
		$filename = sanitize_file_name( $f['name'] );

		$result = Visati_Core_R2_Storage::upload( $key, $data, $allowed['ct'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'visati_documents',
			array(
				'application_id' => $app_id,
				'document_type'  => $document_type,
				'r2_key'         => $key,
				'filename'       => $filename,
				'size_bytes'     => strlen( $data ),
			)
		);

		return rest_ensure_response(
			array(
				'document_id' => (int) $wpdb->insert_id,
				'r2_key'      => $key,
				'filename'    => $filename,
				'size_bytes'  => strlen( $data ),
			)
		);
	}
}
