<?php
/**
 * Installation automatique des nouvelles versions du thème, sans attendre WordPress.
 *
 * - Signal de GitHub (webhook) à chaque envoi sur main : installation dans la minute.
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
	/* L'installation prend plus de temps que GitHub n'en accorde : elle part en tâche de fond. */
	wp_schedule_single_event( time(), 'vsg_au_event', array( 'github', $sha ) );
	spawn_cron();
	return $reply( 202, 'Installation programmée.' );
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
		<p>Version installée : <strong><?php echo esc_html( $local ); ?></strong> · Version sur GitHub : <strong><?php echo esc_html( $rel['version'] ? $rel['version'] : '?' ); ?></strong><?php echo ( ! $rel['version'] && ! empty( $rel['error'] ) ) ? ' (' . esc_html( $rel['error'] ) . ')' : ''; ?>
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
