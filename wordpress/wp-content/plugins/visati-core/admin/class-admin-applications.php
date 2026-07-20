<?php
/**
 * Admin applications — list table + detail view + status update.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Visati_Core_Admin_Applications {

	public static function render_list(): void {
		if ( isset( $_GET['action'] ) && 'view' === $_GET['action'] && isset( $_GET['id'] ) ) {
			self::render_detail( sanitize_text_field( wp_unslash( $_GET['id'] ) ) );
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'visati_applications';

		// Status filter.
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$where = '';
		$params = array();
		if ( $status_filter && in_array( $status_filter, array( 'draft', 'submitted', 'under_review', 'processing', 'approved', 'rejected' ), true ) ) {
			$where = $wpdb->prepare( ' WHERE a.status = %s', $status_filter );
			$params[] = $status_filter;
		}

		// Pagination.
		$per_page = 20;
		$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$table} a {$where}";
		$total     = (int) $wpdb->get_var( $count_sql );

		$sql = "SELECT a.*, vt.post_title AS visa_type_name
				FROM {$table} a
				LEFT JOIN {$wpdb->posts} vt ON a.visa_type_slug = vt.post_name AND vt.post_type = 'visati_visa_type'
				{$where}
				ORDER BY a.created_at DESC
				LIMIT {$per_page} OFFSET {$offset}";
		$applications = $wpdb->get_results( $sql );

		$total_pages = ceil( $total / $per_page );

		include VISATI_CORE_DIR . 'admin/views/applications-list.php';
	}

	public static function render_detail( string $app_id ): void {
		global $wpdb;
		$app_table     = $wpdb->prefix . 'visati_applications';
		$history_table = $wpdb->prefix . 'visati_status_history';
		$docs_table    = $wpdb->prefix . 'visati_documents';
		$payments_table = $wpdb->prefix . 'visati_payments';

		$app = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$app_table} WHERE id = %s", $app_id )
		);

		if ( ! $app ) {
			echo '<div class="wrap"><h1>Application not found</h1><p><a href="' . esc_url( admin_url( 'admin.php?page=visati-applications' ) ) . '">← Back to list</a></p></div>';
			return;
		}

		// Visa type name.
		$visa_name = $wpdb->get_var(
			$wpdb->prepare( "SELECT post_title FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'visati_visa_type'", $app->visa_type_slug )
		);

		// Status history.
		$history = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$history_table} WHERE application_id = %s ORDER BY created_at ASC", $app_id )
		);

		// Documents.
		$documents = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$docs_table} WHERE application_id = %s ORDER BY uploaded_at ASC", $app_id )
		);

		// Payments.
		$payments = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$payments_table} WHERE application_id = %s ORDER BY created_at DESC", $app_id )
		);

		// Handle status update.
		if ( isset( $_POST['visati_update_status'] ) && check_admin_referer( 'visati_status_update' ) ) {
			$new_status = sanitize_text_field( wp_unslash( $_POST['new_status'] ) );
			$note       = sanitize_text_field( wp_unslash( $_POST['status_note'] ) );

			$valid_statuses = array( 'draft', 'submitted', 'under_review', 'processing', 'approved', 'rejected' );
			if ( in_array( $new_status, $valid_statuses, true ) ) {
				// Insert status history.
				$wpdb->insert( $history_table, array(
					'application_id' => $app_id,
					'status'         => $new_status,
					'note'           => $note,
					'changed_by'     => get_current_user_id(),
				), array( '%s', '%s', '%s', '%d' ) );

				// Update application status.
				$wpdb->update( $app_table,
					array( 'status' => $new_status, 'updated_at' => current_time( 'mysql' ) ),
					array( 'id' => $app_id ),
					array( '%s', '%s' ),
					array( '%s' )
				);

				// Send email notification.
				if ( class_exists( 'Visati_Core_Email' ) ) {
					Visati_Core_Email::send_status_update( $app->id, $app->applicant_email, $new_status, $note );
				}

				echo '<div class="notice notice-success is-dismissible"><p>Status updated to <strong>' . esc_html( ucfirst( $new_status ) ) . '</strong>.</p></div>';

				// Refresh data.
				$app    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$app_table} WHERE id = %s", $app_id ) );
				$history = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$history_table} WHERE application_id = %s ORDER BY created_at ASC", $app_id ) );
			}
		}

		// Generate presigned document URLs.
		$signed_docs = array();
		if ( class_exists( 'Visati_Core_R2_Storage' ) && Visati_Core_Settings::r2_is_configured() ) {
			foreach ( $documents as $doc ) {
				$signed_docs[] = (object) array_merge( (array) $doc, array(
					'preview_url' => Visati_Core_R2_Storage::presigned_url( $doc->r2_key ),
				) );
			}
		} else {
			$signed_docs = $documents;
		}

		include VISATI_CORE_DIR . 'admin/views/application-detail.php';
	}
}
