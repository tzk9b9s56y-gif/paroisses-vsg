<?php
/**
 * Nouvelles versions du thème : détection, accord, installation.
 *
 * - Deux modes (Apparence › Mises à jour du thème) : « Après mon accord » (par défaut) ou « Automatique ».
 *   Après accord : une nouvelle version est contrôlée puis mise en attente ; l'administrateur est prévenu
 *   par mail et par un bandeau, voit le commit, et choisit Installer ou Refuser. Rien d'autre ne peut
 *   l'installer, pas même le tableau de bord › Mises à jour de WordPress.
 * - Signal de GitHub (webhook) à chaque envoi sur main : détection dans la minute.
 *   Le signal est signé avec une clé secrète (en-tête X-Hub-Signature-256) : sans la
 *   bonne clé, il est refusé.
 * - Filet de sécurité : vérification et installation toutes les heures (dernier commit lu sans l'API
 *   de GitHub, limitée sur un hébergement mutualisé).
 * - Tout échec ou signal ignoré est noté au journal avec sa raison.
 * - Avant d'installer : numéro de version supérieur et feuille de style complète,
 *   sinon rien n'est installé.
 *
 * Réglage et journal : Apparence › Mises à jour du thème.
 */

defined( 'ABSPATH' ) || exit;

/** Clé partagée avec GitHub (créée au premier besoin). */
function vsg_au_secret() {
	$s = get_option( 'vsg_update_secret' );
	if ( ! $s ) {
		$s = wp_generate_password( 40, false, false );
		update_option( 'vsg_update_secret', $s, false );
	}
	return $s;
}


/** Mode d'installation : « accord » (par défaut) ou « auto ». */
function vsg_au_mode() {
	return 'auto' === get_option( 'vsg_update_mode' ) ? 'auto' : 'accord';
}

/** Version en attente d'accord, ou null. */
function vsg_au_pending() {
	$p = get_option( 'vsg_update_pending' );
	return is_array( $p ) && ! empty( $p['sha'] ) ? $p : null;
}

/** Commits refusés (les 20 derniers). */
function vsg_au_refused() {
	return array_slice( (array) get_option( 'vsg_update_refused', array() ), -20 );
}

/** Lien vers un commit sur GitHub. */
function vsg_au_commit_url( $sha ) {
	return 'https://github.com/' . VSG_GITHUB_REPO . '/commit/' . $sha;
}

/** Met une version en attente et prévient l'administrateur par mail. */
function vsg_au_hold( $rel, $source ) {
	$before = vsg_au_pending();
	$info   = get_transient( 'vsg_au_info_' . $rel['sha'] );
	$p      = array_merge(
		array(
			'message' => '',
			'author'  => '',
			'pusher'  => '',
			'date'    => '',
		),
		is_array( $info ) ? $info : array(),
		array(
			'version'  => $rel['version'],
			'sha'      => $rel['sha'],
			'received' => time(),
			'source'   => $source,
		)
	);
	update_option( 'vsg_update_pending', $p, false );
	$msg = 'Version ' . $p['version'] . ' en attente de votre accord (commit ' . substr( $p['sha'], 0, 7 ) . ').';
	if ( $before && $before['sha'] !== $p['sha'] ) {
		$msg .= ' Elle remplace la ' . $before['version'] . ' (commit ' . substr( $before['sha'], 0, 7 ) . ') qui attendait.';
	}
	vsg_journal( $source, $msg );

	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$body = "Une nouvelle version du thème du site attend votre accord.\n\n"
		. 'Version : ' . $p['version'] . "\n"
		. ( $p['message'] ? 'Message du commit : ' . $p['message'] . "\n" : '' )
		. ( $p['author'] ? 'Auteur indiqué : ' . $p['author'] . "\n" : '' )
		. ( $p['pusher'] ? 'Envoyé par le compte GitHub : ' . $p['pusher'] . "\n" : '' )
		. ( $p['date'] ? 'Date : ' . $p['date'] . "\n" : '' )
		. 'Ce qui a changé : ' . vsg_au_commit_url( $p['sha'] ) . "\n\n"
		. "Si vous attendiez cette version, installez-la ici :\n" . admin_url( 'themes.php?page=vsg-mises-a-jour' ) . "\n\n"
		. "Si vous ne l'attendiez pas, refusez-la sur la même page et vérifiez l'accès au compte GitHub.\n\n"
		. $site . "\n";
	vsg_send_mail( get_option( 'admin_email' ), '[Site] Nouvelle version du thème en attente : ' . $p['version'], $body );
	return $msg;
}

/**
 * Cherche la dernière version, la contrôle, puis l'installe ou la met en attente selon le mode.
 *
 * @param string $source « github », « heure », « manuel » ou « accord » (installation acceptée).
 * @param string $sha    Commit annoncé par GitHub, ou accepté (facultatif).
 */
function vsg_au_run( $source = 'heure', $sha = '' ) {
	if ( get_transient( 'vsg_au_lock' ) ) {
		return 'Une installation est déjà en cours.';
	}
	set_transient( 'vsg_au_lock', 1, 10 * MINUTE_IN_SECONDS );

	$slug  = get_template();
	$local = wp_get_theme( $slug )->get( 'Version' );
	if ( $sha && preg_match( '/^[0-9a-f]{40}$/', $sha ) ) {
		$css = vsg_github_css( $sha );
		$rel = array(
			'version' => $css['version'],
			'sha'     => $sha,
			'error'   => $css['error'],
		);
	} else {
		$rel = vsg_remote_release( true );
	}

	if ( ! $rel['version'] || ! $rel['sha'] ) {
		delete_transient( 'vsg_au_lock' );
		$msg = 'Rien n\'est installé : ' . $rel['error'] . '.';
		vsg_journal( $source, $msg );
		return $msg;
	}
	$older = 'ancienne' === $source;
	if ( $older && ( $rel['version'] === $local || ! vsg_au_old_allowed( $rel['version'] ) ) ) {
		delete_transient( 'vsg_au_lock' );
		$msg = $rel['version'] === $local ? 'La ' . $local . ' est déjà installée.' : 'La ' . $rel['version'] . ' ne peut pas être réinstallée depuis cette page (versions ' . VSG_AU_OLDEST . ' et suivantes seulement).';
		vsg_journal( 'manuel', $msg );
		return $msg;
	}
	if ( ! $older && ! version_compare( $rel['version'], $local, '>' ) ) {
		delete_transient( 'vsg_au_lock' );
		if ( 'heure' !== $source ) {
			vsg_journal( $source, 'Déjà à jour (' . $local . ').' );
		}
		return 'Le thème est déjà à jour (' . $local . ').';
	}
	if ( ! $older && in_array( $rel['sha'], vsg_au_refused(), true ) ) {
		delete_transient( 'vsg_au_lock' );
		$msg = 'La version ' . $rel['version'] . ' (commit ' . substr( $rel['sha'], 0, 7 ) . ') a été refusée : elle n\'est pas installée.';
		if ( 'heure' !== $source ) {
			vsg_journal( $source, $msg );
		}
		return $msg;
	}
	if ( ! $older && 'accord' === vsg_au_mode() && 'accord' !== $source ) {
		delete_transient( 'vsg_au_lock' );
		$pending = vsg_au_pending();
		if ( $pending && $pending['sha'] === $rel['sha'] ) {
			return 'La version ' . $rel['version'] . ' attend déjà votre accord.';
		}
		return vsg_au_hold( $rel, $source );
	}

	/* La version vérifiée devient celle que WordPress proposera, avec l'archive de ce commit. */
	$GLOBALS['vsg_au_installing'] = true;
	$GLOBALS['vsg_au_older']      = $older;
	set_site_transient( 'vsg_remote_release', $rel, HOUR_IN_SECONDS );
	delete_site_transient( 'update_themes' );
	wp_update_themes();

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/theme.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	$backup   = vsg_au_backup( $slug );
	$upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
	$result   = $upgrader->upgrade( $slug );

	$GLOBALS['vsg_au_installing'] = false;
	$GLOBALS['vsg_au_older']      = false;
	/* Le prochain contrôle relit GitHub : la version la plus récente sera de nouveau proposée. */
	delete_site_transient( 'vsg_remote_release' );
	wp_clean_themes_cache();
	$now = wp_get_theme( $slug )->get( 'Version' );
	if ( true === $result && ( $older ? $now === $rel['version'] : version_compare( $now, $local, '>' ) ) ) {
		$msg = $older
			? 'Retour à une version antérieure : ' . $local . ' → ' . $now . ' (commit ' . substr( $rel['sha'], 0, 7 ) . '), par ' . wp_get_current_user()->display_name . '. La version la plus récente sera de nouveau proposée.'
			: 'Mise à jour installée : ' . $local . ' → ' . $now . '.';
		/* Le site doit répondre avec la nouvelle version ; sinon on remet l'ancienne. */
		$ok = vsg_site_responds();
		if ( false === $ok && $backup ) {
			$msg = vsg_au_restore( $slug, $backup )
				? 'La ' . $now . ' a été installée mais le site ne répondait plus : la ' . $local . ' a été remise en place automatiquement.'
				: 'La ' . $now . ' a été installée mais le site ne répond plus, et la remise en place de la ' . $local . ' a échoué. Réinstaller le zip de la ' . $local . ' depuis Apparence › Thèmes.';
		} elseif ( null === $ok ) {
			$msg .= ' (Contrôle de l\'accueil impossible depuis le serveur : vérifier le site.)';
		}
		/* La copie d'avant permet de revenir en arrière à la main, tant que cette version reste en place. */
		if ( false !== $ok && $backup ) {
			update_option(
				'vsg_update_backup',
				array(
					'version'   => $local,
					'installed' => $now,
					'sha'       => $rel['sha'],
					'date'      => time(),
				),
				false
			);
		} else {
			delete_option( 'vsg_update_backup' );
		}
	} else {
		$err = is_wp_error( $result ) ? $result->get_error_message() : implode( ' ', array_map( 'wp_strip_all_tags', (array) $upgrader->skin->get_upgrade_messages() ) );
		$msg = 'Échec de l\'installation de la ' . $rel['version'] . ( $err ? ' : ' . $err : '.' ) . ' Le thème ' . $local . ' reste en place.';
		delete_option( 'vsg_update_backup' );
	}
	$pending = vsg_au_pending();
	if ( $pending && ! $older && ! version_compare( $pending['version'], wp_get_theme( $slug )->get( 'Version' ), '>' ) ) {
		delete_option( 'vsg_update_pending' );
	}
	delete_transient( 'vsg_au_lock' );
	vsg_journal( $source, $msg );
	return $msg;
}

/** Première version réinstallable depuis la page : les précédentes n'ont pas cette page ni le mode « après accord ». */
const VSG_AU_OLDEST = '0.9.40';

/** Une version peut-elle être réinstallée depuis la page ? */
function vsg_au_old_allowed( $version ) {
	return (bool) $version && version_compare( $version, apply_filters( 'vsg_au_oldest', VSG_AU_OLDEST ), '>=' );
}

/**
 * Dernières versions publiées sur GitHub, lues dans le flux public des commits de main
 * (pas d'API, donc pas de limite horaire) : version, commit, date, résumé. 15 minutes en cache.
 *
 * @return array{list:array<int,array>,error:string}
 */
function vsg_au_versions() {
	$cached = get_site_transient( 'vsg_au_versions' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$out = array(
		'list'  => array(),
		'error' => '',
	);
	$r   = wp_remote_get( 'https://github.com/' . VSG_GITHUB_REPO . '/commits/main.atom', array( 'timeout' => 10 ) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		$out['error'] = 'liste des versions injoignable sur GitHub' . ( is_wp_error( $r ) ? ' (' . $r->get_error_message() . ')' : ' (réponse ' . wp_remote_retrieve_response_code( $r ) . ')' );
		set_site_transient( 'vsg_au_versions', $out, 5 * MINUTE_IN_SECONDS );
		return $out;
	}
	preg_match_all( '#<entry>(.*?)</entry>#s', (string) wp_remote_retrieve_body( $r ), $entries );
	$seen = array();
	foreach ( $entries[1] as $e ) {
		if ( ! preg_match( '#Commit/([0-9a-f]{40})</id>#', $e, $sha ) || ! preg_match( '#<title>(.*?)</title>#s', $e, $title ) ) {
			continue;
		}
		$title = html_entity_decode( trim( $title[1] ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
		if ( ! preg_match( '/^(?:Version\s+)?(\d+\.\d+\.\d+)\b/u', $title, $v ) || isset( $seen[ $v[1] ] ) ) {
			continue;
		}
		$seen[ $v[1] ] = true;
		preg_match( '#<updated>(.*?)</updated>#', $e, $date );
		$out['list'][] = array(
			'version' => $v[1],
			'sha'     => $sha[1],
			'date'    => isset( $date[1] ) ? strtotime( $date[1] ) : 0,
			'title'   => trim( preg_replace( '/^(?:Version\s+)?\d+\.\d+\.\d+\s*:?\s*/u', '', $title ) ),
		);
	}
	set_site_transient( 'vsg_au_versions', $out, 15 * MINUTE_IN_SECONDS );
	return $out;
}

/**
 * Dossier de copie du thème, dans wp-content/. Le .htaccess du thème y est copié avec lui :
 * aucun fichier PHP de la copie ne peut être appelé depuis le web.
 */
function vsg_au_dir( $name ) {
	return trailingslashit( WP_CONTENT_DIR ) . $name . '/';
}

/** Initialise l'accès aux fichiers de WordPress (méthode directe sur l'hébergement). */
function vsg_au_fs() {
	global $wp_filesystem;
	require_once ABSPATH . 'wp-admin/includes/file.php';
	if ( ! $wp_filesystem && ! WP_Filesystem() ) {
		return null;
	}
	return $wp_filesystem;
}

/**
 * Copie du thème installé avant la mise à jour, dans wp-content/vsg-sauvegarde/.
 * Hors de wp-content/upgrade/ : WordPress vide ce dossier au début de chaque installation.
 *
 * @return string Dossier de la copie, ou '' si elle n'a pas pu être faite.
 */
function vsg_au_backup( $slug ) {
	$fs = vsg_au_fs();
	if ( ! $fs ) {
		return '';
	}
	$dir = vsg_au_dir( 'vsg-sauvegarde' );
	$fs->delete( $dir, true );
	if ( ! wp_mkdir_p( $dir ) || true !== copy_dir( get_theme_root( $slug ) . '/' . $slug, $dir ) ) {
		$fs->delete( $dir, true );
		return '';
	}
	return $dir;
}

/**
 * Version précédente disponible pour un retour en arrière, ou null.
 *
 * @return array{version:string,installed:string,sha:string,date:int}|null
 */
function vsg_au_previous() {
	$b = get_option( 'vsg_update_backup' );
	if ( ! is_array( $b ) || empty( $b['version'] ) || empty( $b['installed'] ) ) {
		return null;
	}
	/* Seulement si la version installée est toujours celle qui a remplacé la copie. */
	if ( wp_get_theme( get_template() )->get( 'Version' ) !== $b['installed'] ) {
		return null;
	}
	return is_dir( vsg_au_dir( 'vsg-sauvegarde' ) ) ? $b : null;
}

/**
 * Revient à la version précédente (copie faite lors de la dernière installation).
 * La version écartée est ajoutée aux versions refusées : elle ne sera plus proposée.
 * Si l'accueil ne répond plus après le retour, la version d'avant le retour est remise.
 */
function vsg_au_rollback() {
	$b = vsg_au_previous();
	if ( ! $b ) {
		return 'Aucune version précédente disponible.';
	}
	if ( get_transient( 'vsg_au_lock' ) ) {
		return 'Une installation est déjà en cours.';
	}
	set_transient( 'vsg_au_lock', 1, 10 * MINUTE_IN_SECONDS );
	$slug = get_template();
	$fs   = vsg_au_fs();
	if ( ! $fs ) {
		delete_transient( 'vsg_au_lock' );
		return 'Accès aux fichiers du site impossible : rien n\'est changé.';
	}
	$backup = vsg_au_dir( 'vsg-sauvegarde' );
	$keep   = vsg_au_dir( 'vsg-avant-retour' );
	$fs->delete( $keep, true );
	if ( ! wp_mkdir_p( $keep ) || true !== copy_dir( get_theme_root( $slug ) . '/' . $slug, $keep ) ) {
		delete_transient( 'vsg_au_lock' );
		return 'Copie de la version actuelle impossible : rien n\'est changé.';
	}
	$who = wp_get_current_user()->display_name;
	if ( ! vsg_au_restore( $slug, $backup ) || false === vsg_site_responds() ) {
		vsg_au_restore( $slug, $keep );
		$msg = 'Retour à la ' . $b['version'] . ' impossible ou site sans réponse : la ' . $b['installed'] . ' reste en place.';
	} else {
		$refused   = vsg_au_refused();
		$refused[] = $b['sha'];
		update_option( 'vsg_update_refused', array_slice( array_values( array_unique( array_filter( $refused ) ) ), -20 ), false );
		delete_option( 'vsg_update_backup' );
		$pending = vsg_au_pending();
		if ( $pending && $pending['sha'] === $b['sha'] ) {
			delete_option( 'vsg_update_pending' );
		}
		$msg = 'Retour à la ' . $b['version'] . ' par ' . $who . ' : la ' . $b['installed'] . ' (commit ' . substr( $b['sha'], 0, 7 ) . ') est écartée et ne sera plus proposée.';
	}
	$fs->delete( $keep, true );
	delete_transient( 'vsg_au_lock' );
	vsg_journal( 'manuel', $msg );
	return $msg;
}

/** Remet en place la copie faite avant la mise à jour. */
function vsg_au_restore( $slug, $backup ) {
	$fs = vsg_au_fs();
	if ( ! $fs || ! $fs->is_dir( $backup ) ) {
		return false;
	}
	$dest = get_theme_root( $slug ) . '/' . $slug . '/';
	$fs->delete( $dest, true );
	if ( ! wp_mkdir_p( $dest ) || true !== copy_dir( $backup, $dest ) ) {
		return false;
	}
	wp_clean_themes_cache();
	return true;
}

/* Tâches planifiées : le signal de GitHub et la vérification horaire. */
add_action( 'vsg_au_event', 'vsg_au_run', 10, 2 );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'vsg_au_event', array( 'heure', '' ) ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'vsg_au_event', array( 'heure', '' ) );
		}
	}
);
add_action(
	'switch_theme',
	function () {
		wp_clear_scheduled_hook( 'vsg_au_event', array( 'heure', '' ) );
	}
);

/**
 * Signal de GitHub. Les deux formats de GitHub sont acceptés (JSON, ou formulaire avec un champ « payload »).
 * Tout signal refusé ou ignoré est noté au journal avec sa raison ; une signature invalide au plus une fois
 * par heure, pour qu'un tiers ne puisse pas remplir le journal.
 */
function vsg_au_webhook( WP_REST_Request $req ) {
	$reply = function ( $code, $message ) {
		return new WP_REST_Response(
			array(
				'ok'      => $code < 400,
				'message' => $message,
			),
			$code
		);
	};
	$raw   = $req->get_body();
	$sig   = (string) $req->get_header( 'x_hub_signature_256' );
	$calc  = 'sha256=' . hash_hmac( 'sha256', $raw, vsg_au_secret() );
	if ( ! $sig || ! hash_equals( $calc, $sig ) ) {
		if ( ! get_transient( 'vsg_au_badsig' ) ) {
			set_transient( 'vsg_au_badsig', 1, HOUR_IN_SECONDS );
			vsg_journal( 'github', 'Signal refusé : signature invalide. La clé du webhook dans GitHub doit être celle de cette page.' );
		}
		return $reply( 401, 'Signature invalide.' );
	}
	$event = (string) $req->get_header( 'x_github_event' );
	if ( 'ping' === $event ) {
		vsg_journal( 'github', 'Liaison avec GitHub établie.' );
		return $reply( 200, 'Liaison établie.' );
	}
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		parse_str( $raw, $form );
		$data = isset( $form['payload'] ) ? json_decode( (string) $form['payload'], true ) : null;
	}
	if ( 'push' !== $event ) {
		vsg_journal( 'github', 'Signal ignoré : événement « ' . sanitize_key( $event ) . ' » (seul « push » déclenche une installation).' );
		return $reply( 200, 'Ignoré : événement ' . $event . '.' );
	}
	if ( ! is_array( $data ) ) {
		vsg_journal( 'github', 'Signal ignoré : contenu illisible.' );
		return $reply( 200, 'Ignoré : contenu illisible.' );
	}
	$ref = (string) ( $data['ref'] ?? '' );
	if ( 'refs/heads/' . VSG_GITHUB_BRANCH !== $ref ) {
		vsg_journal( 'github', 'Signal ignoré : envoi sur « ' . sanitize_text_field( $ref ) . ' », seule la branche ' . VSG_GITHUB_BRANCH . ' est installée.' );
		return $reply( 200, 'Ignoré : autre branche.' );
	}
	$sha = (string) ( $data['after'] ?? '' );
	if ( preg_match( '/^[0-9a-f]{40}$/', $sha ) ) {
		/* Ce que GitHub dit du commit, montré avec la version en attente. */
		$hc   = is_array( $data['head_commit'] ?? null ) ? $data['head_commit'] : array();
		$line = strtok( (string) ( $hc['message'] ?? '' ), "\n" );
		set_transient(
			'vsg_au_info_' . $sha,
			array(
				'message' => vsg_clip( sanitize_text_field( (string) $line ), 200 ),
				'author'  => vsg_clip( sanitize_text_field( (string) ( $hc['author']['name'] ?? '' ) ), 100 ),
				'pusher'  => vsg_clip( sanitize_text_field( (string) ( $data['pusher']['name'] ?? '' ) ), 100 ),
				'date'    => vsg_clip( sanitize_text_field( (string) ( $hc['timestamp'] ?? '' ) ), 40 ),
			),
			DAY_IN_SECONDS
		);
	}
	/* Le contrôle et l'installation prennent plus de temps que GitHub n'en accorde : ils partent en tâche de fond. */
	wp_schedule_single_event( time(), 'vsg_au_event', array( 'github', $sha ) );
	spawn_cron();
	return $reply( 202, 'accord' === vsg_au_mode() ? 'Version mise en attente d\'accord.' : 'Installation programmée.' );
}

/* Point de réception du signal de GitHub : /wp-json/vsg/v1/github */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'vsg/v1',
			'/github',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // La signature est vérifiée dans le traitement.
				'callback'            => 'vsg_au_webhook',
			)
		);
	}
);

/* ---------- Apparence › Mises à jour du thème ---------- */

add_action(
	'admin_menu',
	function () {
		add_theme_page( 'Mises à jour du thème', 'Mises à jour du thème', 'edit_theme_options', 'vsg-mises-a-jour', 'vsg_au_admin_page' );
	}
);

function vsg_au_admin_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$notice = '';
	if ( isset( $_POST['vsg_au_now'] ) && check_admin_referer( 'vsg_au' ) ) {
		$notice = vsg_au_run( 'manuel' );
	}
	if ( isset( $_POST['vsg_au_rollback'] ) && check_admin_referer( 'vsg_au' ) ) {
		$notice = vsg_au_rollback();
	}
	if ( isset( $_POST['vsg_au_old'] ) && check_admin_referer( 'vsg_au' ) ) {
		$sha    = sanitize_key( wp_unslash( $_POST['vsg_au_old'] ) );
		$known  = wp_list_pluck( vsg_au_versions()['list'], 'sha' );
		$notice = in_array( $sha, $known, true ) ? vsg_au_run( 'ancienne', $sha ) : 'Cette version n\'est plus dans la liste : rechargez la page.';
	}
	if ( isset( $_POST['vsg_au_install'] ) && check_admin_referer( 'vsg_au' ) ) {
		$p      = vsg_au_pending();
		$notice = $p && hash_equals( $p['sha'], sanitize_key( wp_unslash( $_POST['vsg_au_install'] ) ) ) ? vsg_au_run( 'accord', $p['sha'] ) : 'Cette version n\'est plus en attente.';
	}
	if ( isset( $_POST['vsg_au_refuse'] ) && check_admin_referer( 'vsg_au' ) ) {
		$p = vsg_au_pending();
		if ( $p && hash_equals( $p['sha'], sanitize_key( wp_unslash( $_POST['vsg_au_refuse'] ) ) ) ) {
			$refused   = vsg_au_refused();
			$refused[] = $p['sha'];
			update_option( 'vsg_update_refused', array_slice( array_values( array_unique( $refused ) ), -20 ), false );
			delete_option( 'vsg_update_pending' );
			$notice = 'Version ' . $p['version'] . ' (commit ' . substr( $p['sha'], 0, 7 ) . ') refusée par ' . wp_get_current_user()->display_name . '.';
			vsg_journal( 'manuel', $notice );
		}
	}
	if ( isset( $_POST['vsg_au_mode'] ) && check_admin_referer( 'vsg_au' ) ) {
		$mode = 'auto' === sanitize_key( wp_unslash( $_POST['vsg_au_mode'] ) ) ? 'auto' : 'accord';
		if ( vsg_au_mode() !== $mode ) {
			update_option( 'vsg_update_mode', $mode, false );
			$notice = 'auto' === $mode ? 'Les nouvelles versions s\'installeront automatiquement.' : 'Les nouvelles versions attendront votre accord.';
			vsg_journal( 'manuel', 'Mode d\'installation : ' . ( 'auto' === $mode ? 'automatique' : 'après accord' ) . ' (' . wp_get_current_user()->display_name . ').' );
		}
	}
	if ( isset( $_POST['vsg_au_newkey'] ) && check_admin_referer( 'vsg_au' ) ) {
		delete_option( 'vsg_update_secret' );
		vsg_au_secret();
		$notice = 'Nouvelle clé créée : remplacez-la aussi dans GitHub, sinon les signaux seront refusés.';
	}
	$local = wp_get_theme( get_template() )->get( 'Version' );
	$rel   = vsg_remote_release();
	$next  = wp_next_scheduled( 'vsg_au_event', array( 'heure', '' ) );
	$p     = vsg_au_pending();
	if ( $p && ! version_compare( $p['version'], $local, '>' ) ) {
		delete_option( 'vsg_update_pending' );
		$p = null;
	}
	$mode = vsg_au_mode();
	?>
	<div class="wrap">
		<h1>Mises à jour du thème</h1>
		<?php if ( $notice ) : ?><div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
		<p>Version installée : <strong><?php echo esc_html( $local ); ?></strong> · Version sur GitHub : <strong><?php echo esc_html( $rel['version'] ? $rel['version'] : '?' ); ?></strong><?php echo ( ! $rel['version'] && ! empty( $rel['error'] ) ) ? ' (' . esc_html( $rel['error'] ) . ')' : ''; ?>
		<?php if ( $next ) : ?>· Prochaine vérification automatique : <?php echo esc_html( wp_date( 'H:i', $next ) ); ?><?php endif; ?></p>
		<?php if ( $p ) : ?>
			<div class="vsg-box vsg-pending">
				<h2 class="vsg-mt0">Version <?php echo esc_html( $p['version'] ); ?> en attente de votre accord</h2>
				<table class="form-table" role="presentation">
					<?php if ( $p['message'] ) : ?><tr><th scope="row">Message du commit</th><td><?php echo esc_html( $p['message'] ); ?></td></tr><?php endif; ?>
					<?php if ( $p['author'] ) : ?><tr><th scope="row">Auteur indiqué</th><td><?php echo esc_html( $p['author'] ); ?></td></tr><?php endif; ?>
					<?php if ( $p['pusher'] ) : ?><tr><th scope="row">Envoyé par le compte GitHub</th><td><?php echo esc_html( $p['pusher'] ); ?></td></tr><?php endif; ?>
					<tr><th scope="row">Reçue le</th><td><?php echo esc_html( wp_date( 'j/m/Y à H:i', (int) $p['received'] ) ); ?></td></tr>
					<tr><th scope="row">Commit</th><td><a href="<?php echo esc_url( vsg_au_commit_url( $p['sha'] ) ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( substr( $p['sha'], 0, 7 ) ); ?></code> : voir ce qui a changé<span class="screen-reader-text"> (nouvel onglet)</span></a></td></tr>
				</table>
				<p class="description">Le nom de l'auteur ne prouve rien : n'importe qui peut l'écrire. La vraie question est : attendiez-vous une version maintenant ? Sinon, refusez-la et vérifiez l'accès au compte GitHub.</p>
				<form method="post" class="vsg-pending__actions">
					<?php wp_nonce_field( 'vsg_au' ); ?>
					<button class="button button-primary" name="vsg_au_install" value="<?php echo esc_attr( $p['sha'] ); ?>">Installer la <?php echo esc_html( $p['version'] ); ?></button>
					<button class="button vsg-btn-danger" name="vsg_au_refuse" value="<?php echo esc_attr( $p['sha'] ); ?>" onclick="return confirm('Refuser cette version ? Elle ne sera plus proposée.');">Refuser</button>
				</form>
			</div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'vsg_au' ); ?>
			<p><button class="button<?php echo $p ? '' : ' button-primary'; ?>" name="vsg_au_now" value="1"><?php echo 'auto' === $mode ? 'Vérifier et installer maintenant' : 'Vérifier maintenant'; ?></button></p>
		</form>
		<?php $prev = vsg_au_previous(); ?>
		<?php if ( $prev ) : ?>
			<h2>Revenir à la version précédente</h2>
			<p>La <?php echo esc_html( $prev['installed'] ); ?> a été installée le <?php echo esc_html( wp_date( 'j/m/Y à H:i', (int) $prev['date'] ) ); ?>. En cas de problème, vous pouvez remettre la <?php echo esc_html( $prev['version'] ); ?> telle qu'elle était juste avant.<?php echo version_compare( $prev['version'], $prev['installed'], '<' ) ? ' La ' . esc_html( $prev['installed'] ) . ' ne sera alors plus proposée ; une version corrigée le sera.' : ''; ?></p>
			<form method="post">
				<?php wp_nonce_field( 'vsg_au' ); ?>
				<p><button class="button" name="vsg_au_rollback" value="1" onclick="return confirm('Revenir à la <?php echo esc_js( $prev['version'] ); ?> ?');">Revenir à la <?php echo esc_html( $prev['version'] ); ?></button></p>
			</form>
		<?php endif; ?>

		<?php
		$versions = vsg_au_versions();
		$older    = array_values(
			array_filter(
				$versions['list'],
				function ( $v ) {
					return vsg_au_old_allowed( $v['version'] );
				}
			)
		);
		?>
		<h2>Installer une version antérieure</h2>
		<p>Pour revenir à une version précise publiée sur GitHub. Même sécurité qu'une mise à jour : copie de la version en place, contrôle du site, remise en place automatique en cas de panne, inscription au journal. La version la plus récente vous sera ensuite de nouveau proposée : refusez-la si vous voulez rester sur l'ancienne.</p>
		<?php if ( 'auto' === $mode ) : ?><p class="description"><strong>Mode automatique :</strong> la version la plus récente serait réinstallée dans l'heure. Passez d'abord en mode « Après mon accord » (plus bas) pour rester sur une version antérieure.</p><?php endif; ?>
		<?php if ( $versions['error'] ) : ?>
			<p class="description">Liste indisponible pour le moment : <?php echo esc_html( $versions['error'] ); ?>.</p>
		<?php elseif ( ! $older ) : ?>
			<p class="description">Aucune version réinstallable pour l'instant (à partir de la <?php echo esc_html( VSG_AU_OLDEST ); ?>).</p>
		<?php else : ?>
			<form method="post">
				<?php wp_nonce_field( 'vsg_au' ); ?>
				<table class="widefat striped vsg-versions">
					<thead><tr><th scope="col">Version</th><th scope="col">Publiée le</th><th scope="col">Contenu</th><th scope="col"><span class="screen-reader-text">Action</span></th></tr></thead>
					<tbody>
					<?php foreach ( $older as $v ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $v['version'] ); ?></strong><br><a href="<?php echo esc_url( vsg_au_commit_url( $v['sha'] ) ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( substr( $v['sha'], 0, 7 ) ); ?></code><span class="screen-reader-text"> (nouvel onglet)</span></a></td>
							<td><?php echo $v['date'] ? esc_html( wp_date( 'j/m/Y à H:i', $v['date'] ) ) : ''; ?></td>
							<td><?php echo esc_html( $v['title'] ); ?></td>
							<td>
							<?php if ( $v['version'] === $local ) : ?>
								<strong>Installée</strong>
							<?php else : ?>
								<button class="button" name="vsg_au_old" value="<?php echo esc_attr( $v['sha'] ); ?>" onclick="return confirm('Installer la <?php echo esc_js( $v['version'] ); ?> à la place de la <?php echo esc_js( $local ); ?> ?');">Installer la <?php echo esc_html( $v['version'] ); ?></button>
							<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</form>
		<?php endif; ?>

		<h2>Mode d'installation</h2>
		<form method="post">
			<?php wp_nonce_field( 'vsg_au' ); ?>
			<fieldset>
				<legend class="screen-reader-text">Mode d'installation</legend>
				<p><label><input type="radio" name="vsg_au_mode" value="accord" <?php checked( 'accord', $mode ); ?>> <strong>Après mon accord</strong> : une nouvelle version est contrôlée puis mise en attente ; vous êtes prévenu par mail (<?php echo esc_html( get_option( 'admin_email' ) ); ?>) et vous choisissez de l'installer ou de la refuser.</label></p>
				<p><label><input type="radio" name="vsg_au_mode" value="auto" <?php checked( 'auto', $mode ); ?>> <strong>Automatique</strong> : une nouvelle version contrôlée est installée dans la minute, sans intervention.</label></p>
			</fieldset>
			<p><button class="button">Enregistrer le mode</button></p>
		</form>

		<h2>Signal de GitHub à chaque nouvelle version</h2>
		<p>À régler une seule fois dans GitHub : dépôt <strong><?php echo esc_html( VSG_GITHUB_REPO ); ?></strong> › <em>Settings</em> › <em>Webhooks</em> › <em>Add webhook</em>.</p>
		<table class="form-table" role="presentation">
			<tr><th scope="row">Payload URL</th><td><input type="text" class="large-text code" readonly value="<?php echo esc_attr( rest_url( 'vsg/v1/github' ) ); ?>" onclick="this.select()"></td></tr>
			<tr><th scope="row">Content type</th><td><code>application/json</code></td></tr>
			<tr><th scope="row">Secret</th><td>
				<span class="vsg-keyrow">
					<input type="password" id="vsg-au-secret" class="large-text code" readonly autocomplete="off" value="<?php echo esc_attr( vsg_au_secret() ); ?>">
					<button type="button" class="button vsg-eye" id="vsg-au-eye" aria-label="Afficher la clé" title="Afficher la clé"><span class="dashicons dashicons-visibility vsg-eye__icon" aria-hidden="true"></span></button>
					<button type="button" class="button" id="vsg-au-copy">Copier</button>
				</span>
				<p class="description">La clé reste masquée : « Copier » suffit pour la coller dans GitHub.</p>
			</td></tr>
			<tr><th scope="row">Événements</th><td><em>Just the push event</em>, case <em>Active</em> cochée.</td></tr>
		</table>
		<p class="description">Après « Add webhook », GitHub envoie un premier signal de test : la ligne « Liaison avec GitHub établie » doit apparaître dans le journal ci-dessous.</p>
		<form method="post" onsubmit="return confirm('Créer une nouvelle clé ? Il faudra la recopier dans GitHub.');">
			<?php wp_nonce_field( 'vsg_au' ); ?>
			<p><button class="button" name="vsg_au_newkey" value="1">Créer une nouvelle clé</button></p>
		</form>

		<h2>Journal</h2>
		<?php echo vsg_journal_table(); ?>
	</div>
	<?php
}

/* Bandeau dans l'administration tant qu'une version attend un accord. */
add_action(
	'admin_notices',
	function () {
		$p = vsg_au_pending();
		if ( ! $p || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'appearance_page_vsg-mises-a-jour' === $screen->id ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Thème : la version ' . esc_html( $p['version'] ) . ' attend votre accord.</strong> <a href="' . esc_url( admin_url( 'themes.php?page=vsg-mises-a-jour' ) ) . '">Voir et décider</a></p></div>';
	}
);
