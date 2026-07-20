<?php
/**
 * Admin dashboard — menu registration + dashboard page.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Visati_Core_Admin_Dashboard {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
	}

	public static function register_menus(): void {
		// Main Visati menu.
		add_menu_page(
			'Visati',
			'Visati',
			'manage_options',
			'visati-dashboard',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-palmtree',
			30
		);

		// Submenu: Dashboard (replaces top-level).
		add_submenu_page(
			'visati-dashboard',
			'Dashboard',
			'Dashboard',
			'manage_options',
			'visati-dashboard',
			array( __CLASS__, 'render_dashboard' )
		);

		// Submenu: Applications.
		add_submenu_page(
			'visati-dashboard',
			'Applications',
			'Applications',
			'manage_options',
			'visati-applications',
			array( 'Visati_Core_Admin_Applications', 'render_list' )
		);

		// Submenu: Inquiries.
		add_submenu_page(
			'visati-dashboard',
			'Inquiries',
			'Inquiries',
			'manage_options',
			'visati-inquiries',
			array( 'Visati_Core_Admin_Inquiries', 'render_list' )
		);

		// Submenu: Settings.
		add_submenu_page(
			'visati-dashboard',
			'Settings',
			'Settings',
			'manage_options',
			'visati-settings',
			array( 'Visati_Core_Admin_Settings', 'render' )
		);

	}

	public static function enqueue_styles( string $hook ): void {
		if ( strpos( $hook, 'visati' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'visati-admin',
			VISATI_CORE_URL . 'assets/css/admin.css',
			array(),
			VISATI_CORE_VERSION
		);
	}

	public static function render_dashboard(): void {
		global $wpdb;

		$app_table = $wpdb->prefix . 'visati_applications';
		$inq_table = $wpdb->prefix . 'visati_inquiries';
		$pay_table = $wpdb->prefix . 'visati_payments';

		// Stat cards.
		$total_apps     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$app_table}" );
		$pending_apps   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$app_table} WHERE status = %s", 'draft' ) );
		$submitted_apps = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$app_table} WHERE status = %s", 'submitted' ) );
		$approved_apps  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$app_table} WHERE status = %s AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", 'approved' ) );
		$rejected_apps  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$app_table} WHERE status = %s AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", 'rejected' ) );
		$total_inquiries = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$inq_table}" );
		$new_inquiries   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$inq_table} WHERE status = %s", 'new' ) );

		// Revenue (successful payments only).
		$total_revenue    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount_aed),0) FROM {$pay_table} WHERE status = %s", 'succeeded' ) );
		$revenue_30d      = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount_aed),0) FROM {$pay_table} WHERE status = %s AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", 'succeeded' ) );
		$paid_count       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pay_table} WHERE status = %s", 'succeeded' ) );

		// Recent applications.
		$recent_apps = $wpdb->get_results(
			"SELECT a.*, vt.post_title AS visa_type_name
			 FROM {$app_table} a
			 LEFT JOIN {$wpdb->posts} vt ON a.visa_type_slug = vt.post_name AND vt.post_type = 'visati_visa_type'
			 ORDER BY a.created_at DESC LIMIT 10"
		);

		// Recent inquiries.
		$recent_inquiries = $wpdb->get_results(
			"SELECT * FROM {$inq_table} ORDER BY created_at DESC LIMIT 5"
		);

		include VISATI_CORE_DIR . 'admin/views/dashboard.php';
	}
}
