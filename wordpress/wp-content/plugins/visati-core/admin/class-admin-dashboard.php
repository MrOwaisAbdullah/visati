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
		add_action( 'admin_menu', array( __CLASS__, 'move_dashboard_submenu_first' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_dashboard_widget' ) );
	}

	/**
	 * Force "Dashboard" to be the first submenu item under "Visati".
	 *
	 * WordPress registers CPT-based submenus (from `show_in_menu`) before plugin
	 * `add_submenu_page()` calls run, so without this, Visa Types/FAQ/Testimonials/
	 * Job Openings/Discount Codes would appear above Dashboard. Runs at a very late
	 * `admin_menu` priority so every submenu has already been added by the time it fires.
	 *
	 * @return void
	 */
	public static function move_dashboard_submenu_first(): void {
		global $submenu;
		if ( empty( $submenu['visati-dashboard'] ) ) {
			return;
		}

		$items           = $submenu['visati-dashboard'];
		$dashboard_index = null;
		foreach ( $items as $i => $item ) {
			if ( isset( $item[2] ) && 'visati-dashboard' === $item[2] ) {
				$dashboard_index = $i;
				break;
			}
		}
		if ( null === $dashboard_index ) {
			return;
		}

		$dashboard_item = $items[ $dashboard_index ];
		unset( $items[ $dashboard_index ] );
		array_unshift( $items, $dashboard_item );
		$submenu['visati-dashboard'] = array_values( $items );
	}

	/**
	 * Register the "Visati — Quick View" widget on the native WordPress
	 * Dashboard (wp-admin/index.php), so key numbers are visible without
	 * navigating into the Visati menu.
	 *
	 * @return void
	 */
	public static function register_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'visati_quick_view',
			__( 'Visati — Quick View', 'visati-core' ),
			array( __CLASS__, 'render_quick_view_widget' )
		);
		wp_add_dashboard_widget(
			'visati_quick_actions',
			__( 'Visati — Quick Actions', 'visati-core' ),
			array( __CLASS__, 'render_quick_actions_widget' )
		);
	}

	/**
	 * Render the WP Dashboard "Quick Actions" widget — shortcuts to the most
	 * common Visati admin destinations, mirroring the row on the full Visati
	 * Dashboard page but reachable from wp-admin's Home screen directly.
	 *
	 * @return void
	 */
	public static function render_quick_actions_widget(): void {
		include VISATI_CORE_DIR . 'admin/views/dashboard-quick-actions.php';
	}

	/**
	 * Render the WP Dashboard "Quick View" widget content.
	 *
	 * @return void
	 */
	public static function render_quick_view_widget(): void {
		global $wpdb;

		$app_table = $wpdb->prefix . 'visati_applications';
		$inq_table = $wpdb->prefix . 'visati_inquiries';
		$pay_table = $wpdb->prefix . 'visati_payments';

		$submitted_apps = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$app_table} WHERE status = %s", 'submitted' ) );
		$under_review   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$app_table} WHERE status = %s", 'under_review' ) );
		$new_inquiries  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$inq_table} WHERE status = %s", 'new' ) );
		$revenue_30d    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount_aed),0) FROM {$pay_table} WHERE status = %s AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", 'succeeded' ) );

		$recent_apps = $wpdb->get_results(
			"SELECT id, given_name, surname, status, processing_tier, created_at FROM {$app_table}
			 ORDER BY ( processing_tier = 'express' ) DESC, created_at DESC LIMIT 5"
		);

		include VISATI_CORE_DIR . 'admin/views/dashboard-widget.php';
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
			2
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

		// Submenu: Help / Reference (shortcodes, Settings fields, content types, REST API).
		add_submenu_page(
			'visati-dashboard',
			'Visati — Reference',
			'Help',
			'manage_options',
			'visati-help',
			array( 'Visati_Core_Admin_Help', 'render' )
		);

	}

	public static function enqueue_styles( string $hook ): void {
		$our_post_types = array( 'visati_visa_type', 'visati_faq', 'visati_testimonial', 'visati_job', 'visati_discount' );
		$is_our_edit_screen = in_array( $hook, array( 'post.php', 'post-new.php' ), true )
			&& in_array( get_current_screen()->post_type ?? '', $our_post_types, true );

		// Load on all Visati admin pages, the native WP Dashboard (Quick View widget),
		// and post edit screens for our own CPTs (meta boxes otherwise render fully unstyled).
		if ( strpos( $hook, 'visati' ) === false && 'index.php' !== $hook && ! $is_our_edit_screen ) {
			return;
		}
		wp_enqueue_style(
			'visati-admin',
			VISATI_CORE_URL . 'assets/css/admin.css',
			array(),
			visati_asset_version( 'assets/css/admin.css' )
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

		// Revenue (successful payments only). Checkout switched from AED to USD —
		// summed separately per currency so historical AED payments are never
		// silently added into the same total as USD ones (they're not the same
		// unit). `amount_aed` keeps its historical column name for both rows,
		// see class-database.php.
		$total_revenue    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount_aed),0) FROM {$pay_table} WHERE status = %s AND currency = 'usd'", 'succeeded' ) );
		$revenue_30d      = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount_aed),0) FROM {$pay_table} WHERE status = %s AND currency = 'usd' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", 'succeeded' ) );
		$total_revenue_aed_legacy = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount_aed),0) FROM {$pay_table} WHERE status = %s AND ( currency = 'aed' OR currency IS NULL )", 'succeeded' ) );
		$paid_count       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pay_table} WHERE status = %s", 'succeeded' ) );

		// Recent applications — Express jumps to the top since it needs faster turnaround.
		$recent_apps = $wpdb->get_results(
			"SELECT a.*, vt.post_title AS visa_type_name
			 FROM {$app_table} a
			 LEFT JOIN {$wpdb->posts} vt ON a.visa_type_slug = vt.post_name AND vt.post_type = 'visati_visa_type'
			 ORDER BY ( a.processing_tier = 'express' ) DESC, a.created_at DESC LIMIT 10"
		);

		// Recent inquiries.
		$recent_inquiries = $wpdb->get_results(
			"SELECT * FROM {$inq_table} ORDER BY created_at DESC LIMIT 5"
		);

		// R2 storage usage.
		$storage = self::storage_usage();
		$storage_used_bytes = $storage['used_bytes'];
		$storage_quota_gb   = $storage['quota_gb'];
		$storage_pct        = $storage['pct'];

		include VISATI_CORE_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * R2 storage usage — summed from the documents table rather than an R2
	 * ListObjects call, since every byte in the bucket was put there by this
	 * plugin's own upload paths (applicant documents + admin-issued visa files).
	 *
	 * @return array{used_bytes:int,quota_gb:int,pct:float}
	 */
	public static function storage_usage(): array {
		global $wpdb;
		$docs_table          = $wpdb->prefix . 'visati_documents';
		$used_bytes          = (int) $wpdb->get_var( "SELECT COALESCE(SUM(size_bytes),0) FROM {$docs_table}" );
		$quota_gb            = max( 1, (int) Visati_Core_Settings::get( 'r2_storage_quota_gb', 10 ) );
		$quota_bytes         = $quota_gb * 1073741824;
		$pct                 = round( min( 100, ( $used_bytes / $quota_bytes ) * 100 ), 1 );

		return array(
			'used_bytes' => $used_bytes,
			'quota_gb'   => $quota_gb,
			'pct'        => $pct,
		);
	}

	/**
	 * Site-wide admin notice when R2 storage crosses 80% of its configured quota
	 * (e.g. 8-9 GB of a 10 GB quota) — shown on every wp-admin screen, not just
	 * the Visati Dashboard, so it isn't missed.
	 *
	 * @return void
	 */
	public static function storage_alert_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$storage = self::storage_usage();
		if ( $storage['pct'] < 80 ) {
			return;
		}

		$level = $storage['pct'] >= 90 ? 'notice-error' : 'notice-warning';
		printf(
			'<div class="notice %1$s is-dismissible"><p><strong>%2$s:</strong> %3$s (%4$s%% of %5$d GB quota). <a href="%6$s">%7$s</a></p></div>',
			esc_attr( $level ),
			esc_html__( 'Visati — R2 storage almost full', 'visati-core' ),
			esc_html( size_format( $storage['used_bytes'] ) . ' used' ),
			esc_html( (string) $storage['pct'] ),
			(int) $storage['quota_gb'],
			esc_url( admin_url( 'admin.php?page=visati-dashboard' ) ),
			esc_html__( 'View Dashboard', 'visati-core' )
		);
	}
}
