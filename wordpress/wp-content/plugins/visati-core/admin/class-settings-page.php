<?php
/**
 * Admin Settings page — non-secret, client-editable config (WhatsApp, contact,
 * announcement bar, tracking, social). Saved to the `visati_core_settings` option.
 *
 * Secrets (Stripe/R2/SMTP) are NEVER edited here — they live in wp-config.php.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Admin_Settings {

	const CAP   = 'manage_options';
	const SLUG  = 'visati-core';
	const NONCE = 'visati_core_settings_nonce';

	/**
	 * Hook admin menu + save handler.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Register the top-level Visati menu (settings page).
	 *
	 * @return void
	 */
	public static function add_menu(): void {
		add_menu_page(
			__( 'Visati Settings', 'visati-core' ),
			__( 'Visati', 'visati-core' ),
			self::CAP,
			self::SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-palmtree',
			26
		);
	}

	/**
	 * Handle the settings form POST (nonce + capability + sanitize + PRG redirect).
	 *
	 * @return void
	 */
	public static function handle_save(): void {
		if ( ! isset( $_POST['visati_settings_submit'] ) ) {
			return;
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'visati-core' ) );
		}
		check_admin_referer( self::NONCE );

		$opts = get_option( Visati_Core_Settings::OPTION, array() );

		// WhatsApp + contact.
		$opts['whatsapp_number'] = sanitize_text_field( wp_unslash( $_POST['whatsapp_number'] ?? '' ) );
		$opts['whatsapp_text']   = sanitize_text_field( wp_unslash( $_POST['whatsapp_text'] ?? '' ) );
		$opts['contact_email']   = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
		$opts['contact_phone']   = sanitize_text_field( wp_unslash( $_POST['contact_phone'] ?? '' ) );

		// Announcement bar.
		$opts['announcement_active']     = isset( $_POST['announcement_active'] ) ? 1 : 0;
		$opts['announcement_text']       = sanitize_text_field( wp_unslash( $_POST['announcement_text'] ?? '' ) );
		$opts['announcement_cta_label']  = sanitize_text_field( wp_unslash( $_POST['announcement_cta_label'] ?? '' ) );
		$opts['announcement_cta_url']    = esc_url_raw( wp_unslash( $_POST['announcement_cta_url'] ?? '' ) );
		$opts['announcement_bg_color']   = sanitize_hex_color( wp_unslash( $_POST['announcement_bg_color'] ?? '' ) ) ?: '#C9A84C';
		$opts['announcement_text_color'] = sanitize_hex_color( wp_unslash( $_POST['announcement_text_color'] ?? '' ) ) ?: '#0A1628';
		$opts['announcement_end_date']   = sanitize_text_field( wp_unslash( $_POST['announcement_end_date'] ?? '' ) );

		// Tracking + social.
		$opts['ga4_id']           = sanitize_text_field( wp_unslash( $_POST['ga4_id'] ?? '' ) );
		$opts['social_instagram'] = esc_url_raw( wp_unslash( $_POST['social_instagram'] ?? '' ) );
		$opts['social_facebook']  = esc_url_raw( wp_unslash( $_POST['social_facebook'] ?? '' ) );
		$opts['social_linkedin']  = esc_url_raw( wp_unslash( $_POST['social_linkedin'] ?? '' ) );

		update_option( Visati_Core_Settings::OPTION, $opts );

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$s = array( __CLASS__, 'setting' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Visati Settings', 'visati-core' ); ?></h1>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'visati-core' ); ?></p></div>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'Client-editable content. Secrets (Stripe, R2, SMTP) are configured in wp-config.php, not here.', 'visati-core' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE ); ?>

				<h2><?php esc_html_e( 'WhatsApp & Contact', 'visati-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="whatsapp_number"><?php esc_html_e( 'WhatsApp number', 'visati-core' ); ?></label></th>
						<td><input name="whatsapp_number" id="whatsapp_number" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'whatsapp_number' ) ); ?>" placeholder="971500000000"></td></tr>
					<tr><th><label for="whatsapp_text"><?php esc_html_e( 'WhatsApp default message', 'visati-core' ); ?></label></th>
						<td><input name="whatsapp_text" id="whatsapp_text" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'whatsapp_text' ) ); ?>"></td></tr>
					<tr><th><label for="contact_email"><?php esc_html_e( 'Contact email', 'visati-core' ); ?></label></th>
						<td><input name="contact_email" id="contact_email" type="email" class="regular-text" value="<?php echo esc_attr( $s( 'contact_email' ) ); ?>"></td></tr>
					<tr><th><label for="contact_phone"><?php esc_html_e( 'Contact phone', 'visati-core' ); ?></label></th>
						<td><input name="contact_phone" id="contact_phone" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'contact_phone' ) ); ?>"></td></tr>
				</table>

				<h2><?php esc_html_e( 'Announcement bar', 'visati-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Active', 'visati-core' ); ?></th>
						<td><label><input type="checkbox" name="announcement_active" value="1" <?php checked( $s( 'announcement_active' ), 1 ); ?>> <?php esc_html_e( 'Show the announcement bar', 'visati-core' ); ?></label></td></tr>
					<tr><th><label for="announcement_text"><?php esc_html_e( 'Text', 'visati-core' ); ?></label></th>
						<td><input name="announcement_text" id="announcement_text" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'announcement_text' ) ); ?>"></td></tr>
					<tr><th><label for="announcement_cta_label"><?php esc_html_e( 'CTA label', 'visati-core' ); ?></label></th>
						<td><input name="announcement_cta_label" id="announcement_cta_label" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'announcement_cta_label' ) ); ?>"></td></tr>
					<tr><th><label for="announcement_cta_url"><?php esc_html_e( 'CTA URL', 'visati-core' ); ?></label></th>
						<td><input name="announcement_cta_url" id="announcement_cta_url" type="url" class="regular-text" value="<?php echo esc_attr( $s( 'announcement_cta_url' ) ); ?>"></td></tr>
					<tr><th><label for="announcement_bg_color"><?php esc_html_e( 'Background color', 'visati-core' ); ?></label></th>
						<td><input name="announcement_bg_color" id="announcement_bg_color" type="text" class="visati-color" value="<?php echo esc_attr( $s( 'announcement_bg_color' ) ); ?>"></td></tr>
					<tr><th><label for="announcement_text_color"><?php esc_html_e( 'Text color', 'visati-core' ); ?></label></th>
						<td><input name="announcement_text_color" id="announcement_text_color" type="text" class="visati-color" value="<?php echo esc_attr( $s( 'announcement_text_color' ) ); ?>"></td></tr>
					<tr><th><label for="announcement_end_date"><?php esc_html_e( 'End date (optional)', 'visati-core' ); ?></label></th>
						<td><input name="announcement_end_date" id="announcement_end_date" type="date" value="<?php echo esc_attr( $s( 'announcement_end_date' ) ); ?>"></td></tr>
				</table>

				<h2><?php esc_html_e( 'Tracking & Social', 'visati-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="ga4_id"><?php esc_html_e( 'Google Analytics 4 ID', 'visati-core' ); ?></label></th>
						<td><input name="ga4_id" id="ga4_id" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'ga4_id' ) ); ?>" placeholder="G-XXXXXXXXXX"></td></tr>
					<tr><th><label for="social_instagram"><?php esc_html_e( 'Instagram URL', 'visati-core' ); ?></label></th>
						<td><input name="social_instagram" id="social_instagram" type="url" class="regular-text" value="<?php echo esc_attr( $s( 'social_instagram' ) ); ?>"></td></tr>
					<tr><th><label for="social_facebook"><?php esc_html_e( 'Facebook URL', 'visati-core' ); ?></label></th>
						<td><input name="social_facebook" id="social_facebook" type="url" class="regular-text" value="<?php echo esc_attr( $s( 'social_facebook' ) ); ?>"></td></tr>
					<tr><th><label for="social_linkedin"><?php esc_html_e( 'LinkedIn URL', 'visati-core' ); ?></label></th>
						<td><input name="social_linkedin" id="social_linkedin" type="url" class="regular-text" value="<?php echo esc_attr( $s( 'social_linkedin' ) ); ?>"></td></tr>
				</table>

				<?php submit_button( __( 'Save settings', 'visati-core' ), 'primary', 'visati_settings_submit' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Helper to read a setting value for the form.
	 *
	 * @param string $key Setting key.
	 * @return string|int
	 */
	public static function setting( string $key ) {
		return Visati_Core_Settings::get( $key, '' );
	}
}
