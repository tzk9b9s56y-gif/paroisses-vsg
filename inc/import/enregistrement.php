<?php
/**
 * Import de l'agenda : enregistrement des rendez-vous (mise à jour sur date + titre, jamais de doublon) et vidage de l'agenda.
 */

defined( 'ABSPATH' ) || exit;

/** Rendez-vous existant pour date + titre. */
function vsg_import_find( $date, $title ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'evenement',
			'post_status'    => array( 'publish', 'private', 'draft' ),
			'posts_per_page' => 1,
			'title'          => $title,
			'meta_query'     => array(
				array(
					'key'   => '_vsg_date',
					'value' => $date,
				),
			),
			'fields'         => 'ids',
		)
	);
	return $q->posts ? (int) $q->posts[0] : 0;
}

/** Texte de la description en paragraphes (une ligne = un paragraphe). */
function vsg_import_content( $text ) {
	$out = '';
	foreach ( array_filter( array_map( 'trim', explode( "\n", (string) $text ) ) ) as $line ) {
		$out .= "<!-- wp:paragraph -->\n<p>" . esc_html( $line ) . "</p>\n<!-- /wp:paragraph -->\n\n";
	}
	return trim( $out );
}

function vsg_import_apply( $items ) {
	$created = 0;
	$updated = 0;
	foreach ( $items as $it ) {
		$cat  = get_term_by( 'slug', $it['place'], 'category' );
		$args = array(
			'post_type'     => 'evenement',
			'post_title'    => $it['title'],
			'post_content'  => vsg_import_content( $it['text'] ),
			'post_status'   => $it['private'] ? 'private' : 'publish',
			'post_category' => $cat ? array( $cat->term_id ) : array(),
		);
		$id   = vsg_import_find( $it['date'], $it['title'] );
		if ( $id ) {
			$args['ID'] = $id;
			wp_update_post( wp_slash( $args ) );
			++$updated;
		} else {
			$id = wp_insert_post( wp_slash( $args ) );
			++$created;
		}
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_vsg_date', $it['date'] );
			update_post_meta( $id, '_vsg_heure', $it['time'] );
			update_post_meta( $id, '_vsg_lieu', $it['lieu'] );
			update_post_meta( $id, '_vsg_type', $it['type'] );
		}
	}
	return array( $created, $updated );
}

/**
 * Rendez-vous à retirer, publics et internes.
 * $place : '' = tous les lieux, '-' = sans paroisse ni chapelle, sinon slug de catégorie.
 * $type  : '' = tous les groupes, '-' = sans groupe, sinon slug de type.
 * $from  : '' = tout, passé compris ; sinon date AAAA-MM-JJ (rendez-vous à partir de ce jour).
 */
function vsg_purge_ids( $place, $type, $from ) {
	$args = array(
		'post_type'      => 'evenement',
		'post_status'    => array( 'publish', 'private', 'draft', 'future', 'pending' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(),
	);
	if ( $from ) {
		$args['meta_query'][] = array(
			'key'     => '_vsg_date',
			'value'   => $from,
			'compare' => '>=',
		);
	}
	if ( '-' === $type ) {
		$args['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key'     => '_vsg_type',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'   => '_vsg_type',
				'value' => '',
			),
		);
	} elseif ( $type ) {
		$args['meta_query'][] = array(
			'key'   => '_vsg_type',
			'value' => $type,
		);
	}
	$known = array_values( vsg_import_places() );
	if ( '-' === $place ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'category',
				'field'    => 'slug',
				'terms'    => $known,
				'operator' => 'NOT IN',
			),
		);
	} elseif ( $place ) {
		$args['tax_query'] = array(
			array(
				'taxonomy'         => 'category',
				'field'            => 'slug',
				'terms'            => array( $place ),
				'include_children' => false,
			),
		);
	}
	return get_posts( $args );
}

/* Un rendez-vous sorti de la corbeille retrouve son statut d'avant (public ou interne), et non « brouillon ». */
add_filter(
	'wp_untrash_post_status',
	function ( $status, $post_id, $previous ) {
		return 'evenement' === get_post_type( $post_id ) && $previous ? $previous : $status;
	},
	10,
	3
);

/** Description lisible de la sélection, pour l'écran de confirmation. */
function vsg_purge_label( $place, $type, $from ) {
	$places = array_flip( vsg_import_places() );
	$types  = vsg_event_types();
	$p      = '' === $place ? 'tous les lieux' : ( '-' === $place ? 'sans paroisse ni chapelle' : ( $places[ $place ] ?? $place ) );
	$t      = '' === $type ? 'tous les groupes' : ( '-' === $type ? 'sans groupe' : ( $types[ $type ][0] ?? $type ) );
	return $p . ' · ' . $t . ' · ' . ( $from ? 'à venir seulement' : 'passés et à venir' );
}
