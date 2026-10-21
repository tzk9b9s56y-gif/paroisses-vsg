<?php
/**
 * Mises à jour du thème depuis GitHub, sans extension.
 *
 * Le site lit la version indiquée dans le style.css du dernier commit de main.
 * Si elle est plus récente que la version installée, WordPress propose la mise à jour
 * dans Tableau de bord › Mises à jour, comme pour un thème officiel. L'archive
 * téléchargée est celle de ce commit précis ; son dossier est renommé pour garder
 * le nom « paroisses-vsg ».
 *
 * Publier une version : augmenter « Version: » dans style.css et pousser sur main.
 */

defined( 'ABSPATH' ) || exit;

define( 'VSG_GITHUB_REPO', 'tzk9b9s56y-gif/paroisses-vsg' );
define( 'VSG_GITHUB_BRANCH', 'main' );

/**
 * Dernier commit de main, sans passer par l'API de GitHub.
 *
 * L'API est limitée à 60 appels par heure et par adresse IP ; sur un hébergement mutualisé,
 * l'adresse est partagée et la limite vite atteinte. On lit donc d'abord la liste des branches
 * telle que Git la publie (adresse publique du dépôt, sans limite), puis l'API en secours.
 *
 * @return array{sha:string,error:string}
 */
function vsg_github_head() {
	$res = wp_remote_get(
		'https://github.com/' . VSG_GITHUB_REPO . '.git/info/refs?service=git-upload-pack',
		array( 'timeout' => 10 )
	);
	if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res )
		&& preg_match( '#([0-9a-f]{40}) refs/heads/' . preg_quote( VSG_GITHUB_BRANCH, '#' ) . '\n#', wp_remote_retrieve_body( $res ), $m ) ) {
		return array(
			'sha'   => $m[1],
			'error' => '',
		);
	}
	$first = is_wp_error( $res ) ? $res->get_error_message() : 'réponse ' . wp_remote_retrieve_response_code( $res );

	$api = wp_remote_get(
		'https://api.github.com/repos/' . VSG_GITHUB_REPO . '/commits/' . VSG_GITHUB_BRANCH,
		array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/vnd.github.sha' ),
		)
	);
	$sha = is_wp_error( $api ) ? '' : trim( wp_remote_retrieve_body( $api ) );
	if ( 200 === wp_remote_retrieve_response_code( $api ) && preg_match( '/^[0-9a-f]{40}$/', $sha ) ) {
		return array(
			'sha'   => $sha,
			'error' => '',
		);
	}
	if ( is_wp_error( $api ) ) {
		$why = 'GitHub injoignable (' . $api->get_error_message() . ')';
	} elseif ( in_array( (int) wp_remote_retrieve_response_code( $api ), array( 403, 429 ), true ) && '0' === wp_remote_retrieve_header( $api, 'x-ratelimit-remaining' ) ) {
		$why = 'limite horaire de l\'API GitHub atteinte, et dépôt illisible (' . $first . ')';
	} else {
		$why = 'dernier commit introuvable (dépôt : ' . $first . ', API : réponse ' . wp_remote_retrieve_response_code( $api ) . ')';
	}
	return array(
		'sha'   => '',
		'error' => $why,
	);
}

/**
 * Lit et contrôle le style.css d'un commit : fichier complet et numéro de version lisible.
 *
 * @return array{version:string,error:string}
 */
function vsg_github_css( $sha ) {
	$res = wp_remote_get( 'https://raw.githubusercontent.com/' . VSG_GITHUB_REPO . '/' . $sha . '/style.css', array( 'timeout' => 15 ) );
	if ( is_wp_error( $res ) ) {
		return array(
			'version' => '',
			'error'   => 'feuille de style injoignable (' . $res->get_error_message() . ')',
		);
	}
	if ( 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return array(
			'version' => '',
			'error'   => 'feuille de style introuvable (réponse ' . wp_remote_retrieve_response_code( $res ) . ')',
		);
	}
	$css = wp_remote_retrieve_body( $res );
	/* Feuille de style complète : en-tête du thème et règles CSS (une feuille vide ou tronquée est refusée). */
	if ( strlen( $css ) < 20000 || false === stripos( $css, 'Theme Name:' ) || substr_count( $css, '{' ) < 200 ) {
		return array(
			'version' => '',
			'error'   => 'feuille de style incomplète (' . strlen( $css ) . ' octets)',
		);
	}
	if ( ! preg_match( '/^\s*Version:\s*([0-9][0-9.]*)/mi', $css, $m ) ) {
		return array(
			'version' => '',
			'error'   => 'numéro de version illisible',
		);
	}
	return array(
		'version' => $m[1],
		'error'   => '',
	);
}

/**
 * Version publiée sur GitHub et commit correspondant (mis en cache 1 heure, 15 minutes après un échec).
 * Le style.css est lu dans ce commit précis, et l'archive installée est celle de ce même commit :
 * ce qui est installé est exactement ce qui a été vérifié.
 *
 * @return array{version:string,sha:string,error:string}
 */
function vsg_remote_release( $force = false ) {
	$cached = get_site_transient( 'vsg_remote_release' );
	if ( ! $force && is_array( $cached ) && isset( $cached['error'] ) ) {
		return $cached;
	}
	$head = vsg_github_head();
	$rel  = array(
		'version' => '',
		'sha'     => $head['sha'],
		'error'   => $head['error'],
	);
	if ( $head['sha'] ) {
		$css            = vsg_github_css( $head['sha'] );
		$rel['version'] = $css['version'];
		$rel['error']   = $css['error'];
	}
	set_site_transient( 'vsg_remote_release', $rel, $rel['version'] ? HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
	return $rel;
}

add_filter(
	'pre_set_site_transient_update_themes',
	function ( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		$slug   = get_template();
		$theme  = wp_get_theme( $slug );
		$local  = $theme->get( 'Version' );
		$force  = is_admin() && isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification -- paramètre posé par WordPress lui-même (bouton « Vérifier à nouveau »).
		$rel    = vsg_remote_release( $force );
		$remote = $rel['version'];
		$item   = array(
			'theme'       => $slug,
			'new_version' => $remote ? $remote : $local,
			'url'         => 'https://github.com/' . VSG_GITHUB_REPO,
			/* Archive du commit dont la version a été lue, et non l'état de main au moment du téléchargement. */
			'package'     => $rel['sha'] ? 'https://github.com/' . VSG_GITHUB_REPO . '/archive/' . $rel['sha'] . '.zip' : '',
		);
		/* Après accord : WordPress ne propose jamais la version lui-même ; seule la page Mises à jour du thème l'installe. */
		$allowed = 'auto' === vsg_au_mode() || ! empty( $GLOBALS['vsg_au_installing'] );
		if ( $allowed && $remote && version_compare( $remote, $local, '>' ) ) {
			$transient->response[ $slug ] = $item;
		} else {
			unset( $transient->response[ $slug ] );
			$transient->no_update[ $slug ] = $item; // Permet d'activer les mises à jour automatiques.
		}
		return $transient;
	}
);

/* L'archive GitHub contient un dossier « paroisses-vsg-<commit> » : on le renomme. */
add_filter(
	'upgrader_source_selection',
	function ( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;
		$slug = get_template();
		if ( empty( $hook_extra['theme'] ) || $hook_extra['theme'] !== $slug ) {
			return $source;
		}
		$target = trailingslashit( $remote_source ) . $slug . '/';
		if ( trailingslashit( $source ) === $target ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( $source, $target, true ) ) {
			return $target;
		}
		return new WP_Error( 'vsg_rename', 'Impossible de renommer le dossier du thème téléchargé.' );
	},
	10,
	4
);
