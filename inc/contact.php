<?php
/**
 * Formulaire de contact minimal, sans extension.
 * Données conservées : aucune. Le message est envoyé par mail à la paroisse.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['vsg_contact'] = array(
	'errors' => array(),
	'values' => array(),
);

add_action(
	'template_redirect',
	function () {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['vsg_contact_nonce'] ) ) {
			return;
		}
		$state = &$GLOBALS['vsg_contact'];
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vsg_contact_nonce'] ) ), 'vsg_contact' ) ) {
			$state['errors']['form'] = 'La page a expiré. Merci de renvoyer le formulaire.';
			return;
		}
		// Champ piège : rempli uniquement par les robots.
		if ( ! empty( $_POST['vsg_site'] ) ) {
			wp_safe_redirect( add_query_arg( 'message', 'envoye', home_url( wp_unslash( strtok( $_SERVER['REQUEST_URI'] ?? '/', '#' ) ) ) ) );
			exit;
		}
		$v = array(
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
		if ( ! $state['errors'] ) {
			$guard = vsg_form_guard( 'contact' );
			if ( $guard ) {
				$state['errors']['form'] = $guard;
			}
		}
		if ( $state['errors'] ) {
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
		if ( ! $sent && 'local' === wp_get_environment_type() ) {
			$back = home_url( wp_unslash( strtok( $_SERVER['REQUEST_URI'] ?? '/', '#' ) ) );
			wp_safe_redirect( add_query_arg( 'message', 'demo', remove_query_arg( 'message', $back ) ) . '#contact-form' );
			exit;
		}
		if ( ! $sent ) {
			$state['errors']['form'] = 'Le message n\'a pas pu être envoyé. Merci d\'appeler l\'accueil paroissial.';
			return;
		}
		$back = home_url( wp_unslash( strtok( $_SERVER['REQUEST_URI'] ?? '/', '#' ) ) );
		wp_safe_redirect( add_query_arg( 'message', 'envoye', remove_query_arg( 'message', $back ) ) . '#contact-form' );
		exit;
	}
);

/** Motifs proposés dans le formulaire. */
function vsg_contact_objets() {
	return array(
		'pretre'    => 'Rencontrer un prêtre, écoute spirituelle',
		'sacrement' => 'Demande de sacrement (baptême, mariage…)',
		'certificat'=> 'Certificat de baptême ou de confirmation',
		'salle'     => 'Louer une salle paroissiale',
		'autre'     => 'Autre demande',
	);
}

/**
 * Affiche le formulaire.
 *
 * @param int|null $parish_id Paroisse destinataire, ou null pour laisser le choix.
 */
function vsg_contact_form( $parish_id = null ) {
	$state  = $GLOBALS['vsg_contact'];
	$e      = $state['errors'];
	$v      = $state['values'];
	$done   = isset( $_GET['message'] ) && 'envoye' === $_GET['message']; // phpcs:ignore WordPress.Security.NonceVerification
	$objets = vsg_contact_objets();
	$objet  = $v['objet'] ?? sanitize_key( $_GET['objet'] ?? 'autre' ); // phpcs:ignore WordPress.Security.NonceVerification
	$objet  = isset( $objets[ $objet ] ) ? $objet : 'autre';
	$err    = function ( $k ) use ( $e ) {
		return isset( $e[ $k ] ) ? '<span class="field__error" id="err-' . esc_attr( $k ) . '">' . esc_html( $e[ $k ] ) . '</span>' : '';
	};
	$aria   = function ( $k ) use ( $e ) {
		return isset( $e[ $k ] ) ? ' aria-invalid="true" aria-describedby="err-' . esc_attr( $k ) . '"' : '';
	};
	ob_start();
	?>
	<div class="contactform" id="contact-form">
		<?php if ( $done ) : ?>
			<p class="notice notice--ok" role="status">Merci, votre message a bien été envoyé. Nous vous répondrons dès que possible.</p>
		<?php endif; ?>
		<?php if ( isset( $_GET['message'] ) && 'demo' === $_GET['message'] ) : // phpcs:ignore ?>
			<p class="notice notice--ok" role="status">Démonstration : le formulaire fonctionne, mais l'envoi de mails n'est pas réglé sur cet ordinateur. Une fois le site en ligne, le message arrivera dans la boîte mail de la paroisse.</p>
		<?php endif; ?>
		<?php if ( ! empty( $e['form'] ) ) : ?>
			<p class="notice notice--err" role="alert"><?php echo esc_html( $e['form'] ); ?></p>
		<?php elseif ( $e ) : ?>
			<p class="notice notice--err" role="alert">Le formulaire contient des erreurs. Merci de les corriger.</p>
		<?php endif; ?>
		<form method="post" action="#contact-form" novalidate>
			<?php wp_nonce_field( 'vsg_contact', 'vsg_contact_nonce' ); ?>
			<?php echo vsg_form_timer_field(); // phpcs:ignore ?>
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
					<label for="vsg_nom">Nom <span class="muted">(obligatoire)</span></label>
					<input type="text" id="vsg_nom" name="vsg_nom" autocomplete="name" value="<?php echo esc_attr( $v['nom'] ?? '' ); ?>"<?php echo $aria( 'nom' ); // phpcs:ignore ?>>
					<?php echo $err( 'nom' ); // phpcs:ignore ?>
				</div>
				<div class="field">
					<label for="vsg_email">Adresse mail <span class="muted">(obligatoire)</span></label>
					<input type="email" id="vsg_email" name="vsg_email" autocomplete="email" value="<?php echo esc_attr( $v['email'] ?? '' ); ?>"<?php echo $aria( 'email' ); // phpcs:ignore ?>>
					<?php echo $err( 'email' ); // phpcs:ignore ?>
				</div>
			</div>
			<div class="field">
				<label for="vsg_message">Message <span class="muted">(obligatoire)</span></label>
				<textarea id="vsg_message" name="vsg_message" rows="6"<?php echo $aria( 'message' ); // phpcs:ignore ?>><?php echo esc_textarea( $v['message'] ?? '' ); ?></textarea>
				<?php echo $err( 'message' ); // phpcs:ignore ?>
			</div>
			<div class="field field--hp" aria-hidden="true">
				<label for="vsg_site">Ne pas remplir</label>
				<input type="text" id="vsg_site" name="vsg_site" tabindex="-1" autocomplete="off">
			</div>
			<div class="field field--check">
				<input type="checkbox" id="vsg_accord" name="vsg_accord" value="1" <?php checked( ! empty( $v['accord'] ) ); ?><?php echo $aria( 'accord' ); // phpcs:ignore ?>>
				<label for="vsg_accord">J'accepte que mes données servent uniquement à répondre à ma demande. <a href="<?php echo esc_url( vsg_page_url( 'mentions-legales' ) ); ?>">En savoir plus</a></label>
				<?php echo $err( 'accord' ); // phpcs:ignore ?>
			</div>
			<button type="submit" class="btn">Envoyer</button>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
