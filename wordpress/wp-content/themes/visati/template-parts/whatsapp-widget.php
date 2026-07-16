<?php
/**
 * Floating WhatsApp widget — rendered before </body> via wp_footer.
 * Reads number + default message from Visati_Core_Settings.
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'Visati_Core_Settings' ) ) {
	return;
}

$number = Visati_Core_Settings::get( 'whatsapp_number', '' );
if ( '' === $number ) {
	return;
}

// digits only.
$number = preg_replace( '/[^0-9]/', '', $number );
if ( '' === $number ) {
	return;
}

$message = Visati_Core_Settings::get( 'whatsapp_text', 'Hello Visati, I would like to apply for a UAE visa.' );
$url     = 'https://wa.me/' . $number . '?text=' . rawurlencode( $message );
?>
<a class="visati-whatsapp-fab" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Chat on WhatsApp', 'visati' ); ?>">
	<svg viewBox="0 0 32 32" width="28" height="28" fill="currentColor" aria-hidden="true" focusable="false">
		<path d="M16 3C9.4 3 4 8.4 4 15c0 2.1.6 4.1 1.6 5.9L4 29l8.3-1.6c1.7.9 3.7 1.4 5.7 1.4 6.6 0 12-5.4 12-12S22.6 3 16 3zm0 21.8c-1.8 0-3.5-.5-5-1.4l-.4-.2-4.9 1 1-4.8-.2-.4c-1-1.6-1.5-3.4-1.5-5.3 0-5.4 4.4-9.8 9.8-9.8s9.8 4.4 9.8 9.8-4.4 9.8-9.8 9.8zm5.4-7.3c-.3-.1-1.7-.9-2-1-.3-.1-.5-.1-.7.1-.2.3-.7 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5 0-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5 4.5.7.3 1.2.5 1.7.6.7.2 1.3.2 1.8.1.6-.1 1.7-.7 1.9-1.3.2-.7.2-1.2.2-1.3-.1-.2-.3-.2-.6-.4z"/>
	</svg>
</a>
