<?php
/**
 * One-time setup: creates Policy pages + Visa Types archive page.
 *
 * Run: visit /wp-admin/?visati_setup_pages=1 (admin only, nonce-protected).
 * Safe to re-run — skips pages that already exist.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Setup_Pages {

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'maybe_run' ) );
	}

	public static function maybe_run(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_GET['visati_setup_pages'] ) || '1' !== $_GET['visati_setup_pages'] ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'visati_setup_pages' ) ) {
			return;
		}

		self::update_policy_pages();
		wp_safe_redirect( admin_url( 'edit.php?post_type=page&visati_setup=done' ) );
		exit;
	}

	private static function update_policy_pages(): void {
		$pages = array(
			array(
				'slug'     => 'privacy',
				'content'  => self::privacy_policy_content(),
			),
			array(
				'slug'     => 'terms',
				'content'  => self::terms_content(),
			),
			array(
				'slug'     => 'cookies',
				'content'  => self::cookie_policy_content(),
			),
		);

		foreach ( $pages as $p ) {
			$page = get_page_by_path( $p['slug'] );
			if ( ! $page ) {
				continue;
			}
			wp_update_post( array(
				'ID'           => $page->ID,
				'post_content' => $p['content'],
			) );
			update_post_meta( $page->ID, '_wp_page_template', 'plugin-templates/page-policy.php' );
		}

		// Create refund-policy if missing.
		$refund = get_page_by_path( 'refund-policy' );
		if ( ! $refund ) {
			$page_id = wp_insert_post( array(
				'post_title'   => 'Refund Policy',
				'post_name'    => 'refund-policy',
				'post_content' => self::refund_policy_content(),
				'post_status'  => 'publish',
				'post_type'    => 'page',
			) );
			if ( $page_id && ! is_wp_error( $page_id ) ) {
				update_post_meta( $page_id, '_wp_page_template', 'plugin-templates/page-policy.php' );
			}
		}
	}

	/* ── Privacy Policy ────────────────────────────────── */

	private static function privacy_policy_content(): string {
		return <<<HTML
<!-- wp:heading {"level":2} -->
<h2>1. Information We Collect</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>When you use Visati's visa application services, we may collect the following types of personal information:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li>Full name, date of birth, and nationality</li>
<li>Passport details and travel document information</li>
<li>Contact information including email address and phone number</li>
<li>Employment and financial details required for visa processing</li>
<li>Travel itinerary and accommodation information</li>
<li>Payment information for processing fees</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2>2. How We Use Your Information</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We use your personal information solely for the purpose of processing your UAE visa application. This includes:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li>Submitting your visa application to the relevant UAE authorities</li>
<li>Communicating with you about your application status</li>
<li>Providing customer support and answering your inquiries</li>
<li>Processing payments for our services</li>
<li>Sending application-related notifications via WhatsApp or email</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2>3. Data Security</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We implement industry-standard security measures to protect your personal information. All data is transmitted using encrypted connections (SSL/TLS) and stored securely. We do not sell, trade, or rent your personal information to third parties.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>4. Data Retention</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We retain your personal information only for as long as necessary to fulfill the purposes for which it was collected, including to satisfy any legal, accounting, or reporting requirements. Application records are retained for a minimum of 12 months after the visa expiry date.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>5. Third-Party Sharing</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Your personal information may be shared with:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li>UAE immigration authorities for visa processing</li>
<li>Payment processors for secure transaction handling</li>
<li>Government-affiliated typing centers for document preparation</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2>6. Your Rights</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>You have the right to access, correct, or request deletion of your personal information. To exercise these rights, please contact us at <a href="mailto:info@visati.ae">info@visati.ae</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>7. Contact Us</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If you have any questions about this Privacy Policy, please contact us:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li>Email: <a href="mailto:info@visati.ae">info@visati.ae</a></li>
<li>Phone: <a href="tel:+971555934985">+971 55 593 4985</a></li>
<li>WhatsApp: <a href="https://wa.me/971555934985" target="_blank" rel="noopener">Chat with us</a></li>
<li>Address: Boulevard Plaza Tower 1, Downtown Dubai, UAE</li>
</ul>
<!-- /wp:list -->
HTML;
	}

	/* ── Terms of Service ─────────────────────────────── */

	private static function terms_content(): string {
		return <<<HTML
<!-- wp:heading {"level":2} -->
<h2>1. Service Overview</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Visati provides visa consultation and application processing services for travelers visiting the United Arab Emirates. By using our services, you agree to these Terms of Service.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>2. Application Process</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Visa applications are processed through official UAE immigration channels. Processing times vary by visa type and are typically 2–5 business days. Visati does not guarantee visa approval — final decisions are made by UAE immigration authorities.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>3. Fees and Payments</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li>All fees are displayed in AED and USD on our website</li>
<li>Payments are processed securely through our payment partners</li>
<li>Service fees are non-refundable once the application has been submitted to immigration</li>
<li>Government fees may be subject to change without prior notice</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2>4. Client Responsibilities</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>You are responsible for providing accurate and complete information in your visa application. Any false, misleading, or incomplete information may result in application rejection. You must also ensure your passport is valid for at least 6 months from the date of entry into the UAE.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>5. Limitation of Liability</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Visati acts as an intermediary between clients and UAE immigration authorities. We are not responsible for delays, rejections, or decisions made by government authorities. Our liability is limited to the service fees paid for the specific application in question.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>6. Cancellation</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Cancellations made before application submission to immigration are eligible for a partial refund minus administrative fees. Once submitted, applications cannot be cancelled. Please refer to our <a href="/refund-policy">Refund Policy</a> for details.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>7. Contact Us</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>For questions about these Terms, contact us at <a href="mailto:info@visati.ae">info@visati.ae</a> or <a href="https://wa.me/971555934985" target="_blank" rel="noopener">WhatsApp +971 55 593 4985</a>.</p>
<!-- /wp:paragraph -->
HTML;
	}

	/* ── Cookie Policy ────────────────────────────────── */

	private static function cookie_policy_content(): string {
		return <<<HTML
<!-- wp:heading {"level":2} -->
<h2>What Are Cookies</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Cookies are small text files stored on your device when you visit our website. They help us provide you with a better experience by remembering your preferences and understanding how you use our site.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>How We Use Cookies</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We use cookies for the following purposes:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li><strong>Essential cookies:</strong> Required for the website to function properly, including form submissions and session management</li>
<li><strong>Analytics cookies:</strong> Help us understand how visitors interact with our website, allowing us to improve the user experience</li>
<li><strong>Marketing cookies:</strong> Used to track visitors across websites for marketing and advertising purposes</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2>Google Analytics</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We use Google Analytics to analyze website traffic and usage patterns. Google Analytics uses cookies to collect information about your use of our website. This information is used to compile reports and help us improve our site. Google's privacy policy: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">https://policies.google.com/privacy</a></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Google Tag Manager</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We use Google Tag Manager to manage tracking scripts on our website. GTM itself does not use cookies but triggers other tags that may. Google Tag Manager privacy policy: <a href="https://marketingplatform.google.com/about/analytics/terms/us/" target="_blank" rel="noopener noreferrer">https://marketingplatform.google.com/about/analytics/terms/us/</a></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Managing Cookies</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>You can control and manage cookies through your browser settings. Disabling certain cookies may affect the functionality of our website. Most browsers allow you to:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li>View and delete cookies</li>
<li>Block all cookies or only third-party cookies</li>
<li>Set preferences for specific websites</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2>Contact Us</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If you have questions about our use of cookies, please contact us at <a href="mailto:info@visati.ae">info@visati.ae</a>.</p>
<!-- /wp:paragraph -->
HTML;
	}

	/* ── Refund Policy ────────────────────────────────── */

	private static function refund_policy_content(): string {
		return <<<HTML
<!-- wp:heading {"level":2} -->
<h2>Refund Eligibility</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We understand that circumstances may change. Our refund policy is as follows:</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Full Refund</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li>If your visa application is rejected by UAE immigration authorities</li>
<li>If we are unable to process your application due to technical issues on our end</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":3} -->
<h3>Partial Refund</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li>If you cancel before we submit your application to immigration: refund minus AED 50 administrative fee</li>
<li>If you cancel after submission but before processing begins: 50% refund of service fees</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":3} -->
<h3>No Refund</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li>Once the application has been submitted to and is being processed by UAE immigration</li>
<li>If the application is rejected due to inaccurate or incomplete information provided by the client</li>
<li>Government fees are non-refundable regardless of application outcome</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2>How to Request a Refund</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>To request a refund, please contact us with your application ID:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li>Email: <a href="mailto:info@visati.ae">info@visati.ae</a> with subject line "Refund Request - [Application ID]"</li>
<li>WhatsApp: <a href="https://wa.me/971555934985" target="_blank" rel="noopener">+971 55 593 4985</a></li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2>Processing Time</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Approved refunds are processed within 5–7 business days and credited to the original payment method. You will receive a confirmation email once the refund has been processed.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Contact Us</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>For refund inquiries, reach us at <a href="mailto:info@visati.ae">info@visati.ae</a> or <a href="https://wa.me/971555934985" target="_blank" rel="noopener">WhatsApp +971 55 593 4985</a>.</p>
<!-- /wp:paragraph -->
HTML;
	}
}

Visati_Core_Setup_Pages::init();
