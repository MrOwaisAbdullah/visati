<?php
/**
 * Announcement bar — rendered at the top of <body> via wp_body_open.
 * Reads config from Visati_Core_Settings; dismissible via cookie.
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'Visati_Core_Settings' ) ) {
	return;
}

$active = (int) Visati_Core_Settings::get( 'announcement_active', 0 );
$text   = Visati_Core_Settings::get( 'announcement_text', '' );

if ( ! $active || '' === $text ) {
	return;
}

$end = Visati_Core_Settings::get( 'announcement_end_date', '' );
if ( $end && strtotime( $end ) < time() ) {
	return;
}

// Already dismissed this session/day.
if ( ! empty( $_COOKIE['visati_announcement_dismissed'] ) ) {
	return;
}

$cta_label = Visati_Core_Settings::get( 'announcement_cta_label', '' );
$cta_url   = Visati_Core_Settings::get( 'announcement_cta_url', '' );
$bg        = Visati_Core_Settings::get( 'announcement_bg_color', '#C9A84C' );
$fg        = Visati_Core_Settings::get( 'announcement_text_color', '#0A1628' );
?>
<div id="visati-announcement" style="background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $fg ); ?>;">
	<div class="visati-container" style="display:flex;align-items:center;justify-content:center;gap:1rem;padding:0.6rem 1.25rem;position:relative;">
		<span style="font-size:0.9rem;font-weight:600;"><?php echo esc_html( $text ); ?></span>
		<?php if ( $cta_label && $cta_url ) : ?>
			<a href="<?php echo esc_url( $cta_url ); ?>" style="font-size:0.9rem;font-weight:700;text-decoration:underline;"><?php echo esc_html( $cta_label ); ?></a>
		<?php endif; ?>
		<button id="visati-announcement-close" type="button" aria-label="<?php esc_attr_e( 'Dismiss announcement', 'visati' ); ?>" style="position:absolute;right:0.5rem;background:transparent;border:0;color:inherit;font-size:1.25rem;cursor:pointer;line-height:1;">&times;</button>
	</div>
</div>
<script>
	document.getElementById( 'visati-announcement-close' )?.addEventListener( 'click', function () {
		var bar = document.getElementById( 'visati-announcement' );
		if ( bar ) { bar.style.display = 'none'; }
		document.cookie = 'visati_announcement_dismissed=1;path=/;max-age=86400;SameSite=Lax';
	} );
</script>
