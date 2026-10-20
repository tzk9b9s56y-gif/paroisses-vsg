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
 * Version publiée sur GitHub et commit correspondant (mis en cache 1 heure).
 * Le style.css est lu dans ce commit précis, et l'archive installée est celle
 * de ce même commit : ce qui est installé est exactement ce qui a été vérifié.
 *
 * @return array{version:string,sha:string}
 */
function vsg_remote_release( $force = false ) {
	$cached = get_site_transient( 'vsg_remote_release' );
	if ( ! $force && is_array( $cached ) ) {
		return $cached;
	}
	$rel = array(
		'version' => '',
		'sha'     => '',
	);
	$res = wp_remote_get(
		'https://api.github.com/repos/' . VSG_GITHUB_REPO . '/commits/' . VSG_GITHUB_BRANCH,
		array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/vnd.github.sha' ),
		)
	);
	$sha = is_wp_error( $res ) ? '' : trim( wp_remote_retrieve_body( $res ) );
	if ( 200 === wp_remote_retrieve_response_code( $res ) && preg_match( '/^[0-9a-f]{40}$/', $sha ) ) {
		$css = wp_remote_get( 'https://raw.githubusercontent.com/' . VSG_GITHUB_REPO . '/' . $sha . '/style.css', array( 'timeout' => 10 ) );
		if ( ! is_wp_error( $css ) && 200 === wp_remote_retrieve_response_code( $css )
			&& preg_match( '/^\s*Version:\s*([0-9][0-9.]*)/mi', wp_remote_retrieve_body( $css ), $m ) ) {
			$rel = array(
				'version' => $m[1],
				'sha'     => $sha,
			);
		}
	}
	/*
	 * Pas de repli sur « la branche main du moment » : on n'installe que l'archive d'un commit
	 * dont la version a été lue. Si l'API ne répond pas (limite horaire de GitHub), on réessaie
	 * plus tard ; le signal de GitHub, lui, donne directement le commit.
	 */
	/* En cas d'échec complet, on réessaie dans 1 heure plutôt que 6. */
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
		if ( $remote && version_compare( $remote, $local, '>' ) ) {
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
