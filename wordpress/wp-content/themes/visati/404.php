<?php
/**
 * 404 error page — themed "not found" screen.
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="visati-page visati-page--404">
	<div class="visati-container">
		<nav class="visati-post-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'visati' ); ?>">
			<ol>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'visati' ); ?></a></li>
				<li aria-current="page"><span><?php esc_html_e( 'Not found', 'visati' ); ?></span></li>
			</ol>
		</nav>
	</div>

	<section class="visati-404-hero">
		<div class="visati-container visati-404-hero__inner">
			<span class="visati-404-hero__code">404</span>
			<h1><?php esc_html_e( 'This page took a wrong turn.', 'visati' ); ?></h1>
			<p class="visati-404-hero__dek">
				<?php esc_html_e( 'The page you are looking for does not exist or may have been moved. Try searching the site above.', 'visati' ); ?>
			</p>

			<div class="visati-404-search">
				<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="screen-reader-text" for="visati-404-search-input"><?php esc_html_e( 'Search', 'visati' ); ?></label>
					<input id="visati-404-search-input" type="search" name="s" placeholder="<?php esc_attr_e( 'Search the site…', 'visati' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
					<button type="submit" class="visati-btn"><?php esc_html_e( 'Search', 'visati' ); ?></button>
				</form>
			</div>
		</div>
	</section>

</main>

<?php get_footer();