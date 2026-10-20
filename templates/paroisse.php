<?php
/**
 * Template Name: Paroisse – présentation
 *
 * Page d'entrée d'une paroisse : un mot d'accueil, puis le menu des rubriques.
 * Les détails (mot du curé, histoire, lieux) sont sur la page « Notre histoire ».
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$parish = get_post();
vsg_parish_band( $parish, $parish );
$children = get_pages(
	array(
		'parent'      => $parish->ID,
		'sort_column' => 'menu_order',
	)
);
$infos    = vsg_parish_child_by_template( $parish->ID, 'templates/infos.php' );
?>
<section class="band">
	<div class="wrap split">
		<div class="split__text">
			<h1>Bienvenue à <?php echo esc_html( get_the_title() ); ?></h1>
			<div class="prose lead">
			<?php if ( has_excerpt() ) : ?><?php echo wpautop( esc_html( get_the_excerpt() ) ); ?>
				<?php else : ?><?php the_content(); ?><?php endif; ?></div>
			<div class="actions">
				<?php echo vsg_messes_button( $parish->ID ); ?>
				<?php echo vsg_itineraire( vsg_meta( $parish->ID, 'eglise' ), 'l\'église', 'btn btn--ghost' ); ?>
				<?php if ( $infos ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( get_permalink( $infos ) ); ?>#contact-form">Contacter la paroisse</a><?php endif; ?>
			</div>
		</div>
		<div class="split__media"><?php echo vsg_thumb( $parish, 'vsg-hero', 'Photo de l\'église' ); ?></div>
	</div>
</section>

<section class="band band--soft">
	<div class="wrap">
		<?php vsg_section_head( 'Que cherchez-vous ?' ); ?>
		<div class="grid grid--3 tiles tiles--menu">
			<?php
			$sun = vsg_hours( $parish->ID );
			echo vsg_tile( 'Horaires', vsg_hours_url( $parish->ID ) ? vsg_hours_url( $parish->ID ) : vsg_page_url( 'horaires-des-messes' ), ! empty( $sun['dimanche'] ) ? 'Dimanche : ' . implode( ', ', $sun['dimanche']['lines'] ) : 'Église et chapelles' );
			foreach ( $children as $c ) {
				if ( VSG_TPL_CHAPELLE === get_page_template_slug( $c ) || vsg_page_is_empty( $c ) ) {
					continue;
				}
				echo vsg_tile( get_the_title( $c ), get_permalink( $c ), vsg_tile_text( $c ) );
			}
			?>
		</div>
	</div>
</section>

<?php
$terms = vsg_parish_term_ids( $parish->ID );
$q     = vsg_events_query(
	array(
		'terms' => $terms,
		'limit' => 3,
		'kind'  => 'parish',
	)
);
if ( $q->have_posts() ) :
	$agenda = vsg_parish_child_by_template( $parish->ID, 'templates/actualites.php' );
	?>
<section class="band">
	<div class="wrap">
		<?php vsg_section_head( 'Prochains rendez-vous', $agenda ? get_permalink( $agenda ) : '', 'Tout l\'agenda de la paroisse' ); ?>
		<div class="grid grid--3">
			<?php
			foreach ( $q->posts as $ev ) {
				echo vsg_event( $ev, 'card' );
			}
			?>
		</div>
	</div>
</section>
<?php endif; ?>
<?php
get_footer();
