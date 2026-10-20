<?php
/**
 * Intentions de prière : un formulaire, anonyme ou non, envoyé par mail aux prêtres de la paroisse.
 * Rien n'est conservé sur le site.
 */

defined( 'ABSPATH' ) || exit;

/** Adresse qui reçoit les intentions (réglage), sinon l'adresse d'administration. */
function vsg_intentions_email() {
	$e = get_option( 'vsg_intentions_email', '' );
	return is_email( $e ) ? $e : get_option( 'admin_email' );
}

/* Alerte tant qu'aucune adresse dédiée n'est réglée : les intentions partiraient vers l'adresse d'administration. */
add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) || is_email( get_option( 'vsg_intentions_email', '' ) ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Intentions de prière :</strong> aucune adresse dédiée aux prêtres n\'est réglée, elles partent vers l\'adresse d\'administration. <a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">Régler l\'adresse</a></p></div>';
	}
);

/* Réglages : l'adresse qui reçoit les intentions. */
add_action(
	'admin_init',
	function () {
		register_setting(
			'general',
			'vsg_intentions_email',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
			)
		);
		add_settings_field(
			'vsg_intentions_email',
			'Intentions de prière : adresse qui les reçoit',
			function () {
				echo '<input type="email" class="regular-text" name="vsg_intentions_email" value="' . esc_attr( get_option( 'vsg_intentions_email', '' ) ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '"><p class="description">Boîte mail dédiée aux intentions de prière, lue par les prêtres de la paroisse et par eux seuls (les intentions peuvent contenir des informations de santé). Vide = adresse d\'administration du site, déconseillé.</p>';
			},
			'general'
		);
	}
);

/** Traitement de l'envoi. */
function vsg_intention_handle() {
	$back = vsg_form_submitted( 'intention', 'message', 'envoye', '#intention-form' );
	if ( ! $back ) {
		return;
	}
	$state = &vsg_form_state( 'intention' );
	$v     = array(
		'prenom'    => vsg_clip( sanitize_text_field( wp_unslash( $_POST['vsg_prenom'] ?? '' ) ), 60 ),
		'email'     => vsg_clip( sanitize_email( wp_unslash( $_POST['vsg_email'] ?? '' ) ), 190 ),
		'intention' => vsg_clip( sanitize_textarea_field( wp_unslash( $_POST['vsg_intention'] ?? '' ) ), 3000 ),
		'anonyme'   => ! empty( $_POST['vsg_anonyme'] ),
		'accord'    => ! empty( $_POST['vsg_accord'] ),
	);

	$state['values'] = $v;
	if ( mb_strlen( $v['intention'] ) < 5 ) {
		$state['errors']['intention'] = 'Écrivez votre intention.';
	}
	if ( '' !== $v['email'] && ! is_email( $v['email'] ) ) {
		$state['errors']['email'] = 'Cette adresse mail ne semble pas valide.';
	}
	if ( ! $v['accord'] ) {
		$state['errors']['accord'] = 'Cochez cette case pour que votre intention puisse être transmise aux prêtres.';
	}
	if ( ! vsg_form_passes( 'intention' ) ) {
		return;
	}

	$qui     = $v['anonyme'] ? 'Intention anonyme' : ( 'De : ' . ( $v['prenom'] ? $v['prenom'] : 'prénom non indiqué' ) . ( $v['email'] ? ' <' . $v['email'] . '>' : '' ) );
	$body    = "Nouvelle intention de prière déposée sur le site.\nÀ porter dans la prière des prêtres. Elle n'est pas destinée à être lue pendant la messe.\n\n{$qui}\n\n----\n{$v['intention']}\n----\n";
	$headers = array();
	if ( ! $v['anonyme'] && $v['email'] ) {
		$headers[] = 'Reply-To: ' . vsg_reply_to( $v['prenom'] ? $v['prenom'] : 'Intention', $v['email'] );
	}
	$sent = vsg_send_mail( vsg_intentions_email(), '[Site] Intention de prière' . ( $v['anonyme'] ? ' (anonyme)' : '' ), $body, $headers );
	if ( vsg_form_mail_failed( 'intention', $sent, $back, 'message', '#intention-form', 'L\'intention n\'a pas pu être envoyée. Vous pouvez la déposer à l\'accueil ou dans le panier au fond de l\'église.' ) ) {
		return;
	}
	vsg_form_redirect( $back, 'message', 'envoye', '#intention-form' );
}
add_action( 'template_redirect', 'vsg_intention_handle' );

/** Affiche le formulaire. */
function vsg_intention_form() {
	$v    = vsg_form_state( 'intention' )['values'];
	$msgs = array(
		'envoye' => array( 'ok', 'Merci de nous avoir confié votre intention. Les prêtres de la paroisse la porteront dans leur prière de chaque jour.' ),
		'demo'   => array( 'ok', 'Démonstration : le formulaire fonctionne, mais l\'envoi de mails n\'est pas réglé sur cet ordinateur. Une fois le site en ligne, l\'intention arrivera dans la boîte mail des prêtres de la paroisse.' ),
	);
	ob_start();
	?>
	<div class="contactform" id="intention-form">
		<?php echo vsg_form_notices( 'intention', $msgs, vsg_form_status( 'message' ) ); ?>
		<form method="post" action="#intention-form" novalidate>
			<?php echo vsg_form_hidden( 'intention' ); ?>
			<div class="field">
				<?php echo vsg_form_label( 'vsg_intention', 'Votre intention', 'obligatoire' ); ?>
				<textarea id="vsg_intention" name="vsg_intention" rows="6" required placeholder="Pour une personne malade, une famille, une épreuve, une action de grâce…"<?php echo vsg_form_aria( 'intention', 'intention' ); ?>><?php echo esc_textarea( $v['intention'] ?? '' ); ?></textarea>
				<?php echo vsg_form_error( 'intention', 'intention' ); ?>
			</div>
			<div class="field field--check">
				<input type="checkbox" id="vsg_anonyme" name="vsg_anonyme" value="1" <?php checked( ! empty( $v['anonyme'] ) ); ?>>
				<label for="vsg_anonyme">Je souhaite rester anonyme : ni mon prénom ni mon mail ne seront transmis.</label>
			</div>
			<div class="field-row">
				<div class="field">
					<?php echo vsg_form_label( 'vsg_prenom', 'Votre prénom', 'facultatif' ); ?>
					<input type="text" id="vsg_prenom" name="vsg_prenom" value="<?php echo esc_attr( $v['prenom'] ?? '' ); ?>" autocomplete="given-name">
				</div>
				<div class="field">
					<?php echo vsg_form_label( 'vsg_email', 'Votre mail', 'facultatif, si vous souhaitez une réponse' ); ?>
					<input type="email" id="vsg_email" name="vsg_email" value="<?php echo esc_attr( $v['email'] ?? '' ); ?>" autocomplete="email"<?php echo vsg_form_aria( 'intention', 'email' ); ?>>
					<?php echo vsg_form_error( 'intention', 'email' ); ?>
				</div>
			</div>
			<?php echo vsg_form_consent( 'intention', 'vsg_accord_i', 'J\'accepte que mon intention, qui peut concerner ma santé ou ma foi, soit transmise par mail aux prêtres de la paroisse, et à eux seuls.', ! empty( $v['accord'] ) ); ?>
			<p class="muted">Elle n'est ni lue pendant la messe ni conservée sur le site.</p>
			<p><button class="btn" type="submit">Déposer mon intention</button></p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
