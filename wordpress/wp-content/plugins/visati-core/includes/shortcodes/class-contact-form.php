<?php
/**
 * [visati_contact_form] — validates, inserts inquiry, emails admin.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Contact_Form_Shortcode {

	public static function init(): void {
		add_shortcode( 'visati_contact_form', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets(): void {
		wp_register_script( 'visati-contact', VISATI_CORE_URL . 'assets/js/contact-form.js', array(), VISATI_CORE_VERSION, true );
	}

	public static function render(): string {
		$rest_url = esc_url_raw( rest_url( 'visati/v1/inquiries' ) );
		$nonce    = wp_create_nonce( 'wp_rest' );

		ob_start();
		?>
		<form class="visati-contact-form" id="visati-contact-form" novalidate>
			<div class="visati-contact-form__status" id="contact-status" role="alert" hidden></div>

			<div class="visati-field">
				<label for="cf-name">Name <span aria-hidden="true">*</span></label>
				<input type="text" id="cf-name" name="name" required autocomplete="name">
				<div class="visati-field-error">Please enter your name.</div>
			</div>

			<div class="visati-field">
				<label for="cf-email">Email <span aria-hidden="true">*</span></label>
				<input type="email" id="cf-email" name="email" required autocomplete="email">
				<div class="visati-field-error">Please enter a valid email address.</div>
			</div>

			<div class="visati-field">
				<label for="cf-phone">Phone</label>
				<input type="tel" id="cf-phone" name="phone" autocomplete="tel">
			</div>

			<div class="visati-field">
				<label for="cf-subject">Subject <span aria-hidden="true">*</span></label>
				<input type="text" id="cf-subject" name="subject" required>
				<div class="visati-field-error">Please enter a subject.</div>
			</div>

			<div class="visati-field">
				<label for="cf-message">Message <span aria-hidden="true">*</span></label>
				<textarea id="cf-message" name="message" rows="5" required></textarea>
				<div class="visati-field-error">Please enter your message.</div>
			</div>

			<button type="submit" class="visati-btn">
				Send Message
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
			</button>
		</form>
		<?php
		if ( ! wp_script_is( 'visati-contact', 'enqueued' ) ) {
			wp_enqueue_script( 'visati-contact' );
			wp_localize_script(
				'visati-contact',
				'visatiContact',
				array(
					'restUrl' => $rest_url,
					'nonce'   => $nonce,
				)
			);
		}
		return (string) ob_get_clean();
	}
}
