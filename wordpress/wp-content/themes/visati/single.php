<?php
/**
 * Single blog post template.
 *
 * Editorial layout: breadcrumbs, hero, featured image, prose column,
 * sidebar CTA, tags, share links, author box, related posts, and a final CTA.
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$visati_post_id = get_queried_object_id();

while ( have_posts() ) :
	the_post();

	$visati_categories  = get_the_category();
	$visati_primary_cat = ! empty( $visati_categories ) ? $visati_categories[0] : null;
	$visati_tags        = get_the_tags();
	$visati_author_id   = (int) get_the_author_meta( 'ID' );
	$visati_author_name = get_the_author();
	$visati_author_bio  = get_the_author_meta( 'description' );
	$visati_author_url  = get_author_posts_url( $visati_author_id );

	// The production blog is authored by a system SEO user; present it as the
	// editorial team instead of exposing the username on the front end.
	if ( 'visati-seo' === get_the_author_meta( 'user_login' ) ) {
		$visati_author_name = __( 'Visati Editorial Team', 'visati' );
	}

	$visati_published_ts  = (int) get_the_time( 'U' );
	$visati_modified_ts   = (int) get_the_modified_time( 'U' );
	$visati_show_updated  = $visati_modified_ts > ( $visati_published_ts + DAY_IN_SECONDS );
	$visati_modified_date = get_the_modified_date();

	$visati_content      = get_the_content();
	$visati_word_count   = str_word_count( wp_strip_all_tags( $visati_content ) );
	$visati_reading_time = max( 1, (int) ceil( $visati_word_count / 200 ) );

	$visati_excerpt = get_the_excerpt();
	if ( ! $visati_excerpt ) {
		$visati_excerpt = $visati_content;
	}
	$visati_excerpt = wp_trim_words( wp_strip_all_tags( $visati_excerpt ), 34 );

	$visati_blog_url    = home_url( '/blog/' );
	$visati_featured_id = get_post_thumbnail_id();
	$visati_featured_caption = $visati_featured_id ? get_the_post_thumbnail_caption() : '';

	$visati_permalink   = get_permalink();
	$visati_share_url   = rawurlencode( $visati_permalink );
	$visati_share_title = rawurlencode( get_the_title() );
	?>
	<main id="primary" class="visati-page visati-page--post visati-single-post">

		<div class="visati-container">
			<nav class="visati-post-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'visati' ); ?>">
				<ol>
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'visati' ); ?></a></li>
					<li><a href="<?php echo esc_url( $visati_blog_url ); ?>"><?php esc_html_e( 'Blog', 'visati' ); ?></a></li>
					<?php if ( $visati_primary_cat ) : ?>
						<li><a href="<?php echo esc_url( get_category_link( $visati_primary_cat ) ); ?>"><?php echo esc_html( $visati_primary_cat->name ); ?></a></li>
					<?php endif; ?>
					<li aria-current="page"><span><?php echo esc_html( wp_trim_words( get_the_title(), 9, '…' ) ); ?></span></li>
				</ol>
			</nav>
		</div>

		<header class="visati-post-hero">
			<div class="visati-container visati-post-hero__inner">
				<?php if ( $visati_primary_cat ) : ?>
					<a class="visati-post-hero__cat" href="<?php echo esc_url( get_category_link( $visati_primary_cat ) ); ?>">
						<?php echo esc_html( $visati_primary_cat->name ); ?>
					</a>
				<?php endif; ?>

				<h1 class="visati-post-hero__title"><?php the_title(); ?></h1>

				<?php if ( $visati_excerpt ) : ?>
					<p class="visati-post-hero__dek"><?php echo esc_html( $visati_excerpt ); ?></p>
				<?php endif; ?>

				<div class="visati-post-hero__meta">
					<div class="visati-post-hero__author">
						<?php echo get_avatar( $visati_author_id, 44, '', $visati_author_name, array( 'class' => 'visati-post-hero__avatar' ) ); ?>
						<div class="visati-post-hero__author-info">
							<span class="visati-post-hero__author-name"><?php echo esc_html( $visati_author_name ); ?></span>
							<span class="visati-post-hero__author-role"><?php esc_html_e( 'Visa specialists', 'visati' ); ?></span>
						</div>
					</div>

					<span class="visati-post-hero__meta-divider" aria-hidden="true"></span>

					<span class="visati-post-hero__meta-item">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
						<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					</span>

					<?php if ( $visati_show_updated ) : ?>
						<span class="visati-post-hero__meta-item">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
							<span><?php printf( esc_html__( 'Updated %s', 'visati' ), esc_html( $visati_modified_date ) ); ?></span>
						</span>
					<?php endif; ?>

					<span class="visati-post-hero__meta-item">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
						<span><?php printf( esc_html( _n( '%s min read', '%s min read', $visati_reading_time, 'visati' ) ), esc_html( number_format_i18n( $visati_reading_time ) ) ); ?></span>
					</span>
				</div>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="visati-container">
				<figure class="visati-post-featured">
					<?php
					the_post_thumbnail(
						'large',
						array(
							'class'         => 'visati-post-featured__img',
							'loading'       => 'eager',
							'fetchpriority' => 'high',
							'decoding'      => 'async',
						)
					);
					?>
					<?php if ( $visati_featured_caption ) : ?>
						<figcaption><?php echo esc_html( $visati_featured_caption ); ?></figcaption>
					<?php endif; ?>
				</figure>
			</div>
		<?php endif; ?>

		<div class="visati-container">
			<div class="visati-post-layout">
				<article <?php post_class( 'visati-post-body' ); ?>>
					<div class="visati-prose">
						<?php the_content(); ?>
						<?php
						wp_link_pages(
							array(
								'before'      => '<div class="visati-post-pagination">',
								'after'       => '</div>',
								'next_or_number' => 'number',
							)
						);
						?>
					</div>

					<?php if ( ! empty( $visati_tags ) ) : ?>
						<div class="visati-post-tags">
							<span class="visati-post-tags__label"><?php esc_html_e( 'Tagged', 'visati' ); ?></span>
							<?php foreach ( $visati_tags as $visati_tag ) : ?>
								<a href="<?php echo esc_url( get_tag_link( $visati_tag ) ); ?>" class="visati-post-tag">
									#<?php echo esc_html( $visati_tag->name ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<div class="visati-post-share">
						<span class="visati-post-share__label"><?php esc_html_e( 'Share this article', 'visati' ); ?></span>

						<a href="<?php echo esc_url( 'https://twitter.com/intent/tweet?url=' . $visati_share_url . '&text=' . $visati_share_title ); ?>" class="visati-post-share__btn" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Share on X', 'visati' ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4l11.733 16h4.267l-11.733 -16z"/><path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"/></svg>
						</a>

						<a href="<?php echo esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . $visati_share_url ); ?>" class="visati-post-share__btn" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Share on Facebook', 'visati' ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
						</a>

						<a href="<?php echo esc_url( 'https://www.linkedin.com/sharing/share-offsite/?url=' . $visati_share_url ); ?>" class="visati-post-share__btn" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Share on LinkedIn', 'visati' ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
						</a>

						<button type="button" class="visati-post-share__btn" data-visati-copy="<?php echo esc_url( $visati_permalink ); ?>" aria-label="<?php esc_attr_e( 'Copy article link', 'visati' ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
						</button>
					</div>

					<div class="visati-post-author">
						<?php echo get_avatar( $visati_author_id, 64, '', $visati_author_name, array( 'class' => 'visati-post-author__avatar' ) ); ?>
						<div class="visati-post-author__content">
							<span class="visati-post-author__eyebrow"><?php esc_html_e( 'Written by', 'visati' ); ?></span>
							<h2 class="visati-post-author__name">
								<a href="<?php echo esc_url( $visati_author_url ); ?>"><?php echo esc_html( $visati_author_name ); ?></a>
							</h2>
							<p class="visati-post-author__bio">
								<?php echo esc_html( $visati_author_bio ? $visati_author_bio : __( 'Visa insights from the Visati team.', 'visati' ) ); ?>
							</p>
						</div>
					</div>
				</article>

				<?php get_template_part( 'template-parts/blog-sidebar', null, array( 'exclude' => $visati_post_id ) ); ?>
			</div>
		</div>

		<?php
		$visati_related_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'post__not_in'        => array( $visati_post_id ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		);

		$visati_category_ids = wp_get_post_categories( $visati_post_id );
		if ( ! empty( $visati_category_ids ) ) {
			$visati_related_args['category__in'] = $visati_category_ids;
		}

		$visati_related = new WP_Query( $visati_related_args );

		if ( ! $visati_related->have_posts() && ! empty( $visati_category_ids ) ) {
			unset( $visati_related_args['category__in'] );
			$visati_related = new WP_Query( $visati_related_args );
		}

		if ( $visati_related->have_posts() ) :
			?>
			<section class="visati-related-posts" aria-labelledby="visati-related-posts-title">
				<div class="visati-container">
					<div class="visati-related-posts__header">
						<div>
							<span class="visati-eyebrow"><?php esc_html_e( 'Keep reading', 'visati' ); ?></span>
							<h2 id="visati-related-posts-title"><?php esc_html_e( 'Related articles', 'visati' ); ?></h2>
						</div>
						<a href="<?php echo esc_url( $visati_blog_url ); ?>" class="visati-related-posts__all">
							<?php esc_html_e( 'View all articles', 'visati' ); ?>
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
						</a>
					</div>

					<div class="visati-blog-grid visati-related-posts__grid">
						<?php
						while ( $visati_related->have_posts() ) :
							$visati_related->the_post();
							get_template_part( 'template-parts/blog-card' );
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				</div>
			</section>
			<?php
		else :
			wp_reset_postdata();
		endif;

		$visati_whatsapp = class_exists( 'Visati_Core_Settings' )
			? Visati_Core_Settings::get( 'whatsapp_number', '971585542344' )
			: '971585542344';
		$visati_whatsapp = preg_replace( '/[^0-9]/', '', (string) $visati_whatsapp );
		$visati_wa_url   = 'https://wa.me/' . rawurlencode( $visati_whatsapp );
		?>
		<section class="visati-post-cta">
			<div class="visati-container">
				<div class="visati-post-cta__panel">
					<div class="visati-post-cta__copy">
						<span class="visati-eyebrow"><?php esc_html_e( 'Ready to travel?', 'visati' ); ?></span>
						<h2><?php esc_html_e( 'Apply for your UAE visa with Visati.', 'visati' ); ?></h2>
						<p><?php esc_html_e( 'Fast processing, clear requirements, and a real visa specialist checking every document before submission.', 'visati' ); ?></p>
					</div>
					<div class="visati-post-cta__actions">
						<a href="<?php echo esc_url( home_url( '/apply' ) ); ?>" class="visati-btn">
							<?php esc_html_e( 'Apply Now', 'visati' ); ?>
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
						</a>
						<a href="<?php echo esc_url( $visati_wa_url ); ?>" class="visati-btn visati-btn--ghost" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'WhatsApp Us', 'visati' ); ?>
						</a>
					</div>
				</div>
			</div>
		</section>

	</main>

	<script>
	(function () {
		var copyButton = document.querySelector('[data-visati-copy]');
		if (!copyButton || !navigator.clipboard) {
			return;
		}

		copyButton.addEventListener('click', function () {
			navigator.clipboard.writeText(copyButton.getAttribute('data-visati-copy')).then(function () {
				var originalLabel = copyButton.getAttribute('aria-label');
				copyButton.classList.add('is-copied');
				copyButton.setAttribute('aria-label', '<?php echo esc_js( __( 'Link copied', 'visati' ) ); ?>');
				window.setTimeout(function () {
					copyButton.classList.remove('is-copied');
					copyButton.setAttribute('aria-label', originalLabel);
				}, 1800);
			});
		});
	})();
	</script>

	<?php
endwhile;

get_footer();
