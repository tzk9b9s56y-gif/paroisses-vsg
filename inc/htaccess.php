<?php
/**
 * Règles de protection du serveur, écrites dans le .htaccess du site entre deux balises
 * « Paroisses VSG » (le reste du fichier, dont les règles de WordPress, n'est pas touché) :
 * - pas de liste des fichiers d'un dossier (ex. /wp-content/uploads/) ;
 * - readme.html, license.txt, wp-config-sample.php et xmlrpc.php inaccessibles.
 *
 * Après écriture, le site vérifie que l'accueil répond toujours ; sinon les règles sont retirées.
 */

defined( 'ABSPATH' ) || exit;

function vsg_htaccess_rules() {
	return array(
		'Options -Indexes',
		'<IfModule mod_authz_core.c>',
		'<FilesMatch "^(readme\.html|license\.txt|wp-config-sample\.php|xmlrpc\.php)$">',
		'Require all denied',
		'</FilesMatch>',
		'</IfModule>',
	);
}

/**
 * L'accueil répond-il normalement ?
 *
 * @return bool|null true = oui, false = non, null = impossible à vérifier (requête bloquée).
 */
function vsg_site_responds() {
	$res = wp_remote_get(
		add_query_arg( 'vsg-sante', time(), home_url( '/' ) ),
		array(
			'timeout'     => 20,
			'redirection' => 2,
		)
	);
	if ( is_wp_error( $res ) ) {
		return null;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = wp_remote_retrieve_body( $res );
	if ( 200 === $code && false !== stripos( $body, '</html>' ) ) {
		return true;
	}
	/* Mode maintenance : la page d'attente répond 503, c'est normal. */
	if ( 503 === $code && false !== strpos( $body, 'class="maint' ) ) {
		return true;
	}
	return false;
}

function vsg_htaccess_write() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	$file = get_home_path() . '.htaccess';
	if ( ( file_exists( $file ) && ! wp_is_writable( $file ) ) || ( ! file_exists( $file ) && ! wp_is_writable( dirname( $file ) ) ) ) {
		vsg_htaccess_log( 'Fichier .htaccess non modifiable : règles non écrites.' );
		return false;
	}
	if ( ! insert_with_markers( $file, 'Paroisses VSG', vsg_htaccess_rules() ) ) {
		vsg_htaccess_log( 'Écriture du .htaccess impossible.' );
		return false;
	}
	if ( false === vsg_site_responds() ) {
		insert_with_markers( $file, 'Paroisses VSG', array() );
		vsg_htaccess_log( 'Règles refusées par le serveur : retirées, le site fonctionne comme avant.' );
		return false;
	}
	vsg_htaccess_log( 'Règles de protection du serveur en place (liste des dossiers, fichiers d\'installation, XML-RPC).' );
	return true;
}

function vsg_htaccess_log( $msg ) {
	vsg_journal( 'serveur', $msg );
}
