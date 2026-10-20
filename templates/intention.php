<?php
/**
 * Template Name: Intention de prière
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
?>
<section class="band band--soft band--tight">
	<div class="wrap intention__hero">
		<img src="<?php echo esc_url( VSG_URI . '/assets/img/intention.svg' ); ?>" alt="" width="360" height="300">
		<div>
			<h1><?php the_title(); ?></h1>
			<?php if ( trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
		</div>
	</div>
</section>
<section class="band band--tight">
	<div class="wrap narrow">
		<p class="lead">Vous confiez votre intention aux prêtres de la paroisse, qui la portent dans leurs temps de prière quotidiens. Elle reste discrète : elle n'est pas lue à voix haute pendant la messe.</p>
		<?php echo vsg_intention_form(); // phpcs:ignore ?>
	</div>
</section>
<?php
get_footer();
