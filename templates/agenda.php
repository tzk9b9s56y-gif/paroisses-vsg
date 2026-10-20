<?php
/**
 * Template Name: Agenda général
 *
 * Tous les rendez-vous des deux paroisses, mois par mois (un mois ouvert à la fois),
 * avec un filtre par lieu et par type de rendez-vous.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();

$vue = vsg_agenda_page_data();
?>
<section class="band band--soft band--tight">
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<div class="prose lead"><?php the_content(); ?></div>
		<?php vsg_filters( vsg_places_items( $vue['places'], $vue['base'], array_filter( array( 'type' => $vue['type'] ) ) ), $vue['lieu'], 'Lieu' ); ?>
		<nav class="legend" aria-label="Activité"><?php echo vsg_agenda_type_links( $vue['base'], $vue['lieu'], $vue['type'] ); ?></nav>
		<details class="menu menu--filters menu--types"><summary><span class="menu__label">Activité</span><span class="menu__current"><?php echo esc_html( $vue['type_label'] ); ?></span></summary><nav aria-label="Activité"><?php echo vsg_agenda_type_links( $vue['base'], $vue['lieu'], $vue['type'] ); ?></nav></details>
	</div>
</section>
<section class="band">
	<div class="wrap narrow">
		<?php if ( $vue['months'] ) : ?>
			<p class="muted agenda__hint">Touchez un mois pour l'ouvrir ou le replier. Les messes habituelles sont sur la page <a href="<?php echo esc_url( vsg_page_url( 'horaires-des-messes' ) ); ?>">Horaires</a>.</p>
			<?php vsg_months_list( $vue['months'] ); ?>
		<?php else : ?>
			<?php vsg_empty( 'Aucun rendez-vous à venir pour ce choix.' ); ?>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
