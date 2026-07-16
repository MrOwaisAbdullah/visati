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
		if ( ! wp_script_is( 'visati-apply-form', 'enqueued' ) ) {
			wp_enqueue_script( 'visati-apply-form' );
			wp_enqueue_style( 'visati-apply' );
			wp_localize_script(
				'visati-apply-form',
				'visatiApply',
				array(
					'restUrl' => esc_url_raw( rest_url( 'visati/v1' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}

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
					<label class="visati-field visati-grid__full">Email<input type="email" name="applicant_email" placeholder="confirmation@example.com"></label>
				</div>
			</section>

			<!-- Step 3 -->
			<section class="visati-step" data-step="3" hidden>
				<header class="visati-step__head">
					<small>Step 3 of 4</small>
					<h2>Upload documents</h2>
					<p>PDF, JPG, or PNG. Max 5 MB each.</p>
				</header>
				<label class="visati-field">Passport copy<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" data-doc-type="passport_copy"></label>
				<label class="visati-field">Photo<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" data-doc-type="photo"></label>
				<ul class="visati-doc-list"></ul>
			</section>

			<!-- Step 4 -->
			<section class="visati-step" data-step="4" hidden>
				<header class="visati-step__head">
					<small>Step 4 of 4</small>
					<h2>Review &amp; submit</h2>
					<p>You'll proceed to secure payment after submitting.</p>
				</header>
				<dl class="visati-review"></dl>
			</section>

			<div class="visati-nav">
				<button type="button" class="visati-btn visati-btn--ghost" data-action="back" hidden>Back</button>
				<button type="button" class="visati-btn visati-btn--gold" data-action="next">Continue</button>
				<button type="submit" class="visati-btn visati-btn--navy" data-action="submit" hidden>Submit &amp; Pay</button>
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
