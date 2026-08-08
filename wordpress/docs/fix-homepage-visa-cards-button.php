<?php
/**
 * One-time content fix: adds show_more="yes" to the homepage's
 * [visati_visa_cards] shortcode widget, safely (json_decode/modify/encode +
 * wp_slash on write — never raw string-replace on _elementor_data, see
 * migration-log.md 2026-08-08 "Fixed: broke the homepage..." entry).
 *
 * Usage on the live server: wp eval-file fix-homepage-visa-cards-button.php
 * Delete this file after running.
 */

$front_id = (int) get_option( 'page_on_front' );
if ( ! $front_id ) {
	WP_CLI::error( 'No static front page set (page_on_front is empty).' );
}

$data = get_post_meta( $front_id, '_elementor_data', true );
if ( ! $data ) {
	WP_CLI::error( "No _elementor_data on post $front_id." );
}

$tree = json_decode( $data, true );
if ( null === $tree ) {
	WP_CLI::error( 'Existing _elementor_data is not valid JSON — aborting, nothing changed.' );
}

$modified = 0;

function visati_walk_and_update( &$elements, &$modified ) {
	foreach ( $elements as &$el ) {
		if ( isset( $el['widgetType'] ) && 'shortcode' === $el['widgetType']
			&& isset( $el['settings']['shortcode'] )
			&& false !== strpos( $el['settings']['shortcode'], 'visati_visa_cards' )
			&& false === strpos( $el['settings']['shortcode'], 'show_more' ) ) {
			$el['settings']['shortcode'] = preg_replace( '/\]$/', ' show_more="yes"]', $el['settings']['shortcode'] );
			$modified++;
		}
		if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
			visati_walk_and_update( $el['elements'], $modified );
		}
	}
}
visati_walk_and_update( $tree, $modified );

if ( 0 === $modified ) {
	WP_CLI::warning( 'No matching [visati_visa_cards] widget found (or already has show_more) — nothing changed.' );
	exit;
}

$encoded = wp_json_encode( $tree );
if ( null === json_decode( $encoded ) ) {
	WP_CLI::error( 'Re-encoded JSON failed validation — aborting, nothing written.' );
}

update_post_meta( $front_id, '_elementor_data', wp_slash( $encoded ) );

// Verify the write.
$final = get_post_meta( $front_id, '_elementor_data', true );
if ( null === json_decode( $final ) ) {
	WP_CLI::error( 'FINAL CHECK FAILED — data is invalid after write. Restore from wp_get_post_revisions( ' . $front_id . ' ) immediately.' );
}

delete_post_meta( $front_id, '_elementor_css' );
if ( class_exists( '\\Elementor\\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}

WP_CLI::success( "Updated post $front_id: $modified widget(s) modified, JSON valid, Elementor CSS cache cleared." );
