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

/* Filtre par lieu : la paroisse et ses chapelles. */
$places = array();
if ( $parish ) {
	$pt = vsg_page_term( $parish->ID );
	if ( $pt ) {
		$places[ $pt->slug ] = 'Église ' . get_the_title( $parish );
	}
	foreach ( vsg_chapels( $parish->ID ) as $c ) {
		$ct = vsg_page_term( $c->ID );
		if ( $ct ) {
			$places[ $ct->slug ] = get_the_title( $c );
		}
	}
}
$lieu  = isset( $_GET['lieu'] ) ? sanitize_title( wp_unslash( $_GET['lieu'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$lieu  = isset( $places[ $lieu ] ) ? $lieu : '';
$paged = max( 1, absint( $_GET['pg'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification

if ( $lieu ) {
	$lt    = get_term_by( 'slug', $lieu, 'category' );
	$terms = array( $lt->term_id );
	if ( $parish && $pt && $lieu === $pt->slug ) {
		$terms = array( $pt->term_id );
	}
} else {
	$terms = $parish ? vsg_parish_term_ids( $parish->ID ) : array();
}
// L'église seule : la paroisse et ses communautés, sans les chapelles.
$exclude = array();
if ( $lieu && $parish && $pt && $lieu === $pt->slug ) {
	foreach ( vsg_chapels( $parish->ID ) as $c ) {
		$ct = vsg_page_term( $c->ID );
		if ( $ct ) {
			$exclude[] = (int) $ct->term_id;
		}
	}
}
$base     = get_permalink();
?>
<section class="band band--tight">
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
		<?php
		if ( count( $places ) > 1 ) {
			$items = array( '' => array( 'Tous les lieux', $base ) );
			foreach ( $places as $slug => $label ) {
				$items[ $slug ] = array( $label, add_query_arg( 'lieu', $slug, $base ) );
			}
			vsg_filters( $items, $lieu, 'Lieu' );
		}
		?>
	</div>
</section>

<section class="band band--tight">
	<div class="wrap twocol">
		<div class="twocol__side">
			<h2>Agenda</h2>
			<?php
			$q = vsg_events_query(
				array(
					'terms'    => $terms,
					'limit'    => 8,
					'kind'     => 'parish',
					'exclude_terms' => $exclude,
				)
			);
			if ( $q->have_posts() ) {
				echo '<div class="list">';
				foreach ( $q->posts as $ev ) {
					echo vsg_event( $ev, 'row', ! $lieu ); // phpcs:ignore
				}
				echo '</div>';
			} else {
				vsg_empty( 'Aucun rendez-vous à venir.' );
			}
			?>
			<p><a href="<?php echo esc_url( vsg_page_url( 'agenda' ) ); ?>">Voir l'agenda des deux paroisses</a></p>
		</div>
		<div class="twocol__main">
			<h2>Actualités</h2>
			<?php
			$q = vsg_news_query(
				array(
					'terms'    => $terms,
					'limit'    => 6,
					'paged'    => $paged,
					'exclude_terms' => $exclude,
				)
			);
			if ( $q->have_posts() ) {
				echo '<div class="grid grid--2">';
				foreach ( $q->posts as $n ) {
					echo vsg_news_card( $n, ! $lieu, true ); // phpcs:ignore
				}
				echo '</div>';
				if ( $q->max_num_pages > 1 ) {
					echo '<nav class="pager" aria-label="Pages d\'actualités">';
					for ( $i = 1; $i <= $q->max_num_pages; $i++ ) {
						$url = add_query_arg( array_filter( array( 'pg' => $i > 1 ? $i : null, 'lieu' => $lieu ?: null ) ), $base );
						echo '<a href="' . esc_url( $url ) . '"' . ( $i === $paged ? ' aria-current="page"' : '' ) . '>' . (int) $i . '</a>';
					}
					echo '</nav>';
				}
			} else {
				vsg_empty( 'Aucune actualité pour le moment.' );
			}
			?>
		</div>
	</div>
</section>
<?php
get_footer();
