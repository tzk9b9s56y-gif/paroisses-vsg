<?php
/**
 * Fil d'Ariane de chaque page.
 */

defined( 'ABSPATH' ) || exit;

/** Fil d'Ariane simple. */
function vsg_breadcrumb( $items ) {
	echo '<nav class="breadcrumb" aria-label="Fil d\'Ariane"><ol>';
	$last = count( $items ) - 1;
	foreach ( $items as $i => $item ) {
		echo '<li>';
		if ( $i < $last && ! empty( $item[1] ) ) {
			echo '<a href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a>';
		} else {
			echo '<span aria-current="page">' . esc_html( $item[0] ) . '</span>';
		}
		echo '</li>';
	}
	echo '</ol></nav>';
}

/** Fil d'Ariane calculé automatiquement pour la page affichée. */
function vsg_auto_breadcrumb() {
	if ( is_front_page() ) {
		return;
	}
	$items = array( array( 'Accueil', home_url( '/' ) ) );
	if ( is_page() ) {
		foreach ( array_reverse( get_post_ancestors( get_queried_object_id() ) ) as $a ) {
			$items[] = array( get_the_title( $a ), get_permalink( $a ) );
		}
		/* Une chapelle : passer par la page « Les deux chapelles » de sa paroisse. */
		if ( VSG_TPL_CHAPELLE === get_page_template_slug( get_queried_object_id() ) ) {
			$parish = vsg_context_parish( get_queried_object_id() );
			$list   = $parish ? vsg_parish_child_by_template( $parish->ID, 'templates/chapelles.php' ) : null;
			if ( $list ) {
				$items[] = array( get_the_title( $list ), get_permalink( $list ) );
			}
		}
		$cur = VSG_TPL_HORAIRES === get_page_template_slug( get_queried_object_id() ) ? vsg_hours_current() : '';
		if ( $cur ) {
			$items[] = array( get_the_title(), get_permalink() );
			$items[] = array( vsg_hours_places()[ $cur ][1] );
		} else {
			$items[] = array( get_the_title() );
		}
	} elseif ( is_singular() ) {
		$post   = get_queried_object();
		$parish = vsg_context_parish( $post );
		$tpl    = 'feuille' === $post->post_type ? 'templates/feuille.php' : 'templates/actualites.php';
		$group  = 'evenement' === $post->post_type ? vsg_event_group( $post ) : null;
		if ( $group ) {
			/* Rendez-vous d'un groupe : même chemin que la page du groupe (ses pages parentes, puis elle). */
			if ( $group['page'] ) {
				foreach ( array_reverse( get_post_ancestors( $group['page'] ) ) as $a ) {
					$items[] = array( get_the_title( $a ), get_permalink( $a ) );
				}
				$items[] = array( get_the_title( $group['page'] ), $group['url'] );
			} else {
				$list = vsg_communities_page();
				if ( $list ) {
					$items[] = array( get_the_title( $list ), get_permalink( $list ) );
				}
				$items[] = array( $group['label'] );
			}
		} elseif ( $parish ) {
			$items[] = array( get_the_title( $parish ), get_permalink( $parish ) );
			$list    = vsg_parish_child_by_template( $parish->ID, $tpl );
			if ( $list ) {
				$items[] = array( get_the_title( $list ), get_permalink( $list ) );
			}
		} elseif ( 'evenement' === $post->post_type ) {
			$items[] = array( 'Agenda', vsg_page_url( 'agenda' ) );
		} elseif ( 'feuille' === $post->post_type ) {
			$items[] = array( 'Feuilles paroissiales', home_url( '/#feuilles' ) );
		} else {
			/* Actualité commune aux deux paroisses : elle figure dans « Actualités » de l'accueil. */
			$items[] = array( 'Actualités', home_url( '/#actualites' ) );
		}
		$items[] = array( 'feuille' === $post->post_type ? vsg_feuille_label( $post ) : get_the_title( $post ) );
	} elseif ( is_404() ) {
		$items[] = array( 'Page introuvable' );
	} else {
		$items[] = array( wp_strip_all_tags( get_the_archive_title() ) );
	}
	echo '<div class="wrap crumbs">';
	vsg_breadcrumb( $items );
	echo '</div>';
}
