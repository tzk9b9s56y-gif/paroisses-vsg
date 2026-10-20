<?php
/**
 * Demande de messe : intention, date souhaitée, lieu, et offrande.
 *
 * L'offrande se règle en ligne (SumUp, même mécanisme que les dons : aucune donnée bancaire sur le site)
 * ou au secrétariat. Dans les deux cas la demande part par mail au secrétariat et la personne reçoit
 * un accusé de réception. Rien n'est conservé sur le site, sauf une ligne dans le journal des paiements
 * en ligne (date, lieu, montant, référence) pour le rapprochement avec SumUp.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Montant indicatif de l'offrande de messe (Conférence des évêques de France). */
const VSG_MESSE_OFFRANDE = 18;

$GLOBALS['vsg_messe'] = array(
	'errors' => array(),
	'values' => array(),
);

/** Lieux proposés : paroisses et chapelles, plus « sans préférence ». */
function vsg_messe_lieux() {
	$out = array( 0 => 'Sans préférence' );
	foreach ( vsg_dons_targets() as $id => $t ) {
		$out[ $id ] = ( 'Chapelle' === $t['kind'] ? 'Chapelle ' : '' ) . $t['label'];
	}
	return $out;
}

add_action(
	'template_redirect',
	function () {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['vsg_messe_nonce'] ) ) {
			return;
		}
		$state = &$GLOBALS['vsg_messe'];
		$back  = home_url( wp_unslash( strtok( $_SERVER['REQUEST_URI'] ?? '/', '#' ) ) );
		$back  = remove_query_arg( array( 'messe', 'ref' ), $back );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vsg_messe_nonce'] ) ), 'vsg_messe' ) ) {
			$state['errors']['form'] = 'La page a expiré. Merci de renvoyer le formulaire.';
			return;
		}
		if ( ! empty( $_POST['vsg_site'] ) ) { // champ piège
			wp_safe_redirect( add_query_arg( 'messe', 'secretariat', $back ) . '#messe-form' );
			exit;
		}
		$lieux  = vsg_messe_lieux();
		$mode   = isset( $_POST['vsg_regler'] ) && 'ligne' === $_POST['vsg_regler'] ? 'ligne' : 'secretariat';
		$libre  = str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['vsg_offrande'] ?? '' ) ) );
		$amount = round( (float) $libre, 2 );
		$v      = array(
			'intention' => vsg_clip( sanitize_textarea_field( wp_unslash( $_POST['vsg_intention'] ?? '' ) ), 500 ),
			'date'      => sanitize_text_field( wp_unslash( $_POST['vsg_date'] ?? '' ) ),
			'lieu'      => absint( $_POST['vsg_lieu'] ?? 0 ),
			'nom'       => vsg_clip( sanitize_text_field( wp_unslash( $_POST['vsg_nom'] ?? '' ) ), 100 ),
			'email'     => vsg_clip( sanitize_email( wp_unslash( $_POST['vsg_email'] ?? '' ) ), 190 ),
			'tel'       => vsg_clip( sanitize_text_field( wp_unslash( $_POST['vsg_tel'] ?? '' ) ), 30 ),
			'offrande'  => $amount,
			'accord'    => ! empty( $_POST['vsg_accord'] ),
		);
		$state['values'] = $v;
		if ( strlen( $v['intention'] ) < 3 ) {
			$state['errors']['intention'] = 'Indiquez l\'intention de la messe (pour qui, à quelle occasion).';
		}
		if ( '' !== $v['date'] && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v['date'] ) ) {
			$state['errors']['date'] = 'Date illisible.';
		}
		if ( ! isset( $lieux[ $v['lieu'] ] ) ) {
			$state['errors']['lieu'] = 'Choisissez un lieu.';
		}
		if ( '' === $v['nom'] ) {
			$state['errors']['nom'] = 'Indiquez votre nom.';
		}
		if ( ! is_email( $v['email'] ) ) {
			$state['errors']['email'] = 'Indiquez une adresse mail valide, pour l\'accusé de réception.';
		}
		if ( $amount < VSG_MESSE_OFFRANDE || $amount > 5000 ) {
			$state['errors']['offrande'] = sprintf( 'L\'offrande est de %d € au minimum.', VSG_MESSE_OFFRANDE );
		}
		if ( ! $v['accord'] ) {
			$state['errors']['accord'] = 'Cochez cette case pour que nous puissions traiter votre demande.';
		}
		if ( 'ligne' === $mode && ! vsg_dons_ready() ) {
			$mode = 'secretariat';
		}
		if ( ! $state['errors'] ) {
			$guard = vsg_form_guard( 'messe' );
			if ( $guard ) {
				$state['errors']['form'] = $guard;
			}
		}
		if ( $state['errors'] ) {
			return;
		}

		$lieu_label = $lieux[ $v['lieu'] ];
		$date_label = $v['date'] ? vsg_date_long( $v['date'] ) . ' ' . substr( $v['date'], 0, 4 ) : 'pas de date précise';
		$ref        = 'messe-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false );
		$pay_url    = '';

		if ( 'ligne' === $mode ) {
			$s = vsg_dons_settings();
			$r = vsg_dons_request(
				'POST',
				'/checkouts',
				array(
					'checkout_reference' => $ref,
					'amount'             => $amount,
					'currency'           => 'EUR',
					'merchant_code'      => $s['merchant'],
					'description'        => 'Offrande de messe – ' . $lieu_label,
					'redirect_url'       => add_query_arg( array( 'messe' => 'regle', 'ref' => $ref ), $back ) . '#messe-form',
					'hosted_checkout'    => array( 'enabled' => true ),
				)
			);
			$pay_url = $r['data']['hosted_checkout_url'] ?? '';
			if ( $r['error'] || ! in_array( $r['code'], array( 200, 201 ), true ) || ! $pay_url || ! vsg_sumup_url_ok( $pay_url ) ) {
				vsg_dons_log( array( 'date' => current_time( 'd/m/Y H:i' ), 'paroisse' => $lieu_label, 'montant' => $amount, 'ref' => $ref, 'statut' => 'Messe : erreur SumUp (' . ( $r['error'] ? $r['error'] : $r['code'] ) . ')' ) );
				$state['errors']['form'] = 'Le service de paiement n\'a pas répondu. Vous pouvez envoyer la demande et régler l\'offrande au secrétariat.';
				return;
			}
			vsg_dons_log( array( 'date' => current_time( 'd/m/Y H:i' ), 'paroisse' => $lieu_label, 'montant' => $amount, 'ref' => $ref, 'statut' => 'Messe : envoyé vers SumUp' ) );
		}

		$regl = 'ligne' === $mode
			? sprintf( "Offrande : %s € – règlement en ligne (SumUp), référence %s.\nÀ vérifier dans l'espace SumUp avant d'inscrire la messe.", number_format( $amount, 2, ',', ' ' ), $ref )
			: sprintf( "Offrande : %s € – à régler au secrétariat (espèces ou chèque). Référence %s.", number_format( $amount, 2, ',', ' ' ), $ref );
		$body = "Demande de messe reçue par le site.\n\n"
			. "Intention : {$v['intention']}\n"
			. "Date souhaitée : {$date_label}\n"
			. "Lieu : {$lieu_label}\n\n"
			. "Demandeur : {$v['nom']}\nMail : {$v['email']}\n" . ( $v['tel'] ? "Téléphone : {$v['tel']}\n" : '' ) . "\n"
			. $regl . "\n";
		$sent = vsg_send_mail(
			vsg_contact_email(),
			'[Site] Demande de messe – ' . $v['nom'] . ( 'ligne' === $mode ? ' (réglée en ligne)' : ' (à régler)' ),
			$body,
			array( 'Reply-To: ' . vsg_reply_to( $v['nom'], $v['email'] ) )
		);
		if ( ! $sent && 'local' !== wp_get_environment_type() ) {
			$state['errors']['form'] = 'La demande n\'a pas pu être envoyée. Merci d\'appeler l\'accueil paroissial.';
			return;
		}
		/* Accusé de réception au demandeur. */
		/* Aucun texte saisi par le visiteur dans ce mail (ni nom, ni intention) : il ne peut pas servir à envoyer des messages à un tiers. */
		$ack = "Bonjour,\n\nNous avons bien reçu votre demande de messe :\n\n"
			. "Date souhaitée : {$date_label}\nLieu : {$lieu_label}\n"
			. "Offrande : " . number_format( $amount, 2, ',', ' ' ) . " €" . ( 'ligne' === $mode ? ' (règlement en ligne)' : ' (à régler au secrétariat)' ) . "\nRéférence : {$ref}\n\n"
			. ( 'ligne' === $mode
				? "Si le paiement a abouti, SumUp vous a envoyé un reçu. Le secrétariat vous confirmera la date de la messe.\n"
				: "Pour finaliser la demande, merci de passer au secrétariat régler l'offrande (espèces ou chèque à l'ordre de la paroisse). Le secrétariat vous confirmera alors la date de la messe.\n" )
			. "\n" . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . "\n";
		vsg_send_mail( $v['email'], 'Votre demande de messe – ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $ack );

		if ( $pay_url ) {
			wp_redirect( $pay_url ); // phpcs:ignore WordPress.Security.SafeRedirect -- domaine SumUp.
			exit;
		}
		wp_safe_redirect( add_query_arg( 'messe', $sent ? 'secretariat' : 'demo', $back ) . '#messe-form' );
		exit;
	}
);

/** Affiche le formulaire. */
function vsg_messe_form() {
	$state = $GLOBALS['vsg_messe'];
	$e     = $state['errors'];
	$v     = wp_parse_args( $state['values'], array( 'intention' => '', 'date' => '', 'lieu' => 0, 'nom' => '', 'email' => '', 'tel' => '', 'offrande' => VSG_MESSE_OFFRANDE, 'accord' => false ) );
	$done  = isset( $_GET['messe'] ) ? sanitize_key( $_GET['messe'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$ready = vsg_dons_ready();
	$err   = function ( $k ) use ( $e ) {
		return isset( $e[ $k ] ) ? '<span class="field__error" id="err-m-' . esc_attr( $k ) . '">' . esc_html( $e[ $k ] ) . '</span>' : '';
	};
	$inv   = function ( $k ) use ( $e ) {
		return isset( $e[ $k ] ) ? ' aria-invalid="true" aria-describedby="err-m-' . esc_attr( $k ) . '"' : '';
	};
	/* Permanences du secrétariat, depuis la fiche de la première paroisse. */
	$perm = '';
	foreach ( vsg_parishes() as $p ) {
		$perm = trim( vsg_meta( $p->ID, 'permanences' ) );
		if ( $perm ) {
			break;
		}
	}
	ob_start();
	?>
	<div class="contactform" id="messe-form">
		<?php if ( 'regle' === $done ) : ?>
			<p class="notice notice--ok" role="status">Merci. Votre demande est transmise au secrétariat, qui vous confirmera la date de la messe. Si le paiement a abouti, SumUp vous a envoyé un reçu par mail.</p>
		<?php elseif ( 'secretariat' === $done ) : ?>
			<p class="notice notice--ok" role="status">Merci. Votre demande est transmise au secrétariat. Pour la finaliser, passez régler l'offrande à l'accueil paroissial (espèces ou chèque) : le secrétariat vous confirmera alors la date de la messe.</p>
		<?php elseif ( 'demo' === $done ) : ?>
			<p class="notice notice--ok" role="status">Démonstration : le formulaire fonctionne, mais l'envoi de mails n'est pas réglé sur cet ordinateur.</p>
		<?php endif; ?>
		<?php if ( ! empty( $e['form'] ) ) : ?>
			<p class="notice notice--err" role="alert"><?php echo esc_html( $e['form'] ); ?></p>
		<?php elseif ( $e ) : ?>
			<p class="notice notice--err" role="alert">Le formulaire contient des erreurs. Merci de les corriger.</p>
		<?php endif; ?>
		<form method="post" action="#messe-form" novalidate>
			<?php wp_nonce_field( 'vsg_messe', 'vsg_messe_nonce' ); ?>
			<?php echo vsg_form_timer_field(); // phpcs:ignore ?>
			<p class="field field--hp" aria-hidden="true"><label for="vsg_site_m">Site web</label><input type="text" id="vsg_site_m" name="vsg_site" tabindex="-1" autocomplete="off"></p>

			<div class="field">
				<label for="vsg_intention_m">Intention de la messe <span class="muted">(obligatoire)</span></label>
				<textarea id="vsg_intention_m" name="vsg_intention" rows="4" required<?php echo $inv( 'intention' ); // phpcs:ignore ?> placeholder="Par exemple : pour le repos de l'âme de …, pour les 50 ans de mariage de …, en action de grâce pour …"><?php echo esc_textarea( $v['intention'] ); ?></textarea>
				<?php echo $err( 'intention' ); // phpcs:ignore ?>
			</div>
			<div class="field-row">
				<div class="field">
					<label for="vsg_date_m">Date souhaitée <span class="muted">(facultatif)</span></label>
					<input type="date" id="vsg_date_m" name="vsg_date" value="<?php echo esc_attr( $v['date'] ); ?>" min="<?php echo esc_attr( vsg_today() ); ?>"<?php echo $inv( 'date' ); // phpcs:ignore ?>>
					<?php echo $err( 'date' ); // phpcs:ignore ?>
				</div>
				<div class="field">
					<label for="vsg_lieu_m">Lieu</label>
					<select id="vsg_lieu_m" name="vsg_lieu"<?php echo $inv( 'lieu' ); // phpcs:ignore ?>>
						<?php foreach ( vsg_messe_lieux() as $id => $label ) : ?>
							<option value="<?php echo (int) $id; ?>" <?php selected( (int) $v['lieu'], (int) $id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php echo $err( 'lieu' ); // phpcs:ignore ?>
				</div>
			</div>
			<div class="field-row">
				<div class="field">
					<label for="vsg_nom_m">Votre nom <span class="muted">(obligatoire)</span></label>
					<input type="text" id="vsg_nom_m" name="vsg_nom" value="<?php echo esc_attr( $v['nom'] ); ?>" required autocomplete="name"<?php echo $inv( 'nom' ); // phpcs:ignore ?>>
					<?php echo $err( 'nom' ); // phpcs:ignore ?>
				</div>
				<div class="field">
					<label for="vsg_email_m">Votre mail <span class="muted">(obligatoire)</span></label>
					<input type="email" id="vsg_email_m" name="vsg_email" value="<?php echo esc_attr( $v['email'] ); ?>" required autocomplete="email"<?php echo $inv( 'email' ); // phpcs:ignore ?>>
					<?php echo $err( 'email' ); // phpcs:ignore ?>
				</div>
			</div>
			<div class="field-row">
				<div class="field">
					<label for="vsg_tel_m">Téléphone <span class="muted">(facultatif)</span></label>
					<input type="tel" id="vsg_tel_m" name="vsg_tel" value="<?php echo esc_attr( $v['tel'] ); ?>" autocomplete="tel">
				</div>
				<div class="field">
					<label for="vsg_offrande_m">Offrande <span class="muted">(obligatoire)</span></label>
					<div class="offrande"><input type="number" id="vsg_offrande_m" name="vsg_offrande" min="<?php echo (int) VSG_MESSE_OFFRANDE; ?>" max="5000" step="0.01" required value="<?php echo esc_attr( (string) $v['offrande'] ); ?>"<?php echo $inv( 'offrande' ); // phpcs:ignore ?>><span class="offrande__eur">€</span></div>
					<?php echo $err( 'offrande' ); // phpcs:ignore ?>
				</div>
			</div>
			<p class="muted">L'offrande de messe est de <?php echo (int) VSG_MESSE_OFFRANDE; ?> € au minimum (un montant inférieur est refusé), montant indicatif fixé par la Conférence des évêques de France. Vous pouvez donner davantage. En cas de difficulté, parlez-en simplement au secrétariat : personne n'est refusé.</p>
			<div class="field field--check">
				<input type="checkbox" id="vsg_accord_m" name="vsg_accord" value="1" <?php checked( $v['accord'] ); ?><?php echo $inv( 'accord' ); // phpcs:ignore ?>>
				<label for="vsg_accord_m">J'accepte que mes données servent uniquement à traiter cette demande. <a href="<?php echo esc_url( vsg_page_url( 'mentions-legales' ) ); ?>">En savoir plus</a></label>
				<?php echo $err( 'accord' ); // phpcs:ignore ?>
			</div>
			<div class="btnrow">
				<?php if ( $ready ) : ?>
					<button class="btn" type="submit" name="vsg_regler" value="ligne">Régler l'offrande en ligne</button>
					<button class="btn btn--ghost" type="submit" name="vsg_regler" value="secretariat">Je réglerai au secrétariat</button>
				<?php else : ?>
					<button class="btn" type="submit" name="vsg_regler" value="secretariat">Envoyer la demande</button>
				<?php endif; ?>
			</div>
			<p class="muted">
				<?php if ( $ready ) : ?>Le paiement en ligne se fait sur la page sécurisée de SumUp (carte, Apple Pay, Google Pay) ; aucune donnée bancaire ne passe par ce site. <?php endif; ?>
				Au secrétariat : espèces ou chèque à l'ordre de la paroisse<?php echo $perm ? ', aux heures de permanence (' . esc_html( implode( ' ; ', array_filter( array_map( 'trim', explode( "\n", $perm ) ) ) ) ) . ')' : ''; ?>.
				Rien n'est conservé sur le site : la demande part par mail au secrétariat.
			</p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
