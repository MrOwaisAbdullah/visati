<?php
/**
 * Seed sample FAQ and Testimonial data from the Next.js site.
 *
 * Usage: wp eval-file seed-content.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/../../../' );
}

// ─── FAQs ────────────────────────────────────────────────────────────

$faqs = array(
	array(
		'question' => 'How long does visa processing take?',
		'answer'   => 'Standard processing takes 2–3 business days. Express processing is available for same-day or next-day delivery.',
		'category' => 'process',
		'order'    => 1,
	),
	array(
		'question' => 'What documents do I need to apply?',
		'answer'   => 'You need a clear passport scan (6+ months validity), a passport-sized photo, and your travel dates. Additional documents may be required for specific visa types.',
		'category' => 'documents',
		'order'    => 2,
	),
	array(
		'question' => 'What is the approval rate?',
		'answer'   => 'We maintain a 99% approval rate. Our team reviews every application before submission to ensure completeness and accuracy.',
		'category' => 'general',
		'order'    => 3,
	),
	array(
		'question' => 'Can I track my application?',
		'answer'   => 'Yes! Use our Track Application page with your Application ID to check real-time status updates.',
		'category' => 'application',
		'order'    => 4,
	),
	array(
		'question' => 'What payment methods do you accept?',
		'answer'   => 'We accept all major credit/debit cards (Visa, Mastercard, Amex) via Stripe secure payments.',
		'category' => 'pricing',
		'order'    => 5,
	),
	array(
		'question' => 'Do you offer refunds?',
		'answer'   => 'If your visa is rejected, we offer a full refund of our service fee. Government fees are non-refundable.',
		'category' => 'pricing',
		'order'    => 6,
	),
	array(
		'question' => 'What visa types do you offer?',
		'answer'   => 'We offer 14-day, 30-day, and 60-day visas in both single and multiple entry options, plus visa extensions.',
		'category' => 'visa-types',
		'order'    => 7,
	),
	array(
		'question' => 'Can I apply for someone else?',
		'answer'   => 'Yes, you can apply on behalf of family members or colleagues. You\'ll need their passport details and photo.',
		'category' => 'application',
		'order'    => 8,
	),
);

$faq_count = 0;
foreach ( $faqs as $f ) {
	// Skip if a FAQ with the same question already exists.
	$existing = get_posts( array(
		'post_type'   => 'visati_faq',
		'title'       => $f['question'],
		'post_status' => 'publish',
		'numberposts' => 1,
	) );
	if ( $existing ) {
		echo "SKIP (exists): {$f['question']}\n";
		continue;
	}

	$id = wp_insert_post( array(
		'post_type'    => 'visati_faq',
		'post_title'   => $f['question'],
		'post_status'  => 'publish',
		'menu_order'   => $f['order'],
	) );

	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_visati_faq_question', $f['question'] );
		update_post_meta( $id, '_visati_faq_answer', $f['answer'] );
		update_post_meta( $id, '_visati_faq_category', $f['category'] );
		echo "✓ FAQ: {$f['question']}\n";
		++$faq_count;
	}
}

echo "\nSeeded {$faq_count} FAQ items.\n\n";

// ─── Testimonials ────────────────────────────────────────────────────

$testimonials = array(
	array(
		'name'        => 'Priya Sharma',
		'country'     => 'IN',
		'rating'      => 5,
		'review_text' => 'Got my 60-day visa in 26 hours. The WhatsApp updates kept me sane — I knew exactly where my application was at every step.',
		'order'       => 1,
	),
	array(
		'name'        => 'James Whitfield',
		'country'     => 'GB',
		'rating'      => 5,
		'review_text' => 'Booked a business trip on Monday, had my visa on Wednesday. Visati is a different league from the agencies I used before.',
		'order'       => 2,
	),
	array(
		'name'        => 'Sophie Laurent',
		'country'     => 'FR',
		'rating'      => 5,
		'review_text' => 'A consultant called me before I even noticed my photo was the wrong size. That kind of attention is rare these days.',
		'order'       => 3,
	),
	array(
		'name'        => 'David Chen',
		'country'     => 'US',
		'rating'      => 5,
		'review_text' => 'The whole process took less time than booking my flight. Incredibly smooth experience from start to finish.',
		'order'       => 4,
	),
	array(
		'name'        => 'Fatima Al Marzouqi',
		'country'     => 'AE',
		'rating'      => 5,
		'review_text' => 'I\'ve used Visati three times now for family visitors. Every single time has been flawless. They just get it right.',
		'order'       => 5,
	),
);

$testimonial_count = 0;
foreach ( $testimonials as $t ) {
	$existing = get_posts( array(
		'post_type'   => 'visati_testimonial',
		'title'       => $t['name'],
		'post_status' => 'publish',
		'numberposts' => 1,
	) );
	if ( $existing ) {
		echo "SKIP (exists): {$t['name']}\n";
		continue;
	}

	$id = wp_insert_post( array(
		'post_type'    => 'visati_testimonial',
		'post_title'   => $t['name'],
		'post_status'  => 'publish',
		'menu_order'   => $t['order'],
	) );

	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_visati_country', $t['country'] );
		update_post_meta( $id, '_visati_rating', $t['rating'] );
		update_post_meta( $id, '_visati_review_text', $t['review_text'] );
		echo "✓ Testimonial: {$t['name']}\n";
		++$testimonial_count;
	}
}

echo "\nSeeded {$testimonial_count} testimonials.\n\n";

// ─── Visa Types ────────────────────────────────────────────────────

$visa_types = array(
	array(
		'title'        => '14-Day Single Entry',
		'slug'         => '14-day-single-entry',
		'tagline'      => 'Quick visit or short business trip',
		'price_aed'    => 200,
		'price_usd'    => 55,
		'duration'     => 14,
		'entry_type'   => 'single',
		'is_featured'  => false,
		'badge_text'   => '',
		'order'        => 1,
	),
	array(
		'title'        => '14-Day Multiple Entry',
		'slug'         => '14-day-multiple-entry',
		'tagline'      => 'Flexible short trips across borders',
		'price_aed'    => 320,
		'price_usd'    => 87,
		'duration'     => 14,
		'entry_type'   => 'multi',
		'is_featured'  => false,
		'badge_text'   => '',
		'order'        => 2,
	),
	array(
		'title'        => '30-Day Single Entry',
		'slug'         => '30-day-single-entry',
		'tagline'      => 'Most popular for holidays and family visits',
		'price_aed'    => 250,
		'price_usd'    => 68,
		'duration'     => 30,
		'entry_type'   => 'single',
		'is_featured'  => true,
		'badge_text'   => 'Most Popular',
		'order'        => 3,
	),
	array(
		'title'        => '30-Day Multiple Entry',
		'slug'         => '30-day-multiple-entry',
		'tagline'      => 'Ideal for business with multiple Gulf trips',
		'price_aed'    => 390,
		'price_usd'    => 106,
		'duration'     => 30,
		'entry_type'   => 'multi',
		'is_featured'  => false,
		'badge_text'   => '',
		'order'        => 4,
	),
	array(
		'title'        => '60-Day Single Entry',
		'slug'         => '60-day-single-entry',
		'tagline'      => 'Extended stays for longer visits',
		'price_aed'    => 350,
		'price_usd'    => 95,
		'duration'     => 60,
		'entry_type'   => 'single',
		'is_featured'  => false,
		'badge_text'   => '',
		'order'        => 5,
	),
	array(
		'title'        => '60-Day Multiple Entry',
		'slug'         => '60-day-multiple-entry',
		'tagline'      => 'Maximum flexibility for extended trips',
		'price_aed'    => 490,
		'price_usd'    => 133,
		'duration'     => 60,
		'entry_type'   => 'multi',
		'is_featured'  => false,
		'badge_text'   => '',
		'order'        => 6,
	),
	array(
		'title'        => '90-Day Single Entry',
		'slug'         => '90-day-single-entry',
		'tagline'      => 'Long-term visits and extended family stays',
		'price_aed'    => 550,
		'price_usd'    => 150,
		'duration'     => 90,
		'entry_type'   => 'single',
		'is_featured'  => false,
		'badge_text'   => '',
		'order'        => 7,
	),
	array(
		'title'        => '30-Day Tourist Visa',
		'slug'         => '30-day-tourist-visa',
		'tagline'      => 'Explore Dubai, Abu Dhabi & beyond',
		'price_aed'    => 275,
		'price_usd'    => 75,
		'duration'     => 30,
		'entry_type'   => 'single',
		'is_featured'  => true,
		'badge_text'   => 'Best Value',
		'order'        => 8,
	),
);

$visa_count = 0;
foreach ( $visa_types as $v ) {
	// Skip if a visa type with the same slug already exists.
	$existing = get_posts( array(
		'post_type'   => 'visati_visa_type',
		'name'        => $v['slug'],
		'post_status' => 'publish',
		'numberposts' => 1,
	) );
	if ( $existing ) {
		echo "SKIP (exists): {$v['title']}\n";
		continue;
	}

	$id = wp_insert_post( array(
		'post_type'    => 'visati_visa_type',
		'post_title'   => $v['title'],
		'post_name'    => $v['slug'],
		'post_status'  => 'publish',
		'menu_order'   => $v['order'],
	) );

	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_visati_tagline', $v['tagline'] );
		update_post_meta( $id, '_visati_price_aed', $v['price_aed'] );
		update_post_meta( $id, '_visati_price_usd', $v['price_usd'] );
		update_post_meta( $id, '_visati_duration_days', $v['duration'] );
		update_post_meta( $id, '_visati_entry_type', $v['entry_type'] );
		update_post_meta( $id, '_visati_is_featured', $v['is_featured'] ? 'on' : '' );
		update_post_meta( $id, '_visati_badge_text', $v['badge_text'] );
		echo "✓ Visa Type: {$v['title']}\n";
		++$visa_count;
	}
}

echo "\nSeeded {$visa_count} visa types.\n";
