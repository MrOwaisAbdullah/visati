<?php
/**
 * Application model — CRUD/query for the visati_applications + status_history tables.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Application {

	/**
	 * Applications table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'visati_applications';
	}

	/**
	 * Status history table name.
	 *
	 * @return string
	 */
	public static function history_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'visati_status_history';
	}

	/**
	 * Create a draft application and return its new ID.
	 *
	 * @param array $data Sanitized application fields.
	 * @return string|WP_Error Application ID (VIS-YYYY-XXXXXX) or WP_Error on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$id = visati_generate_application_id();

		$result = $wpdb->insert(
			self::table(),
			array(
				'id'                     => $id,
				'visa_type_slug'         => $data['visa_type_slug'],
				'processing_tier'        => $data['processing_tier'] ?? 'standard',
				'nationality'            => $data['nationality'],
				'given_name'             => $data['given_name'],
				'surname'                => $data['surname'],
				'applicant_email'        => $data['applicant_email'],
				'applicant_phone'        => $data['applicant_phone'] ?? '',
				'passport_number'        => $data['passport_number'],
				'date_of_birth'          => $data['date_of_birth'],
				'place_of_birth'         => $data['place_of_birth'] ?? '',
				'profession'             => $data['profession'] ?? '',
				'address'                => $data['address'] ?? '',
				'passport_expiry'        => $data['passport_expiry'],
				'travel_date'            => $data['travel_date'],
				'travelers_data'         => $data['travelers_data'] ?? null,
				'status'                 => 'draft',
			)
		);

		if ( false === $result ) {
			return new WP_Error( 'db_insert_failed', __( 'Failed to create application.', 'visati-core' ), array( 'db_error' => $wpdb->last_error ) );
		}

		return $id;
	}

	/**
	 * Fetch a single application row.
	 *
	 * @param string $id Application ID.
	 * @return array|null
	 */
	public static function get( string $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %s', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is prefix-derived.
		return $row ? $row : null;
	}

	/**
	 * Whether an application exists.
	 *
	 * @param string $id Application ID.
	 * @return bool
	 */
	public static function exists( string $id ): bool {
		return null !== self::get( $id );
	}

	/**
	 * Append a status_history row (append-only — never mutates prior rows).
	 *
	 * @param string $app_id      Application ID.
	 * @param string $status      New status.
	 * @param string $note        Optional note.
	 * @param int    $changed_by  Admin user ID (0 for system).
	 * @return void
	 */
	public static function add_status( string $app_id, string $status, string $note = '', int $changed_by = 0 ): void {
		global $wpdb;
		$wpdb->insert(
			self::history_table(),
			array(
				'application_id' => $app_id,
				'status'         => $status,
				'note'           => $note,
				'changed_by'     => $changed_by > 0 ? $changed_by : null,
			)
		);
	}

	/**
	 * Permanently delete an application: its R2-stored documents, the documents
	 * table rows, and the status history — freeing the R2 storage those files
	 * used. Deliberately does NOT touch `wp_visati_payments` — payment records
	 * are kept for accounting/audit purposes even after the application itself
	 * is gone (the Dashboard's revenue totals query payments directly, not
	 * joined to applications, so this doesn't affect revenue reporting).
	 *
	 * @param string $app_id Application ID.
	 * @return true|WP_Error
	 */
	public static function delete( string $app_id ) {
		global $wpdb;

		if ( ! self::exists( $app_id ) ) {
			return new WP_Error( 'not_found', __( 'Application not found.', 'visati-core' ) );
		}

		$docs_table = $wpdb->prefix . 'visati_documents';
		$documents  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$docs_table} WHERE application_id = %s", $app_id )
		);

		if ( class_exists( 'Visati_Core_R2_Storage' ) && Visati_Core_Settings::r2_is_configured() ) {
			foreach ( $documents as $doc ) {
				Visati_Core_R2_Storage::delete( $doc->r2_key );
			}
		}

		$wpdb->delete( $docs_table, array( 'application_id' => $app_id ), array( '%s' ) );
		$wpdb->delete( self::history_table(), array( 'application_id' => $app_id ), array( '%s' ) );
		$wpdb->delete( self::table(), array( 'id' => $app_id ), array( '%s' ) );

		return true;
	}
}
