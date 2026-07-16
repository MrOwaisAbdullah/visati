<?php
/**
 * [visati_quick_apply] — nationality + visa select that deep-links to /apply.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Quick_Apply_Shortcode {

	public static function init(): void {
		add_shortcode( 'visati_quick_apply', array( __CLASS__, 'render' ) );
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
		<div class="visati-quick-apply">
			<div class="visati-quick-apply__row">
				<select id="qa-nationality" aria-label="Nationality">
					<option value="">Select nationality</option>
					<?php foreach ( $countries as $code => $name ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<select id="qa-visa" aria-label="Visa type">
					<option value="">Select visa type</option>
					<?php foreach ( $visas as $v ) : ?>
						<option value="<?php echo esc_attr( $v->post_name ); ?>"><?php echo esc_html( $v->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="visati-btn" id="qa-submit">
					Quick Apply
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
				</button>
			</div>
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
