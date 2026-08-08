<?php
/**
 * Admin meta boxes for Visa Types, FAQs, and Testimonials.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Visati_Core_Meta_Boxes {

	/**
	 * Hook meta box registration into WordPress.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post_visati_visa_type', array( __CLASS__, 'save_visa_type' ), 10, 2 );
		add_action( 'save_post_visati_faq', array( __CLASS__, 'save_faq' ), 10, 2 );
		add_action( 'save_post_visati_testimonial', array( __CLASS__, 'save_testimonial' ), 10, 2 );
		add_action( 'save_post_visati_discount', array( __CLASS__, 'save_discount' ), 10, 2 );
		add_action( 'save_post_visati_job', array( __CLASS__, 'save_job' ), 10, 2 );
	}

	/**
	 * Register all meta boxes.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_meta_box(
			'visati_visa_details',
			__( 'Visa Details', 'visati-core' ),
			array( __CLASS__, 'render_visa_details' ),
			'visati_visa_type',
			'normal',
			'high'
		);

		add_meta_box(
			'visati_faq_details',
			__( 'FAQ Details', 'visati-core' ),
			array( __CLASS__, 'render_faq_details' ),
			'visati_faq',
			'normal',
			'high'
		);

		add_meta_box(
			'visati_testimonial_details',
			__( 'Testimonial Details', 'visati-core' ),
			array( __CLASS__, 'render_testimonial_details' ),
			'visati_testimonial',
			'normal',
			'high'
		);

		add_meta_box(
			'visati_discount_details',
			__( 'Discount Details', 'visati-core' ),
			array( __CLASS__, 'render_discount_details' ),
			'visati_discount',
			'normal',
			'high'
		);

		add_meta_box(
			'visati_job_details',
			__( 'Job Details', 'visati-core' ),
			array( __CLASS__, 'render_job_details' ),
			'visati_job',
			'normal',
			'high'
		);
	}

	// ─── Visa Type Meta Box ───────────────────────────────────────────

	/**
	 * Render the Visa Details meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public static function render_visa_details( WP_Post $post ): void {
		wp_nonce_field( 'visati_visa_details_nonce', 'visati_visa_nonce' );

		$fields = array(
			'_visati_tagline'       => array(
				'label' => __( 'Card Tagline', 'visati-core' ),
				'desc'  => __( 'Short description shown on the visa card (e.g. "Perfect for short trips").', 'visati-core' ),
				'type'  => 'text',
			),
			'_visati_price_usd'     => array(
				'label' => __( 'Price (USD)', 'visati-core' ),
				'desc'  => __( 'This is the amount actually charged at checkout.', 'visati-core' ),
				'type'  => 'number',
			),
			'_visati_price_aed'     => array(
				'label' => __( 'Price (AED)', 'visati-core' ),
				'desc'  => __( 'Reference only, shown alongside the USD price — not charged.', 'visati-core' ),
				'type'  => 'number',
			),
			'_visati_extra_traveler_pricing_mode' => array(
				'label'   => __( 'Extra Traveler Pricing', 'visati-core' ),
				'desc'    => __( 'How additional travelers (beyond the primary applicant) are charged. Set the price to 0 in either mode for no charge.', 'visati-core' ),
				'type'    => 'select',
				'options' => array(
					'per_traveler' => __( 'Per traveler — price × number of extra travelers', 'visati-core' ),
					'flat'         => __( 'One-time flat fee — charged once if any travelers are added, regardless of count', 'visati-core' ),
				),
			),
			'_visati_extra_traveler_price_usd' => array(
				'label' => __( 'Extra Traveler Price (USD)', 'visati-core' ),
				'desc'  => __( 'Amount used by the pricing mode above. 0 bundles additional travelers at no extra charge.', 'visati-core' ),
				'type'  => 'number',
			),
			'_visati_duration_days' => array(
				'label' => __( 'Duration (days)', 'visati-core' ),
				'desc'  => __( 'Visa validity in days (e.g. 14, 30, 60).', 'visati-core' ),
				'type'  => 'number',
			),
			'_visati_entry_type'    => array(
				'label'   => __( 'Entry Type', 'visati-core' ),
				'desc'    => __( 'Single or multiple entry.', 'visati-core' ),
				'type'    => 'select',
				'options' => array(
					'single' => __( 'Single Entry', 'visati-core' ),
					'multi'  => __( 'Multiple Entry', 'visati-core' ),
				),
			),
			'_visati_is_featured'   => array(
				'label' => __( 'Featured', 'visati-core' ),
				'desc'  => __( 'Mark this visa type as featured (shows "Most Popular" badge).', 'visati-core' ),
				'type'  => 'checkbox',
			),
			'_visati_badge_text'    => array(
				'label' => __( 'Custom Badge Text', 'visati-core' ),
				'desc'  => __( 'Override the badge label (leave empty for default "Most Popular" if featured).', 'visati-core' ),
				'type'  => 'text',
			),
		);

		self::render_fields( $post, $fields );

		// Rendered separately (not via the shared array) so a brand-new/never-saved
		// visa type defaults to "has express" checked, matching the original data model.
		$has_express = metadata_exists( 'post', $post->ID, '_visati_has_express' )
			? (int) get_post_meta( $post->ID, '_visati_has_express', true )
			: 1;
		?>
		<table class="form-table visati-meta-box" role="presentation">
			<tr>
				<th style="width:200px;"><?php esc_html_e( 'Express Available', 'visati-core' ); ?></th>
				<td>
					<label><input type="checkbox" name="_visati_has_express" value="1" <?php checked( $has_express, 1 ); ?>> <?php esc_html_e( 'Offer express processing for this visa type', 'visati-core' ); ?></label>
					<p class="description"><?php esc_html_e( 'Uncheck to hide the Express option on the apply form for this visa type (e.g. Visa Extension).', 'visati-core' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save Visa Type meta fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_visa_type( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['visati_visa_nonce'] ) || ! wp_verify_nonce( $_POST['visati_visa_nonce'], 'visati_visa_details_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$text_fields = array( '_visati_tagline', '_visati_badge_text', '_visati_entry_type' );
		foreach ( $text_fields as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		// Whitelisted (not just sanitized) since this mode value drives what a
		// customer is actually charged — an unexpected value must never silently
		// fall through into the pricing calculation.
		if ( isset( $_POST['_visati_extra_traveler_pricing_mode'] ) ) {
			$mode = sanitize_text_field( wp_unslash( $_POST['_visati_extra_traveler_pricing_mode'] ) );
			update_post_meta( $post_id, '_visati_extra_traveler_pricing_mode', in_array( $mode, array( 'per_traveler', 'flat' ), true ) ? $mode : 'per_traveler' );
		}

		$number_fields = array( '_visati_price_aed', '_visati_price_usd', '_visati_extra_traveler_price_usd', '_visati_duration_days' );
		foreach ( $number_fields as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, absint( $_POST[ $key ] ) );
			}
		}

		update_post_meta( $post_id, '_visati_is_featured', isset( $_POST['_visati_is_featured'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_visati_has_express', isset( $_POST['_visati_has_express'] ) ? 1 : 0 );
	}

	// ─── FAQ Meta Box ─────────────────────────────────────────────────

	/**
	 * Render the FAQ Details meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public static function render_faq_details( WP_Post $post ): void {
		wp_nonce_field( 'visati_faq_details_nonce', 'visati_faq_nonce' );

		$fields = array(
			'_visati_faq_question' => array(
				'label' => __( 'Question', 'visati-core' ),
				'desc'  => __( 'The question shown in the accordion header.', 'visati-core' ),
				'type'  => 'text',
			),
			'_visati_faq_answer'   => array(
				'label' => __( 'Answer', 'visati-core' ),
				'desc'  => __( 'The full answer text for this FAQ.', 'visati-core' ),
				'type'  => 'textarea',
			),
			'_visati_faq_category' => array(
				'label' => __( 'Category', 'visati-core' ),
				'desc'  => __( 'Grouping label (e.g. "General", "Visa Process", "Pricing", "Documents").', 'visati-core' ),
				'type'  => 'text',
			),
		);

		self::render_fields( $post, $fields );
	}

	/**
	 * Save FAQ meta fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_faq( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['visati_faq_nonce'] ) || ! wp_verify_nonce( $_POST['visati_faq_nonce'], 'visati_faq_details_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_visati_faq_question'] ) ) {
			update_post_meta( $post_id, '_visati_faq_question', sanitize_text_field( wp_unslash( $_POST['_visati_faq_question'] ) ) );
		}
		if ( isset( $_POST['_visati_faq_answer'] ) ) {
			update_post_meta( $post_id, '_visati_faq_answer', sanitize_textarea_field( wp_unslash( $_POST['_visati_faq_answer'] ) ) );
		}
		if ( isset( $_POST['_visati_faq_category'] ) ) {
			update_post_meta( $post_id, '_visati_faq_category', sanitize_text_field( wp_unslash( $_POST['_visati_faq_category'] ) ) );
		}
	}

	// ─── Testimonial Meta Box ─────────────────────────────────────────

	/**
	 * Render the Testimonial Details meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public static function render_testimonial_details( WP_Post $post ): void {
		wp_nonce_field( 'visati_testimonial_details_nonce', 'visati_testimonial_nonce' );

		$fields = array(
			'_visati_country'      => array(
				'label' => __( 'Country', 'visati-core' ),
				'desc'  => __( 'Reviewer\'s country (e.g. "IN", "PK", "GB").', 'visati-core' ),
				'type'  => 'text',
			),
			'_visati_rating'       => array(
				'label'   => __( 'Rating', 'visati-core' ),
				'desc'    => __( 'Star rating from 1 to 5.', 'visati-core' ),
				'type'    => 'select',
				'options' => array(
					'5' => __( '5 — Excellent', 'visati-core' ),
					'4' => __( '4 — Very Good', 'visati-core' ),
					'3' => __( '3 — Good', 'visati-core' ),
					'2' => __( '2 — Fair', 'visati-core' ),
					'1' => __( '1 — Poor', 'visati-core' ),
				),
			),
			'_visati_review_text'  => array(
				'label' => __( 'Review Text', 'visati-core' ),
				'desc'  => __( 'The full review from the customer.', 'visati-core' ),
				'type'  => 'textarea',
			),
		);

		self::render_fields( $post, $fields );
	}

	/**
	 * Save Testimonial meta fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_testimonial( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['visati_testimonial_nonce'] ) || ! wp_verify_nonce( $_POST['visati_testimonial_nonce'], 'visati_testimonial_details_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_visati_country'] ) ) {
			update_post_meta( $post_id, '_visati_country', sanitize_text_field( wp_unslash( $_POST['_visati_country'] ) ) );
		}
		if ( isset( $_POST['_visati_rating'] ) ) {
			update_post_meta( $post_id, '_visati_rating', absint( $_POST['_visati_rating'] ) );
		}
		if ( isset( $_POST['_visati_review_text'] ) ) {
			update_post_meta( $post_id, '_visati_review_text', sanitize_textarea_field( wp_unslash( $_POST['_visati_review_text'] ) ) );
		}
	}

	// ─── Discount Code Meta Box ───────────────────────────────────────

	/**
	 * Render the Discount Details meta box.
	 *
	 * The post title IS the code the customer types in (e.g. "SUMMER20") —
	 * matched case-insensitively at validation time, no separate code field.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public static function render_discount_details( WP_Post $post ): void {
		wp_nonce_field( 'visati_discount_details_nonce', 'visati_discount_nonce' );

		$used_count = (int) get_post_meta( $post->ID, '_visati_discount_used_count', true );

		$fields = array(
			'_visati_discount_type'     => array(
				'label'   => __( 'Discount Type', 'visati-core' ),
				'desc'    => __( 'Percentage off, or a fixed USD amount off.', 'visati-core' ),
				'type'    => 'select',
				'options' => array(
					'percentage' => __( 'Percentage (%)', 'visati-core' ),
					'fixed'      => __( 'Fixed amount (USD)', 'visati-core' ),
				),
			),
			'_visati_discount_value'    => array(
				'label' => __( 'Discount Value', 'visati-core' ),
				'desc'  => __( 'e.g. 20 for 20% off, or 15 for USD 15 off (checkout is charged in USD).', 'visati-core' ),
				'type'  => 'number',
			),
			'_visati_discount_max_uses' => array(
				'label' => __( 'Max Uses', 'visati-core' ),
				'desc'  => __( 'Total number of times this code can be used. Leave 0 for unlimited.', 'visati-core' ),
				'type'  => 'number',
			),
			'_visati_discount_expiry'   => array(
				'label' => __( 'Expiry Date', 'visati-core' ),
				'desc'  => __( 'Code stops working after this date. Leave empty for no expiry.', 'visati-core' ),
				'type'  => 'date',
			),
			'_visati_discount_active'   => array(
				'label' => __( 'Active', 'visati-core' ),
				'desc'  => __( 'Uncheck to disable this code without deleting it.', 'visati-core' ),
				'type'  => 'checkbox',
			),
		);

		self::render_fields( $post, $fields );

		echo '<table class="form-table visati-meta-box" role="presentation"><tr>';
		echo '<th style="width:200px;">' . esc_html__( 'Times Used', 'visati-core' ) . '</th>';
		echo '<td><strong>' . esc_html( $used_count ) . '</strong> <p class="description">' . esc_html__( 'Tracked automatically after each successful payment — not editable.', 'visati-core' ) . '</p></td>';
		echo '</tr></table>';
	}

	/**
	 * Save Discount meta fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_discount( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['visati_discount_nonce'] ) || ! wp_verify_nonce( $_POST['visati_discount_nonce'], 'visati_discount_details_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_visati_discount_type'] ) ) {
			$type = sanitize_text_field( wp_unslash( $_POST['_visati_discount_type'] ) );
			update_post_meta( $post_id, '_visati_discount_type', in_array( $type, array( 'percentage', 'fixed' ), true ) ? $type : 'percentage' );
		}
		if ( isset( $_POST['_visati_discount_value'] ) ) {
			update_post_meta( $post_id, '_visati_discount_value', absint( $_POST['_visati_discount_value'] ) );
		}
		if ( isset( $_POST['_visati_discount_max_uses'] ) ) {
			update_post_meta( $post_id, '_visati_discount_max_uses', absint( $_POST['_visati_discount_max_uses'] ) );
		}
		if ( isset( $_POST['_visati_discount_expiry'] ) ) {
			update_post_meta( $post_id, '_visati_discount_expiry', sanitize_text_field( wp_unslash( $_POST['_visati_discount_expiry'] ) ) );
		}
		update_post_meta( $post_id, '_visati_discount_active', isset( $_POST['_visati_discount_active'] ) ? 1 : 0 );

		// Times-used counter is system-managed — initialize once, never overwritten by the form.
		if ( '' === get_post_meta( $post_id, '_visati_discount_used_count', true ) ) {
			update_post_meta( $post_id, '_visati_discount_used_count', 0 );
		}
	}

	// ─── Job Opening Meta Box ─────────────────────────────────────────

	/**
	 * Render the Job Details meta box. The main content editor holds the full
	 * job description — this only covers structured listing fields.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public static function render_job_details( WP_Post $post ): void {
		wp_nonce_field( 'visati_job_details_nonce', 'visati_job_nonce' );

		$fields = array(
			'_visati_job_department'   => array(
				'label' => __( 'Department', 'visati-core' ),
				'desc'  => __( 'e.g. "Operations", "Sales", "Customer Support".', 'visati-core' ),
				'type'  => 'text',
			),
			'_visati_job_location'     => array(
				'label' => __( 'Location', 'visati-core' ),
				'desc'  => __( 'e.g. "Dubai, UAE" or "Remote".', 'visati-core' ),
				'type'  => 'text',
			),
			'_visati_job_type'         => array(
				'label'   => __( 'Employment Type', 'visati-core' ),
				'desc'    => __( 'How this role is classified.', 'visati-core' ),
				'type'    => 'select',
				'options' => array(
					'full-time'  => __( 'Full-time', 'visati-core' ),
					'part-time'  => __( 'Part-time', 'visati-core' ),
					'contract'   => __( 'Contract', 'visati-core' ),
					'internship' => __( 'Internship', 'visati-core' ),
				),
			),
			'_visati_job_apply_email' => array(
				'label' => __( 'Application Email', 'visati-core' ),
				'desc'  => __( 'Where applications for this role should be sent.', 'visati-core' ),
				'type'  => 'text',
			),
			'_visati_job_active'       => array(
				'label' => __( 'Active', 'visati-core' ),
				'desc'  => __( 'Uncheck to hide this opening from the careers page without deleting it.', 'visati-core' ),
				'type'  => 'checkbox',
			),
		);

		self::render_fields( $post, $fields );
	}

	/**
	 * Save Job Opening meta fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_job( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['visati_job_nonce'] ) || ! wp_verify_nonce( $_POST['visati_job_nonce'], 'visati_job_details_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_visati_job_department'] ) ) {
			update_post_meta( $post_id, '_visati_job_department', sanitize_text_field( wp_unslash( $_POST['_visati_job_department'] ) ) );
		}
		if ( isset( $_POST['_visati_job_location'] ) ) {
			update_post_meta( $post_id, '_visati_job_location', sanitize_text_field( wp_unslash( $_POST['_visati_job_location'] ) ) );
		}
		if ( isset( $_POST['_visati_job_type'] ) ) {
			$type = sanitize_text_field( wp_unslash( $_POST['_visati_job_type'] ) );
			update_post_meta( $post_id, '_visati_job_type', in_array( $type, array( 'full-time', 'part-time', 'contract', 'internship' ), true ) ? $type : 'full-time' );
		}
		if ( isset( $_POST['_visati_job_apply_email'] ) ) {
			update_post_meta( $post_id, '_visati_job_apply_email', sanitize_email( wp_unslash( $_POST['_visati_job_apply_email'] ) ) );
		}
		update_post_meta( $post_id, '_visati_job_active', isset( $_POST['_visati_job_active'] ) ? 1 : 0 );
	}

	// ─── Shared Renderer ──────────────────────────────────────────────

	/**
	 * Render a set of meta fields for a post.
	 *
	 * @param WP_Post $post   Current post.
	 * @param array   $fields Field definitions keyed by meta key.
	 * @return void
	 */
	private static function render_fields( WP_Post $post, array $fields ): void {
		echo '<table class="form-table visati-meta-box" role="presentation">';
		foreach ( $fields as $key => $f ) {
			$value = get_post_meta( $post->ID, $key, true );
			echo '<tr>';
			echo '<th style="width:200px;"><label for="' . esc_attr( $key ) . '">' . esc_html( $f['label'] ) . '</label></th>';
			echo '<td>';

			switch ( $f['type'] ) {
				case 'textarea':
					printf(
						'<textarea id="%s" name="%s" rows="5" class="large-text">%s</textarea>',
						esc_attr( $key ),
						esc_attr( $key ),
						esc_textarea( $value )
					);
					break;

				case 'select':
					printf(
						'<select id="%s" name="%s" class="regular-text">',
						esc_attr( $key ),
						esc_attr( $key )
					);
					foreach ( $f['options'] as $opt_val => $opt_label ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $opt_val ),
							selected( $value, $opt_val, false ),
							esc_html( $opt_label )
						);
					}
					echo '</select>';
					break;

				case 'checkbox':
					printf(
						'<label><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>',
						esc_attr( $key ),
						esc_attr( $key ),
						checked( $value, 1, false ),
						esc_html( $f['desc'] )
					);
					$f['desc'] = '';
					break;

				case 'number':
					printf(
						'<input id="%s" name="%s" type="number" min="0" class="regular-text" value="%s">',
						esc_attr( $key ),
						esc_attr( $key ),
						esc_attr( $value )
					);
					break;

				case 'date':
					printf(
						'<input id="%s" name="%s" type="date" value="%s">',
						esc_attr( $key ),
						esc_attr( $key ),
						esc_attr( $value )
					);
					break;

				default:
					printf(
						'<input id="%s" name="%s" type="text" class="regular-text" value="%s">',
						esc_attr( $key ),
						esc_attr( $key ),
						esc_attr( $value )
					);
					break;
			}

			if ( ! empty( $f['desc'] ) ) {
				echo '<p class="description">' . esc_html( $f['desc'] ) . '</p>';
			}

			echo '</td></tr>';
		}
		echo '</table>';
	}
}
