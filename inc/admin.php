<?php
/**
 * Administration simplifiée : un tableau de bord avec les actions courantes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
			remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
			remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
			remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
		}
		wp_add_dashboard_widget( 'vsg_actions', 'Que voulez-vous faire ?', 'vsg_dashboard_actions', null, null, 'normal', 'high' );
	}
);

function vsg_dashboard_actions() {
	$feuilles = vsg_feuilles_query( array_filter( array_map( function ( $p ) {
		$t = vsg_page_term( $p->ID );
		return $t ? $t->term_id : 0;
	}, vsg_parishes() ) ), 1 );
	$last = $feuilles->have_posts() ? $feuilles->posts[0] : null;
	$actions = array(
		array( 'Publier une actualité', admin_url( 'post-new.php' ), 'Annonce, compte rendu, photos.' ),
		array( 'Ajouter un rendez-vous', admin_url( 'post-new.php?post_type=evenement' ), 'Il apparaîtra dans l\'agenda à la bonne date.' ),
		array( 'Déposer la feuille paroissiale', admin_url( 'post-new.php?post_type=feuille' ), 'Un PDF par paroisse et par semaine.' ),
		array( 'Modifier une page', admin_url( 'edit.php?post_type=page' ), 'Sacrements, catéchèse, coordonnées…' ),
	);
	echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">';
	foreach ( $actions as $a ) {
		echo '<a href="' . esc_url( $a[1] ) . '" style="display:block;padding:16px;border:1px solid #c3c4c7;border-radius:8px;text-decoration:none;background:#fff">';
		echo '<strong style="display:block;font-size:15px;color:#1F3A5F;margin-bottom:4px">' . esc_html( $a[0] ) . '</strong>';
		echo '<span style="color:#50575e">' . esc_html( $a[2] ) . '</span></a>';
	}
	echo '</div>';
	if ( $last ) {
		echo '<p style="margin:14px 0 0">Dernière feuille déposée : <strong>' . esc_html( get_the_title( $last ) ) . '</strong> (' . esc_html( vsg_post_date( $last ) ) . ').</p>';
	}
	echo '<p style="margin:10px 0 0"><a href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">Voir le site</a></p>';
}

/* ---------- Listes de l'admin : colonne et filtre « Paroisse » ---------- */

/** Types concernés : actualités, agenda, feuilles paroissiales. */
function vsg_admin_list_types() {
	return array( 'post', 'evenement', 'feuille' );
}

/** Colonne « Paroisse » à la place de la colonne des catégories. */
function vsg_admin_parish_columns( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		if ( 'categories' === $k ) {
			continue;
		}
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['vsg_paroisse'] = 'Paroisse';
		}
	}
	return $new;
}

function vsg_admin_parish_cell( $col, $post_id ) {
	if ( 'vsg_paroisse' !== $col ) {
		return;
	}
	$parish = vsg_context_parish( $post_id );
	$place  = vsg_place_label( $post_id );
	if ( $parish ) {
		echo '<strong style="color:' . esc_attr( vsg_parish_color( $parish ) ) . '">' . esc_html( get_the_title( $parish ) ) . '</strong>';
		if ( $place && $place !== get_the_title( $parish ) ) {
			echo '<br><span style="color:#646970">' . esc_html( $place ) . '</span>';
		}
	} elseif ( $place ) {
		echo '<span style="color:#646970">' . esc_html( $place ) . '</span>';
	} else {
		echo '<em style="color:#b32d2e">Aucune paroisse choisie</em>';
	}
}

add_action(
	'admin_init',
	function () {
		foreach ( vsg_admin_list_types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", 'vsg_admin_parish_columns', 20 );
			add_action( "manage_{$type}_posts_custom_column", 'vsg_admin_parish_cell', 10, 2 );
		}
	}
);

/* Filtre « Toutes les paroisses » au-dessus de la liste. */
add_action(
	'restrict_manage_posts',
	function ( $post_type ) {
		if ( ! in_array( $post_type, vsg_admin_list_types(), true ) ) {
			return;
		}
		$cur = isset( $_GET['vsg_paroisse'] ) ? sanitize_key( wp_unslash( $_GET['vsg_paroisse'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<label class="screen-reader-text" for="vsg_paroisse">Filtrer par paroisse</label><select name="vsg_paroisse" id="vsg_paroisse">';
		echo '<option value="">Toutes les paroisses</option>';
		foreach ( vsg_parishes() as $p ) {
			echo '<option value="' . esc_attr( $p->ID ) . '"' . selected( $cur, (string) $p->ID, false ) . '>' . esc_html( get_the_title( $p ) ) . '</option>';
		}
		if ( vsg_common_term() ) {
			echo '<option value="commun"' . selected( $cur, 'commun', false ) . '>Les deux paroisses</option>';
		}
		echo '</select>';
	}
);

add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || empty( $_GET['vsg_paroisse'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! in_array( $q->get( 'post_type' ) ? $q->get( 'post_type' ) : 'post', vsg_admin_list_types(), true ) ) {
			return;
		}
		$val  = sanitize_key( wp_unslash( $_GET['vsg_paroisse'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$term = 'commun' === $val ? vsg_common_term() : vsg_page_term( (int) $val );
		if ( ! $term ) {
			return;
		}
		$q->set(
			'tax_query',
			array(
				array(
					'taxonomy'         => 'category',
					'terms'            => array( (int) $term->term_id ),
					'include_children' => true,
				),
			)
		);
	}
);

/* ---------- Feuilles paroissiales : titre complet ---------- */

/** Date du dimanche qui ouvre la semaine en cours (aujourd'hui si c'est dimanche, sinon le prochain). */
function vsg_feuille_week_label() {
	$ts = time(); // wp_date() applique déjà le fuseau du site
	$w  = (int) wp_date( 'w', $ts );
	$ts = $w ? strtotime( '+' . ( 7 - $w ) . ' days', $ts ) : $ts;
	$p  = vsg_date_parts( wp_date( 'Y-m-d', $ts ) );
	return 'Semaine du ' . ( '1' === $p['day'] ? '1er' : $p['day'] ) . ' ' . $p['month_full'] . ' ' . $p['year'];
}

/* Titre proposé quand on dépose une nouvelle feuille. */
add_filter(
	'default_title',
	function ( $title, $post ) {
		return ( $post && 'feuille' === $post->post_type && '' === $title ) ? vsg_feuille_week_label() : $title;
	},
	10,
	2
);

/* À l'enregistrement, le nom de la paroisse est ajouté devant le titre s'il n'y est pas. */
add_action(
	'wp_after_insert_post',
	function ( $post_id, $post ) {
		if ( 'feuille' !== $post->post_type || 'auto-draft' === $post->post_status || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$parish = vsg_context_parish( $post );
		if ( ! $parish ) {
			return;
		}
		$name  = get_the_title( $parish );
		$title = trim( $post->post_title );
		if ( '' === $title ) {
			$title = vsg_feuille_week_label();
		}
		if ( 0 === strpos( $title, $name ) ) {
			return;
		}
		/* Retire un éventuel nom d'une autre paroisse (changement de catégorie). */
		foreach ( vsg_parishes() as $p ) {
			$prefix = get_the_title( $p ) . ' – ';
			if ( 0 === strpos( $title, $prefix ) ) {
				$title = substr( $title, strlen( $prefix ) );
			}
		}
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $name . ' – ' . $title,
			)
		);
	},
	10,
	2
);

/* Le filtre « Paroisse » remplace la liste des catégories de WordPress. */
add_filter(
	'disable_categories_dropdown',
	function ( $disable, $post_type ) {
		return in_array( $post_type, vsg_admin_list_types(), true ) ? true : $disable;
	},
	10,
	2
);

/* Garde-fou : une feuille, un rendez-vous ou une actualité enregistré sans paroisse n'apparaît nulle part. */
add_action(
	'enqueue_block_editor_assets',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, vsg_admin_list_types(), true ) ) {
			return;
		}
		$js = <<<'JS'
( function () {
	var d = wp.data, was = false, ID = 'vsg-no-parish';
	d.subscribe( function () {
		var ed = d.select( 'core/editor' );
		if ( ! ed ) { return; }
		var saving = ed.isSavingPost() && ! ed.isAutosavingPost();
		var done = was && ! saving;
		was = saving;
		if ( ! done ) { return; }
		setTimeout( function () {
			var cats = ed.getEditedPostAttribute( 'categories' ) || [];
			var real = cats.filter( function ( c ) { return c !== VSG_DEFAULT_CAT; } );
			var n = d.dispatch( 'core/notices' );
			if ( ! real.length ) {
				n.createWarningNotice( 'Aucune paroisse n\'est cochée : ce contenu n\'apparaît nulle part sur le site. Cochez une paroisse (ou « Les deux paroisses ») dans « Paroisses et lieux », colonne de droite, puis enregistrez.', { id: ID, isDismissible: true } );
			} else {
				n.removeNotice( ID );
			}
		}, 0 );
	} );
} )();
JS;
		wp_register_script( 'vsg-garde-fou', '', array( 'wp-data', 'wp-editor', 'wp-notices' ), VSG_VERSION, true );
		wp_enqueue_script( 'vsg-garde-fou' );
		wp_add_inline_script( 'vsg-garde-fou', 'var VSG_DEFAULT_CAT = ' . (int) get_option( 'default_category' ) . ';' . $js );
	}
);

/*
 * Pages attendues par le thème (menus, liens, formulaires). Si l'une a été supprimée ou son adresse
 * changée, les liens vers elle mènent à l'accueil : on le signale aux administrateurs.
 */
add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		if ( ! current_user_can( 'manage_options' ) || ! $screen || ! in_array( $screen->id, array( 'dashboard', 'edit-page', 'page' ), true ) ) {
			return;
		}
		$missing = array();
		foreach ( array( 'agenda', 'communautes', 'contact', 'demande-de-messe', 'horaires-des-messes', 'mentions-legales', 'prier', 'soutenir', 'vivre-en-eglise' ) as $slug ) {
			$p = get_page_by_path( $slug );
			if ( ! $p || 'publish' !== $p->post_status ) {
				$missing[] = $slug;
			}
		}
		if ( $missing ) {
			echo '<div class="notice notice-warning"><p><strong>Pages introuvables :</strong> ' . esc_html( implode( ', ', $missing ) ) . '. Les liens du site vers ces pages mènent à l\'accueil. Vérifiez qu\'elles sont publiées et que leur adresse (slug) n\'a pas changé.</p></div>';
		}
	}
);
