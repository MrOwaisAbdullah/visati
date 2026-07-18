<?php
/**
 * [visati_faq] — searchable FAQ accordion from the CPT.
 *
 * Usage: [visati_faq] or [visati_faq category="visa-types" count="20"]
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_FAQ_Shortcode {

	public static function init(): void {
		add_shortcode( 'visati_faq', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets(): void {
		wp_register_script( 'visati-faq', VISATI_CORE_URL . 'assets/js/faq.js', array(), VISATI_CORE_VERSION, true );
	}

	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'category' => '',
				'count'    => 50,
			),
			$atts,
			'visati_faq'
		);

		$args = array(
			'post_type'      => 'visati_faq',
			'posts_per_page' => (int) $atts['count'],
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$faqs = get_posts( $args );

		if ( empty( $faqs ) ) {
			return '<p class="visati-faq-empty">No FAQ items found.</p>';
		}

		// Collect unique categories.
		$categories = array();
		foreach ( $faqs as $faq ) {
			$cat = get_post_meta( $faq->ID, '_visati_faq_category', true );
			if ( $cat && ! in_array( $cat, $categories, true ) ) {
				$categories[] = $cat;
			}
		}
		sort( $categories );

		ob_start();
		?>

		<div class="visati-faq-layout">
			<div class="visati-faq-heading">
				<p class="visati-faq-label">FAQ</p>
				<h2 class="visati-faq-title">Questions,<br>answered.</h2>
				<p class="visati-faq-desc">Can't find what you're looking for? Our team is on WhatsApp, 24/7.</p>
				<a href="/faq" class="visati-faq-cta">All FAQs →</a>
			</div>

			<div class="visati-faq-right">
				<?php if ( count( $categories ) > 1 ) : ?>
					<div class="visati-faq-categories" role="tablist" aria-label="FAQ categories">
						<button class="visati-faq-cat-btn is-active" data-category="all" role="tab" aria-selected="true">All</button>
						<?php foreach ( $categories as $cat ) : ?>
							<button class="visati-faq-cat-btn" data-category="<?php echo esc_attr( $cat ); ?>" role="tab" aria-selected="false">
								<?php echo esc_html( ucfirst( str_replace( '-', ' ', $cat ) ) ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<input type="search" class="visati-faq-search" placeholder="Search FAQ..." aria-label="Search FAQ">

				<div class="visati-faq-list" role="list">
					<?php foreach ( $faqs as $faq ) :
						$question = get_post_meta( $faq->ID, '_visati_faq_question', true ) ?: $faq->post_title;
						$answer   = get_post_meta( $faq->ID, '_visati_faq_answer', true );
						if ( ! $answer ) {
							$answer = apply_filters( 'the_content', $faq->post_content );
						}
						$cat = get_post_meta( $faq->ID, '_visati_faq_category', true );
					?>
						<div class="visati-faq-item" data-category="<?php echo esc_attr( $cat ); ?>" role="listitem">
							<button class="visati-faq-item__q" aria-expanded="false">
								<span><?php echo esc_html( $question ); ?></span>
							</button>
							<div class="visati-faq-item__a" role="region">
								<?php echo wp_kses_post( $answer ); ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="visati-faq-empty" hidden>No results found.</div>
			</div>
		</div>

		<?php
		if ( ! wp_script_is( 'visati-faq', 'enqueued' ) ) {
			wp_enqueue_script( 'visati-faq' );
		}

		return (string) ob_get_clean();
	}
}
