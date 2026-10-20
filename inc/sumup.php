<?php
/**
 * Paiement en ligne par SumUp (page de paiement hébergée), utilisé par les dons et les offrandes de messe.
 *
 * Le site ne manipule jamais de données bancaires : il crée un paiement du montant voulu chez SumUp,
 * puis envoie la personne sur checkout.sumup.com. Un journal sans donnée bancaire (date, lieu,
 * montant, référence, statut) sert au rapprochement avec l'espace SumUp.
 *
 * Réglages : le code marchand est dans l'option vsg_dons (page Réglages › Dons) ; la clé API dans
 * l'option vsg_dons_key, jamais chargée automatiquement, ou dans la constante VSG_SUMUP_KEY de wp-config.php.
 */

defined( 'ABSPATH' ) || exit;

const VSG_SUMUP_API = 'https://api.sumup.com/v0.1';

/** Clé API : constante de wp-config.php si elle existe, sinon l'option. */
function vsg_sumup_key() {
	if ( defined( 'VSG_SUMUP_KEY' ) && '' !== (string) VSG_SUMUP_KEY ) {
		return (string) VSG_SUMUP_KEY;
	}
	return (string) get_option( 'vsg_dons_key', '' );
}

/** Code marchand SumUp (réglé dans Réglages › Dons, ou retrouvé par le test de connexion). */
function vsg_sumup_merchant() {
	$o = get_option( 'vsg_dons', array() );
	return is_array( $o ) ? (string) ( $o['merchant'] ?? '' ) : '';
}

/** Le paiement en ligne est-il réglé (clé et code marchand) ? */
function vsg_sumup_ready() {
	return '' !== trim( vsg_sumup_key() ) && '' !== trim( vsg_sumup_merchant() );
}

/** L'adresse de paiement renvoyée par SumUp pointe-t-elle bien chez SumUp ? */
function vsg_sumup_url_ok( $url ) {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	return 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) && ( 'sumup.com' === $host || str_ends_with( $host, '.sumup.com' ) );
}

/**
 * Appel à l'API SumUp.
 *
 * @return array{code:int,data:mixed,error:string}
 */
function vsg_sumup_request( $method, $path, $body = null ) {
	$args = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Bearer ' . vsg_sumup_key(),
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$r = wp_remote_request( VSG_SUMUP_API . $path, $args );
	if ( is_wp_error( $r ) ) {
		return array(
			'code'  => 0,
			'data'  => null,
			'error' => $r->get_error_message(),
		);
	}
	return array(
		'code'  => (int) wp_remote_retrieve_response_code( $r ),
		'data'  => json_decode( wp_remote_retrieve_body( $r ), true ),
		'error' => '',
	);
}

/**
 * Crée un paiement et renvoie l'adresse de la page de paiement SumUp.
 * Le résultat (envoi ou erreur) est noté dans le journal.
 *
 * @param string $prefix      « don » ou « messe » : préfixe de la référence.
 * @param float  $amount      Montant en euros.
 * @param string $description Libellé affiché chez SumUp.
 * @param string $redirect    Adresse de retour après paiement.
 * @param string $lieu        Lieu ou paroisse, pour le journal.
 * @param string $statut      Préfixe de statut dans le journal (« » ou « Messe : »).
 * @return array{ref:string,url:string} url vide en cas d'échec.
 */
function vsg_sumup_checkout( $prefix, $amount, $description, $redirect, $lieu, $statut = '' ) {
	$ref = $prefix . '-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false );
	$r   = vsg_sumup_request(
		'POST',
		'/checkouts',
		array(
			'checkout_reference' => $ref,
			'amount'             => $amount,
			'currency'           => 'EUR',
			'merchant_code'      => vsg_sumup_merchant(),
			'description'        => $description,
			'redirect_url'       => $redirect,
			'hosted_checkout'    => array( 'enabled' => true ),
		)
	);
	$url = (string) ( $r['data']['hosted_checkout_url'] ?? '' );
	$ok  = ! $r['error'] && in_array( $r['code'], array( 200, 201 ), true ) && $url && vsg_sumup_url_ok( $url );
	vsg_paiements_log( $lieu, $amount, $ref, $statut . ( $ok ? 'Envoyé vers SumUp' : 'Erreur SumUp (' . ( $r['error'] ? $r['error'] : $r['code'] ) . ')' ) );
	return array(
		'ref' => $ref,
		'url' => $ok ? $url : '',
	);
}

/** Test de la clé : renvoie array( ok, message ) et note le code marchand s'il manquait. */
function vsg_sumup_test() {
	$r = vsg_sumup_request( 'GET', '/me' );
	if ( $r['error'] ) {
		return array(
			'ok'      => false,
			'message' => 'Connexion impossible : ' . $r['error'],
		);
	}
	if ( 200 !== $r['code'] ) {
		return array(
			'ok'      => false,
			'message' => 'SumUp refuse la clé (réponse ' . $r['code'] . '). Vérifiez la clé API.',
		);
	}
	$code = $r['data']['merchant_profile']['merchant_code'] ?? '';
	$name = $r['data']['merchant_profile']['company_name'] ?? '';
	if ( $code && '' === vsg_sumup_merchant() ) {
		$o             = get_option( 'vsg_dons', array() );
		$o             = is_array( $o ) ? $o : array();
		$o['merchant'] = $code;
		update_option( 'vsg_dons', $o );
	}
	return array(
		'ok'      => true,
		'message' => 'Connexion réussie : compte « ' . $name . ' », code marchand ' . $code . '.',
	);
}

/* ---------- Journal (sans données bancaires) ---------- */

/** Ajoute une ligne au journal des paiements. Les erreurs sont limitées à 50 : elles ne chassent pas les vrais envois. */
function vsg_paiements_log( $lieu, $amount, $ref, $statut ) {
	$log   = get_option( 'vsg_dons_log', array() );
	$log[] = array(
		'date'     => current_time( 'd/m/Y H:i' ),
		'paroisse' => $lieu,
		'montant'  => $amount,
		'ref'      => $ref,
		'statut'   => $statut,
	);
	$err   = 0;
	foreach ( array_reverse( array_keys( $log ) ) as $k ) {
		if ( false !== stripos( (string) ( $log[ $k ]['statut'] ?? '' ), 'erreur' ) ) {
			++$err;
			if ( $err > 50 ) {
				unset( $log[ $k ] );
			}
		}
	}
	update_option( 'vsg_dons_log', array_slice( array_values( $log ), -300 ), false );
}

/** Tableau du journal pour la page de réglages. */
function vsg_paiements_log_table() {
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
