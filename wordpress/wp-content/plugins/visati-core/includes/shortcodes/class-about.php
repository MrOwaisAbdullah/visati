<?php
/**
 * [visati_about_hero] [visati_about_stats] [visati_about_mission] [visati_about_values] [visati_about_cta]
 * About page sections.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_About_Shortcodes {

	public static function init(): void {
		add_shortcode( 'visati_about_hero', array( __CLASS__, 'hero' ) );
		add_shortcode( 'visati_about_stats', array( __CLASS__, 'stats' ) );
		add_shortcode( 'visati_about_mission', array( __CLASS__, 'mission' ) );
		add_shortcode( 'visati_about_values', array( __CLASS__, 'values' ) );
		add_shortcode( 'visati_about_cta', array( __CLASS__, 'cta' ) );
	}

	/**
	 * Hero — navy background, centered text, CTA buttons.
	 */
	public static function hero(): string {
		$apply_url = esc_url( home_url( '/apply' ) );
		$whatsapp  = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'whatsapp_number', '971585542344' ) : '971585542344';
		$wa_url    = 'https://wa.me/' . esc_attr( $whatsapp ) . '?text=' . rawurlencode( 'Hello Visati, I have a question about UAE visas.' );

		ob_start();
		?>
		<section class="visati-about-hero">
			<div class="visati-about-hero__bg" aria-hidden="true">
				<div class="visati-about-hero__ring visati-about-hero__ring--1"></div>
				<div class="visati-about-hero__ring visati-about-hero__ring--2"></div>
				<div class="visati-about-hero__ring visati-about-hero__ring--3"></div>
			</div>
			<div class="visati-about-hero__inner">
				<p class="visati-about-hero__label">About Visati</p>
				<h1 class="visati-about-hero__title">UAE visas shouldn't be this complicated.</h1>
				<p class="visati-about-hero__desc">Too many portals, contradictory instructions, emails that go nowhere. We built Visati to fix that: one clean process, a licensed consultant on every file, and WhatsApp for anything in between.</p>
				<div class="visati-about-hero__btns">
					<a href="<?php echo $apply_url; ?>" class="visati-btn visati-btn--gold">Apply Now →</a>
					<a href="<?php echo $wa_url; ?>" class="visati-btn visati-btn--outline-white" target="_blank" rel="noopener noreferrer">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
						Chat with Us
					</a>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Stats strip — 4 columns.
	 */
	public static function stats(): string {
		ob_start();
		?>
		<section class="visati-about-stats">
			<div class="visati-container">
				<div class="visati-about-stats__grid">
					<div class="visati-about-stats__item">
						<p class="visati-about-stats__number">1,000+</p>
						<p class="visati-about-stats__label">Happy Travelers</p>
					</div>
					<div class="visati-about-stats__item">
						<p class="visati-about-stats__number">99%</p>
						<p class="visati-about-stats__label">Approval Rate</p>
					</div>
					<div class="visati-about-stats__item">
						<p class="visati-about-stats__number">100+</p>
						<p class="visati-about-stats__label">Google Reviews</p>
					</div>
					<div class="visati-about-stats__item">
						<p class="visati-about-stats__number">24–72h</p>
						<p class="visati-about-stats__label">Avg. Processing</p>
					</div>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Mission — two-column: text left, image + trust card right.
	 */
	public static function mission(): string {
		ob_start();
		?>
		<section class="visati-about-mission">
			<div class="visati-container">
				<div class="visati-about-mission__grid">
					<div class="visati-about-mission__copy">
						<p class="visati-section-label">Our Mission</p>
						<h2 class="visati-about-mission__title">Visa approvals should be the easy part of your trip.</h2>
						<p class="visati-about-mission__text">UAE immigration requirements change without much warning, and the margin for error is zero. A missed document or wrong photo dimension can mean delays or costly amendments.</p>
						<p class="visati-about-mission__text">We handle the details. Our consultants stay with you from picking the right visa category all the way to final approval.</p>
						<ul class="visati-about-mission__list">
							<li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Licensed UAE immigration consultants</li>
							<li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Document review before every submission</li>
							<li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Status updates via WhatsApp</li>
							<li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> No hidden fees</li>
						</ul>
					</div>
					<div class="visati-about-mission__visual">
						<div class="visati-about-mission__img-wrap">
							<img src="https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=800&q=80" alt="Dubai skyline — Visati's home city" loading="lazy" class="visati-about-mission__img">
							<div class="visati-about-mission__img-overlay"></div>
							<span class="visati-about-mission__img-caption">Downtown Dubai, UAE</span>
						</div>
						<div class="visati-about-mission__trust">
							<div class="visati-about-mission__trust-stars">★★★★★ <span>4.9 / 5.0 average rating</span></div>
							<blockquote class="visati-about-mission__trust-quote">"Applied on Monday, visa in my inbox by Wednesday afternoon. The team updated me via WhatsApp at every stage — never felt in the dark for a moment."</blockquote>
							<div class="visati-about-mission__trust-author">
								<div class="visati-about-mission__trust-avatar">SR</div>
								<div>
									<p class="visati-about-mission__trust-name">S. Rahman</p>
									<p class="visati-about-mission__trust-role">Tourist 60-day Visa · Verified client</p>
								</div>
							</div>
							<div class="visati-about-mission__trust-badge">
									<span class="visati-about-mission__trust-badge-num">99%</span>
									<span class="visati-about-mission__trust-badge-label">approval<br>rate</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Values — 3 cards.
	 */
	public static function values(): string {
		$values = array(
			array(
				'icon'  => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
				'title' => 'Speed without shortcuts',
				'body'  => 'Most applications are reviewed and submitted within hours. Fast does not mean careless: we check every file before it goes in.',
			),
			array(
				'icon'  => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
				'title' => 'Accuracy on every file',
				'body'  => 'A missed document or wrong entry type can take days to fix. A licensed consultant reviews every application before submission.',
			),
			array(
				'icon'  => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
				'title' => 'Based in Dubai',
				'body'  => 'We know UAE immigration requirements the way locals do, including the current rules and when something changed last week.',
			),
		);

		ob_start();
		?>
		<section class="visati-about-values">
			<div class="visati-container">
				<div class="visati-about-values__header">
					<p class="visati-section-label">Why Visati</p>
					<h2 class="visati-about-values__title">What sets us apart.</h2>
				</div>
				<div class="visati-about-values__grid">
					<?php foreach ( $values as $v ) : ?>
						<div class="visati-about-values__card">
							<div class="visati-about-values__icon"><?php echo $v['icon']; ?></div>
							<h3 class="visati-about-values__card-title"><?php echo esc_html( $v['title'] ); ?></h3>
							<p class="visati-about-values__card-body"><?php echo esc_html( $v['body'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * CTA — bottom call to action.
	 */
	public static function cta(): string {
		$apply_url = esc_url( home_url( '/apply' ) );
		$types_url = esc_url( home_url( '/visa-types' ) );

		ob_start();
		?>
		<section class="visati-about-cta">
			<div class="visati-container visati-about-cta__inner">
				<p class="visati-about-cta__label">Ready to start?</p>
				<h2 class="visati-about-cta__title">Your UAE visa, handled by people who know it.</h2>
				<p class="visati-about-cta__desc">1,000+ travellers have already applied through Visati.</p>
				<div class="visati-about-cta__btns">
					<a href="<?php echo $apply_url; ?>" class="visati-btn visati-btn--gold">Start Application →</a>
					<a href="<?php echo $types_url; ?>" class="visati-btn visati-btn--outline-dark">Browse Visa Types</a>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
