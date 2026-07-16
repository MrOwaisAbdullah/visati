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
}
