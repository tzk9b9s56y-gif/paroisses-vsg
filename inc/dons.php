<?php
/**
 * Dons en ligne à la paroisse, via SumUp (paiement hébergé).
 *
 * Le site ne manipule jamais de données bancaires : il crée un paiement chez SumUp
 * avec le montant choisi, puis envoie la personne sur checkout.sumup.com.
 * Réglages dans Réglages › Dons. Bloc affiché avec le shortcode [vsg_don].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VSG_DONS_API', 'https://api.sumup.com/v0.1' );

/* Don minimum : 10 € (les petits montants servent surtout à tester des cartes volées). */
const VSG_DON_MIN = 10;

function vsg_dons_amounts() {
	return array( 10, 20, 50, 100, 200 );
}

function vsg_dons_defaults() {
	return array(
		'api_key'  => '',
		'merchant' => '',
		'why'      => "Entretenir et chauffer nos églises ; Les bâtiments appartiennent aux communes, mais les réparations courantes, le chauffage, l'électricité et l'assurance restent à la charge de la paroisse.\nAccompagner les familles ; Catéchisme, aumônerie, préparation aux sacrements : les livrets, les sorties et le matériel ont un coût.\nAider ceux qui frappent à la porte ; Colis, aides d'urgence, accueil des personnes isolées, en lien avec les associations de la ville.\nFaire vivre la liturgie ; Fleurs, cierges, hosties, livres de chant, instruments : tout ce qui rend les célébrations belles et priantes.",
		'ordre'     => '',
		'adresse'   => '',
		'titulaire' => '',
		'iban'      => '',
		'bic'       => '',
	);
}

/**
 * Réglages des dons. La clé API SumUp est rangée à part (option vsg_dons_key, jamais chargée
 * automatiquement), ou dans la constante VSG_SUMUP_KEY de wp-config.php si elle existe.
 */
function vsg_dons_settings() {
	$o = get_option( 'vsg_dons', array() );
	$s = wp_parse_args( is_array( $o ) ? $o : array(), vsg_dons_defaults() );
	$s['api_key'] = ( defined( 'VSG_SUMUP_KEY' ) && '' !== (string) VSG_SUMUP_KEY ) ? (string) VSG_SUMUP_KEY : (string) get_option( 'vsg_dons_key', '' );
	return $s;
}

/** 0.9.25 : clé SumUp sortie du réglage général, secrets sans chargement automatique. */
function vsg_secrets_migrate() {
	$o = get_option( 'vsg_dons', array() );
	if ( is_array( $o ) && ! empty( $o['api_key'] ) ) {
		update_option( 'vsg_dons_key', $o['api_key'], false );
		unset( $o['api_key'] );
		update_option( 'vsg_dons', $o );
	}
	if ( function_exists( 'vsg_secrets_no_autoload' ) ) {
		vsg_secrets_no_autoload();
	}
}


/** Lieux auxquels on peut donner : paroisses et chapelles, avec la paroisse de rattachement. */
function vsg_dons_targets() {
	$out = array();
	foreach ( vsg_parishes() as $p ) {
		$out[ $p->ID ] = array( 'post' => $p, 'parish' => $p, 'label' => get_the_title( $p ), 'kind' => 'Paroisse' );
		foreach ( vsg_chapels( $p->ID ) as $c ) {
			$out[ $c->ID ] = array( 'post' => $c, 'parish' => $p, 'label' => get_the_title( $c ), 'kind' => 'Chapelle' );
		}
	}
	return $out;
}

/** L'adresse de paiement renvoyée par SumUp pointe-t-elle bien chez SumUp ? */
function vsg_sumup_url_ok( $url ) {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	return 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) && ( 'sumup.com' === $host || str_ends_with( $host, '.sumup.com' ) );
}

function vsg_dons_ready() {
	$s = vsg_dons_settings();
	return '' !== trim( $s['api_key'] ) && '' !== trim( $s['merchant'] );
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
	$out = array(
		'api_key'  => isset( $in['api_key'] ) ? trim( sanitize_text_field( $in['api_key'] ) ) : '',
		'merchant' => isset( $in['merchant'] ) ? strtoupper( trim( sanitize_text_field( $in['merchant'] ) ) ) : '',
		'why'      => isset( $in['why'] ) ? sanitize_textarea_field( $in['why'] ) : '',
		'ordre'     => sanitize_text_field( $in['ordre'] ?? '' ),
		'adresse'   => sanitize_textarea_field( $in['adresse'] ?? '' ),
		'titulaire' => sanitize_text_field( $in['titulaire'] ?? '' ),
		'iban'      => strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $in['iban'] ?? '' ) ),
		'bic'       => strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $in['bic'] ?? '' ) ),
	);
	/* La clé est rangée à part. Champ laissé vide = on garde la clé déjà enregistrée (elle n'est jamais réaffichée). */
	if ( ! empty( $in['clear_key'] ) ) {
		delete_option( 'vsg_dons_key' );
	} elseif ( '' !== $out['api_key'] ) {
		update_option( 'vsg_dons_key', $out['api_key'], false );
	}
	unset( $out['api_key'] );
	return $out;
}

function vsg_dons_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s    = vsg_dons_settings();
	$test = null;
	if ( isset( $_POST['vsg_dons_test'] ) && check_admin_referer( 'vsg_dons_test' ) ) {
		$test = vsg_dons_test();
	}
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
						<input type="password" id="vsg_api_key" name="vsg_dons[api_key]" class="regular-text" autocomplete="new-password" value="" placeholder="<?php echo $s['api_key'] ? 'Clé enregistrée (laisser vide pour la conserver)' : 'sup_sk_…'; ?>">
						<p class="description">Espace SumUp › Développeurs › Clés API. La clé est enregistrée mais jamais réaffichée. <?php if ( $s['api_key'] ) : ?><label><input type="checkbox" name="vsg_dons[clear_key]" value="1"> Supprimer la clé enregistrée</label><?php endif; ?></p>
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
			<?php $p0 = vsg_parishes() ? vsg_parishes()[0] : null; ?>
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
			<p><button class="button" name="vsg_dons_test" value="1" <?php disabled( '' === $s['api_key'] ); ?>>Tester la connexion SumUp</button></p>
		</form>
		<h2>Journal des dons</h2>
		<?php vsg_dons_log_table(); ?>
	</div>
	<?php
}

function vsg_dons_iban( $iban ) {
	return trim( chunk_split( $iban, 4, ' ' ) );
}

function vsg_dons_request( $method, $path, $body = null ) {
	$s    = vsg_dons_settings();
	$args = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Bearer ' . $s['api_key'],
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$r = wp_remote_request( VSG_DONS_API . $path, $args );
	if ( is_wp_error( $r ) ) {
		return array( 'code' => 0, 'data' => null, 'error' => $r->get_error_message() );
	}
	return array(
		'code'  => (int) wp_remote_retrieve_response_code( $r ),
		'data'  => json_decode( wp_remote_retrieve_body( $r ), true ),
		'error' => '',
	);
}

function vsg_dons_test() {
	$r = vsg_dons_request( 'GET', '/me' );
	if ( $r['error'] ) {
		return array( 'ok' => false, 'message' => 'Connexion impossible : ' . $r['error'] );
	}
	if ( 200 !== $r['code'] ) {
		return array( 'ok' => false, 'message' => 'SumUp refuse la clé (réponse ' . $r['code'] . '). Vérifiez la clé API.' );
	}
	$code = $r['data']['merchant_profile']['merchant_code'] ?? '';
	$name = $r['data']['merchant_profile']['company_name'] ?? '';
	if ( $code ) {
		$s = vsg_dons_settings();
		if ( '' === $s['merchant'] ) {
			$s['merchant'] = $code;
			update_option( 'vsg_dons', $s );
		}
	}
	return array( 'ok' => true, 'message' => 'Connexion réussie : compte « ' . $name . ' », code marchand ' . $code . '.' );
}

/* ---------- Journal (sans données bancaires) ---------- */

function vsg_dons_log( $entry ) {
	$log   = get_option( 'vsg_dons_log', array() );
	$log[] = $entry;
	/* Les erreurs (souvent des essais répétés) sont limitées à 50 : elles ne chassent pas les vrais envois. */
	$err = 0;
	foreach ( array_reverse( array_keys( $log ) ) as $k ) {
		if ( false !== stripos( (string) ( $log[ $k ]['statut'] ?? '' ), 'erreur' ) && ++$err > 50 ) {
			unset( $log[ $k ] );
		}
	}
	update_option( 'vsg_dons_log', array_slice( array_values( $log ), -300 ), false );
}

function vsg_dons_log_table() {
	$log = array_reverse( (array) get_option( 'vsg_dons_log', array() ) );
	if ( ! $log ) {
		echo '<p>Aucun don en ligne pour l\'instant. Le détail des paiements se consulte dans l\'espace SumUp.</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Paroisse</th><th>Montant</th><th>Référence</th><th>Statut</th></tr></thead><tbody>';
	foreach ( array_slice( $log, 0, 50 ) as $l ) {
		echo '<tr><td>' . esc_html( $l['date'] ) . '</td><td>' . esc_html( $l['paroisse'] ) . '</td><td>' . esc_html( number_format_i18n( $l['montant'], 2 ) ) . ' €</td><td><code>' . esc_html( $l['ref'] ) . '</code></td><td>' . esc_html( $l['statut'] ) . '</td></tr>';
	}
	echo '</tbody></table><p class="description">« Envoyé vers SumUp » signifie que la personne a été dirigée vers la page de paiement ; seul l\'espace SumUp confirme que le don a bien été réglé.</p>';
}

/* ---------- Traitement du formulaire ---------- */

add_action( 'admin_post_nopriv_vsg_don', 'vsg_dons_handle' );
add_action( 'admin_post_vsg_don', 'vsg_dons_handle' );

function vsg_dons_handle() {
	$back = vsg_page_url( 'soutenir' );
	$fail = function ( $code ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'don', $code, $back ) . '#don' );
		exit;
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
	$parish = $target['parish'];
	$label  = ( 'Chapelle' === $target['kind'] ? '' : 'paroisse ' ) . $target['label'];
	$libre  = str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['vsg_libre'] ?? '' ) ) );
	$amount = '' !== trim( $libre ) ? (float) $libre : (float) ( $_POST['vsg_montant'] ?? 0 );
	$amount = round( $amount, 2 );
	if ( $amount < VSG_DON_MIN || $amount > 5000 ) {
		$fail( 'montant' );
	}
	if ( ! vsg_dons_ready() ) {
		$fail( 'indisponible' );
	}
	$s   = vsg_dons_settings();
	$ref = 'don-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false );
	$r   = vsg_dons_request(
		'POST',
		'/checkouts',
		array(
			'checkout_reference' => $ref,
			'amount'             => $amount,
			'currency'           => 'EUR',
			'merchant_code'      => $s['merchant'],
			'description'        => 'Don à la ' . $label . ( 'Chapelle' === $target['kind'] ? ' (paroisse ' . get_the_title( $parish ) . ')' : '' ),
			'redirect_url'       => add_query_arg( 'don', 'merci', $back ) . '#don',
			'hosted_checkout'    => array( 'enabled' => true ),
		)
	);
	$url = $r['data']['hosted_checkout_url'] ?? '';
	if ( $r['error'] || ! in_array( $r['code'], array( 200, 201 ), true ) || ! $url ) {
		vsg_dons_log( array( 'date' => current_time( 'd/m/Y H:i' ), 'paroisse' => $target['label'], 'montant' => $amount, 'ref' => $ref, 'statut' => 'Erreur SumUp (' . ( $r['error'] ? $r['error'] : $r['code'] ) . ')' ) );
		$fail( 'erreur' );
	}
	if ( ! vsg_sumup_url_ok( $url ) ) {
		$fail( 'erreur' );
	}
	vsg_dons_log( array( 'date' => current_time( 'd/m/Y H:i' ), 'paroisse' => $target['label'], 'montant' => $amount, 'ref' => $ref, 'statut' => 'Envoyé vers SumUp' ) );
	wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect -- domaine SumUp.
	exit;
}

/* ---------- Affichage : shortcode [vsg_don] ---------- */

add_shortcode( 'vsg_don', 'vsg_dons_block' );

function vsg_dons_block() {
	$s      = vsg_dons_settings();
	$ready  = vsg_dons_ready();
	$state  = isset( $_GET['don'] ) ? sanitize_key( $_GET['don'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$msgs   = array(
		'merci'        => array( 'ok', 'Merci pour votre don. Si vous avez réglé, SumUp vous a envoyé un reçu par mail.' ),
		'expire'       => array( 'err', 'La page a expiré. Merci de renvoyer le formulaire.' ),
		'trop'         => array( 'err', 'Merci de patienter quelques instants avant de renvoyer le formulaire.' ),
		'montant'      => array( 'err', 'Merci d\'indiquer un montant entre ' . VSG_DON_MIN . ' € et 5 000 €.' ),
		'paroisse'     => array( 'err', 'Merci de choisir une paroisse.' ),
		'indisponible' => array( 'err', 'Le paiement en ligne n\'est pas encore ouvert. Vous pouvez donner par chèque ou par virement.' ),
		'erreur'       => array( 'err', 'Le service de paiement n\'a pas répondu. Réessayez dans quelques minutes, ou donnez par chèque ou par virement.' ),
	);
	$why    = array();
	foreach ( preg_split( '/\r?\n/', $s['why'] ) as $line ) {
		$parts = array_map( 'trim', explode( ';', $line, 2 ) );
		if ( '' !== $parts[0] ) {
			$why[] = $parts;
		}
	}
	ob_start();
	?>
	<div class="don" id="don">
		<?php if ( isset( $msgs[ $state ] ) ) : ?>
			<p class="notice notice--<?php echo esc_attr( $msgs[ $state ][0] ); ?>" role="<?php echo 'ok' === $msgs[ $state ][0] ? 'status' : 'alert'; ?>"><?php echo esc_html( $msgs[ $state ][1] ); ?></p>
		<?php endif; ?>
		<form class="don__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="vsg_don">
			<?php wp_nonce_field( 'vsg_don', 'vsg_don_nonce' ); ?>
			<?php echo vsg_form_timer_field(); // phpcs:ignore ?>
			<p class="field field--hp" aria-hidden="true"><label for="vsg_site_don">Site web</label><input type="text" id="vsg_site_don" name="vsg_site" tabindex="-1" autocomplete="off"></p>
			<fieldset class="don__fs">
				<legend>Je soutiens</legend>
				<div class="don__parishes">
					<?php $first = true; foreach ( vsg_dons_targets() as $id => $t ) : ?>
						<label class="don__parish<?php echo 'Chapelle' === $t['kind'] ? ' don__parish--chapel' : ''; ?>" style="--accent:<?php echo esc_attr( vsg_parish_color( $t['parish'] ) ); ?>">
							<input type="radio" name="vsg_paroisse" value="<?php echo (int) $id; ?>" <?php checked( $first ); ?>>
							<span><small><?php echo esc_html( $t['kind'] ); ?></small><?php echo esc_html( 'Chapelle' === $t['kind'] ? preg_replace( '/^Chapelle\s+/u', '', $t['label'] ) : $t['label'] ); ?></span>
						</label>
						<?php $first = false; endforeach; ?>
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
		<?php $p0 = vsg_parishes() ? vsg_parishes()[0] : null; $tel = $p0 ? vsg_meta( $p0->ID, 'telephone' ) : ''; ?>
		<div class="infocard don__bank">
			<p><strong>Chèque</strong> à l'ordre de « <?php echo esc_html( $s['ordre'] ? $s['ordre'] : ( $p0 ? 'Paroisse ' . get_the_title( $p0 ) : 'la paroisse' ) ); ?> », à déposer ou envoyer au secrétariat :<br><?php echo vsg_nl2br( $s['adresse'] ? $s['adresse'] : ( $p0 ? vsg_meta( $p0->ID, 'adresse' ) : '' ) ); // phpcs:ignore ?></p>
			<?php if ( $s['iban'] ) : ?>
				<p><strong>Virement</strong><?php echo $s['titulaire'] ? ' à ' . esc_html( $s['titulaire'] ) : ''; ?><br>IBAN : <code><?php echo esc_html( vsg_dons_iban( $s['iban'] ) ); ?></code><?php if ( $s['bic'] ) : ?><br>BIC : <code><?php echo esc_html( $s['bic'] ); ?></code><?php endif; ?></p>
			<?php else : ?>
				<p><strong>Virement</strong> : demandez le RIB au secrétariat<?php echo $tel ? ' au ' . esc_html( $tel ) : ''; ?>.</p>
			<?php endif; ?>
			<p class="muted">Les deux paroisses et les deux chapelles reçoivent sur le même compte : indiquez au dos du chèque ou dans le libellé du virement le lieu que vous souhaitez soutenir, par exemple « Don chapelle Saint-Louis ».</p>
		</div>
		<?php if ( $why ) : ?>
			<h3>Pourquoi soutenir ?</h3>
			<div class="don__why">
				<?php foreach ( $why as $w ) : ?>
					<div class="don__tile"><strong><?php echo esc_html( $w[0] ); ?></strong><?php if ( ! empty( $w[1] ) ) : ?><p><?php echo esc_html( $w[1] ); ?></p><?php endif; ?></div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
