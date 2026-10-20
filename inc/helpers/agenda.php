<?php
/**
 * Rendez-vous : types, requêtes, regroupement par mois ; actualités et feuilles paroissiales.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Types de rendez-vous : slug => [libellé, couleur, groupe].
 * « groupe » = true : une communauté ou un groupe de prière, dont le programme est importé à part
 * et s'affiche seulement sur sa page ; false : la vie paroissiale (agenda du site), y compris
 * les groupes liés à la paroisse (chorale, servants, aumônerie, jeunes), qui ont aussi leur page.
 */
function vsg_event_types() {
	return array(
		/* Communautés et groupes de prière : programme à part */
		'padre-pio'        => array( 'Communauté des Enfants de Padre Pio', '#2E6B4E', true ),
		'tamoule'          => array( 'Communauté tamoule', '#B85C1A', true ),
		'anizan'           => array( 'Groupe Anizan', '#1F2A3A', true ),
		'groupe-priere'    => array( 'Groupe de prière', '#7C4A2D', true ),
		/* Vie paroissiale : agenda du site */
		'liturgie'         => array( 'Liturgie, temps forts', '#5B4A8A', false ),
		'sacrements'       => array( 'Baptême, communion, confirmation', '#7A2E3B', false ),
		'catechisme'       => array( 'Catéchisme', '#B0306B', false ),
		'aumonerie'        => array( 'Aumônerie collège et lycée', '#3B4CCA', false ),
		'pastorale-jeunes' => array( 'Pastorale des jeunes', '#0F766E', false ),
		'jeunes-18-30'     => array( 'Les 18-30 ans', '#1D5C8C', false ),
		'servants'         => array( 'Servants d\'autel', '#9E2A2A', false ),
		'chorale'          => array( 'Chorale paroissiale', '#9D3C9D', false ),
		'paroisse'         => array( 'Vie des paroisses', '#8C6516', false ),
		'brocante'         => array( 'Brocante', '#1D6FA3', false ),
		'diocese'          => array( 'Diocèse', '#2A8FBD', false ),
		'eap'              => array( 'EAP, CEP, équipes', '#C0392B', false ),
		'salle'            => array( 'Réservation de salle', '#6B7280', false ),
	);
}

/** Options d'une liste déroulante des types, rangées en deux groupes. */
function vsg_event_type_options( $current = '' ) {
	$out = '';
	foreach ( array(
		'Communautés et groupes de prière' => true,
		'Vie paroissiale'                  => false,
	) as $label => $is_group ) {
		$out .= '<optgroup label="' . esc_attr( $label ) . '">';
		foreach ( vsg_event_types() as $slug => $t ) {
			if ( (bool) $t[2] === $is_group ) {
				$out .= '<option value="' . esc_attr( $slug ) . '"' . selected( $current, $slug, false ) . '>' . esc_html( $t[0] ) . '</option>';
			}
		}
		$out .= '</optgroup>';
	}
	return $out;
}

/** Anciens libellés encore reconnus à l'import. */
function vsg_event_type_aliases() {
	return array(
		'Catéchisme, aumônerie' => 'catechisme',
		'Communauté Padre Pio'  => 'padre-pio',
		'Servants d\'autel'     => 'servants',
		'18-30 ans'             => 'jeunes-18-30',
		'Aumônerie'             => 'aumonerie',
		'Chorale'               => 'chorale',
	);
}

function vsg_event_type( $post ) {
	$slug  = vsg_meta( $post->ID, 'type' );
	$types = vsg_event_types();
	return isset( $types[ $slug ] ) ? array_merge(
		array( 'slug' => $slug ),
		array(
			'label' => $types[ $slug ][0],
			'color' => $types[ $slug ][1],
		)
	) : null;
}

function vsg_events_query( $args = array() ) {
	$args   = wp_parse_args(
		$args,
		array(
			'terms'         => array(),
			'limit'         => 3,
			'from'          => vsg_today(),
			'children'      => true,
			'exclude_terms' => array(),
			'kind'          => '', // '' = tout, 'parish' = activités paroissiales, 'groups' = groupes et communautés
			'type'          => '', // un type de rendez-vous précis
		)
	);
	$q      = array(
		'post_type'      => 'evenement',
		'post_status'    => current_user_can( 'read_private_posts' ) ? array( 'publish', 'private' ) : 'publish',
		'posts_per_page' => $args['limit'],
		'meta_key'       => '_vsg_date',
		'orderby'        => array(
			'meta_value' => 'ASC',
			'title'      => 'ASC',
		),
		'meta_query'     => array(
			array(
				'key'     => '_vsg_date',
				'value'   => $args['from'],
				'compare' => '>=',
				'type'    => 'DATE',
			),
		),
	);
	$groups = array_keys( vsg_group_types() );
	if ( 'parish' === $args['kind'] ) {
		$q['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key'     => '_vsg_type',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_vsg_type',
				'value'   => $groups,
				'compare' => 'NOT IN',
			),
		);
	} elseif ( 'groups' === $args['kind'] ) {
		$q['meta_query'][] = array(
			'key'     => '_vsg_type',
			'value'   => $groups,
			'compare' => 'IN',
		);
	}
	if ( $args['type'] ) {
		$q['meta_query'][] = array(
			'key'   => '_vsg_type',
			'value' => $args['type'],
		);
	}
	if ( $args['terms'] ) {
		$q['tax_query'] = array(
			array(
				'taxonomy'         => 'category',
				'terms'            => $args['terms'],
				'include_children' => (bool) $args['children'],
			),
		);
		if ( $args['exclude_terms'] ) {
			$q['tax_query'][] = array(
				'taxonomy' => 'category',
				'terms'    => $args['exclude_terms'],
				'operator' => 'NOT IN',
			);
		}
	}
	return new WP_Query( $q );
}

/** Paroisse d'un rendez-vous, d'après sa catégorie (une chapelle compte pour sa paroisse). */
function vsg_event_parish( $post ) {
	foreach ( vsg_parishes() as $p ) {
		$ids = array();
		$t   = vsg_page_term( $p->ID );
		if ( $t ) {
			$ids[] = (int) $t->term_id;
			$ids   = array_merge( $ids, array_map( 'intval', get_term_children( $t->term_id, 'category' ) ) );
		}
		if ( $ids && has_term( $ids, 'category', $post ) ) {
			return $p;
		}
	}
	return null;
}

/** Groupe organisateur d'un rendez-vous : array( label, color, url ) ou null. */
function vsg_event_group( $post ) {
	$type = vsg_event_type( $post );
	if ( ! $type || ! array_key_exists( $type['slug'], vsg_group_types() ) ) {
		return null;
	}
	$page = vsg_group_page( $type['slug'], vsg_event_parish( $post ) );
	return array(
		'label' => $type['label'],
		'color' => $type['color'],
		'url'   => $page ? get_permalink( $page ) : '',
		'page'  => $page,
	);
}

/** Regroupe des rendez-vous par mois puis par jour : ym => array( label, days => array( ymd => posts ) ). */
function vsg_events_by_month( $posts ) {
	$months = array();
	foreach ( $posts as $ev ) {
		$date  = vsg_meta( $ev->ID, 'date' );
		$parts = vsg_date_parts( $date );
		$key   = $parts ? $parts['ym'] : '0000-00';
		if ( ! isset( $months[ $key ] ) ) {
			$months[ $key ] = array(
				'label' => $parts ? ucfirst( $parts['month_full'] ) . ' ' . $parts['year'] : '',
				'days'  => array(),
			);
		}
		$months[ $key ]['days'][ $date ][] = $ev;
	}
	return $months;
}

/** Liste mois par mois (un mois ouvert, les autres repliés). */
function vsg_months_list( $months, $show_place = true ) {
	$i = 0;
	foreach ( $months as $ym => $m ) {
		++$i;
		echo '<details class="month"' . ( 1 === $i ? ' open' : '' ) . ' id="m-' . esc_attr( $ym ) . '">';
		echo '<summary><h2 class="month__title">' . esc_html( $m['label'] ) . '</h2><span class="month__count">' . esc_html( array_sum( array_map( 'count', $m['days'] ) ) ) . ' rendez-vous</span></summary><div class="month__body">';
		foreach ( $m['days'] as $ymd => $evs ) {
			echo '<div class="day"><h3 class="day__title">' . esc_html( ucfirst( vsg_date_long( $ymd ) ) ) . '</h3><div class="list list--tight">';
			foreach ( $evs as $ev ) {
				echo vsg_event( $ev, 'row', $show_place );
			}
			echo '</div></div>';
		}
		echo '</div></details>';
	}
}

/** Les $n premiers rendez-vous d'une requête, sans répéter un même titre (ex. brocante sur deux jours). */
function vsg_events_unique( $posts, $n = 3 ) {
	/* Les rendez-vous qui ont une affiche (image mise en avant) passent devant : ce sont les temps forts annoncés. */
	$posts = array_merge(
		array_filter(
			$posts,
			function ( $p ) {
				return has_post_thumbnail( $p ); }
		),
		array_filter(
			$posts,
			function ( $p ) {
				return ! has_post_thumbnail( $p ); }
		)
	);
	$out   = array();
	$seen  = array();
	foreach ( $posts as $p ) {
		$k = get_the_title( $p );
		if ( isset( $seen[ $k ] ) ) {
			continue;
		}
		$seen[ $k ] = true;
		$out[]      = $p;
		if ( count( $out ) >= $n ) {
			break;
		}
	}
	return $out;
}

/** Lieu affichable : paroisse ou chapelle (sauf « Les deux paroisses ») et lieu précis. */
function vsg_event_where( $post ) {
	$place  = vsg_place_label( $post );
	$common = vsg_common_term();
	if ( $common && $place === $common->name ) {
		$place = '';
	}
	$lieu = vsg_meta( $post->ID, 'lieu' );
	if ( $place && $lieu && false !== mb_stripos( $lieu, $place ) ) {
		$place = ''; // « Petite salle, Notre-Dame de Lourdes » nomme déjà la paroisse.
	}
	return array_values( array_filter( array( $place, $lieu ) ) );
}

function vsg_news_query( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'terms'         => array(),
			'limit'         => 3,
			'paged'         => 1,
			'exclude'       => array(),
			'children'      => true,
			'exclude_terms' => array(),
		)
	);
	$q    = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $args['limit'],
		'paged'               => $args['paged'],
		'post__not_in'        => $args['exclude'],
		'ignore_sticky_posts' => true,
	);
	if ( $args['terms'] ) {
		$q['tax_query'] = array(
			array(
				'taxonomy'         => 'category',
				'terms'            => $args['terms'],
				'include_children' => (bool) $args['children'],
			),
		);
		if ( $args['exclude_terms'] ) {
			$q['tax_query'][] = array(
				'taxonomy' => 'category',
				'terms'    => $args['exclude_terms'],
				'operator' => 'NOT IN',
			);
		}
	}
	return new WP_Query( $q );
}

function vsg_feuilles_query( $terms, $limit = 1 ) {
	return new WP_Query(
		array(
			'post_type'      => 'feuille',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'tax_query'      => array(
				array(
					'taxonomy'         => 'category',
					'terms'            => $terms,
					'include_children' => false,
				),
			),
		)
	);
}

/** Titre d'une feuille sans le nom de la paroisse (déjà affiché sur le site). */
function vsg_feuille_label( $post ) {
	$title = get_the_title( $post );
	foreach ( vsg_parishes() as $p ) {
		$prefix = get_the_title( $p ) . ' – ';
		if ( 0 === strpos( $title, $prefix ) ) {
			return substr( $title, strlen( $prefix ) );
		}
	}
	return $title;
}

/** Lien du PDF d'une feuille : fichier joint, sinon premier lien .pdf du contenu. */
function vsg_feuille_pdf( $post ) {
	$post  = get_post( $post );
	$media = get_attached_media( 'application/pdf', $post->ID );
	if ( $media ) {
		$first = reset( $media );
		return wp_get_attachment_url( $first->ID );
	}
	if ( preg_match( '/href="([^"]+\.pdf)"/i', $post->post_content, $m ) ) {
		return $m[1];
	}
	return '';
}
