<?php
/**
 * Installation automatique des nouvelles versions du thème, sans attendre WordPress.
 *
 * - Signal de GitHub (webhook) à chaque envoi sur main : installation dans la minute.
 *   Le signal est signé avec une clé secrète (en-tête X-Hub-Signature-256) : sans la
 *   bonne clé, il est refusé.
 * - Filet de sécurité : vérification et installation toutes les heures.
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


/**
 * Vérifie le style.css d'un commit : version lisible et fichier complet.
 *
 * @return string Version, ou '' si le fichier ne convient pas.
 */
function vsg_au_check_css( $sha ) {
	$ref = $sha ? $sha : VSG_GITHUB_BRANCH;
	$res = wp_remote_get( 'https://raw.githubusercontent.com/' . VSG_GITHUB_REPO . '/' . $ref . '/style.css' . ( $sha ? '' : '?t=' . time() ), array( 'timeout' => 15 ) );
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return '';
	}
	$css = wp_remote_retrieve_body( $res );
	/* Feuille de style complète : en-tête du thème et règles CSS (une feuille vide ou tronquée est refusée). */
	if ( strlen( $css ) < 20000 || false === stripos( $css, 'Theme Name:' ) || substr_count( $css, '{' ) < 200 ) {
		return '';
	}
	return preg_match( '/^\s*Version:\s*([0-9][0-9.]*)/mi', $css, $m ) ? $m[1] : '';
}

/**
 * Cherche la dernière version et l'installe si elle est plus récente et complète.
 *
 * @param string $source « github », « heure » ou « manuel ».
 * @param string $sha    Commit annoncé par GitHub (facultatif).
 */
function vsg_au_run( $source = 'heure', $sha = '' ) {
	if ( get_transient( 'vsg_au_lock' ) ) {
		return 'Une installation est déjà en cours.';
	}
	set_transient( 'vsg_au_lock', 1, 10 * MINUTE_IN_SECONDS );

	$slug  = get_template();
	$local = wp_get_theme( $slug )->get( 'Version' );
	if ( $sha && preg_match( '/^[0-9a-f]{40}$/', $sha ) ) {
		$rel = array(
			'version' => vsg_au_check_css( $sha ),
			'sha'     => $sha,
		);
	} else {
		delete_site_transient( 'vsg_remote_release' );
		$rel = vsg_remote_release( true );
		if ( $rel['version'] ) {
			$rel['version'] = vsg_au_check_css( $rel['sha'] ) ? $rel['version'] : '';
		}
	}

	if ( ! $rel['version'] || empty( $rel['sha'] ) ) {
		delete_transient( 'vsg_au_lock' );
		$msg = 'Version de GitHub illisible ou feuille de style incomplète : rien n\'est installé.';
		vsg_journal( $source, $msg );
		return $msg;
	}
	if ( ! version_compare( $rel['version'], $local, '>' ) ) {
		delete_transient( 'vsg_au_lock' );
		if ( 'heure' !== $source ) {
			vsg_journal( $source, 'Déjà à jour (' . $local . ').' );
		}
		return 'Le thème est déjà à jour (' . $local . ').';
	}

	/* La version vérifiée devient celle que WordPress proposera, avec l'archive de ce commit. */
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

	wp_clean_themes_cache();
	$now = wp_get_theme( $slug )->get( 'Version' );
	if ( true === $result && version_compare( $now, $local, '>' ) ) {
		$msg = 'Mise à jour installée : ' . $local . ' → ' . $now . '.';
		/* Le site doit répondre avec la nouvelle version ; sinon on remet l'ancienne. */
		$ok = vsg_site_responds();
		if ( false === $ok && $backup ) {
			$msg = vsg_au_restore( $slug, $backup )
				? 'La ' . $now . ' a été installée mais le site ne répondait plus : la ' . $local . ' a été remise en place automatiquement.'
				: 'La ' . $now . ' a été installée mais le site ne répond plus, et la remise en place de la ' . $local . ' a échoué. Réinstaller le zip de la ' . $local . ' depuis Apparence › Thèmes.';
		} elseif ( null === $ok ) {
			$msg .= ' (Contrôle de l\'accueil impossible depuis le serveur : vérifier le site.)';
		}
	} else {
		$err = is_wp_error( $result ) ? $result->get_error_message() : implode( ' ', array_map( 'wp_strip_all_tags', (array) $upgrader->skin->get_upgrade_messages() ) );
		$msg = 'Échec de l\'installation de la ' . $rel['version'] . ( $err ? ' : ' . $err : '.' ) . ' Le thème ' . $local . ' reste en place.';
	}
	delete_transient( 'vsg_au_lock' );
	vsg_journal( $source, $msg );
	return $msg;
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
 * Copie du thème installé avant la mise à jour, dans wp-content/upgrade/vsg-sauvegarde/.
 *
 * @return string Dossier de la copie, ou '' si elle n'a pas pu être faite.
 */
function vsg_au_backup( $slug ) {
	$fs = vsg_au_fs();
	if ( ! $fs ) {
		return '';
	}
	$dir = trailingslashit( WP_CONTENT_DIR ) . 'upgrade/vsg-sauvegarde/';
	$fs->delete( $dir, true );
	if ( ! wp_mkdir_p( $dir ) || true !== copy_dir( get_theme_root( $slug ) . '/' . $slug, $dir ) ) {
		$fs->delete( $dir, true );
		return '';
	}
	return $dir;
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
				'callback'            => function ( WP_REST_Request $req ) {
					$sig  = (string) $req->get_header( 'x_hub_signature_256' );
					$calc = 'sha256=' . hash_hmac( 'sha256', $req->get_body(), vsg_au_secret() );
					if ( ! $sig || ! hash_equals( $calc, $sig ) ) {
						return new WP_REST_Response(
							array(
								'ok'      => false,
								'message' => 'Signature invalide.',
							),
							401
						);
					}
					$event = (string) $req->get_header( 'x_github_event' );
					if ( 'ping' === $event ) {
						vsg_journal( 'github', 'Liaison avec GitHub établie.' );
						return new WP_REST_Response(
							array(
								'ok'      => true,
								'message' => 'Liaison établie.',
							),
							200
						);
					}
					$data = json_decode( $req->get_body(), true );
					if ( 'push' !== $event || ! is_array( $data ) || 'refs/heads/' . VSG_GITHUB_BRANCH !== ( $data['ref'] ?? '' ) ) {
						return new WP_REST_Response(
							array(
								'ok'      => true,
								'message' => 'Ignoré.',
							),
							200
						);
					}
					$sha = (string) ( $data['after'] ?? '' );
					/* L'installation prend plus de temps que GitHub n'en accorde : elle part en tâche de fond. */
					wp_schedule_single_event( time(), 'vsg_au_event', array( 'github', $sha ) );
					spawn_cron();
					return new WP_REST_Response(
						array(
							'ok'      => true,
							'message' => 'Installation programmée.',
						),
						202
					);
				},
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
	if ( isset( $_POST['vsg_au_newkey'] ) && check_admin_referer( 'vsg_au' ) ) {
		delete_option( 'vsg_update_secret' );
		vsg_au_secret();
		$notice = 'Nouvelle clé créée : remplacez-la aussi dans GitHub, sinon les signaux seront refusés.';
	}
	$local = wp_get_theme( get_template() )->get( 'Version' );
	$rel   = vsg_remote_release();
	$next  = wp_next_scheduled( 'vsg_au_event', array( 'heure', '' ) );
	?>
	<div class="wrap">
		<h1>Mises à jour du thème</h1>
		<?php if ( $notice ) : ?><div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
		<p>Version installée : <strong><?php echo esc_html( $local ); ?></strong> · Version sur GitHub : <strong><?php echo esc_html( $rel['version'] ? $rel['version'] : '?' ); ?></strong>
		<?php if ( $next ) : ?>· Prochaine vérification automatique : <?php echo esc_html( wp_date( 'H:i', $next ) ); ?><?php endif; ?></p>
		<form method="post">
			<?php wp_nonce_field( 'vsg_au' ); ?>
			<p><button class="button button-primary" name="vsg_au_now" value="1">Vérifier et installer maintenant</button></p>
		</form>

		<h2>Installation immédiate à chaque nouvelle version</h2>
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
