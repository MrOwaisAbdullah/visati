<?php
/**
 * Template Name: Apply Page
 * Description: Visa application form page.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="visati-page visati-page--apply">
	<div class="visati-section">
		<div class="visati-container">
			<div style="text-align:center;margin-bottom:2rem;">
				<span class="visati-eyebrow"><?php esc_html_e( 'Start your application', 'visati-core' ); ?></span>
				<h1 style="margin-top:0.5rem;"><?php esc_html_e( 'Apply for a UAE Visa', 'visati-core' ); ?></h1>
				<p style="color:rgba(26,26,46,0.6);max-width:520px;margin:0.75rem auto 0;">
					<?php esc_html_e( 'Complete the form below. It takes less than 5 minutes.', 'visati-core' ); ?>
				</p>
			</div>

			<?php
			// Success state.
			if ( isset( $_GET['success'] ) && '1' === $_GET['success'] && ! empty( $_GET['id'] ) ) :
				$app_id = sanitize_text_field( wp_unslash( $_GET['id'] ) );
			?>
				<div class="visati-card" style="max-width:560px;margin:0 auto;text-align:center;padding:3rem 2rem;">
					<div style="font-size:3rem;margin-bottom:1rem;">&#10003;</div>
					<h2 style="margin-top:0;"><?php esc_html_e( 'Application Submitted!', 'visati-core' ); ?></h2>
					<p style="color:rgba(26,26,46,0.6);margin:0.75rem 0 1.5rem;">
						<?php esc_html_e( 'Your application ID is:', 'visati-core' ); ?>
					</p>
					<p class="visati-mono" style="font-size:1.5rem;font-weight:700;color:var(--visati-navy);margin:0 0 1.5rem;">
						<?php echo esc_html( $app_id ); ?>
					</p>
					<p style="color:rgba(26,26,46,0.5);font-size:0.9rem;margin:0 0 1.5rem;">
						<?php esc_html_e( 'We have sent a confirmation to your email. You can track your application status below.', 'visati-core' ); ?>
					</p>
					<a href="<?php echo esc_url( home_url( '/track?id=' . rawurlencode( $app_id ) ) ); ?>" class="visati-btn">
						<?php esc_html_e( 'Track Application', 'visati-core' ); ?>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
					</a>
				</div>
			<?php else : ?>
				<?php echo do_shortcode( '[visati_apply_form]' ); ?>
			<?php endif; ?>
		</div>
	</div>
</main>

<?php
get_footer();
