<?php
/**
 * REST API bootstrap (namespace visati/v1) + shared response helpers.
 *
 * Real routes (applications, documents, track, webhooks/stripe) are registered by
 * their own controllers in later phases. This provides the namespace, a status ping,
 * and helpers used across all controllers.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_REST_API {

	const NAMESPACE = 'visati/v1';

	/**
	 * Hook route registration.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'status' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Public status ping (plugin + version only — no secrets, no config flags).
	 *
	 * @return array<string,string>
	 */
	public static function status(): array {
		return array(
			'plugin'  => 'visati-core',
			'version' => VISATI_CORE_VERSION,
		);
	}

	/* ── Helpers for controllers ─────────────────────────────────────── */

	/**
	 * Build a WP_Error with HTTP status.
	 *
	 * @param string $code     Error code.
	 * @param string $message  Human message.
	 * @param int    $status   HTTP status.
	 * @return WP_Error
	 */
	public static function error( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Public permission callback that still requires a valid REST nonce for
	 * state-changing requests. (Use only where a nonce is sent.)
	 *
	 * @return bool
	 */
	public static function permission_public(): bool {
		return true;
	}

	/**
	 * Admin-only permission callback.
	 *
	 * @return bool
	 */
	public static function permission_admin(): bool {
		return current_user_can( 'visati_manage_apps' );
	}
}
