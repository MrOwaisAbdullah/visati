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
	const SLUG  = 'visati-settings';
	const NONCE = 'visati_core_settings_nonce';

	/**
	 * Settings page is registered as a submenu by Visati_Core_Admin_Dashboard.
	 * We only hook the save handler here.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue WP color picker + media uploader on our settings page.
	 */
	public static function enqueue_assets( string $hook ): void {
		// Only load color picker + media on the Visati settings page.
		if ( 'visati_page_visati-settings' !== $hook && 'toplevel_page_visati-settings' !== $hook ) {
			return;
		}

		// Color picker.
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Media uploader.
		wp_enqueue_media();
		wp_enqueue_script( 'jquery' );

		// Inline init script for color pickers + media uploader.
		wp_add_inline_script( 'wp-color-picker', "
			jQuery(document).ready(function($) {
				// Color pickers
				$('.visati-color-picker').wpColorPicker();

				// Media uploader for logo
				$(document).on('click', '.visati-upload-btn', function(e) {
					e.preventDefault();
					var button = $(this);
					var target = button.data('target');
					var frame = wp.media({
						title: 'Select Image',
						multiple: false,
						library: { type: 'image' }
					});
					frame.on('select', function() {
						var attachment = frame.state().get('selection').first().toJSON();
						$('#' + target).val(attachment.url);
						$('#' + target + '-preview').attr('src', attachment.url).show();
						button.text('Change').after(' <a href=\"#\" class=\"visati-remove-btn\" data-target=\"' + target + '\">Remove</a>');
					});
					frame.open();
				});

				// Remove image
				$(document).on('click', '.visati-remove-btn', function(e) {
					e.preventDefault();
					var target = $(this).data('target');
					$('#' + target).val('');
					$('#' + target + '-preview').hide();
					$(this).prev('.visati-upload-btn').text('Select Image');
					$(this).remove();
				});
			});
		" );
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

		// Contact page details.
		$opts['contact_hours']          = sanitize_textarea_field( wp_unslash( $_POST['contact_hours'] ?? '' ) );
		$opts['contact_whatsapp_note']  = sanitize_text_field( wp_unslash( $_POST['contact_whatsapp_note'] ?? '' ) );
		$opts['contact_email_note']     = sanitize_text_field( wp_unslash( $_POST['contact_email_note'] ?? '' ) );
		$opts['contact_phone_note']     = sanitize_text_field( wp_unslash( $_POST['contact_phone_note'] ?? '' ) );
		$opts['contact_office_note']    = sanitize_text_field( wp_unslash( $_POST['contact_office_note'] ?? '' ) );
		$opts['contact_hours_note']     = sanitize_text_field( wp_unslash( $_POST['contact_hours_note'] ?? '' ) );
		$opts['google_maps_embed_url']  = esc_url_raw( wp_unslash( $_POST['google_maps_embed_url'] ?? '' ) );

		// Announcement bar.
		$opts['announcement_active']     = isset( $_POST['announcement_active'] ) ? 1 : 0;
		$opts['announcement_text']       = sanitize_text_field( wp_unslash( $_POST['announcement_text'] ?? '' ) );
		$opts['announcement_cta_label']  = sanitize_text_field( wp_unslash( $_POST['announcement_cta_label'] ?? '' ) );
		$opts['announcement_cta_url']    = esc_url_raw( wp_unslash( $_POST['announcement_cta_url'] ?? '' ) );
		$opts['announcement_bg_color']   = sanitize_hex_color( wp_unslash( $_POST['announcement_bg_color'] ?? '' ) ) ?: '#C9A84C';
		$opts['announcement_text_color'] = sanitize_hex_color( wp_unslash( $_POST['announcement_text_color'] ?? '' ) ) ?: '#0A1628';
		$opts['announcement_end_date']   = sanitize_text_field( wp_unslash( $_POST['announcement_end_date'] ?? '' ) );

		// Notifications.
		$emails_raw = wp_unslash( $_POST['notification_emails'] ?? '' );
		$emails     = preg_split( '/[\r\n,]+/', $emails_raw );
		$emails     = array_values( array_unique( array_filter( array_map( 'sanitize_email', array_map( 'trim', $emails ) ), 'is_email' ) ) );
		$opts['notification_emails'] = implode( "\n", $emails );

		// Tracking + social.
		$opts['ga4_id']             = sanitize_text_field( wp_unslash( $_POST['ga4_id'] ?? '' ) );
		$opts['gtm_container_id']   = sanitize_text_field( wp_unslash( $_POST['gtm_container_id'] ?? '' ) );
		$opts['facebook_pixel_id']  = sanitize_text_field( wp_unslash( $_POST['facebook_pixel_id'] ?? '' ) );
		$opts['tiktok_pixel_id']    = sanitize_text_field( wp_unslash( $_POST['tiktok_pixel_id'] ?? '' ) );
		$opts['social_instagram']   = esc_url_raw( wp_unslash( $_POST['social_instagram'] ?? '' ) );
		$opts['social_facebook']    = esc_url_raw( wp_unslash( $_POST['social_facebook'] ?? '' ) );
		$opts['social_linkedin']    = esc_url_raw( wp_unslash( $_POST['social_linkedin'] ?? '' ) );
		$opts['social_twitter']     = esc_url_raw( wp_unslash( $_POST['social_twitter'] ?? '' ) );

		// Header.
		$opts['header_logo']      = esc_url_raw( wp_unslash( $_POST['header_logo'] ?? '' ) );
		$opts['header_cta_label'] = sanitize_text_field( wp_unslash( $_POST['header_cta_label'] ?? '' ) );
		$opts['header_cta_url']   = esc_url_raw( wp_unslash( $_POST['header_cta_url'] ?? '' ) );

		// Apply page — order summary guarantee.
		$opts['guarantee_title'] = sanitize_text_field( wp_unslash( $_POST['guarantee_title'] ?? '' ) );
		$opts['guarantee_text']  = sanitize_textarea_field( wp_unslash( $_POST['guarantee_text'] ?? '' ) );

		// Express processing fees.
		$opts['express_surcharge_aed'] = absint( $_POST['express_surcharge_aed'] ?? 99 );
		$opts['express_surcharge_usd'] = absint( $_POST['express_surcharge_usd'] ?? 27 );

		// Homepage — Global Reach section.
		$opts['global_reach_label']     = sanitize_text_field( wp_unslash( $_POST['global_reach_label'] ?? '' ) );
		$opts['global_reach_title']     = sanitize_text_field( wp_unslash( $_POST['global_reach_title'] ?? '' ) );
		$opts['global_reach_desc']      = sanitize_textarea_field( wp_unslash( $_POST['global_reach_desc'] ?? '' ) );
		$opts['global_reach_cta_label'] = sanitize_text_field( wp_unslash( $_POST['global_reach_cta_label'] ?? '' ) );
		$opts['global_reach_cta_url']   = sanitize_text_field( wp_unslash( $_POST['global_reach_cta_url'] ?? '' ) );
		foreach ( array( 1, 2, 3, 4 ) as $i ) {
			$opts[ "global_reach_stat{$i}_number" ] = sanitize_text_field( wp_unslash( $_POST[ "global_reach_stat{$i}_number" ] ?? '' ) );
			$opts[ "global_reach_stat{$i}_label" ]  = sanitize_text_field( wp_unslash( $_POST[ "global_reach_stat{$i}_label" ] ?? '' ) );
		}

		// Footer.
		$opts['brand_tagline']       = sanitize_text_field( wp_unslash( $_POST['brand_tagline'] ?? '' ) );
		$opts['brand_description']   = sanitize_textarea_field( wp_unslash( $_POST['brand_description'] ?? '' ) );
		$opts['contact_address']     = sanitize_textarea_field( wp_unslash( $_POST['contact_address'] ?? '' ) );
		$opts['contact_trade_licence'] = sanitize_text_field( wp_unslash( $_POST['contact_trade_licence'] ?? '' ) );
		$opts['footer_copyright']    = sanitize_text_field( wp_unslash( $_POST['footer_copyright'] ?? '' ) );

		// Email template.
		$opts['email_logo_url']       = esc_url_raw( wp_unslash( $_POST['email_logo_url'] ?? '' ) );
		$opts['email_brand_name']     = sanitize_text_field( wp_unslash( $_POST['email_brand_name'] ?? '' ) );
		$opts['email_primary_color']  = sanitize_hex_color( wp_unslash( $_POST['email_primary_color'] ?? '' ) ) ?: '#0A1628';
		$opts['email_accent_color']   = sanitize_hex_color( wp_unslash( $_POST['email_accent_color'] ?? '' ) ) ?: '#C9A84C';
		$opts['email_bg_color']       = sanitize_hex_color( wp_unslash( $_POST['email_bg_color'] ?? '' ) ) ?: '#F8F9FB';
		$opts['email_footer_text']    = sanitize_text_field( wp_unslash( $_POST['email_footer_text'] ?? '' ) );

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

				<h2><?php esc_html_e( 'Contact Page Details', 'visati-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="contact_hours"><?php esc_html_e( 'Office hours', 'visati-core' ); ?></label></th>
						<td><textarea name="contact_hours" id="contact_hours" rows="2" class="regular-text"><?php echo esc_textarea( $s( 'contact_hours' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Two lines, e.g. "Sunday — Thursday" / "9:00 AM — 9:00 PM GST".', 'visati-core' ); ?></p></td></tr>
					<tr><th><label for="contact_whatsapp_note"><?php esc_html_e( 'WhatsApp button subtext', 'visati-core' ); ?></label></th>
						<td><input name="contact_whatsapp_note" id="contact_whatsapp_note" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'contact_whatsapp_note' ) ); ?>"></td></tr>
					<tr><th><label for="contact_email_note"><?php esc_html_e( 'Email row note', 'visati-core' ); ?></label></th>
						<td><input name="contact_email_note" id="contact_email_note" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'contact_email_note' ) ); ?>"></td></tr>
					<tr><th><label for="contact_phone_note"><?php esc_html_e( 'Phone row note', 'visati-core' ); ?></label></th>
						<td><input name="contact_phone_note" id="contact_phone_note" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'contact_phone_note' ) ); ?>"></td></tr>
					<tr><th><label for="contact_office_note"><?php esc_html_e( 'Office row note', 'visati-core' ); ?></label></th>
						<td><input name="contact_office_note" id="contact_office_note" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'contact_office_note' ) ); ?>"></td></tr>
					<tr><th><label for="contact_hours_note"><?php esc_html_e( 'Hours row note', 'visati-core' ); ?></label></th>
						<td><input name="contact_hours_note" id="contact_hours_note" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'contact_hours_note' ) ); ?>"></td></tr>
					<tr><th><label for="google_maps_embed_url"><?php esc_html_e( 'Google Maps embed URL', 'visati-core' ); ?></label></th>
						<td><textarea name="google_maps_embed_url" id="google_maps_embed_url" rows="3" class="large-text"><?php echo esc_textarea( $s( 'google_maps_embed_url' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Google Maps → Share → Embed a map → copy just the src="..." URL from the <iframe> tag.', 'visati-core' ); ?></p></td></tr>
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
						<td><input name="announcement_bg_color" id="announcement_bg_color" type="text" class="visati-color-picker" value="<?php echo esc_attr( $s( 'announcement_bg_color' ) ); ?>"></td></tr>
					<tr><th><label for="announcement_text_color"><?php esc_html_e( 'Text color', 'visati-core' ); ?></label></th>
						<td><input name="announcement_text_color" id="announcement_text_color" type="text" class="visati-color-picker" value="<?php echo esc_attr( $s( 'announcement_text_color' ) ); ?>"></td></tr>
					<tr><th><label for="announcement_end_date"><?php esc_html_e( 'End date (optional)', 'visati-core' ); ?></label></th>
						<td><input name="announcement_end_date" id="announcement_end_date" type="date" value="<?php echo esc_attr( $s( 'announcement_end_date' ) ); ?>"></td></tr>
				</table>

				<h2><?php esc_html_e( 'Notifications', 'visati-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="notification_emails"><?php esc_html_e( 'Notification recipients', 'visati-core' ); ?></label></th>
						<td>
							<textarea name="notification_emails" id="notification_emails" rows="3" class="large-text" placeholder="admin@visati.ae&#10;manager@visati.ae"><?php echo esc_textarea( $s( 'notification_emails' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One email per line (or comma-separated). Every address here receives new application and new inquiry alerts. Leave empty to fall back to the wp-config.php ADMIN_NOTIFICATION_EMAIL / WordPress admin email.', 'visati-core' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Tracking & Social', 'visati-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="ga4_id"><?php esc_html_e( 'Google Analytics 4 ID', 'visati-core' ); ?></label></th>
						<td><input name="ga4_id" id="ga4_id" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'ga4_id' ) ); ?>" placeholder="G-XXXXXXXXXX"></td></tr>
					<tr><th><label for="gtm_container_id"><?php esc_html_e( 'Google Tag Manager ID', 'visati-core' ); ?></label></th>
						<td><input name="gtm_container_id" id="gtm_container_id" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'gtm_container_id' ) ); ?>" placeholder="GTM-XXXXXXX"></td></tr>
					<tr><th><label for="facebook_pixel_id"><?php esc_html_e( 'Facebook Pixel ID', 'visati-core' ); ?></label></th>
						<td><input name="facebook_pixel_id" id="facebook_pixel_id" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'facebook_pixel_id' ) ); ?>" placeholder="1234567890"></td></tr>
					<tr><th><label for="tiktok_pixel_id"><?php esc_html_e( 'TikTok Pixel ID', 'visati-core' ); ?></label></th>
						<td><input name="tiktok_pixel_id" id="tiktok_pixel_id" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'tiktok_pixel_id' ) ); ?>" placeholder="Cxxxxxxxxx"></td></tr>
					<tr><th><label for="social_instagram"><?php esc_html_e( 'Instagram URL', 'visati-core' ); ?></label></th>
						<td><input name="social_instagram" id="social_instagram" type="url" class="regular-text" value="<?php echo esc_attr( $s( 'social_instagram' ) ); ?>"></td></tr>
					<tr><th><label for="social_facebook"><?php esc_html_e( 'Facebook URL', 'visati-core' ); ?></label></th>
						<td><input name="social_facebook" id="social_facebook" type="url" class="regular-text" value="<?php echo esc_attr( $s( 'social_facebook' ) ); ?>"></td></tr>
					<tr><th><label for="social_linkedin"><?php esc_html_e( 'LinkedIn URL', 'visati-core' ); ?></label></th>
						<td><input name="social_linkedin" id="social_linkedin" type="url" class="regular-text" value="<?php echo esc_attr( $s( 'social_linkedin' ) ); ?>"></td></tr>
					<tr><th><label for="social_twitter"><?php esc_html_e( 'Twitter/X URL', 'visati-core' ); ?></label></th>
						<td><input name="social_twitter" id="social_twitter" type="url" class="regular-text" value="<?php echo esc_attr( $s( 'social_twitter' ) ); ?>"></td></tr>
				</table>

				<h2><?php esc_html_e( 'Header', 'visati-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="header_logo"><?php esc_html_e( 'Logo image', 'visati-core' ); ?></label></th>
						<td>
							<input name="header_logo" id="header_logo" type="hidden" value="<?php echo esc_attr( $s( 'header_logo' ) ); ?>">
							<?php if ( $s( 'header_logo' ) ) : ?>
								<img id="header_logo-preview" src="<?php echo esc_url( $s( 'header_logo' ) ); ?>" style="max-height:60px;margin-bottom:8px;display:block;">
							<?php else : ?>
								<img id="header_logo-preview" src="" style="max-height:60px;margin-bottom:8px;display:none;">
							<?php endif; ?>
							<button type="button" class="button visati-upload-btn" data-target="header_logo">
								<?php echo $s( 'header_logo' ) ? 'Change' : 'Select Image'; ?>
							</button>
							<?php if ( $s( 'header_logo' ) ) : ?>
								<a href="#" class="visati-remove-btn" data-target="header_logo">Remove</a>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Leave empty to use the default gold wing logo.', 'visati-core' ); ?></p>
						</td>
					</tr>
					<tr><th><label for="header_cta_label"><?php esc_html_e( 'CTA button text', 'visati-core' ); ?></label></th>
						<td><input name="header_cta_label" id="header_cta_label" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'header_cta_label' ) ); ?>" placeholder="Apply Now"></td></tr>
					<tr><th><label for="header_cta_url"><?php esc_html_e( 'CTA button URL', 'visati-core' ); ?></label></th>
						<td><input name="header_cta_url" id="header_cta_url" type="url" class="regular-text" value="<?php echo esc_attr( $s( 'header_cta_url' ) ); ?>" placeholder="/apply"></td></tr>
				</table>

				<h2><?php esc_html_e( 'Apply Page — Order Summary', 'visati-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="guarantee_title"><?php esc_html_e( 'Guarantee title', 'visati-core' ); ?></label></th>
						<td><input name="guarantee_title" id="guarantee_title" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'guarantee_title' ) ); ?>"></td></tr>
					<tr><th><label for="guarantee_text"><?php esc_html_e( 'Guarantee description', 'visati-core' ); ?></label></th>
						<td><textarea name="guarantee_text" id="guarantee_text" rows="3" class="large-text"><?php echo esc_textarea( $s( 'guarantee_text' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown in the dark trust badge on the apply form\'s order summary sidebar.', 'visati-core' ); ?></p></td></tr>
				</table>

				<h2><?php esc_html_e( 'Express Processing Fee', 'visati-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="express_surcharge_aed"><?php esc_html_e( 'Express surcharge (AED)', 'visati-core' ); ?></label></th>
						<td><input name="express_surcharge_aed" id="express_surcharge_aed" type="number" min="0" class="small-text" value="<?php echo esc_attr( $s( 'express_surcharge_aed' ) ); ?>" placeholder="99">
							<p class="description"><?php esc_html_e( 'Added to the visa fee when applicant chooses Express processing.', 'visati-core' ); ?></p></td></tr>
					<tr><th><label for="express_surcharge_usd"><?php esc_html_e( 'Express surcharge (USD)', 'visati-core' ); ?></label></th>
						<td><input name="express_surcharge_usd" id="express_surcharge_usd" type="number" min="0" class="small-text" value="<?php echo esc_attr( $s( 'express_surcharge_usd' ) ); ?>" placeholder="27">
							<p class="description"><?php esc_html_e( 'USD equivalent — used in the Stripe checkout amount.', 'visati-core' ); ?></p></td></tr>
				</table>

				<h2><?php esc_html_e( 'Homepage — Global Reach Section', 'visati-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="global_reach_label"><?php esc_html_e( 'Eyebrow label', 'visati-core' ); ?></label></th>
						<td><input name="global_reach_label" id="global_reach_label" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'global_reach_label' ) ); ?>"></td></tr>
					<tr><th><label for="global_reach_title"><?php esc_html_e( 'Heading', 'visati-core' ); ?></label></th>
						<td><input name="global_reach_title" id="global_reach_title" type="text" class="large-text" value="<?php echo esc_attr( $s( 'global_reach_title' ) ); ?>"></td></tr>
					<tr><th><label for="global_reach_desc"><?php esc_html_e( 'Description', 'visati-core' ); ?></label></th>
						<td><textarea name="global_reach_desc" id="global_reach_desc" rows="2" class="large-text"><?php echo esc_textarea( $s( 'global_reach_desc' ) ); ?></textarea></td></tr>
					<tr><th><label for="global_reach_cta_label"><?php esc_html_e( 'CTA label', 'visati-core' ); ?></label></th>
						<td><input name="global_reach_cta_label" id="global_reach_cta_label" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'global_reach_cta_label' ) ); ?>"></td></tr>
					<tr><th><label for="global_reach_cta_url"><?php esc_html_e( 'CTA URL', 'visati-core' ); ?></label></th>
						<td><input name="global_reach_cta_url" id="global_reach_cta_url" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'global_reach_cta_url' ) ); ?>" placeholder="/about"></td></tr>
					<?php foreach ( array( 1, 2, 3, 4 ) as $i ) : ?>
						<tr><th><?php echo esc_html( sprintf( __( 'Stat %d — number', 'visati-core' ), $i ) ); ?></th>
							<td><input name="global_reach_stat<?php echo $i; ?>_number" type="text" class="regular-text" value="<?php echo esc_attr( $s( "global_reach_stat{$i}_number" ) ); ?>" placeholder="e.g. 1000+">
								<input name="global_reach_stat<?php echo $i; ?>_label" type="text" class="regular-text" value="<?php echo esc_attr( $s( "global_reach_stat{$i}_label" ) ); ?>" placeholder="Label" style="margin-left:8px;"></td></tr>
					<?php endforeach; ?>
					<tr><td colspan="2"><p class="description"><?php esc_html_e( 'Stat numbers animate as a count-up when the section scrolls into view — enter the final value including any suffix, e.g. "1000+", "99%", "4.9/5".', 'visati-core' ); ?></p></td></tr>
				</table>

				<h2><?php esc_html_e( 'Footer & Brand', 'visati-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="brand_tagline"><?php esc_html_e( 'Brand tagline', 'visati-core' ); ?></label></th>
						<td><input name="brand_tagline" id="brand_tagline" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'brand_tagline' ) ); ?>" placeholder="Dubai Visas. Simplified."></td></tr>
					<tr><th><label for="brand_description"><?php esc_html_e( 'Brand description', 'visati-core' ); ?></label></th>
						<td><textarea name="brand_description" id="brand_description" rows="3" class="large-text"><?php echo esc_textarea( $s( 'brand_description' ) ); ?></textarea></td></tr>
					<tr><th><label for="contact_address"><?php esc_html_e( 'Office address', 'visati-core' ); ?></label></th>
						<td><textarea name="contact_address" id="contact_address" rows="2" class="large-text"><?php echo esc_textarea( $s( 'contact_address' ) ); ?></textarea></td></tr>
					<tr><th><label for="contact_trade_licence"><?php esc_html_e( 'Trade licence number', 'visati-core' ); ?></label></th>
						<td><input name="contact_trade_licence" id="contact_trade_licence" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'contact_trade_licence' ) ); ?>" placeholder="DED-XXXX-XXXX"></td></tr>
					<tr><th><label for="footer_copyright"><?php esc_html_e( 'Custom copyright text', 'visati-core' ); ?></label></th>
						<td><input name="footer_copyright" id="footer_copyright" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'footer_copyright' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Leave empty for auto-generated copyright line.', 'visati-core' ); ?></p></td></tr>
				</table>

				<h2><?php esc_html_e( 'Email Template', 'visati-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="email_logo_url"><?php esc_html_e( 'Logo image', 'visati-core' ); ?></label></th>
						<td>
							<input name="email_logo_url" id="email_logo_url" type="hidden" value="<?php echo esc_attr( $s( 'email_logo_url' ) ); ?>">
							<?php if ( $s( 'email_logo_url' ) ) : ?>
								<img id="email_logo_url-preview" src="<?php echo esc_url( $s( 'email_logo_url' ) ); ?>" style="max-height:40px;margin-bottom:8px;display:block;">
							<?php else : ?>
								<img id="email_logo_url-preview" src="" style="max-height:40px;margin-bottom:8px;display:none;">
							<?php endif; ?>
							<button type="button" class="button visati-upload-btn" data-target="email_logo_url">
								<?php echo $s( 'email_logo_url' ) ? 'Change' : 'Select Image'; ?>
							</button>
							<?php if ( $s( 'email_logo_url' ) ) : ?>
								<a href="#" class="visati-remove-btn" data-target="email_logo_url">Remove</a>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Logo shown in email header. Leave empty for text-only brand name.', 'visati-core' ); ?></p>
						</td>
					</tr>
					<tr><th><label for="email_brand_name"><?php esc_html_e( 'Brand name', 'visati-core' ); ?></label></th>
						<td><input name="email_brand_name" id="email_brand_name" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'email_brand_name' ) ); ?>" placeholder="Visati"></td></tr>
					<tr><th><label for="email_primary_color"><?php esc_html_e( 'Header background', 'visati-core' ); ?></label></th>
						<td><input name="email_primary_color" id="email_primary_color" type="text" class="visati-color-picker" value="<?php echo esc_attr( $s( 'email_primary_color' ) ); ?>"></td></tr>
					<tr><th><label for="email_accent_color"><?php esc_html_e( 'Accent / CTA color', 'visati-core' ); ?></label></th>
						<td><input name="email_accent_color" id="email_accent_color" type="text" class="visati-color-picker" value="<?php echo esc_attr( $s( 'email_accent_color' ) ); ?>"></td></tr>
					<tr><th><label for="email_bg_color"><?php esc_html_e( 'Email background', 'visati-core' ); ?></label></th>
						<td><input name="email_bg_color" id="email_bg_color" type="text" class="visati-color-picker" value="<?php echo esc_attr( $s( 'email_bg_color' ) ); ?>"></td></tr>
					<tr><th><label for="email_footer_text"><?php esc_html_e( 'Footer text', 'visati-core' ); ?></label></th>
						<td><input name="email_footer_text" id="email_footer_text" type="text" class="regular-text" value="<?php echo esc_attr( $s( 'email_footer_text' ) ); ?>" placeholder="This is an automated message from Visati."></td></tr>
				</table>

				<?php submit_button( __( 'Save settings', 'visati-core' ), 'primary', 'visati_settings_submit' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Helper to read a setting value for the form.
	 *
	 * Falls back to the same hardcoded defaults used in header.php/footer.php
	 * when nothing has been saved yet, so the form shows the site's current
	 * live content instead of blank fields.
	 *
	 * @param string $key Setting key.
	 * @return string|int
	 */
	public static function setting( string $key ) {
		return Visati_Core_Settings::get( $key, self::default_for( $key ) );
	}

	/**
	 * Default value matching the current hardcoded fallback in header.php/footer.php,
	 * for a given setting key. Empty string if there's no established default.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private static function default_for( string $key ): string {
		$defaults = array(
			'whatsapp_number'        => '971585542344',
			'contact_email'          => 'visa@visati.ae',
			'contact_phone'          => '+971 58 554 2344',
			'contact_address'        => "Boulevard Plaza Tower 1\nDowntown Dubai, UAE",
			'contact_trade_licence'  => 'DED-XXXX-XXXX',
			'social_instagram'       => 'https://instagram.com/visati.ae',
			'social_facebook'        => 'https://facebook.com/visati.ae',
			'social_twitter'         => 'https://twitter.com/visatiuae',
			'social_linkedin'        => 'https://linkedin.com/company/visati',
			'brand_tagline'          => 'Dubai Visas. Simplified.',
			'brand_description'     => 'UAE visa consultancy. Concierge-grade processing for travellers and businesses.',
			'header_cta_label'       => 'Apply Now',
			'header_cta_url'         => '/apply',
			'guarantee_title'        => '100% refund on rejection.',
			'guarantee_text'         => 'If your visa is rejected in violation with our policy, we refund the full fee. No questions.',
			'express_surcharge_aed'  => '99',
			'express_surcharge_usd'  => '27',
			'contact_hours'          => "Sunday — Thursday\n9:00 AM — 9:00 PM GST",
			'contact_whatsapp_note'  => 'Average reply 2 min',
			'contact_email_note'     => 'Reply within 1 hour',
			'contact_phone_note'     => 'Sun–Thu, 9am–6pm GST',
			'contact_office_note'    => 'By appointment',
			'contact_hours_note'     => 'WhatsApp 24/7',
			'google_maps_embed_url'  => 'https://maps.google.com/maps?q=' . rawurlencode( 'Boulevard Plaza Tower 1, Downtown Dubai, UAE' ) . '&output=embed',
			'global_reach_label'         => 'Global Reach',
			'global_reach_title'         => 'Trusted by Applicants Across the Globe',
			'global_reach_desc'          => 'We are a Dubai based visa agency serving residents of all nationalities across USA, UK, Canada, Australia and Europe.',
			'global_reach_cta_label'     => 'More About Us',
			'global_reach_cta_url'       => '/about',
			'global_reach_stat1_number'  => '1000+',
			'global_reach_stat1_label'   => 'Happy Travelers',
			'global_reach_stat2_number'  => '99%',
			'global_reach_stat2_label'   => 'Approval Rate',
			'global_reach_stat3_number'  => '100+',
			'global_reach_stat3_label'   => 'Google Reviews',
			'global_reach_stat4_number'  => '4.9/5',
			'global_reach_stat4_label'   => 'Customer Rating',
			'email_logo_url'             => '',
			'email_brand_name'           => 'Visati',
			'email_primary_color'        => '#0A1628',
			'email_accent_color'         => '#C9A84C',
			'email_bg_color'             => '#F8F9FB',
			'email_footer_text'          => 'This is an automated message from Visati.',
		);
		return $defaults[ $key ] ?? '';
	}
}
