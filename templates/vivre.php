<?php
/**
 * Template Name: Vivre en Église (démarches)
 *
 * Page commune : la liste des démarches, chacune menant à la bonne page.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();

$all_comm   = vsg_communities_page();
$common_sac = get_page_by_path( 'sacrements' );
$items      = array(
	array( 'Demander le baptême', $common_sac ? get_permalink( $common_sac ) : '', 'Pour un bébé, un enfant ou un adulte' ),
	array( 'Se marier à l\'église', $common_sac ? get_permalink( $common_sac ) : '', 'Prendre contact au moins un an avant' ),
	array( 'Organiser des funérailles', $common_sac ? get_permalink( $common_sac ) : '', 'Accompagner un défunt et sa famille' ),
	array( 'Obtenir un certificat de baptême ou de confirmation', $common_sac ? get_permalink( $common_sac ) : '', 'Pour un mariage, un parrainage, une inscription' ),
	array( 'Demander une messe', vsg_page_url( 'demande-de-messe' ), 'Faire célébrer une messe à une intention particulière, offrande en ligne ou au secrétariat' ),
	array( 'Rencontrer un prêtre', add_query_arg( 'objet', 'pretre', vsg_page_url( 'contact' ) ) . '#contact-form', 'Écoute, accompagnement, confession sur rendez-vous' ),
	array( 'Louer une salle paroissiale', vsg_page_url( 'contact' ) . '?objet=salle#contact-form', 'Pour une fête de famille ou une réunion' ),
	array( 'Trouver un groupe de prière', $all_comm ? get_permalink( $all_comm ) : '', 'Groupes et communautés des deux paroisses' ),
	array( 'Faire partie d\'une chorale', $all_comm ? get_permalink( $all_comm ) : '', 'Chanter à la messe du dimanche' ),
	array( 'Donner au denier de l\'Église', vsg_page_url( 'soutenir' ) . '#denier', 'Faire vivre les prêtres et la paroisse' ),
	array( 'Léguer à l\'Église', vsg_page_url( 'soutenir' ) . '#legs', 'Legs, donation, assurance-vie' ),
);
?>
<section class="band band--soft band--tight">
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
	</div>
</section>
<section class="band">
	<div class="wrap">
		<div class="grid grid--3 tiles tiles--menu">
			<?php
			foreach ( $items as $it ) {
				if ( $it[1] ) {
					echo vsg_tile( $it[0], $it[1], $it[2] );
				}
			}
			?>
		</div>
	</div>
</section>
<?php
get_footer();
