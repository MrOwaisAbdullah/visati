<?php
/**
 * "Visati — Quick View" widget on the native WordPress Dashboard.
 *
 * @var int   $submitted_apps
 * @var int   $under_review
 * @var int   $new_inquiries
 * @var float $revenue_30d
 * @var array $recent_apps
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="visati-widget">
	<div class="visati-widget__stats">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-applications&status=submitted' ) ); ?>" class="visati-widget__stat">
			<span class="visati-widget__stat-number"><?php echo esc_html( $submitted_apps ); ?></span>
			<span class="visati-widget__stat-label">Submitted</span>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-applications&status=under_review' ) ); ?>" class="visati-widget__stat">
			<span class="visati-widget__stat-number"><?php echo esc_html( $under_review ); ?></span>
			<span class="visati-widget__stat-label">Under Review</span>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-inquiries&status=new' ) ); ?>" class="visati-widget__stat">
			<span class="visati-widget__stat-number"><?php echo esc_html( $new_inquiries ); ?></span>
			<span class="visati-widget__stat-label">New Inquiries</span>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-dashboard' ) ); ?>" class="visati-widget__stat">
			<span class="visati-widget__stat-number">AED <?php echo esc_html( number_format( $revenue_30d, 0 ) ); ?></span>
			<span class="visati-widget__stat-label">Revenue (30d)</span>
		</a>
	</div>

	<?php if ( ! empty( $recent_apps ) ) : ?>
		<p class="visati-widget__heading">Recent applications</p>
		<ul class="visati-widget__list">
			<?php foreach ( $recent_apps as $app ) : ?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-applications&action=view&id=' . esc_attr( $app->id ) ) ); ?>">
						<?php if ( 'express' === $app->processing_tier ) : ?>
							<span class="visati-express-badge" title="Express — needs faster turnaround">⚡ Express</span>
						<?php endif; ?>
						<?php echo esc_html( $app->given_name . ' ' . $app->surname ); ?>
					</a>
					<span class="visati-status-badge visati-status-badge--<?php echo esc_attr( $app->status ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $app->status ) ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<p class="visati-widget__footer">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-dashboard' ) ); ?>">Open full Visati dashboard →</a>
	</p>
</div>
