<?php
/**
 * Formulaire de contact, sans extension.
 * Données conservées : aucune. Le message est envoyé par mail à la paroisse.
 */

defined( 'ABSPATH' ) || exit;

/** Motifs proposés dans le formulaire. */
function vsg_contact_objets() {
	return array(
		'pretre'     => 'Rencontrer un prêtre, écoute spirituelle',
		'sacrement'  => 'Demande de sacrement (baptême, mariage…)',
		'certificat' => 'Certificat de baptême ou de confirmation',
		'salle'      => 'Louer une salle paroissiale',
		'autre'      => 'Autre demande',
	);
}

/** Traitement de l'envoi. */
function vsg_contact_handle() {
	$back = vsg_form_submitted( 'contact', 'message', 'envoye', '#contact-form' );
	if ( ! $back ) {
		return;
	}
	$state = &vsg_form_state( 'contact' );
	$v     = array(
		'nom'     => vsg_clip( sanitize_text_field( wp_unslash( $_POST['vsg_nom'] ?? '' ) ), 100 ),
		'email'   => vsg_clip( sanitize_email( wp_unslash( $_POST['vsg_email'] ?? '' ) ), 190 ),
		'message' => vsg_clip( sanitize_textarea_field( wp_unslash( $_POST['vsg_message'] ?? '' ) ), 5000 ),
		'dest'    => absint( $_POST['vsg_dest'] ?? 0 ),
		'objet'   => sanitize_key( $_POST['vsg_objet'] ?? '' ),
		'accord'  => ! empty( $_POST['vsg_accord'] ),
	);

	$state['values'] = $v;
	if ( '' === $v['nom'] ) {
		$state['errors']['nom'] = 'Indiquez votre nom.';
	}
	if ( ! is_email( $v['email'] ) ) {
		$state['errors']['email'] = 'Indiquez une adresse mail valide.';
	}
	if ( strlen( $v['message'] ) < 5 ) {
		$state['errors']['message'] = 'Écrivez votre message.';
	}
	if ( ! $v['accord'] ) {
		$state['errors']['accord'] = 'Cochez cette case pour que nous puissions vous répondre.';
	}
	if ( ! vsg_form_passes( 'contact' ) ) {
		return;
	}

	$to    = vsg_contact_email();
	$label = 'les paroisses';
	if ( $v['dest'] && VSG_TPL_PAROISSE === get_page_template_slug( $v['dest'] ) ) {
		$label = get_the_title( $v['dest'] );
		$email = vsg_meta( $v['dest'], 'email' );
		if ( is_email( $email ) ) {
			$to = $email;
		}
	}
	$objets = vsg_contact_objets();
	$objet  = $objets[ $v['objet'] ] ?? $objets['autre'];
	$sent   = vsg_send_mail(
		$to,
		'[Site] ' . $objet . ' – ' . $v['nom'],
		"Objet : {$objet}\nNom : {$v['nom']}\nMail : {$v['email']}\nDestinataire : {$label}\n\n{$v['message']}\n",
		array( 'Reply-To: ' . vsg_reply_to( $v['nom'], $v['email'] ) )
	);
	if ( vsg_form_mail_failed( 'contact', $sent, $back, 'message', '#contact-form', 'Le message n\'a pas pu être envoyé. Merci d\'appeler l\'accueil paroissial.' ) ) {
		return;
	}
	vsg_form_redirect( $back, 'message', 'envoye', '#contact-form' );
}
add_action( 'template_redirect', 'vsg_contact_handle' );

/**
 * Affiche le formulaire.
 *
 * @param int|null $parish_id Paroisse destinataire, ou null pour laisser le choix.
 */
function vsg_contact_form( $parish_id = null ) {
	$v      = vsg_form_state( 'contact' )['values'];
	$objets = vsg_contact_objets();
	$objet  = $v['objet'] ?? sanitize_key( $_GET['objet'] ?? 'autre' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- motif pré-choisi par un lien du site.
	$objet  = isset( $objets[ $objet ] ) ? $objet : 'autre';
	$msgs   = array(
		'envoye' => array( 'ok', 'Merci, votre message a bien été envoyé. Nous vous répondrons dès que possible.' ),
		'demo'   => array( 'ok', 'Démonstration : le formulaire fonctionne, mais l\'envoi de mails n\'est pas réglé sur cet ordinateur. Une fois le site en ligne, le message arrivera dans la boîte mail de la paroisse.' ),
	);
	ob_start();
	?>
	<div class="contactform" id="contact-form">
		<?php echo vsg_form_notices( 'contact', $msgs, vsg_form_status( 'message' ) ); ?>
		<form method="post" action="#contact-form" novalidate>
			<?php echo vsg_form_hidden( 'contact' ); ?>
			<?php if ( $parish_id ) : ?>
				<input type="hidden" name="vsg_dest" value="<?php echo (int) $parish_id; ?>">
			<?php else : ?>
				<div class="field">
					<label for="vsg_dest">Votre message concerne</label>
					<select id="vsg_dest" name="vsg_dest">
						<?php foreach ( vsg_parishes() as $p ) : ?>
							<option value="<?php echo (int) $p->ID; ?>" <?php selected( $v['dest'] ?? 0, $p->ID ); ?>>Paroisse <?php echo esc_html( get_the_title( $p ) ); ?></option>
						<?php endforeach; ?>
						<option value="0" <?php selected( isset( $v['dest'] ) && 0 === $v['dest'] ); ?>>Les deux paroisses / le site</option>
					</select>
				</div>
			<?php endif; ?>
			<div class="field">
				<label for="vsg_objet">Votre demande</label>
				<select id="vsg_objet" name="vsg_objet">
					<?php foreach ( $objets as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $objet, $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="field-row">
				<div class="field">
					<?php echo vsg_form_label( 'vsg_nom', 'Nom', 'obligatoire' ); ?>
					<input type="text" id="vsg_nom" name="vsg_nom" autocomplete="name" value="<?php echo esc_attr( $v['nom'] ?? '' ); ?>"<?php echo vsg_form_aria( 'contact', 'nom' ); ?>>
					<?php echo vsg_form_error( 'contact', 'nom' ); ?>
				</div>
				<div class="field">
					<?php echo vsg_form_label( 'vsg_email', 'Adresse mail', 'obligatoire' ); ?>
					<input type="email" id="vsg_email" name="vsg_email" autocomplete="email" value="<?php echo esc_attr( $v['email'] ?? '' ); ?>"<?php echo vsg_form_aria( 'contact', 'email' ); ?>>
					<?php echo vsg_form_error( 'contact', 'email' ); ?>
				</div>
			</div>
			<div class="field">
				<?php echo vsg_form_label( 'vsg_message', 'Message', 'obligatoire' ); ?>
				<textarea id="vsg_message" name="vsg_message" rows="6"<?php echo vsg_form_aria( 'contact', 'message' ); ?>><?php echo esc_textarea( $v['message'] ?? '' ); ?></textarea>
				<?php echo vsg_form_error( 'contact', 'message' ); ?>
			</div>
			<?php echo vsg_form_consent( 'contact', 'vsg_accord', 'J\'accepte que mes données servent uniquement à répondre à ma demande.', ! empty( $v['accord'] ) ); ?>
			<button type="submit" class="btn">Envoyer</button>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
