<?php
/**
 * Import the pre-built Elementor homepage template.
 *
 * Usage: wp eval-file import-homepage.php --allow-root
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/../../../../' );
}

// Load WP.
require_once ABSPATH . 'wp-load.php';

$json_path = __DIR__ . '/../wp-content/plugins/visati-core/admin/templates/homepage.json';

if ( ! file_exists( $json_path ) ) {
	echo "Error: homepage.json not found at {$json_path}\n";
	exit( 1 );
}

$json   = file_get_contents( $json_path );
$config = json_decode( $json, true );

if ( json_last_error() !== JSON_ERROR_NONE ) {
	echo "Error: Invalid JSON — " . json_last_error_msg() . "\n";
	exit( 1 );
}

/**
 * Extract shortcodes from HTML content and split into separate elements.
 *
 * Returns array of elements (HTML widgets + shortcode widgets).
 */
function extract_shortcodes_from_html( $html, $base_id ) {
	$pattern = '/\[([a-z_]+(?:\s+[^\]]*)?)\]/';
	preg_match_all( $pattern, $html, $matches, PREG_OFFSET_CAPTURE );

	if ( empty( $matches[0] ) ) {
		// No shortcodes found, return single HTML widget.
		return array(
			array(
				'id'                => $base_id . '_html',
				'elType'            => 'widget',
				'widgetType'        => 'html',
				'settings'          => array( 'html' => $html ),
				'elements'          => array(),
				'isInner'           => 0,
			),
		);
	}

	$elements = array();
	$last_pos = 0;

	foreach ( $matches[0] as $i => $match ) {
		$shortcode_full = $match[0];
		$offset         = $match[1];
		$shortcode_tag  = $matches[1][$i][0];

		// Extract shortcode name (first word).
		$parts   = preg_split( '/\s+/', $shortcode_tag, 2 );
		$tag     = $parts[0];
		$attrs   = $parts[1] ?? '';
		$shortcode = '[' . $tag . ( $attrs ? ' ' . $attrs : '' ) . ']';

		// Add HTML before shortcode (if any).
		$html_before = trim( substr( $html, $last_pos, $offset - $last_pos ) );
		if ( $html_before ) {
			$elements[] = array(
				'id'                => $base_id . '_html_' . $i,
				'elType'            => 'widget',
				'widgetType'        => 'html',
				'settings'          => array( 'html' => $html_before ),
				'elements'          => array(),
				'isInner'           => 0,
			);
		}

		// Add shortcode widget.
		$elements[] = array(
			'id'                => $base_id . '_sc_' . $tag . '_' . $i,
			'elType'            => 'widget',
			'widgetType'        => 'shortcode',
			'settings'          => array( 'shortcode' => $shortcode ),
			'elements'          => array(),
			'isInner'           => 0,
		);

		$last_pos = $offset + strlen( $shortcode_full );
	}

	// Add remaining HTML after last shortcode.
	$html_after = trim( substr( $html, $last_pos ) );
	if ( $html_after ) {
		$elements[] = array(
			'id'                => $base_id . '_html_end',
			'elType'            => 'widget',
			'widgetType'        => 'html',
			'settings'          => array( 'html' => $html_after ),
			'elements'          => array(),
			'isInner'           => 0,
		);
	}

	return $elements;
}

/**
 * Format Elementor data, extracting shortcodes from HTML widgets.
 */
function format_elementor_data( $elements ) {
	$formatted = array();

	foreach ( $elements as $element ) {
		// If this is an HTML widget containing shortcodes, split it.
		if (
			$element['elType'] === 'widget'
			&& ( $element['widgetType'] ?? '' ) === 'html'
			&& ! empty( $element['settings']['html'] )
			&& preg_match( '/\[([a-z_]+)/', $element['settings']['html'] )
		) {
			$split = extract_shortcodes_from_html( $element['settings']['html'], $element['id'] );
			$formatted = array_merge( $formatted, $split );
			continue;
		}

		$item = array(
			'id'                => $element['id'],
			'elType'            => $element['elType'],
			'settings'          => $element['settings'] ?? array(),
			'elements'          => isset( $element['elements'] ) ? format_elementor_data( $element['elements'] ) : array(),
			'isInner'           => 0,
			'widgetType'        => $element['widgetType'] ?? '',
		);

		$formatted[] = $item;
	}

	return $formatted;
}

// Check if a "Home" page already exists.
$existing = get_page_by_path( 'home' );

if ( $existing ) {
	$page_id = $existing->ID;
	echo "Updating existing Home page (ID: {$page_id})...\n";
} else {
	$page_id = wp_insert_post( array(
		'post_title'   => 'Home',
		'post_name'    => 'home',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => '',
	) );

	if ( is_wp_error( $page_id ) ) {
		echo "Error creating page: " . $page_id->get_error_message() . "\n";
		exit( 1 );
	}

	echo "Created Home page (ID: {$page_id})...\n";
}

// Build the Elementor data structure.
$elementor_data = format_elementor_data( $config['content'] );

// Save Elementor data.
update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $page_id, '_elementor_version', '4.1.5' );
update_post_meta( $page_id, '_wp_page_template', 'default' );

// Set as static front page.
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page_id );

echo "\n✅ Homepage imported successfully!\n";
echo "   Page ID: {$page_id}\n";
echo "   Set as: Static front page\n";
echo "   URL: " . get_permalink( $page_id ) . "\n\n";
echo "Next steps:\n";
echo "   1. Visit wp-admin → Settings → Reading to confirm static front page\n";
echo "   2. Edit the page with Elementor to customize\n";
echo "   3. Shortcodes extracted: [visati_quick_apply], [visati_visa_cards], [visati_testimonials], [visati_faq]\n";
