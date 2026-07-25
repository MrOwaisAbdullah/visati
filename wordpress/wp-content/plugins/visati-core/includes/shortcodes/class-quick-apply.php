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
	}

	public static function enqueue_assets(): void {
		wp_enqueue_style( 'visati-apply', VISATI_CORE_URL . 'assets/css/apply.css', array(), visati_asset_version( 'assets/css/apply.css' ) );
	}

	public static function render(): string {
		wp_enqueue_style( 'visati-apply', VISATI_CORE_URL . 'assets/css/apply.css', array(), visati_asset_version( 'assets/css/apply.css' ) );

		$visas = get_posts(
			array(
				'post_type'      => 'visati_visa_type',
				'posts_per_page' => 50,
				'no_found_rows'  => true,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		$countries = visati_get_nationalities();

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
				<label class="visati-qa-card__label" for="qa-nationality-button">Nationality</label>
				<div class="visati-combobox visati-qa-card__combobox" data-qa-combobox>
					<input type="hidden" id="qa-nationality" value="">
					<button type="button" id="qa-nationality-button" class="visati-combobox__button" data-qa-combobox-button aria-haspopup="listbox" aria-expanded="false" aria-label="Select nationality">
						<span class="visati-combobox__flag" data-qa-combobox-flag></span>
						<span class="visati-combobox__text" data-qa-combobox-text>Select nationality</span>
						<svg class="visati-combobox__chevron" viewBox="0 0 24 24" width="16" height="16"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
					</button>
					<div class="visati-combobox__panel" data-qa-combobox-panel hidden>
						<ul class="visati-combobox__list" data-qa-combobox-list role="listbox" aria-label="Nationalities">
							<?php foreach ( $countries as $code => $name ) : ?>
								<li role="option" tabindex="-1" data-value="<?php echo esc_attr( $code ); ?>" data-label="<?php echo esc_attr( $name ); ?>" data-flag="<?php echo esc_attr( strtolower( $code ) ); ?>">
									<img src="<?php echo esc_url( VISATI_CORE_URL . 'assets/images/flags/' . strtolower( $code ) . '.svg' ); ?>" alt="" width="20" height="14">
									<span><?php echo esc_html( $name ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
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

			/* Nationality flag combobox — standalone (this widget can appear on pages
			   that don't load the full apply-form script, e.g. the homepage). */
			var cb = document.querySelector('[data-qa-combobox]');
			if(!cb) return;
			var button = cb.querySelector('[data-qa-combobox-button]');
			var panel  = cb.querySelector('[data-qa-combobox-panel]');
			var list   = cb.querySelector('[data-qa-combobox-list]');
			var hidden = document.getElementById('qa-nationality');
			var textEl = cb.querySelector('[data-qa-combobox-text]');
			var flagEl = cb.querySelector('[data-qa-combobox-flag]');

			function close(){
				cb.classList.remove('is-open');
				panel.hidden = true;
				button.setAttribute('aria-expanded', 'false');
			}
			function open(){
				cb.classList.add('is-open');
				panel.hidden = false;
				button.setAttribute('aria-expanded', 'true');
			}
			function selectItem(li){
				var val  = li.getAttribute('data-value');
				var label = li.getAttribute('data-label');
				var flag = li.getAttribute('data-flag');
				hidden.value = val;
				textEl.textContent = label;
				button.classList.add('is-filled');
				flagEl.innerHTML = flag ? '<img src="<?php echo esc_url( VISATI_CORE_URL . "assets/images/flags/" ); ?>' + flag + '.svg" alt="" width="20" height="14">' : '';
				Array.prototype.forEach.call(list.querySelectorAll('li'), function(x){
					x.classList.toggle('is-selected', x === li);
				});
				close();
				button.focus();
			}
			button.addEventListener('click', function(e){
				e.preventDefault();
				panel.hidden ? open() : close();
			});
			list.addEventListener('click', function(e){
				var li = e.target.closest ? e.target.closest('li') : null;
				if(li) selectItem(li);
			});
			document.addEventListener('click', function(e){
				if(!cb.contains(e.target)) close();
			});
		})();
		</script>
		<?php
		return (string) ob_get_clean();
	}

}
