<?php
/**
 * Inquiry detail view.
 *
 * @var object $inquiry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap visati-admin">
	<h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-inquiries' ) ); ?>">← Inquiries</a>
		&mdash; Inquiry #<?php echo esc_html( $inquiry->id ); ?>
	</h1>

	<div class="visati-app-detail">
		<div class="visati-app-detail__main">
			<h2>Inquiry Details</h2>
			<table>
				<tr><th>Name</th><td><?php echo esc_html( $inquiry->name ); ?></td></tr>
				<tr><th>Email</th><td><a href="mailto:<?php echo esc_attr( $inquiry->email ); ?>"><?php echo esc_html( $inquiry->email ); ?></a></td></tr>
				<tr><th>Phone</th><td><?php echo esc_html( $inquiry->phone ?: '—' ); ?></td></tr>
				<tr><th>Subject</th><td><?php echo esc_html( $inquiry->subject ); ?></td></tr>
				<tr><th>Status</th><td><span class="visati-status-badge visati-status-badge--<?php echo esc_attr( $inquiry->status ); ?>"><?php echo esc_html( ucfirst( $inquiry->status ) ); ?></span></td></tr>
				<tr><th>Date</th><td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $inquiry->created_at ) ) ); ?></td></tr>
			</table>

			<h2>Message</h2>
			<div style="background:#f8f9fb;padding:20px;border-radius:8px;white-space:pre-wrap;line-height:1.6;"><?php echo esc_html( $inquiry->message ); ?></div>
		</div>

		<div class="visati-app-detail__sidebar">
			<h3>Actions</h3>
			<form method="post" style="margin-bottom:16px;">
				<?php wp_nonce_field( 'visati_resolve_inquiry' ); ?>
				<?php if ( 'resolved' === $inquiry->status ) : ?>
					<button type="submit" name="visati_toggle_resolve" class="button">Mark as Read</button>
				<?php else : ?>
					<button type="submit" name="visati_toggle_resolve" class="button button-primary">Mark as Resolved</button>
				<?php endif; ?>
			</form>

			<h3>Reply</h3>
			<p>
				<a href="mailto:<?php echo esc_attr( $inquiry->email ); ?>?subject=<?php echo esc_attr( 'Re: ' . $inquiry->subject ); ?>" class="button">
					Reply via Email
				</a>
			</p>

			<p style="margin-top:20px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-inquiries' ) ); ?>">← Back to list</a>
			</p>
		</div>
	</div>
</div>
