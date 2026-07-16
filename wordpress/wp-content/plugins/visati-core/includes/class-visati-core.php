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
		add_action( 'admin_notices', array( 'Visati_Core_Settings', 'admin_config_notice' ) );

		Visati_Core_REST_API::init();
		Visati_Core_Applications_Controller::init();
		Visati_Core_Documents_Controller::init();
		Visati_Core_Admin_Settings::init();

		// Future modules (added in later phases):
		// - Visati_Core_R2_Storage, Visati_Core_Stripe_Payments, shortcodes, admin dashboard.
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
