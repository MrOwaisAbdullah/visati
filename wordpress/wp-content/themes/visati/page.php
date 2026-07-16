<?php
/**
 * Default page template — renders WordPress/Elementor page content.
 *
 * @package Visati
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="visati-page">
	<div class="visati-container" style="padding-top:2rem;padding-bottom:4rem;">
		<?php
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
		?>
	</div>
</main>

<?php
get_footer();
