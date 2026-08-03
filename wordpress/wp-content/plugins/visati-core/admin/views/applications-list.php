<?php
/**
 * Applications list view.
 *
 * @var array  $applications
 * @var int    $total
 * @var int    $page
 * @var int    $total_pages
 * @var string $status_filter
 * @var string $search
 * @var string $visa_filter
 * @var array  $visa_types
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap visati-admin">
	<div class="visati-list-header">
		<h1 class="visati-list-header__title">Applications</h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-dashboard' ) ); ?>" class="button visati-list-header__dashboard-btn">
			<span class="dashicons dashicons-dashboard" aria-hidden="true"></span> Dashboard
		</a>
	</div>

	<div class="visati-list-toolbar">
		<div class="visati-list-tabs">
			<?php
			$filters = array(
				''             => 'All',
				'draft'        => 'Draft',
				'submitted'    => 'Submitted',
				'under_review' => 'Under Review',
				'processing'   => 'Processing',
				'approved'     => 'Approved',
				'rejected'     => 'Rejected',
			);
			foreach ( $filters as $val => $label ) :
				$count_q = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}visati_applications" . ( $val ? ' WHERE status = %s' : '' ),
					$val
				) );
				$is_current = ( $status_filter === $val );
				$url = $val ? add_query_arg( 'status', $val, admin_url( 'admin.php?page=visati-applications' ) ) : admin_url( 'admin.php?page=visati-applications' );
				if ( $search ) {
					$url = add_query_arg( 's', $search, $url );
				}
				if ( $visa_filter ) {
					$url = add_query_arg( 'visa_type', $visa_filter, $url );
				}
				?>
				<a href="<?php echo esc_url( $url ); ?>" class="visati-list-tab<?php echo $is_current ? ' is-current' : ''; ?>">
					<?php echo esc_html( $label ); ?> <span class="visati-list-tab__count"><?php echo esc_html( $count_q ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<form method="get" class="visati-list-search">
			<input type="hidden" name="page" value="visati-applications">
			<?php if ( $status_filter ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>"><?php endif; ?>
			<input type="search" name="s" class="visati-list-search__input" placeholder="Search name, ID, email, nationality…" value="<?php echo esc_attr( $search ); ?>">
			<select name="visa_type" class="visati-list-search__select">
				<option value="">All visa types</option>
				<?php foreach ( $visa_types as $vt ) : ?>
					<option value="<?php echo esc_attr( $vt->post_name ); ?>" <?php selected( $visa_filter, $vt->post_name ); ?>><?php echo esc_html( $vt->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button">Filter</button>
			<?php if ( $search || $visa_filter ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 's' => false, 'visa_type' => false ) ) ); ?>" class="button">Clear</a>
			<?php endif; ?>
		</form>
	</div>

	<form method="post" id="visati-applications-bulk-form" onsubmit="return visatiConfirmBulkDelete(event);">
		<?php wp_nonce_field( 'visati_bulk_delete_applications' ); ?>
		<div class="visati-bulk-actions">
			<button type="submit" name="visati_bulk_delete" class="button" style="color:#dc3545;border-color:#dc3545;">Delete Selected</button>
		</div>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:24px"><input type="checkbox" id="visati-select-all"></th>
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
					<tr><td colspan="7">No applications found.</td></tr>
				<?php else : ?>
					<?php foreach ( $applications as $app ) : ?>
						<tr>
							<td><input type="checkbox" name="app_ids[]" value="<?php echo esc_attr( $app->id ); ?>" class="visati-row-checkbox"></td>
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
	</form>
	<script>
	(function() {
		var selectAll = document.getElementById( 'visati-select-all' );
		if ( selectAll ) {
			selectAll.addEventListener( 'change', function() {
				document.querySelectorAll( '.visati-row-checkbox' ).forEach( function( cb ) { cb.checked = selectAll.checked; } );
			} );
		}
	})();
	function visatiConfirmBulkDelete( e ) {
		var checked = document.querySelectorAll( '.visati-row-checkbox:checked' ).length;
		if ( 0 === checked ) {
			e.preventDefault();
			alert( 'Select at least one application to delete.' );
			return false;
		}
		return confirm( 'Permanently delete ' + checked + ' selected application(s)? This removes their uploaded documents from storage too. This cannot be undone.' );
	}
	</script>

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
