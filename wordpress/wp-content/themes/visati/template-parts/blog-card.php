<?php
/**
 * Reusable blog card used by archives and related-post sections.
 *
 * Must be called inside a WordPress loop.
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$visati_card_id       = get_the_ID();
$visati_card_cats     = get_the_category( $visati_card_id );
$visati_card_cat      = ! empty( $visati_card_cats ) ? $visati_card_cats[0] : null;
$visati_card_thumb    = get_the_post_thumbnail_url( $visati_card_id, 'medium_large' );
$visati_card_excerpt  = get_the_excerpt();
$visati_card_excerpt  = $visati_card_excerpt ? $visati_card_excerpt : get_the_content();
$visati_card_excerpt  = wp_trim_words( wp_strip_all_tags( $visati_card_excerpt ), 20 );
$visati_card_title    = get_the_title();
$visati_card_permalink = get_permalink();
?>
<article class="visati-blog-card">
	<a href="<?php echo esc_url( $visati_card_permalink ); ?>" class="visati-blog-card__stretched-link" aria-label="<?php echo esc_attr( $visati_card_title ); ?>"></a>

	<?php if ( $visati_card_thumb ) : ?>
		<img
			src="<?php echo esc_url( $visati_card_thumb ); ?>"
			alt="<?php echo esc_attr( $visati_card_title ); ?>"
			class="visati-blog-card__image"
			loading="lazy"
		>
	<?php else : ?>
		<div class="visati-blog-card__image visati-blog-card__image--placeholder" aria-hidden="true"></div>
	<?php endif; ?>

	<div class="visati-blog-card__body">
		<?php if ( $visati_card_cat ) : ?>
			<span class="visati-blog-card__cat"><?php echo esc_html( $visati_card_cat->name ); ?></span>
		<?php endif; ?>

		<h3 class="visati-blog-card__title"><?php echo esc_html( $visati_card_title ); ?></h3>

		<?php if ( $visati_card_excerpt ) : ?>
			<p class="visati-blog-card__excerpt"><?php echo esc_html( $visati_card_excerpt ); ?></p>
		<?php endif; ?>

		<div class="visati-blog-card__meta">
			<span><?php echo esc_html( get_the_date() ); ?></span>
		</div>
	</div>
</article>
