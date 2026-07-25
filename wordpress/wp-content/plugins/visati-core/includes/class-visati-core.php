<?php
/**
 * Main plugin class (singleton). Loads and wires plugin modules.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core {

	/**
	 * Singleton instance.
	 *
	 * @var Visati_Core|null
	 */
	private static $instance = null;

	/**
	 * Boot the plugin.
	 *
	 * @return Visati_Core
	 */
	public static function instance(): Visati_Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire hooks. Feature modules are registered here as they are built.
	 */
	private function __construct() {
		add_action( 'init', array( 'Visati_Core_Post_Types', 'register' ) );
		add_action( 'init', array( 'Visati_Core_Database', 'ensure_columns' ) );
		add_action( 'admin_notices', array( 'Visati_Core_Settings', 'admin_config_notice' ) );

		Visati_Core_REST_API::init();
		Visati_Core_Applications_Controller::init();
		Visati_Core_Documents_Controller::init();
		Visati_Core_Inquiries_Controller::init();
		Visati_Core_Track_Controller::init();
		Visati_Core_Webhooks_Controller::init();
		Visati_Core_Discount_Controller::init();
		Visati_Core_Tracking::init();

		// Shortcodes.
		Visati_Core_Apply_Form_Shortcode::init();
		Visati_Core_Visa_Cards_Shortcode::init();
		Visati_Core_Quick_Apply_Shortcode::init();
		Visati_Core_Testimonials_Shortcode::init();
		Visati_Core_FAQ_Shortcode::init();
		Visati_Core_Contact_Form_Shortcode::init();
		Visati_Core_About_Shortcodes::init();
		Visati_Core_Jobs_Shortcode::init();
		Visati_Core_Blog_Shortcode::init();
		Visati_Core_Global_Reach_Shortcode::init();
		Visati_Core_Services_Shortcode::init();

		// Admin.
		Visati_Core_Admin_Settings::init();
		Visati_Core_Admin_Dashboard::init();
		Visati_Core_Meta_Boxes::init();
		Visati_Core_Setup_Pages::init();
	}

	/**
	 * Activation: create tables, add role, register CPTs, flush rewrites.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Visati_Core_Database::create_tables();
		Visati_Core_Roles::add_consultant_role();
		Visati_Core_Post_Types::register();
		Visati_Core_Pages::create_pages();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation: flush rewrites. Data is preserved.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
