<?php
/**
 * Central settings + secrets accessor.
 *
 * Secrets are read FIRST from the environment (the Next.js .env.local, loaded by
 * Visati_Core_Env_Loader) and then from wp-config.php VISATI_* constants as a
 * fallback. Values are never echoed. Non-secret, client-editable config lives in
 * the `visati_core_settings` option (edited via the admin Settings page).
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Settings {

	const OPTION = 'visati_core_settings';

	/* ── Secrets (env first, then wp-config constant) ────────────────── */

	public static function stripe_secret_key(): string {
		return self::env( array( 'STRIPE_SECRET_KEY' ), 'VISATI_STRIPE_SECRET_KEY' );
	}

	public static function stripe_webhook_secret(): string {
		return self::env( array( 'STRIPE_WEBHOOK_SECRET' ), 'VISATI_STRIPE_WEBHOOK_SECRET' );
	}

	public static function r2_account_id(): string {
		return self::env( array( 'R2_ACCOUNT_ID' ), 'VISATI_R2_ACCOUNT_ID' );
	}

	public static function r2_access_key(): string {
		return self::env( array( 'R2_ACCESS_KEY_ID' ), 'VISATI_R2_ACCESS_KEY' );
	}

	public static function r2_secret_key(): string {
		return self::env( array( 'R2_SECRET_ACCESS_KEY' ), 'VISATI_R2_SECRET_KEY' );
	}

	public static function r2_bucket(): string {
		return self::env( array( 'R2_BUCKET_NAME' ), 'VISATI_R2_BUCKET' );
	}

	/**
	 * R2 S3-compatible endpoint.
	 */
	public static function r2_endpoint(): string {
		$acct = self::r2_account_id();
		return $acct ? sprintf( 'https://%s.r2.cloudflarestorage.com', $acct ) : '';
	}

	public static function resend_api_key(): string {
		return self::env( array( 'RESEND_API_KEY' ), 'VISATI_RESEND_API_KEY' );
	}

	/**
	 * Verified sender address (RESEND_FROM_EMAIL env, or VISATI_RESEND_FROM_EMAIL in wp-config),
	 * or '' to fall back to noreply@<host>. Must be on the domain verified in Resend (send.visati.ae).
	 */
	public static function from_email(): string {
		return self::env( array( 'RESEND_FROM_EMAIL' ), 'VISATI_RESEND_FROM_EMAIL' );
	}

	/**
	 * Admin alert recipient (new application / inquiry notifications).
	 *
	 * @deprecated Use notification_emails() for multi-recipient support.
	 */
	public static function admin_notification_email(): string {
		return self::env( array( 'ADMIN_NOTIFICATION_EMAIL', 'NEXT_PUBLIC_ADMIN_EMAIL' ), '' );
	}

	/**
	 * Admin alert recipients (new application / inquiry notifications).
	 *
	 * Prefers the client-editable `notification_emails` setting (one address per
	 * line, set via the Settings page). Falls back to the env/wp-config value,
	 * then to the WordPress admin email — always returns at least one address
	 * if any source has one.
	 *
	 * @return array<int,string>
	 */
	public static function notification_emails(): array {
		$raw = (string) self::get( 'notification_emails', '' );
		if ( '' !== $raw ) {
			$emails = preg_split( '/[\r\n,]+/', $raw );
			$emails = array_values( array_unique( array_filter( array_map( 'trim', $emails ), 'is_email' ) ) );
			if ( $emails ) {
				return $emails;
			}
		}

		$fallback = self::admin_notification_email();
		if ( $fallback && is_email( $fallback ) ) {
			return array( $fallback );
		}

		$wp_admin_email = get_option( 'admin_email' );
		return $wp_admin_email ? array( $wp_admin_email ) : array();
	}

	/**
	 * Express processing surcharge in AED (default 99).
	 * Admin settings take priority over env/wp-config.
	 */
	public static function express_surcharge_aed(): int {
		$admin = self::get( 'express_surcharge_aed', '' );
		if ( '' !== $admin ) {
			return (int) $admin;
		}
		$val = self::env( array( 'EXPRESS_SURCHARGE_AED' ), '' );
		return '' !== $val ? (int) $val : 99;
	}

	/**
	 * Express processing surcharge in USD (default 27).
	 * Admin settings take priority over env/wp-config.
	 */
	public static function express_surcharge_usd(): int {
		$admin = self::get( 'express_surcharge_usd', '' );
		if ( '' !== $admin ) {
			return (int) $admin;
		}
		$val = self::env( array( 'EXPRESS_SURCHARGE_USD' ), '' );
		return '' !== $val ? (int) $val : 27;
	}

	/**
	 * Whether bot document access (REST + Application Password) is enabled. Settings → Notifications.
	 *
	 * @return bool
	 */
	public static function bot_access_enabled(): bool {
		return (bool) self::get( 'bot_document_access', 0 );
	}
	/* ── Configuration status (booleans only — never values) ─────────── */

	public static function stripe_is_configured(): bool {
		return (bool) self::stripe_secret_key() && (bool) self::stripe_webhook_secret();
	}

	public static function r2_is_configured(): bool {
		return self::r2_account_id() && self::r2_access_key() && self::r2_secret_key() && self::r2_bucket();
	}

	/**
	 * Required secrets that are currently missing.
	 *
	 * @return array<int,string>
	 */
	public static function missing_secrets(): array {
		$missing = array();
		if ( ! self::stripe_is_configured() ) {
			$missing[] = 'Stripe (STRIPE_SECRET_KEY / STRIPE_WEBHOOK_SECRET)';
		}
		if ( ! self::r2_is_configured() ) {
			$missing[] = 'Cloudflare R2 (R2_ACCOUNT_ID / R2_ACCESS_KEY_ID / R2_SECRET_ACCESS_KEY / R2_BUCKET_NAME)';
		}
		return $missing;
	}

	/**
	 * Admin notice listing missing secrets (admin only). Values are never shown.
	 *
	 * @return void
	 */
	public static function admin_config_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$missing = self::missing_secrets();
		if ( empty( $missing ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p><strong>%s:</strong> %s</p><p>%s</p></div>',
			esc_html__( 'Visati Core', 'visati-core' ),
			esc_html__( 'the following secrets are not defined (in .env.local or wp-config.php) — payment and document features will be disabled until added:', 'visati-core' ),
			esc_html( implode( '; ', $missing ) )
		);
	}

	/* ── Non-secret, client-editable config (wp_options) ─────────────── */

	/**
	 * Get a non-secret option value.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get( string $key, $default = '' ) {
		static $opts = null;
		if ( null === $opts ) {
			$opts = get_option( self::OPTION, array() );
		}
		return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
	}

	/**
	 * Set a non-secret option value.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public static function set( string $key, $value ): void {
		$opts         = get_option( self::OPTION, array() );
		$opts[ $key ] = $value;
		update_option( self::OPTION, $opts );
	}

	/* ── Internals ───────────────────────────────────────────────────── */

	/**
	 * Read a secret: try env names first, then a wp-config constant.
	 *
	 * @param array<int,string> $names    Environment variable names.
	 * @param string            $constant Optional wp-config constant name.
	 * @return string
	 */
	protected static function env( array $names, string $constant = '' ): string {
		foreach ( $names as $name ) {
			$val = getenv( $name );
			if ( is_string( $val ) && '' !== $val ) {
				return $val;
			}
		}
		if ( '' !== $constant && defined( $constant ) ) {
			$val = constant( $constant );
			return is_string( $val ) ? $val : '';
		}
		return '';
	}
}
