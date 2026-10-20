<?php
/**
 * Template Name: Paroisse – actualités et agenda
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$parish = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, get_post() );
}

$vue = vsg_actualites_page_data( $parish );
?>
<section class="band band--tight">
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
		<?php if ( count( $vue['places'] ) > 1 ) : ?>
			<?php vsg_filters( vsg_places_items( $vue['places'], $vue['base'] ), $vue['lieu'], 'Lieu' ); ?>
		<?php endif; ?>
	</div>
</section>

<section class="band band--tight">
	<div class="wrap twocol">
		<div class="twocol__side">
			<h2>Agenda</h2>
			<?php if ( $vue['events']->have_posts() ) : ?>
				<div class="list">
					<?php foreach ( $vue['events']->posts as $ev ) : ?>
						<?php echo vsg_event( $ev, 'row', ! $vue['lieu'] ); ?>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<?php vsg_empty( 'Aucun rendez-vous à venir.' ); ?>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( vsg_page_url( 'agenda' ) ); ?>">Voir l'agenda des deux paroisses</a></p>
		</div>
		<div class="twocol__main">
			<h2>Actualités</h2>
			<?php if ( $vue['news']->have_posts() ) : ?>
				<div class="grid grid--2">
					<?php foreach ( $vue['news']->posts as $n ) : ?>
						<?php echo vsg_news_card( $n, ! $vue['lieu'], true ); ?>
					<?php endforeach; ?>
				</div>
				<?php echo vsg_news_pager( (int) $vue['news']->max_num_pages, $vue['page'], $vue['base'], $vue['lieu'] ); ?>
			<?php else : ?>
				<?php vsg_empty( 'Aucune actualité pour le moment.' ); ?>
			<?php endif; ?>
		</div>
	</div>
</section>
<?php
get_footer();
