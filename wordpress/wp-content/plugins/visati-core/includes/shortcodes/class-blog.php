<?php
/**
 * [visati_blog] — blog grid with category filters + sidebar (categories,
 * recent posts, quick actions). Uses native WordPress posts/categories.
 *
 * Usage: [visati_blog] or [visati_blog count="9"]
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Blog_Shortcode {

	public static function init(): void {
		add_shortcode( 'visati_blog', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets(): void {
		wp_register_script( 'visati-blog', VISATI_CORE_URL . 'assets/js/blog.js', array(), visati_asset_version( 'assets/js/blog.js' ), true );
	}

	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'count' => 9,
			),
			$atts,
			'visati_blog'
		);

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => (int) $atts['count'],
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'post_status'    => 'publish',
			)
		);

		$categories = get_categories( array( 'hide_empty' => true ) );

		$whatsapp = ( class_exists( 'Visati_Core_Settings' ) ? Visati_Core_Settings::get( 'whatsapp_number', '971585542344' ) : '' ) ?: '971585542344';
		$wa_url   = 'https://wa.me/' . rawurlencode( $whatsapp ) . '?text=' . rawurlencode( 'Hello Visati, I have a question about UAE visas.' );

		ob_start();
		?>
		<div class="visati-blog-layout">
			<div class="visati-blog-main">
				<?php if ( count( $categories ) > 1 ) : ?>
					<div class="visati-blog-filters" role="tablist" aria-label="Filter by category">
						<button class="visati-blog-filter is-active" data-category="all" role="tab" aria-selected="true">All</button>
						<?php foreach ( $categories as $cat ) : ?>
							<button class="visati-blog-filter" data-category="<?php echo esc_attr( $cat->slug ); ?>" role="tab" aria-selected="false">
								<?php echo esc_html( $cat->name ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( empty( $posts ) ) : ?>
					<p class="visati-blog-empty">No blog posts yet — check back soon.</p>
				<?php else : ?>
					<div class="visati-blog-grid">
						<?php foreach ( $posts as $post_item ) :
							setup_postdata( $post_item );
							$post_cats = get_the_category( $post_item->ID );
							$primary_cat = ! empty( $post_cats ) ? $post_cats[0] : null;
							$thumb = get_the_post_thumbnail_url( $post_item->ID, 'medium_large' );
							$excerpt = wp_trim_words( wp_strip_all_tags( $post_item->post_excerpt ?: $post_item->post_content ), 22 );
						?>
							<article class="visati-blog-card" data-category="<?php echo esc_attr( $primary_cat ? $primary_cat->slug : '' ); ?>">
								<a href="<?php echo esc_url( get_permalink( $post_item ) ); ?>" class="visati-blog-card__stretched-link" aria-label="<?php echo esc_attr( get_the_title( $post_item ) ); ?>"></a>
								<?php if ( $thumb ) : ?>
									<img src="<?php echo esc_url( $thumb ); ?>" alt="" class="visati-blog-card__image" loading="lazy">
								<?php else : ?>
									<div class="visati-blog-card__image visati-blog-card__image--placeholder" aria-hidden="true"></div>
								<?php endif; ?>
								<div class="visati-blog-card__body">
									<?php if ( $primary_cat ) : ?>
										<span class="visati-blog-card__cat"><?php echo esc_html( $primary_cat->name ); ?></span>
									<?php endif; ?>
									<h3 class="visati-blog-card__title"><?php echo esc_html( get_the_title( $post_item ) ); ?></h3>
									<p class="visati-blog-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
									<div class="visati-blog-card__meta">
										<span><?php echo esc_html( get_the_date( '', $post_item ) ); ?></span>
										<span><?php echo esc_html( get_the_author_meta( 'display_name', $post_item->post_author ) ); ?></span>
									</div>
								</div>
							</article>
						<?php endforeach; wp_reset_postdata(); ?>
					</div>
					<p class="visati-blog-empty" hidden>No posts in this category yet.</p>
				<?php endif; ?>
			</div>

			<aside class="visati-blog-sidebar">
				<?php if ( ! empty( $categories ) ) : ?>
					<div class="visati-blog-sidebar__block">
						<h3 class="visati-blog-sidebar__heading">Categories</h3>
						<ul class="visati-blog-sidebar__list">
							<?php foreach ( $categories as $cat ) : ?>
								<li><a href="<?php echo esc_url( get_category_link( $cat ) ); ?>" class="visati-blog-sidebar__link"><?php echo esc_html( $cat->name ); ?> <span>(<?php echo esc_html( $cat->count ); ?>)</span></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $posts ) ) : ?>
					<div class="visati-blog-sidebar__block">
						<h3 class="visati-blog-sidebar__heading">Recent Posts</h3>
						<ul class="visati-blog-sidebar__recent">
							<?php foreach ( array_slice( $posts, 0, 5 ) as $recent ) : ?>
								<li>
									<a href="<?php echo esc_url( get_permalink( $recent ) ); ?>" class="visati-blog-sidebar__link"><?php echo esc_html( get_the_title( $recent ) ); ?></a>
									<span><?php echo esc_html( get_the_date( '', $recent ) ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<div class="visati-blog-sidebar__block visati-blog-sidebar__quick-actions">
					<h3 class="visati-blog-sidebar__heading">Quick Actions</h3>
					<a href="<?php echo esc_url( home_url( '/apply' ) ); ?>" class="visati-btn theme-primary-btn">
						Apply for a Visa
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
					</a>
					<a href="<?php echo esc_url( home_url( '/track' ) ); ?>" class="visati-btn visati-btn--outline">Track Application</a>
					<a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer" class="visati-btn visati-btn--outline">WhatsApp Us</a>
				</div>
			</aside>
		</div>
		<?php
		if ( ! wp_script_is( 'visati-blog', 'enqueued' ) ) {
			wp_enqueue_script( 'visati-blog' );
		}
		return (string) ob_get_clean();
	}
}
