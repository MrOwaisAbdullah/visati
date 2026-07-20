<?php
/**
 * Template Name: Track Page
 * Description: Application tracking page.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Enqueue tracking script (prints in footer) + pass REST config.
wp_enqueue_script(
	'visati-tracking',
	VISATI_CORE_URL . 'assets/js/tracking.js',
	array(),
	VISATI_CORE_VERSION,
	true
);
wp_localize_script(
	'visati-tracking',
	'visatiTrack',
	array(
		'restUrl' => esc_url_raw( rest_url( 'visati/v1' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
	)
);

get_header();
?>

<main id="primary" class="visati-page visati-page--track">
	<div class="visati-section">
		<div class="visati-container">
			<div style="text-align:center;margin-bottom:2rem;">
				<span class="visati-eyebrow"><?php esc_html_e( 'Application Status', 'visati-core' ); ?></span>
				<h1 style="margin-top:0.5rem;"><?php esc_html_e( 'Track Your Visa Application', 'visati-core' ); ?></h1>
				<p style="color:rgba(26,26,46,0.6);max-width:480px;margin:0.75rem auto 0;">
					<?php esc_html_e( 'Enter your application ID to see the current status and timeline.', 'visati-core' ); ?>
				</p>
			</div>

			<div class="visati-track-search" style="justify-content:center;">
				<input type="text" id="track-id-input" placeholder="VIS-2026-XXXXXX" aria-label="Application ID" style="text-transform:uppercase;">
				<button type="button" class="visati-btn" id="track-search-btn">
					Track
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
				</button>
			</div>

			<div id="track-result" style="max-width:600px;margin:0 auto;"></div>
		</div>
	</div>
</main>

<?php
get_footer();
