<?php
/**
 * Template Name: Communautés
 *
 * Dans une paroisse : les communautés de cette paroisse (onglet).
 * Hors paroisse : la page commune qui les regroupe toutes.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$parish = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, get_post() );
}
?>
<section class="band band--tight">
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
		<?php
		if ( ! $parish ) {
			vsg_communities_menu(); }
		?>
	</div>
</section>

<?php if ( $parish ) : ?>
	<section class="band band--tight">
		<div class="wrap">
			<?php
			$list = vsg_communities( $parish->ID );
			if ( $list ) {
				echo '<div class="grid grid--3">';
				foreach ( $list as $c ) {
					echo vsg_community_card( $c, false );
				}
				echo '</div>';
			} else {
				vsg_empty( 'Aucune communauté présentée pour le moment.' );
			}
			$all = vsg_communities_page();
			if ( $all ) {
				echo '<p class="morelink"><a href="' . esc_url( get_permalink( $all ) ) . '">Voir les groupes et communautés des deux paroisses</a></p>';
			}
			?>
		</div>
	</section>
<?php else : ?>
	<?php
	$shown  = 0;
	$common = vsg_communities_common();
	if ( $common ) :
		++$shown;
		?>
		<section class="band band--tight">
			<div class="wrap">
				<?php
				vsg_section_head( 'Pour les deux paroisses' );
				echo '<div class="grid grid--3">';
				foreach ( $common as $c ) {
					echo vsg_community_card( $c, false );
				}
				echo '</div>';
				?>
			</div>
		</section>
	<?php endif; ?>
	<?php
	foreach ( vsg_parishes() as $p ) :
		$list = vsg_communities( $p->ID );
		if ( ! $list ) {
			continue;
		}
		++$shown;
		?>
		<section class="band band--tight">
			<div class="wrap">
				<?php
				vsg_section_head( 'Paroisse ' . get_the_title( $p ), get_permalink( $p ), 'Voir la paroisse' );
				echo '<div class="grid grid--3">';
				foreach ( $list as $c ) {
					echo vsg_community_card( $c, false );
				}
				echo '</div>';
				?>
			</div>
		</section>
	<?php endforeach; ?>
	<?php
	if ( ! $shown ) {
		echo '<section class="band band--tight"><div class="wrap">';
		vsg_empty( 'Aucune communauté présentée pour le moment.' );
		echo '</div></section>';
	}
	?>
<?php endif; ?>
<?php
get_footer();
