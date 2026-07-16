<?php
/**
 * Global footer — brand, link columns, contact, social, copyright.
 * Reads config from Visati_Core_Settings.
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name = get_bloginfo( 'name' );
$tagline   = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'brand_tagline', 'Dubai Visas. Simplified.' ) : 'Dubai Visas. Simplified.';
$desc      = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'brand_description', 'UAE visa consultancy. Concierge-grade processing for travellers and businesses.' ) : '';

$email     = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'contact_email', 'visa@visati.ae' ) : 'visa@visati.ae';
$phone     = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'contact_phone', '+971 58 554 2344' ) : '+971 58 554 2344';
$address   = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'contact_address', "Boulevard Plaza Tower 1\nDowntown Dubai, UAE" ) : "Boulevard Plaza Tower 1\nDowntown Dubai, UAE";
$licence   = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'contact_trade_licence', 'DED-XXXX-XXXX' ) : 'DED-XXXX-XXXX';
$whatsapp  = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'whatsapp_number', '971585542344' ) : '971585542344';

$ig  = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'social_instagram', 'https://instagram.com/visati.ae' ) : '#';
$fb  = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'social_facebook', 'https://facebook.com/visati.ae' ) : '#';
$x   = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'social_twitter', 'https://twitter.com/visatiuae' ) : '#';
$li  = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'social_linkedin', 'https://linkedin.com/company/visati' ) : '#';

$copyright = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'footer_copyright', '' ) : '';
if ( '' === $copyright ) {
	$copyright = sprintf( '&copy; %d %s &middot; Dubai, UAE &middot; Trade Licence %s', gmdate( 'Y' ), esc_html( $site_name ), esc_html( $licence ) );
}
?>
<footer class="visati-footer" role="contentinfo">
	<div class="visati-container visati-footer__inner">

		<!-- Brand -->
		<div class="visati-footer__brand">
			<?php if ( $logo_url = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'header_logo', '' ) : '' ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" height="28" class="visati-footer__logo">
			<?php else : ?>
				<span class="visati-footer__wordmark"><?php echo esc_html( $site_name ); ?></span>
			<?php endif; ?>
			<p class="visati-footer__desc"><?php echo esc_html( $desc ); ?></p>
			<div class="visati-footer__social">
				<a href="<?php echo esc_url( $ig ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram" class="visati-footer__social-link">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="5"/><circle cx="17.5" cy="6.5" r="1.5"/></svg>
				</a>
				<a href="<?php echo esc_url( $fb ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook" class="visati-footer__social-link">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
				</a>
				<a href="<?php echo esc_url( $x ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Twitter" class="visati-footer__social-link">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4l11.733 16h4.267l-11.733 -16z"/><path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"/></svg>
				</a>
				<a href="<?php echo esc_url( $li ); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" class="visati-footer__social-link">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
				</a>
			</div>
		</div>

		<!-- Link columns -->
		<?php
		$footer_links = array(
			'Visas'    => array(
				array( 'label' => '14-Day Tourist',  'url' => '/visa-types' ),
				array( 'label' => '30-Day Tourist',  'url' => '/visa-types' ),
				array( 'label' => '60-Day Tourist',  'url' => '/visa-types' ),
				array( 'label' => '30-Day Multi-Entry', 'url' => '/visa-types' ),
				array( 'label' => '60-Day Multi-Entry', 'url' => '/visa-types' ),
				array( 'label' => 'Visa Extension',  'url' => '/visa-types' ),
			),
			'Company'  => array(
				array( 'label' => 'About',       'url' => '/about' ),
				array( 'label' => 'Services',    'url' => '/services' ),
				array( 'label' => 'Careers',     'url' => '/careers' ),
				array( 'label' => 'Blog',        'url' => '/blog' ),
				array( 'label' => 'Testimonials', 'url' => '/#testimonials' ),
			),
			'Support'  => array(
				array( 'label' => 'FAQ',          'url' => '/faq' ),
				array( 'label' => 'Contact',      'url' => '/contact' ),
				array( 'label' => 'WhatsApp',     'url' => 'https://wa.me/' . $whatsapp ),
				array( 'label' => 'Refund Policy', 'url' => '/faq' ),
				array( 'label' => 'Terms',        'url' => '/terms' ),
			),
		);

		foreach ( $footer_links as $section => $links ) :
		?>
			<div class="visati-footer__col">
				<h4 class="visati-footer__heading"><?php echo esc_html( $section ); ?></h4>
				<ul class="visati-footer__list">
					<?php foreach ( $links as $link ) : ?>
						<li>
							<a href="<?php echo esc_url( home_url( $link['url'] ) ); ?>" class="visati-footer__link">
								<?php echo esc_html( $link['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>

		<!-- Contact -->
		<div class="visati-footer__col visati-footer__col--wide">
			<h4 class="visati-footer__heading">Contact</h4>
			<div class="visati-footer__contact">
				<div class="visati-footer__contact-row">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
					<span><?php echo nl2br( esc_html( $address ) ); ?></span>
				</div>
				<div class="visati-footer__contact-row">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
					<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
				</div>
				<div class="visati-footer__contact-row">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
					<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
				</div>
			</div>
		</div>
	</div>

	<!-- Bottom bar -->
	<div class="visati-container">
		<div class="visati-footer__bottom">
			<p class="visati-footer__copy"><?php echo wp_kses_post( $copyright ); ?></p>
			<div class="visati-footer__legal">
				<a href="<?php echo esc_url( home_url( '/privacy' ) ); ?>">Privacy</a>
				<a href="<?php echo esc_url( home_url( '/terms' ) ); ?>">Terms</a>
				<a href="<?php echo esc_url( home_url( '/cookies' ) ); ?>">Cookies</a>
			</div>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
