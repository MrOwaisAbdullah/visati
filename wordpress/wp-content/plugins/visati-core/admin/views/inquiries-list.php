<?php
/**
 * Inquiries list view.
 *
 * @var array  $inquiries
 * @var int    $total
 * @var int    $page
 * @var int    $total_pages
 * @var string $status_filter
 * @var string $search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap visati-admin">
	<div class="visati-list-header">
		<h1 class="visati-list-header__title">Inquiries</h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-dashboard' ) ); ?>" class="button visati-list-header__dashboard-btn">
			<span class="dashicons dashicons-dashboard" aria-hidden="true"></span> Dashboard
		</a>
	</div>

	<div class="visati-list-toolbar">
		<div class="visati-list-tabs">
			<?php
			$filters = array(
				''         => 'All',
				'new'      => 'New',
				'read'     => 'Read',
				'resolved' => 'Resolved',
			);
			foreach ( $filters as $val => $label ) :
				$count_q = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}visati_inquiries" . ( $val ? ' WHERE status = %s' : '' ),
					$val
				) );
				$is_current = ( $status_filter === $val );
				$url = $val ? add_query_arg( 'status', $val, admin_url( 'admin.php?page=visati-inquiries' ) ) : admin_url( 'admin.php?page=visati-inquiries' );
				if ( $search ) {
					$url = add_query_arg( 's', $search, $url );
				}
				?>
				<a href="<?php echo esc_url( $url ); ?>" class="visati-list-tab<?php echo $is_current ? ' is-current' : ''; ?>">
					<?php echo esc_html( $label ); ?> <span class="visati-list-tab__count"><?php echo esc_html( $count_q ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<form method="get" class="visati-list-search">
			<input type="hidden" name="page" value="visati-inquiries">
			<?php if ( $status_filter ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>"><?php endif; ?>
			<input type="search" name="s" class="visati-list-search__input" placeholder="Search name, email, subject, message…" value="<?php echo esc_attr( $search ); ?>">
			<button type="submit" class="button">Filter</button>
			<?php if ( $search ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 's' => false ) ) ); ?>" class="button">Clear</a>
			<?php endif; ?>
		</form>
	</div>

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
