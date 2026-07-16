<?php
require_once dirname( __FILE__ ) . '/../../wp-load.php';

$kit_id = get_option( 'elementor_active_kit' );
if ( ! $kit_id ) {
	echo "No active kit found\n";
	exit;
}

$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
if ( ! $settings ) {
	echo "No kit settings found\n";
	exit;
}

echo "=== Global Colors ===\n";
if ( ! empty( $settings['system_colors'] ) ) {
	foreach ( $settings['system_colors'] as $color ) {
		echo "  " . ( $color['title'] ?? 'unnamed' ) . ": " . ( $color['color'] ?? 'none' ) . "\n";
	}
}
if ( ! empty( $settings['custom_colors'] ) ) {
	echo "Custom Colors:\n";
	foreach ( $settings['custom_colors'] as $color ) {
		echo "  " . ( $color['title'] ?? 'unnamed' ) . ": " . ( $color['color'] ?? 'none' ) . "\n";
	}
}

echo "\n=== Button Defaults ===\n";
if ( ! empty( $settings['button_color'] ) ) {
	echo "Button color: " . $settings['button_color'] . "\n";
}
if ( ! empty( $settings['button_text_color'] ) ) {
	echo "Button text: " . $settings['button_text_color'] . "\n";
}

echo "\n=== Color-related Settings Keys ===\n";
$color_keys = array_filter(
	array_keys( $settings ),
	function( $k ) {
		return stripos( $k, 'color' ) !== false || stripos( $k, 'button' ) !== false || stripos( $k, 'accent' ) !== false || stripos( $k, 'link' ) !== false;
	}
);
foreach ( $color_keys as $k ) {
	$val = $settings[ $k ];
	if ( is_array( $val ) ) {
		echo "  $k: " . wp_json_encode( $val ) . "\n";
	} else {
		echo "  $k: $val\n";
	}
}
