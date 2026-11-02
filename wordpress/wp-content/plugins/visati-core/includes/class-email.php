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
		$admin = self::admin_emails();
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

				if ( Visati_Core_Settings::bot_access_enabled() ) {
			$body .= '<p><strong>' . esc_html__( 'Bot document index (requires an Application Password, role Visati Bot):', 'visati-core' ) . '</strong><br><code>' . esc_html( rest_url( Visati_Core_REST_API::NAMESPACE . '/applications/' . $app_id . '/documents' ) ) . '</code></p>';
			}

			$docs = self::document_links( $app_id );
		if ( $docs ) {
			$body .= '<p><strong>' . esc_html__( 'Documents (admin login required to open):', 'visati-core' ) . '</strong></p><ul style="padding-left:20px;margin:0 0 16px;">';
			foreach ( $docs as $doc ) {
				$body .= '<li><a href="' . esc_url( $doc['url'] ) . '">' . esc_html( $doc['label'] ) . '</a></li>';
			}
			$body .= '</ul>';
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
		$admin = self::admin_emails();
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
	 * Header reproduces the site header's full brand lockup (mark + wordmark +
	 * tagline) rather than a single logo image, and the shell is dark-mode
	 * aware via `prefers-color-scheme` for clients that support it (Apple
	 * Mail, Outlook.com, Gmail on some platforms) — clients that don't simply
	 * see the light-mode version, since the media query is additive, not a
	 * replacement for inline styles.
	 *
	 * @param string $body_html Inner HTML (already escaped).
	 * @param string $app_id    Optional application ID for context.
	 * @return string
	 */
	private static function wrap( string $body_html, string $app_id = '' ): string {
		$home    = esc_url( home_url( '/' ) );
		$logo    = Visati_Core_Settings::get( 'email_logo_url', '' );
		$brand   = Visati_Core_Settings::get( 'email_brand_name', 'Visati' );
		$tagline = ( class_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'brand_tagline', 'Dubai Visas. Simplified.' ) : '' ) ?: 'Dubai Visas. Simplified.';
		$footer  = Visati_Core_Settings::get( 'email_footer_text', __( 'This is an automated message from Visati.', 'visati-core' ) );
		$navy    = Visati_Core_Settings::get( 'email_primary_color', '#0A1628' );
		$gold    = Visati_Core_Settings::get( 'email_accent_color', '#C9A84C' );
		$mist    = Visati_Core_Settings::get( 'email_bg_color', '#F8F9FB' );
		$mark    = esc_url( VISATI_CORE_URL . 'assets/images/logo-mark-email.png' );

		// Logo: an uploaded custom logo image fully replaces the lockup; otherwise
		// build the mark + wordmark + tagline lockup matching the site header.
		if ( $logo ) {
			$logo_html = '<a href="' . $home . '" style="text-decoration:none;"><img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $brand ) . '" height="32" style="display:block;height:32px;width:auto;"></a>';
		} else {
			$logo_html = '<a href="' . $home . '" style="text-decoration:none;">'
				. '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
				. '<td style="vertical-align:middle;padding-right:10px;"><img src="' . $mark . '" width="28" height="39" alt="" style="display:block;"></td>'
				. '<td style="vertical-align:middle;">'
				. '<div style="font-family:\'Montserrat\',Arial,sans-serif;font-weight:800;font-size:18px;letter-spacing:0.04em;color:#FFFFFF;text-transform:uppercase;line-height:1.1;">' . esc_html( $brand ) . '</div>'
				. '<div style="font-family:Arial,sans-serif;font-size:10px;color:rgba(255,255,255,0.6);letter-spacing:0.02em;margin-top:2px;">' . esc_html( $tagline ) . '</div>'
				. '</td>'
				. '</tr></table>'
				. '</a>';
		}

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark">
<style>
@media ( prefers-color-scheme: dark ) {
	.visati-email-bg { background: #0F1115 !important; }
	.visati-email-card { background: #1A1D24 !important; box-shadow: none !important; }
	.visati-email-body { color: #E4E6EB !important; }
	.visati-email-body a { color: ' . $gold . ' !important; }
	.visati-email-footer { background: #14161B !important; color: #8A8F98 !important; }
}
</style>
</head>
<body class="visati-email-bg" style="margin:0;padding:0;background:' . $mist . ';font-family:Inter,Arial,sans-serif;color:#1A1A2E;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="visati-email-bg" style="background:' . $mist . ';">
<tr><td align="center" style="padding:24px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" class="visati-email-card" style="background:#FFFFFF;border-radius:14px;overflow:hidden;box-shadow:0 10px 30px rgba(10,22,40,0.08);">
<tr><td style="background:' . $navy . ';padding:24px 32px;">' . $logo_html . '</td></tr>
<tr><td class="visati-email-body" style="padding:32px;color:#1A1A2E;font-size:15px;line-height:1.6;">' . $body_html . '</td></tr>
<tr><td class="visati-email-footer" style="padding:24px 32px;background:' . $mist . ';color:#7A7A7A;font-size:13px;">' . esc_html( $footer ) . '</td></tr>
</table>
</td></tr>
</table>
</body></html>';
	}

	/**
	 * Send an HTML email with Visati From headers. Resend name/email/content-type are
	 * set via filters and removed immediately after sending.
	 *
	 * @param string|array<int,string> $to      Recipient(s).
	 * @param string                   $subject Subject.
	 * @param string                   $html    HTML body.
	 * @return bool
	 */
	private static function mail( $to, string $subject, string $html ): bool {
		add_filter( 'wp_mail_from', array( __CLASS__, 'from_email' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'from_name' ) );
		add_filter( 'wp_mail_content_type', array( __CLASS__, 'content_type' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'log_failure' ) );

		$sent = wp_mail( $to, $subject, $html );

		remove_filter( 'wp_mail_from', array( __CLASS__, 'from_email' ) );
		remove_filter( 'wp_mail_from_name', array( __CLASS__, 'from_name' ) );
		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'content_type' ) );
		remove_action( 'wp_mail_failed', array( __CLASS__, 'log_failure' ) );

		if ( ! $sent ) {
			error_log( sprintf( '[visati-core] wp_mail returned false for subject "%s"', $subject ) );
		}

		return $sent;
	}

	/**
	 * Log wp_mail() failures (WP_Error from the mailer / SMTP plugin) to debug.log.
	 *
	 * @param WP_Error $error Error raised by wp_mail().
	 * @return void
	 */
	public static function log_failure( $error ): void {
		if ( is_wp_error( $error ) ) {
			error_log( '[visati-core] wp_mail_failed: ' . $error->get_error_message() );
		}
	}

	/**
	 * Send a test notification to the configured recipients (Settings page button).
	 *
	 * @param array<int,string> $to Recipients.
	 * @return bool True if wp_mail() accepted the message for delivery.
	 */
	public static function send_test( array $to ): bool {
		if ( ! $to ) {
			return false;
		}
		$body = '<p>' . esc_html__( 'This is a test notification from Visati. If you received it, admin alerts are reaching this inbox.', 'visati-core' ) . '</p>'
			. '<p>' . esc_html__( 'Sent at:', 'visati-core' ) . ' ' . esc_html( current_time( 'mysql' ) ) . '</p>';

		return self::mail( $to, __( 'Visati test notification', 'visati-core' ), self::wrap( $body ) );
	}

	/**
	 * From email: the configured verified sender (Settings / VISATI_RESEND_FROM_EMAIL),
	 * falling back to noreply@<host> only when none is set.
	 *
	 * @return string
	 */
	public static function from_email(): string {
		$configured = Visati_Core_Settings::from_email();
		if ( $configured && is_email( $configured ) ) {
			return $configured;
		}
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
	 * Admin-only download links for an application's uploaded documents. Each link goes to
	 * wp-admin (login + manage_options required) and mints a short-lived R2 URL on click,
	 * so the storage URL itself is never placed in email.
	 *
	 * @param string $app_id Application ID.
	 * @return array<int,array{url:string,label:string}>
	 */
	private static function document_links( string $app_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, document_type, filename FROM {$wpdb->prefix}visati_documents WHERE application_id = %s ORDER BY uploaded_at ASC",
				$app_id
			)
		);

		$links = array();
		foreach ( (array) $rows as $row ) {
			$links[] = array(
				'url'   => admin_url( 'admin-post.php?action=visati_download_document&id=' . (int) $row->id ),
				'label' => $row->document_type . ' — ' . $row->filename,
			);
		}
		return $links;
	}

	/**
	 * Admin notification recipient(s) — from settings (multi-recipient), env/wp-config,
	 * or WordPress admin_email, in that order.
	 *
	 * @return array<int,string>
	 */
	private static function admin_emails(): array {
		return Visati_Core_Settings::notification_emails();
	}
}
