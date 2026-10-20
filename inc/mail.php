<?php
/**
 * Mails du site : adresse du formulaire de contact, adresse d'expédition,
 * envoi par le serveur SMTP de l'hébergeur (OVH) si un mot de passe est réglé.
 * Tout se règle dans Réglages › Général.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Adresse qui reçoit le formulaire de contact (sauf si la paroisse choisie a sa propre adresse). */
function vsg_contact_email() {
	$e = get_option( 'vsg_contact_email', '' );
	return is_email( $e ) ? $e : get_option( 'admin_email' );
}

/** Adresse d'expédition des mails du site. */
function vsg_mail_from() {
	$e = get_option( 'vsg_mail_from', '' );
	return is_email( $e ) ? $e : '';
}

/**
 * Mot de passe de la boîte d'expédition : constante VSG_SMTP_PASS dans wp-config.php si elle existe,
 * sinon le réglage (enregistré sans chargement automatique à chaque page).
 */
function vsg_smtp_pass() {
	if ( defined( 'VSG_SMTP_PASS' ) && '' !== (string) VSG_SMTP_PASS ) {
		return (string) VSG_SMTP_PASS;
	}
	return (string) get_option( 'vsg_smtp_pass', '' );
}

/* Secrets jamais chargés automatiquement avec les réglages de chaque page. */
function vsg_secrets_no_autoload() {
	if ( function_exists( 'wp_set_option_autoload_values' ) ) {
		wp_set_option_autoload_values( array( 'vsg_smtp_pass' => false, 'vsg_dons_key' => false, 'vsg_update_secret' => false ) );
	}
}
add_action( 'update_option_vsg_smtp_pass', 'vsg_secrets_no_autoload' );
add_action( 'add_option_vsg_smtp_pass', 'vsg_secrets_no_autoload' );

/** Dernier échec d'envoi : adresses masquées, oublié au bout de 7 jours (voir les mentions légales). */
function vsg_mail_error_store( $msg, $fallback ) {
	$msg = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[adresse]', (string) $msg );
	delete_option( 'vsg_mail_last_error' );
	set_transient( 'vsg_mail_last_error', array( 'time' => time(), 'msg' => $msg, 'fallback' => $fallback ), 7 * DAY_IN_SECONDS );
}

add_action(
	'admin_init',
	function () {
		register_setting( 'general', 'vsg_contact_email', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( 'general', 'vsg_mail_from', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( 'general', 'vsg_smtp_host', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting(
			'general',
			'vsg_smtp_pass',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $v ) {
					/* Champ laissé vide : on garde le mot de passe déjà enregistré. */
					if ( ! empty( $_POST['vsg_smtp_clear'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
						return '';
					}
					$v = (string) $v;
					return '' === $v ? (string) get_option( 'vsg_smtp_pass', '' ) : $v;
				},
			)
		);

		add_settings_field(
			'vsg_contact_email',
			'Formulaire de contact : adresse qui le reçoit',
			function () {
				echo '<input type="email" class="regular-text" name="vsg_contact_email" value="' . esc_attr( get_option( 'vsg_contact_email', '' ) ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '"><p class="description">En général l\'adresse du secrétariat. Si le visiteur choisit une paroisse qui a sa propre adresse (fiche de la paroisse), le message part à cette adresse. Vide = adresse d\'administration du site.</p>';
			},
			'general'
		);
		add_settings_field(
			'vsg_mail_from',
			'Mails du site : adresse d\'expédition',
			function () {
				echo '<input type="email" class="regular-text" name="vsg_mail_from" value="' . esc_attr( get_option( 'vsg_mail_from', '' ) ) . '" placeholder="ne-pas-repondre@ndl-sg-eglises94.fr"><p class="description">Adresse du domaine du site, utilisée seulement pour envoyer (formulaires, mots de passe). Personne n\'y répond : les réponses vont à l\'auteur du message.</p>';
			},
			'general'
		);
		add_settings_field(
			'vsg_smtp',
			'Mails du site : serveur d\'envoi',
			function () {
				$has = '' !== vsg_smtp_pass();
				echo '<p><label>Serveur SMTP<br><input type="text" class="regular-text" name="vsg_smtp_host" value="' . esc_attr( get_option( 'vsg_smtp_host', '' ) ) . '" placeholder="ssl0.ovh.net"></label></p>';
				echo '<p><label>Mot de passe de l\'adresse d\'expédition<br><input type="password" class="regular-text" name="vsg_smtp_pass" value="" autocomplete="new-password" placeholder="' . ( $has ? 'Enregistré (laisser vide pour le garder)' : '' ) . '"></label></p>';
				echo '<p class="description">Avec un mot de passe, les mails partent par le serveur de la boîte mail (port 465, SSL) : ils arrivent bien mieux qu\'en envoi direct. Sans mot de passe, l\'envoi se fait par le serveur du site.</p>';
				if ( $has ) {
					echo '<p><label><input type="checkbox" name="vsg_smtp_clear" value="1"> Effacer le mot de passe enregistré (retour à l\'envoi par le serveur du site)</label></p>';
				}
				$test = wp_nonce_url( admin_url( 'admin-post.php?action=vsg_mail_test' ), 'vsg_mail_test' );
				echo '<p><a class="button" href="' . esc_url( $test ) . '">Envoyer un mail de test</a> <span class="description">à ' . esc_html( wp_get_current_user()->user_email ) . ' (enregistrez d\'abord vos changements)</span></p>';
				$last = get_transient( 'vsg_mail_last_error' );
				if ( is_array( $last ) && ! empty( $last['msg'] ) ) {
					echo '<p class="description">Dernier échec d\'envoi par la boîte mail, le ' . esc_html( wp_date( 'j/m/Y à H\hi', $last['time'] ) ) . ' : <code>' . esc_html( $last['msg'] ) . '</code>' . ( ! empty( $last['fallback'] ) ? ' Le message est reparti par le serveur du site.' : '' ) . '</p>';
				}
			},
			'general'
		);
	}
);

/* Adresse et nom d'expéditeur. */
add_filter(
	'wp_mail_from',
	function ( $from ) {
		return vsg_mail_from() ? vsg_mail_from() : $from;
	}
);
add_filter(
	'wp_mail_from_name',
	function ( $name ) {
		return vsg_mail_from() ? wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) : $name;
	}
);

/* Envoi par SMTP authentifié quand l'adresse d'expédition et son mot de passe sont réglés. */
add_action(
	'phpmailer_init',
	function ( $mailer ) {
		$from = vsg_mail_from();
		$pass = vsg_smtp_pass();
		if ( ! $from || '' === $pass || ! empty( $GLOBALS['vsg_smtp_off'] ) ) {
			return;
		}
		$host = get_option( 'vsg_smtp_host', '' );
		$mailer->isSMTP();
		$mailer->Host       = $host ? $host : 'ssl0.ovh.net';
		$mailer->Port       = 465;
		$mailer->SMTPSecure = 'ssl';
		$mailer->SMTPAuth   = true;
		$mailer->Username   = $from;
		$mailer->Password   = $pass;
	}
);

/** L'envoi par la boîte mail (SMTP) est-il configuré ? */
function vsg_smtp_on() {
	return vsg_mail_from() && '' !== vsg_smtp_pass();
}

/* On garde la raison du dernier échec pour l'afficher dans Réglages › Général. */
add_action(
	'wp_mail_failed',
	function ( $error ) {
		$GLOBALS['vsg_mail_error'] = $error->get_error_message();
	}
);

/**
 * Envoi d'un mail du site. Si l'envoi par la boîte mail échoue,
 * le message repart aussitôt par le serveur de l'hébergement.
 */
function vsg_send_mail( $to, $subject, $message, $headers = array() ) {
	$GLOBALS['vsg_mail_error'] = '';
	$sent = wp_mail( $to, $subject, $message, $headers );
	if ( ! $sent && vsg_smtp_on() ) {
		$err                     = $GLOBALS['vsg_mail_error'];
		$GLOBALS['vsg_smtp_off'] = true;
		$sent                    = wp_mail( $to, $subject, $message, $headers );
		$GLOBALS['vsg_smtp_off'] = false;
		vsg_mail_error_store( $err, $sent );
	}
	return $sent;
}

/* Bouton « Envoyer un mail de test ». */
add_action(
	'admin_post_vsg_mail_test',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Accès refusé.' );
		}
		check_admin_referer( 'vsg_mail_test' );
		$to                        = wp_get_current_user()->user_email;
		$smtp                      = vsg_smtp_on();
		$GLOBALS['vsg_mail_error'] = '';
		$ok                        = wp_mail( $to, '[Site] Mail de test', "Ceci est un mail de test envoyé depuis Réglages › Général.\n\nMode d'envoi : " . ( $smtp ? 'boîte mail ' . vsg_mail_from() . ' (SMTP)' : 'serveur du site' ) . "\n" );
		if ( $ok ) {
			$msg = array( 'success', 'Mail de test envoyé à ' . $to . ( $smtp ? ' par la boîte mail ' . vsg_mail_from() . '.' : ' par le serveur du site.' ) . ' Vérifiez sa réception (et les spams).' );
		} else {
			$msg = array( 'error', 'Échec de l\'envoi' . ( $smtp ? ' par la boîte mail ' . vsg_mail_from() : ' par le serveur du site' ) . '. Raison : ' . ( $GLOBALS['vsg_mail_error'] ? $GLOBALS['vsg_mail_error'] : 'inconnue' ) );
		}
		set_transient( 'vsg_mail_test_' . get_current_user_id(), $msg, 300 );
		wp_safe_redirect( admin_url( 'options-general.php' ) );
		exit;
	}
);

add_action(
	'admin_notices',
	function () {
		$key = 'vsg_mail_test_' . get_current_user_id();
		$msg = get_transient( $key );
		if ( ! $msg ) {
			return;
		}
		delete_transient( $key );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $msg[0] ), esc_html( $msg[1] ) );
	}
);

/** En-tête Reply-To bien formé : nom entre guillemets, sans caractère qui casserait l'en-tête. */
function vsg_reply_to( $name, $email ) {
	$name = trim( str_replace( array( '"', '\\', "\r", "\n", '<', '>' ), '', (string) $name ) );
	return ( '' !== $name ? '"' . $name . '" ' : '' ) . '<' . $email . '>';
}
