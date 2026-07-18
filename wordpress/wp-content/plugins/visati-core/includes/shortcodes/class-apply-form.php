<?php
/**
 * [visati_apply_form] — renders the visa application wizard (stepper + fields),
 * matching the original Next.js design (connecting-line stepper, flag nationality dropdown).
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Apply_Form_Shortcode {

	/**
	 * Nationality list: ISO-3166 alpha-2 => label (flag via flagcdn.com/<code>.svg).
	 */
	const NATIONALITIES = array(
		'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AR' => 'Argentina', 'AU' => 'Australia',
		'AT' => 'Austria', 'BD' => 'Bangladesh', 'BE' => 'Belgium', 'BR' => 'Brazil', 'BG' => 'Bulgaria',
		'CA' => 'Canada', 'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia', 'HR' => 'Croatia',
		'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'EG' => 'Egypt', 'EE' => 'Estonia', 'FI' => 'Finland',
		'FR' => 'France', 'DE' => 'Germany', 'GR' => 'Greece', 'HU' => 'Hungary', 'IN' => 'India',
		'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IL' => 'Israel',
		'IT' => 'Italy', 'JP' => 'Japan', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya',
		'KW' => 'Kuwait', 'LV' => 'Latvia', 'LB' => 'Lebanon', 'LT' => 'Lithuania', 'LU' => 'Luxembourg',
		'MY' => 'Malaysia', 'MX' => 'Mexico', 'MA' => 'Morocco', 'NL' => 'Netherlands', 'NZ' => 'New Zealand',
		'NG' => 'Nigeria', 'NO' => 'Norway', 'PK' => 'Pakistan', 'PS' => 'Palestine', 'PH' => 'Philippines',
		'PL' => 'Poland', 'PT' => 'Portugal', 'QA' => 'Qatar', 'RO' => 'Romania', 'RU' => 'Russia',
		'SA' => 'Saudi Arabia', 'RS' => 'Serbia', 'SG' => 'Singapore', 'SK' => 'Slovakia', 'SI' => 'Slovenia',
		'ZA' => 'South Africa', 'KR' => 'South Korea', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SE' => 'Sweden',
		'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan', 'TH' => 'Thailand', 'TN' => 'Tunisia',
		'TR' => 'Turkey', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States',
		'VN' => 'Vietnam', 'YE' => 'Yemen',
	);

	/**
	 * Hook shortcode + asset registration.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_shortcode( 'visati_apply_form', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register assets (enqueued when shortcode renders).
	 *
	 * @return void
	 */
	public static function register_assets(): void {
		wp_register_script( 'visati-apply-form', VISATI_CORE_URL . 'assets/js/application-form.js', array(), VISATI_CORE_VERSION, true );
		wp_register_style( 'visati-apply', VISATI_CORE_URL . 'assets/css/apply.css', array(), VISATI_CORE_VERSION );
	}

	/**
	 * Render the wizard.
	 *
	 * @return string
	 */
	public static function render(): string {
		$visas    = get_posts(
			array(
				'post_type'      => 'visati_visa_type',
				'posts_per_page' => 50,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);
		$surcharge = Visati_Core_Settings::express_surcharge_aed();

		if ( ! wp_script_is( 'visati-apply-form', 'enqueued' ) ) {
			wp_enqueue_script( 'visati-apply-form' );
			wp_enqueue_style( 'visati-apply' );
			wp_localize_script(
				'visati-apply-form',
				'visatiApply',
				array(
					'restUrl' => esc_url_raw( rest_url( 'visati/v1' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'visas'   => array_reduce( $visas, function ( $carry, $v ) {
						$carry[ $v->post_name ] = array(
							'name'       => $v->post_title,
							'entry_type' => get_post_meta( $v->ID, '_visati_entry_type', true ) ?: 'single',
							'price_aed'  => (float) get_post_meta( $v->ID, '_visati_price_aed', true ),
							'price_usd'  => (float) get_post_meta( $v->ID, '_visati_price_usd', true ),
							'duration'   => get_post_meta( $v->ID, '_visati_duration_days', true ),
						);
						return $carry;
					}, array() ),
					'surcharge_aed' => (float) $surcharge,
					'surcharge_usd' => (float) Visati_Core_Settings::express_surcharge_usd(),
					'whatsapp_number' => sanitize_text_field( Visati_Core_Settings::get( 'whatsapp_number', '' ) ),
				)
			);
		}

		ob_start();
		?>
		<form id="visati-apply-form" class="visati-apply" novalidate>
			<!-- Stepper -->
			<div class="visati-stepper" aria-hidden="true">
				<div class="visati-stepper__line"></div>
				<div class="visati-stepper__progress" data-progress></div>
				<ol class="visati-stepper__items">
					<?php
					$labels = array( 'Visa Selection', 'Personal Details', 'Documents', 'Review & Submit' );
					foreach ( $labels as $i => $label ) :
						$n = $i + 1;
						?>
						<li class="visati-stepper__item<?php echo 1 === $n ? ' is-active' : ''; ?>" data-step-marker="<?php echo (int) $n; ?>">
							<span class="visati-stepper__circle"><span class="visati-stepper__num"><?php echo (int) $n; ?></span><svg class="visati-stepper__check" viewBox="0 0 24 24" width="14" height="14"><path fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
							<span class="visati-stepper__meta"><small>Step <?php echo (int) $n; ?></small><?php echo esc_html( $label ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>

			<!-- Step 1 -->
			<section class="visati-step is-current" data-step="1">
				<header class="visati-step__head">
					<small>Step 1 of 4</small>
					<h2>Choose your visa</h2>
					<p>Select a visa type and processing speed.</p>
				</header>
				<label class="visati-field"><?php esc_html_e( 'Visa type', 'visati-core' ); ?>
					<select name="visa_type_slug" required>
						<option value=""><?php esc_html_e( 'Select…', 'visati-core' ); ?></option>
						<?php foreach ( $visas as $v ) : ?>
							<option value="<?php echo esc_attr( $v->post_name ); ?>"><?php echo esc_html( $v->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<fieldset class="visati-tier">
					<legend>Processing</legend>
					<label><input type="radio" name="processing_tier" value="standard" checked> Standard</label>
					<label><input type="radio" name="processing_tier" value="express"> Express (+AED <?php echo esc_html( $surcharge ); ?>)</label>
				</fieldset>
			</section>

			<!-- Step 2 -->
			<section class="visati-step" data-step="2" hidden>
				<header class="visati-step__head">
					<small>Step 2 of 4</small>
					<h2>Personal details</h2>
					<p>Enter your details exactly as they appear in your passport.</p>
				</header>
				<div class="visati-grid">
					<label class="visati-field">Given name<input name="given_name" required placeholder="As in passport"></label>
					<label class="visati-field">Surname<input name="surname" required placeholder="As in passport"></label>

					<?php self::render_nationality_field(); ?>

					<label class="visati-field">Passport number<input name="passport_number" required placeholder="e.g. A1234567" class="visati-mono"></label>
					<label class="visati-field">Date of birth<input type="date" name="date_of_birth" required></label>
					<label class="visati-field">Passport expiry<input type="date" name="passport_expiry" required></label>
					<label class="visati-field">Travel date<input type="date" name="travel_date" required></label>
					<label class="visati-field">Email<input type="email" name="applicant_email" required placeholder="confirmation@example.com"></label>
				</div>

				<div class="visati-travelers" data-travelers hidden>
					<h3 class="visati-travelers__title">Additional Travelers</h3>
					<p class="visati-travelers__desc">Add family members or companions included in this multi-entry visa.</p>
					<div class="visati-travelers__list" data-travelers-list></div>
					<button type="button" class="visati-btn visati-btn--outline visati-travelers__add" data-travelers-add>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
						Add Traveler
					</button>
				</div>
			</section>

			<!-- Step 3 -->
			<section class="visati-step" data-step="3" hidden>
				<header class="visati-step__head">
					<small>Step 3 of 4</small>
					<h2>Upload documents</h2>
					<p>PDF, JPG, or PNG. Max 5 MB each.</p>
				</header>
				<div class="visati-doc-upload">
					<div class="visati-field visati-doc-upload__field">
						<span class="visati-field__label">Passport copy <span class="visati-field__required">*</span></span>
						<div class="visati-doc-upload__box" data-doc-box="passport_copy">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
							<span class="visati-doc-upload__text">Choose file or drag here</span>
							<span class="visati-doc-upload__name" data-doc-name></span>
							<input type="file" name="doc_passport" accept=".pdf,.jpg,.jpeg,.png" data-doc-type="passport_copy" hidden>
						</div>
					</div>
					<div class="visati-field visati-doc-upload__field">
						<span class="visati-field__label">Photo <span class="visati-field__required">*</span></span>
						<div class="visati-doc-upload__box" data-doc-box="photo">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
							<span class="visati-doc-upload__text">Choose file or drag here</span>
							<span class="visati-doc-upload__name" data-doc-name></span>
							<input type="file" name="doc_photo" accept=".pdf,.jpg,.jpeg,.png" data-doc-type="photo" hidden>
						</div>
					</div>
					<div class="visati-field visati-doc-upload__field">
						<span class="visati-field__label">Supporting document <span class="visati-field__optional">(optional)</span></span>
						<div class="visati-doc-upload__box" data-doc-box="supporting">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
							<span class="visati-doc-upload__text">Choose file or drag here</span>
							<span class="visati-doc-upload__name" data-doc-name></span>
							<input type="file" name="doc_supporting" accept=".pdf,.jpg,.jpeg,.png" data-doc-type="supporting" hidden>
						</div>
					</div>
				</div>
				<ul class="visati-doc-list"></ul>
				<p class="visati-doc-note">Passport must be valid for 6+ months. Photo must have white background and clearly show your face.</p>
			</section>

			<!-- Step 4 -->
			<section class="visati-step" data-step="4" hidden>
				<header class="visati-step__head">
					<small>Step 4 of 4</small>
					<h2>Review &amp; submit</h2>
					<p>You'll proceed to secure payment after submitting.</p>
				</header>
				<dl class="visati-review"></dl>
				<div class="visati-review__actions">
					<a href="#" class="visati-btn visati-btn--whatsapp" data-action="whatsapp" target="_blank" rel="noopener">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
						Chat on WhatsApp
					</a>
				</div>
			</section>

			<div class="visati-nav">
				<button type="button" class="visati-btn visati-btn--ghost" data-action="back" hidden>Back</button>
				<button type="button" class="visati-btn" data-action="next">Continue</button>
			</div>
			<p class="visati-error" role="alert" hidden></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the nationality combobox (button + hidden input + searchable flag list).
	 *
	 * @return void
	 */
	private static function render_nationality_field(): void {
		?>
		<div class="visati-field visati-combobox" data-combobox>
			<span class="visati-field__label">Nationality</span>
			<input type="hidden" name="nationality" value="" required>
			<button type="button" class="visati-combobox__button" data-combobox-button>
				<span class="visati-combobox__flag" data-combobox-flag></span>
				<span class="visati-combobox__text" data-combobox-text>Select nationality</span>
				<svg class="visati-combobox__chevron" viewBox="0 0 24 24" width="16" height="16"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
			</button>
			<div class="visati-combobox__panel" data-combobox-panel hidden>
				<input type="text" class="visati-combobox__search" data-combobox-search placeholder="Search…">
				<ul class="visati-combobox__list" data-combobox-list>
					<?php foreach ( self::NATIONALITIES as $code => $label ) : ?>
						<li role="option" data-value="<?php echo esc_attr( $label ); ?>" data-flag="<?php echo esc_attr( strtolower( $code ) ); ?>">
							<img src="https://flagcdn.com/<?php echo esc_attr( strtolower( $code ) ); ?>.svg" alt="" width="20" height="14" loading="lazy">
							<span><?php echo esc_html( $label ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}
}
