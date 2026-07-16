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

		// Applications (id = VIS-YYYY-XXXXXX).
		$tables[] = "CREATE TABLE {$prefix}visati_applications (
			id VARCHAR(20) NOT NULL,
			post_id BIGINT UNSIGNED DEFAULT NULL,
			visa_type_slug VARCHAR(50) NOT NULL,
			processing_tier ENUM('standard','express') DEFAULT 'standard',
			nationality VARCHAR(100) NOT NULL,
			given_name VARCHAR(100) NOT NULL,
			surname VARCHAR(100) NOT NULL,
			applicant_email VARCHAR(255) NOT NULL,
			passport_number VARCHAR(50) NOT NULL,
			date_of_birth DATE NOT NULL,
			passport_expiry DATE NOT NULL,
			travel_date DATE NOT NULL,
			status ENUM({$enum_app}) DEFAULT 'draft',
			stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
			stripe_checkout_session_id VARCHAR(255) DEFAULT NULL,
			amount_paid_aed DECIMAL(10,2) DEFAULT 0,
			discount_code VARCHAR(50) DEFAULT NULL,
			discount_amount_aed DECIMAL(10,2) DEFAULT 0,
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

		update_option( 'visati_core_db_version', VISATI_CORE_VERSION );
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
