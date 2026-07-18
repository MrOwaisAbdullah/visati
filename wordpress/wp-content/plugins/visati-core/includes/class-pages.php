<?php
/**
 * Creates all required WordPress pages on plugin activation.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Pages {

	/**
	 * Page definitions: slug => template file (in plugin templates/ dir).
	 * Templates starting with "front-page" live in the theme.
	 */
	private static $pages = array(
		'home'          => '',
		'visa-types'    => '',
		'apply'         => 'page-apply.php',
		'track'         => 'page-track.php',
		'contact'       => '',
		'faq'           => '',
		'about'         => '',
		'services'      => '',
		'careers'       => '',
		'blog'          => '',
		'privacy'       => 'page-policy.php',
		'terms'         => 'page-policy.php',
		'cookies'       => 'page-policy.php',
		'refund-policy' => 'page-policy.php',
	);

	/**
	 * Create missing pages. Idempotent — skips pages that already exist.
	 *
	 * @return void
	 */
	public static function create_pages(): void {
		foreach ( self::$pages as $slug => $template ) {
			$existing = get_page_by_path( $slug );
			if ( $existing ) {
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_title'   => ucwords( str_replace( '-', ' ', $slug ) ),
					'post_name'    => $slug,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => self::default_content( $slug ),
				)
			);

			if ( is_wp_error( $page_id ) || 0 === $page_id ) {
				continue;
			}

			// Assign page template.
			if ( '' !== $template ) {
				update_post_meta( $page_id, '_wp_page_template', 'plugin-templates/' . $template );
			}

			// Set front page / posts page.
			if ( 'home' === $slug ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $page_id );
			}
			if ( 'blog' === $slug ) {
				update_option( 'page_for_posts', $page_id );
			}
		}

		// Flush rewrite rules after page creation.
		flush_rewrite_rules();
	}

	/**
	 * Default content for each page (shortcode or placeholder text).
	 */
	private static function default_content( string $slug ): string {
		$map = array(
			'visa-types' => '<!-- Elementor page — build via EMCP or paste shortcode -->[visati_visa_cards count="12"]',
			'apply'      => '',
			'track'      => '',
			'contact'    => '<!-- Elementor page — build via EMCP or paste shortcode -->[visati_contact_form]',
			'faq'        => '<!-- Elementor page — build via EMCP or paste shortcode -->[visati_faq]',
			'about'      => '<!-- Elementor page — build via EMCP -->',
			'services'   => '<!-- Elementor page — build via EMCP -->',
			'careers'    => '<!-- Elementor page — build via EMCP -->',
			'blog'       => '',
			'privacy'    => '<p>Privacy policy content goes here.</p>',
			'terms'      => '<p>Terms of service content goes here.</p>',
			'cookies'    => '<p>Cookie policy content goes here.</p>',
		);
		return isset( $map[ $slug ] ) ? $map[ $slug ] : '';
	}
}
