<?php
/**
 * Custom database tables. Created idempotently on activation via dbDelta().
 *
 * Foreign keys are intentionally omitted — dbDelta manages them poorly and they
 * can break activation; integrity is enforced in the application layer + indexes.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Visati_Core_Database {

	/**
	 * Create all custom tables (idempotent).
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$prefix   = $wpdb->prefix;
		$collate  = $wpdb->get_charset_collate();
		$enum_app = "'draft','submitted','reviewing','processing','approved','rejected'";

		$tables = array();

		// Applications (id = VIS-YYYY-XXXXXX). Note: `amount_paid_aed`/
		// `discount_amount_aed` keep their historical column names even after the
		// checkout currency switched to USD — renaming a column on a live table
		// with real financial history is riskier than pairing the existing numeric
		// columns with the new `currency` column, which says which unit the value
		// in those columns is actually denominated in for that row.
		$tables[] = "CREATE TABLE {$prefix}visati_applications (
			id VARCHAR(20) NOT NULL,
			post_id BIGINT UNSIGNED DEFAULT NULL,
			visa_type_slug VARCHAR(50) NOT NULL,
			processing_tier ENUM('standard','express') DEFAULT 'standard',
			nationality VARCHAR(100) NOT NULL,
			given_name VARCHAR(100) NOT NULL,
			surname VARCHAR(100) NOT NULL,
			applicant_email VARCHAR(255) NOT NULL,
			applicant_phone VARCHAR(50) DEFAULT NULL,
			passport_number VARCHAR(50) NOT NULL,
			date_of_birth DATE NOT NULL,
			place_of_birth VARCHAR(150) DEFAULT NULL,
			profession VARCHAR(150) DEFAULT NULL,
			address VARCHAR(255) DEFAULT NULL,
			passport_expiry DATE NOT NULL,
			travel_date DATE NOT NULL,
			travelers_data LONGTEXT DEFAULT NULL,
			status ENUM({$enum_app}) DEFAULT 'draft',
			stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
			stripe_checkout_session_id VARCHAR(255) DEFAULT NULL,
			amount_paid_aed DECIMAL(10,2) DEFAULT 0,
			discount_code VARCHAR(50) DEFAULT NULL,
			discount_amount_aed DECIMAL(10,2) DEFAULT 0,
			currency VARCHAR(3) DEFAULT 'aed',
			sample_id VARCHAR(50) DEFAULT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_status (status),
			KEY idx_email (applicant_email),
			KEY idx_created (created_at),
			KEY idx_stripe (stripe_payment_intent_id)
		) {$collate};";

		// Status history (append-only audit log).
		$tables[] = "CREATE TABLE {$prefix}visati_status_history (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			application_id VARCHAR(20) NOT NULL,
			status ENUM({$enum_app}) NOT NULL,
			note TEXT NULL,
			changed_by BIGINT UNSIGNED DEFAULT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_application (application_id)
		) {$collate};";

		// Document metadata (files live in Cloudflare R2).
		$tables[] = "CREATE TABLE {$prefix}visati_documents (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			application_id VARCHAR(20) NOT NULL,
			document_type VARCHAR(50) NOT NULL,
			r2_key VARCHAR(500) NOT NULL,
			filename VARCHAR(255) NOT NULL,
			size_bytes INT UNSIGNED NOT NULL,
			uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_application (application_id)
		) {$collate};";

		// Payments (Stripe).
		$tables[] = "CREATE TABLE {$prefix}visati_payments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			application_id VARCHAR(20) NOT NULL,
			stripe_payment_intent_id VARCHAR(255) NOT NULL,
			amount_aed DECIMAL(10,2) NOT NULL,
			currency VARCHAR(3) DEFAULT 'aed',
			status ENUM('pending','succeeded','failed') DEFAULT 'pending',
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uk_stripe (stripe_payment_intent_id),
			KEY idx_application (application_id)
		) {$collate};";

		// Inquiries (contact form submissions).
		$tables[] = "CREATE TABLE {$prefix}visati_inquiries (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(200) NOT NULL,
			email VARCHAR(255) NOT NULL,
			phone VARCHAR(50) DEFAULT NULL,
			subject VARCHAR(300) NOT NULL,
			message TEXT NOT NULL,
			resolved TINYINT(1) DEFAULT 0,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_resolved (resolved)
		) {$collate};";

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		/* Safety migration: dbDelta() won't add columns to existing tables.
		   Check for travelers_data and add it if missing. */
		self::ensure_columns();

		update_option( 'visati_core_db_version', VISATI_CORE_VERSION );
	}

	/**
	 * Self-healing column check. dbDelta() does not add columns to tables that
	 * already exist, so any column introduced after the first activation must be
	 * added explicitly. Runs on activation and on every admin init (cheap).
	 *
	 * @return void
	 */
	public static function ensure_columns(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		// Only run in admin, during REST API requests, or under WP-CLI (schema
		// changes need a real write context — but WP-CLI is a legitimate one too,
		// not just wp-admin/REST; missing this meant `wp` commands could never
		// trigger a pending migration, only an actual wp-admin page load could).
		if ( ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix;
		$table  = $prefix . 'visati_applications';

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $columns ) ) {
			return;
		}

		if ( ! in_array( 'travelers_data', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN travelers_data LONGTEXT DEFAULT NULL AFTER travel_date" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( ! in_array( 'applicant_phone', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN applicant_phone VARCHAR(50) DEFAULT NULL AFTER applicant_email" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( ! in_array( 'place_of_birth', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN place_of_birth VARCHAR(150) DEFAULT NULL AFTER date_of_birth" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if ( ! in_array( 'profession', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN profession VARCHAR(150) DEFAULT NULL AFTER place_of_birth" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if ( ! in_array( 'address', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN address VARCHAR(255) DEFAULT NULL AFTER profession" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Checkout currency switched from AED to USD — existing rows predate this
		// column and were genuinely charged in AED, so the column default ('aed')
		// is correct for them; new rows explicitly set 'usd' at insert time.
		if ( ! in_array( 'currency', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN currency VARCHAR(3) DEFAULT 'aed' AFTER discount_amount_aed" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$pay_table   = $prefix . 'visati_payments';
		$pay_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$pay_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( is_array( $pay_columns ) && ! in_array( 'currency', $pay_columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$pay_table} ADD COLUMN currency VARCHAR(3) DEFAULT 'aed' AFTER amount_aed" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Inquiries: the admin UI (list/detail/filters) has always assumed a 3-state
		// `status` column (new/read/resolved), but the table only ever had a binary
		// `resolved` flag — add the missing column and backfill from `resolved`.
		$inq_table   = $prefix . 'visati_inquiries';
		$inq_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$inq_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( is_array( $inq_columns ) && ! in_array( 'status', $inq_columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$inq_table} ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'new' AFTER resolved" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "UPDATE {$inq_table} SET status = 'resolved' WHERE resolved = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Table names (helper).
	 *
	 * @return array<string,string>
	 */
	public static function table_names(): array {
		global $wpdb;
		$prefix = $wpdb->prefix;
		return array(
			'applications'   => "{$prefix}visati_applications",
			'status_history' => "{$prefix}visati_status_history",
			'documents'      => "{$prefix}visati_documents",
			'payments'       => "{$prefix}visati_payments",
			'inquiries'      => "{$prefix}visati_inquiries",
		);
	}
}
