<?php
/**
 * [visati_services] — the 6-item services feature grid, ported from the
 * original Next.js Services page (src/app/(public)/services/page.tsx).
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Services_Shortcode {

	/**
	 * Icon paths (lucide-style, 24x24 viewBox), matching the original page's
	 * Plane/Zap/FileCheck/Globe/Shield/Headphones icon choices per service.
	 */
	const ICONS = array(
		'plane'       => '<polygon points="22 2 15 22 11 13 2 9 22 2"/>',
		'zap'         => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
		'file-check'  => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/>',
		'globe'       => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a14.5 14.5 0 0 1 4 10 14.5 14.5 0 0 1-4 10 14.5 14.5 0 0 1-4-10 14.5 14.5 0 0 1 4-10z"/>',
		'shield'      => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
		'headphones'  => '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1v-3a9 9 0 0 1 18 0v3a1 1 0 0 1-1 1h-2a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>',
	);

	const SERVICES = array(
		array(
			'icon'        => 'plane',
			'title'       => 'Tourist Visas',
			'description' => '30-day and 60-day single and multi-entry tourist visas for the UAE. Choose the duration that fits your trip.',
			'features'    => array( '30-day single entry', '60-day single entry', '30-day multi-entry', '60-day multi-entry' ),
		),
		array(
			'icon'        => 'zap',
			'title'       => 'Express Processing',
			'description' => 'Need your visa fast? Express processing gets your application reviewed and submitted within hours, not days.',
			'features'    => array( 'Priority review', 'Same-day submission', 'Dedicated consultant' ),
		),
		array(
			'icon'        => 'file-check',
			'title'       => 'Document Assistance',
			'description' => 'Unsure which documents you need? We review your files, catch mistakes, and make sure everything is correct before submission.',
			'features'    => array( 'Pre-submission review', 'Error identification', 'Format guidance' ),
		),
		array(
			'icon'        => 'globe',
			'title'       => 'Visa Extensions',
			'description' => 'Already in the UAE and need more time? We handle visa extension applications so you can stay longer without leaving.',
			'features'    => array( 'In-country extension', 'Status check', 'Deadline tracking' ),
		),
		array(
			'icon'        => 'shield',
			'title'       => 'Business Visas',
			'description' => 'Visa options for business travelers and entrepreneurs visiting the UAE for meetings, conferences, or exploring opportunities.',
			'features'    => array( 'Business visit visa', 'Multiple entry options', 'Fast processing' ),
		),
		array(
			'icon'        => 'headphones',
			'title'       => '24/7 Support',
			'description' => 'Questions about your application? Our support team is available around the clock via WhatsApp, email, or phone.',
			'features'    => array( 'WhatsApp support', 'Email assistance', 'Phone support' ),
		),
	);

	public static function init(): void {
		add_shortcode( 'visati_services_hero', array( __CLASS__, 'hero' ) );
		add_shortcode( 'visati_services', array( __CLASS__, 'render' ) );
		add_shortcode( 'visati_services_cta', array( __CLASS__, 'cta' ) );
	}

	/**
	 * Hero — navy background, decorative rings (reuses the About page's
	 * hero visual treatment), centered eyebrow/title/desc, no buttons.
	 */
	public static function hero(): string {
		ob_start();
		?>
		<section class="visati-about-hero">
			<div class="visati-about-hero__bg" aria-hidden="true">
				<div class="visati-about-hero__ring visati-about-hero__ring--1"></div>
				<div class="visati-about-hero__ring visati-about-hero__ring--2"></div>
				<div class="visati-about-hero__ring visati-about-hero__ring--3"></div>
			</div>
			<div class="visati-about-hero__inner">
				<p class="visati-about-hero__label">What We Do</p>
				<h1 class="visati-about-hero__title">UAE Visa Services</h1>
				<p class="visati-about-hero__desc">Everything you need to get your visa — from application to approval.</p>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * CTA — bottom call to action (reuses the About page's CTA visual
	 * treatment), Apply Now + WhatsApp Us buttons.
	 */
	public static function cta(): string {
		$apply_url = esc_url( home_url( '/apply' ) );
		$whatsapp  = ( class_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'whatsapp_number', '971585542344' ) : '' ) ?: '971585542344';
		$wa_url    = 'https://wa.me/' . esc_attr( $whatsapp ) . '?text=' . rawurlencode( 'Hello Visati, I have a question about UAE visas.' );

		ob_start();
		?>
		<section class="visati-about-cta">
			<div class="visati-container visati-about-cta__inner">
				<p class="visati-about-cta__label">Ready to start?</p>
				<h2 class="visati-about-cta__title">Apply for your UAE visa.</h2>
				<p class="visati-about-cta__desc">Average response under 2 minutes. We respond faster on WhatsApp.</p>
				<div class="visati-about-cta__btns">
					<a href="<?php echo $apply_url; ?>" class="visati-btn visati-btn--gold">Apply Now →</a>
					<a href="<?php echo $wa_url; ?>" class="visati-btn visati-btn--whatsapp" target="_blank" rel="noopener noreferrer">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
						WhatsApp Us
					</a>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render(): string {
		ob_start();
		?>
		<div class="visati-services-grid">
			<?php foreach ( self::SERVICES as $service ) : ?>
				<div class="visati-service-card">
					<div class="visati-service-card__icon" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><?php echo self::ICONS[ $service['icon'] ] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed internal icon-path constant, not user input. ?></svg>
					</div>
					<h3 class="visati-service-card__title"><?php echo esc_html( $service['title'] ); ?></h3>
					<p class="visati-service-card__desc"><?php echo esc_html( $service['description'] ); ?></p>
					<ul class="visati-service-card__features">
						<?php foreach ( $service['features'] as $feature ) : ?>
							<li><?php echo esc_html( $feature ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
