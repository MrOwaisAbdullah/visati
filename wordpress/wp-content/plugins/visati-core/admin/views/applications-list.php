<?php
/**
 * Applications list view.
 *
 * @var array  $applications
 * @var int    $total
 * @var int    $page
 * @var int    $total_pages
 * @var string $status_filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap visati-admin">
	<h1 class="wp-heading-inline">Applications</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-dashboard' ) ); ?>" class="page-title-action">Dashboard</a>

	<ul class="subsubsub">
		<?php
		$filters = array(
			''           => 'All',
			'draft'      => 'Draft',
			'submitted'  => 'Submitted',
			'under_review' => 'Under Review',
			'processing' => 'Processing',
			'approved'   => 'Approved',
			'rejected'   => 'Rejected',
		);
		foreach ( $filters as $val => $label ) :
			$count_q = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}visati_applications" . ( $val ? " WHERE status = %s" : '' ),
				$val
			) );
			$current = ( $status_filter === $val ) ? ' class="current"' : '';
			$url = $val ? add_query_arg( 'status', $val, admin_url( 'admin.php?page=visati-applications' ) ) : admin_url( 'admin.php?page=visati-applications' );
			?>
			<li><a href="<?php echo esc_url( $url ); ?>"<?php echo $current; ?>><?php echo esc_html( $label ); ?></a> (<?php echo esc_html( $count_q ); ?>)</li>
		<?php endforeach; ?>
	</ul>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:140px">Application ID</th>
				<th style="width:150px">Full Name</th>
				<th>Nationality</th>
				<th>Visa Type</th>
				<th style="width:110px">Status</th>
				<th style="width:100px">Date</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $applications ) ) : ?>
				<tr><td colspan="6">No applications found.</td></tr>
			<?php else : ?>
				<?php foreach ( $applications as $app ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-applications&action=view&id=' . esc_attr( $app->id ) ) ); ?>">
								<strong><?php echo esc_html( $app->id ); ?></strong>
							</a>
						</td>
						<td><?php echo esc_html( $app->given_name . ' ' . $app->surname ); ?></td>
						<td><?php echo esc_html( $app->nationality ); ?></td>
						<td><?php echo esc_html( $app->visa_type_name ?: $app->visa_type_slug ); ?></td>
						<td><span class="visati-status-badge visati-status-badge--<?php echo esc_attr( $app->status ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $app->status ) ) ); ?></span></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $app->created_at ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post( paginate_links( array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $page,
					'total'   => $total_pages,
				) ) );
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
