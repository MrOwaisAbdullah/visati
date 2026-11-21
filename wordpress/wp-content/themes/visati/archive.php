<?php
/**
 * Blog archive template — categories, tags, authors, and date archives.
 *
 * Uses the same card and sidebar partials as the single-post template.
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

global $wp_query;

if ( is_category() ) {
	$visati_archive_title = single_cat_title( '', false );
	$visati_archive_label = __( 'Category', 'visati' );
} elseif ( is_tag() ) {
	$visati_archive_title = single_tag_title( '', false );
	$visati_archive_label = __( 'Tag', 'visati' );
} elseif ( is_author() ) {
	$visati_author_obj    = get_queried_object();
	$visati_archive_title = $visati_author_obj instanceof WP_User ? $visati_author_obj->display_name : get_the_archive_title();
	if ( $visati_author_obj instanceof WP_User && 'visati-seo' === $visati_author_obj->user_login ) {
		$visati_archive_title = __( 'Visati Editorial Team', 'visati' );
	}
	$visati_archive_label = __( 'Author', 'visati' );
} else {
	$visati_archive_title = get_the_archive_title();
	$visati_archive_label = __( 'Archive', 'visati' );
}

$visati_archive_description = get_the_archive_description();
$visati_blog_url            = home_url( '/blog/' );
$visati_archive_count       = isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : 0;
?>

<main id="primary" class="visati-page visati-page--blog-archive">

	<div class="visati-container">
		<nav class="visati-post-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'visati' ); ?>">
			<ol>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'visati' ); ?></a></li>
				<li><a href="<?php echo esc_url( $visati_blog_url ); ?>"><?php esc_html_e( 'Blog', 'visati' ); ?></a></li>
				<li aria-current="page"><span><?php echo esc_html( $visati_archive_title ); ?></span></li>
			</ol>
		</nav>
	</div>

	<header class="visati-archive-hero">
		<div class="visati-container visati-archive-hero__inner">
			<span class="visati-eyebrow"><?php echo esc_html( $visati_archive_label ); ?></span>
			<h1><?php echo esc_html( $visati_archive_title ); ?></h1>

			<?php if ( $visati_archive_description ) : ?>
				<div class="visati-archive-hero__desc"><?php echo wp_kses_post( $visati_archive_description ); ?></div>
			<?php endif; ?>

			<?php if ( $visati_archive_count ) : ?>
				<p class="visati-archive-hero__count">
					<?php
					printf(
						esc_html( _n( '%s article', '%s articles', $visati_archive_count, 'visati' ) ),
						esc_html( number_format_i18n( $visati_archive_count ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	</header>

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
					<p class="visati-blog-empty"><?php esc_html_e( 'No articles found. Try another topic or head back to the blog.', 'visati' ); ?></p>
					<p><a href="<?php echo esc_url( $visati_blog_url ); ?>" class="visati-btn"><?php esc_html_e( 'Back to the blog', 'visati' ); ?></a></p>
				<?php endif; ?>
			</div>

			<?php get_template_part( 'template-parts/blog-sidebar' ); ?>
		</div>
	</div>

</main>

<?php
get_footer();
