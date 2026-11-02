<?php
/**
 * Custom roles and capabilities.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Visati_Core_Roles {

	/**
	 * Add the visati_consultant role (staff who process applications).
	 *
	 * @return void
	 */
	public static function add_consultant_role(): void {
		add_role(
			'visati_consultant',
			__( 'Visati Consultant', 'visati-core' ),
			array(
				'read'                   => true,
				'edit_posts'             => true,
				'delete_posts'           => false,
				'publish_posts'          => false,
				'upload_files'           => true,
				// Visati capabilities (mapped to the admin application/inquiry screens later).
				'visati_manage_apps'     => true,
				'visati_manage_inquiries'=> true,
				'visati_view_documents'  => true,
			)
		);
	}

	/**
	 * Add the visati_bot role: read-only, may only download applicant documents via the REST API.
	 * Used with a WordPress Application Password. No wp-admin access.
	 *
	 * @return void
	 */
	public static function add_bot_role(): void {
		add_role(
			'visati_bot',
			__( 'Visati Bot', 'visati-core' ),
			array(
				'read'                      => true,
				'visati_download_documents' => true,
			)
		);
	}

	/**
	 * Ensure the bot role exists and administrators hold the download capability.
	 * Runs on every load because replacing the plugin files does not re-run the activation hook.
	 *
	 * @return void
	 */
	public static function ensure_bot_role(): void {
		if ( ! get_role( 'visati_bot' ) ) {
			self::add_bot_role();
		}
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( 'visati_download_documents' ) ) {
			$admin->add_cap( 'visati_download_documents' );
		}
	}
}
