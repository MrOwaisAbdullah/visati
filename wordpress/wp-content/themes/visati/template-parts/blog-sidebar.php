<?php
/**
 * Blog sidebar — CTA, categories, and recent posts.
 *
 * The CTA stays outside the scroll container so its shadow is never clipped
 * by the inner scrollbar. Topics + recent posts scroll inside it.
 *
 * Optional $args:
 * - exclude (int) Post ID to omit from the recent-posts list.
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$visati_sidebar_exclude = isset( $args['exclude'] ) ? (int) $args['exclude'] : 0;
$visati_sidebar_cats    = get_categories( array( 'hide_empty' => true ) );

$visati_sidebar_recent_args = array(
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'posts_per_page'      => 3,
	'no_found_rows'       => true,
	'ignore_sticky_posts' => true,
);
if ( $visati_sidebar_exclude ) {
	$visati_sidebar_recent_args['post__not_in'] = array( $visati_sidebar_exclude );
}
$visati_sidebar_recent = get_posts( $visati_sidebar_recent_args );

$visati_sidebar_whatsapp = class_exists( 'Visati_Core_Settings' )
	? Visati_Core_Settings::get( 'whatsapp_number', '971585542344' )
	: '971585542344';
$visati_sidebar_whatsapp = preg_replace( '/[^0-9]/', '', (string) $visati_sidebar_whatsapp );
$visati_sidebar_wa_url   = 'https://wa.me/' . rawurlencode( $visati_sidebar_whatsapp ) . '?text=' . rawurlencode( 'Hello Visati, I have a question about UAE visas.' );
?>
<aside class="visati-blog-sidebar" aria-label="<?php esc_attr_e( 'Blog sidebar', 'visati' ); ?>">

	<div class="visati-blog-sidebar__cta">
		<span class="visati-blog-sidebar__cta-eyebrow"><?php esc_html_e( 'Need a visa?', 'visati' ); ?></span>
		<h2 class="visati-blog-sidebar__cta-title"><?php esc_html_e( 'Let’s get your UAE visa sorted.', 'visati' ); ?></h2>
		<p class="visati-blog-sidebar__cta-text"><?php esc_html_e( 'Fast processing, expert document checks, and support from real visa specialists.', 'visati' ); ?></p>
		<a href="<?php echo esc_url( home_url( '/apply' ) ); ?>" class="visati-btn">
			<?php esc_html_e( 'Apply Now', 'visati' ); ?>
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
		</a>
		<a href="<?php echo esc_url( $visati_sidebar_wa_url ); ?>" class="visati-blog-sidebar__wa" target="_blank" rel="noopener noreferrer">
			<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
			<?php esc_html_e( 'or WhatsApp us', 'visati' ); ?>
		</a>
	</div>

	<div class="visati-blog-sidebar__scroll">
		<?php if ( ! empty( $visati_sidebar_cats ) ) : ?>
			<div class="visati-blog-sidebar__block">
				<h2 class="visati-blog-sidebar__heading"><?php esc_html_e( 'Explore topics', 'visati' ); ?></h2>
				<ul class="visati-blog-sidebar__list">
					<?php foreach ( $visati_sidebar_cats as $visati_sidebar_cat ) : ?>
						<li>
							<a href="<?php echo esc_url( get_category_link( $visati_sidebar_cat ) ); ?>" class="visati-blog-sidebar__link">
								<?php echo esc_html( $visati_sidebar_cat->name ); ?>
								<span>(<?php echo esc_html( number_format_i18n( $visati_sidebar_cat->count ) ); ?>)</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $visati_sidebar_recent ) ) : ?>
			<div class="visati-blog-sidebar__block">
				<h2 class="visati-blog-sidebar__heading"><?php esc_html_e( 'Recent posts', 'visati' ); ?></h2>
				<ul class="visati-blog-sidebar__recent">
					<?php foreach ( $visati_sidebar_recent as $visati_sidebar_post ) : ?>
						<li>
							<a href="<?php echo esc_url( get_permalink( $visati_sidebar_post ) ); ?>" class="visati-blog-sidebar__link">
								<?php echo esc_html( get_the_title( $visati_sidebar_post ) ); ?>
							</a>
							<span><?php echo esc_html( get_the_date( '', $visati_sidebar_post ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>

</aside>
