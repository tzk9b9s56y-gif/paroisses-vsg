<?php
/**
 * Journal du site : les 15 derniers événements techniques (mises à jour, signaux GitHub, règles du serveur),
 * affiché dans Apparence › Mises à jour du thème. Aucune donnée personnelle n'y est écrite.
 */

defined( 'ABSPATH' ) || exit;

/** Origines possibles d'une ligne, avec leur libellé. */
function vsg_journal_sources() {
	return array(
		'github'  => 'Signal GitHub',
		'heure'   => 'Vérification horaire',
		'manuel'  => 'Manuel',
		'accord'  => 'Votre accord',
		'serveur' => 'Serveur',
	);
}

/** Ajoute une ligne au journal. */
function vsg_journal( $source, $msg ) {
	$log   = get_option( 'vsg_update_log', array() );
	$log[] = array(
		'time'   => current_time( 'd/m/Y H:i' ),
		'source' => $source,
		'msg'    => $msg,
	);
	update_option( 'vsg_update_log', array_slice( $log, -15 ), false );
}

/** Lignes du journal, la plus récente en premier. */
function vsg_journal_entries() {
	return array_reverse( (array) get_option( 'vsg_update_log', array() ) );
}

/** Tableau du journal (HTML). */
function vsg_journal_table() {
	$log = vsg_journal_entries();
	if ( ! $log ) {
		return '<p>Rien pour l\'instant.</p>';
	}
	$h = '<table class="widefat striped vsg-w900"><thead><tr><th>Date</th><th>Origine</th><th>Résultat</th></tr></thead><tbody>';
	foreach ( $log as $l ) {
		$h .= '<tr><td>' . esc_html( $l['time'] ) . '</td><td>' . esc_html( vsg_journal_sources()[ $l['source'] ] ?? $l['source'] ) . '</td><td>' . esc_html( $l['msg'] ) . '</td></tr>';
	}
	return $h . '</tbody></table>';
}
