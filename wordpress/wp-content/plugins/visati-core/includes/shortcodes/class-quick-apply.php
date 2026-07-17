<?php
/**
 * [visati_quick_apply] — hero card with nationality, visa type, travel date → deep-links to /apply.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Quick_Apply_Shortcode {

	public static function init(): void {
		add_shortcode( 'visati_quick_apply', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets(): void {
		wp_enqueue_style( 'visati-apply', VISATI_CORE_URL . 'assets/css/apply.css', array(), VISATI_CORE_VERSION );
	}

	public static function render(): string {
		$visas = get_posts(
			array(
				'post_type'      => 'visati_visa_type',
				'posts_per_page' => 50,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		$countries = self::get_popular_countries();

		ob_start();
		?>
		<div class="visati-qa-card">
			<div class="visati-qa-card__header">
				<div>
					<span class="visati-qa-card__eyebrow">QUICK APPLY</span>
					<h3 class="visati-qa-card__title">Check eligibility in 30 sec</h3>
				</div>
				<div class="visati-qa-card__icon">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
				</div>
			</div>

			<div class="visati-qa-card__field">
				<label class="visati-qa-card__label" for="qa-nationality">Nationality</label>
				<select id="qa-nationality" class="visati-qa-card__select">
					<option value="">Select nationality</option>
					<?php foreach ( $countries as $code => $name ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="visati-qa-card__field">
				<label class="visati-qa-card__label" for="qa-visa">Visa Type</label>
				<select id="qa-visa" class="visati-qa-card__select">
					<option value="">Select type</option>
					<?php foreach ( $visas as $v ) : ?>
						<option value="<?php echo esc_attr( $v->post_name ); ?>"><?php echo esc_html( $v->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="visati-qa-card__field">
				<label class="visati-qa-card__label" for="qa-date">Travel Date</label>
				<input type="date" id="qa-date" class="visati-qa-card__input" placeholder="mm/dd/yyyy">
			</div>

			<button type="button" class="visati-btn visati-qa-card__submit" id="qa-submit">
				Continue
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
			</button>

			<p class="visati-qa-card__secure">
				<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
				Encrypted &amp; secure &middot; No payment now
			</p>
		</div>
		<script>
		(function(){
			var btn = document.getElementById('qa-submit');
			if(!btn) return;
			btn.addEventListener('click', function(){
				var visa = document.getElementById('qa-visa');
				var nat  = document.getElementById('qa-nationality');
				var slug = visa && visa.value;
				if(!slug){ visa && visa.focus(); return; }
				var url = '<?php echo esc_url( home_url( '/apply' ) ); ?>?visa=' + encodeURIComponent(slug);
				if(nat && nat.value) url += '&nationality=' + encodeURIComponent(nat.value);
				window.location.href = url;
			});
		})();
		</script>
		<?php
		return (string) ob_get_clean();
	}

	private static function get_popular_countries(): array {
		return array(
			'IN' => 'India',
			'PK' => 'Pakistan',
			'BD' => 'Bangladesh',
			'LK' => 'Sri Lanka',
			'PH' => 'Philippines',
			'NP' => 'Nepal',
			'EG' => 'Egypt',
			'GB' => 'United Kingdom',
			'US' => 'United States',
			'CA' => 'Canada',
			'DE' => 'Germany',
			'FR' => 'France',
			'AU' => 'Australia',
			'CN' => 'China',
			'RU' => 'Russia',
			'TR' => 'Turkey',
			'SA' => 'Saudi Arabia',
			'JO' => 'Jordan',
			'LB' => 'Lebanon',
			'AE' => 'UAE',
		);
	}
}
