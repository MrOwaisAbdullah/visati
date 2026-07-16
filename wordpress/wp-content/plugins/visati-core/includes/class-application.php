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
	 * @return string Application ID (VIS-YYYY-XXXXXX).
	 */
	public static function create( array $data ): string {
		global $wpdb;

		$id = visati_generate_application_id();

		$wpdb->insert(
			self::table(),
			array(
				'id'                     => $id,
				'visa_type_slug'         => $data['visa_type_slug'],
				'processing_tier'        => $data['processing_tier'] ?? 'standard',
				'nationality'            => $data['nationality'],
				'given_name'             => $data['given_name'],
				'surname'                => $data['surname'],
				'applicant_email'        => $data['applicant_email'],
				'passport_number'        => $data['passport_number'],
				'date_of_birth'          => $data['date_of_birth'],
				'passport_expiry'        => $data['passport_expiry'],
				'travel_date'            => $data['travel_date'],
				'status'                 => 'draft',
			)
		);

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
}
