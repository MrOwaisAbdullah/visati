<?php
/**
 * Single visa_type CPT — detail page.
 *
 * @package Visati
 */

get_header();

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

<main id="primary" class="visati-page visati-page--visa-single">
	<div class="visati-section">
		<div class="visati-container">

			<div class="visati-visa-single__header">
				<a href="<?php echo esc_url( get_post_type_archive_link( 'visati_visa_type' ) ); ?>" class="visati-visa-single__back">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
					Back to Visa Types
				</a>

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

				<h1><?php the_title(); ?></h1>

				<?php if ( $tagline ) : ?>
					<p class="visati-visa-single__tagline"><?php echo esc_html( $tagline ); ?></p>
				<?php endif; ?>
			</div>

			<div class="visati-visa-single__grid">
				<div class="visati-visa-single__content">
					<?php the_content(); ?>
				</div>

				<aside class="visati-visa-single__sidebar">
					<div class="visati-visa-single__price-card">
						<span class="visati-visa-single__price-label">Starting at</span>
						<div class="visati-visa-single__price">
							<?php if ( $price_aed ) : ?>
								<span class="visati-visa-single__price-aed">AED <?php echo esc_html( $price_aed ); ?></span>
							<?php endif; ?>
							<?php if ( $price_usd ) : ?>
								<span class="visati-visa-single__price-usd">~$<?php echo esc_html( number_format( (float) $price_usd, 0 ) ); ?></span>
							<?php endif; ?>
						</div>
						<a href="<?php echo esc_url( $apply_url ); ?>" class="visati-btn" style="width:100%;justify-content:center;">
							Apply Now
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
						</a>
					</div>

					<div class="visati-visa-single__details">
						<h2>Visa Details</h2>
						<dl>
							<?php if ( $duration ) : ?>
								<dt>Duration</dt>
								<dd><?php echo esc_html( $duration ); ?> Days</dd>
							<?php endif; ?>
							<?php if ( $entry_type ) : ?>
								<dt>Entry Type</dt>
								<dd><?php echo esc_html( ucfirst( $entry_type ) ); ?></dd>
							<?php endif; ?>
							<dt>Processing</dt>
							<dd>2–5 Business Days</dd>
						</dl>
					</div>

					<div class="visati-visa-single__need-help">
						<h2>Need Help?</h2>
						<p>Our visa experts are available to answer your questions.</p>
						<a href="https://wa.me/971555934985" class="visati-btn--whatsapp" style="width:100%;justify-content:center;" target="_blank" rel="noopener">
							Chat on WhatsApp
						</a>
					</div>
				</aside>
			</div>

		</div>
	</div>
</main>

<?php
get_footer();
