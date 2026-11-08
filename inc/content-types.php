<?php
/**
 * Types de contenu : rendez-vous de l'agenda et feuilles paroissiales.
 * Ils utilisent les catégories (une par paroisse et par chapelle).
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		register_post_type(
			'evenement',
			array(
				'labels'        => array(
					'name'               => 'Agenda',
					'singular_name'      => 'Rendez-vous',
					'menu_name'          => 'Agenda',
					'add_new'            => 'Ajouter un rendez-vous',
					'add_new_item'       => 'Ajouter un rendez-vous',
					'edit_item'          => 'Modifier le rendez-vous',
					'new_item'           => 'Nouveau rendez-vous',
					'view_item'          => 'Voir le rendez-vous',
					'search_items'       => 'Rechercher un rendez-vous',
					'not_found'          => 'Aucun rendez-vous',
					'not_found_in_trash' => 'Aucun rendez-vous dans la corbeille',
					'all_items'          => 'Tous les rendez-vous',
				),
				'public'        => true,
				'show_in_rest'  => true,
				'menu_icon'     => 'dashicons-calendar-alt',
				'menu_position' => 5,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'taxonomies'    => array( 'category' ),
				'has_archive'   => false,
				'rewrite'       => array( 'slug' => 'rendez-vous' ),
			)
		);

		register_post_type(
			'feuille',
			array(
				'labels'        => array(
					'name'               => 'Feuilles paroissiales',
					'singular_name'      => 'Feuille paroissiale',
					'menu_name'          => 'Feuilles paroissiales',
					'add_new'            => 'Déposer une feuille',
					'add_new_item'       => 'Déposer une feuille paroissiale',
					'edit_item'          => 'Modifier la feuille',
					'new_item'           => 'Nouvelle feuille',
					'view_item'          => 'Voir la feuille',
					'not_found'          => 'Aucune feuille',
					'not_found_in_trash' => 'Aucune feuille dans la corbeille',
					'all_items'          => 'Toutes les feuilles',
				),
				'public'        => true,
				'show_in_rest'  => true,
				'menu_icon'     => 'dashicons-media-document',
				'menu_position' => 6,
				'supports'      => array( 'title', 'editor' ),
				'taxonomies'    => array( 'category' ),
				'has_archive'   => false,
				'rewrite'       => array( 'slug' => 'feuille' ),
				/* L'éditeur s'ouvre avec un bloc « Fichier » prêt à recevoir le PDF. */
				'template'      => array(
					array(
						'core/file',
						array(
							'displayPreview'     => false,
							'downloadButtonText' => 'Télécharger',
						),
					),
				),
			)
		);
	}
);

/* Libellés plus parlants pour la secrétaire. */
add_action(
	'init',
	function () {
		global $wp_post_types, $wp_taxonomies;
		if ( isset( $wp_post_types['post'] ) ) {
			$l                 = $wp_post_types['post']->labels;
			$l->name           = 'Actualités';
			$l->menu_name      = 'Actualités';
			$l->singular_name  = 'Actualité';
			$l->add_new        = 'Publier une actualité';
			$l->add_new_item   = 'Publier une actualité';
			$l->all_items      = 'Toutes les actualités';
			$l->edit_item      = 'Modifier l\'actualité';
			$l->name_admin_bar = 'Actualité';
		}
		if ( isset( $wp_taxonomies['category'] ) ) {
			$l                = $wp_taxonomies['category']->labels;
			$l->name          = 'Paroisses et lieux';
			$l->singular_name = 'Paroisse ou lieu';
			$l->menu_name     = 'Paroisses et lieux';
		}
	},
	20
);

/* Colonne « Date du rendez-vous » dans la liste de l'agenda, triée par défaut. */
add_filter(
	'manage_evenement_posts_columns',
	function ( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			if ( 'date' === $k ) {
				$new['vsg_date'] = 'Date du rendez-vous';
				continue;
			}
			$new[ $k ] = $v;
		}
		return $new;
	}
);
add_action(
	'manage_evenement_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'vsg_date' === $col ) {
			$d = vsg_meta( $post_id, 'date' );
			$h = vsg_time_label( vsg_meta( $post_id, 'heure' ) );
			if ( $d && vsg_today() === $d ) {
				echo '<strong class="vsg-when vsg-when--today">Aujourd\'hui</strong><br>';
			} elseif ( $d && gmdate( 'Y-m-d', strtotime( vsg_today() . ' +1 day' ) ) === $d ) {
				echo '<strong class="vsg-when">Demain</strong><br>';
			} elseif ( $d && $d < vsg_today() ) {
				echo '<span class="vsg-when vsg-when--past">Passé</span><br>';
			}
			echo esc_html( $d ? ucfirst( vsg_date_long( $d ) ) . ' ' . vsg_date_parts( $d )['year'] . ( $h ? ', ' . $h : '' ) : '—' );
		}
	},
	10,
	2
);
add_filter(
	'manage_edit-evenement_sortable_columns',
	function ( $cols ) {
		$cols['vsg_date'] = 'vsg_date';
		return $cols;
	}
);
/* Le même jour, les rendez-vous suivent l'heure (ceux sans heure en premier). */
add_filter(
	'posts_orderby',
	function ( $orderby, $q ) {
		$dir = $q->get( 'vsg_heure_order' );
		if ( ! $dir ) {
			return $orderby;
		}
		global $wpdb;
		return $orderby . ", (SELECT h.meta_value FROM {$wpdb->postmeta} h WHERE h.post_id = {$wpdb->posts}.ID AND h.meta_key = '_vsg_heure' LIMIT 1) " . ( 'ASC' === $dir ? 'ASC' : 'DESC' );
	},
	10,
	2
);

/*
 * Liste de l'agenda dans l'administration : vues « À venir » (par défaut), « Aujourd'hui »,
 * « Passés », « Tous » ; filtres par mois du rendez-vous et par type ou groupe.
 */

/** Vue demandée dans la liste de l'agenda : a-venir, aujourdhui, passes ou tous. */
function vsg_agenda_admin_view() {
	// phpcs:disable WordPress.Security.NonceVerification -- simples filtres de la liste des rendez-vous.
	$v = isset( $_GET['vsg_vue'] ) ? sanitize_key( wp_unslash( $_GET['vsg_vue'] ) ) : '';
	if ( in_array( $v, array( 'a-venir', 'aujourdhui', 'passes', 'tous' ), true ) ) {
		return $v;
	}
	/* Sans vue choisie : « À venir », sauf si l'on cherche, filtre par mois ou regarde un statut précis. */
	foreach ( array( 's', 'vsg_mois', 'post_status', 'author' ) as $k ) {
		if ( ! empty( $_GET[ $k ] ) && 'all' !== $_GET[ $k ] ) {
			return 'tous';
		}
	}
	// phpcs:enable
	return 'a-venir';
}

/** Nombre de rendez-vous (non supprimés) d'une vue, pour les compteurs. */
function vsg_agenda_admin_count( $view ) {
	$today = vsg_today();
	$args  = array(
		'post_type'      => 'evenement',
		'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	);
	$cmp   = array(
		'a-venir'    => '>=',
		'aujourdhui' => '=',
		'passes'     => '<',
	);
	if ( isset( $cmp[ $view ] ) ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_vsg_date',
				'value'   => $today,
				'compare' => $cmp[ $view ],
				'type'    => 'DATE',
			),
		);
	}
	return count( get_posts( $args ) );
}

add_filter(
	'views_edit-evenement',
	function ( $views ) {
		$cur   = vsg_agenda_admin_view();
		$base  = admin_url( 'edit.php?post_type=evenement' );
		$items = array(
			'a-venir'    => 'À venir',
			'aujourdhui' => 'Aujourd\'hui',
			'passes'     => 'Passés',
			'tous'       => 'Tous',
		);
		$new   = array();
		foreach ( $items as $key => $label ) {
			$current     = $cur === $key && empty( $_GET['post_status'] ); // phpcs:ignore WordPress.Security.NonceVerification -- simple vue de la liste.
			$new[ $key ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'vsg_vue', $key, $base ) ),
				$current ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				vsg_agenda_admin_count( $key )
			);
		}
		/* On garde les vues de WordPress utiles (brouillons, corbeille…), sans « Tous » ni « Publiés ». */
		foreach ( $views as $key => $html ) {
			if ( ! in_array( $key, array( 'all', 'publish', 'mine' ), true ) ) {
				$new[ $key ] = $html;
			}
		}
		return $new;
	}
);

/* Le filtre « dates de création » de WordPress est remplacé par le mois du rendez-vous. */
add_filter(
	'disable_months_dropdown',
	function ( $disable, $post_type ) {
		return 'evenement' === $post_type ? true : $disable;
	},
	10,
	2
);

add_action(
	'restrict_manage_posts',
	function ( $post_type ) {
		if ( 'evenement' !== $post_type ) {
			return;
		}
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification -- simples filtres de la liste des rendez-vous.
		$vue  = vsg_agenda_admin_view();
		$mois = isset( $_GET['vsg_mois'] ) ? sanitize_text_field( wp_unslash( $_GET['vsg_mois'] ) ) : '';
		$type = isset( $_GET['vsg_type'] ) ? sanitize_key( wp_unslash( $_GET['vsg_type'] ) ) : '';
		// phpcs:enable
		echo '<input type="hidden" name="vsg_vue" value="' . esc_attr( $vue ) . '">';

		$rows = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT SUBSTR(m.meta_value, 1, 7) FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND p.post_type = %s AND p.post_status <> 'trash' AND m.meta_value <> '' ORDER BY 1 DESC",
				'_vsg_date',
				'evenement'
			)
		);
		echo '<label class="screen-reader-text" for="vsg_mois">Filtrer par mois du rendez-vous</label><select name="vsg_mois" id="vsg_mois">';
		echo '<option value="">Tous les mois</option>';
		foreach ( $rows as $ym ) {
			if ( ! preg_match( '/^\d{4}-\d{2}$/', $ym ) ) {
				continue;
			}
			$label = ucfirst( wp_date( 'F Y', strtotime( $ym . '-01 12:00' ) ) );
			echo '<option value="' . esc_attr( $ym ) . '"' . selected( $mois, $ym, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		echo '<label class="screen-reader-text" for="vsg_type">Filtrer par type ou groupe</label><select name="vsg_type" id="vsg_type">';
		echo '<option value="">Tous les types et groupes</option>';
		foreach ( vsg_event_types() as $slug => $t ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $type, $slug, false ) . '>' . esc_html( $t[0] ) . '</option>';
		}
		echo '</select>';
	},
	5
);

add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || 'evenement' !== $q->get( 'post_type' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification -- simples filtres de la liste des rendez-vous.
		$vue   = vsg_agenda_admin_view();
		$today = vsg_today();
		$meta  = array();
		$cmp   = array(
			'a-venir'    => '>=',
			'aujourdhui' => '=',
			'passes'     => '<',
		);
		if ( isset( $cmp[ $vue ] ) && empty( $_GET['post_status'] ) ) {
			$meta[] = array(
				'key'     => '_vsg_date',
				'value'   => $today,
				'compare' => $cmp[ $vue ],
				'type'    => 'DATE',
			);
		}
		$mois = isset( $_GET['vsg_mois'] ) ? sanitize_text_field( wp_unslash( $_GET['vsg_mois'] ) ) : '';
		if ( preg_match( '/^\d{4}-\d{2}$/', $mois ) ) {
			$meta[] = array(
				'key'     => '_vsg_date',
				'value'   => $mois . '-', // LIKE ajoute lui-même les jokers
				'compare' => 'LIKE',
			);
		}
		$type = isset( $_GET['vsg_type'] ) ? sanitize_key( wp_unslash( $_GET['vsg_type'] ) ) : '';
		if ( $type && isset( vsg_event_types()[ $type ] ) ) {
			$meta[] = array(
				'key'   => '_vsg_type',
				'value' => $type,
			);
		}
		if ( $meta ) {
			$meta['relation'] = 'AND';
			$q->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		/* Tri par date du rendez-vous : croissant pour ce qui vient, décroissant pour le passé. */
		$orderby = $q->get( 'orderby' );
		if ( ! $orderby || 'vsg_date' === $orderby ) {
			$q->set( 'meta_key', '_vsg_date' );
			$order = empty( $_GET['order'] ) ? ( in_array( $vue, array( 'passes', 'tous' ), true ) ? 'DESC' : 'ASC' ) : ( 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC' );
			$q->set( 'orderby', 'meta_value' );
			$q->set( 'order', $order );
			$q->set( 'vsg_heure_order', $order );
		}
		// phpcs:enable
	}
);
