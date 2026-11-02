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
			add_action( 'rest_api_init', array( __CLASS__, 'register_bot_routes' ) );
			add_action( 'init', array( 'Visati_Core_Roles', 'ensure_bot_role' ) );
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

	/**
	 * Register bot routes (Application Password auth). Every response mints a fresh, short-lived
	 * R2 URL, so links never go stale. Only applicant documents are exposed; issued visa files are excluded.
	 *
	 * @return void
	 */
	public static function register_bot_routes(): void {
		register_rest_route(
			Visati_Core_REST_API::NAMESPACE,
			'/applications/(?P<app_id>[A-Za-z0-9-]+)/documents',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'bot_list_documents' ),
				'permission_callback' => array( __CLASS__, 'bot_permission' ),
			)
		);
		register_rest_route(
			Visati_Core_REST_API::NAMESPACE,
			'/documents/(?P<id>\d+)/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'bot_download' ),
				'permission_callback' => array( __CLASS__, 'bot_permission' ),
			)
		);
	}

	/**
	 * Permission check for bot routes: feature toggle, login, capability, rate limit.
	 *
	 * @return true|WP_Error
	 */
	public static function bot_permission() {
		if ( ! Visati_Core_Settings::bot_access_enabled() ) {
			return new WP_Error( 'visati_bot_disabled', 'Bot document access is disabled.', array( 'status' => 403 ) );
		}
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'visati_auth_required', 'Authentication required.', array( 'status' => 401 ) );
		}
		if ( ! current_user_can( 'visati_download_documents' ) ) {
			return new WP_Error( 'visati_forbidden', 'Not allowed.', array( 'status' => 403 ) );
		}
		if ( ! visati_rate_limit( 'bot_docs', 120, 600 ) ) {
			return new WP_Error( 'visati_rate_limited', 'Too many requests.', array( 'status' => 429 ) );
		}
		return true;
	}

	/**
	 * GET /applications/{app_id}/documents — applicant documents, each with a per-request download URL.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function bot_list_documents( WP_REST_Request $req ) {
		$app_id = visati_sanitize_application_id( (string) $req['app_id'] );
		if ( '' === $app_id ) {
			return new WP_Error( 'visati_bad_id', 'Invalid application ID.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, document_type, filename, size_bytes, uploaded_at FROM {$wpdb->prefix}visati_documents WHERE application_id = %s AND document_type NOT LIKE %s ORDER BY uploaded_at ASC",
				$app_id,
				'issued\_visa\_%'
			)
		);

		$docs = array();
		foreach ( (array) $rows as $row ) {
			$docs[] = array(
				'id'            => (int) $row->id,
				'document_type' => $row->document_type,
				'filename'      => $row->filename,
				'size_bytes'    => (int) $row->size_bytes,
				'uploaded_at'   => $row->uploaded_at,
				'download_url'  => rest_url( Visati_Core_REST_API::NAMESPACE . '/documents/' . (int) $row->id . '/download' ),
			);
		}

		return rest_ensure_response( array( 'application_id' => $app_id, 'documents' => $docs ) );
	}

	/**
	 * GET /documents/{id}/download — returns a fresh 5-minute R2 URL for one applicant document.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function bot_download( WP_REST_Request $req ) {
		global $wpdb;
		$id  = absint( $req['id'] );
		$doc = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT r2_key, filename FROM {$wpdb->prefix}visati_documents WHERE id = %d AND document_type NOT LIKE %s",
				$id,
				'issued\_visa\_%'
			)
		);
		if ( ! $doc ) {
			return new WP_Error( 'visati_not_found', 'Document not found.', array( 'status' => 404 ) );
		}

		$url = Visati_Core_R2_Storage::presigned_url( $doc->r2_key, 300 );
		if ( ! $url ) {
			return new WP_Error( 'visati_storage_unavailable', 'Document storage is not configured.', array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'id' => $id, 'filename' => $doc->filename, 'url' => $url, 'expires_in' => 300 ) );
	}
}
