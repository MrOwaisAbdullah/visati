<?php
/**
 * Search results template.
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$visati_query = get_search_query();
$visati_blog_url = home_url( '/blog/' );
?>
<main id="primary" class="visati-page visati-page--search">
	<div class="visati-container">
		<nav class="visati-post-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'visati' ); ?>">
			<ol>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'visati' ); ?></a></li>
				<li aria-current="page"><span><?php esc_html_e( 'Search', 'visati' ); ?></span></li>
			</ol>
		</nav>
	</div>

	<header class="visati-archive-hero">
		<div class="visati-container visati-archive-hero__inner">
			<span class="visati-eyebrow"><?php esc_html_e( 'Search results', 'visati' ); ?></span>
			<h1>
				<?php
				if ( $visati_query ) {
					printf( esc_html__( 'Results for “%s”', 'visati' ), esc_html( $visati_query ) );
				} else {
					esc_html_e( 'Search the site', 'visati' );
				}
				?>
			</h1>
			<?php if ( have_posts() ) : ?>
				<p class="visati-archive-hero__count">
					<?php
					global $wp_query;
					printf(
						esc_html( _n( '%s result found', '%s results found', (int) $wp_query->found_posts, 'visati' ) ),
						esc_html( number_format_i18n( (int) $wp_query->found_posts ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	</header>

	<div class="visati-container visati-404-search visati-search-bar">
		<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="search" name="s" placeholder="<?php esc_attr_e( 'Search again…', 'visati' ); ?>" value="<?php echo esc_attr( $visati_query ); ?>">
			<button type="submit" class="visati-btn"><?php esc_html_e( 'Search', 'visati' ); ?></button>
		</form>
	</div>

	<div class="visati-container">
		<div class="visati-blog-layout">
			<div class="visati-blog-main">
				<?php if ( have_posts() ) : ?>
					<div class="visati-blog-grid">
						<?php
						while ( have_posts() ) :
							the_post();
							get_template_part( 'template-parts/blog-card' );
						endwhile;
						?>
					</div>

					<div class="visati-archive-pagination">
						<?php
						the_posts_pagination(
							array(
								'mid_size'           => 1,
								'prev_text'          => esc_html__( 'Previous', 'visati' ),
								'next_text'          => esc_html__( 'Next', 'visati' ),
								'screen_reader_text' => esc_html__( 'Posts navigation', 'visati' ),
							)
						);
						?>
					</div>
				<?php else : ?>
					<p class="visati-blog-empty"><?php esc_html_e( 'No results found. Try a different search term.', 'visati' ); ?></p>
					<p><a href="<?php echo esc_url( $visati_blog_url ); ?>" class="visati-btn"><?php esc_html_e( 'Back to the blog', 'visati' ); ?></a></p>
				<?php endif; ?>
			</div>

			<?php get_template_part( 'template-parts/blog-sidebar' ); ?>
		</div>
	</div>
</main>

<?php get_footer();