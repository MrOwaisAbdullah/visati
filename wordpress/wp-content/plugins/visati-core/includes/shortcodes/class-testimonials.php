<?php
/**
 * [visati_testimonials] — horizontal scrolling testimonial cards from the CPT.
 *
 * Usage: [visati_testimonials] or [visati_testimonials count="6"]
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

	final class Visati_Core_Testimonials_Shortcode {

		public static function init(): void {
			add_shortcode( 'visati_testimonials', array( __CLASS__, 'render' ) );
			add_shortcode( 'visati_rating_badge', array( __CLASS__, 'render_rating_badge' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		}

		/**
		 * [visati_rating_badge] — "★★★★★ 4.9 / Based on 100+ reviews..." summary
		 * badge. Uses the same plain-Unicode star character the testimonial cards
		 * already use, NOT an icon font — Elementor's native Star Rating widget
		 * renders stars via a private-use-area icon-font codepoint that silently
		 * renders blank on devices where that font fails to load.
		 */
		public static function render_rating_badge( $atts ): string {
			$atts = shortcode_atts(
				array(
					'rating' => '4.9',
					'note'   => 'Based on 100+ reviews on Trustpilot &amp; Google',
				),
				$atts,
				'visati_rating_badge'
			);

			ob_start();
			?>
			<div class="visati-rating-badge">
				<div class="visati-rating-badge__row">
					<span class="visati-rating-badge__stars" aria-hidden="true">★★★★★</span>
					<span class="visati-rating-badge__value"><?php echo esc_html( $atts['rating'] ); ?></span>
				</div>
				<p class="visati-rating-badge__note"><?php echo wp_kses_post( $atts['note'] ); ?></p>
			</div>
			<?php
			return (string) ob_get_clean();
		}

		public static function register_assets(): void {
			wp_register_script( 'visati-testimonials', VISATI_CORE_URL . 'assets/js/testimonials.js', array(), visati_asset_version( 'assets/js/testimonials.js' ), true );
		}

	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'count' => 12,
			),
			$atts,
			'visati_testimonials'
		);

		$testimonials = get_posts(
			array(
				'post_type'      => 'visati_testimonial',
				'posts_per_page' => (int) $atts['count'],
				'no_found_rows'  => true,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		if ( empty( $testimonials ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="visati-testimonials-wrapper">
			<button class="visati-testimonials-arrow visati-testimonials-arrow--prev" aria-label="Previous" type="button">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
			</button>
			<div class="visati-testimonials-track">
				<?php foreach ( $testimonials as $t ) :
					$country_code = get_post_meta( $t->ID, '_visati_country', true );
					$rating       = (int) get_post_meta( $t->ID, '_visati_rating', true );
					$review_text  = get_post_meta( $t->ID, '_visati_review_text', true );
					$name         = $t->post_title;
					$flag_code    = strtolower( trim( $country_code ) );
					$country_name = self::code_to_name( $country_code );
				?>
					<div class="visati-testimonial-card">
						<?php if ( $rating ) : ?>
							<div class="visati-testimonial-card__stars" aria-label="<?php echo esc_attr( $rating ); ?> out of 5 stars">
								<?php echo esc_html( str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ) ); ?>
							</div>
						<?php endif; ?>

						<?php if ( $review_text ) : ?>
							<p class="visati-testimonial-card__text">"<?php echo esc_html( $review_text ); ?>"</p>
						<?php else : ?>
							<p class="visati-testimonial-card__text">"<?php echo esc_html( $t->post_content ); ?>"</p>
						<?php endif; ?>

						<div class="visati-testimonial-card__author">
							<?php if ( $flag_code && 2 === strlen( $flag_code ) && file_exists( VISATI_CORE_DIR . 'assets/images/flags/' . $flag_code . '.svg' ) ) : ?>
								<img src="<?php echo esc_url( VISATI_CORE_URL . 'assets/images/flags/' . $flag_code . '.svg' ); ?>" alt="" width="22" height="16" class="visati-testimonial-card__flag">
							<?php endif; ?>
							<div>
								<div class="visati-testimonial-card__name"><?php echo esc_html( $name ); ?></div>
								<?php if ( $country_name ) : ?>
									<div class="visati-testimonial-card__country"><?php echo esc_html( $country_name ); ?></div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<button class="visati-testimonials-arrow visati-testimonials-arrow--next" aria-label="Next" type="button">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
			</button>
		</div>
		<?php
		if ( ! wp_script_is( 'visati-testimonials', 'enqueued' ) ) {
			wp_enqueue_script( 'visati-testimonials' );
		}
		return (string) ob_get_clean();
	}

	private static function code_to_name( string $code ): string {
		$map = array(
			'AE' => 'UAE', 'IN' => 'India', 'PK' => 'Pakistan', 'BD' => 'Bangladesh',
			'LK' => 'Sri Lanka', 'PH' => 'Philippines', 'NP' => 'Nepal', 'EG' => 'Egypt',
			'GB' => 'United Kingdom', 'US' => 'United States', 'CA' => 'Canada',
			'DE' => 'Germany', 'FR' => 'France', 'AU' => 'Australia', 'CN' => 'China',
			'RU' => 'Russia', 'TR' => 'Turkey', 'SA' => 'Saudi Arabia', 'JO' => 'Jordan',
			'LB' => 'Lebanon',
		);
		return isset( $map[ strtoupper( $code ) ] ) ? $map[ strtoupper( $code ) ] : '';
	}
}
