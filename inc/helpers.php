<?php
/**
 * Fonctions utilitaires : paroisses, dates, cartes d'affichage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VSG_TPL_PAROISSE = 'templates/paroisse.php';
const VSG_TPL_CHAPELLE = 'templates/chapelle.php';
const VSG_COMMON_CAT   = 'les-deux-paroisses';
const VSG_TPL_COMMUNAUTE  = 'templates/communaute.php';
const VSG_TPL_COMMUNAUTES = 'templates/communautes.php';
const VSG_TPL_HORAIRES    = 'templates/horaires.php';

/* ---------- Méta ---------- */

function vsg_meta( $post_id, $key ) {
	return (string) get_post_meta( $post_id, '_vsg_' . $key, true );
}

/* ---------- Dates en français, indépendantes de la langue du site ---------- */

function vsg_months() {
	return array( 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' );
}

function vsg_months_short() {
	return array( 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.' );
}

function vsg_days() {
	return array( 'dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi' );
}

/** Découpe une date AAAA-MM-JJ en éléments affichables. */
function vsg_date_parts( $ymd ) {
	$t = strtotime( $ymd . ' 12:00:00' );
	if ( ! $t ) {
		return null;
	}
	$m = (int) gmdate( 'n', $t ) - 1;
	return array(
		'day'       => gmdate( 'j', $t ),
		'month'     => vsg_months_short()[ $m ],
		'month_full'=> vsg_months()[ $m ],
		'year'      => gmdate( 'Y', $t ),
		'dow'       => vsg_days()[ (int) gmdate( 'w', $t ) ],
		'ym'        => gmdate( 'Y-m', $t ),
	);
}

function vsg_date_long( $ymd ) {
	$p = vsg_date_parts( $ymd );
	return $p ? sprintf( '%s %s %s', $p['dow'], $p['day'] === '1' ? '1er' : $p['day'], $p['month_full'] ) : '';
}

/** Date de publication d'un article, en français. */
function vsg_post_date( $post ) {
	$p = vsg_date_parts( get_the_date( 'Y-m-d', $post ) );
	return $p ? sprintf( '%s %s %s', $p['day'] === '1' ? '1er' : $p['day'], $p['month_full'], $p['year'] ) : '';
}

function vsg_time_label( $hm ) {
	if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $hm, $m ) ) {
		return '';
	}
	return (int) $m[1] . ' h' . ( '00' === $m[2] ? '' : ' ' . $m[2] );
}

function vsg_today() {
	return current_time( 'Y-m-d' );
}

/* ---------- Paroisses et lieux ---------- */

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

/**
 * Communautés et groupes de prière (pages avec le modèle « Communauté »).
 * Avec une paroisse : seulement celles qui lui sont rattachées.
 */
function vsg_communities( $parish_id = null ) {
	$pages = get_pages(
		array(
			'meta_key'     => '_wp_page_template',
			'meta_value'   => VSG_TPL_COMMUNAUTE,
			'sort_column'  => 'menu_order,post_title',
			'hierarchical' => 0,
		)
	);
	if ( null === $parish_id ) {
		return $pages;
	}
	return array_values(
		array_filter(
			$pages,
			function ( $c ) use ( $parish_id ) {
				$p = vsg_context_parish( $c );
				return $p && (int) $p->ID === (int) $parish_id;
			}
		)
	);
}

/** Communautés communes aux deux paroisses (hors arborescence d'une paroisse). */
function vsg_communities_common() {
	return array_values( array_filter( vsg_communities(), function ( $c ) { return ! vsg_context_parish( $c ); } ) );
}

/**
 * Menu « Groupe ou communauté » : tous les groupes, rangés par paroisse, pour aller droit à l'un d'eux.
 * $current : ID de la page affichée (ou 0 sur la page qui les liste).
 */
function vsg_communities_menu( $current = 0 ) {
	$all = vsg_communities_page();
	if ( ! vsg_communities() ) {
		return;
	}
	$items = array();
	if ( $all ) {
		$items[''] = array( 'Tous les groupes', get_permalink( $all ) );
	}
	$groups = array( 'Pour les deux paroisses' => vsg_communities_common() );
	foreach ( vsg_parishes() as $p ) {
		$groups[ get_the_title( $p ) ] = vsg_communities( $p->ID );
	}
	$seen = array_count_values( wp_list_pluck( vsg_communities(), 'post_title' ) );
	foreach ( $groups as $head => $list ) {
		if ( ! $list ) {
			continue;
		}
		$items[ 'head-' . sanitize_title( $head ) ] = array( $head, '' );
		foreach ( $list as $c ) {
			$label = get_the_title( $c );
			$ctx   = vsg_context_parish( $c );
			if ( $ctx && $seen[ $label ] > 1 ) {
				$label .= ' – ' . get_the_title( $ctx );
			}
			$items[ 'c-' . $c->ID ] = array( $label, get_permalink( $c ) . '#programme' );
		}
	}
	$cur = $current ? 'c-' . $current : '';
	$cur_label = isset( $items[ $cur ] ) ? $items[ $cur ][0] : '';
	$links = '';
	foreach ( $items as $slug => $it ) {
		if ( '' === $it[1] ) {
			$links .= '<span class="legend__head">' . esc_html( $it[0] ) . '</span>';
		} else {
			$links .= '<a href="' . esc_url( $it[1] ) . '"' . ( $slug === $cur ? ' aria-current="true"' : '' ) . '>' . esc_html( $it[0] ) . '</a>';
		}
	}
	echo '<nav class="filters filters--groups" aria-label="Groupe ou communauté">' . $links . '</nav>'; // phpcs:ignore
	echo '<details class="menu menu--filters"><summary><span class="menu__label">Groupe ou communauté</span><span class="menu__current">' . esc_html( $cur_label ) . '</span></summary><nav aria-label="Groupe ou communauté">' . $links . '</nav></details>'; // phpcs:ignore
}

/** Page commune qui liste toutes les communautés (modèle « Communautés », hors paroisse). */
function vsg_communities_page() {
	foreach ( get_pages( array( 'meta_key' => '_wp_page_template', 'meta_value' => VSG_TPL_COMMUNAUTES, 'hierarchical' => 0 ) ) as $p ) {
		if ( ! vsg_context_parish( $p ) ) {
			return $p;
		}
	}
	return null;
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
/** Lien vers le denier du diocèse de Créteil (modifiable par un filtre). */
function vsg_denier_url() {
	return apply_filters( 'vsg_denier_url', 'https://denier.diocese94.fr/denier/~mon-don' );
}

/** Textes du jour sur le site de l'AELF (lectures de la messe, ou « lectures » pour la liturgie des heures). */
function vsg_aelf_url( $office = 'messe' ) {
	return 'https://www.aelf.org/' . wp_date( 'Y-m-d' ) . '/romain/' . ( 'heures' === $office ? 'lectures' : 'messe' );
}

function vsg_page_url( $slug ) {
	$p = get_page_by_path( $slug );
	return $p ? get_permalink( $p ) : home_url( '/' );
}

/* ---------- Couleurs ---------- */

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

/* ---------- Horaires des messes ---------- */

/** Lignes d'horaires d'un lieu, par moment de la semaine. */
function vsg_hours( $page_id ) {
	$out = array();
	foreach ( array( 'semaine' => 'En semaine', 'samedi' => 'Samedi', 'dimanche' => 'Dimanche', 'confessions' => 'Confessions et adoration' ) as $k => $label ) {
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", vsg_meta( $page_id, 'messes_' . $k ) ) ) ) );
		if ( $lines ) {
			$out[ $k ] = array( 'label' => $label, 'lines' => $lines );
		}
	}
	return $out;
}

/** Bloc d'horaires d'un lieu (titre facultatif). */
function vsg_hours_block( $page_id, $title = '', $compact = false ) {
	$hours = vsg_hours( $page_id );
	ob_start();
	echo '<div class="hours' . ( $compact ? ' hours--compact' : '' ) . '">';
	if ( $title ) {
		echo '<h3 class="hours__title">' . esc_html( $title ) . '</h3>';
	}
	if ( ! $hours ) {
		echo '<p class="empty">Horaires à compléter.</p>';
	} else {
		echo '<dl class="hours__list">';
		foreach ( $hours as $h ) {
			echo '<div class="hours__row"><dt>' . esc_html( $h['label'] ) . '</dt><dd>';
			foreach ( $h['lines'] as $l ) {
				echo '<span>' . esc_html( $l ) . '</span>';
			}
			echo '</dd></div>';
		}
		echo '</dl>';
	}
	echo '</div>';
	return ob_get_clean();
}

/** Page commune des horaires (modèle « Horaires »). */
function vsg_hours_page() {
	$pages = get_pages( array( 'meta_key' => '_wp_page_template', 'meta_value' => VSG_TPL_HORAIRES, 'hierarchical' => 0 ) );
	return $pages ? $pages[0] : null;
}

/**
 * Les lieux qui ont une page d'horaires : slug => array( post, libellé, adresse, paroisse, page du lieu ).
 * Ordre : chaque église suivie de ses chapelles.
 */
function vsg_hours_places() {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$out = array();
	foreach ( vsg_parishes() as $p ) {
		$out[ $p->post_name ] = array( $p, 'Église ' . get_the_title( $p ), vsg_meta( $p->ID, 'eglise' ), $p, get_permalink( $p ) );
		foreach ( vsg_chapels( $p->ID ) as $c ) {
			$out[ $c->post_name ] = array( $c, get_the_title( $c ), vsg_meta( $c->ID, 'adresse' ), $p, get_permalink( $c ) );
		}
	}
	return $out;
}

/** Lieu demandé par ?lieu= sur la page des horaires (ou ''). */
function vsg_hours_current() {
	$slug = isset( $_GET['lieu'] ) ? sanitize_title( wp_unslash( $_GET['lieu'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	return ( $slug && isset( vsg_hours_places()[ $slug ] ) ) ? $slug : '';
}

/** Page des horaires, filtrée sur un lieu (église ou chapelle) quand $page_id est donné. */
function vsg_hours_url( $page_id = 0 ) {
	$page = vsg_hours_page();
	if ( ! $page ) {
		return '';
	}
	$url = get_permalink( $page );
	if ( $page_id ) {
		$p = get_post( $page_id );
		if ( $p && isset( vsg_hours_places()[ $p->post_name ] ) && (int) vsg_hours_places()[ $p->post_name ][0]->ID === (int) $p->ID ) {
			$url = add_query_arg( 'lieu', $p->post_name, $url );
		}
	}
	return $url;
}

add_filter(
	'document_title_parts',
	function ( $parts ) {
		$cur = vsg_hours_current();
		if ( $cur && is_page() && VSG_TPL_HORAIRES === get_page_template_slug( get_queried_object_id() ) ) {
			$parts['title'] = vsg_hours_places()[ $cur ][1] . ' – ' . $parts['title'];
		}
		return $parts;
	}
);

/** Tuile de menu. */
function vsg_tile( $title, $url, $text = '', $icon = '' ) {
	return '<a class="tile" href="' . esc_url( $url ) . '">' . ( $icon ? '<span class="tile__icon" aria-hidden="true">' . $icon . '</span>' : '' ) . '<span class="tile__title">' . esc_html( $title ) . '</span>' . ( $text ? '<span class="tile__text">' . esc_html( $text ) . '</span>' : '' ) . '</a>';
}

/** Description courte d'une rubrique de paroisse, d'après son modèle. */
function vsg_tile_text( $page ) {
	if ( $page->post_excerpt ) {
		return $page->post_excerpt;
	}
	$map = array(
		'templates/actualites.php'  => 'Rendez-vous et nouvelles de la paroisse',
		'templates/feuille.php'     => 'La feuille de la semaine en PDF',
		'templates/infos.php'       => 'Accueil, contact, plans d\'accès',
		'templates/sacrements.php'  => 'Baptême, mariage, funérailles…',
		'templates/galerie.php'     => 'Photos des événements',
		VSG_TPL_COMMUNAUTES         => 'Qui ils sont, quand les rejoindre',
		'templates/histoire.php'    => 'Les origines, les lieux de culte',
		'templates/chapelles.php'   => 'Saint-Louis et Saint-Joseph : horaires, accès, histoire',
	);
	$tpl = get_page_template_slug( $page );
	if ( isset( $map[ $tpl ] ) ) {
		return $map[ $tpl ];
	}
	if ( 'catechese-et-jeunes' === $page->post_name ) {
		return 'Éveil à la foi, caté, aumônerie';
	}
	return '';
}

/**
 * Albums (actualités avec une galerie d'images) et vidéos d'une paroisse.
 *
 * @return array{0:WP_Post[],1:WP_Post[]}
 */
function vsg_gallery_items( $parish ) {
	static $cache = array();
	$key = $parish ? $parish->ID : 0;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$q      = vsg_news_query(
		array(
			'terms' => $parish ? vsg_parish_term_ids( $parish->ID ) : array(),
			'limit' => 60,
		)
	);
	$albums = array();
	$videos = array();
	foreach ( $q->posts as $p ) {
		if ( has_block( 'core/gallery', $p ) ) {
			$albums[] = $p;
		}
		if ( has_block( 'core/embed', $p ) || has_block( 'core/video', $p ) ) {
			$videos[] = $p;
		}
	}
	$cache[ $key ] = array( $albums, $videos );
	return $cache[ $key ];
}

/** Rubrique à masquer dans les onglets et les tuiles : galerie sans album ni vidéo. */
function vsg_page_is_empty( $page ) {
	if ( 'templates/galerie.php' !== get_page_template_slug( $page ) ) {
		return false;
	}
	list( $albums, $videos ) = vsg_gallery_items( vsg_context_parish( $page ) );
	return ! $albums && ! $videos;
}

/** Bloc « Rencontrer un prêtre ». */
function vsg_priest_block( $parish_id = 0 ) {
	$tel = $parish_id ? vsg_meta( $parish_id, 'telephone' ) : '';
	ob_start();
	?>
	<div class="infocard infocard--soft">
		<h2>Rencontrer un prêtre</h2>
		<p>Pour un temps d'écoute, un accompagnement spirituel ou une confession en dehors des horaires, un prêtre peut vous recevoir sur rendez-vous.</p>
		<?php if ( $tel ) : ?><p>Par téléphone à l'accueil : <strong><?php echo esc_html( $tel ); ?></strong></p><?php endif; ?>
		<p><a class="btn" href="<?php echo esc_url( add_query_arg( 'objet', 'pretre', vsg_page_url( 'contact' ) ) . '#contact-form' ); ?>">Demander un rendez-vous</a></p>
	</div>
	<?php echo vsg_cure_contact_block(); // phpcs:ignore ?>
	<?php
	return ob_get_clean();
}

/* ---------- Filtres (liste sur grand écran, menu déroulant sur téléphone) ---------- */

/**
 * @param array  $items   slug => array( label, url ).
 * @param string $current slug courant.
 * @param string $label   Libellé du menu.
 */
function vsg_filters( $items, $current, $label = 'Filtrer par lieu' ) {
	$cur_label = isset( $items[ $current ] ) ? $items[ $current ][0] : '';
	echo '<nav class="filters" aria-label="' . esc_attr( $label ) . '">';
	foreach ( $items as $slug => $it ) {
		echo '<a href="' . esc_url( $it[1] ) . '"' . ( $slug === $current ? ' aria-current="true"' : '' ) . '>' . esc_html( $it[0] ) . '</a>';
	}
	echo '</nav>';
	echo '<details class="menu menu--filters"><summary><span class="menu__label">' . esc_html( $label ) . '</span><span class="menu__current">' . esc_html( $cur_label ) . '</span></summary><nav aria-label="' . esc_attr( $label ) . '">';
	foreach ( $items as $slug => $it ) {
		echo '<a href="' . esc_url( $it[1] ) . '"' . ( $slug === $current ? ' aria-current="true"' : '' ) . '>' . esc_html( $it[0] ) . '</a>';
	}
	echo '</nav></details>';
}

/* ---------- Requêtes ---------- */

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
	foreach ( array( 'Communautés et groupes de prière' => true, 'Vie paroissiale' => false ) as $label => $is_group ) {
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
	return isset( $types[ $slug ] ) ? array_merge( array( 'slug' => $slug ), array( 'label' => $types[ $slug ][0], 'color' => $types[ $slug ][1] ) ) : null;
}

/** Contact direct du curé (réglé sur la page d'accueil). */
function vsg_cure_contact_block() {
	$home = get_option( 'page_on_front' );
	$nom  = vsg_meta( $home, 'cure_nom' );
	$tel  = vsg_meta( $home, 'cure_tel' );
	$mail = vsg_meta( $home, 'cure_email' );
	if ( ! $tel && ! $mail ) {
		return '';
	}
	$h = '<div class="infocard infocard--soft curecontact"><h2>Joindre directement le curé</h2>';
	$h .= '<p>Pour ce type de demande, vous pouvez aussi contacter ' . ( $nom ? 'le ' . esc_html( preg_replace( '/,.*$/', '', $nom ) ) : 'le curé' ) . ' :</p><ul class="plainlist">';
	if ( $tel ) {
		$h .= '<li><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ) . '">' . esc_html( $tel ) . '</a></li>';
	}
	if ( $mail ) {
		$h .= '<li>' . vsg_mailto( $mail ) . '</li>';
	}
	return $h . '</ul></div>';
}

function vsg_events_query( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'terms'    => array(),
			'limit'    => 3,
			'from'     => vsg_today(),
			'children' => true,
			'exclude_terms' => array(),
			'kind'     => '', // '' = tout, 'parish' = activités paroissiales, 'groups' = groupes et communautés
			'type'     => '', // un type de rendez-vous précis
		)
	);
	$q = array(
		'post_type'      => 'evenement',
		'post_status'    => current_user_can( 'read_private_posts' ) ? array( 'publish', 'private' ) : 'publish',
		'posts_per_page' => $args['limit'],
		'meta_key'       => '_vsg_date',
		'orderby'        => array( 'meta_value' => 'ASC', 'title' => 'ASC' ),
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
			array( 'key' => '_vsg_type', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_vsg_type', 'value' => $groups, 'compare' => 'NOT IN' ),
		);
	} elseif ( 'groups' === $args['kind'] ) {
		$q['meta_query'][] = array( 'key' => '_vsg_type', 'value' => $groups, 'compare' => 'IN' );
	}
	if ( $args['type'] ) {
		$q['meta_query'][] = array( 'key' => '_vsg_type', 'value' => $args['type'] );
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

function vsg_news_query( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'terms'   => array(),
			'limit'   => 3,
			'paged'   => 1,
			'exclude' => array(),
			'children'=> true,
			'exclude_terms' => array(),
		)
	);
	$q = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => $args['limit'],
		'paged'          => $args['paged'],
		'post__not_in'   => $args['exclude'],
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

/** Lien du PDF d'une feuille : fichier joint, sinon premier lien .pdf du contenu. */
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

/* ---------- Affichage ---------- */

function vsg_placeholder( $label = 'Photo' ) {
	return '<div class="ph" aria-hidden="true"><span>' . esc_html( $label ) . '</span></div>';
}

/** Vrai si l'image mise en avant est une bannière très large (plus de 2 fois plus large que haute). */
function vsg_thumb_is_wide( $post ) {
	$id = get_post_thumbnail_id( $post );
	if ( ! $id ) {
		return false;
	}
	$src = wp_get_attachment_image_src( $id, 'full' );
	return $src && $src[2] > 0 && $src[1] / $src[2] > 2;
}

function vsg_thumb( $post, $size = 'vsg-card', $label = 'Photo' ) {
	if ( has_post_thumbnail( $post ) ) {
		return get_the_post_thumbnail( $post, $size, array( 'alt' => '' ) );
	}
	return vsg_placeholder( $label );
}

function vsg_date_badge( $ymd ) {
	$p = vsg_date_parts( $ymd );
	if ( ! $p ) {
		return '';
	}
	return '<span class="datebadge" aria-hidden="true"><span class="datebadge__d">' . esc_html( $p['day'] ) . '</span><span class="datebadge__m">' . esc_html( $p['month'] ) . '</span></span>';
}

/** Rendez-vous : « card » (accueil), « row » (listes). */
/** Communautés et groupes de prière (programme à part) : slug => libellé. */
function vsg_group_types() {
	$out = array();
	foreach ( vsg_event_types() as $slug => $t ) {
		if ( ! empty( $t[2] ) ) {
			$out[ $slug ] = $t[0];
		}
	}
	return $out;
}

/**
 * Page d'un groupe pour un type d'agenda. Un même groupe peut avoir une page par paroisse
 * (la chorale) : on prend celle de la paroisse indiquée, sinon la première.
 */
function vsg_group_page( $type, $parish = null ) {
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => '_vsg_type',
			'meta_value'     => $type,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		)
	);
	if ( ! $pages ) {
		return null;
	}
	if ( $parish ) {
		foreach ( $pages as $p ) {
			$ctx = vsg_context_parish( $p );
			if ( $ctx && (int) $ctx->ID === (int) $parish->ID ) {
				return $p;
			}
		}
	}
	return $pages[0];
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
	return array( 'label' => $type['label'], 'color' => $type['color'], 'url' => $page ? get_permalink( $page ) : '', 'page' => $page );
}

/** Regroupe des rendez-vous par mois puis par jour : ym => array( label, days => array( ymd => posts ) ). */
function vsg_events_by_month( $posts ) {
	$months = array();
	foreach ( $posts as $ev ) {
		$date  = vsg_meta( $ev->ID, 'date' );
		$parts = vsg_date_parts( $date );
		$key   = $parts ? $parts['ym'] : '0000-00';
		if ( ! isset( $months[ $key ] ) ) {
			$months[ $key ] = array( 'label' => $parts ? ucfirst( $parts['month_full'] ) . ' ' . $parts['year'] : '', 'days' => array() );
		}
		$months[ $key ]['days'][ $date ][] = $ev;
	}
	return $months;
}

/** Liste mois par mois (un mois ouvert, les autres repliés). */
function vsg_months_list( $months, $show_place = true ) {
	$i = 0;
	foreach ( $months as $ym => $m ) {
		$i++;
		echo '<details class="month"' . ( 1 === $i ? ' open' : '' ) . ' id="m-' . esc_attr( $ym ) . '">';
		echo '<summary><h2 class="month__title">' . esc_html( $m['label'] ) . '</h2><span class="month__count">' . esc_html( array_sum( array_map( 'count', $m['days'] ) ) ) . ' rendez-vous</span></summary><div class="month__body">';
		foreach ( $m['days'] as $ymd => $evs ) {
			echo '<div class="day"><h3 class="day__title">' . esc_html( ucfirst( vsg_date_long( $ymd ) ) ) . '</h3><div class="list list--tight">';
			foreach ( $evs as $ev ) {
				echo vsg_event( $ev, 'row', $show_place ); // phpcs:ignore
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
		array_filter( $posts, function ( $p ) { return has_post_thumbnail( $p ); } ),
		array_filter( $posts, function ( $p ) { return ! has_post_thumbnail( $p ); } )
	);
	$out  = array();
	$seen = array();
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
	$place = vsg_place_label( $post );
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

function vsg_event( $post, $variant = 'card', $show_place = true ) {
	$date  = vsg_meta( $post->ID, 'date' );
	$heure = vsg_time_label( vsg_meta( $post->ID, 'heure' ) );
	$lieu  = vsg_meta( $post->ID, 'lieu' );
	$group = vsg_event_group( $post );
	$type  = vsg_event_type( $post );
	if ( $group ) {
		// Activité d'un groupe : l'étiquette nomme le groupe, le lieu passe dans la ligne de détails.
		$chip  = $group['label'];
		$style = ' style="--accent:' . esc_attr( $group['color'] ) . '"';
		$meta  = array_merge( array( $heure ), vsg_event_where( $post ) );
	} else {
		$chip  = $show_place ? vsg_place_label( $post ) : '';
		$style = vsg_accent_attr( $post );
		$meta  = array( $heure, $lieu, ( $type && 'row' === $variant ) ? $type['label'] : '' );
	}
	$meta  = array_filter( $meta );
	$when  = trim( vsg_date_long( $date ) . ( $heure ? ', ' . $heure : '' ) );
	$img   = ( 'card' === $variant && has_post_thumbnail( $post ) ) ? get_the_post_thumbnail( $post, 'medium_large', array( 'class' => 'event__img', 'alt' => vsg_poster_alt( $post ), 'loading' => 'eager' ) ) : '';
	ob_start();
	?>
	<a class="event event--<?php echo esc_attr( $variant ); ?><?php echo $img ? ' event--img' : ''; ?>" href="<?php echo esc_url( get_permalink( $post ) ); ?>"<?php echo $style; // phpcs:ignore ?>>
		<?php echo $img; // phpcs:ignore ?>
		<?php echo vsg_date_badge( $date ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<span class="event__body">
			<?php if ( $chip ) : ?><span class="chip<?php echo $group ? ' chip--group' : ''; ?>"><?php echo esc_html( $chip ); ?></span><?php endif; ?>
			<?php if ( 'private' === $post->post_status ) : ?><span class="chip chip--private">Interne</span><?php endif; ?>
			<span class="event__title"><?php if ( $type && ! $group ) : ?><span class="dot" style="background:<?php echo esc_attr( $type['color'] ); ?>" title="<?php echo esc_attr( $type['label'] ); ?>"></span><?php endif; ?><?php echo esc_html( get_the_title( $post ) ); ?></span>
			<span class="sr-only"><?php echo esc_html( $when ); ?></span>
			<?php if ( $meta ) : ?><span class="event__meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></span><?php endif; ?>
		</span>
	</a>
	<?php
	return ob_get_clean();
}

function vsg_news_card( $post, $show_place = true, $show_date = false ) {
	ob_start();
	?>
	<?php $wide = vsg_thumb_is_wide( $post ); ?>
	<a class="card" href="<?php echo esc_url( get_permalink( $post ) ); ?>"<?php echo vsg_accent_attr( $post ); // phpcs:ignore ?>>
		<span class="card__media<?php echo $wide ? ' card__media--wide' : ''; ?>"><?php echo vsg_thumb( $post, $wide ? 'large' : 'vsg-card' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<span class="card__body">
			<?php if ( $show_place && vsg_place_label( $post ) ) : ?><span class="chip"><?php echo esc_html( vsg_place_label( $post ) ); ?></span><?php endif; ?>
			<?php if ( $show_date ) : ?><span class="card__date"><?php echo esc_html( vsg_post_date( $post ) ); ?></span><?php endif; ?>
			<span class="card__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
			<span class="card__text"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $post ) ) ); ?></span>
		</span>
	</a>
	<?php
	return ob_get_clean();
}

/** Carte d'une communauté : nom, paroisse, résumé, premier rendez-vous régulier. */
function vsg_community_card( $page, $show_parish = true ) {
	$parish = vsg_context_parish( $page );
	$prog   = array_values( array_filter( array_map( 'trim', explode( "\n", vsg_meta( $page->ID, 'programme' ) ) ) ) );
	ob_start();
	?>
	<a class="card" href="<?php echo esc_url( get_permalink( $page ) ); ?>"<?php echo vsg_accent_attr( $page ); // phpcs:ignore ?>>
		<span class="card__media"><?php echo vsg_thumb( $page, 'vsg-card', 'Communauté' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<span class="card__body">
			<?php if ( $show_parish && $parish ) : ?><span class="chip"><?php echo esc_html( get_the_title( $parish ) ); ?></span><?php endif; ?>
			<span class="card__title"><?php echo esc_html( get_the_title( $page ) ); ?></span>
			<span class="card__text"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $page ) ) ); ?></span>
			<?php if ( $prog ) : ?><span class="card__date"><?php echo esc_html( $prog[0] ); ?></span><?php endif; ?>
		</span>
	</a>
	<?php
	return ob_get_clean();
}

function vsg_section_head( $title, $link_url = '', $link_label = '', $tag = 'h2' ) {
	echo '<div class="sectionhead"><' . $tag . '>' . esc_html( $title ) . '</' . $tag . '>'; // phpcs:ignore
	if ( $link_url ) {
		echo '<a href="' . esc_url( $link_url ) . '">' . esc_html( $link_label ) . '</a>';
	}
	echo '</div>';
}

function vsg_empty( $text ) {
	echo '<p class="empty">' . esc_html( $text ) . '</p>';
}

/** Bandeau d'une paroisse avec ses onglets. */
function vsg_parish_band( $parish, $current = null ) {
	get_template_part( 'template-parts/parish-band', null, array( 'parish' => $parish, 'current' => $current ) );
}

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

/** Bouton « Horaires » vers la page des horaires du lieu. */
function vsg_messes_button( $page_id, $class = 'btn' ) {
	$url = vsg_hours_url( $page_id );
	if ( ! $url ) {
		$url = vsg_page_url( 'horaires-des-messes' );
	}
	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">Horaires</a>';
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

function vsg_nl2br( $text ) {
	return nl2br( esc_html( $text ) );
}

/**
 * Texte alternatif d'une affiche : celui saisi dans la Médiathèque,
 * sinon « Affiche : » suivi du titre du rendez-vous.
 */
function vsg_poster_alt( $post ) {
	$alt = trim( (string) get_post_meta( get_post_thumbnail_id( $post ), '_wp_attachment_image_alt', true ) );
	return '' !== $alt ? $alt : 'Affiche : ' . wp_strip_all_tags( get_the_title( $post ) );
}
