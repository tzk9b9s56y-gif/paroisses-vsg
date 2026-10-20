<?php
/**
 * Template Name: Demande de messe
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
?>
<section class="band band--soft band--tight">
	<div class="wrap narrow">
		<h1><?php the_title(); ?></h1>
		<?php if ( trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
	</div>
</section>
<section class="band band--tight">
	<div class="wrap narrow">
		<?php echo vsg_messe_form(); ?>
	</div>
</section>
<?php
get_footer();
