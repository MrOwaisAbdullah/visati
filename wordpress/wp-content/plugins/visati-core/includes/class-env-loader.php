<?php
/**
 * Lightweight .env loader — reuses the Next.js project's .env.local so secrets
 * live in one place. No dependency. Loaded once at plugin bootstrap.
 *
 * Path resolution:
 *   1. VISATI_ENV_FILE constant (define in wp-config.php, e.g. the repo .env.local)
 *   2. Best-effort relative candidates (local dev via the directory junction)
 * Real environment variables always win over file values.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Env_Loader {

	/**
	 * Load the env file (if found) into getenv()/$_ENV/$_SERVER.
	 *
	 * @return void
	 */
	public static function load(): void {
		$file = self::resolve_path();
		if ( '' === $file || ! is_readable( $file ) ) {
			return;
		}

		$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( ! is_array( $lines ) ) {
			return;
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue; // comment / blank.
			}
			$eq = strpos( $line, '=' );
			if ( false === $eq ) {
				continue;
			}

			$name  = trim( substr( $line, 0, $eq ) );
			$value = trim( substr( $line, $eq + 1 ) );
			if ( '' === $name ) {
				continue;
			}

			// Strip a single layer of matching surrounding quotes.
			$len = strlen( $value );
			if ( $len >= 2 && ( '"' === $value[0] || "'" === $value[0] ) && $value[0] === $value[ $len - 1 ] ) {
				$value = substr( $value, 1, -1 );
			}

			// Real environment wins — do not override.
			if ( false === getenv( $name ) && ! isset( $_ENV[ $name ] ) ) {
				putenv( $name . '=' . $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.functions_usage_putenv
				$_ENV[ $name ]   = $value;
				$_SERVER[ $name ] = $value;
			}
		}
	}

	/**
	 * Resolve the env file path (explicit constant, then candidates).
	 *
	 * @return string Empty if not found.
	 */
	protected static function resolve_path(): string {
		if ( defined( 'VISATI_ENV_FILE' ) && '' !== VISATI_ENV_FILE ) {
			return (string) VISATI_ENV_FILE;
		}

		$base = rtrim( VISATI_CORE_DIR, '/\\' );
		$candidates = array(
			dirname( $base, 4 ) . '/.env.local', // plugin -> wordpress -> repo root (local, via junction).
			dirname( $base, 5 ) . '/.env.local',
			ABSPATH . '.env.local',
		);
		foreach ( $candidates as $candidate ) {
			if ( is_readable( $candidate ) ) {
				return $candidate;
			}
		}
		return '';
	}
}
