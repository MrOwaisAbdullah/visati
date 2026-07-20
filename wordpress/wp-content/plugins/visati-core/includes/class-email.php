<?php
/**
 * Email notifications (provider-agnostic — uses wp_mail(); delivery is routed by
 * the WP Mail SMTP plugin to the configured provider, e.g. Resend/Brevo/Hostinger).
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Email {

	/**
	 * Send the application confirmation email (after successful payment).
	 *
	 * @param string $app_id    Application ID (VIS-YYYY-XXXXXX).
	 * @param string $to        Applicant email.
	 * @param string $visa_name Visa type name.
	 * @return bool
	 */
	public static function send_confirmation( string $app_id, string $to, string $visa_name = '' ): bool {
		$subject = sprintf(
			/* translators: %s: application ID */
			__( 'Your Visati application %s is submitted', 'visati-core' ),
			$app_id
		);

		$track_url = esc_url( home_url( '/track?id=' . rawurlencode( $app_id ) ) );

		$body = '<p>' . esc_html__( 'Thank you — your UAE visa application has been submitted.', 'visati-core' ) . '</p>
			<p>' . esc_html__( 'Your application reference:', 'visati-core' ) . '
				<span style="display:inline-block;padding:6px 12px;background:#0A1628;color:#C9A84C;border-radius:8px;font-family:monospace;font-weight:700;">' . esc_html( $app_id ) . '</span>
			</p>';

		if ( $visa_name ) {
			$body .= '<p>' . esc_html__( 'Visa type:', 'visati-core' ) . ' ' . esc_html( $visa_name ) . '</p>';
		}

		$body .= '<p><a href="' . $track_url . '" style="display:inline-block;padding:12px 24px;background:#C9A84C;color:#0A1628;border-radius:999px;font-weight:700;text-decoration:none;">' . esc_html__( 'Track your application', 'visati-core' ) . '</a></p>';

		return self::mail( $to, $subject, self::wrap( $body, $app_id ) );
	}

	/**
	 * Send a status-update email (when an admin changes the application status).
	 *
	 * @param string $app_id Application ID.
	 * @param string $to     Applicant email.
	 * @param string $status New status label.
	 * @param string $note   Optional team note.
	 * @return bool
	 */
	public static function send_status_update( string $app_id, string $to, string $status, string $note = '' ): bool {
		$subject   = sprintf(
			/* translators: %s: application ID */
			__( 'Update on your Visati application %s', 'visati-core' ),
			$app_id
		);
		$track_url = esc_url( home_url( '/track?id=' . rawurlencode( $app_id ) ) );

		$body = '<p>' . esc_html__( 'Your application status has been updated.', 'visati-core' ) . '</p>
			<p>' . esc_html__( 'Reference:', 'visati-core' ) . ' <strong style="font-family:monospace;">' . esc_html( $app_id ) . '</strong><br>' .
			esc_html__( 'New status:', 'visati-core' ) . ' <strong>' . esc_html( ucfirst( $status ) ) . '</strong></p>';

		if ( $note ) {
			$body .= '<p style="padding:12px;background:#F8F9FB;border-radius:8px;">' . esc_html( $note ) . '</p>';
		}

		$body .= '<p><a href="' . $track_url . '" style="display:inline-block;padding:12px 24px;background:#C9A84C;color:#0A1628;border-radius:999px;font-weight:700;text-decoration:none;">' . esc_html__( 'View timeline', 'visati-core' ) . '</a></p>';

		return self::mail( $to, $subject, self::wrap( $body, $app_id ) );
	}

	/**
	 * Send admin alert when a new application is submitted.
	 *
	 * @param string $app_id    Application ID (VIS-YYYY-XXXXXX).
	 * @param string $visa_name Visa type name.
	 * @param string $name      Applicant name.
	 * @return bool
	 */
	public static function send_admin_alert( string $app_id, string $visa_name, string $name = '' ): bool {
		$admin = self::admin_email();
		if ( ! $admin ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: application ID */
			__( 'New Visati application submitted — %s', 'visati-core' ),
			$app_id
		);

		$track_url = esc_url( admin_url( 'admin.php?page=visati-applications&action=view&id=' . rawurlencode( $app_id ) ) );

		$body = '<p>' . esc_html__( 'A new visa application has been submitted and is awaiting review.', 'visati-core' ) . '</p>'
			. '<p><strong>' . esc_html__( 'Reference:', 'visati-core' ) . '</strong> '
			. '<span style="font-family:monospace;font-weight:700;">' . esc_html( $app_id ) . '</span></p>';

		if ( $name ) {
			$body .= '<p><strong>' . esc_html__( 'Applicant:', 'visati-core' ) . '</strong> ' . esc_html( $name ) . '</p>';
		}

		if ( $visa_name ) {
			$body .= '<p><strong>' . esc_html__( 'Visa type:', 'visati-core' ) . '</strong> ' . esc_html( $visa_name ) . '</p>';
		}

		$body .= '<p><a href="' . $track_url . '" style="display:inline-block;padding:12px 24px;background:#C9A84C;color:#0A1628;border-radius:999px;font-weight:700;text-decoration:none;">'
			. esc_html__( 'Review application', 'visati-core' ) . '</a></p>';

		return self::mail( $admin, $subject, self::wrap( $body, $app_id ) );
	}

	/**
	 * Send admin alert when a new inquiry is submitted.
	 *
	 * @param int    $inquiry_id Inquiry ID.
	 * @param array  $params     Form data (name, email, subject, message).
	 * @return bool
	 */
	public static function send_inquiry_notification( int $inquiry_id, array $params = array() ): bool {
		$admin = self::admin_email();
		if ( ! $admin ) {
			return false;
		}

		$name    = sanitize_text_field( $params['name'] ?? '' );
		$email   = sanitize_email( $params['email'] ?? '' );
		$subject = sanitize_text_field( $params['subject'] ?? '' );
		$message = sanitize_textarea_field( $params['message'] ?? '' );

		$subj = sprintf(
			/* translators: %s: inquiry subject */
			__( 'New Visati inquiry — %s', 'visati-core' ),
			$subject ?: __( '(no subject)', 'visati-core' )
		);

		$admin_url = esc_url( admin_url( 'admin.php?page=visati-inquiries&action=view&id=' . $inquiry_id ) );

		$body = '<p>' . esc_html__( 'A new inquiry has been submitted via the contact form.', 'visati-core' ) . '</p>'
			. '<p><strong>' . esc_html__( 'From:', 'visati-core' ) . '</strong> '
			. esc_html( $name ) . ' &lt;' . esc_html( $email ) . '&gt;</p>'
			. '<p><strong>' . esc_html__( 'Subject:', 'visati-core' ) . '</strong> '
			. esc_html( $subject ) . '</p>';

		if ( $message ) {
			$body .= '<div style="padding:12px;background:#F8F9FB;border-radius:8px;margin:16px 0;">'
				. nl2br( esc_html( $message ) ) . '</div>';
		}

		$body .= '<p><a href="' . $admin_url . '" style="display:inline-block;padding:12px 24px;background:#C9A84C;color:#0A1628;border-radius:999px;font-weight:700;text-decoration:none;">'
			. esc_html__( 'View inquiry', 'visati-core' ) . '</a></p>';

		return self::mail( $admin, $subj, self::wrap( $body ) );
	}

	/* ── Internals ───────────────────────────────────────────────────── */

	/**
	 * Wrap inner content in the Visati-branded HTML email shell.
	 *
	 * @param string $body_html Inner HTML (already escaped).
	 * @param string $app_id    Optional application ID for context.
	 * @return string
	 */
	private static function wrap( string $body_html, string $app_id = '' ): string {
		$home = esc_url( home_url( '/' ) );
		return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#F8F9FB;font-family:Inter,Arial,sans-serif;color:#1A1A2E;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8F9FB;">
<tr><td align="center" style="padding:24px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:14px;overflow:hidden;box-shadow:0 10px 30px rgba(10,22,40,0.08);">
<tr><td style="background:#0A1628;padding:24px 32px;"><a href="' . $home . '" style="color:#C9A84C;font-weight:700;font-size:20px;text-decoration:none;">Visati</a></td></tr>
<tr><td style="padding:32px;color:#1A1A2E;font-size:15px;line-height:1.6;">' . $body_html . '</td></tr>
<tr><td style="padding:24px 32px;background:#F8F9FB;color:#7A7A7A;font-size:13px;">' . esc_html__( 'This is an automated message from Visati.', 'visati-core' ) . '</td></tr>
</table>
</td></tr>
</table>
</body></html>';
	}

	/**
	 * Send an HTML email with Visati From headers. Resend name/email/content-type are
	 * set via filters and removed immediately after sending.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $html    HTML body.
	 * @return bool
	 */
	private static function mail( string $to, string $subject, string $html ): bool {
		add_filter( 'wp_mail_from', array( __CLASS__, 'from_email' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'from_name' ) );
		add_filter( 'wp_mail_content_type', array( __CLASS__, 'content_type' ) );

		$sent = wp_mail( $to, $subject, $html );

		remove_filter( 'wp_mail_from', array( __CLASS__, 'from_email' ) );
		remove_filter( 'wp_mail_from_name', array( __CLASS__, 'from_name' ) );
		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'content_type' ) );

		return $sent;
	}

	/**
	 * From email derived from the site domain (noreply@<host>).
	 *
	 * @return string
	 */
	public static function from_email(): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host ? 'noreply@' . $host : 'noreply@visati.ae';
	}

	/**
	 * From name.
	 *
	 * @return string
	 */
	public static function from_name(): string {
		return 'Visati';
	}

	/**
	 * HTML content type.
	 *
	 * @return string
	 */
	public static function content_type(): string {
		return 'text/html; charset=UTF-8';
	}

	/**
	 * Admin notification email — from settings or WordPress admin_email.
	 *
	 * @return string
	 */
	private static function admin_email(): string {
		$admin = Visati_Core_Settings::admin_notification_email();
		if ( $admin && is_email( $admin ) ) {
			return $admin;
		}
		return get_option( 'admin_email' );
	}
}
