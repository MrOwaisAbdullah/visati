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
		add_shortcode( 'visati_contact_page', array( __CLASS__, 'render_page' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets(): void {
		wp_register_script( 'visati-contact', VISATI_CORE_URL . 'assets/js/contact-form.js', array(), visati_asset_version( 'assets/js/contact-form.js' ), true );
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
				<input type="text" id="cf-name" name="name" required autocomplete="name" aria-describedby="cf-name-error" aria-invalid="false">
				<div class="visati-field-error" id="cf-name-error">Please enter your name.</div>
			</div>

			<div class="visati-field">
				<label for="cf-email">Email <span aria-hidden="true">*</span></label>
				<input type="email" id="cf-email" name="email" required autocomplete="email" aria-describedby="cf-email-error" aria-invalid="false">
				<div class="visati-field-error" id="cf-email-error">Please enter a valid email address.</div>
			</div>

			<div class="visati-field">
				<label for="cf-phone">Phone</label>
				<input type="tel" id="cf-phone" name="phone" autocomplete="tel">
			</div>

			<div class="visati-field">
				<label for="cf-subject">Subject <span aria-hidden="true">*</span></label>
				<input type="text" id="cf-subject" name="subject" required aria-describedby="cf-subject-error" aria-invalid="false">
				<div class="visati-field-error" id="cf-subject-error">Please enter a subject.</div>
			</div>

			<div class="visati-field">
				<label for="cf-message">Message <span aria-hidden="true">*</span></label>
				<textarea id="cf-message" name="message" rows="5" required aria-describedby="cf-message-error" aria-invalid="false"></textarea>
				<div class="visati-field-error" id="cf-message-error">Please enter your message.</div>
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

	/**
	 * Full contact page — two-column layout with details + form.
	 */
	public static function render_page(): string {
		$s               = class_exists( 'Visati_Core_Settings' ) ? array( 'Visati_Core_Settings', 'get' ) : null;
		$email           = ( $s ? $s( 'contact_email', 'visa@visati.ae' ) : '' ) ?: 'visa@visati.ae';
		$phone           = ( $s ? $s( 'contact_phone', '+971 58 554 2344' ) : '' ) ?: '+971 58 554 2344';
		$address         = ( $s ? $s( 'contact_address', "Boulevard Plaza Tower 1\nDowntown Dubai, UAE" ) : '' ) ?: "Boulevard Plaza Tower 1\nDowntown Dubai, UAE";
		$whatsapp        = ( $s ? $s( 'whatsapp_number', '971585542344' ) : '' ) ?: '971585542344';
		$wa_url          = 'https://wa.me/' . rawurlencode( $whatsapp ) . '?text=' . rawurlencode( 'Hello Visati, I have a question about UAE visas.' );
		$whatsapp_note   = ( $s ? $s( 'contact_whatsapp_note', 'Average reply 2 min' ) : '' ) ?: 'Average reply 2 min';
		$email_note      = ( $s ? $s( 'contact_email_note', 'Reply within 1 hour' ) : '' ) ?: 'Reply within 1 hour';
		$phone_note      = ( $s ? $s( 'contact_phone_note', 'Sun–Thu, 9am–6pm GST' ) : '' ) ?: 'Sun–Thu, 9am–6pm GST';
		$office_note     = ( $s ? $s( 'contact_office_note', 'By appointment' ) : '' ) ?: 'By appointment';
		$hours           = ( $s ? $s( 'contact_hours', "Sunday — Thursday\n9:00 AM — 9:00 PM GST" ) : '' ) ?: "Sunday — Thursday\n9:00 AM — 9:00 PM GST";
		$hours_note      = ( $s ? $s( 'contact_hours_note', 'WhatsApp 24/7' ) : '' ) ?: 'WhatsApp 24/7';
		// Default: derive a working embed straight from the office address, so the
		// map still renders even before anyone visits Settings and saves anything.
		$maps_default_url = 'https://maps.google.com/maps?q=' . rawurlencode( str_replace( "\n", ', ', $address ) ) . '&output=embed';
		$maps_embed_url   = $s ? $s( 'google_maps_embed_url', $maps_default_url ) : $maps_default_url;

		ob_start();
		?>
		<div class="visati-contact-page">
			<div class="visati-contact-page__left">
				<p class="visati-section-label">Contact</p>
				<h1 class="visati-contact-page__title">Get in touch.</h1>
				<p class="visati-contact-page__desc">Whether you're applying, tracking, or just exploring options — our consultants are around. Pick the channel that suits you.</p>

				<a href="<?php echo esc_url( $wa_url ); ?>" class="visati-btn visati-btn--whatsapp" target="_blank" rel="noopener noreferrer">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
					WhatsApp · <?php echo esc_html( $whatsapp_note ); ?>
				</a>

				<div class="visati-contact-page__details">
					<div class="visati-contact-page__detail">
						<div class="visati-contact-page__detail-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
						</div>
						<div>
							<span class="visati-contact-page__detail-label">Email</span>
							<a href="mailto:<?php echo esc_attr( $email ); ?>" class="visati-contact-page__detail-value"><?php echo esc_html( $email ); ?></a>
							<span class="visati-contact-page__detail-note"><?php echo esc_html( $email_note ); ?></span>
						</div>
					</div>
					<div class="visati-contact-page__detail">
						<div class="visati-contact-page__detail-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
						</div>
						<div>
							<span class="visati-contact-page__detail-label">Phone</span>
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>" class="visati-contact-page__detail-value"><?php echo esc_html( $phone ); ?></a>
							<span class="visati-contact-page__detail-note"><?php echo esc_html( $phone_note ); ?></span>
						</div>
					</div>
					<div class="visati-contact-page__detail">
						<div class="visati-contact-page__detail-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
						</div>
						<div>
							<span class="visati-contact-page__detail-label">Office</span>
							<span class="visati-contact-page__detail-value"><?php echo nl2br( esc_html( $address ) ); ?></span>
							<span class="visati-contact-page__detail-note"><?php echo esc_html( $office_note ); ?></span>
						</div>
					</div>
					<div class="visati-contact-page__detail">
						<div class="visati-contact-page__detail-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
						</div>
						<div>
							<span class="visati-contact-page__detail-label">Hours</span>
							<span class="visati-contact-page__detail-value"><?php echo nl2br( esc_html( $hours ) ); ?></span>
							<span class="visati-contact-page__detail-note"><?php echo esc_html( $hours_note ); ?></span>
						</div>
					</div>
				</div>

				<?php if ( $maps_embed_url ) : ?>
				<div class="visati-contact-page__map">
					<iframe title="<?php echo esc_attr( 'Visati Office — ' . $address ); ?>" src="<?php echo esc_url( $maps_embed_url ); ?>" width="100%" height="100%" style="border:0" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
				</div>
				<?php endif; ?>
			</div>

			<div class="visati-contact-page__right">
				<div class="visati-contact-page__form-card">
					<h2 class="visati-contact-page__form-title">Send an inquiry.</h2>
					<p class="visati-contact-page__form-desc">For everything that isn't a live application.</p>
					<?php echo self::render(); ?>
					<p class="visati-contact-page__form-note">We'll reply within 1 business hour.</p>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
