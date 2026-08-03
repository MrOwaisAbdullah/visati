<?php
/**
 * REST endpoint: GET /visati/v1/track?id=VIS-YYYY-XXXXXX
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Track_Controller {

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'visati/v1',
			'/track',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_track' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'visati_sanitize_application_id',
						'validate_callback' => function ( $val ) {
							return '' !== visati_sanitize_application_id( $val );
						},
					),
				),
			)
		);
	}

	public static function handle_track( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		// Rate limit: application IDs are guessable in principle (6 chars from a
		// 32-char alphabet), and this endpoint now also hands out a presigned
		// download link for the issued visa document, not just status text —
		// throttle brute-force ID scanning per IP.
		if ( ! visati_rate_limit( 'track', 20, 600 ) ) {
			return new WP_REST_Response( array(
				'message' => __( 'Too many lookups. Please try again in a few minutes.', 'visati-core' ),
			), 429 );
		}

		$app_id = $request->get_param( 'id' );
		$app_table      = $wpdb->prefix . 'visati_applications';
		$history_table  = $wpdb->prefix . 'visati_status_history';

		$app = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$app_table} WHERE id = %s", $app_id )
		);

		if ( ! $app ) {
			return new WP_REST_Response( array(
				'id'      => $app_id,
				'message' => 'Application not found. Please check your ID and try again.',
			), 404 );
		}

		// Fetch status history.
		$history = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, note, created_at FROM {$history_table} WHERE application_id = %s ORDER BY created_at DESC",
				$app_id
			)
		);

		$timeline = array();
		foreach ( $history as $row ) {
			$timeline[] = array(
				'status' => $row->status,
				'note'   => $row->note,
				'date'   => gmdate( 'M j, Y g:i A', strtotime( $row->created_at ) ),
			);
		}

		// Get visa type title.
		$visa_title = '';
		if ( $app->visa_type_slug ) {
			$visa_post = get_page_by_path( $app->visa_type_slug, OBJECT, 'visati_visa_type' );
			if ( ! $visa_post ) {
				// Try by post_name.
				$visa_post = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT post_title FROM {$wpdb->prefix}posts WHERE post_type = 'visati_visa_type' AND post_name = %s",
						$app->visa_type_slug
					)
				);
			}
			$visa_title = $visa_post ? $visa_post->post_title : $app->visa_type_slug;
		}

		// Issued visa document(s) — admin-uploaded, reserved document_type prefix
		// 'issued_visa_'. One combined file can cover every traveler, and/or each
		// traveler can have their own — return whichever exist. A short-lived
		// presigned R2 URL is generated fresh on every request rather than storing
		// a permanent public link — the application ID itself is the applicant's
		// "auth" here, same as the rest of this endpoint.
		$visa_documents = array();
		if ( class_exists( 'Visati_Core_R2_Storage' ) && Visati_Core_Settings::r2_is_configured() ) {
			$docs_table = $wpdb->prefix . 'visati_documents';
			$docs       = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$docs_table} WHERE application_id = %s AND document_type LIKE %s ORDER BY id ASC",
					$app_id,
					'issued_visa_%'
				)
			);
			$travelers_data = ! empty( $app->travelers_data ) ? json_decode( $app->travelers_data, true ) : array();
			// Only label documents when there's more than one to tell apart — a
			// single-traveler application (or a lone combined file) has nothing
			// ambiguous to disambiguate, so the button just reads "Download Your
			// Visa" with no "All Travelers"/name suffix.
			$needs_label = count( $docs ) > 1;
			foreach ( $docs as $doc ) {
				$label = '';
				if ( $needs_label ) {
					$slot_key = substr( $doc->document_type, strlen( 'issued_visa_' ) );
					if ( 'combined' === $slot_key ) {
						$label = 'All Travelers';
					} elseif ( 'primary' === $slot_key ) {
						$label = trim( $app->given_name . ' ' . $app->surname );
					} elseif ( preg_match( '/^traveler_(\d+)$/', $slot_key, $m ) && is_array( $travelers_data ) && isset( $travelers_data[ (int) $m[1] ] ) ) {
						$label = $travelers_data[ (int) $m[1] ]['name'] ?? $slot_key;
					} else {
						$label = $slot_key;
					}
				}
				$visa_documents[] = array(
					'label'        => $label,
					'filename'     => $doc->filename,
					'download_url' => Visati_Core_R2_Storage::presigned_url( $doc->r2_key, 1800 ),
				);
			}
		}

		return new WP_REST_Response( array(
			'id'             => $app->id,
			'status'         => $app->status,
			'visa_type'      => $visa_title,
			'timeline'       => $timeline,
			'visa_documents' => $visa_documents,
		), 200 );
	}
}
