<?php
/**
 * Uninstall handler — runs when the plugin is deleted via WordPress admin.
 * Removes plugin options and (optionally) custom tables. Does NOT remove
 * CPT posts by default — that would destroy client content. Tables are dropped
 * only if VISATI_CORE_PURGE is defined true in wp-config.php.
 *
 * @package Visati_Core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

// Remove plugin options.
delete_option( 'visati_core_db_version' );

// Drop custom tables only on explicit purge request.
if ( defined( 'VISATI_CORE_PURGE' ) && VISATI_CORE_PURGE ) {
	$tables = array(
		"{$prefix}visati_applications",
		"{$prefix}visati_status_history",
		"{$prefix}visati_documents",
		"{$prefix}visati_payments",
		"{$prefix}visati_inquiries",
	);
	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL, no user input.
	}

	// Remove the custom role.
	remove_role( 'visati_consultant' );
}
