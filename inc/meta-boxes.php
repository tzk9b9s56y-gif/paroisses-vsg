<?php
/**
 * Champs simples pour la secrétaire : date des rendez-vous, coordonnées des lieux.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'add_meta_boxes',
	function ( $post_type, $post ) {
		if ( 'evenement' === $post_type ) {
			add_meta_box( 'vsg_event', 'Date et lieu du rendez-vous', 'vsg_box_event', 'evenement', 'side', 'high' );
		}
		if ( 'feuille' === $post_type ) {
			add_meta_box( 'vsg_feuille', 'Fichier PDF', 'vsg_box_feuille', 'feuille', 'side', 'high' );
		}
		if ( 'page' === $post_type ) {
			$tpl = get_page_template_slug( $post );
			if ( in_array( $tpl, array( VSG_TPL_PAROISSE, VSG_TPL_CHAPELLE ), true ) ) {
				add_meta_box( 'vsg_place', 'Coordonnées affichées sur le site', 'vsg_box_place', 'page', 'normal', 'high' );
			}
			if ( (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
				add_meta_box( 'vsg_cure', 'Le mot du curé (affiché sur l\'accueil)', 'vsg_box_cure', 'page', 'normal', 'high' );
				add_meta_box( 'vsg_theme', 'Thème de l\'année (bannière de la page d\'accueil)', 'vsg_box_theme', 'page', 'normal', 'high' );
			}
			if ( VSG_TPL_COMMUNAUTE === $tpl ) {
				add_meta_box( 'vsg_community', 'Informations sur la communauté', 'vsg_box_community', 'page', 'normal', 'high' );
			}
		}
	},
	10,
	2
);

function vsg_field( $post_id, $key, $label, $type = 'text', $help = '' ) {
	$val = vsg_meta( $post_id, $key );
	$id  = 'vsg_' . $key;
	echo '<p style="margin:0 0 12px"><label for="' . esc_attr( $id ) . '" style="display:block;font-weight:600;margin-bottom:4px">' . esc_html( $label ) . '</label>';
	if ( 'textarea' === $type ) {
		echo '<textarea class="widefat" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '">' . esc_textarea( $val ) . '</textarea>';
	} else {
		echo '<input class="widefat" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . esc_attr( $val ) . '">';
	}
	if ( $help ) {
		echo '<span style="display:block;color:#50575e;margin-top:4px">' . esc_html( $help ) . '</span>';
	}
	echo '</p>';
}

function vsg_box_event( $post ) {
	wp_nonce_field( 'vsg_save', 'vsg_nonce' );
	vsg_field( $post->ID, 'date', 'Date', 'date' );
	vsg_field( $post->ID, 'heure', 'Heure', 'time', 'Laisser vide si toute la journée.' );
	vsg_field( $post->ID, 'lieu', 'Lieu précis (facultatif)', 'text', 'Ex. : grande salle, petite salle, cuisine…' );
	$cur = vsg_meta( $post->ID, 'type' );
	echo '<p style="margin:0 0 12px"><label for="vsg_type" style="display:block;font-weight:600;margin-bottom:4px">Type de rendez-vous</label><select class="widefat" id="vsg_type" name="vsg_type"><option value="">—</option>';
	echo vsg_event_type_options( $cur ); // phpcs:ignore
	echo '</select><span style="display:block;color:#50575e;margin-top:4px">Un groupe ou une communauté : le rendez-vous s\'affiche aussi sur sa page.</span></p>';
	echo '<p style="color:#50575e;margin:0">Cochez la paroisse ou la chapelle dans « Paroisses et lieux ». Pour un rendez-vous interne (réservation de salle, réunion d\'équipe), mettez la visibilité sur « Privé » : seules les personnes connectées le verront.</p>';
}

function vsg_box_feuille( $post ) {
	wp_nonce_field( 'vsg_save', 'vsg_nonce' );
	$pdf = vsg_feuille_pdf( $post );
	echo '<p style="margin-top:0">Glissez le fichier PDF dans le contenu (bloc « Fichier »), puis cochez la paroisse dans « Paroisses et lieux ».</p>';
	if ( $pdf ) {
		echo '<p><strong>PDF détecté :</strong><br><a href="' . esc_url( $pdf ) . '" target="_blank" rel="noopener">' . esc_html( wp_basename( $pdf ) ) . '</a></p>';
	} else {
		echo '<p style="color:#b32d2e"><strong>Aucun PDF pour l\'instant.</strong></p>';
	}
}

function vsg_box_place( $post ) {
	wp_nonce_field( 'vsg_save', 'vsg_nonce' );
	$is_parish = VSG_TPL_PAROISSE === get_page_template_slug( $post );
	if ( $is_parish ) {
		$col = vsg_meta( $post->ID, 'couleur' ) ?: vsg_parish_color( $post );
		echo '<p style="margin:0 0 12px"><label for="vsg_couleur" style="display:block;font-weight:600;margin-bottom:4px">Couleur de la paroisse</label><input type="color" id="vsg_couleur" name="vsg_couleur" value="' . esc_attr( $col ) . '" style="width:64px;height:36px;padding:2px"> <span style="color:#50575e">Sert à repérer la paroisse sur le site (bandeau, pastilles).</span></p>';
		vsg_field( $post->ID, 'eglise', 'Adresse de l\'église', 'text' );
		vsg_field( $post->ID, 'adresse', 'Adresse de l\'accueil paroissial', 'text' );
		vsg_field( $post->ID, 'permanences', 'Permanences de l\'accueil', 'textarea', 'Une ligne par créneau.' );
		vsg_field( $post->ID, 'telephone', 'Téléphone', 'text' );
		vsg_field( $post->ID, 'email', 'Adresse mail (reçoit aussi les messages du formulaire)', 'email' );
		echo '<p style="color:#50575e;margin:12px 0 0">Le résumé de la page (panneau « Extrait ») sert d\'introduction sur la page d\'accueil de la paroisse.</p>';
	} else {
		vsg_field( $post->ID, 'adresse', 'Adresse de la chapelle', 'text' );
		vsg_field( $post->ID, 'telephone', 'Téléphone de la chapelle (facultatif)', 'text' );
		vsg_field( $post->ID, 'permanences', 'Permanences de l\'accueil (facultatif)', 'textarea', 'Une ligne par créneau.' );
		vsg_field( $post->ID, 'acces', 'Accès (transports, stationnement, accessibilité)', 'textarea' );
	}
	echo '<h3 style="margin:18px 0 8px;padding-top:12px;border-top:1px solid #dcdcde">Horaires des messes' . ( $is_parish ? ' à l\'église' : ' à la chapelle' ) . '</h3>';
	echo '<p style="color:#50575e;margin:0 0 10px">Une ligne par horaire, par exemple « 9 h » ou « 18 h 30 (messe anticipée) ». Laissez vide s\'il n\'y a pas de messe.</p>';
	vsg_field( $post->ID, 'messes_semaine', 'En semaine', 'textarea', 'Ex. : Mardi et jeudi : 9 h' );
	vsg_field( $post->ID, 'messes_samedi', 'Samedi', 'textarea' );
	vsg_field( $post->ID, 'messes_dimanche', 'Dimanche', 'textarea' );
	vsg_field( $post->ID, 'messes_confessions', 'Confessions et adoration (facultatif)', 'textarea' );
	vsg_cat_field( $post->ID );
}

/** Liste des catégories : relie une page à ses actualités et rendez-vous. */
function vsg_cat_field( $post_id, $help = '' ) {
	$current = vsg_meta( $post_id, 'cat' );
	echo '<p style="margin:0"><label for="vsg_cat" style="display:block;font-weight:600;margin-bottom:4px">Catégorie liée (réglage technique)</label><select id="vsg_cat" name="vsg_cat">';
	echo '<option value="">—</option>';
	foreach ( get_categories( array( 'hide_empty' => false ) ) as $t ) {
		echo '<option value="' . esc_attr( $t->slug ) . '"' . selected( $current, $t->slug, false ) . '>' . esc_html( $t->name ) . '</option>';
	}
	echo '</select></p>';
	if ( $help ) {
		echo '<p style="color:#50575e;margin:4px 0 0">' . esc_html( $help ) . '</p>';
	}
}

function vsg_box_cure( $post ) {
	wp_nonce_field( 'vsg_save', 'vsg_nonce' );
	vsg_field( $post->ID, 'cure_nom', 'Nom du curé', 'text', 'Ex. : Père Jean Dupont, curé' );
	vsg_field( $post->ID, 'cure_mot', 'Texte du mot du curé', 'textarea' );
	vsg_field( $post->ID, 'cure_photo', 'Adresse de la photo du curé (copiée depuis la médiathèque)', 'url' );
	vsg_field( $post->ID, 'cure_tel', 'Téléphone direct du curé (facultatif : vide = rien n\'est affiché)', 'text' );
	vsg_field( $post->ID, 'cure_email', 'Mail direct du curé', 'email' );
	vsg_field( $post->ID, 'pretres', 'Autres prêtres : une ligne par prêtre, sous la forme « Nom ; rôle ; adresse de la photo »', 'textarea' );
}

function vsg_box_theme( $post ) {
	wp_nonce_field( 'vsg_save', 'vsg_nonce' );
	vsg_field( $post->ID, 'theme_titre', 'Thème de l\'année', 'text', 'Ex. : Ensemble, vivons la fraternité. Vide = rien n\'est affiché.' );
	vsg_field( $post->ID, 'theme_image', 'Visuel du thème (adresse de l\'image, copiée depuis la médiathèque)', 'url', 'Facultatif. Une image large (bannière) convient le mieux.' );
	vsg_field( $post->ID, 'theme_lien', 'Lien quand on clique sur le thème', 'url', 'Par exemple l\'actualité de la rentrée pastorale.' );
}

function vsg_box_community( $post ) {
	wp_nonce_field( 'vsg_save', 'vsg_nonce' );
	echo '<p style="margin-top:0;color:#50575e">La présentation et les activités se rédigent dans le contenu de la page. Le résumé (panneau « Extrait ») s\'affiche sur les cartes.</p>';
	vsg_field( $post->ID, 'programme', 'Rendez-vous réguliers', 'textarea', 'Une ligne par rendez-vous. Ex. : Mardi, 20 h : prière et adoration.' );
	vsg_field( $post->ID, 'lieu', 'Lieu habituel', 'text', 'Ex. : église Notre-Dame de Lourdes, salle paroissiale…' );
	vsg_field( $post->ID, 'responsable', 'Responsable ou personne à contacter', 'text' );
	vsg_field( $post->ID, 'telephone', 'Téléphone', 'text' );
	vsg_field( $post->ID, 'email', 'Adresse mail', 'email' );
	vsg_field( $post->ID, 'site', 'Site internet de la communauté', 'url' );
	vsg_field( $post->ID, 'facebook', 'Page Facebook', 'url' );
	vsg_field( $post->ID, 'instagram', 'Compte Instagram', 'url' );
	vsg_field( $post->ID, 'youtube', 'Chaîne YouTube', 'url' );
	$cur = vsg_meta( $post->ID, 'type' );
	echo '<p style="margin:0 0 12px"><label for="vsg_type" style="display:block;font-weight:600;margin-bottom:4px">Rendez-vous affichés sur cette page</label><select class="widefat" id="vsg_type" name="vsg_type"><option value="">—</option>' . vsg_event_type_options( $cur ); // phpcs:ignore
	echo '</select><span style="display:block;color:#50575e;margin-top:4px">Les rendez-vous de ce type s\'affichent dans le programme de la page. Une communauté ou un groupe de prière : son programme est importé à part et n\'apparaît pas dans l\'agenda du site.</span></p>';
	vsg_cat_field( $post->ID, 'Les actualités classées dans cette catégorie s\'affichent sur la page de la communauté.' );
}

add_action(
	'save_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['vsg_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vsg_nonce'] ) ), 'vsg_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$fields = array(
			'date'        => 'date',
			'heure'       => 'time',
			'lieu'        => 'text',
			'eglise'      => 'text',
			'adresse'     => 'text',
			'permanences' => 'textarea',
			'telephone'   => 'text',
			'email'       => 'email',
			'acces'       => 'textarea',
			'cat'         => 'slug',
			'couleur'     => 'color',
			'cure_nom'    => 'text',
			'cure_mot'    => 'textarea',
			'cure_photo'  => 'url',
			'cure_tel'    => 'text',
			'theme_titre' => 'text',
			'theme_image' => 'url',
			'theme_lien'  => 'url',
			'cure_email'  => 'email',
			'type'        => 'slug',
			'pretres'     => 'textarea',
			'messes_semaine'     => 'textarea',
			'messes_samedi'      => 'textarea',
			'messes_dimanche'    => 'textarea',
			'messes_confessions' => 'textarea',
			'programme'   => 'textarea',
			'responsable' => 'text',
			'site'        => 'url',
			'facebook'    => 'url',
			'instagram'   => 'url',
			'youtube'     => 'url',
		);
		foreach ( $fields as $key => $type ) {
			$name = 'vsg_' . $key;
			if ( ! isset( $_POST[ $name ] ) ) {
				continue;
			}
			if ( ! is_scalar( $_POST[ $name ] ) ) {
				continue;
			}
			$raw = (string) wp_unslash( $_POST[ $name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			switch ( $type ) {
				case 'date':
					$val = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '';
					break;
				case 'time':
					$val = preg_match( '/^\d{2}:\d{2}$/', $raw ) ? $raw : '';
					break;
				case 'textarea':
					$val = sanitize_textarea_field( $raw );
					break;
				case 'email':
					$val = sanitize_email( $raw );
					break;
				case 'url':
					$val = esc_url_raw( $raw );
					break;
				case 'slug':
					$val = sanitize_title( $raw );
					break;
				case 'color':
					$val = preg_match( '/^#[0-9a-fA-F]{6}$/', $raw ) ? strtoupper( $raw ) : '';
					break;
				default:
					$val = sanitize_text_field( $raw );
			}
			update_post_meta( $post_id, '_vsg_' . $key, $val );
		}
	}
);
