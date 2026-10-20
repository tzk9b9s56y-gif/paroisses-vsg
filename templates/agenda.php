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

$places = array();
foreach ( vsg_parishes() as $p ) {
	$t = vsg_page_term( $p->ID );
	if ( $t ) {
		$places[ $t->slug ] = get_the_title( $p );
	}
	foreach ( vsg_chapels( $p->ID ) as $c ) {
		$ct = vsg_page_term( $c->ID );
		if ( $ct ) {
			$places[ $ct->slug ] = get_the_title( $c );
		}
	}
}
$types = vsg_event_types();
$lieu  = isset( $_GET['lieu'] ) ? sanitize_title( wp_unslash( $_GET['lieu'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$lieu  = isset( $places[ $lieu ] ) ? $lieu : '';
$type  = isset( $_GET['type'] ) ? sanitize_title( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$type  = ( isset( $types[ $type ] ) && empty( $types[ $type ][2] ) ) ? $type : '';
$terms = array();
if ( $lieu ) {
	$terms[] = get_term_by( 'slug', $lieu, 'category' )->term_id;
	$common  = vsg_common_term();
	if ( $common ) {
		$terms[] = $common->term_id;
	}
}
/* Une paroisse n'inclut pas ses chapelles : chaque lieu a son filtre. Le type est filtré dans la requête. */
$q      = vsg_events_query( array( 'terms' => $terms, 'limit' => 500, 'children' => false, 'kind' => 'parish', 'type' => $type ) );
$months = vsg_events_by_month( $q->posts );
$base = get_permalink();
$link = function ( $l, $t ) use ( $base ) {
	$args = array_filter( array( 'lieu' => $l, 'type' => $t ) );
	return $args ? add_query_arg( $args, $base ) : $base;
};
?>
<section class="band band--soft band--tight">
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<div class="prose lead"><?php the_content(); ?></div>
		<?php
		$items = array( '' => array( 'Tous les lieux', $link( '', $type ) ) );
		foreach ( $places as $slug => $label ) {
			$items[ $slug ] = array( $label, $link( $slug, $type ) );
		}
		vsg_filters( $items, $lieu, 'Lieu' );
		?>
		<?php
		$type_links = '<a href="' . esc_url( $link( $lieu, '' ) ) . '"' . ( '' === $type ? ' aria-current="true"' : '' ) . '>Toutes les activités</a>';
		foreach ( $types as $slug => $t ) {
			if ( ! empty( $t[2] ) || ( 'salle' === $slug && ! current_user_can( 'read_private_posts' ) ) ) {
				continue;
			}
			$type_links .= '<a href="' . esc_url( $link( $lieu, $slug ) ) . '"' . ( $slug === $type ? ' aria-current="true"' : '' ) . '><span class="dot" style="background:' . esc_attr( $t[1] ) . '"></span>' . esc_html( $t[0] ) . '</a>';
		}
		?>
		<nav class="legend" aria-label="Activité"><?php echo $type_links; // phpcs:ignore ?></nav>
		<details class="menu menu--filters menu--types"><summary><span class="menu__label">Activité</span><span class="menu__current"><?php echo esc_html( $type ? $types[ $type ][0] : 'Toutes les activités' ); ?></span></summary><nav aria-label="Activité"><?php echo $type_links; // phpcs:ignore ?></nav></details>
	</div>
</section>
<section class="band">
	<div class="wrap narrow">
		<?php if ( $months ) : ?>
			<p class="muted agenda__hint">Touchez un mois pour l'ouvrir ou le replier. Les messes habituelles sont sur la page <a href="<?php echo esc_url( vsg_page_url( 'horaires-des-messes' ) ); ?>">Horaires</a>.</p>
			<?php vsg_months_list( $months ); ?>
		<?php else : ?>
			<?php vsg_empty( 'Aucun rendez-vous à venir pour ce choix.' ); ?>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
