<?php
/**
 * [visati_visa_cards] — renders a grid of visa type cards from the CPT.
 *
 * Usage: [visati_visa_cards] or [visati_visa_cards count="6" featured="yes"]
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Visa_Cards_Shortcode {

	public static function init(): void {
		add_shortcode( 'visati_visa_cards', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'count'    => 12,
				'featured' => '',    // 'yes' = only featured, 'no' = exclude featured, '' = all
				'columns'  => 3,
			),
			$atts,
			'visati_visa_cards'
		);

		$args = array(
			'post_type'      => 'visati_visa_type',
			'posts_per_page' => (int) $atts['count'],
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		if ( 'yes' === $atts['featured'] ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_visati_is_featured',
					'value' => '1',
				),
			);
		} elseif ( 'no' === $atts['featured'] ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_visati_is_featured',
					'value'   => '1',
					'compare' => '!=',
				),
			);
		}

		$visas = get_posts( $args );

		if ( empty( $visas ) ) {
			return '<p class="visati-track-not-found">No visa types available at the moment.</p>';
		}

		ob_start();
		?>
		<div class="visati-visa-grid" style="--cols:<?php echo esc_attr( $atts['columns'] ); ?>">
			<?php foreach ( $visas as $visa ) :
				$slug        = $visa->post_name;
				$title       = $visa->post_title;
				$tagline     = get_post_meta( $visa->ID, '_visati_tagline', true );
				$price_aed   = get_post_meta( $visa->ID, '_visati_price_aed', true );
				$price_usd   = get_post_meta( $visa->ID, '_visati_price_usd', true );
				$duration    = get_post_meta( $visa->ID, '_visati_duration_days', true );
				$entry_type  = get_post_meta( $visa->ID, '_visati_entry_type', true );
				$has_express = get_post_meta( $visa->ID, '_visati_has_express', true );
				$is_featured = get_post_meta( $visa->ID, '_visati_is_featured', true );
				$badge_text  = get_post_meta( $visa->ID, '_visati_badge_text', true );
				$features    = get_post_meta( $visa->ID, '_visati_features', true );
				$apply_url   = home_url( '/apply?visa=' . rawurlencode( $slug ) );
			?>
				<div class="visati-visa-card">
					<?php if ( $is_featured && $badge_text ) : ?>
						<span class="visati-visa-card__badge"><?php echo esc_html( $badge_text ); ?></span>
					<?php endif; ?>

					<h3 class="visati-visa-card__title"><?php echo esc_html( $title ); ?></h3>

					<?php if ( $tagline ) : ?>
						<p class="visati-visa-card__tagline"><?php echo esc_html( $tagline ); ?></p>
					<?php endif; ?>

					<?php if ( $price_aed ) : ?>
						<div class="visati-visa-card__price">
							<?php echo esc_html( visati_format_aed( $price_aed ) ); ?>
							<?php if ( $price_usd ) : ?>
								<span>(~$<?php echo esc_html( number_format( (float) $price_usd, 0 ) ); ?>)</span>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<div class="visati-visa-card__meta">
						<?php if ( $duration ) : ?>
							<span><?php echo esc_html( $duration ); ?> days</span>
						<?php endif; ?>
						<?php if ( $entry_type ) : ?>
							<span><?php echo esc_html( ucfirst( $entry_type ) ); ?> entry</span>
						<?php endif; ?>
						<?php if ( $has_express ) : ?>
							<span>Express avail.</span>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $features ) && is_array( $features ) ) : ?>
						<ul class="visati-visa-card__features">
							<?php foreach ( array_slice( $features, 0, 4 ) as $f ) :
								$text = is_array( $f ) && isset( $f['feature'] ) ? $f['feature'] : ( is_string( $f ) ? $f : '' );
								if ( '' !== $text ) :
							?>
								<li><?php echo esc_html( $text ); ?></li>
							<?php
								endif;
							endforeach; ?>
						</ul>
					<?php endif; ?>

					<a href="<?php echo esc_url( $apply_url ); ?>" class="visati-btn">
						Apply Now
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
