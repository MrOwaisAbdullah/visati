<?php
/**
 * Admin inquiries — list + detail + resolve.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Visati_Core_Admin_Inquiries {

	public static function render_list(): void {
		if ( isset( $_GET['action'] ) && 'view' === $_GET['action'] && isset( $_GET['id'] ) ) {
			self::render_detail( (int) $_GET['id'] );
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'visati_inquiries';

		// Filters.
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$where_parts = array();
		$params      = array();

		if ( $status_filter && in_array( $status_filter, array( 'new', 'read', 'resolved' ), true ) ) {
			$where_parts[] = 'status = %s';
			$params[]      = $status_filter;
		}

		if ( $search ) {
			$like          = '%' . $wpdb->esc_like( $search ) . '%';
			$where_parts[] = '(name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s)';
			array_push( $params, $like, $like, $like, $like );
		}

		$where = $where_parts ? ' WHERE ' . implode( ' AND ', $where_parts ) : '';

		$per_page = 20;
		$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		$count_sql   = "SELECT COUNT(*) FROM {$table} {$where}";
		$total       = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );
		$list_sql    = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT {$per_page} OFFSET {$offset}";
		$inquiries   = $params ? $wpdb->get_results( $wpdb->prepare( $list_sql, $params ) ) : $wpdb->get_results( $list_sql );
		$total_pages = ceil( $total / $per_page );

		include VISATI_CORE_DIR . 'admin/views/inquiries-list.php';
	}

	public static function render_detail( int $id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'visati_inquiries';

		$inquiry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		if ( ! $inquiry ) {
			echo '<div class="wrap"><h1>Inquiry not found</h1><p><a href="' . esc_url( admin_url( 'admin.php?page=visati-inquiries' ) ) . '">← Back to list</a></p></div>';
			return;
		}

		// Mark as read.
		if ( 'new' === $inquiry->status ) {
			$wpdb->update( $table, array( 'status' => 'read' ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
			$inquiry->status = 'read';
		}

		// Handle resolve toggle.
		if ( isset( $_POST['visati_toggle_resolve'] ) && check_admin_referer( 'visati_resolve_inquiry' ) ) {
			$new_status = ( 'resolved' === $inquiry->status ) ? 'read' : 'resolved';
			$wpdb->update( $table, array( 'status' => $new_status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
			$inquiry->status = $new_status;
			echo '<div class="notice notice-success is-dismissible"><p>Inquiry marked as <strong>' . esc_html( ucfirst( $new_status ) ) . '</strong>.</p></div>';
		}

		include VISATI_CORE_DIR . 'admin/views/inquiry-detail.php';
	}
}
