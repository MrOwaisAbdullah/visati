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

	/**
	 * Issued-visa-document upload limit. Larger than the 5 MB applicant-upload
	 * limit — a multi-page scanned visa, or a zip of several travelers'
	 * documents, can reasonably run bigger than a single passport photo.
	 */
	const VISA_DOC_MAX_BYTES = 10485760; // 10 MB.

	/**
	 * MIME types accepted for admin-issued visa documents. Broader than the
	 * applicant-upload allow-list (`Visati_Core_Documents_Controller::ALLOWED`)
	 * since the admin may also want to hand back a zip/rar bundling several
	 * travelers' files at once.
	 *
	 * @var array<string,array{ext:string,ct:string}>
	 */
	const VISA_DOC_ALLOWED = array(
		'application/pdf'              => array( 'ext' => 'pdf', 'ct' => 'application/pdf' ),
		'image/jpeg'                   => array( 'ext' => 'jpg', 'ct' => 'image/jpeg' ),
		'image/png'                    => array( 'ext' => 'png', 'ct' => 'image/png' ),
		'application/zip'              => array( 'ext' => 'zip', 'ct' => 'application/zip' ),
		'application/x-zip-compressed' => array( 'ext' => 'zip', 'ct' => 'application/zip' ),
		'application/x-rar-compressed' => array( 'ext' => 'rar', 'ct' => 'application/x-rar-compressed' ),
		'application/vnd.rar'          => array( 'ext' => 'rar', 'ct' => 'application/x-rar-compressed' ),
	);

	/**
	 * Build the set of upload "slots" for an application's issued visa
	 * document(s). A single-traveler application (no additional travelers) gets
	 * just one slot — there's nothing to "combine" for one person. An
	 * application with additional travelers gets a combined slot (one file
	 * covering everyone) plus one slot per traveler (primary applicant + each
	 * additional traveler), and the admin chooses which mode to use.
	 *
	 * @param object $app Application row.
	 * @return array<string,string> Slot key => human label.
	 */
	private static function traveler_slots( $app ): array {
		$travelers = ! empty( $app->travelers_data ) ? json_decode( $app->travelers_data, true ) : array();
		$travelers = is_array( $travelers ) ? $travelers : array();

		if ( empty( $travelers ) ) {
			return array( 'primary' => trim( $app->given_name . ' ' . $app->surname ) );
		}

		$slots = array();
		$slots['combined'] = 'All Travelers (single combined file)';
		$slots['primary']  = trim( $app->given_name . ' ' . $app->surname ) . ' (Primary Applicant)';

		foreach ( $travelers as $i => $t ) {
			$name = ! empty( $t['name'] ) ? $t['name'] : ( 'Traveler ' . ( $i + 2 ) );
			$rel  = ! empty( $t['relationship'] ) ? ' (' . ucfirst( $t['relationship'] ) . ')' : '';
			$slots[ 'traveler_' . $i ] = $name . $rel;
		}

		return $slots;
	}

	public static function render_list(): void {
		if ( isset( $_GET['action'] ) && 'view' === $_GET['action'] && isset( $_GET['id'] ) ) {
			self::render_detail( sanitize_text_field( wp_unslash( $_GET['id'] ) ) );
			return;
		}

		// Bulk delete — permanently removes each selected application, its R2
		// documents, and its status history (payment records are kept). Requires
		// the nonce below AND a client-side confirm() before the form can submit
		// (see applications-list.php), since this is not reversible.
		$bulk_deleted = 0;
		if ( isset( $_POST['visati_bulk_delete'] ) && check_admin_referer( 'visati_bulk_delete_applications' ) ) {
			$ids = isset( $_POST['app_ids'] ) && is_array( $_POST['app_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['app_ids'] ) ) : array();
			foreach ( $ids as $id ) {
				$result = Visati_Core_Application::delete( $id );
				if ( ! is_wp_error( $result ) ) {
					++$bulk_deleted;
				}
			}
			if ( $bulk_deleted > 0 ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d application deleted.', '%d applications deleted.', $bulk_deleted, 'visati-core' ), $bulk_deleted ) ) . '</p></div>';
			}
		}

		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Application deleted — its documents were removed from storage.</p></div>';
		}

		global $wpdb;
		$table = $wpdb->prefix . 'visati_applications';

		// Filters.
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$visa_filter   = isset( $_GET['visa_type'] ) ? sanitize_text_field( wp_unslash( $_GET['visa_type'] ) ) : '';

		$where_parts = array();
		$params      = array();

		if ( $status_filter && in_array( $status_filter, array( 'draft', 'submitted', 'under_review', 'processing', 'approved', 'rejected' ), true ) ) {
			$where_parts[] = 'a.status = %s';
			$params[]      = $status_filter;
		}

		if ( $visa_filter ) {
			$where_parts[] = 'a.visa_type_slug = %s';
			$params[]      = $visa_filter;
		}

		if ( $search ) {
			$like          = '%' . $wpdb->esc_like( $search ) . '%';
			$where_parts[] = '(a.id LIKE %s OR a.given_name LIKE %s OR a.surname LIKE %s OR a.applicant_email LIKE %s OR a.nationality LIKE %s)';
			array_push( $params, $like, $like, $like, $like, $like );
		}

		$where = $where_parts ? ' WHERE ' . implode( ' AND ', $where_parts ) : '';

		// Pagination.
		$per_page = 20;
		$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$table} a {$where}";
		$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

		$sql = "SELECT a.*, vt.post_title AS visa_type_name
				FROM {$table} a
				LEFT JOIN {$wpdb->posts} vt ON a.visa_type_slug = vt.post_name AND vt.post_type = 'visati_visa_type'
				{$where}
				ORDER BY a.created_at DESC
				LIMIT {$per_page} OFFSET {$offset}";
		$applications = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );

		$total_pages = ceil( $total / $per_page );

		// Visa types for the filter dropdown.
		$visa_types = $wpdb->get_results(
			"SELECT post_title, post_name FROM {$wpdb->posts} WHERE post_type = 'visati_visa_type' AND post_status = 'publish' ORDER BY post_title ASC"
		);

		include VISATI_CORE_DIR . 'admin/views/applications-list.php';
	}

	public static function render_detail( string $app_id ): void {
		global $wpdb;
		$app_table     = $wpdb->prefix . 'visati_applications';
		$history_table = $wpdb->prefix . 'visati_status_history';
		$docs_table    = $wpdb->prefix . 'visati_documents';
		$payments_table = $wpdb->prefix . 'visati_payments';

		// Delete application — permanently removes this application, its R2
		// documents, and its status history (payment record is kept). The row is
		// gone after this, so redirect back to the list rather than continue
		// rendering a detail page for an application that no longer exists.
		if ( isset( $_POST['visati_delete_application'] ) && check_admin_referer( 'visati_delete_application_' . $app_id ) ) {
			$result = Visati_Core_Application::delete( $app_id );
			$redirect = admin_url( 'admin.php?page=visati-applications' );
			if ( ! is_wp_error( $result ) ) {
				$redirect = add_query_arg( 'deleted', '1', $redirect );
			}
			wp_safe_redirect( $redirect );
			exit;
		}

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

		// Upload "slots" for issued visa document(s) — a combined slot covering
		// every traveler, plus one slot per traveler on this application. Admin
		// can use whichever fits; nothing forces one file vs. one-per-person.
		$traveler_slots = self::traveler_slots( $app );

		// Handle a batch issued-visa-document upload — one shared form with a
		// file input per slot (name="visa_documents[<slot>]") and a single
		// submit button, so the admin can choose files for as many slots as
		// they want and upload them all in one click rather than one form (and
		// one click) per slot. Stored in the same documents table as applicant
		// uploads, tagged 'issued_visa_<slot>' — only the latest file per slot
		// is kept, so re-uploading to a slot replaces it without touching others.
		if ( isset( $_POST['visati_upload_visa_docs'] ) && check_admin_referer( 'visati_upload_visa_docs_' . $app_id ) ) {
			$uploaded_count = 0;
			$upload_errors  = array();

			foreach ( $traveler_slots as $traveler_key => $traveler_label ) {
				$err = $_FILES['visa_documents']['error'][ $traveler_key ] ?? UPLOAD_ERR_NO_FILE;
				if ( UPLOAD_ERR_NO_FILE === $err ) {
					continue; // Nothing chosen for this slot — skip silently.
				}
				if ( UPLOAD_ERR_OK !== $err ) {
					$upload_errors[] = $traveler_label . ': upload failed.';
					continue;
				}

				$size = (int) ( $_FILES['visa_documents']['size'][ $traveler_key ] ?? 0 );
				$type = (string) ( $_FILES['visa_documents']['type'][ $traveler_key ] ?? '' );
				$tmp  = (string) ( $_FILES['visa_documents']['tmp_name'][ $traveler_key ] ?? '' );
				$name = (string) ( $_FILES['visa_documents']['name'][ $traveler_key ] ?? '' );

				if ( $size > self::VISA_DOC_MAX_BYTES ) {
					$upload_errors[] = $traveler_label . ': exceeds the 10 MB limit.';
					continue;
				}
				if ( ! isset( self::VISA_DOC_ALLOWED[ $type ] ) ) {
					$upload_errors[] = $traveler_label . ': only PDF, JPG, PNG, ZIP, or RAR are accepted.';
					continue;
				}
				if ( ! class_exists( 'Visati_Core_R2_Storage' ) || ! Visati_Core_Settings::r2_is_configured() ) {
					$upload_errors[] = $traveler_label . ': Cloudflare R2 is not configured.';
					continue;
				}

				$data = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- temp upload.
				if ( false === $data ) {
					$upload_errors[] = $traveler_label . ': could not read the uploaded file.';
					continue;
				}

				$doc_type = 'issued_visa_' . $traveler_key;
				$allowed  = self::VISA_DOC_ALLOWED[ $type ];
				$key      = gmdate( 'Y' ) . '/' . $app_id . '/' . $doc_type . '-' . bin2hex( random_bytes( 8 ) ) . '.' . $allowed['ext'];
				$result   = Visati_Core_R2_Storage::upload( $key, $data, $allowed['ct'] );

				if ( is_wp_error( $result ) ) {
					$upload_errors[] = $traveler_label . ': ' . $result->get_error_message();
					continue;
				}

				// Remove any previous file for this same slot — only the latest is kept per slot.
				$old_docs = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$docs_table} WHERE application_id = %s AND document_type = %s", $app_id, $doc_type )
				);
				foreach ( $old_docs as $old_doc ) {
					Visati_Core_R2_Storage::delete( $old_doc->r2_key );
					$wpdb->delete( $docs_table, array( 'id' => $old_doc->id ), array( '%d' ) );
				}

				$wpdb->insert(
					$docs_table,
					array(
						'application_id' => $app_id,
						'document_type'  => $doc_type,
						'r2_key'         => $key,
						'filename'       => sanitize_file_name( $name ),
						'size_bytes'     => strlen( $data ),
					)
				);
				++$uploaded_count;
			}

			if ( $uploaded_count > 0 ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d visa document uploaded.', '%d visa documents uploaded.', $uploaded_count, 'visati-core' ), $uploaded_count ) ) . ' The applicant can now download from the Track page.</p></div>';
			}
			if ( ! empty( $upload_errors ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( implode( ' ', $upload_errors ) ) . '</p></div>';
			}
			if ( 0 === $uploaded_count && empty( $upload_errors ) ) {
				echo '<div class="notice notice-warning is-dismissible"><p>No files were selected.</p></div>';
			}

			// Refresh documents so the view reflects the new upload(s) immediately.
			$documents = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$docs_table} WHERE application_id = %s ORDER BY uploaded_at ASC", $app_id )
			);
		}

		// Remove one slot's issued-visa document without uploading a replacement
		// (e.g. the wrong file was uploaded by mistake) — applicant loses that
		// slot's download link on the Track page until/unless a new one is
		// uploaded. Other slots are untouched.
		if ( isset( $_POST['visati_delete_visa_doc'], $_POST['traveler_key'] ) ) {
			$traveler_key = sanitize_key( wp_unslash( $_POST['traveler_key'] ) );
			if ( isset( $traveler_slots[ $traveler_key ] ) && check_admin_referer( 'visati_delete_visa_doc_' . $app_id . '_' . $traveler_key ) ) {
				$doc_type = 'issued_visa_' . $traveler_key;
				$old_docs = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$docs_table} WHERE application_id = %s AND document_type = %s", $app_id, $doc_type )
				);
				foreach ( $old_docs as $old_doc ) {
					if ( class_exists( 'Visati_Core_R2_Storage' ) ) {
						Visati_Core_R2_Storage::delete( $old_doc->r2_key );
					}
					$wpdb->delete( $docs_table, array( 'id' => $old_doc->id ), array( '%d' ) );
				}
				echo '<div class="notice notice-success is-dismissible"><p>Visa document removed for ' . esc_html( $traveler_slots[ $traveler_key ] ) . '.</p></div>';

				// Refresh documents so the view reflects the removal immediately.
				$documents = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$docs_table} WHERE application_id = %s ORDER BY uploaded_at ASC", $app_id )
				);
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
