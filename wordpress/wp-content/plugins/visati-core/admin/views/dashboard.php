<?php
/**
 * Admin dashboard view — stat cards + recent activity.
 *
 * @var int    $total_apps
 * @var int    $pending_apps
 * @var int    $submitted_apps
 * @var int    $approved_apps
 * @var int    $rejected_apps
 * @var int    $total_inquiries
 * @var int    $new_inquiries
 * @var float  $total_revenue
 * @var float  $revenue_30d
 * @var int    $paid_count
 * @var array  $recent_apps
 * @var array  $recent_inquiries
 * @var int    $storage_used_bytes
 * @var int    $storage_quota_gb
 * @var float  $storage_pct
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap visati-admin">
	<h1>Visati Dashboard</h1>

	<?php include VISATI_CORE_DIR . 'admin/views/dashboard-quick-actions.php'; ?>

	<div class="visati-stat-cards">
		<div class="visati-stat-card">
			<div class="visati-stat-card__number"><?php echo esc_html( $total_apps ); ?></div>
			<div class="visati-stat-card__label">Total Applications</div>
		</div>
		<div class="visati-stat-card visati-stat-card--pending">
			<div class="visati-stat-card__number"><?php echo esc_html( $pending_apps ); ?></div>
			<div class="visati-stat-card__label">Draft</div>
		</div>
		<div class="visati-stat-card visati-stat-card--submitted">
			<div class="visati-stat-card__number"><?php echo esc_html( $submitted_apps ); ?></div>
			<div class="visati-stat-card__label">Submitted</div>
		</div>
		<div class="visati-stat-card visati-stat-card--approved">
			<div class="visati-stat-card__number"><?php echo esc_html( $approved_apps ); ?></div>
			<div class="visati-stat-card__label">Approved (30d)</div>
		</div>
		<div class="visati-stat-card visati-stat-card--rejected">
			<div class="visati-stat-card__number"><?php echo esc_html( $rejected_apps ); ?></div>
			<div class="visati-stat-card__label">Rejected (30d)</div>
		</div>
		<div class="visati-stat-card">
			<div class="visati-stat-card__number"><?php echo esc_html( $total_inquiries ); ?></div>
			<div class="visati-stat-card__label">Total Inquiries</div>
		</div>
		<div class="visati-stat-card visati-stat-card--pending">
			<div class="visati-stat-card__number"><?php echo esc_html( $new_inquiries ); ?></div>
			<div class="visati-stat-card__label">New Inquiries</div>
		</div>
		<div class="visati-stat-card visati-stat-card--revenue">
			<div class="visati-stat-card__number">AED <?php echo esc_html( number_format( $total_revenue, 0 ) ); ?></div>
			<div class="visati-stat-card__label">Total Revenue</div>
		</div>
		<div class="visati-stat-card visati-stat-card--revenue">
			<div class="visati-stat-card__number">AED <?php echo esc_html( number_format( $revenue_30d, 0 ) ); ?></div>
			<div class="visati-stat-card__label">Revenue (30d)</div>
		</div>
		<div class="visati-stat-card visati-stat-card--approved">
			<div class="visati-stat-card__number"><?php echo esc_html( $paid_count ); ?></div>
			<div class="visati-stat-card__label">Paid Applications</div>
		</div>
	</div>

	<div class="visati-admin-grid">
		<div class="visati-admin-panel">
			<h2>Recent Applications</h2>
			<?php if ( empty( $recent_apps ) ) : ?>
				<p class="visati-admin-empty">No applications yet.</p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>ID</th>
							<th>Name</th>
							<th>Visa Type</th>
							<th>Status</th>
							<th>Date</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_apps as $app ) : ?>
							<?php $is_express = ( 'express' === $app->processing_tier ); ?>
							<tr<?php echo $is_express ? ' class="visati-express-row"' : ''; ?>>
								<td>
									<?php if ( $is_express ) : ?><span class="visati-express-badge" title="Express — needs faster turnaround">⚡ Express</span><?php endif; ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-applications&action=view&id=' . esc_attr( $app->id ) ) ); ?>">
										<?php echo esc_html( $app->id ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $app->given_name . ' ' . $app->surname ); ?></td>
								<td><?php echo esc_html( $app->visa_type_name ?: $app->visa_type_slug ); ?></td>
								<td><span class="visati-status-badge visati-status-badge--<?php echo esc_attr( $app->status ); ?>"><?php echo esc_html( ucfirst( $app->status ) ); ?></span></td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $app->created_at ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-applications' ) ); ?>">View all applications →</a></p>
		</div>

		<div class="visati-admin-sidebar">
			<div class="visati-storage-panel">
				<h2>Storage (Cloudflare R2)</h2>
				<?php
				$visati_circumference = 2 * M_PI * 52;
				$visati_filled         = ( $storage_pct / 100 ) * $visati_circumference;
				$visati_donut_color    = $storage_pct >= 90 ? '#dc3545' : ( $storage_pct >= 80 ? '#C9A84C' : '#0A1628' );
				?>
				<div class="visati-storage-donut">
					<svg viewBox="0 0 120 120" width="120" height="120">
						<circle cx="60" cy="60" r="52" fill="none" stroke="rgba(10,22,40,0.08)" stroke-width="14"></circle>
						<circle cx="60" cy="60" r="52" fill="none" stroke="<?php echo esc_attr( $visati_donut_color ); ?>" stroke-width="14"
							stroke-dasharray="<?php echo esc_attr( round( $visati_filled, 2 ) . ' ' . round( $visati_circumference, 2 ) ); ?>"
							stroke-linecap="round"
							transform="rotate(-90 60 60)"></circle>
					</svg>
					<div class="visati-storage-donut__label">
						<span class="visati-storage-donut__pct"><?php echo esc_html( $storage_pct ); ?>%</span>
					</div>
				</div>
				<p class="visati-storage-panel__meta">
					<?php echo esc_html( size_format( $storage_used_bytes ) ); ?> of <?php echo esc_html( $storage_quota_gb ); ?> GB used
				</p>
				<?php if ( $storage_pct >= 60 ) : ?>
					<p class="description">Running low on space? You can delete old applications to remove their documents from storage and free up room — see <a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-applications' ) ); ?>">Applications</a>.</p>
				<?php endif; ?>
				<p class="description"><a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-settings' ) ); ?>">Adjust quota →</a></p>
			</div>

		<div class="visati-admin-panel">
			<h2>Recent Inquiries</h2>
			<?php if ( empty( $recent_inquiries ) ) : ?>
				<p class="visati-admin-empty">No inquiries yet.</p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Name</th>
							<th>Email</th>
							<th>Subject</th>
							<th>Status</th>
							<th>Date</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_inquiries as $inq ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-inquiries&action=view&id=' . (int) $inq->id ) ); ?>">
										<?php echo esc_html( $inq->name ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $inq->email ); ?></td>
								<td><?php echo esc_html( $inq->subject ); ?></td>
								<td><span class="visati-status-badge visati-status-badge--<?php echo esc_attr( $inq->status ); ?>"><?php echo esc_html( ucfirst( $inq->status ) ); ?></span></td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $inq->created_at ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-inquiries' ) ); ?>">View all inquiries →</a></p>
		</div>
		</div>
	</div>
</div>
