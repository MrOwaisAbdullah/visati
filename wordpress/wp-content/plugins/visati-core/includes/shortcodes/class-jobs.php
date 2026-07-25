<?php
/**
 * [visati_jobs] — open job listings from the visati_job CPT.
 *
 * Usage: [visati_jobs] or [visati_jobs count="20"]
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Jobs_Shortcode {

	public static function init(): void {
		add_shortcode( 'visati_jobs', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'count' => 20,
			),
			$atts,
			'visati_jobs'
		);

		$jobs = get_posts(
			array(
				'post_type'      => 'visati_job',
				'posts_per_page' => (int) $atts['count'],
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'post_status'    => 'publish',
			)
		);

		// Filter to active only — treat never-saved (empty meta) as active, matching the meta box default.
		$jobs = array_values( array_filter( $jobs, function ( $job ) {
			$active = get_post_meta( $job->ID, '_visati_job_active', true );
			return '' === $active || '0' !== $active;
		} ) );

		if ( empty( $jobs ) ) {
			return '<p class="visati-jobs-empty">There are no open positions right now — check back soon, or reach out on WhatsApp to introduce yourself.</p>';
		}

		$type_labels = array(
			'full-time'  => 'Full-time',
			'part-time'  => 'Part-time',
			'contract'   => 'Contract',
			'internship' => 'Internship',
		);

		ob_start();
		?>
		<div class="visati-jobs-list">
			<?php foreach ( $jobs as $job ) :
				$department = get_post_meta( $job->ID, '_visati_job_department', true );
				$location   = get_post_meta( $job->ID, '_visati_job_location', true );
				$type       = get_post_meta( $job->ID, '_visati_job_type', true ) ?: 'full-time';
				$apply_email = get_post_meta( $job->ID, '_visati_job_apply_email', true );
				$excerpt    = wp_trim_words( wp_strip_all_tags( $job->post_content ), 28 );
				$mail_subject = rawurlencode( 'Application: ' . $job->post_title );
			?>
				<div class="visati-job-card">
					<div class="visati-job-card__main">
						<h3 class="visati-job-card__title"><?php echo esc_html( $job->post_title ); ?></h3>
						<div class="visati-job-card__meta">
							<?php if ( $department ) : ?><span><?php echo esc_html( $department ); ?></span><?php endif; ?>
							<?php if ( $location ) : ?><span><?php echo esc_html( $location ); ?></span><?php endif; ?>
							<span class="visati-job-card__badge"><?php echo esc_html( $type_labels[ $type ] ?? ucfirst( $type ) ); ?></span>
						</div>
						<?php if ( $excerpt ) : ?>
							<p class="visati-job-card__desc"><?php echo esc_html( $excerpt ); ?></p>
						<?php endif; ?>
					</div>
					<?php if ( $apply_email ) : ?>
						<a href="<?php echo esc_url( 'mailto:' . $apply_email . '?subject=' . $mail_subject ); ?>" class="visati-btn theme-primary-btn visati-job-card__apply">
							Apply Now
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
						</a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
