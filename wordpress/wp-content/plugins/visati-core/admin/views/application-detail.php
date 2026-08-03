<?php
/**
 * Application detail view.
 *
 * @var object $app
 * @var string $visa_name
 * @var array  $history
 * @var array  $signed_docs
 * @var array  $payments
 * @var array  $traveler_slots
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Split out the admin-issued visa documents (if any — one per upload slot)
// from applicant-submitted documents, and index the visa docs by slot key.
$visati_visa_docs_by_slot = array();
$visati_applicant_docs    = array();
foreach ( $signed_docs as $visati_doc ) {
	if ( 0 === strpos( $visati_doc->document_type, 'issued_visa_' ) ) {
		$visati_slot_key = substr( $visati_doc->document_type, strlen( 'issued_visa_' ) );
		$visati_visa_docs_by_slot[ $visati_slot_key ] = $visati_doc;
	} else {
		$visati_applicant_docs[] = $visati_doc;
	}
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

		<?php if ( ! empty( $visati_applicant_docs ) ) : ?>
			<h2>Documents</h2>
			<ul class="visati-doc-list">
				<?php foreach ( $visati_applicant_docs as $doc ) :
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

			<?php
			$visati_is_multi_traveler = isset( $traveler_slots['combined'] );
			// Default the combined/separate toggle to whichever mode already has a
			// file — prefer "separate" if any per-traveler slot is filled, since
			// that's a more deliberate signal than the default "combined" state.
			$visati_visa_mode = 'combined';
			if ( $visati_is_multi_traveler ) {
				foreach ( $traveler_slots as $visati_check_key => $visati_check_label ) {
					if ( 'combined' !== $visati_check_key && isset( $visati_visa_docs_by_slot[ $visati_check_key ] ) ) {
						$visati_visa_mode = 'separate';
						break;
					}
				}
			}
			?>

			<h3>Visa Documents</h3>
			<?php if ( $visati_is_multi_traveler ) : ?>
				<p class="description">Choose whether to upload one combined file for everyone, or a separate file per traveler. Accepted: PDF, JPG, PNG, ZIP, or RAR — 10 MB max per file.</p>
				<p class="visati-visa-mode-toggle">
					<label><input type="radio" name="visati_visa_mode_display" value="combined" <?php checked( $visati_visa_mode, 'combined' ); ?>> One combined file</label>
					<label><input type="radio" name="visati_visa_mode_display" value="separate" <?php checked( $visati_visa_mode, 'separate' ); ?>> Separate file per traveler</label>
				</p>
			<?php else : ?>
				<p class="description">Accepted: PDF, JPG, PNG, ZIP, or RAR — 10 MB max.</p>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'visati_upload_visa_docs_' . $app->id ); ?>
				<?php foreach ( $traveler_slots as $visati_slot_key => $visati_slot_label ) : ?>
					<?php
					$visati_slot_doc   = $visati_visa_docs_by_slot[ $visati_slot_key ] ?? null;
					$visati_slot_group = ( 'combined' === $visati_slot_key ) ? 'combined' : 'separate';
					$visati_hide_slot  = $visati_is_multi_traveler && ( $visati_slot_group !== $visati_visa_mode );
					?>
					<div class="visati-visa-doc-box"
						<?php if ( $visati_is_multi_traveler ) : ?>
							data-visa-doc-group="<?php echo esc_attr( $visati_slot_group ); ?>"
							<?php if ( $visati_hide_slot ) : ?>style="display:none;"<?php endif; ?>
						<?php endif; ?>
					>
						<p><strong><?php echo esc_html( $visati_slot_label ); ?></strong></p>
						<?php if ( $visati_slot_doc ) : ?>
							<p>
								Current file: <?php echo esc_html( $visati_slot_doc->filename ); ?>
								(<?php echo esc_html( size_format( $visati_slot_doc->size_bytes ) ); ?>)
							</p>
							<div class="visati-visa-doc-box__actions">
								<?php if ( ! empty( $visati_slot_doc->preview_url ) ) : ?>
									<a href="<?php echo esc_url( $visati_slot_doc->preview_url ); ?>" target="_blank" rel="noopener" class="button">View / Download</a>
								<?php endif; ?>
								<button type="submit" form="visati-remove-visa-doc-<?php echo esc_attr( $visati_slot_key ); ?>" class="button" style="color:#dc3545;border-color:#dc3545;">Remove</button>
							</div>
						<?php else : ?>
							<p class="description">No file uploaded yet.</p>
						<?php endif; ?>
						<input type="file" name="visa_documents[<?php echo esc_attr( $visati_slot_key ); ?>]" accept="application/pdf,image/jpeg,image/png,.zip,.rar">
					</div>
				<?php endforeach; ?>
				<button type="submit" name="visati_upload_visa_docs" class="button button-primary">Upload</button>
			</form>

			<h3>Danger Zone</h3>
			<form method="post" onsubmit="return confirm('Permanently delete application <?php echo esc_js( $app->id ); ?>? This removes its uploaded documents from storage too. This cannot be undone.');">
				<?php wp_nonce_field( 'visati_delete_application_' . $app->id ); ?>
				<button type="submit" name="visati_delete_application" class="button" style="color:#dc3545;border-color:#dc3545;">Delete Application</button>
			</form>
		</div>
	</div>

	<?php
	// Standalone "Remove visa document" forms — one per slot that currently has
	// a file. Kept outside the upload form above (forms cannot nest); each
	// slot's visible "Remove" button lives inside the upload form's markup but
	// triggers ITS OWN form via the HTML5 `form="…"` attribute.
	foreach ( $traveler_slots as $visati_slot_key => $visati_slot_label ) :
		if ( ! isset( $visati_visa_docs_by_slot[ $visati_slot_key ] ) ) {
			continue;
		}
		?>
		<form method="post" id="visati-remove-visa-doc-<?php echo esc_attr( $visati_slot_key ); ?>" style="display:none;" onsubmit="return confirm('Remove the visa document for <?php echo esc_js( $visati_slot_label ); ?>? The applicant will no longer see this download link on the Track page until you upload a new one.');">
			<?php wp_nonce_field( 'visati_delete_visa_doc_' . $app->id . '_' . $visati_slot_key ); ?>
			<input type="hidden" name="traveler_key" value="<?php echo esc_attr( $visati_slot_key ); ?>">
			<input type="hidden" name="visati_delete_visa_doc" value="1">
		</form>
	<?php endforeach; ?>
</div>
<?php if ( isset( $traveler_slots['combined'] ) ) : ?>
<script>
(function () {
	document.querySelectorAll( 'input[name="visati_visa_mode_display"]' ).forEach( function ( radio ) {
		radio.addEventListener( 'change', function () {
			var mode = this.value;
			document.querySelectorAll( '[data-visa-doc-group]' ).forEach( function ( box ) {
				box.style.display = ( box.dataset.visaDocGroup === mode ) ? '' : 'none';
			} );
		} );
	} );
})();
</script>
<?php endif; ?>
