<?php
/**
 * Paroisses, chapelles, catégories rattachées, couleurs, pages communes et liens externes.
 */

defined( 'ABSPATH' ) || exit;

/** Pages « paroisse », dans l'ordre du menu. */
function vsg_parishes() {
	static $cache = null;
	if ( null === $cache ) {
		$cache = get_pages(
			array(
				'meta_key'    => '_wp_page_template',
				'meta_value'  => VSG_TPL_PAROISSE,
				'sort_column' => 'menu_order',
				'hierarchical'=> 0,
			)
		);
	}
	return $cache;
}

/** Chapelles d'une paroisse (pages enfants avec le modèle « chapelle »). */
function vsg_chapels( $parish_id ) {
	$out = array();
	foreach ( get_pages( array( 'parent' => $parish_id, 'sort_column' => 'menu_order' ) ) as $p ) {
		if ( VSG_TPL_CHAPELLE === get_page_template_slug( $p ) ) {
			$out[] = $p;
		}
	}
	return $out;
}

/** Terme de catégorie rattaché à une page (paroisse ou chapelle). */
function vsg_page_term( $page_id ) {
	$slug = vsg_meta( $page_id, 'cat' );
	return $slug ? get_term_by( 'slug', $slug, 'category' ) : false;
}

function vsg_common_term() {
	return get_term_by( 'slug', VSG_COMMON_CAT, 'category' );
}

/** Catégories à afficher pour une paroisse : la sienne (avec ses chapelles) et « les deux paroisses ». */
function vsg_parish_term_ids( $parish_id ) {
	$ids  = array();
	$term = vsg_page_term( $parish_id );
	if ( $term ) {
		$ids[] = (int) $term->term_id;
	}
	$common = vsg_common_term();
	if ( $common ) {
		$ids[] = (int) $common->term_id;
	}
	return $ids;
}

/** Paroisse à laquelle se rattache le contenu affiché (page, article, rendez-vous, feuille). */
function vsg_context_parish( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return null;
	}
	if ( 'page' === $post->post_type ) {
		$chain = array_merge( array( $post->ID ), get_post_ancestors( $post ) );
		foreach ( $chain as $id ) {
			if ( VSG_TPL_PAROISSE === get_page_template_slug( $id ) ) {
				return get_post( $id );
			}
		}
		return null;
	}
	$terms = get_the_terms( $post, 'category' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return null;
	}
	foreach ( vsg_parishes() as $parish ) {
		$pt = vsg_page_term( $parish->ID );
		if ( ! $pt ) {
			continue;
		}
		foreach ( $terms as $t ) {
			if ( (int) $t->term_id === (int) $pt->term_id || term_is_ancestor_of( $pt, $t, 'category' ) ) {
				return $parish;
			}
		}
	}
	return null;
}

/** Nom du lieu le plus précis d'un contenu (chapelle > paroisse > « Les deux paroisses »). */
function vsg_place_label( $post ) {
	$terms = get_the_terms( $post, 'category' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	/* La catégorie par défaut de WordPress (« Uncategorized », « Non classé ») n'est jamais affichée. */
	$default = (int) get_option( 'default_category' );
	$terms   = array_values(
		array_filter(
			$terms,
			function ( $t ) use ( $default ) {
				return (int) $t->term_id !== $default;
			}
		)
	);
	if ( ! $terms ) {
		return '';
	}
	usort(
		$terms,
		function ( $a, $b ) {
			return ( $b->parent ? 1 : 0 ) - ( $a->parent ? 1 : 0 );
		}
	);
	return $terms[0]->name;
}

/** Page d'un modèle donné à l'intérieur d'une paroisse. */
function vsg_parish_child_by_template( $parish_id, $template ) {
	foreach ( get_pages( array( 'parent' => $parish_id ) ) as $p ) {
		if ( $template === get_page_template_slug( $p ) ) {
			return $p;
		}
	}
	return null;
}

/** Page commune repérée par son identifiant court. */
function vsg_page_url( $slug ) {
	$p = get_page_by_path( $slug );
	return $p ? get_permalink( $p ) : home_url( '/' );
}

/** Lien vers le denier du diocèse de Créteil (modifiable par un filtre). */
function vsg_denier_url() {
	return apply_filters( 'vsg_denier_url', 'https://denier.diocese94.fr/denier/~mon-don' );
}

/** Textes du jour sur le site de l'AELF (lectures de la messe, ou « lectures » pour la liturgie des heures). */
function vsg_aelf_url( $office = 'messe' ) {
	return 'https://www.aelf.org/' . wp_date( 'Y-m-d' ) . '/romain/' . ( 'heures' === $office ? 'lectures' : 'messe' );
}

/** Couleur d'accent d'une paroisse : réglée dans sa fiche, sinon une couleur par défaut selon son rang. */
function vsg_parish_color( $parish ) {
	$parish = get_post( $parish );
	if ( ! $parish ) {
		return '';
	}
	$c = vsg_meta( $parish->ID, 'couleur' );
	if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ) {
		return $c;
	}
	$defaults = array( '#8C6516', '#1F3A5F', '#7A2E3B', '#5B4A8A' );
	$i        = 0;
	foreach ( vsg_parishes() as $k => $p ) {
		if ( $p->ID === $parish->ID ) {
			$i = $k;
		}
	}
	return $defaults[ $i % count( $defaults ) ];
}

/** Couleur d'un terme de catégorie : celle de la paroisse à laquelle il appartient. */
function vsg_term_color( $term ) {
	if ( ! $term ) {
		return '';
	}
	foreach ( vsg_parishes() as $p ) {
		$pt = vsg_page_term( $p->ID );
		if ( $pt && ( (int) $pt->term_id === (int) $term->term_id || term_is_ancestor_of( $pt, $term, 'category' ) ) ) {
			return vsg_parish_color( $p );
		}
	}
	return '';
}

/** Attribut style="--accent:…" pour un contenu, d'après sa paroisse. */
function vsg_accent_attr( $post ) {
	$parish = vsg_context_parish( $post );
	$color  = $parish ? vsg_parish_color( $parish ) : '';
	return $color ? ' style="--accent:' . esc_attr( $color ) . '"' : '';
}

function vsg_maps_url( $address ) {
	return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address );
}

/** Lien « Itinéraire » vers Google Maps, avec une petite épingle. */
function vsg_itineraire( $address, $place = '', $class = 'maplink' ) {
	if ( ! $address ) {
		return '';
	}
	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( vsg_maps_url( $address ) ) . '" target="_blank" rel="noopener"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>Itinéraire<span class="sr-only">' . ( $place ? ' vers ' . esc_html( $place ) : '' ) . ' (Google Maps, nouvel onglet)</span></a>';
}
