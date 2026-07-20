<?php
/**
 * Archive for visa_type CPT — card grid.
 *
 * @package Visati
 */

get_header();
?>

<main id="primary" class="visati-page visati-page--visa-archive">
	<div class="visati-section">
		<div class="visati-container">

			<div class="visati-visa-archive__header">
				<span class="visati-eyebrow"><?php esc_html_e( 'Our Services', 'visati' ); ?></span>
				<h1><?php esc_html_e( 'Visa Types', 'visati' ); ?></h1>
				<p><?php esc_html_e( 'Choose the right visa for your trip to the UAE.', 'visati' ); ?></p>
			</div>

			<?php
			$visas = new WP_Query( array(
				'post_type'      => 'visati_visa_type',
				'posts_per_page' => 100,
				'no_found_rows'  => true,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			) );

			if ( $visas->have_posts() ) :
			?>
				<div class="visati-visa-grid" style="--cols:3">
					<?php while ( $visas->have_posts() ) : $visas->the_post();
						$visa_id    = get_the_ID();
						$slug       = get_post_field( 'post_name', $visa_id );
						$tagline    = get_post_meta( $visa_id, '_visati_tagline', true );
						$price_aed  = get_post_meta( $visa_id, '_visati_price_aed', true );
						$price_usd  = get_post_meta( $visa_id, '_visati_price_usd', true );
						$duration   = get_post_meta( $visa_id, '_visati_duration_days', true );
						$entry_type = get_post_meta( $visa_id, '_visati_entry_type', true );
						$featured   = get_post_meta( $visa_id, '_visati_is_featured', true );
						$badge      = get_post_meta( $visa_id, '_visati_badge_text', true );
						$apply_url  = home_url( '/apply?visa=' . rawurlencode( $slug ) );
					?>
						<div class="visati-visa-card<?php echo $featured ? ' visati-visa-card--featured' : ''; ?>">

							<div class="visati-visa-card__tags">
								<?php if ( $duration ) : ?>
									<span class="visati-visa-card__tag"><?php echo esc_html( $duration ); ?> DAYS</span>
								<?php endif; ?>
								<?php if ( $entry_type ) : ?>
									<span class="visati-visa-card__tag"><?php echo esc_html( ucfirst( $entry_type ) ); ?> Entry</span>
								<?php endif; ?>
								<?php if ( $featured && $badge ) : ?>
									<span class="visati-visa-card__badge"><?php echo esc_html( $badge ); ?></span>
								<?php endif; ?>
							</div>

							<h3 class="visati-visa-card__title"><?php the_title(); ?></h3>

							<?php if ( $tagline ) : ?>
								<p class="visati-visa-card__tagline"><?php echo esc_html( $tagline ); ?></p>
							<?php endif; ?>

							<div class="visati-visa-card__footer">
								<div class="visati-visa-card__price-block">
									<span class="visati-visa-card__price-label">Starting at</span>
									<div class="visati-visa-card__price">
										<?php if ( $price_aed ) : ?>
											<span class="visati-visa-card__price-aed">AED <?php echo esc_html( $price_aed ); ?></span>
										<?php endif; ?>
										<?php if ( $price_usd ) : ?>
											<span class="visati-visa-card__price-usd">~$<?php echo esc_html( number_format( (float) $price_usd, 0 ) ); ?></span>
										<?php endif; ?>
									</div>
								</div>
								<a href="<?php echo esc_url( $apply_url ); ?>" class="visati-visa-card__apply">
									Apply
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
								</a>
							</div>
						</div>
					<?php endwhile; ?>
				</div>
			<?php
			wp_reset_postdata();
			else :
			?>
				<p class="visati-track-not-found">No visa types available yet. Please check back soon.</p>
			<?php endif; ?>

		</div>
	</div>
</main>

<?php
get_footer();
