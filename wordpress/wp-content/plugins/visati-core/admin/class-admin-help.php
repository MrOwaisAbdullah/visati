<?php
/**
 * Admin Help / Documentation page — a single in-dashboard reference for every
 * shortcode, Settings field, and content type this plugin + theme ship.
 *
 * The shortcode list is read live from WordPress's own shortcode registry
 * (filtered to the `visati_` prefix) rather than hardcoded, so a newly added
 * shortcode always appears here even if this file's description table isn't
 * updated the same day — it just shows with a generic fallback description
 * until someone fills one in.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Admin_Help {

	const CAP  = 'manage_options';
	const SLUG = 'visati-help';

	/**
	 * Human-written descriptions/examples for each shortcode, keyed by tag.
	 * Shortcodes registered but missing from this array still appear in the
	 * list (pulled live from the registry) with a generic fallback line.
	 *
	 * @return array<string,array{desc:string,example:string,atts?:array<string,string>}>
	 */
	private static function shortcode_docs(): array {
		return array(
			'visati_apply_form'     => array(
				'desc'    => 'The full 4-step visa application wizard (visa/processing → personal details → documents → review & pay), with a live Order Summary sidebar including the discount code field. Used once, on the Apply page.',
				'example' => '[visati_apply_form]',
			),
			'visati_quick_apply'    => array(
				'desc'    => 'Compact "check eligibility in 30 sec" card — nationality, visa type, travel date — that deep-links into the full Apply form with those fields pre-filled. Used in the homepage hero.',
				'example' => '[visati_quick_apply]',
			),
			'visati_visa_cards'     => array(
				'desc'    => 'Grid of visa type cards (price, duration, entry type, Featured badge) pulled from the Visa Types content type.',
				'example' => '[visati_visa_cards count="12" columns="3" featured="1"]',
				'atts'    => array(
					'count'    => 'Max cards to show. Default 12.',
					'columns'  => 'Grid columns. Default 3.',
					'featured' => 'Set to "1" to show only Featured visa types.',
				),
			),
			'visati_faq'            => array(
				'desc'    => 'Accordion-style FAQ list with category filter pills, pulled from the FAQ content type.',
				'example' => '[visati_faq category="Pricing" count="20"]',
				'atts'    => array(
					'category' => 'Filter to one FAQ category (matches the Category meta field). Leave empty for all.',
					'count'    => 'Max FAQs to show. Default 50.',
				),
			),
			'visati_testimonials'   => array(
				'desc'    => 'Horizontally scrolling testimonial cards (star rating, quote, country flag) with prev/next arrows, pulled from the Testimonials content type.',
				'example' => '[visati_testimonials count="12"]',
				'atts'    => array( 'count' => 'Max cards to show. Default 12.' ),
			),
			'visati_rating_badge'   => array(
				'desc'    => '"★★★★★ 4.9 — Based on 100+ reviews" summary badge. Uses plain text stars (not an icon font), so it renders reliably on every device — use this instead of Elementor\'s native Star Rating widget, which can render blank on some devices.',
				'example' => '[visati_rating_badge rating="4.9" note="Based on 100+ reviews on Trustpilot &amp; Google"]',
				'atts'    => array(
					'rating' => 'The number shown next to the stars. Default "4.9".',
					'note'   => 'The line underneath. Default "Based on 100+ reviews on Trustpilot &amp; Google".',
				),
			),
			'visati_jobs'           => array(
				'desc'    => 'List of open job postings, pulled from the Job Openings content type (inactive/closed roles are hidden automatically).',
				'example' => '[visati_jobs count="20"]',
				'atts'    => array( 'count' => 'Max jobs to show. Default 20.' ),
			),
			'visati_blog'           => array(
				'desc'    => 'Blog post grid with category filter pills and a sidebar (categories, recent posts). Uses standard WordPress posts, not a custom content type.',
				'example' => '[visati_blog count="9"]',
				'atts'    => array( 'count' => 'Posts per page. Default 9.' ),
			),
			'visati_contact_form'   => array(
				'desc'    => 'Just the inquiry form (name/email/phone/subject/message) — no surrounding page layout.',
				'example' => '[visati_contact_form]',
			),
			'visati_contact_page'   => array(
				'desc'    => 'Full Contact page layout — contact details, office hours, embedded Google Map, and the inquiry form together. Used on the Contact page.',
				'example' => '[visati_contact_page]',
			),
			'visati_about_hero'     => array(
				'desc'    => 'About page hero — navy background, decorative rings, heading + description + Apply/WhatsApp buttons.',
				'example' => '[visati_about_hero]',
			),
			'visati_about_stats'    => array(
				'desc'    => 'About page 4-column stats strip.',
				'example' => '[visati_about_stats]',
			),
			'visati_about_mission'  => array(
				'desc'    => 'About page mission/story section.',
				'example' => '[visati_about_mission]',
			),
			'visati_about_values'   => array(
				'desc'    => 'About page values/principles cards.',
				'example' => '[visati_about_values]',
			),
			'visati_about_cta'      => array(
				'desc'    => 'About page bottom call-to-action band.',
				'example' => '[visati_about_cta]',
			),
			'visati_services_hero'  => array(
				'desc'    => 'Services page hero — reuses the same navy/decorative-rings visual treatment as the About hero, with Services-specific copy.',
				'example' => '[visati_services_hero]',
			),
			'visati_services'       => array(
				'desc'    => 'The 6-card services feature grid (Tourist Visas, Express Processing, Document Assistance, Visa Extensions, Business Visas, 24/7 Support).',
				'example' => '[visati_services]',
			),
			'visati_services_cta'   => array(
				'desc'    => 'Services page bottom call-to-action band (Apply Now + WhatsApp Us).',
				'example' => '[visati_services_cta]',
			),
			'visati_global_reach'   => array(
				'desc'    => 'Animated world-map section (dashed arcs from Dubai to 10 regions, pulsing pins, 4-stat count-up). Heading/description/CTA/stat text are all editable in Settings → Homepage — Global Reach Section.',
				'example' => '[visati_global_reach]',
			),
		);
	}

	/**
	 * Human-written descriptions for each custom content type, keyed by post type slug.
	 *
	 * @return array<string,string>
	 */
	private static function cpt_docs(): array {
		return array(
			'visati_visa_type'   => 'Each visa type offered (e.g. "30-Day Tourist", "60-Day Multiple Entry"). Powers the visa dropdown on the Apply form, the Visa Types archive page, and the [visati_visa_cards] shortcode.',
			'visati_faq'         => 'One question + answer per post. Powers [visati_faq] and the FAQ page.',
			'visati_testimonial' => 'One customer review per post. Powers [visati_testimonials] on the homepage.',
			'visati_job'         => 'One open role per post. Powers [visati_jobs] on the Careers page.',
			'visati_discount'    => 'One discount/coupon code per post — the post title IS the code customers type in (matched case-insensitively). Powers the discount field on the Apply page\'s Order Summary.',
		);
	}

	/**
	 * Settings page fields, grouped exactly as they appear on the Settings
	 * page itself, for cross-reference (kept as a hand-written table rather
	 * than introspected, since Settings fields are plain HTML inputs with no
	 * live registry to read from).
	 *
	 * @return array<string,array<array{field:string,desc:string}>>
	 */
	private static function settings_docs(): array {
		return array(
			'WhatsApp & Contact'          => array(
				array( 'field' => 'WhatsApp number', 'desc' => 'Used for every WhatsApp CTA site-wide (header, footer, Contact page, Apply page Order Summary, Services page).' ),
				array( 'field' => 'WhatsApp default message', 'desc' => 'Pre-filled text when a visitor clicks a WhatsApp button.' ),
				array( 'field' => 'Contact email / phone', 'desc' => 'Shown in the footer and Contact page.' ),
			),
			'Contact Page Details'        => array(
				array( 'field' => 'Office hours', 'desc' => 'Two lines shown on the Contact page.' ),
				array( 'field' => 'WhatsApp / Email / Phone / Office / Hours row notes', 'desc' => 'The small subtext under each contact method on the Contact page (e.g. "Average reply 2 min").' ),
				array( 'field' => 'Google Maps embed URL', 'desc' => 'The iframe src for the embedded map. Get it via Google Maps → Share → Embed a map.' ),
			),
			'Announcement bar'            => array(
				array( 'field' => 'Active / Text / CTA label / CTA URL / colors / End date', 'desc' => 'The dismissible bar above the header. Leave "Active" unchecked to hide it entirely.' ),
			),
			'Notifications'                => array(
				array( 'field' => 'Notification recipients', 'desc' => 'One email per line — every address here gets new-application and new-inquiry alerts. Falls back to the WordPress admin email if left empty.' ),
			array( 'field' => 'Enable bot document access', 'desc' => 'Turns on the bot REST endpoints (Application Password, Visati Bot role) and adds the bot document index to alert emails. Off = every bot request gets 403. See Document Downloads & Bot Access below.' ),
			),
			'Tracking & Social'           => array(
				array( 'field' => 'GA4 ID / GTM ID / Facebook Pixel / TikTok Pixel', 'desc' => 'Analytics/ad tracking IDs, injected site-wide if set.' ),
				array( 'field' => 'Instagram / Facebook / LinkedIn / Twitter URLs', 'desc' => 'Footer social icon links.' ),
			),
			'Header'                       => array(
				array( 'field' => 'Logo image', 'desc' => 'Overrides the default gold wing mark in the header. Leave empty for the default.' ),
				array( 'field' => 'CTA button text / URL', 'desc' => 'The header\'s "Apply Now" button.' ),
			),
			'Apply Page — Order Summary'  => array(
				array( 'field' => 'Guarantee title / text', 'desc' => 'The dark trust badge in the Apply form\'s Order Summary sidebar.' ),
			),
			'Express Processing Fee'      => array(
				array( 'field' => 'Express surcharge (AED / USD)', 'desc' => 'Added to the visa fee when an applicant chooses Express processing. USD value is used in the Stripe checkout amount.' ),
			),
			'Homepage — Global Reach Section' => array(
				array( 'field' => 'Eyebrow / Heading / Description / CTA label / CTA URL', 'desc' => 'Text content of the [visati_global_reach] section.' ),
				array( 'field' => 'Stat 1–4 number + label', 'desc' => 'The 4 count-up stats (e.g. "1000+" / "Happy Travelers"). Enter the number including any suffix.' ),
			),
			'Footer & Brand'               => array(
				array( 'field' => 'Brand tagline', 'desc' => 'Shown under "VISATI" in the header and footer, and in the email template header (e.g. "Dubai Visas. Simplified.").' ),
				array( 'field' => 'Brand description', 'desc' => 'Footer brand paragraph.' ),
				array( 'field' => 'Office address / Trade licence number', 'desc' => 'Footer contact block + auto-generated copyright line.' ),
				array( 'field' => 'Custom copyright text', 'desc' => 'Fully overrides the auto-generated copyright line (which otherwise includes the trade licence number). Leave empty for the auto-generated version.' ),
			),
			'Email Template'               => array(
				array( 'field' => 'Logo image', 'desc' => 'If set, fully replaces the default mark+wordmark+tagline lockup in transactional emails with this single image.' ),
				array( 'field' => 'Brand name', 'desc' => 'Used in the email header lockup when no custom logo is set. Default "Visati".' ),
				array( 'field' => 'Header background / Accent / Email background colors', 'desc' => 'Email shell colors. The email is also dark-mode aware for supporting mail clients.' ),
				array( 'field' => 'Footer text', 'desc' => 'Small print at the bottom of every transactional email.' ),
			),
		);
	}

	/**
	 * REST API endpoints this plugin registers, for developer reference.
	 *
	 * @return array<array{method:string,route:string,desc:string}>
	 */
	private static function rest_docs(): array {
		return array(
			array( 'method' => 'POST', 'route' => '/wp-json/visati/v1/applications', 'desc' => 'Create a draft application (step 1 of the Apply form).' ),
			array( 'method' => 'POST', 'route' => '/wp-json/visati/v1/documents', 'desc' => 'Upload a document (passport copy, photo) to an in-progress application, stored in Cloudflare R2.' ),
			array( 'method' => 'POST', 'route' => '/wp-json/visati/v1/checkout-session', 'desc' => 'Create a Stripe Checkout session for a submitted application. Re-validates any discount code server-side.' ),
			array( 'method' => 'POST', 'route' => '/wp-json/visati/v1/webhooks/stripe', 'desc' => 'Stripe webhook receiver — marks applications as submitted/paid, records discount usage, sends confirmation emails.' ),
			array( 'method' => 'GET', 'route' => '/wp-json/visati/v1/track', 'desc' => 'Look up an application by ID for the Track page.' ),
			array( 'method' => 'POST', 'route' => '/wp-json/visati/v1/discount/validate', 'desc' => 'Preview a discount code\'s computed amount before checkout, without applying it.' ),
			array( 'method' => 'POST', 'route' => '/wp-json/visati/v1/inquiries', 'desc' => 'Submit the Contact page inquiry form.' ),
			array( 'method' => 'GET', 'route' => '/wp-json/visati/v1/applications/{id}/documents', 'desc' => 'Bot access: list an application\'s applicant documents, each with a download_url. Needs an Application Password (Visati Bot role) and the bot toggle on.' ),
			array( 'method' => 'GET', 'route' => '/wp-json/visati/v1/documents/{id}/download', 'desc' => 'Bot access: returns a fresh 5-minute storage URL for one applicant document.' ),
		);
	}

	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$shortcode_docs = self::shortcode_docs();
		$cpt_docs       = self::cpt_docs();

		// Live registry, filtered to our prefix — never silently goes stale.
		global $shortcode_tags;
		$registered = array_filter( array_keys( (array) $shortcode_tags ), function ( $tag ) {
			return 0 === strpos( $tag, 'visati_' );
		} );
		sort( $registered );

		$registered_cpts = array_filter( get_post_types( array( '_builtin' => false ) ), function ( $slug ) {
			return 0 === strpos( $slug, 'visati_' );
		} );
		sort( $registered_cpts );
		?>
		<div class="wrap visati-help">
			<h1><?php esc_html_e( 'Visati — Reference', 'visati-core' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Every shortcode, Settings field, content type, and API endpoint this plugin and theme ship, in one place.', 'visati-core' ); ?></p>

			<p class="visati-help__jump">
				<a href="#visati-help-shortcodes"><?php esc_html_e( 'Shortcodes', 'visati-core' ); ?></a> ·
				<a href="#visati-help-content-types"><?php esc_html_e( 'Content Types', 'visati-core' ); ?></a> ·
				<a href="#visati-help-settings"><?php esc_html_e( 'Settings Fields', 'visati-core' ); ?></a> ·
				<a href="#visati-help-api"><?php esc_html_e( 'REST API', 'visati-core' ); ?></a>
			</p>

			<h2 id="visati-help-shortcodes"><?php esc_html_e( 'Shortcodes', 'visati-core' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Drop these into any Page/Post content, or an Elementor Shortcode widget.', 'visati-core' ); ?></p>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th style="width:220px;"><?php esc_html_e( 'Shortcode', 'visati-core' ); ?></th>
					<th><?php esc_html_e( 'What it does', 'visati-core' ); ?></th>
					<th style="width:260px;"><?php esc_html_e( 'Example', 'visati-core' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $registered as $tag ) :
						$doc = $shortcode_docs[ $tag ] ?? array(
							'desc'    => __( '(No description written yet for this shortcode.)', 'visati-core' ),
							'example' => '[' . $tag . ']',
						);
					?>
						<tr>
							<td><code>[<?php echo esc_html( $tag ); ?>]</code></td>
							<td>
								<?php echo esc_html( $doc['desc'] ); ?>
								<?php if ( ! empty( $doc['atts'] ) ) : ?>
									<ul style="margin:0.5em 0 0;">
										<?php foreach ( $doc['atts'] as $att => $att_desc ) : ?>
											<li><code><?php echo esc_html( $att ); ?></code> — <?php echo esc_html( $att_desc ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( $doc['example'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $registered ) ) : ?>
						<tr><td colspan="3"><?php esc_html_e( 'No visati_* shortcodes are currently registered.', 'visati-core' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>

			<h2 id="visati-help-content-types"><?php esc_html_e( 'Content Types', 'visati-core' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th style="width:220px;"><?php esc_html_e( 'Content type', 'visati-core' ); ?></th>
					<th><?php esc_html_e( 'What it\'s for', 'visati-core' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $registered_cpts as $slug ) :
						$obj = get_post_type_object( $slug );
					?>
						<tr>
							<td><strong><?php echo esc_html( $obj ? $obj->labels->name : $slug ); ?></strong><br><code><?php echo esc_html( $slug ); ?></code></td>
							<td><?php echo esc_html( $cpt_docs[ $slug ] ?? __( '(No description written yet.)', 'visati-core' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2 id="visati-help-settings"><?php esc_html_e( 'Settings Fields', 'visati-core' ); ?></h2>
			<p class="description">
				<?php
				printf(
					/* translators: %s: link to the Settings page */
					esc_html__( 'All client-editable content lives in %s. Secrets (Stripe, R2, Resend/SMTP) are never edited in wp-admin — they live in wp-config.php on the server.', 'visati-core' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=visati-settings' ) ) . '">' . esc_html__( 'Visati → Settings', 'visati-core' ) . '</a>'
				);
				?>
			</p>
			<?php foreach ( self::settings_docs() as $section => $fields ) : ?>
				<h3><?php echo esc_html( $section ); ?></h3>
				<table class="wp-list-table widefat fixed striped" style="margin-bottom:1.5em;">
					<tbody>
						<?php foreach ( $fields as $row ) : ?>
							<tr>
								<td style="width:280px;"><strong><?php echo esc_html( $row['field'] ); ?></strong></td>
								<td><?php echo esc_html( $row['desc'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>

			<h2 id="visati-help-bot-access"><?php esc_html_e( 'Document Downloads & Bot Access', 'visati-core' ); ?></h2>
			<?php $visati_api = rest_url( Visati_Core_REST_API::NAMESPACE ); ?>

			<h3><?php esc_html_e( 'Admin download links (alert emails)', 'visati-core' ); ?></h3>
			<p><?php esc_html_e( 'Each new application alert lists the applicant documents. Clicking a link opens wp-admin and downloads the file. You must be logged in as an administrator. Each click fetches a fresh 5-minute storage link, so the link in the email never expires. Storage links are never placed in email.', 'visati-core' ); ?></p>

			<h3><?php esc_html_e( 'Setting up a bot, CMS or AI agent', 'visati-core' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Settings → Notifications → tick "Enable bot document access", then Save.', 'visati-core' ); ?></li>
				<li><?php esc_html_e( 'Users → Add New: create a user (for example visati-bot) and set the role to Visati Bot.', 'visati-core' ); ?></li>
				<li><?php esc_html_e( 'Edit that user → Application Passwords → enter a name → Add New Application Password. Copy it now, because WordPress will not show it again.', 'visati-core' ); ?></li>
				<li><?php esc_html_e( 'Store the password in the bot\'s secret store. Never paste it into chat, email or the Settings page.', 'visati-core' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Endpoints', 'visati-core' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th style="width:80px;"><?php esc_html_e( 'Method', 'visati-core' ); ?></th>
					<th style="width:420px;"><?php esc_html_e( 'URL', 'visati-core' ); ?></th>
					<th><?php esc_html_e( 'Returns', 'visati-core' ); ?></th>
				</tr></thead>
				<tbody>
					<tr><td><code>GET</code></td><td><code><?php echo esc_html( $visati_api ); ?>/applications/{VIS-ID}/documents</code></td><td><?php esc_html_e( 'The applicant document list. Each entry has a download_url.', 'visati-core' ); ?></td></tr>
					<tr><td><code>GET</code></td><td><code><?php echo esc_html( $visati_api ); ?>/documents/{id}/download</code></td><td><?php esc_html_e( 'JSON with a url valid for expires_in (300) seconds. Request the url within that window.', 'visati-core' ); ?></td></tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Example', 'visati-core' ); ?></h3>
			<pre><code>curl -u "visati-bot:APP_PASSWORD" "<?php echo esc_html( $visati_api ); ?>/applications/VIS-2026-XXXXXX/documents"
curl -u "visati-bot:APP_PASSWORD" "<?php echo esc_html( $visati_api ); ?>/documents/12/download"
curl -L -o passport.pdf "URL_FROM_THE_RESPONSE"</code></pre>

			<h3><?php esc_html_e( 'Rules', 'visati-core' ); ?></h3>
			<ul style="list-style:disc;padding-left:20px;">
				<li><?php esc_html_e( 'Applicant documents only. Issued visa files are never returned to bots.', 'visati-core' ); ?></li>
				<li><?php esc_html_e( 'Toggle off = every bot request gets 403, even with valid credentials.', 'visati-core' ); ?></li>
				<li><?php esc_html_e( 'Requests are rate limited by the same helper as the public forms (120 per 10 minutes).', 'visati-core' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Errors', 'visati-core' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th style="width:80px;"><?php esc_html_e( 'Code', 'visati-core' ); ?></th><th><?php esc_html_e( 'Meaning and fix', 'visati-core' ); ?></th></tr></thead>
				<tbody>
					<tr><td>401</td><td><?php esc_html_e( 'Not logged in. Check the username and Application Password (spaces are fine).', 'visati-core' ); ?></td></tr>
					<tr><td>403</td><td><?php esc_html_e( 'Bot access is off, or the user does not have the Visati Bot role.', 'visati-core' ); ?></td></tr>
					<tr><td>404</td><td><?php esc_html_e( 'Document ID not found, or it is an issued visa file (not available to bots).', 'visati-core' ); ?></td></tr>
					<tr><td>429</td><td><?php esc_html_e( 'Rate limit reached. Wait 10 minutes.', 'visati-core' ); ?></td></tr>
					<tr><td>500</td><td><?php esc_html_e( 'Document storage is not configured. Check the VISATI_R2_* constants in wp-config.php.', 'visati-core' ); ?></td></tr>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Revoking access', 'visati-core' ); ?></h3>
			<p><?php esc_html_e( 'Untick the toggle to block all bots at once. To revoke one bot, delete its Application Password on its user profile, or delete the user.', 'visati-core' ); ?></p>

			<h3><?php esc_html_e( 'Privacy', 'visati-core' ); ?></h3>
			<p><?php esc_html_e( 'Passports and photos are personal data. Before you connect a third-party CMS or AI service, confirm that your privacy notice and applicant consent cover that processing.', 'visati-core' ); ?></p>

						<h2 id="visati-help-api"><?php esc_html_e( 'REST API', 'visati-core' ); ?></h2>
			<p class="description"><?php esc_html_e( 'For developer reference — the apply form, tracking page, and Stripe webhook all talk to these.', 'visati-core' ); ?></p>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th style="width:80px;"><?php esc_html_e( 'Method', 'visati-core' ); ?></th>
					<th style="width:320px;"><?php esc_html_e( 'Route', 'visati-core' ); ?></th>
					<th><?php esc_html_e( 'Purpose', 'visati-core' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( self::rest_docs() as $row ) : ?>
						<tr>
							<td><code><?php echo esc_html( $row['method'] ); ?></code></td>
							<td><code><?php echo esc_html( $row['route'] ); ?></code></td>
							<td><?php echo esc_html( $row['desc'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
