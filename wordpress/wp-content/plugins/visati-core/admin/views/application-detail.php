<?php
/**
 * Application detail view.
 *
 * @var object $app
 * @var string $visa_name
 * @var array  $history
 * @var array  $signed_docs
 * @var array  $payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap visati-admin">
	<h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-applications' ) ); ?>">← Applications</a>
		&mdash; <?php echo esc_html( $app->id ); ?>
	</h1>

	<div class="visati-app-detail">
		<div class="visati-app-detail__main">
			<h2>Application Details</h2>
			<table>
				<tr><th>Application ID</th><td><code><?php echo esc_html( $app->id ); ?></code></td></tr>
				<tr><th>Status</th><td><span class="visati-status-badge visati-status-badge--<?php echo esc_attr( $app->status ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $app->status ) ) ); ?></span></td></tr>
				<tr><th>Full Name</th><td><?php echo esc_html( $app->given_name . ' ' . $app->surname ); ?></td></tr>
				<tr><th>Email</th><td><?php echo esc_html( $app->applicant_email ); ?></td></tr>
				<tr><th>Nationality</th><td><?php echo esc_html( $app->nationality ); ?></td></tr>
				<tr><th>Passport Number</th><td><?php echo esc_html( $app->passport_number ); ?></td></tr>
				<tr><th>Passport Expiry</th><td><?php echo esc_html( $app->passport_expiry ); ?></td></tr>
				<tr><th>Date of Birth</th><td><?php echo esc_html( $app->date_of_birth ); ?></td></tr>
				<tr><th>Visa Type</th><td><?php echo esc_html( $visa_name ?: $app->visa_type_slug ); ?></td></tr>
				<tr><th>Processing Tier</th><td><?php echo esc_html( ucfirst( $app->processing_tier ) ); ?></td></tr>
				<tr><th>Travel Date</th><td><?php echo esc_html( $app->travel_date ); ?></td></tr>
				<?php
				$travelers = ! empty( $app->travelers_data ) ? json_decode( $app->travelers_data, true ) : array();
				if ( ! empty( $travelers ) ) :
				?>
					<tr>
						<th>Additional Travelers</th>
						<td>
							<table class="visati-travelers-table">
								<thead>
									<tr><th>#</th><th>Relationship</th><th>Name</th><th>Passport</th><th>DOB</th></tr>
								</thead>
								<tbody>
									<?php foreach ( $travelers as $i => $t ) : ?>
										<tr>
											<td><?php echo esc_html( $i + 1 ); ?></td>
											<td><?php echo esc_html( ucfirst( $t['relationship'] ?? '' ) ); ?></td>
											<td><?php echo esc_html( $t['name'] ?? '' ); ?></td>
											<td><code><?php echo esc_html( $t['passport'] ?? '' ); ?></code></td>
											<td><?php echo esc_html( $t['dob'] ?? '' ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</td>
					</tr>
				<?php endif; ?>
				<tr><th>Created</th><td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $app->created_at ) ) ); ?></td></tr>
				<tr><th>Last Updated</th><td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $app->updated_at ) ) ); ?></td></tr>
			</table>

			<?php if ( ! empty( $history ) ) : ?>
				<h2>Status History</h2>
				<ul class="visati-timeline">
					<?php foreach ( array_reverse( $history ) as $entry ) : ?>
						<li>
							<div class="visati-timeline__status"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $entry->status ) ) ); ?></div>
							<?php if ( $entry->note ) : ?>
								<div class="visati-timeline__note"><?php echo esc_html( $entry->note ); ?></div>
							<?php endif; ?>
							<div class="visati-timeline__date">
								<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at ) ) ); ?>
								<?php if ( $entry->changed_by ) : ?>
									&mdash; by <?php echo esc_html( get_the_author_meta( 'display_name', $entry->changed_by ) ); ?>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

		<h2>Payment</h2>
		<?php if ( ! empty( $payments ) ) : ?>
			<table class="visati-payments-table">
				<thead>
					<tr>
						<th>Amount</th>
						<th>Status</th>
						<th>Stripe Payment Intent</th>
						<th>Date</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $payments as $pay ) : ?>
						<tr>
							<td><strong>AED <?php echo esc_html( number_format( (float) $pay->amount_aed, 2 ) ); ?></strong></td>
							<td><span class="visati-status-badge visati-status-badge--<?php echo esc_attr( $pay->status ); ?>"><?php echo esc_html( ucfirst( $pay->status ) ); ?></span></td>
							<td><code><?php echo esc_html( $pay->stripe_payment_intent_id ); ?></code></td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $pay->created_at ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="visati-no-payment">No payment recorded for this application yet.</p>
		<?php endif; ?>

		<?php if ( ! empty( $signed_docs ) ) : ?>
			<h2>Documents</h2>
			<ul class="visati-doc-list">
				<?php foreach ( $signed_docs as $doc ) :
					$is_image = in_array( strtolower( pathinfo( $doc->filename, PATHINFO_EXTENSION ) ), array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ) );
					$is_pdf   = strtolower( pathinfo( $doc->filename, PATHINFO_EXTENSION ) ) === 'pdf';
					$data_type = $is_image ? 'image' : ( $is_pdf ? 'pdf' : 'link' );
					?>
					<li>
						<span>
							<strong><?php echo esc_html( $doc->document_type ); ?></strong>
							&mdash; <?php echo esc_html( $doc->filename ); ?>
							(<?php echo esc_html( size_format( $doc->size_bytes ) ); ?>)
						</span>
						<?php if ( ! empty( $doc->preview_url ) ) : ?>
							<a href="#"
							   class="visati-doc-viewer-link"
							   data-url="<?php echo esc_url( $doc->preview_url ); ?>"
							   data-type="<?php echo esc_attr( $data_type ); ?>"
							   data-title="<?php echo esc_attr( $doc->document_type . ' — ' . $doc->filename ); ?>">
								View
							</a>
							<a href="<?php echo esc_url( $doc->preview_url ); ?>" target="_blank" rel="noopener" class="visati-doc-download-link">Download</a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<!-- Document Viewer Modal -->
			<div id="visati-doc-modal" class="visati-doc-modal" style="display:none;">
				<div class="visati-doc-modal__overlay"></div>
				<div class="visati-doc-modal__container">
					<div class="visati-doc-modal__header">
						<span class="visati-doc-modal__title"></span>
						<button type="button" class="visati-doc-modal__close">&times;</button>
					</div>
					<div class="visati-doc-modal__body"></div>
				</div>
			</div>

			<script>
			(function(){
				var modal = document.getElementById('visati-doc-modal');
				if (!modal) return;
				var overlay  = modal.querySelector('.visati-doc-modal__overlay');
				var closeBtn = modal.querySelector('.visati-doc-modal__close');
				var title    = modal.querySelector('.visati-doc-modal__title');
				var body     = modal.querySelector('.visati-doc-modal__body');

				function closeModal() {
					modal.style.display = 'none';
					body.innerHTML = '';
					document.body.style.overflow = '';
				}

				function openModal(url, type, docTitle) {
					title.textContent = docTitle;
					body.innerHTML = '';
					document.body.style.overflow = 'hidden';

					if (type === 'image') {
						body.innerHTML = '<img src="' + url + '" style="max-width:100%;max-height:80vh;display:block;margin:0 auto;" />';
					} else if (type === 'pdf') {
						body.innerHTML = '<iframe src="' + url + '#toolbar=1&navpanes=0" style="width:100%;height:80vh;border:none;"></iframe>';
					} else {
						window.open(url, '_blank');
						closeModal();
						return;
					}
					modal.style.display = 'flex';
				}

				overlay.addEventListener('click', closeModal);
				closeBtn.addEventListener('click', closeModal);
				document.addEventListener('keydown', function(e) {
					if (e.key === 'Escape' && modal.style.display === 'flex') closeModal();
				});

				document.querySelectorAll('.visati-doc-viewer-link').forEach(function(link) {
					link.addEventListener('click', function(e) {
						e.preventDefault();
						openModal(this.dataset.url, this.dataset.type, this.dataset.title);
					});
				});
			})();
			</script>
		<?php endif; ?>
		</div>

		<div class="visati-app-detail__sidebar">
			<h3>Update Status</h3>
			<div class="visati-status-form">
				<form method="post">
					<?php wp_nonce_field( 'visati_status_update' ); ?>
					<select name="new_status" required>
						<option value="">Select status…</option>
						<?php
						$statuses = array( 'draft', 'submitted', 'under_review', 'processing', 'approved', 'rejected' );
						foreach ( $statuses as $s ) :
							$selected = ( $app->status === $s ) ? 'selected' : '';
							?>
							<option value="<?php echo esc_attr( $s ); ?>" <?php echo $selected; ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $s ) ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<textarea name="status_note" placeholder="Optional note (visible to applicant)…"></textarea>
					<button type="submit" name="visati_update_status" class="button button-primary">Update Status</button>
				</form>
			</div>

			<h3>Quick Actions</h3>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-applications' ) ); ?>">← Back to list</a>
			</p>
		</div>
	</div>
</div>
