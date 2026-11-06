<?php
/**
 * Administration claire pour le secrétariat comme pour l'administrateur :
 * - menu court, dans l'ordre d'usage : publier (agenda, actualités, feuilles), puis le site
 *   (pages, médias), puis la partie technique (administrateur seulement) ;
 * - rendez-vous : formulaire simple, « Date et lieu » juste sous le titre (et non plus en bas
 *   de la colonne de droite, où on ne le voyait pas) ;
 * - encadré « comment faire » en haut de chaque écran de saisie, exemples dans les champs ;
 * - alertes : rendez-vous sans date, sans paroisse ou sans image ;
 * - tableau de bord : brouillons en cours ; page de connexion aux couleurs des paroisses.
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Menu de l'administration ---------- */

add_filter( 'custom_menu_order', '__return_true' );
add_filter(
	'menu_order',
	function () {
		return array(
			'index.php',
			'separator1',
			'edit.php?post_type=evenement',
			'edit.php',
			'edit.php?post_type=feuille',
			'separator2',
			'edit.php?post_type=page',
			'upload.php',
			'separator-last',
			'themes.php',
			'users.php',
			'options-general.php',
			'tools.php',
			'plugins.php',
		);
	}
);

add_action(
	'admin_menu',
	function () {
		global $menu;
		foreach ( (array) $menu as $i => $item ) {
			if ( 'upload.php' === $item[2] ) {
				$menu[ $i ][0] = 'Médias (images, PDF)'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- libellé du menu de WordPress.
			}
			if ( 'users.php' === $item[2] ) {
				$menu[ $i ][0] = 'Comptes'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- libellé du menu de WordPress.
			}
		}
		/* « Ajouter » en double dans chaque menu : le bouton en haut de la liste suffit. */
		remove_submenu_page( 'edit.php?post_type=evenement', 'post-new.php?post_type=evenement' );
		remove_submenu_page( 'edit.php', 'post-new.php' );
		remove_submenu_page( 'edit.php?post_type=feuille', 'post-new.php?post_type=feuille' );
		remove_submenu_page( 'edit.php?post_type=page', 'post-new.php?post_type=page' );
		remove_submenu_page( 'upload.php', 'media-new.php' );
		remove_submenu_page( 'edit.php', 'edit-tags.php?taxonomy=post_tag' );
		/* Secrétariat : pas d'outils d'export / import de WordPress (l'import de l'agenda reste dans « Agenda »). */
		if ( vsg_is_secretariat() ) {
			remove_menu_page( 'tools.php' );
		}
	},
	999
);

/* Bouton « + Créer » de la barre du haut : seulement ce que l'équipe publie. */
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		foreach ( array( 'new-media', 'new-user', 'wp-logo', 'comments' ) as $id ) {
			$bar->remove_node( $id );
		}
	},
	80
);

/* ---------- Formulaires de saisie ---------- */

/* Rendez-vous : formulaire simple (titre, date et lieu, texte) plutôt que l'éditeur de blocs. */
add_filter(
	'use_block_editor_for_post_type',
	function ( $actif, $type ) {
		return 'evenement' === $type ? false : $actif;
	},
	10,
	2
);

/* « Date et lieu » juste sous le titre du rendez-vous. */
add_action(
	'add_meta_boxes_evenement',
	function () {
		global $wp_meta_boxes;
		if ( isset( $wp_meta_boxes['evenement']['side']['high']['vsg_event'] ) ) {
			$wp_meta_boxes['evenement']['vsg_sous_titre']['high']['vsg_event'] = $wp_meta_boxes['evenement']['side']['high']['vsg_event']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- déplacement d'un cadre de saisie du thème.
			unset( $wp_meta_boxes['evenement']['side']['high']['vsg_event'] );
		}
	},
	99
);
add_action(
	'edit_form_after_title',
	function ( $post ) {
		if ( 'evenement' === $post->post_type ) {
			do_meta_boxes( get_current_screen(), 'vsg_sous_titre', $post );
		}
	}
);

/* Exemple dans le champ du titre. */
add_filter(
	'enter_title_here',
	function ( $text, $post ) {
		$examples = array(
			'evenement' => 'Nom du rendez-vous (ex. : Messe des familles, Réunion de l\'équipe liturgique)',
			'post'      => 'Titre de l\'actualité',
			'feuille'   => 'Titre de la feuille (proposé automatiquement)',
			'page'      => 'Titre de la page',
		);
		return $examples[ $post->post_type ] ?? $text;
	},
	10,
	2
);

/* « Image mise en avant » : on dit à quoi elle sert. */
foreach ( array( 'evenement', 'post', 'page' ) as $vsg_type ) {
	add_filter(
		'post_type_labels_' . $vsg_type,
		function ( $l ) {
			$l->featured_image        = 'Image ou affiche (carte et en-tête)';
			$l->set_featured_image    = 'Choisir l\'image';
			$l->remove_featured_image = 'Retirer l\'image';
			$l->use_featured_image    = 'Utiliser comme image';
			return $l;
		}
	);
}
unset( $vsg_type );

/**
 * Aide affichée en haut de l'écran de saisie : [ titre, étapes ].
 *
 * @return array<string, array{0:string,1:string[]}>
 */
function vsg_help_steps() {
	return array(
		'evenement' => array(
			'Ajouter un rendez-vous',
			array(
				'Écrivez le <strong>nom</strong> du rendez-vous.',
				'Remplissez <strong>Date et lieu</strong>, juste en dessous (heure facultative ; type si c\'est un groupe ou une communauté).',
				'Écrivez les détails dans le grand champ de texte (facultatif).',
				'À droite : cochez la <strong>paroisse ou la chapelle</strong> dans « Paroisses et lieux », et choisissez une <strong>image</strong> si vous en avez une.',
				'Cliquez sur <strong>Publier</strong>. Le rendez-vous apparaît dans l\'agenda à sa date. Pour un rendez-vous interne, mettez la visibilité sur « Privé ».',
			),
		),
		'post'      => array(
			'Publier une actualité',
			array(
				'Écrivez le <strong>titre</strong> puis le texte.',
				'Dans le panneau de droite, onglet « Article » : cochez la <strong>paroisse</strong> dans « Paroisses et lieux » (ou « Les deux paroisses ») et choisissez l\'<strong>image</strong>.',
				'Cliquez sur <strong>Publier</strong>.',
			),
		),
		'feuille'   => array(
			'Déposer la feuille paroissiale',
			array(
				'Le <strong>titre</strong> est proposé automatiquement (semaine du dimanche à venir) ; le nom de la paroisse s\'ajoute à l\'enregistrement.',
				'Dans le bloc « Fichier », cliquez sur <strong>Téléverser</strong> et choisissez le PDF.',
				'Dans le panneau de droite : cochez la <strong>paroisse</strong> dans « Paroisses et lieux ».',
				'Cliquez sur <strong>Publier</strong>.',
			),
		),
	);
}

function vsg_help_box( $steps ) {
	echo '<details class="vsg-help-box" open><summary>' . esc_html( $steps[0] ) . ' : comment faire</summary><ol>';
	foreach ( $steps[1] as $s ) {
		echo '<li>' . wp_kses( $s, array( 'strong' => array() ) ) . '</li>';
	}
	echo '</ol></details>';
}

/* Formulaire simple (rendez-vous) : encadré au-dessus du titre. */
add_action(
	'edit_form_top',
	function ( $post ) {
		$steps = vsg_help_steps()[ $post->post_type ] ?? null;
		if ( $steps ) {
			vsg_help_box( $steps );
		}
	}
);

/* Éditeur de blocs (actualités, feuilles) : même encadré, en notice repliable. */
add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || ! $screen->is_block_editor() ) {
			return;
		}
		$steps = vsg_help_steps()[ $screen->post_type ] ?? null;
		if ( $steps ) {
			vsg_help_box( $steps );
		}
	}
);

/* ---------- Alertes sur un rendez-vous publié ---------- */

add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || 'evenement' !== $screen->post_type ) {
			return;
		}
		$post = get_post();
		if ( ! $post || ! in_array( $post->post_status, array( 'publish', 'private', 'future' ), true ) ) {
			return;
		}
		$missing = array();
		if ( ! vsg_meta( $post->ID, 'date' ) ) {
			$missing[] = 'la <strong>date</strong> (le rendez-vous n\'apparaît pas dans l\'agenda)';
		}
		$cats = array_diff( wp_get_post_categories( $post->ID ), array( (int) get_option( 'default_category' ) ) );
		if ( ! $cats ) {
			$missing[] = 'la <strong>paroisse</strong> (cochez-la dans « Paroisses et lieux », à droite)';
		}
		if ( $missing ) {
			echo '<div class="notice notice-warning"><p>Ce rendez-vous est enregistré, mais il lui manque ' . wp_kses( implode( ' et ', $missing ), array( 'strong' => array() ) ) . '.</p></div>';
		}
	}
);

/* ---------- Listes et tableau de bord ---------- */

/* Colonnes utiles seulement. */
foreach ( array( 'posts', 'pages', 'evenement_posts', 'feuille_posts' ) as $vsg_list ) {
	add_filter(
		'manage_' . $vsg_list . '_columns',
		function ( $cols ) {
			unset( $cols['author'], $cols['comments'], $cols['tags'] );
			return $cols;
		},
		30
	);
}
unset( $vsg_list );

add_action(
	'wp_dashboard_setup',
	function () {
		wp_add_dashboard_widget( 'vsg_brouillons', 'Brouillons en cours', 'vsg_dashboard_drafts', null, null, 'side' );
		if ( vsg_is_secretariat() ) {
			remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
		}
	},
	20
);
remove_action( 'welcome_panel', 'wp_welcome_panel' );

function vsg_dashboard_drafts() {
	$drafts = get_posts(
		array(
			'post_type'      => array( 'evenement', 'post', 'feuille', 'page' ),
			'post_status'    => array( 'draft', 'pending', 'future' ),
			'posts_per_page' => 8,
			'orderby'        => 'modified',
		)
	);
	if ( ! $drafts ) {
		echo '<p>Aucun brouillon : tout est publié.</p>';
		return;
	}
	$types = array(
		'evenement' => 'Rendez-vous',
		'post'      => 'Actualité',
		'feuille'   => 'Feuille',
		'page'      => 'Page',
	);
	echo '<ul>';
	foreach ( $drafts as $d ) {
		$state = 'future' === $d->post_status ? 'programmé' : ( 'pending' === $d->post_status ? 'à relire' : 'brouillon' );
		echo '<li><a href="' . esc_url( (string) get_edit_post_link( $d ) ) . '">' . esc_html( get_the_title( $d ) ? get_the_title( $d ) : '(sans titre)' ) . '</a> — ' . esc_html( $types[ $d->post_type ] . ', ' . $state ) . '</li>';
	}
	echo '</ul>';
}

/*
 * Tableau de bord fixe : une seule colonne pleine largeur, panneaux ni déplaçables ni repliables.
 * Les flèches « Monter / Descendre » de WordPress créaient des colonnes vides (« Glissez les blocs ici »)
 * et coinçaient les actions dans une colonne étroite ; les réglages déjà enregistrés sont ignorés.
 */
add_filter(
	'get_user_option_screen_layout_dashboard',
	function () {
		return 1;
	}
);
add_filter( 'get_user_option_meta-box-order_dashboard', '__return_empty_array' );
add_filter( 'get_user_option_closedpostboxes_dashboard', '__return_empty_array' );
add_action(
	'admin_head-index.php',
	function () {
		echo '<style>
			#dashboard-widgets .postbox-container { width: 100% !important; }
			#dashboard-widgets .handle-actions, #dashboard-widgets .empty-container, #dashboard-widgets .sortable-placeholder { display: none !important; }
			#dashboard-widgets .postbox-header .hndle { cursor: default !important; }
			#dashboard-widgets .meta-box-sortables { min-height: 0 !important; }
			#dashboard-widgets-wrap { max-width: 1100px; }
		</style>';
	}
);
add_action(
	'admin_footer-index.php',
	function () {
		echo '<script>jQuery( function ( $ ) { if ( $.fn.sortable ) { $( "#dashboard-widgets .meta-box-sortables" ).sortable( "disable" ); } } );</script>';
	}
);

/* Pied de page de l'administration : un rappel utile plutôt que la mention de WordPress. */
add_filter(
	'admin_footer_text',
	function () {
		return 'Une question ? Le tableau de bord « Que voulez-vous faire ? » mène à chaque action ; chaque écran de saisie a son encadré « comment faire ».';
	}
);

/* Les avis de mise à jour de WordPress concernent l'administrateur, pas le secrétariat. */
add_action(
	'admin_head',
	function () {
		if ( vsg_is_secretariat() ) {
			remove_action( 'admin_notices', 'update_nag', 3 );
			remove_action( 'admin_notices', 'maintenance_nag', 10 );
		}
	}
);

/* ---------- Page de connexion aux couleurs des paroisses ---------- */

add_action(
	'login_enqueue_scripts',
	function () {
		$logo_id = get_theme_mod( 'custom_logo' );
		$logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : VSG_URI . '/assets/img/logo-mark.svg';
		echo '<style>
			body.login { background: #F4F6F9; }
			.login h1 a { background-image: url("' . esc_url( (string) $logo ) . '"); background-size: contain; width: 120px; height: 100px; }
			.login .button-primary { background: #1F3A5F; border-color: #1F3A5F; }
			.login .button-primary:hover, .login .button-primary:focus { background: #162B47; border-color: #162B47; }
			.login #nav a, .login #backtoblog a { color: #1F3A5F; }
		</style>';
	}
);
add_filter(
	'login_headerurl',
	function () {
		return home_url( '/' );
	}
);
add_filter(
	'login_headertext',
	function () {
		return get_bloginfo( 'name' );
	}
);
