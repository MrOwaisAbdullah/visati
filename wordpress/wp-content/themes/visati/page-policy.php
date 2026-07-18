<?php
/**
 * Template Name: Policy Page
 * Description: Hero header + Elementor-editable card content.
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$page_id = get_queried_object_id();
$date    = get_the_modified_date( 'F j, Y', $page_id );
?>

<main id="primary" class="visati-page visati-page--policy">

	<div class="visati-policy-hero">
		<div class="visati-container">
			<span class="visati-eyebrow"><?php esc_html_e( 'Legal', 'visati' ); ?></span>
			<h1><?php the_title(); ?></h1>
			<p class="visati-policy-updated">Last updated: <?php echo esc_html( $date ); ?></p>
		</div>
	</div>

	<div class="visati-policy-body">
		<div class="visati-container" style="max-width:800px;">
			<div class="visati-policy-card">
				<?php
				while ( have_posts() ) :
					the_post();
					the_content();
				endwhile;
				?>
			</div>
		</div>
	</div>

</main>

<?php
get_footer();
