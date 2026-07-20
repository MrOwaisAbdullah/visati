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
	 * Verified sender address (from RESEND_FROM_EMAIL), or '' to fall back to noreply@<host>.
	 */
	public static function from_email(): string {
		return self::env( array( 'RESEND_FROM_EMAIL' ), '' );
	}

	/**
	 * Admin alert recipient (new application / inquiry notifications).
	 */
	public static function admin_notification_email(): string {
		return self::env( array( 'ADMIN_NOTIFICATION_EMAIL', 'NEXT_PUBLIC_ADMIN_EMAIL' ), '' );
	}

	/**
	 * Express processing surcharge in AED (default 99).
	 */
	public static function express_surcharge_aed(): int {
		$val = self::env( array( 'EXPRESS_SURCHARGE_AED' ), '' );
		return '' !== $val ? (int) $val : 99;
	}

	/**
	 * Express processing surcharge in USD (default 27).
	 */
	public static function express_surcharge_usd(): int {
		$val = self::env( array( 'EXPRESS_SURCHARGE_USD' ), '' );
		return '' !== $val ? (int) $val : 27;
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
