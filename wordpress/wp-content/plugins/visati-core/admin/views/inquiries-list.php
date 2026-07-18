<?php
/**
 * Inquiries list view.
 *
 * @var array  $inquiries
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
	<h1 class="wp-heading-inline">Inquiries</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-dashboard' ) ); ?>" class="page-title-action">Dashboard</a>

	<ul class="subsubsub">
		<?php
		$filters = array(
			''         => 'All',
			'new'      => 'New',
			'read'     => 'Read',
			'resolved' => 'Resolved',
		);
		foreach ( $filters as $val => $label ) :
			$count_q = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}visati_inquiries" . ( $val ? " WHERE status = %s" : '' ),
				$val
			) );
			$current = ( $status_filter === $val ) ? ' class="current"' : '';
			$url = $val ? add_query_arg( 'status', $val, admin_url( 'admin.php?page=visati-inquiries' ) ) : admin_url( 'admin.php?page=visati-inquiries' );
			?>
			<li><a href="<?php echo esc_url( $url ); ?>"<?php echo $current; ?>><?php echo esc_html( $label ); ?></a> (<?php echo esc_html( $count_q ); ?>)</li>
		<?php endforeach; ?>
	</ul>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:50px">ID</th>
				<th style="width:150px">Name</th>
				<th style="width:200px">Email</th>
				<th>Subject</th>
				<th style="width:100px">Status</th>
				<th style="width:120px">Date</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $inquiries ) ) : ?>
				<tr><td colspan="6">No inquiries found.</td></tr>
			<?php else : ?>
				<?php foreach ( $inquiries as $inq ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-inquiries&action=view&id=' . (int) $inq->id ) ); ?>">
								<strong><?php echo esc_html( $inq->id ); ?></strong>
							</a>
						</td>
						<td><?php echo esc_html( $inq->name ); ?></td>
						<td><?php echo esc_html( $inq->email ); ?></td>
						<td><?php echo esc_html( $inq->subject ); ?></td>
						<td><span class="visati-status-badge visati-status-badge--<?php echo esc_attr( $inq->status ); ?>"><?php echo esc_html( ucfirst( $inq->status ) ); ?></span></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $inq->created_at ) ) ); ?></td>
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
