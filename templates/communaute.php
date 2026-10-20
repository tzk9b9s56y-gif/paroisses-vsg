<?php
/**
 * Template Name: Communauté
 *
 * Une communauté ou un groupe de prière. À placer sous la page « Communautés »
 * d'une paroisse : la page hérite alors du bandeau et des onglets de la paroisse.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$community   = get_post();
$parish = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, $community );
}
$term  = vsg_page_term( $community->ID );
$prog  = array_values( array_filter( array_map( 'trim', explode( "\n", vsg_meta( $community->ID, 'programme' ) ) ) ) );
$site  = vsg_meta( $community->ID, 'site' );
$links = array_filter(
	array(
		'Facebook'  => vsg_meta( $community->ID, 'facebook' ),
		'Instagram' => vsg_meta( $community->ID, 'instagram' ),
		'YouTube'   => vsg_meta( $community->ID, 'youtube' ),
	)
);
$resp  = vsg_meta( $community->ID, 'responsable' );
$tel   = vsg_meta( $community->ID, 'telephone' );
$mail  = vsg_meta( $community->ID, 'email' );
$list  = $community->post_parent ? get_post( $community->post_parent ) : null;
/* Groupe dans l'agenda : ses rendez-vous et le lien vers tout son programme. */
$gtype = vsg_meta( $community->ID, 'type' );
$gtype = isset( vsg_event_types()[ $gtype ] ) ? $gtype : '';
$gterms = array();
$gurl   = '';
if ( $gtype ) {
	$args = array( 'type' => $gtype );
	/* Un groupe présent dans les deux paroisses (la chorale) : chaque page montre sa paroisse. */
	$shared = count( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'meta_key' => '_vsg_type', 'meta_value' => $gtype, 'fields' => 'ids', 'posts_per_page' => 2 ) ) ) > 1;
	if ( $shared && $parish ) {
		$gterms = vsg_parish_term_ids( $parish->ID );
		$pt     = vsg_page_term( $parish->ID );
		if ( $pt ) {
			$args['lieu'] = $pt->slug;
		}
	}
	$gurl = get_permalink() . '#programme';
}
?>
<section class="band">
	<div class="wrap">
		<div class="split">
			<div class="split__text">
				<span class="eyebrow">Communauté</span>
				<h1><?php echo esc_html( get_the_title() ); ?></h1>
				<?php if ( has_excerpt() ) : ?><p class="lead"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
				<?php if ( $parish ) : ?><p>Présente à la <a href="<?php echo esc_url( get_permalink( $parish ) ); ?>">paroisse <?php echo esc_html( get_the_title( $parish ) ); ?></a>.</p><?php else : ?><p>Pour les deux paroisses, Notre-Dame de Lourdes et Sainte-Geneviève.</p><?php endif; ?>
				<?php if ( $site || $links ) : ?>
					<div class="actions">
						<?php if ( $site ) : ?><a class="btn" href="<?php echo esc_url( $site ); ?>" target="_blank" rel="noopener">Site de la communauté<span class="sr-only"> (nouvel onglet)</span></a><?php endif; ?>
						<?php foreach ( $links as $label => $url ) : ?>
							<a class="btn btn--ghost" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $label ); ?><span class="sr-only"> (nouvel onglet)</span></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
			<div class="split__media"><?php echo vsg_thumb( $community, 'vsg-hero', 'Photo de la communauté' ); // phpcs:ignore ?></div>
		</div>
	</div>
</section>

<section class="band band--tight">
	<div class="wrap twocol">
		<div class="twocol__side">
			<div class="infocard infocard--soft">
				<h2>Rendez-vous réguliers</h2>
				<?php if ( $prog ) : ?>
					<ul class="plainlist">
						<?php foreach ( $prog as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?>
					</ul>
				<?php elseif ( $gurl ) : ?>
					<p>Toutes les dates sont dans le <a href="#programme">programme</a> ci-dessous.</p>
				<?php else : ?>
					<p>Les dates sont annoncées dans l'<a href="<?php echo esc_url( vsg_page_url( 'agenda' ) ); ?>">agenda</a> et la feuille paroissiale.</p>
				<?php endif; ?>
				<?php if ( vsg_meta( $community->ID, 'lieu' ) ) : ?><p><strong>Lieu habituel</strong><br><?php echo esc_html( vsg_meta( $community->ID, 'lieu' ) ); ?></p><?php endif; ?>
			</div>
			<?php if ( $resp || $tel || $mail ) : ?>
				<div class="infocard">
					<h2>Contact</h2>
					<?php if ( $resp ) : ?><p><?php echo esc_html( $resp ); ?></p><?php endif; ?>
					<?php if ( $tel ) : ?><p><?php echo esc_html( $tel ); ?></p><?php endif; ?>
					<?php if ( $mail ) : ?><p><?php echo vsg_mailto( $mail ); // phpcs:ignore ?></p><?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<div class="twocol__main prose">
			<?php the_content(); ?>
		</div>
	</div>
</section>

<?php if ( $gtype || $term ) : ?>
<section class="band band--soft programme" id="programme">
	<div class="wrap narrow">
		<?php
		vsg_section_head( 'Programme' );
		vsg_communities_menu( $community->ID );
		if ( $gtype ) {
			$q = vsg_events_query( array( 'terms' => $gterms, 'type' => $gtype, 'limit' => 500 ) );
			/* Groupe présent dans les deux paroisses : on garde aussi ses rendez-vous sans lieu. */
			if ( $gterms ) {
				$ids  = wp_list_pluck( $q->posts, 'ID' );
				$all  = vsg_events_query( array( 'type' => $gtype, 'limit' => 500 ) )->posts;
				$list = array_filter( $all, function ( $ev ) use ( $ids ) { return in_array( $ev->ID, $ids, true ) || ! vsg_place_label( $ev ); } );
			} else {
				$list = $q->posts;
			}
		} else {
			$list = vsg_events_query( array( 'terms' => array( $term->term_id ), 'limit' => 500 ) )->posts;
		}
		$months = vsg_events_by_month( $list );
		if ( $months ) {
			echo '<p class="muted agenda__hint">Touchez un mois pour l\'ouvrir ou le replier.</p>';
			vsg_months_list( $months );
		} else {
			vsg_empty( 'Aucun rendez-vous annoncé pour le moment.' );
		}
		?>
	</div>
</section>
<?php endif; ?>
<?php if ( $term ) : ?>
	<?php
	$q = vsg_news_query( array( 'terms' => array( $term->term_id ), 'limit' => 3 ) );
	if ( $q->have_posts() ) :
		?>
<section class="band">
	<div class="wrap">
		<?php vsg_section_head( 'Actualités de la communauté' ); ?>
		<div class="grid grid--3">
			<?php
			foreach ( $q->posts as $n ) {
				echo vsg_news_card( $n, false, true ); // phpcs:ignore
			}
			?>
		</div>
	</div>
</section>
	<?php endif; ?>
<?php endif; ?>
<?php
get_footer();
