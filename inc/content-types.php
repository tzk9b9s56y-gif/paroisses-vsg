<?php
/**
 * Types de contenu : rendez-vous de l'agenda et feuilles paroissiales.
 * Ils utilisent les catégories (une par paroisse et par chapelle).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		register_post_type(
			'evenement',
			array(
				'labels'       => array(
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
				'public'       => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-calendar-alt',
				'menu_position'=> 5,
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'taxonomies'   => array( 'category' ),
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'rendez-vous' ),
			)
		);

		register_post_type(
			'feuille',
			array(
				'labels'       => array(
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
				'public'       => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-media-document',
				'menu_position'=> 6,
				'supports'     => array( 'title', 'editor' ),
				'taxonomies'   => array( 'category' ),
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'feuille' ),
				/* L'éditeur s'ouvre avec un bloc « Fichier » prêt à recevoir le PDF. */
				'template'     => array( array( 'core/file', array( 'displayPreview' => false, 'downloadButtonText' => 'Télécharger' ) ) ),
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
			$l                     = $wp_post_types['post']->labels;
			$l->name               = 'Actualités';
			$l->menu_name          = 'Actualités';
			$l->singular_name      = 'Actualité';
			$l->add_new            = 'Publier une actualité';
			$l->add_new_item       = 'Publier une actualité';
			$l->all_items          = 'Toutes les actualités';
			$l->edit_item          = 'Modifier l\'actualité';
			$l->name_admin_bar     = 'Actualité';
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
			echo esc_html( $d ? ucfirst( vsg_date_long( $d ) ) . ' ' . vsg_date_parts( $d )['year'] . ( $h ? ', ' . $h : '' ) : '—' );
			if ( $d && $d < vsg_today() ) {
				echo ' <em>(passé)</em>';
			}
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
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || 'evenement' !== $q->get( 'post_type' ) ) {
			return;
		}
		$orderby = $q->get( 'orderby' );
		if ( ! $orderby || 'vsg_date' === $orderby ) {
			$q->set( 'meta_key', '_vsg_date' );
			$q->set( 'orderby', 'meta_value' );
			if ( ! $q->get( 'order' ) || ! $orderby ) {
				$q->set( 'order', 'DESC' );
			}
		}
	}
);
