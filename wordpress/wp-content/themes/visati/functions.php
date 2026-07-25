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

define( 'VISATI_THEME_VERSION', '0.4.32' );

/**
 * TEMPORARY diagnostic — checks what the real web-serving PHP process
 * actually returns for the fields that keep appearing stale. Remove after use.
 */
add_action( 'init', function () {
	if ( ! isset( $_GET['visati_debug_settings'] ) ) {
		return;
	}
	wp_die(
		'opcache_enabled=' . ( function_exists( 'opcache_get_status' ) && opcache_get_status( false ) ? 'yes' : 'no' ) .
		' | social_facebook=' . Visati_Core_Settings::get( 'social_facebook', 'DEFAULT-FB' ) .
		' | social_instagram=' . Visati_Core_Settings::get( 'social_instagram', 'DEFAULT-IG' ) .
		' | contact_trade_licence=' . Visati_Core_Settings::get( 'contact_trade_licence', 'DEFAULT-LICENCE' ) .
		' | footer_copyright=' . Visati_Core_Settings::get( 'footer_copyright', 'DEFAULT-EMPTY' )
	);
} );

/**
 * Theme setup: nav menus and core supports.
 *
 * @return void
 */
function visati_setup(): void {
	register_nav_menus(
		array(
			'primary'        => __( 'Primary Menu', 'visati' ),
			'footer-visas'   => __( 'Footer — Visas column', 'visati' ),
			'footer-company' => __( 'Footer — Company column', 'visati' ),
			'footer-support' => __( 'Footer — Support column', 'visati' ),
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
 * Footer nav menus reuse the .visati-footer__link style — add the class so they
 * don't fall into the global link-reset rule in app.css, which excludes it.
 *
 * @param array    $atts Link attributes.
 * @param WP_Post  $item Menu item.
 * @param stdClass $args wp_nav_menu() args, includes theme_location.
 * @return array
 */
function visati_footer_menu_link_class( array $atts, $item, $args ): array {
	if ( isset( $args->theme_location ) && 0 === strpos( (string) $args->theme_location, 'footer-' ) ) {
		$atts['class'] = isset( $atts['class'] ) ? $atts['class'] . ' visati-footer__link' : 'visati-footer__link';
	}
	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'visati_footer_menu_link_class', 10, 3 );

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
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Montserrat:wght@700;800&family=Poppins:wght@400;500&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap',
		array(),
		null
	);

	// Child theme stylesheet (style.css) + design-system CSS.
	// Use filemtime() so every edit auto-busts browser + CDN cache (30-day hcdn).
	wp_enqueue_style( 'visati-style', get_stylesheet_uri(), array( 'hello-elementor-style' ), filemtime( get_stylesheet_directory() . '/style.css' ) );
	$app_path = get_stylesheet_directory() . '/assets/css/app.css';
	wp_enqueue_style( 'visati-app', trailingslashit( get_stylesheet_directory_uri() ) . 'assets/css/app.css', array( 'visati-style' ), file_exists( $app_path ) ? filemtime( $app_path ) : VISATI_THEME_VERSION );
}

/**
 * Dequeue Hello Elementor's reset.css which applies pink #c36 to all buttons/links.
 * Our design system in app.css handles all button/link styling.
 *
 * @return void
 */
function visati_dequeue_hello_elementor_reset(): void {
	// Dequeue all Hello Elementor CSS files except the main style.css
	wp_dequeue_style( 'hello-elementor-css' );
	wp_dequeue_style( 'hello-elementor-theme-style-css' );
	wp_dequeue_style( 'hello-elementor-header-footer-css' );
}
add_action( 'wp_enqueue_scripts', 'visati_dequeue_hello_elementor_reset', 999 );

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

/**
 * Register plugin template directory so WP can find plugin-templates/*.php.
 *
 * @param array $templates Existing template paths.
 * @return array
 */
function visati_plugin_templates( $templates ) {
	$plugin_dir = WP_CONTENT_DIR . '/plugins/visati-core/templates';
	if ( is_dir( $plugin_dir ) ) {
		$files = glob( $plugin_dir . '/*.php' );
		foreach ( $files as $file ) {
			$name = 'plugin-templates/' . basename( $file );
			$templates[ $name ] = $file;
		}
	}
	return $templates;
}
add_filter( 'theme_page_templates', 'visati_plugin_templates' );

/**
 * Register policy template for Elementor.
 */
function visati_elementor_theme_location( $manager ) {
	$manager->register_all_coreLocations();
}
add_action( 'elementor/theme/register_locations', 'visati_elementor_theme_location' );

/**
 * Load plugin templates from the plugin directory.
 */
function visati_load_plugin_template( $template ) {
	if ( is_page() ) {
		$page_id = get_queried_object_id();
		$page_template = get_post_meta( $page_id, '_wp_page_template', true );
		if ( $page_template && 0 === strpos( $page_template, 'plugin-templates/' ) ) {
			$file = WP_CONTENT_DIR . '/plugins/visati-core/templates/' . basename( $page_template );
			if ( file_exists( $file ) ) {
				return $file;
			}
		}
	}
	return $template;
}
add_filter( 'template_include', 'visati_load_plugin_template', 99 );

/**
 * Force correct archive template for visa_type CPT.
 */
function visati_force_archive_template( $template ) {
	if ( is_post_type_archive( 'visati_visa_type' ) || is_singular( 'visati_visa_type' ) ) {
		$archive = get_stylesheet_directory() . '/archive-visa_type.php';
		$single  = get_stylesheet_directory() . '/single-visa_type.php';

		if ( is_post_type_archive( 'visati_visa_type' ) && file_exists( $archive ) ) {
			return $archive;
		}
		if ( is_singular( 'visati_visa_type' ) && file_exists( $single ) ) {
			return $single;
		}
	}

	// Force policy pages to page-policy.php regardless of DB meta.
	if ( is_page() ) {
		$slug = get_post_field( 'post_name', get_queried_object_id() );
		$policy_slugs = array( 'privacy', 'terms', 'cookies', 'refund-policy' );
		if ( in_array( $slug, $policy_slugs, true ) ) {
			$file = get_stylesheet_directory() . '/page-policy.php';
			if ( file_exists( $file ) ) {
				return $file;
			}
		}
	}

	return $template;
}
add_filter( 'template_include', 'visati_force_archive_template', 98 );

/**
 * Output JSON-LD structured data for SEO.
 * Organization schema on all pages; FAQPage schema when on the FAQ page.
 *
 * @return void
 */
function visati_json_ld_schemas(): void {
	$site_url  = home_url();
	$site_name = get_bloginfo( 'name' );
	$logo_url  = Visati_Core_Settings::get( 'logo_url', '' );
	if ( ! $logo_url ) {
		$logo_url = $site_url . '/wp-content/themes/visati/assets/images/logo-mark.svg';
	}
	$phone     = preg_replace( '/[^0-9+]/', '', Visati_Core_Settings::get( 'phone', '' ) );
	$email     = Visati_Core_Settings::get( 'email', '' );
	$whatsapp  = preg_replace( '/[^0-9+]/', '', Visati_Core_Settings::get( 'whatsapp_number', '' ) );

	// ─── Organization ──────────────────────────────────────────────
	$org = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Organization',
		'name'     => $site_name,
		'url'      => $site_url,
		'logo'     => $logo_url,
		'description' => __( 'Trusted UAE visa consultancy — 99% approval rate, fast processing, dedicated consultant support.', 'visati' ),
	);

	if ( $phone ) {
		$org['contactPoint'] = array(
			'@type'       => 'ContactPoint',
			'telephone'   => $phone,
			'contactType' => 'customer service',
			'areaServed'  => 'AE',
			'availableLanguage' => array( 'English', 'Arabic' ),
		);
	}

	// LocalBusiness is more specific — use it when we have address data.
	$address = Visati_Core_Settings::get( 'address', '' );
	if ( $address ) {
		$org['@type']       = 'LocalBusiness';
		$org['address']     = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $address,
			'addressCountry'  => 'AE',
		);
		$org['geo']         = array(
			'@type'       => 'GeoCoordinates',
			'latitude'    => 25.2048,
			'longitude'   => 55.2708,
		);
	}

	echo '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";

	// ─── FAQPage (only on pages with FAQ shortcode content) ───────
	if ( is_front_page() || is_page() ) {
		$page_id = get_queried_object_id();
		$content = $page_id ? get_post_field( 'post_content', $page_id ) : '';
		if ( $content && ( has_shortcode( $content, 'visati_faq' ) || has_shortcode( $content, 'visati_contact_page' ) ) ) {
			$faqs = get_posts( array(
				'post_type'      => 'visati_faq',
				'post_status'    => 'publish',
				'numberposts'    => 50,
				'no_found_rows'  => true,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			) );

			if ( $faqs ) {
				$faq_schema = array(
					'@context'   => 'https://schema.org',
					'@type'      => 'FAQPage',
					'mainEntity' => array(),
				);
				foreach ( $faqs as $faq ) {
					$question = get_post_meta( $faq->ID, '_visati_faq_question', true );
					$answer   = get_post_meta( $faq->ID, '_visati_faq_answer', true );
					if ( $question && $answer ) {
						$faq_schema['mainEntity'][] = array(
							'@type'          => 'Question',
							'name'           => $question,
							'acceptedAnswer' => array(
								'@type' => 'Answer',
								'text'  => wp_strip_all_tags( $answer ),
							),
						);
					}
				}
				if ( ! empty( $faq_schema['mainEntity'] ) ) {
					echo '<script type="application/ld+json">' . wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
				}
			}
		}
	}
}
add_action( 'wp_head', 'visati_json_ld_schemas', 1 );


