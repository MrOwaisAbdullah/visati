<?php
/**
 * [visati_apply_form] — renders the 5-step visa application wizard.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Apply_Form_Shortcode {

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
	 * Register (not enqueue) assets — enqueued only when the shortcode renders.
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

		$visas = get_posts(
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
			<ol class="visati-step-indicator">
				<li class="is-active"><span>1</span><?php esc_html_e( 'Visa', 'visati-core' ); ?></li>
				<li><span>2</span><?php esc_html_e( 'Details', 'visati-core' ); ?></li>
				<li><span>3</span><?php esc_html_e( 'Documents', 'visati-core' ); ?></li>
				<li><span>4</span><?php esc_html_e( 'Review', 'visati-core' ); ?></li>
			</ol>

			<section class="visati-step" data-step="1">
				<h2><?php esc_html_e( 'Choose your visa', 'visati-core' ); ?></h2>
				<label class="visati-field"><?php esc_html_e( 'Visa type', 'visati-core' ); ?>
					<select name="visa_type_slug" required>
						<option value=""><?php esc_html_e( 'Select…', 'visati-core' ); ?></option>
						<?php foreach ( $visas as $v ) : ?>
							<option value="<?php echo esc_attr( $v->post_name ); ?>"><?php echo esc_html( $v->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<fieldset class="visati-tier">
					<legend><?php esc_html_e( 'Processing', 'visati-core' ); ?></legend>
					<label><input type="radio" name="processing_tier" value="standard" checked> <?php esc_html_e( 'Standard', 'visati-core' ); ?></label>
					<label><input type="radio" name="processing_tier" value="express"> <?php echo esc_html( sprintf( __( 'Express (+AED %d)', 'visati-core' ), $surcharge ) ); ?></label>
				</fieldset>
			</section>

			<section class="visati-step" data-step="2" hidden>
				<h2><?php esc_html_e( 'Your details', 'visati-core' ); ?></h2>
				<div class="visati-grid">
					<label class="visati-field"><?php esc_html_e( 'Nationality', 'visati-core' ); ?><input name="nationality" required></label>
					<label class="visati-field"><?php esc_html_e( 'Given name', 'visati-core' ); ?><input name="given_name" required></label>
					<label class="visati-field"><?php esc_html_e( 'Surname', 'visati-core' ); ?><input name="surname" required></label>
					<label class="visati-field"><?php esc_html_e( 'Passport number', 'visati-core' ); ?><input name="passport_number" required></label>
					<label class="visati-field"><?php esc_html_e( 'Date of birth', 'visati-core' ); ?><input type="date" name="date_of_birth" required></label>
					<label class="visati-field"><?php esc_html_e( 'Passport expiry', 'visati-core' ); ?><input type="date" name="passport_expiry" required></label>
					<label class="visati-field"><?php esc_html_e( 'Travel date', 'visati-core' ); ?><input type="date" name="travel_date" required></label>
					<label class="visati-field"><?php esc_html_e( 'Email', 'visati-core' ); ?><input type="email" name="applicant_email"></label>
				</div>
			</section>

			<section class="visati-step" data-step="3" hidden>
				<h2><?php esc_html_e( 'Upload documents', 'visati-core' ); ?></h2>
				<p class="visati-help"><?php esc_html_e( 'PDF, JPG, or PNG. Max 5 MB each.', 'visati-core' ); ?></p>
				<label class="visati-field"><?php esc_html_e( 'Passport copy', 'visati-core' ); ?>
					<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" data-doc-type="passport_copy">
				</label>
				<label class="visati-field"><?php esc_html_e( 'Photo', 'visati-core' ); ?>
					<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" data-doc-type="photo">
				</label>
				<ul class="visati-doc-list"></ul>
			</section>

			<section class="visati-step" data-step="4" hidden>
				<h2><?php esc_html_e( 'Review & submit', 'visati-core' ); ?></h2>
				<dl class="visati-review"></dl>
				<p class="visati-help"><?php esc_html_e( "You'll proceed to secure payment after submitting.", 'visati-core' ); ?></p>
			</section>

			<div class="visati-nav">
				<button type="button" class="visati-btn visati-btn--outline" data-action="back" hidden><?php esc_html_e( 'Back', 'visati-core' ); ?></button>
				<button type="button" class="visati-btn" data-action="next"><?php esc_html_e( 'Continue', 'visati-core' ); ?></button>
				<button type="submit" class="visati-btn visati-btn--navy" data-action="submit" hidden><?php esc_html_e( 'Submit & Pay', 'visati-core' ); ?></button>
			</div>
			<p class="visati-error" role="alert" hidden></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}
}
