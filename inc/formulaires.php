<?php
/**
 * Socle commun des formulaires du site (contact, intention de prière, demande de messe, don).
 *
 * Chaque formulaire garde ses champs, ses contrôles et son envoi ; tout le reste est ici :
 * - l'état pendant la requête (valeurs renvoyées, erreurs par champ) ;
 * - la protection : jeton, champ piège, délai minimal, limites d'envoi ;
 * - les champs cachés et les messages, toujours construits de la même façon ;
 * - les attributs d'accessibilité des champs en erreur.
 */

defined( 'ABSPATH' ) || exit;

/* ---------- État ---------- */

/**
 * État d'un formulaire pendant la requête : array( 'errors' => champ => message, 'values' => champ => valeur ).
 *
 * @param string $form Identifiant court du formulaire (contact, intention, messe, don).
 * @return array Référence : les modifications sont conservées.
 */
function &vsg_form_state( $form ) {
	static $states = array();
	if ( ! isset( $states[ $form ] ) ) {
		$states[ $form ] = array(
			'errors' => array(),
			'values' => array(),
		);
	}
	return $states[ $form ];
}

/* ---------- Protection ---------- */

/** Champ caché daté et signé : un envoi trop rapide ou trop vieux est refusé (voir vsg_form_guard). */
function vsg_form_timer_field() {
	$t = time();
	return '<input type="hidden" name="vsg_t" value="' . esc_attr( $t . '.' . wp_hash( 'vsg_t' . $t ) ) . '">';
}

/** Coupe un texte saisi à une longueur raisonnable (protège les mails et la mémoire). */
function vsg_clip( $text, $max ) {
	return mb_substr( (string) $text, 0, $max );
}

/* Plafond pour tout le site : au plus 30 envois de formulaire par heure, toutes connexions confondues. */
const VSG_FORMS_PER_HOUR = 30;

/**
 * Limites d'envoi d'un formulaire public. À appeler après la vérification du jeton.
 *
 * @param string $form   Identifiant du formulaire.
 * @param int    $max    Envois autorisés par connexion pendant $period.
 * @param int    $period Durée en secondes.
 * @return string Message d'erreur, ou '' si l'envoi est accepté.
 */
function vsg_form_guard( $form, $max = 5, $period = 600 ) {
	$raw   = sanitize_text_field( wp_unslash( $_POST['vsg_t'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- le jeton est vérifié par l'appelant.
	$parts = explode( '.', $raw, 2 );
	$t     = (int) ( $parts[0] ?? 0 );
	if ( ! $t || ! hash_equals( wp_hash( 'vsg_t' . $t ), (string) ( $parts[1] ?? '' ) ) ) {
		return 'La page a expiré. Merci de renvoyer le formulaire.';
	}
	if ( vsg_maint_is_on() && ! current_user_can( 'edit_posts' ) ) {
		return 'Le site est en maintenance : les formulaires sont fermés pour quelques instants.';
	}
	if ( time() - $t < 4 ) {
		return 'Envoi trop rapide. Merci de patienter quelques secondes et de renvoyer le formulaire.';
	}
	if ( time() - $t > 2 * HOUR_IN_SECONDS ) {
		return 'La page a expiré. Merci de renvoyer le formulaire.';
	}
	$hour  = 'vsg_rl_site_' . gmdate( 'YmdH' );
	$total = (int) get_transient( $hour );
	if ( $total >= VSG_FORMS_PER_HOUR ) {
		return 'Le service est momentanément saturé. Merci de réessayer dans une heure, ou d\'appeler l\'accueil paroissial.';
	}
	$key = 'vsg_rl_' . $form . '_' . vsg_ip_hash();
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) {
		return 'Vous avez déjà envoyé plusieurs demandes. Merci de réessayer un peu plus tard, ou d\'appeler l\'accueil paroissial.';
	}
	set_transient( $key, $n + 1, $period );
	set_transient( $hour, $total + 1, HOUR_IN_SECONDS );
	return '';
}

/**
 * Adresse de retour après un envoi : la page courante, sans ancre ni paramètre d'état.
 *
 * @param string|string[] $strip Paramètres d'adresse à retirer.
 */
function vsg_form_back( $strip ) {
	$uri = wp_unslash( strtok( $_SERVER['REQUEST_URI'] ?? '/', '#' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- adresse relative, nettoyée par home_url() et remove_query_arg().
	return remove_query_arg( $strip, home_url( $uri ) );
}

/**
 * Début commun du traitement d'un envoi (sur template_redirect).
 *
 * Renvoie null si la requête n'est pas un envoi de ce formulaire ; false si le jeton est périmé
 * (l'erreur est notée dans l'état) ; sinon l'adresse de retour. Un robot (champ piège rempli)
 * est renvoyé vers la page comme si tout s'était bien passé.
 *
 * @param string $form   Identifiant du formulaire ; le jeton se nomme vsg_<form>_nonce.
 * @param string $param  Paramètre d'adresse qui porte l'état après envoi.
 * @param string $ok     Valeur de ce paramètre pour un envoi réussi.
 * @param string $anchor Ancre du bloc du formulaire (#…).
 * @return string|false|null
 */
function vsg_form_submitted( $form, $param, $ok, $anchor ) {
	$field = 'vsg_' . $form . '_nonce';
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST[ $field ] ) ) {
		return null;
	}
	$back = vsg_form_back( $param );
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), 'vsg_' . $form ) ) {
		$state                   = &vsg_form_state( $form );
		$state['errors']['form'] = 'La page a expiré. Merci de renvoyer le formulaire.';
		return false;
	}
	if ( ! empty( $_POST['vsg_site'] ) ) {
		vsg_form_redirect( $back, $param, $ok, $anchor );
	}
	return $back;
}

/** Applique les limites d'envoi quand les champs sont bons ; vrai si l'envoi peut continuer. */
function vsg_form_passes( $form, $max = 5, $period = 600 ) {
	$state = &vsg_form_state( $form );
	if ( ! $state['errors'] ) {
		$guard = vsg_form_guard( $form, $max, $period );
		if ( $guard ) {
			$state['errors']['form'] = $guard;
		}
	}
	return ! $state['errors'];
}

/** Renvoie vers la page du formulaire avec un état (envoyé, démonstration…) et s'arrête là. */
function vsg_form_redirect( $back, $param, $value, $anchor ) {
	wp_safe_redirect( add_query_arg( $param, $value, $back ) . $anchor );
	exit;
}

/**
 * Suite commune après l'envoi du mail : en local, l'absence de serveur de mail n'est pas une erreur
 * (état « demo ») ; en ligne, l'échec est signalé et le formulaire reste affiché.
 *
 * @return bool Vrai si le traitement doit s'arrêter (erreur notée).
 */
function vsg_form_mail_failed( $form, $sent, $back, $param, $anchor, $message ) {
	if ( $sent ) {
		return false;
	}
	if ( 'local' === wp_get_environment_type() ) {
		vsg_form_redirect( $back, $param, 'demo', $anchor );
	}
	$state                   = &vsg_form_state( $form );
	$state['errors']['form'] = $message;
	return true;
}

/* ---------- Affichage ---------- */

/** Valeur d'état portée par l'adresse après un envoi (?message=envoye, ?don=merci…). */
function vsg_form_status( $param ) {
	return isset( $_GET[ $param ] ) ? sanitize_key( $_GET[ $param ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple affichage d'un message, aucune action.
}

/** Champs cachés communs : jeton, champ daté, champ piège. */
function vsg_form_hidden( $form ) {
	$h  = wp_nonce_field( 'vsg_' . $form, 'vsg_' . $form . '_nonce', true, false );
	$h .= vsg_form_timer_field();
	$h .= '<p class="field field--hp" aria-hidden="true"><label for="vsg_site_' . esc_attr( $form ) . '">Site web</label><input type="text" id="vsg_site_' . esc_attr( $form ) . '" name="vsg_site" tabindex="-1" autocomplete="off"></p>';
	return $h;
}

/**
 * Messages en tête du formulaire : l'état après envoi, puis les erreurs.
 *
 * @param string $form     Identifiant du formulaire.
 * @param array  $messages Valeur d'état => array( 'ok'|'err', texte ).
 * @param string $status   Valeur d'état courante.
 */
function vsg_form_notices( $form, $messages, $status ) {
	$e = vsg_form_state( $form )['errors'];
	$h = '';
	if ( isset( $messages[ $status ] ) ) {
		list( $kind, $text ) = $messages[ $status ];
		$h                  .= '<p class="notice notice--' . esc_attr( $kind ) . '" role="' . ( 'ok' === $kind ? 'status' : 'alert' ) . '">' . esc_html( $text ) . '</p>';
	}
	if ( ! empty( $e['form'] ) ) {
		$h .= '<p class="notice notice--err" role="alert">' . esc_html( $e['form'] ) . '</p>';
	} elseif ( $e ) {
		$h .= '<p class="notice notice--err" role="alert">Le formulaire contient des erreurs. Merci de les corriger.</p>';
	}
	return $h;
}

/** Message d'erreur d'un champ, sous le champ. */
function vsg_form_error( $form, $k ) {
	$e = vsg_form_state( $form )['errors'];
	return isset( $e[ $k ] ) ? '<span class="field__error" id="err-' . esc_attr( $form . '-' . $k ) . '">' . esc_html( $e[ $k ] ) . '</span>' : '';
}

/** Attributs d'un champ en erreur, pour les lecteurs d'écran. */
function vsg_form_aria( $form, $k ) {
	$e = vsg_form_state( $form )['errors'];
	return isset( $e[ $k ] ) ? ' aria-invalid="true" aria-describedby="err-' . esc_attr( $form . '-' . $k ) . '"' : '';
}

/** Libellé d'un champ avec sa mention (obligatoire, facultatif…). */
function vsg_form_label( $field_id, $text, $note = '' ) {
	return '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $text ) . ( $note ? ' <span class="muted">(' . esc_html( $note ) . ')</span>' : '' ) . '</label>';
}

/** Case de consentement, avec son lien vers les mentions légales. */
function vsg_form_consent( $form, $id, $text, $checked ) {
	$h  = '<div class="field field--check">';
	$h .= '<input type="checkbox" id="' . esc_attr( $id ) . '" name="vsg_accord" value="1"' . checked( $checked, true, false ) . vsg_form_aria( $form, 'accord' ) . '>';
	$h .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $text ) . ' <a href="' . esc_url( vsg_page_url( 'mentions-legales' ) ) . '">En savoir plus</a></label>';
	$h .= vsg_form_error( $form, 'accord' );
	$h .= '</div>';
	return $h;
}
