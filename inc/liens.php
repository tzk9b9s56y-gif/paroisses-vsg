<?php
/**
 * Liens utiles (Saint-Siège, Vatican News, diocèse, AELF…), réglés dans Réglages › Général,
 * affichés dans le pied de page.
 */

defined( 'ABSPATH' ) || exit;

function vsg_liens_defaut() {
	return "Le Saint-Siège (Vatican) | https://www.vatican.va/content/vatican/fr.html\n"
		. "Vatican News | https://www.vaticannews.va/fr.html\n"
		. "Diocèse de Créteil | https://catholiques-val-de-marne.cef.fr/\n"
		. "Église catholique en France | https://eglise.catholique.fr/\n"
		. 'AELF – textes liturgiques | https://www.aelf.org/';
}

/** Adresse web acceptable : http(s) et bien formée. */
function vsg_liens_url_ok( $url ) {
	return (bool) filter_var( $url, FILTER_VALIDATE_URL ) && in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true );
}

/** Liste des liens : array( array( 'label', 'url' ) ). */
function vsg_liens() {
	$raw = get_option( 'vsg_liens', null );
	if ( null === $raw ) {
		$raw = vsg_liens_defaut();
	}
	$out = array();
	foreach ( explode( "\n", (string) $raw ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( 2 === count( $parts ) && $parts[0] && vsg_liens_url_ok( $parts[1] ) ) {
			$out[] = array( $parts[0], $parts[1] );
		}
	}
	return $out;
}

/** Liste HTML des liens, ou '' s'il n'y en a pas. */
function vsg_liens_html() {
	$liens = vsg_liens();
	if ( ! $liens ) {
		return '';
	}
	$h = '<ul class="liens">';
	foreach ( $liens as $l ) {
		$h .= '<li><a href="' . esc_url( $l[1] ) . '" target="_blank" rel="noopener">' . esc_html( $l[0] ) . '<span class="sr-only"> (nouvel onglet)</span></a></li>';
	}
	return $h . '</ul>';
}

add_action(
	'admin_init',
	function () {
		register_setting(
			'general',
			'vsg_liens',
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $v ) {
					$lines = array();
					foreach ( explode( "\n", (string) $v ) as $line ) {
						$parts = array_map( 'trim', explode( '|', sanitize_text_field( $line ), 2 ) );
						if ( 2 === count( $parts ) && $parts[0] && vsg_liens_url_ok( $parts[1] ) ) {
							$lines[] = $parts[0] . ' | ' . esc_url_raw( $parts[1] );
						}
					}
					return implode( "\n", $lines );
				},
			)
		);
		add_settings_field(
			'vsg_liens',
			'Liens utiles (pied de page)',
			function () {
				$raw = get_option( 'vsg_liens', null );
				echo '<textarea name="vsg_liens" class="large-text code" rows="6">' . esc_textarea( null === $raw ? vsg_liens_defaut() : $raw ) . '</textarea>';
				echo '<p class="description">Un lien par ligne : <code>Titre | https://adresse</code>. Les lignes mal formées sont ignorées. Videz la zone pour ne rien afficher.</p>';
			},
			'general'
		);
	}
);
