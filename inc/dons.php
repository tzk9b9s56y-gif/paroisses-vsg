<?php
/**
 * Dons à la paroisse : réglages (Réglages › Dons), formulaire du site (shortcode [vsg_don])
 * et traitement de l'envoi. Le paiement lui-même passe par SumUp (inc/sumup.php) ;
 * chèque et virement sont affichés avec les coordonnées réglées.
 */

defined( 'ABSPATH' ) || exit;

/* Don minimum : 10 € (les petits montants servent surtout à tester des cartes volées). */
const VSG_DON_MIN = 10;
const VSG_DON_MAX = 5000;

/** Montants proposés. */
function vsg_dons_amounts() {
	return array( 10, 20, 50, 100, 200 );
}

function vsg_dons_defaults() {
	return array(
		'merchant'  => '',
		'why'       => "Entretenir et chauffer nos églises ; Les bâtiments appartiennent aux communes, mais les réparations courantes, le chauffage, l'électricité et l'assurance restent à la charge de la paroisse.\nAccompagner les familles ; Catéchisme, aumônerie, préparation aux sacrements : les livrets, les sorties et le matériel ont un coût.\nAider ceux qui frappent à la porte ; Colis, aides d'urgence, accueil des personnes isolées, en lien avec les associations de la ville.\nFaire vivre la liturgie ; Fleurs, cierges, hosties, livres de chant, instruments : tout ce qui rend les célébrations belles et priantes.",
		'ordre'     => '',
		'adresse'   => '',
		'titulaire' => '',
		'iban'      => '',
		'bic'       => '',
	);
}

/** Réglages des dons (sans la clé API, qui est rangée à part : voir vsg_sumup_key). */
function vsg_dons_settings() {
	$o = get_option( 'vsg_dons', array() );
	return wp_parse_args( is_array( $o ) ? $o : array(), vsg_dons_defaults() );
}

/** 0.9.25 : clé SumUp sortie du réglage général, secrets sans chargement automatique. */
function vsg_secrets_migrate() {
	$o = get_option( 'vsg_dons', array() );
	if ( is_array( $o ) && ! empty( $o['api_key'] ) ) {
		update_option( 'vsg_dons_key', $o['api_key'], false );
		unset( $o['api_key'] );
		update_option( 'vsg_dons', $o );
	}
	vsg_secrets_no_autoload();
}

/** Lieux auxquels on peut donner : paroisses et chapelles, avec la paroisse de rattachement. */
function vsg_dons_targets() {
	$out = array();
	foreach ( vsg_parishes() as $p ) {
		$out[ $p->ID ] = array(
			'post'   => $p,
			'parish' => $p,
			'label'  => get_the_title( $p ),
			'kind'   => 'Paroisse',
		);
		foreach ( vsg_chapels( $p->ID ) as $c ) {
			$out[ $c->ID ] = array(
				'post'   => $c,
				'parish' => $p,
				'label'  => get_the_title( $c ),
				'kind'   => 'Chapelle',
			);
		}
	}
	return $out;
}

/** Montant saisi (virgule acceptée), arrondi au centime. */
function vsg_dons_amount( $raw ) {
	return round( (float) str_replace( ',', '.', trim( (string) $raw ) ), 2 );
}

/** IBAN affiché par groupes de 4. */
function vsg_dons_iban( $iban ) {
	return trim( chunk_split( $iban, 4, ' ' ) );
}

/* ---------- Réglages ---------- */

add_action(
	'admin_menu',
	function () {
		add_options_page( 'Dons en ligne', 'Dons', 'manage_options', 'vsg-dons', 'vsg_dons_admin_page' );
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'vsg_dons',
			'vsg_dons',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'vsg_dons_sanitize',
			)
		);
	}
);

function vsg_dons_sanitize( $in ) {
	$in  = is_array( $in ) ? $in : array();
	$out = array(
		'merchant'  => strtoupper( trim( sanitize_text_field( $in['merchant'] ?? '' ) ) ),
		'why'       => sanitize_textarea_field( $in['why'] ?? '' ),
		'ordre'     => sanitize_text_field( $in['ordre'] ?? '' ),
		'adresse'   => sanitize_textarea_field( $in['adresse'] ?? '' ),
		'titulaire' => sanitize_text_field( $in['titulaire'] ?? '' ),
		'iban'      => strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $in['iban'] ?? '' ) ),
		'bic'       => strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $in['bic'] ?? '' ) ),
	);
	/* La clé est rangée à part. Champ laissé vide = on garde la clé déjà enregistrée (elle n'est jamais réaffichée). */
	$key = trim( sanitize_text_field( $in['api_key'] ?? '' ) );
	if ( ! empty( $in['clear_key'] ) ) {
		delete_option( 'vsg_dons_key' );
	} elseif ( '' !== $key ) {
		update_option( 'vsg_dons_key', $key, false );
	}
	return $out;
}

function vsg_dons_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s       = vsg_dons_settings();
	$has_key = '' !== vsg_sumup_key();
	$test    = null;
	if ( isset( $_POST['vsg_dons_test'] ) && check_admin_referer( 'vsg_dons_test' ) ) {
		$test = vsg_sumup_test();
	}
	$p0 = vsg_parishes() ? vsg_parishes()[0] : null;
	?>
	<div class="wrap">
		<h1>Dons en ligne</h1>
		<p>Le paiement est assuré par <strong>SumUp</strong> : le site crée un paiement du montant choisi, la personne paie sur la page sécurisée de SumUp (carte, Apple Pay, Google Pay) et l'argent arrive sur le compte SumUp de la paroisse. Aucune donnée bancaire ne passe par ce site.</p>
		<?php if ( null !== $test ) : ?>
			<div class="notice <?php echo $test['ok'] ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html( $test['message'] ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'vsg_dons' ); ?>
			<h2>Compte SumUp</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="vsg_api_key">Clé API (secrète)</label></th>
					<td>
						<input type="password" id="vsg_api_key" name="vsg_dons[api_key]" class="regular-text" autocomplete="new-password" value="" placeholder="<?php echo $has_key ? 'Clé enregistrée (laisser vide pour la conserver)' : 'sup_sk_…'; ?>">
						<p class="description">Espace SumUp › Développeurs › Clés API. La clé est enregistrée mais jamais réaffichée. 
						<?php if ( $has_key ) : ?><label><input type="checkbox" name="vsg_dons[clear_key]" value="1"> Supprimer la clé enregistrée</label><?php endif; ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="vsg_merchant">Code marchand</label></th>
					<td><input type="text" id="vsg_merchant" name="vsg_dons[merchant]" class="regular-text" value="<?php echo esc_attr( $s['merchant'] ); ?>" placeholder="MXXXXXXX"><p class="description">Affiché dans le profil SumUp. Le bouton « Tester » le retrouve automatiquement si la clé est bonne.</p></td>
				</tr>
			</table>
			<h2>Pourquoi soutenir</h2>
			<p class="description">Une ligne par motif : <code>Titre ; texte</code>. Quatre lignes font une belle grille.</p>
			<textarea name="vsg_dons[why]" rows="6" class="large-text"><?php echo esc_textarea( $s['why'] ); ?></textarea>
			<h2>Chèque et virement</h2>
			<p class="description">Les deux paroisses et les deux chapelles reçoivent sur le même compte : un seul RIB, un seul ordre de chèque.</p>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Chèque à l'ordre de</th><td><input type="text" name="vsg_dons[ordre]" class="regular-text" value="<?php echo esc_attr( $s['ordre'] ); ?>" placeholder="Paroisse Notre-Dame de Lourdes"></td></tr>
				<tr><th scope="row">Adresse d'envoi</th><td><textarea name="vsg_dons[adresse]" rows="2" class="large-text" placeholder="<?php echo $p0 ? esc_attr( vsg_meta( $p0->ID, 'adresse' ) ) : ''; ?>"><?php echo esc_textarea( $s['adresse'] ); ?></textarea><p class="description">Vide = adresse du secrétariat.</p></td></tr>
				<tr><th scope="row">Titulaire du compte</th><td><input type="text" name="vsg_dons[titulaire]" class="regular-text" value="<?php echo esc_attr( $s['titulaire'] ); ?>"></td></tr>
				<tr><th scope="row">IBAN</th><td><input type="text" name="vsg_dons[iban]" class="regular-text" value="<?php echo esc_attr( vsg_dons_iban( $s['iban'] ) ); ?>"></td></tr>
				<tr><th scope="row">BIC</th><td><input type="text" name="vsg_dons[bic]" class="regular-text" value="<?php echo esc_attr( $s['bic'] ); ?>"></td></tr>
			</table>
			<?php submit_button( 'Enregistrer' ); ?>
		</form>
		<form method="post">
			<?php wp_nonce_field( 'vsg_dons_test' ); ?>
			<p><button class="button" name="vsg_dons_test" value="1" <?php disabled( ! $has_key ); ?>>Tester la connexion SumUp</button></p>
		</form>
		<h2>Journal des dons</h2>
		<?php vsg_paiements_log_table(); ?>
	</div>
	<?php
}

/* ---------- Traitement du formulaire ---------- */

add_action( 'admin_post_nopriv_vsg_don', 'vsg_dons_handle' );
add_action( 'admin_post_vsg_don', 'vsg_dons_handle' );

function vsg_dons_handle() {
	$back = vsg_page_url( 'soutenir' );
	$fail = function ( $code ) use ( $back ) {
		vsg_form_redirect( $back, 'don', $code, '#don' );
	};
	if ( empty( $_POST['vsg_don_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vsg_don_nonce'] ) ), 'vsg_don' ) ) {
		$fail( 'expire' );
	}
	if ( ! empty( $_POST['vsg_site'] ) ) {
		$fail( 'merci' ); // robot : on fait semblant.
	}
	if ( vsg_form_guard( 'don', 3, 10 * MINUTE_IN_SECONDS ) ) {
		$fail( 'trop' );
	}
	$pid     = absint( $_POST['vsg_paroisse'] ?? 0 );
	$targets = vsg_dons_targets();
	if ( ! isset( $targets[ $pid ] ) ) {
		$fail( 'paroisse' );
	}
	$target = $targets[ $pid ];
	$libre  = sanitize_text_field( wp_unslash( $_POST['vsg_libre'] ?? '' ) );
	$amount = vsg_dons_amount( '' !== trim( $libre ) ? $libre : sanitize_text_field( wp_unslash( $_POST['vsg_montant'] ?? '0' ) ) );
	if ( $amount < VSG_DON_MIN || $amount > VSG_DON_MAX ) {
		$fail( 'montant' );
	}
	if ( ! vsg_sumup_ready() ) {
		$fail( 'indisponible' );
	}
	$label = ( 'Chapelle' === $target['kind'] ? '' : 'paroisse ' ) . $target['label'];
	$pay   = vsg_sumup_checkout(
		'don',
		$amount,
		'Don à la ' . $label . ( 'Chapelle' === $target['kind'] ? ' (paroisse ' . get_the_title( $target['parish'] ) . ')' : '' ),
		add_query_arg( 'don', 'merci', $back ) . '#don',
		$target['label']
	);
	if ( ! $pay['url'] ) {
		$fail( 'erreur' );
	}
	wp_redirect( $pay['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- adresse vérifiée chez SumUp par vsg_sumup_url_ok().
	exit;
}

/* ---------- Affichage : shortcode [vsg_don] ---------- */

add_shortcode( 'vsg_don', 'vsg_dons_block' );

function vsg_dons_block() {
	$s     = vsg_dons_settings();
	$ready = vsg_sumup_ready();
	$msgs  = array(
		'merci'        => array( 'ok', 'Merci pour votre don. Si vous avez réglé, SumUp vous a envoyé un reçu par mail.' ),
		'expire'       => array( 'err', 'La page a expiré. Merci de renvoyer le formulaire.' ),
		'trop'         => array( 'err', 'Merci de patienter quelques instants avant de renvoyer le formulaire.' ),
		'montant'      => array( 'err', 'Merci d\'indiquer un montant entre ' . VSG_DON_MIN . ' € et 5 000 €.' ),
		'paroisse'     => array( 'err', 'Merci de choisir une paroisse.' ),
		'indisponible' => array( 'err', 'Le paiement en ligne n\'est pas encore ouvert. Vous pouvez donner par chèque ou par virement.' ),
		'erreur'       => array( 'err', 'Le service de paiement n\'a pas répondu. Réessayez dans quelques minutes, ou donnez par chèque ou par virement.' ),
	);
	$why   = array();
	foreach ( preg_split( '/\r?\n/', $s['why'] ) as $line ) {
		$parts = array_map( 'trim', explode( ';', $line, 2 ) );
		if ( '' !== $parts[0] ) {
			$why[] = $parts;
		}
	}
	$p0  = vsg_parishes() ? vsg_parishes()[0] : null;
	$tel = $p0 ? vsg_meta( $p0->ID, 'telephone' ) : '';
	ob_start();
	?>
	<div class="don" id="don">
		<?php echo vsg_form_notices( 'don', $msgs, vsg_form_status( 'don' ) ); ?>
		<form class="don__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="vsg_don">
			<?php echo vsg_form_hidden( 'don' ); ?>
			<fieldset class="don__fs">
				<legend>Je soutiens</legend>
				<div class="don__parishes">
					<?php $first = true; ?>
					<?php foreach ( vsg_dons_targets() as $id => $t ) : ?>
						<label class="don__parish<?php echo 'Chapelle' === $t['kind'] ? ' don__parish--chapel' : ''; ?>" style="--accent:<?php echo esc_attr( vsg_parish_color( $t['parish'] ) ); ?>">
							<input type="radio" name="vsg_paroisse" value="<?php echo (int) $id; ?>" <?php checked( $first ); ?>>
							<span><small><?php echo esc_html( $t['kind'] ); ?></small><?php echo esc_html( 'Chapelle' === $t['kind'] ? preg_replace( '/^Chapelle\s+/u', '', $t['label'] ) : $t['label'] ); ?></span>
						</label>
						<?php $first = false; ?>
					<?php endforeach; ?>
				</div>
				<p class="muted don__hint">Les quatre lieux partagent le même compte ; votre choix indique à la paroisse à quoi votre don est destiné.</p>
			</fieldset>
			<fieldset class="don__fs">
				<legend>Montant</legend>
				<div class="don__amounts">
					<?php foreach ( vsg_dons_amounts() as $i => $a ) : ?>
						<label class="don__amount"><input type="radio" name="vsg_montant" value="<?php echo (int) $a; ?>" <?php checked( 1 === $i ); ?>><span><?php echo (int) $a; ?> €</span></label>
					<?php endforeach; ?>
					<label class="don__amount don__amount--free"><span class="sr-only">Autre montant</span><input type="text" name="vsg_libre" inputmode="decimal" placeholder="Autre montant (<?php echo (int) VSG_DON_MIN; ?> € min.)" autocomplete="off" maxlength="8"><span class="don__eur">€</span></label>
				</div>
			</fieldset>
			<div class="don__submit">
				<?php if ( $ready ) : ?>
					<button class="btn" type="submit">Soutenir</button>
					<p class="muted">Paiement sécurisé par SumUp : carte, Apple Pay ou Google Pay. Vous ne quittez ce site que pour payer.</p>
				<?php else : ?>
					<button class="btn" type="button" disabled aria-disabled="true">Paiement en ligne bientôt disponible</button>
					<p class="muted">En attendant, vous pouvez donner par chèque ou par virement, ci-dessous.</p>
				<?php endif; ?>
			</div>
		</form>
		<h3 id="cheque">Par chèque ou par virement</h3>
		<div class="infocard don__bank">
			<p><strong>Chèque</strong> à l'ordre de « <?php echo esc_html( $s['ordre'] ? $s['ordre'] : ( $p0 ? 'Paroisse ' . get_the_title( $p0 ) : 'la paroisse' ) ); ?> », à déposer ou envoyer au secrétariat :<br><?php echo vsg_nl2br( $s['adresse'] ? $s['adresse'] : ( $p0 ? vsg_meta( $p0->ID, 'adresse' ) : '' ) ); ?></p>
			<?php if ( $s['iban'] ) : ?>
				<p><strong>Virement</strong><?php echo $s['titulaire'] ? ' à ' . esc_html( $s['titulaire'] ) : ''; ?><br>IBAN : <code><?php echo esc_html( vsg_dons_iban( $s['iban'] ) ); ?></code>
				<?php if ( $s['bic'] ) : ?><br>BIC : <code><?php echo esc_html( $s['bic'] ); ?></code><?php endif; ?></p>
			<?php else : ?>
				<p><strong>Virement</strong> : demandez le RIB au secrétariat<?php echo $tel ? ' au ' . esc_html( $tel ) : ''; ?>.</p>
			<?php endif; ?>
			<p class="muted">Les deux paroisses et les deux chapelles reçoivent sur le même compte : indiquez au dos du chèque ou dans le libellé du virement le lieu que vous souhaitez soutenir, par exemple « Don chapelle Saint-Louis ».</p>
		</div>
		<?php if ( $why ) : ?>
			<h3>Pourquoi soutenir ?</h3>
			<div class="don__why">
				<?php foreach ( $why as $w ) : ?>
					<div class="don__tile"><strong><?php echo esc_html( $w[0] ); ?></strong>
					<?php if ( ! empty( $w[1] ) ) : ?><p><?php echo esc_html( $w[1] ); ?></p><?php endif; ?></div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
