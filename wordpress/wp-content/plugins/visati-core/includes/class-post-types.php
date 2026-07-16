<?php
/**
 * Custom Post Types registration.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Visati_Core_Post_Types {

	/**
	 * Register all Visati CPTs.
	 *
	 * @return void
	 */
	public static function register(): void {
		self::register_visa_type();
		self::register_faq();
		self::register_testimonial();
		self::register_job();
		self::register_discount();
	}

	/**
	 * Visa Type CPT — the catalogue (entry type, duration, price, features).
	 *
	 * @return void
	 */
	private static function register_visa_type(): void {
		register_post_type(
			'visati_visa_type',
			array(
				'labels'       => array(
					'name'               => __( 'Visa Types', 'visati-core' ),
					'singular_name'      => __( 'Visa Type', 'visati-core' ),
					'add_new_item'       => __( 'Add New Visa Type', 'visati-core' ),
					'edit_item'          => __( 'Edit Visa Type', 'visati-core' ),
					'all_items'          => __( 'All Visa Types', 'visati-core' ),
					'search_items'       => __( 'Search Visa Types', 'visati-core' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'menu_icon'    => 'dashicons-palmtree',
				'supports'     => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'visa-types' ),
			)
		);
	}

	/**
	 * FAQ CPT.
	 *
	 * @return void
	 */
	private static function register_faq(): void {
		register_post_type(
			'visati_faq',
			array(
				'labels'       => array(
					'name'               => __( 'FAQ', 'visati-core' ),
					'singular_name'      => __( 'FAQ Item', 'visati-core' ),
					'add_new_item'       => __( 'Add New FAQ Item', 'visati-core' ),
					'search_items'       => __( 'Search FAQ', 'visati-core' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'menu_icon'    => 'dashicons-editor-help',
				'supports'     => array( 'title', 'editor', 'page-attributes' ),
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Testimonial CPT.
	 *
	 * @return void
	 */
	private static function register_testimonial(): void {
		register_post_type(
			'visati_testimonial',
			array(
				'labels'       => array(
					'name'          => __( 'Testimonials', 'visati-core' ),
					'singular_name' => __( 'Testimonial', 'visati-core' ),
					'add_new_item'  => __( 'Add New Testimonial', 'visati-core' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'menu_icon'    => 'dashicons-star-filled',
				'supports'     => array( 'title', 'editor' ),
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Job Opening CPT.
	 *
	 * @return void
	 */
	private static function register_job(): void {
		register_post_type(
			'visati_job',
			array(
				'labels'       => array(
					'name'          => __( 'Job Openings', 'visati-core' ),
					'singular_name' => __( 'Job Opening', 'visati-core' ),
					'add_new_item'  => __( 'Add New Job Opening', 'visati-core' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'menu_icon'    => 'dashicons-briefcase',
				'supports'     => array( 'title', 'editor' ),
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Discount Code CPT — admin-only (not public).
	 *
	 * @return void
	 */
	private static function register_discount(): void {
		register_post_type(
			'visati_discount',
			array(
				'labels'       => array(
					'name'          => __( 'Discount Codes', 'visati-core' ),
					'singular_name' => __( 'Discount Code', 'visati-core' ),
					'add_new_item'  => __( 'Add New Discount Code', 'visati-core' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'menu_icon'    => 'dashicons-tickets-alt',
				'supports'     => array( 'title' ),
				'show_in_rest' => true,
			)
		);
	}
}
