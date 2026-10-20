<?php
/**
 * Données des modèles de page : chaque fonction prépare ce qu'un modèle affiche
 * (filtres, requêtes, champs), pour que les fichiers de templates/ ne fassent qu'afficher.
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Filtres par lieu ---------- */

/**
 * Lieux proposés en filtre : slug de catégorie => libellé.
 *
 * @param WP_Post|null $parish Une paroisse (son église et ses chapelles), ou null pour tous les lieux.
 */
function vsg_places_filter( $parish = null ) {
	$places   = array();
	$parishes = $parish ? array( $parish ) : vsg_parishes();
	foreach ( $parishes as $p ) {
		$pt = vsg_page_term( $p->ID );
		if ( $pt ) {
			$places[ $pt->slug ] = ( $parish ? 'Église ' : '' ) . get_the_title( $p );
		}
		foreach ( vsg_chapels( $p->ID ) as $c ) {
			$ct = vsg_page_term( $c->ID );
			if ( $ct ) {
				$places[ $ct->slug ] = get_the_title( $c );
			}
		}
	}
	return $places;
}

/** Valeur d'un filtre passé dans l'adresse (?lieu=…, ?type=…), limitée aux choix possibles. */
function vsg_filter_value( $param, $allowed ) {
	$v = isset( $_GET[ $param ] ) ? sanitize_title( wp_unslash( $_GET[ $param ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple filtre d'affichage.
	return isset( $allowed[ $v ] ) ? $v : '';
}

/** Numéro de page demandé (?pg=…). */
function vsg_page_number() {
	return max( 1, absint( $_GET['pg'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple pagination.
}

/** Liens du filtre « Lieu » : slug => array( libellé, adresse ). */
function vsg_places_items( $places, $base, $extra = array() ) {
	$items = array( '' => array( 'Tous les lieux', $extra ? add_query_arg( $extra, $base ) : $base ) );
	foreach ( $places as $slug => $label ) {
		$items[ $slug ] = array( $label, add_query_arg( array_merge( array( 'lieu' => $slug ), $extra ), $base ) );
	}
	return $items;
}

/* ---------- Agenda général ---------- */

/** Adresse de l'agenda pour un lieu et un type donnés. */
function vsg_agenda_link( $base, $lieu, $type ) {
	$args = array_filter(
		array(
			'lieu' => $lieu,
			'type' => $type,
		)
	);
	return $args ? add_query_arg( $args, $base ) : $base;
}

/** Types proposés en filtre sur l'agenda : la vie paroissiale, sans les réservations de salle pour le public. */
function vsg_agenda_types() {
	$out = array();
	foreach ( vsg_event_types() as $slug => $t ) {
		if ( ! empty( $t[2] ) || ( 'salle' === $slug && ! current_user_can( 'read_private_posts' ) ) ) {
			continue;
		}
		$out[ $slug ] = $t;
	}
	return $out;
}

/** Liens du filtre « Activité » (HTML), repris dans la légende et dans le menu déroulant. */
function vsg_agenda_type_links( $base, $lieu, $type ) {
	$h = '<a href="' . esc_url( vsg_agenda_link( $base, $lieu, '' ) ) . '"' . ( '' === $type ? ' aria-current="true"' : '' ) . '>Toutes les activités</a>';
	foreach ( vsg_agenda_types() as $slug => $t ) {
		$h .= '<a href="' . esc_url( vsg_agenda_link( $base, $lieu, $slug ) ) . '"' . ( $slug === $type ? ' aria-current="true"' : '' ) . '><span class="dot" style="background:' . esc_attr( $t[1] ) . '"></span>' . esc_html( $t[0] ) . '</a>';
	}
	return $h;
}

/**
 * Données de l'agenda général.
 *
 * @return array{places:array,lieu:string,type:string,type_label:string,months:array,base:string}
 */
function vsg_agenda_page_data() {
	$places = vsg_places_filter();
	$types  = vsg_event_types();
	$lieu   = vsg_filter_value( 'lieu', $places );
	$type   = vsg_filter_value( 'type', $types );
	if ( $type && ! empty( $types[ $type ][2] ) ) {
		$type = '';
	}
	$terms = array();
	if ( $lieu ) {
		$terms[] = get_term_by( 'slug', $lieu, 'category' )->term_id;
		$common  = vsg_common_term();
		if ( $common ) {
			$terms[] = $common->term_id;
		}
	}
	/* Une paroisse n'inclut pas ses chapelles : chaque lieu a son filtre. Le type est filtré dans la requête. */
	$q = vsg_events_query(
		array(
			'terms'    => $terms,
			'limit'    => 500,
			'children' => false,
			'kind'     => 'parish',
			'type'     => $type,
		)
	);
	return array(
		'places'     => $places,
		'lieu'       => $lieu,
		'type'       => $type,
		'type_label' => $type ? $types[ $type ][0] : 'Toutes les activités',
		'months'     => vsg_events_by_month( $q->posts ),
		'base'       => get_permalink(),
	);
}

/* ---------- Actualités et agenda d'une paroisse ---------- */

/**
 * Données de la page « actualités et agenda » d'une paroisse.
 *
 * @return array{places:array,lieu:string,page:int,base:string,events:WP_Query,news:WP_Query}
 */
function vsg_actualites_page_data( $parish ) {
	$places = $parish ? vsg_places_filter( $parish ) : array();
	$lieu   = vsg_filter_value( 'lieu', $places );
	$pt     = $parish ? vsg_page_term( $parish->ID ) : null;
	$church = $lieu && $pt && $lieu === $pt->slug;

	if ( $lieu ) {
		$terms = array( $church ? $pt->term_id : get_term_by( 'slug', $lieu, 'category' )->term_id );
	} else {
		$terms = $parish ? vsg_parish_term_ids( $parish->ID ) : array();
	}
	/* L'église seule : la paroisse et ses communautés, sans les chapelles. */
	$exclude = array();
	if ( $church ) {
		foreach ( vsg_chapels( $parish->ID ) as $c ) {
			$ct = vsg_page_term( $c->ID );
			if ( $ct ) {
				$exclude[] = (int) $ct->term_id;
			}
		}
	}
	$page = vsg_page_number();
	return array(
		'places' => $places,
		'lieu'   => $lieu,
		'page'   => $page,
		'base'   => get_permalink(),
		'events' => vsg_events_query(
			array(
				'terms'         => $terms,
				'limit'         => 8,
				'kind'          => 'parish',
				'exclude_terms' => $exclude,
			)
		),
		'news'   => vsg_news_query(
			array(
				'terms'         => $terms,
				'limit'         => 6,
				'paged'         => $page,
				'exclude_terms' => $exclude,
			)
		),
	);
}

/** Pagination des actualités (HTML). */
function vsg_news_pager( $pages, $current, $base, $lieu ) {
	if ( $pages < 2 ) {
		return '';
	}
	$h = '<nav class="pager" aria-label="Pages d\'actualités">';
	for ( $i = 1; $i <= $pages; $i++ ) {
		$url = add_query_arg(
			array_filter(
				array(
					'pg'   => $i > 1 ? $i : null,
					'lieu' => $lieu ? $lieu : null,
				)
			),
			$base
		);
		$h  .= '<a href="' . esc_url( $url ) . '"' . ( $i === $current ? ' aria-current="page"' : '' ) . '>' . (int) $i . '</a>';
	}
	return $h . '</nav>';
}

/* ---------- Communauté ---------- */

/**
 * Données de la page d'une communauté ou d'un groupe de prière.
 *
 * @return array{term:WP_Term|false,prog:array,site:string,links:array,resp:string,tel:string,mail:string,lieu:string,gtype:string,months:array,news:WP_Query|null}
 */
function vsg_communaute_page_data( $community, $parish ) {
	$id    = $community->ID;
	$term  = vsg_page_term( $id );
	$gtype = vsg_meta( $id, 'type' );
	$gtype = isset( vsg_event_types()[ $gtype ] ) ? $gtype : '';

	/* Programme : les rendez-vous du groupe (type), sinon ceux de sa catégorie. */
	$list = array();
	if ( $gtype ) {
		/* Un groupe présent dans les deux paroisses (la chorale) : chaque page montre sa paroisse, plus les rendez-vous sans lieu. */
		$shared = $parish && count(
			get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'meta_key'       => '_vsg_type',
					'meta_value'     => $gtype,
					'fields'         => 'ids',
					'posts_per_page' => 2,
				)
			)
		) > 1; // phpcs:ignore WordPress.DB.SlowDBQuery -- deux pages au plus, mise en cache par WordPress.
		$all    = vsg_events_query(
			array(
				'type'  => $gtype,
				'limit' => 500,
			)
		)->posts;
		if ( $shared ) {
			$mine = wp_list_pluck(
				vsg_events_query(
					array(
						'terms' => vsg_parish_term_ids( $parish->ID ),
						'type'  => $gtype,
						'limit' => 500,
					)
				)->posts,
				'ID'
			);
			$list = array_filter(
				$all,
				function ( $ev ) use ( $mine ) {
					return in_array( $ev->ID, $mine, true ) || ! vsg_place_label( $ev );
				}
			);
		} else {
			$list = $all;
		}
	} elseif ( $term ) {
		$list = vsg_events_query(
			array(
				'terms' => array( $term->term_id ),
				'limit' => 500,
			)
		)->posts;
	}

	return array(
		'term'   => $term,
		'prog'   => array_values( array_filter( array_map( 'trim', explode( "\n", vsg_meta( $id, 'programme' ) ) ) ) ),
		'site'   => vsg_meta( $id, 'site' ),
		'links'  => array_filter(
			array(
				'Facebook'  => vsg_meta( $id, 'facebook' ),
				'Instagram' => vsg_meta( $id, 'instagram' ),
				'YouTube'   => vsg_meta( $id, 'youtube' ),
			)
		),
		'resp'   => vsg_meta( $id, 'responsable' ),
		'tel'    => vsg_meta( $id, 'telephone' ),
		'mail'   => vsg_meta( $id, 'email' ),
		'lieu'   => vsg_meta( $id, 'lieu' ),
		'gtype'  => $gtype,
		'months' => vsg_events_by_month( $list ),
		'news'   => $term ? vsg_news_query(
			array(
				'terms' => array( $term->term_id ),
				'limit' => 3,
			)
		) : null,
	);
}

/* ---------- Feuilles paroissiales ---------- */

/** Feuilles d'une paroisse : la dernière et les archives. */
function vsg_feuille_page_data( $parish ) {
	$term = $parish ? vsg_page_term( $parish->ID ) : null;
	$all  = $term ? vsg_feuilles_query( array( $term->term_id ), 13 )->posts : array();
	return array(
		'current'  => $all ? array_shift( $all ) : null,
		'archives' => $all,
	);
}

/** Contenu d'une feuille sans le bloc « fichier » (le PDF est proposé à part). */
function vsg_feuille_body( $post ) {
	$rest = '';
	foreach ( parse_blocks( $post->post_content ) as $b ) {
		if ( 'core/file' !== $b['blockName'] ) {
			$rest .= render_block( $b );
		}
	}
	return $rest;
}

/* ---------- Bandeau d'une paroisse ---------- */

/**
 * Onglets d'une paroisse et onglet actif.
 *
 * @return array{tabs:WP_Post[],active:int,label:string}
 */
function vsg_parish_tabs( $parish, $current ) {
	$tabs = array( $parish );
	foreach ( get_pages(
		array(
			'parent'      => $parish->ID,
			'sort_column' => 'menu_order',
		)
	) as $child ) {
		if ( VSG_TPL_CHAPELLE !== get_page_template_slug( $child ) && ! vsg_page_is_empty( $child ) ) {
			$tabs[] = $child;
		}
	}
	$active = $parish->ID;
	if ( $current instanceof WP_Post ) {
		if ( 'page' === $current->post_type ) {
			$chain = array_merge( array( $current->ID ), get_post_ancestors( $current ) );
			foreach ( $tabs as $t ) {
				if ( $t->ID !== $parish->ID && in_array( $t->ID, $chain, true ) ) {
					$active = $t->ID;
				}
			}
			if ( VSG_TPL_CHAPELLE === get_page_template_slug( $current ) ) {
				$cp = vsg_parish_child_by_template( $parish->ID, 'templates/chapelles.php' );
				if ( $cp ) {
					$active = $cp->ID;
				}
			}
		} else {
			$tpl = 'feuille' === $current->post_type ? 'templates/feuille.php' : 'templates/actualites.php';
			$p   = vsg_parish_child_by_template( $parish->ID, $tpl );
			if ( $p ) {
				$active = $p->ID;
			}
		}
	}
	$label = 'Présentation';
	foreach ( $tabs as $t ) {
		if ( $t->ID === $active ) {
			$label = $t->ID === $parish->ID ? 'Présentation' : get_the_title( $t );
		}
	}
	return array(
		'tabs'   => $tabs,
		'active' => $active,
		'label'  => $label,
	);
}
