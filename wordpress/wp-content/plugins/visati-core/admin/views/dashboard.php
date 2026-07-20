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
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap visati-admin">
	<h1>Visati Dashboard</h1>

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
							<tr>
								<td>
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
								<td><span class="visati-status-badge visati-status-badge--<?php echo $inq->resolved ? 'resolved' : 'new'; ?>"><?php echo $inq->resolved ? 'Resolved' : 'New'; ?></span></td>
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
