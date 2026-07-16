<?php
/**
 * Global header — sticky nav bar with logo, primary menu, CTA, and mobile drawer.
 * Reads config from Visati_Core_Settings.
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logo_url   = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'header_logo', '' ) : '';
if ( ! $logo_url ) {
	$logo_url = get_theme_file_uri( 'assets/images/logo-mark.svg' );
}
$cta_label  = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'header_cta_label', 'Apply Now' ) : 'Apply Now';
$cta_url    = function_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'header_cta_url', '/apply' ) : '/apply';
$site_name  = get_bloginfo( 'name' );
$site_desc  = get_bloginfo( 'description' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header id="site-header" class="visati-header" role="banner">
	<div class="visati-header__bar">
		<div class="visati-container visati-header__inner">

			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="visati-header__brand" aria-label="<?php echo esc_attr( $site_name ); ?> home">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" class="visati-header__logo" height="36">
				<?php else : ?>
					<span class="visati-header__wordmark"><?php echo esc_html( $site_name ); ?></span>
				<?php endif; ?>
			</a>

			<nav class="visati-header__nav" aria-label="<?php esc_attr_e( 'Primary', 'visati' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'visati-header__links',
						'fallback_cb'    => false,
						'depth'          => 1,
						'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
					)
				);
				?>
			</nav>

			<div class="visati-header__actions">
				<a href="<?php echo esc_url( $cta_url ); ?>" class="visati-btn visati-header__cta">
					<?php echo esc_html( $cta_label ); ?>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
				</a>
				<button class="visati-header__toggle" type="button" aria-label="<?php esc_attr_e( 'Open menu', 'visati' ); ?>" aria-expanded="false" aria-controls="mobile-drawer">
					<span></span><span></span><span></span>
				</button>
			</div>
		</div>
	</div>

	<!-- Mobile drawer -->
	<div id="mobile-drawer" class="visati-drawer" hidden>
		<div class="visati-drawer__overlay" tabindex="-1"></div>
		<div class="visati-drawer__panel" role="dialog" aria-label="<?php esc_attr_e( 'Navigation', 'visati' ); ?>">
			<div class="visati-drawer__header">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="visati-header__brand">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" height="28">
					<?php else : ?>
						<span class="visati-header__wordmark"><?php echo esc_html( $site_name ); ?></span>
					<?php endif; ?>
				</a>
				<button class="visati-drawer__close" type="button" aria-label="<?php esc_attr_e( 'Close menu', 'visati' ); ?>">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
				</button>
			</div>
			<nav class="visati-drawer__nav" aria-label="<?php esc_attr_e( 'Mobile', 'visati' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'visati-drawer__links',
						'fallback_cb'    => false,
						'depth'          => 1,
					)
				);
				?>
				<a href="<?php echo esc_url( $cta_url ); ?>" class="visati-btn visati-btn--navy" style="width:100%;justify-content:center;margin-top:1rem;">
					<?php echo esc_html( $cta_label ); ?>
				</a>
			</nav>
		</div>
	</div>
</header>

<script>
(function(){
	var header = document.getElementById('site-header');
	if(!header) return;

	/* Sticky shrink on scroll */
	var last = 0;
	window.addEventListener('scroll', function(){
		var y = window.pageYOffset;
		header.classList.toggle('is-scrolled', y > 10);
		last = y;
	},{passive:true});

	/* Mobile drawer */
	var toggle = header.querySelector('.visati-header__toggle');
	var drawer = document.getElementById('mobile-drawer');
	var closeBtn = drawer ? drawer.querySelector('.visati-drawer__close') : null;
	var overlay = drawer ? drawer.querySelector('.visati-drawer__overlay') : null;

	function openDrawer(){
		if(!drawer) return;
		drawer.hidden = false;
		toggle.setAttribute('aria-expanded','true');
		document.body.style.overflow='hidden';
	}
	function closeDrawer(){
		if(!drawer) return;
		drawer.hidden = true;
		toggle.setAttribute('aria-expanded','false');
		document.body.style.overflow='';
	}
	if(toggle) toggle.addEventListener('click', openDrawer);
	if(closeBtn) closeBtn.addEventListener('click', closeDrawer);
	if(overlay) overlay.addEventListener('click', closeDrawer);
	document.addEventListener('keydown',function(e){ if(e.key==='Escape') closeDrawer(); });
})();
</script>
