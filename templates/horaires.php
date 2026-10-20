<?php
/**
 * Template Name: Horaires
 *
 * Page commune des horaires (messes, confessions, adoration) : les quatre lieux, tout déplié.
 * Le menu « Lieu » n'affiche qu'un lieu (?lieu=…), comme le filtre de l'agenda.
 * Les horaires se saisissent dans la fiche de chaque lieu (« Coordonnées affichées sur le site »).
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$places = vsg_hours_places();
$cur    = vsg_hours_current();
$base   = get_permalink();

$items = array( '' => array( 'Tous les lieux', $base ) );
foreach ( $places as $slug => $l ) {
	$items[ $slug ] = array( $l[1], add_query_arg( 'lieu', $slug, $base ) );
}
$shown = $cur ? array( $cur => $places[ $cur ] ) : $places;
?>
<section class="band band--soft band--tight">
	<div class="wrap">
		<h1><?php echo esc_html( $cur ? $places[ $cur ][1] : get_the_title() ); ?></h1>
		<?php if ( ! $cur && trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
		<?php vsg_filters( $items, $cur, 'Lieu' ); ?>
	</div>
</section>

<section class="band band--tight">
	<div class="wrap">
		<div class="<?php echo $cur ? 'narrow' : 'grid grid--2'; ?>">
			<?php foreach ( $shown as $slug => $l ) : ?>
				<?php
				$parish     = $l[3];
				$place_term = vsg_page_term( $l[0]->ID );
				$agenda     = $place_term ? add_query_arg( 'lieu', $place_term->slug, vsg_page_url( 'agenda' ) ) : vsg_page_url( 'agenda' );
				?>
				<div class="hourscard" id="<?php echo esc_attr( $slug ); ?>" style="--accent:<?php echo esc_attr( vsg_parish_color( $parish ) ); ?>">
					<?php if ( ! $cur ) : ?><h2 class="hourscard__title"><a href="<?php echo esc_url( $items[ $slug ][1] ); ?>"><?php echo esc_html( $l[1] ); ?></a></h2><?php endif; ?>
					<?php if ( $l[2] ) : ?><p class="muted"><?php echo esc_html( $l[2] ); ?> <?php echo vsg_itineraire( $l[2], $l[1] ); ?></p><?php endif; ?>
					<?php echo vsg_hours_block( $l[0]->ID ); ?>
					<ul class="plainlist hourslinks">
						<li><a href="<?php echo esc_url( $agenda ); ?>">Messes et célébrations particulières : l'agenda <?php echo $l[0] === $parish ? 'de l\'église' : 'de la chapelle'; ?></a></li>
						<li><a href="<?php echo esc_url( $l[4] ); ?>"><?php echo $l[0] === $parish ? 'La paroisse ' . esc_html( get_the_title( $parish ) ) : esc_html( $l[1] ) . ' : présentation et accès'; ?></a></li>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<section class="band band--tight">
	<div class="wrap narrow"><?php echo vsg_priest_block( $cur ? $places[ $cur ][3]->ID : 0 ); ?></div>
</section>
<?php
get_footer();
