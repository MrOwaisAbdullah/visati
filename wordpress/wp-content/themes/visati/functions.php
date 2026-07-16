<?php
/**
 * Visati child theme functions.
 *
 * Classic child theme of Hello Elementor. Handles global setup, menu registration,
 * Google Fonts, and design-system CSS. Section content + the application engine live
 * in the `visati-core` plugin.
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VISATI_THEME_VERSION', '0.2.0' );

/**
 * Theme setup: nav menus and core supports.
 *
 * @return void
 */
function visati_setup(): void {
	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'visati' ),
			'footer'  => __( 'Footer Menu', 'visati' ),
		)
	);

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support(
		'html5',
		array( 'search-form', 'gallery', 'caption', 'style', 'script' )
	);
}
add_action( 'after_setup_theme', 'visati_setup' );

/**
 * Enqueue parent theme, Google Fonts, and Visati design-system styles.
 *
 * Fonts and token CSS are intentionally global (used on every page).
 *
 * @return void
 */
function visati_enqueue_assets(): void {
	// Parent (Hello Elementor) stylesheet.
	wp_enqueue_style( 'hello-elementor-style', trailingslashit( get_template_directory_uri() ) . 'style.css', array(), null );

	// Google Fonts: Plus Jakarta Sans (headings), Inter (body), JetBrains Mono (IDs/codes).
	wp_enqueue_style(
		'visati-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap',
		array(),
		null
	);

	// Child theme stylesheet (style.css) + design-system CSS.
	wp_enqueue_style( 'visati-style', get_stylesheet_uri(), array( 'hello-elementor-style' ), VISATI_THEME_VERSION );
	wp_enqueue_style( 'visati-app', trailingslashit( get_stylesheet_directory_uri() ) . 'assets/css/app.css', array( 'visati-style' ), VISATI_THEME_VERSION );
}
add_action( 'wp_enqueue_scripts', 'visati_enqueue_assets' );

/**
 * Render the announcement bar at the top of <body>.
 *
 * @return void
 */
function visati_render_announcement_bar(): void {
	if ( class_exists( 'Visati_Core_Settings' ) ) {
		get_template_part( 'template-parts/announcement-bar' );
	}
}
add_action( 'wp_body_open', 'visati_render_announcement_bar' );

/**
 * Render the floating WhatsApp widget before </body>.
 *
 * @return void
 */
function visati_render_whatsapp_widget(): void {
	if ( class_exists( 'Visati_Core_Settings' ) ) {
		get_template_part( 'template-parts/whatsapp-widget' );
	}
}
add_action( 'wp_footer', 'visati_render_whatsapp_widget' );
