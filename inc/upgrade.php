<?php
/**
 * Corrections de contenu appliquées une seule fois, après une mise à jour du thème.
 *
 * Les étapes antérieures à la 0.9.17 ont été retirées : le site en ligne les a toutes passées
 * (version de contenu 0.9.18 au moment du retrait).
 */

defined( 'ABSPATH' ) || exit;

const VSG_CONTENT_VERSION = '0.9.33';

add_action(
	'init',
	function () {
		$done = get_option( 'vsg_content_version', '0' );
		if ( version_compare( $done, VSG_CONTENT_VERSION, '>=' ) ) {
			return;
		}
		/* Verrou : une seule exécution à la fois, même si deux visiteurs arrivent ensemble. */
		$lock = (int) get_option( 'vsg_upgrade_lock', 0 );
		if ( $lock && $lock > time() - 10 * MINUTE_IN_SECONDS ) {
			return;
		}
		delete_option( 'vsg_upgrade_lock' );
		if ( ! add_option( 'vsg_upgrade_lock', time(), '', false ) ) {
			return;
		}
		$steps = array(
			'0.9.17' => 'vsg_upgrade_0917',
			'0.9.18' => 'vsg_upgrade_0918',
			'0.9.25' => 'vsg_upgrade_0925',
			'0.9.32' => 'vsg_upgrade_0932',
			'0.9.33' => 'vsg_upgrade_0933',
		);
		foreach ( $steps as $ver => $fn ) {
			if ( version_compare( $done, $ver, '<' ) ) {
				/* Version enregistrée avant l'étape : une étape qui échoue n'est pas rejouée en boucle. */
				update_option( 'vsg_content_version', $ver );
				$fn();
			}
		}
		update_option( 'vsg_content_version', VSG_CONTENT_VERSION );
		delete_option( 'vsg_upgrade_lock' );
	},
	20
);

/** Le texte d'un contenu contient-il encore la phrase de la démonstration ? (sinon : modifié, on n'y touche pas) */
function vsg_upgrade_is_demo_text( $post, $needle ) {
	$txt = html_entity_decode( wp_strip_all_tags( (string) $post->post_content ), ENT_QUOTES, 'UTF-8' );
	$txt = str_replace( array( "\u{2019}", "\u{00A0}" ), array( "'", ' ' ), $txt );
	return false !== mb_strpos( $txt, $needle );
}

/**
 * Mentions légales et données personnelles.
 * Éditeur : les paroisses, qui relèvent de l'Association diocésaine de Créteil ;
 * directeur de la publication : le curé.
 */
function vsg_mentions_content() {
	$p    = function ( $t ) {
		return "<!-- wp:paragraph -->\n<p>" . $t . "</p>\n<!-- /wp:paragraph -->\n\n";
	};
	$h    = function ( $t, $l = 2 ) {
		$a = 2 === $l ? '' : ' {"level":' . $l . '}';
		return "<!-- wp:heading{$a} -->\n<h{$l} class=\"wp-block-heading\">" . $t . "</h{$l}>\n<!-- /wp:heading -->\n\n";
	};
	$ul   = function ( $items ) {
		$o = "<!-- wp:list -->\n<ul class=\"wp-block-list\">";
		foreach ( $items as $i ) {
			$o .= "<!-- wp:list-item -->\n<li>" . $i . "</li>\n<!-- /wp:list-item -->";
		}
		return $o . "</ul>\n<!-- /wp:list -->\n\n";
	};
	$sec  = 'secretariat@ndl-sg-eglises94.fr';
	$adr  = '40 avenue Anatole-France, 94190 Villeneuve-Saint-Georges';
	$dioc = 'Association diocésaine de Créteil, 2 avenue Pasteur Vallery-Radot, 94000 Créteil';

	return $h( 'Éditeur du site' )
		. $p( 'Le site ndl-sg-eglises94.fr est édité par les paroisses Notre-Dame de Lourdes et Sainte-Geneviève de Villeneuve-Saint-Georges, avec les chapelles Saint-Louis et Saint-Joseph, diocèse de Créteil.' )
		. $p( 'Les paroisses n\'ont pas de personnalité juridique propre : elles relèvent de l\'Association diocésaine de Créteil, association reconnue d\'utilité publique, SIREN 340 818 483, 2 avenue Pasteur Vallery-Radot, 94000 Créteil.' )
		. $p( 'Contact : secrétariat paroissial, ' . $adr . ', 01 43 89 02 97, ' . $sec . '.' )
		. $h( 'Directeur de la publication' )
		. $p( 'Le père Dramane Mathieu Marie Coulibaly, curé des paroisses Notre-Dame de Lourdes et Sainte-Geneviève.' )
		. $h( 'Hébergement' )
		. $p( 'OVH SAS, société par actions simplifiée au capital de 50 000 000 €, RCS Lille Métropole 424 761 419, 2 rue Kellermann, 59100 Roubaix, France.' )
		. $h( 'Données personnelles' )
		. $p( 'Responsable du traitement : l\'Association diocésaine de Créteil, pour les paroisses Notre-Dame de Lourdes et Sainte-Geneviève.' )
		. $p( 'Le site ne crée pas de compte pour les visiteurs et ne garde pas ce que vous envoyez par ses formulaires : chaque message part par mail vers la boîte concernée, puis le site l\'oublie. Les messages reçus sont conservés dans ces boîtes le temps de traiter la demande, puis supprimés au plus tard trois mois après.' )
		. $h( 'Ce que chaque formulaire transmet', 3 )
		. $ul(
			array(
				'<strong>Contact</strong> : nom, adresse mail, objet et message. Lu par le secrétariat ou la paroisse choisie.',
				'<strong>Intention de prière</strong> : le texte de l\'intention ; le prénom et l\'adresse mail seulement si vous les donnez. Lue par les prêtres de la paroisse, et eux seuls.',
				'<strong>Demande de messe</strong> : nom, adresse mail, téléphone si vous le donnez, intention, date et lieu souhaités, montant de l\'offrande et mode de règlement. Lue par le secrétariat. Les pièces comptables liées à l\'offrande suivent les durées légales de la comptabilité.',
				'<strong>Don en ligne</strong> : le montant et le lieu choisi. Le site garde un relevé des 300 derniers paiements (date, lieu, montant, référence), sans nom ni coordonnées.',
			)
		)
		. $p( 'Ces données servent uniquement à répondre à votre demande. Elles ne sont ni vendues, ni prêtées, ni utilisées pour de la publicité.' )
		. $p( 'Une intention de prière peut révéler des informations sur votre santé ou votre foi. Le formulaire vous demande donc votre accord explicite avant l\'envoi, et la transmet aux seuls prêtres de la paroisse.' )
		. $h( 'Paiements', 3 )
		. $p( 'Les dons et les offrandes de messe réglés en ligne passent par SumUp, prestataire de paiement. Vous payez sur sa page sécurisée, selon sa propre politique de confidentialité : le site ne voit jamais vos coordonnées bancaires.' )
		. $h( 'Vos droits', 3 )
		. $p( 'Vous pouvez demander à consulter, corriger ou effacer les données vous concernant, vous opposer à leur utilisation ou retirer votre accord. Écrivez au secrétariat (' . $sec . ' ou ' . $adr . '), au service communication du diocèse de Créteil (communication@eveche-creteil.cef.fr) ou par courrier à l\'' . $dioc . '.' )
		. $p( 'Si vous estimez que vos droits ne sont pas respectés, vous pouvez adresser une réclamation à la CNIL (cnil.fr).' )
		. $h( 'Cookies' )
		. $p( 'Le site ne dépose aucun cookie de mesure d\'audience ni de publicité et n\'utilise aucun outil de statistiques : c\'est pourquoi aucun bandeau de consentement ne s\'affiche. Seules les personnes qui gèrent le site reçoivent, en se connectant, les cookies techniques de leur session.' )
		. $p( 'Les vidéos YouTube sont intégrées en mode « confidentialité renforcée » (youtube-nocookie.com). YouTube peut toutefois déposer ses propres traceurs quand vous lancez une vidéo. Les liens « Itinéraire » ouvrent Google Maps, qui applique ses propres règles.' )
		. $h( 'Sécurité' )
		. $p( 'Pour bloquer les robots, le site limite le nombre de tentatives de connexion et d\'envois de formulaires. Il ne conserve pas votre adresse IP : il en calcule une empreinte chiffrée, effacée au bout de 10 à 15 minutes. L\'hébergeur conserve par ailleurs les journaux techniques de ses serveurs, selon ses propres obligations.' )
		. $h( 'Contenus et liens externes' )
		. $p( 'Certaines pages présentent des vidéos publiées sur YouTube par d\'autres auteurs, dont la source est indiquée sous chaque vidéo. Elles restent la propriété de leurs auteurs. Les textes des lectures du jour sont consultables sur le site de l\'AELF. Les paroisses ne sont pas responsables du contenu des sites vers lesquels le site renvoie.' )
		. $h( 'Droit à l\'image et propriété' )
		. $p( 'Les photos sont publiées avec l\'accord des personnes reconnaissables, et de leurs parents pour les mineurs. Pour demander le retrait d\'une photo, écrivez au secrétariat. Les textes et photos du site appartiennent aux paroisses ou à leurs auteurs : merci de demander l\'autorisation avant de les reproduire.' )
		. $p( '<em>Dernière mise à jour : octobre 2026.</em>' );
}

/** 0.9.17 : mentions légales complètes (seulement si la page n'a pas été réécrite à la main). */
function vsg_upgrade_0917() {
	foreach ( get_posts(
		array(
			'post_type'      => 'page',
			'name'           => 'mentions-legales',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
		)
	) as $p ) {
		if ( vsg_upgrade_is_demo_text( $p, 'Le père Mathieu Coulibaly, curé.' ) ) {
			wp_update_post(
				array(
					'ID'           => $p->ID,
					'post_title'   => 'Mentions légales et données personnelles',
					'post_content' => vsg_mentions_content(),
				)
			);
		}
	}
}

/** Pages « Communauté » : slug de la page => type de rendez-vous dans l'agenda. */
function vsg_upgrade_group_pages() {
	return array(
		'enfants-de-padre-pio' => 'padre-pio',
		'communaute-tamoule'   => 'tamoule',
		'groupe-anizan'        => 'anizan',
		'groupe-de-priere'     => 'groupe-priere',
		'chorale'              => 'chorale',
		'pastorale-des-jeunes' => 'pastorale-jeunes',
		'aumonerie'            => 'aumonerie',
		'jeunes-18-30'         => 'jeunes-18-30',
		'servants-d-autel'     => 'servants',
	);
}

/** Ajoute une image du thème à la médiathèque et la met en image de la page. */
function vsg_upgrade_set_illustration( $page_id, $file, $title ) {
	$src = VSG_DIR . '/assets/img/illustrations/' . $file;
	if ( ! file_exists( $src ) || has_post_thumbnail( $page_id ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$tmp = wp_tempnam( $file );
	copy( $src, $tmp );
	$id = media_handle_sideload(
		array(
			'name'     => $file,
			'tmp_name' => $tmp,
		),
		$page_id,
		$title
	);
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return;
	}
	update_post_meta( $id, '_wp_attachment_image_alt', '' );
	set_post_thumbnail( $page_id, $id );
}

/**
 * 0.9.18 : un groupe = une page + un type dans l'agenda ; pages de la communauté tamoule
 * et du groupe Anizan ; illustrations des groupes sans image ; page « Horaires » ;
 * mentions légales : contact du diocèse.
 */
function vsg_upgrade_0918() {
	$tpl   = VSG_TPL_COMMUNAUTE;
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
			'meta_key'       => '_wp_page_template',
			'meta_value'     => $tpl,
		)
	);

	/* 1. Type d'agenda de chaque page de groupe (si rien n'est encore choisi). */
	$map = vsg_upgrade_group_pages();
	foreach ( $pages as $p ) {
		if ( isset( $map[ $p->post_name ] ) && ! get_post_meta( $p->ID, '_vsg_type', true ) ) {
			update_post_meta( $p->ID, '_vsg_type', $map[ $p->post_name ] );
		}
	}

	/* 2. Communauté tamoule et groupe Anizan : leurs rendez-vous ont lieu à Notre-Dame de Lourdes et à la chapelle Saint-Louis. */
	$ndl_list = 0;
	foreach ( $pages as $p ) {
		if ( 'enfants-de-padre-pio' === $p->post_name ) {
			$ndl_list = (int) $p->post_parent;
		}
	}
	if ( $ndl_list ) {
		$order = 10;
		foreach ( array(
			'tamoule' => array( 'Communauté tamoule', 'communaute-tamoule' ),
			'anizan'  => array( 'Groupe Anizan', 'groupe-anizan' ),
		) as $type => $info ) {
			if ( get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'draft', 'private' ),
					'meta_key'       => '_vsg_type',
					'meta_value'     => $type,
					'fields'         => 'ids',
					'posts_per_page' => 1,
				)
			) ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_title'  => $info[0],
					'post_name'   => $info[1],
					'post_parent' => $ndl_list,
					'menu_order'  => $order++,
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_wp_page_template', $tpl );
				update_post_meta( $id, '_vsg_type', $type );
				vsg_set_a_venir( $id );
			}
		}
	}

	/* 3. Illustrations pour les groupes sans image (la secrétaire peut les remplacer). */
	$files = array(
		'pastorale-des-jeunes' => 'pastorale-des-jeunes.jpg',
		'aumonerie'            => 'aumonerie.jpg',
		'jeunes-18-30'         => 'jeunes-18-30.jpg',
		'servants-d-autel'     => 'servants-d-autel.jpg',
		'groupe-de-priere'     => 'groupe-de-priere.jpg',
		'communaute-tamoule'   => 'communaute-tamoule.jpg',
		'groupe-anizan'        => 'groupe-anizan.jpg',
	);
	foreach ( get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
			'meta_key'       => '_wp_page_template',
			'meta_value'     => $tpl,
		)
	) as $p ) {
		$file = $files[ $p->post_name ] ?? '';
		if ( 'chorale' === $p->post_name ) {
			$ctx  = vsg_context_parish( $p );
			$file = ( $ctx && 'sainte-genevieve' === $ctx->post_name ) ? 'chorale-sg.jpg' : 'chorale-ndl.jpg';
		}
		if ( $file ) {
			vsg_upgrade_set_illustration( $p->ID, $file, 'Image d\'illustration – ' . get_the_title( $p ) );
		}
	}

	/* 4. « Horaires » : la page couvre aussi les confessions et l'adoration. */
	$hours = vsg_hours_page();
	if ( $hours && 'Horaires des messes' === $hours->post_title ) {
		wp_update_post(
			array(
				'ID'         => $hours->ID,
				'post_title' => 'Horaires',
			)
		);
	}
	foreach ( get_posts(
		array(
			'post_type'      => 'page',
			'name'           => 'agenda',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
		)
	) as $p ) {
		if ( false !== strpos( $p->post_content, '/horaires-des-messes/">Horaires des messes</a>' ) ) {
			wp_update_post(
				array(
					'ID'           => $p->ID,
					'post_content' => str_replace( '/horaires-des-messes/">Horaires des messes</a>', '/horaires-des-messes/">Horaires</a>', $p->post_content ),
				)
			);
		}
	}

	/* 5. Mentions légales : ajout du contact du diocèse (si la page n'a pas été réécrite à la main). */
	foreach ( get_posts(
		array(
			'post_type'      => 'page',
			'name'           => 'mentions-legales',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
		)
	) as $p ) {
		if ( vsg_upgrade_is_demo_text( $p, 'Écrivez au secrétariat (secretariat@ndl-sg-eglises94.fr ou 40 avenue Anatole-France, 94190 Villeneuve-Saint-Georges) ou à l\'Association diocésaine' ) ) {
			wp_update_post(
				array(
					'ID'           => $p->ID,
					'post_content' => vsg_mentions_content(),
				)
			);
		}
	}
}


/**
 * 0.9.25 : suites de l'audit.
 * - plus aucune trace de la démonstration (liste des contenus suivis, compte de démo) ;
 * - brocantes restées sans paroisse rattachées à Notre-Dame de Lourdes ;
 * - secrets (mot de passe SMTP, clé SumUp) qui ne sont plus chargés à chaque page ;
 * - règles de protection dans le .htaccess.
 */
function vsg_upgrade_0925() {
	delete_option( 'vsg_demo_ids' );
	delete_option( 'vsg_demo_editor_pwd' );
	$demo = get_user_by( 'login', 'secretariat' );
	if ( $demo && str_ends_with( (string) $demo->user_email, '@example.org' ) && ! user_can( $demo, 'manage_options' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		wp_delete_user( $demo->ID, $admins ? (int) $admins[0] : null );
	}

	$ndl = get_term_by( 'slug', 'notre-dame-de-lourdes', 'category' );
	if ( $ndl ) {
		$default = (int) get_option( 'default_category' );
		foreach ( get_posts(
			array(
				'post_type'      => 'evenement',
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => -1,
				'meta_key'       => '_vsg_type',
				'meta_value'     => 'brocante',
			)
		) as $p ) {
			$terms = wp_get_post_terms( $p->ID, 'category', array( 'fields' => 'ids' ) );
			if ( ! array_diff( array_map( 'intval', $terms ), array( $default ) ) ) {
				wp_set_post_terms( $p->ID, array( (int) $ndl->term_id ), 'category' );
			}
		}
	}

	vsg_secrets_migrate();
	vsg_htaccess_write();
}

/** 0.9.32 : page « Le mot du curé » (texte complet ; il se saisit toujours dans la page d'accueil). */
function vsg_upgrade_0932() {
	if ( vsg_cure_page() || get_page_by_path( 'mot-du-cure' ) ) {
		return;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Le mot du curé',
			'post_name'    => 'mot-du-cure',
			'post_excerpt' => 'Le mot du curé de l\'Ensemble paroissial Notre-Dame de Lourdes – Sainte-Geneviève pour l\'année pastorale.',
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_wp_page_template', VSG_TPL_MOT_CURE );
	}
}

/**
 * 0.9.33 : le mot du curé passe du champ de la page d'accueil au contenu de sa page.
 * L'ancien contenu de la page reste dans ses révisions ; le champ est ensuite vidé.
 */
function vsg_upgrade_0933() {
	$home = (int) get_option( 'page_on_front' );
	$text = $home ? trim( (string) get_post_meta( $home, '_vsg_cure_mot', true ) ) : '';
	if ( '' === $text || '[' === $text[0] ) {
		if ( $home ) {
			delete_post_meta( $home, '_vsg_cure_mot' );
		}
		return;
	}
	vsg_upgrade_0932();
	$page = vsg_cure_page();
	if ( ! $page ) {
		return;
	}
	$blocks = array();
	foreach ( preg_split( '/\R+/u', $text ) as $para ) {
		$para = trim( $para );
		if ( '' !== $para ) {
			$blocks[] = "<!-- wp:paragraph -->\n<p>" . htmlspecialchars( $para, ENT_NOQUOTES, 'UTF-8' ) . "</p>\n<!-- /wp:paragraph -->";
		}
	}
	if ( trim( (string) $page->post_content ) && ! wp_get_post_revisions( $page->ID ) ) {
		wp_save_post_revision( $page->ID );
	}
	$done = wp_update_post(
		array(
			'ID'           => $page->ID,
			'post_content' => implode( "\n\n", $blocks ),
		),
		true
	);
	if ( $done && ! is_wp_error( $done ) ) {
		delete_post_meta( $home, '_vsg_cure_mot' );
	}
}
