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
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		}

		public static function register_assets(): void {
			wp_register_script( 'visati-testimonials', VISATI_CORE_URL . 'assets/js/testimonials.js', array(), VISATI_CORE_VERSION, true );
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
					$flag         = self::code_to_flag( $country_code );
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
							<?php if ( $flag ) : ?>
								<span class="visati-testimonial-card__flag" aria-hidden="true"><?php echo esc_html( $flag ); ?></span>
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

	private static function code_to_flag( string $code ): string {
		$code = strtoupper( trim( $code ) );
		if ( 2 !== strlen( $code ) ) {
			return '';
		}
		$offset = 0x1F1E6 - 65; // Regional Indicator Symbol Letter A.
		$flag   = '';
		for ( $i = 0; $i < 2; $i++ ) {
			$flag .= mb_chr( ord( $code[ $i ] ) + $offset );
		}
		return $flag;
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
