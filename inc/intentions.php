<?php
/**
 * Intentions de prière : un formulaire, anonyme ou non, envoyé par mail aux prêtres de la paroisse.
 * Rien n'est conservé sur le site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['vsg_intention'] = array( 'errors' => array(), 'values' => array() );

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
		register_setting( 'general', 'vsg_intentions_email', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ) );
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

add_action(
	'template_redirect',
	function () {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['vsg_intention_nonce'] ) ) {
			return;
		}
		$state = &$GLOBALS['vsg_intention'];
		$back  = home_url( wp_unslash( strtok( $_SERVER['REQUEST_URI'] ?? '/', '#' ) ) );
		$back  = remove_query_arg( 'message', $back );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vsg_intention_nonce'] ) ), 'vsg_intention' ) ) {
			$state['errors']['form'] = 'La page a expiré. Merci de renvoyer le formulaire.';
			return;
		}
		if ( ! empty( $_POST['vsg_site'] ) ) {
			wp_safe_redirect( add_query_arg( 'message', 'envoye', $back ) . '#intention-form' );
			exit;
		}
		$v = array(
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
		if ( ! $state['errors'] ) {
			$guard = vsg_form_guard( 'intention' );
			if ( $guard ) {
				$state['errors']['form'] = $guard;
			}
		}
		if ( $state['errors'] ) {
			return;
		}
		$qui  = $v['anonyme'] ? 'Intention anonyme' : ( 'De : ' . ( $v['prenom'] ? $v['prenom'] : 'prénom non indiqué' ) . ( $v['email'] ? ' <' . $v['email'] . '>' : '' ) );
		$body = "Nouvelle intention de prière déposée sur le site.\nÀ porter dans la prière des prêtres. Elle n'est pas destinée à être lue pendant la messe.\n\n{$qui}\n" . "\n----\n{$v['intention']}\n----\n";
		$headers = array();
		if ( ! $v['anonyme'] && $v['email'] ) {
			$headers[] = 'Reply-To: ' . vsg_reply_to( $v['prenom'] ? $v['prenom'] : 'Intention', $v['email'] );
		}
		$sent = vsg_send_mail( vsg_intentions_email(), '[Site] Intention de prière' . ( $v['anonyme'] ? ' (anonyme)' : '' ), $body, $headers );
		if ( ! $sent && 'local' === wp_get_environment_type() ) {
			wp_safe_redirect( add_query_arg( 'message', 'demo', $back ) . '#intention-form' );
			exit;
		}
		if ( ! $sent ) {
			$state['errors']['form'] = 'L\'intention n\'a pas pu être envoyée. Vous pouvez la déposer à l\'accueil ou dans le panier au fond de l\'église.';
			return;
		}
		wp_safe_redirect( add_query_arg( 'message', 'envoye', $back ) . '#intention-form' );
		exit;
	}
);

function vsg_intention_form() {
	$state = $GLOBALS['vsg_intention'];
	$e     = $state['errors'];
	$v     = $state['values'];
	$msg   = isset( $_GET['message'] ) ? sanitize_key( $_GET['message'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$err   = function ( $k ) use ( $e ) {
		return isset( $e[ $k ] ) ? '<span class="field__error" id="err-' . esc_attr( $k ) . '">' . esc_html( $e[ $k ] ) . '</span>' : '';
	};
	$aria  = function ( $k ) use ( $e ) {
		return isset( $e[ $k ] ) ? ' aria-invalid="true" aria-describedby="err-' . esc_attr( $k ) . '"' : '';
	};
	ob_start();
	?>
	<div class="contactform" id="intention-form">
		<?php if ( 'envoye' === $msg ) : ?>
			<p class="notice notice--ok" role="status">Merci de nous avoir confié votre intention. Les prêtres de la paroisse la porteront dans leur prière de chaque jour.</p>
		<?php elseif ( 'demo' === $msg ) : ?>
			<p class="notice notice--ok" role="status">Démonstration : le formulaire fonctionne, mais l'envoi de mails n'est pas réglé sur cet ordinateur. Une fois le site en ligne, l'intention arrivera dans la boîte mail des prêtres de la paroisse.</p>
		<?php endif; ?>
		<?php if ( ! empty( $e['form'] ) ) : ?>
			<p class="notice notice--err" role="alert"><?php echo esc_html( $e['form'] ); ?></p>
		<?php elseif ( $e ) : ?>
			<p class="notice notice--err" role="alert">Le formulaire contient des erreurs. Merci de les corriger.</p>
		<?php endif; ?>
		<form method="post" action="#intention-form" novalidate>
			<?php wp_nonce_field( 'vsg_intention', 'vsg_intention_nonce' ); ?>
			<?php echo vsg_form_timer_field(); // phpcs:ignore ?>
			<p class="field field--hp" aria-hidden="true"><label for="vsg_site_i">Site web</label><input type="text" id="vsg_site_i" name="vsg_site" tabindex="-1" autocomplete="off"></p>
			<div class="field">
				<label for="vsg_intention">Votre intention <span class="muted">(obligatoire)</span></label>
				<textarea id="vsg_intention" name="vsg_intention" rows="6" required placeholder="Pour une personne malade, une famille, une épreuve, une action de grâce…"<?php echo $aria( 'intention' ); // phpcs:ignore ?>><?php echo esc_textarea( $v['intention'] ?? '' ); ?></textarea>
				<?php echo $err( 'intention' ); // phpcs:ignore ?>
			</div>
			<div class="field field--check">
				<input type="checkbox" id="vsg_anonyme" name="vsg_anonyme" value="1" <?php checked( ! empty( $v['anonyme'] ) ); ?>>
				<label for="vsg_anonyme">Je souhaite rester anonyme : ni mon prénom ni mon mail ne seront transmis.</label>
			</div>
			<div class="field-row">
				<div class="field">
					<label for="vsg_prenom">Votre prénom <span class="muted">(facultatif)</span></label>
					<input type="text" id="vsg_prenom" name="vsg_prenom" value="<?php echo esc_attr( $v['prenom'] ?? '' ); ?>" autocomplete="given-name">
				</div>
				<div class="field">
					<label for="vsg_email">Votre mail <span class="muted">(facultatif, si vous souhaitez une réponse)</span></label>
					<input type="email" id="vsg_email" name="vsg_email" value="<?php echo esc_attr( $v['email'] ?? '' ); ?>" autocomplete="email"<?php echo $aria( 'email' ); // phpcs:ignore ?>>
					<?php echo $err( 'email' ); // phpcs:ignore ?>
				</div>
			</div>
			<div class="field field--check">
				<input type="checkbox" id="vsg_accord_i" name="vsg_accord" value="1" <?php checked( ! empty( $v['accord'] ) ); ?><?php echo $aria( 'accord' ); // phpcs:ignore ?>>
				<label for="vsg_accord_i">J'accepte que mon intention, qui peut concerner ma santé ou ma foi, soit transmise par mail aux prêtres de la paroisse, et à eux seuls. <a href="<?php echo esc_url( vsg_page_url( 'mentions-legales' ) ); ?>">En savoir plus</a></label>
				<?php echo $err( 'accord' ); // phpcs:ignore ?>
			</div>
			<p class="muted">Elle n'est ni lue pendant la messe ni conservée sur le site.</p>
			<p><button class="btn" type="submit">Déposer mon intention</button></p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
