<?php
/**
 * Shared Quick Actions button row — used on both the full Visati Dashboard
 * page and the "Visati — Quick Actions" widget on the native WP Dashboard.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="visati-quick-actions">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-applications&status=submitted' ) ); ?>" class="visati-quick-action">
		<span class="dashicons dashicons-media-document" aria-hidden="true"></span> Review Submitted
	</a>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-inquiries&status=new' ) ); ?>" class="visati-quick-action">
		<span class="dashicons dashicons-email-alt" aria-hidden="true"></span> New Inquiries
	</a>
	<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=visati_discount' ) ); ?>" class="visati-quick-action">
		<span class="dashicons dashicons-tag" aria-hidden="true"></span> Add Discount Code
	</a>
	<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=visati_visa_type' ) ); ?>" class="visati-quick-action">
		<span class="dashicons dashicons-airplane" aria-hidden="true"></span> Add Visa Type
	</a>
	<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=visati_faq' ) ); ?>" class="visati-quick-action">
		<span class="dashicons dashicons-editor-help" aria-hidden="true"></span> Add FAQ
	</a>
	<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=visati_testimonial' ) ); ?>" class="visati-quick-action">
		<span class="dashicons dashicons-star-filled" aria-hidden="true"></span> Add Testimonial
	</a>
	<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=visati_job' ) ); ?>" class="visati-quick-action">
		<span class="dashicons dashicons-briefcase" aria-hidden="true"></span> Add Job Opening
	</a>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=visati-settings' ) ); ?>" class="visati-quick-action">
		<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> Settings
	</a>
</div>
