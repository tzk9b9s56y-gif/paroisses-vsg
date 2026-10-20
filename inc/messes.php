<?php
/**
 * Demande de messe : intention, date souhaitée, lieu, et offrande.
 *
 * L'offrande se règle en ligne (SumUp, comme les dons : aucune donnée bancaire sur le site)
 * ou au secrétariat. Dans les deux cas la demande part par mail au secrétariat et la personne reçoit
 * un accusé de réception. Rien n'est conservé sur le site, sauf une ligne dans le journal des paiements
 * en ligne (date, lieu, montant, référence) pour le rapprochement avec SumUp.
 */

defined( 'ABSPATH' ) || exit;

/** Montant indicatif de l'offrande de messe (Conférence des évêques de France). */
const VSG_MESSE_OFFRANDE = 18;

/** Lieux proposés : paroisses et chapelles, plus « sans préférence ». */
function vsg_messe_lieux() {
	$out = array( 0 => 'Sans préférence' );
	foreach ( vsg_dons_targets() as $id => $t ) {
		$out[ $id ] = ( 'Chapelle' === $t['kind'] ? 'Chapelle ' : '' ) . $t['label'];
	}
	return $out;
}

/** Traitement de l'envoi. */
function vsg_messe_handle() {
	$back = vsg_form_submitted( 'messe', 'messe', 'secretariat', '#messe-form' );
	if ( ! $back ) {
		return;
	}
	$back   = remove_query_arg( 'ref', $back );
	$state  = &vsg_form_state( 'messe' );
	$lieux  = vsg_messe_lieux();
	$mode   = isset( $_POST['vsg_regler'] ) && 'ligne' === $_POST['vsg_regler'] && vsg_sumup_ready() ? 'ligne' : 'secretariat';
	$amount = vsg_dons_amount( sanitize_text_field( wp_unslash( $_POST['vsg_offrande'] ?? '' ) ) );
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
	if ( $amount < VSG_MESSE_OFFRANDE || $amount > VSG_DON_MAX ) {
		$state['errors']['offrande'] = sprintf( 'L\'offrande est de %d € au minimum.', VSG_MESSE_OFFRANDE );
	}
	if ( ! $v['accord'] ) {
		$state['errors']['accord'] = 'Cochez cette case pour que nous puissions traiter votre demande.';
	}
	if ( ! vsg_form_passes( 'messe' ) ) {
		return;
	}

	$lieu_label = $lieux[ $v['lieu'] ];
	$date_label = $v['date'] ? vsg_date_long( $v['date'] ) . ' ' . substr( $v['date'], 0, 4 ) : 'pas de date précise';
	$montant    = number_format( $amount, 2, ',', ' ' );
	$pay        = array(
		'ref' => 'messe-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ),
		'url' => '',
	);

	if ( 'ligne' === $mode ) {
		$pay = vsg_sumup_checkout(
			'messe',
			$amount,
			'Offrande de messe – ' . $lieu_label,
			add_query_arg( 'messe', 'regle', $back ) . '#messe-form',
			$lieu_label,
			'Messe : '
		);
		if ( ! $pay['url'] ) {
			$state['errors']['form'] = 'Le service de paiement n\'a pas répondu. Vous pouvez envoyer la demande et régler l\'offrande au secrétariat.';
			return;
		}
	}
	$ref = $pay['ref'];

	$regl = 'ligne' === $mode
		? "Offrande : {$montant} € – règlement en ligne (SumUp), référence {$ref}.\nÀ vérifier dans l'espace SumUp avant d'inscrire la messe."
		: "Offrande : {$montant} € – à régler au secrétariat (espèces ou chèque). Référence {$ref}.";
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

	/* Accusé de réception au demandeur. Aucun texte saisi par le visiteur (ni nom, ni intention) : il ne peut pas servir à envoyer des messages à un tiers. */
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$ack  = "Bonjour,\n\nNous avons bien reçu votre demande de messe :\n\n"
		. "Date souhaitée : {$date_label}\nLieu : {$lieu_label}\n"
		. "Offrande : {$montant} €" . ( 'ligne' === $mode ? ' (règlement en ligne)' : ' (à régler au secrétariat)' ) . "\nRéférence : {$ref}\n\n"
		. ( 'ligne' === $mode
			? "Si le paiement a abouti, SumUp vous a envoyé un reçu. Le secrétariat vous confirmera la date de la messe.\n"
			: "Pour finaliser la demande, merci de passer au secrétariat régler l'offrande (espèces ou chèque à l'ordre de la paroisse). Le secrétariat vous confirmera alors la date de la messe.\n" )
		. "\n{$site}\n";
	vsg_send_mail( $v['email'], 'Votre demande de messe – ' . $site, $ack );

	if ( $pay['url'] ) {
		wp_redirect( $pay['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- adresse vérifiée chez SumUp par vsg_sumup_url_ok().
		exit;
	}
	vsg_form_redirect( $back, 'messe', $sent ? 'secretariat' : 'demo', '#messe-form' );
}
add_action( 'template_redirect', 'vsg_messe_handle' );

/** Permanences du secrétariat, depuis la fiche de la première paroisse qui les indique. */
function vsg_messe_permanences() {
	foreach ( vsg_parishes() as $p ) {
		$perm = trim( vsg_meta( $p->ID, 'permanences' ) );
		if ( $perm ) {
			return implode( ' ; ', array_filter( array_map( 'trim', explode( "\n", $perm ) ) ) );
		}
	}
	return '';
}

/** Affiche le formulaire. */
function vsg_messe_form() {
	$v     = wp_parse_args(
		vsg_form_state( 'messe' )['values'],
		array(
			'intention' => '',
			'date'      => '',
			'lieu'      => 0,
			'nom'       => '',
			'email'     => '',
			'tel'       => '',
			'offrande'  => VSG_MESSE_OFFRANDE,
			'accord'    => false,
		)
	);
	$ready = vsg_sumup_ready();
	$perm  = vsg_messe_permanences();
	$msgs  = array(
		'regle'       => array( 'ok', 'Merci. Votre demande est transmise au secrétariat, qui vous confirmera la date de la messe. Si le paiement a abouti, SumUp vous a envoyé un reçu par mail.' ),
		'secretariat' => array( 'ok', 'Merci. Votre demande est transmise au secrétariat. Pour la finaliser, passez régler l\'offrande à l\'accueil paroissial (espèces ou chèque) : le secrétariat vous confirmera alors la date de la messe.' ),
		'demo'        => array( 'ok', 'Démonstration : le formulaire fonctionne, mais l\'envoi de mails n\'est pas réglé sur cet ordinateur.' ),
	);
	ob_start();
	?>
	<div class="contactform" id="messe-form">
		<?php echo vsg_form_notices( 'messe', $msgs, vsg_form_status( 'messe' ) ); ?>
		<form method="post" action="#messe-form" novalidate>
			<?php echo vsg_form_hidden( 'messe' ); ?>

			<div class="field">
				<?php echo vsg_form_label( 'vsg_intention_m', 'Intention de la messe', 'obligatoire' ); ?>
				<textarea id="vsg_intention_m" name="vsg_intention" rows="4" required<?php echo vsg_form_aria( 'messe', 'intention' ); ?> placeholder="Par exemple : pour le repos de l'âme de …, pour les 50 ans de mariage de …, en action de grâce pour …"><?php echo esc_textarea( $v['intention'] ); ?></textarea>
				<?php echo vsg_form_error( 'messe', 'intention' ); ?>
			</div>
			<div class="field-row">
				<div class="field">
					<?php echo vsg_form_label( 'vsg_date_m', 'Date souhaitée', 'facultatif' ); ?>
					<input type="date" id="vsg_date_m" name="vsg_date" value="<?php echo esc_attr( $v['date'] ); ?>" min="<?php echo esc_attr( vsg_today() ); ?>"<?php echo vsg_form_aria( 'messe', 'date' ); ?>>
					<?php echo vsg_form_error( 'messe', 'date' ); ?>
				</div>
				<div class="field">
					<label for="vsg_lieu_m">Lieu</label>
					<select id="vsg_lieu_m" name="vsg_lieu"<?php echo vsg_form_aria( 'messe', 'lieu' ); ?>>
						<?php foreach ( vsg_messe_lieux() as $id => $label ) : ?>
							<option value="<?php echo (int) $id; ?>" <?php selected( (int) $v['lieu'], (int) $id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php echo vsg_form_error( 'messe', 'lieu' ); ?>
				</div>
			</div>
			<div class="field-row">
				<div class="field">
					<?php echo vsg_form_label( 'vsg_nom_m', 'Votre nom', 'obligatoire' ); ?>
					<input type="text" id="vsg_nom_m" name="vsg_nom" value="<?php echo esc_attr( $v['nom'] ); ?>" required autocomplete="name"<?php echo vsg_form_aria( 'messe', 'nom' ); ?>>
					<?php echo vsg_form_error( 'messe', 'nom' ); ?>
				</div>
				<div class="field">
					<?php echo vsg_form_label( 'vsg_email_m', 'Votre mail', 'obligatoire' ); ?>
					<input type="email" id="vsg_email_m" name="vsg_email" value="<?php echo esc_attr( $v['email'] ); ?>" required autocomplete="email"<?php echo vsg_form_aria( 'messe', 'email' ); ?>>
					<?php echo vsg_form_error( 'messe', 'email' ); ?>
				</div>
			</div>
			<div class="field-row">
				<div class="field">
					<?php echo vsg_form_label( 'vsg_tel_m', 'Téléphone', 'facultatif' ); ?>
					<input type="tel" id="vsg_tel_m" name="vsg_tel" value="<?php echo esc_attr( $v['tel'] ); ?>" autocomplete="tel">
				</div>
				<div class="field">
					<?php echo vsg_form_label( 'vsg_offrande_m', 'Offrande', 'obligatoire' ); ?>
					<div class="offrande"><input type="number" id="vsg_offrande_m" name="vsg_offrande" min="<?php echo (int) VSG_MESSE_OFFRANDE; ?>" max="<?php echo (int) VSG_DON_MAX; ?>" step="0.01" required value="<?php echo esc_attr( (string) $v['offrande'] ); ?>"<?php echo vsg_form_aria( 'messe', 'offrande' ); ?>><span class="offrande__eur">€</span></div>
					<?php echo vsg_form_error( 'messe', 'offrande' ); ?>
				</div>
			</div>
			<p class="muted">L'offrande de messe est de <?php echo (int) VSG_MESSE_OFFRANDE; ?> € au minimum (un montant inférieur est refusé), montant indicatif fixé par la Conférence des évêques de France. Vous pouvez donner davantage. En cas de difficulté, parlez-en simplement au secrétariat : personne n'est refusé.</p>
			<?php echo vsg_form_consent( 'messe', 'vsg_accord_m', 'J\'accepte que mes données servent uniquement à traiter cette demande.', (bool) $v['accord'] ); ?>
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
				Au secrétariat : espèces ou chèque à l'ordre de la paroisse<?php echo $perm ? ', aux heures de permanence (' . esc_html( $perm ) . ')' : ''; ?>.
				Rien n'est conservé sur le site : la demande part par mail au secrétariat.
			</p>
		</form>
	</div>
	<?php
	return ob_get_clean();
}
