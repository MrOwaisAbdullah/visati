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

		return new WP_REST_Response( array(
			'id'        => $app->id,
			'status'    => $app->status,
			'visa_type' => $visa_title,
			'timeline'  => $timeline,
		), 200 );
	}
}
