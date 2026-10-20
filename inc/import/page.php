<?php
/**
 * Page Agenda › Importer / exporter : envoi du fichier, aperçu, enregistrement, export, vidage.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=evenement', 'Importer / exporter l\'agenda', 'Importer / exporter', 'edit_others_posts', 'vsg-import', 'vsg_import_page' );
	}
);

function vsg_import_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( 'Accès refusé.' );
	}
	$user   = get_current_user_id();
	$key    = 'vsg_import_' . $user;
	$notice = '';
	$items  = array();
	$errors = array();
	$stage  = 'upload';
	$purge  = array();

	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && check_admin_referer( 'vsg_import', 'vsg_import_nonce' ) ) {
		$action = sanitize_key( $_POST['vsg_action'] ?? '' );
		if ( 'preview' === $action && ! empty( $_FILES['vsg_file']['tmp_name'] ) ) {
			$f = $_FILES['vsg_file'];
			if ( UPLOAD_ERR_OK !== (int) $f['error'] || ! is_uploaded_file( $f['tmp_name'] ) ) {
				$notice = 'Le fichier n\'a pas pu être reçu. Réessayez.';
			} elseif ( $f['size'] > VSG_IMPORT_MAX ) {
				$notice = 'Fichier trop lourd (2 Mo maximum).';
			} else {
				$ext  = strtolower( pathinfo( $f['name'], PATHINFO_EXTENSION ) );
				$rows = 'csv' === $ext ? vsg_import_read_csv( $f['tmp_name'] ) : ( 'xlsx' === $ext ? vsg_import_read_xlsx( $f['tmp_name'] ) : new WP_Error( 'ext', 'Format non reconnu : utilisez un fichier .xlsx ou .csv.' ) );
				if ( is_wp_error( $rows ) ) {
					$notice = $rows->get_error_message();
				} else {
					$group                  = sanitize_key( $_POST['vsg_group'] ?? '' );
					$group                  = isset( vsg_event_types()[ $group ] ) ? $group : '';
					list( $items, $errors ) = vsg_import_parse( $rows, $group );
					set_transient( $key, $items, HOUR_IN_SECONDS );
					$stage = 'preview';
				}
			}
		} elseif ( 'purge-count' === $action || 'purge-apply' === $action ) {
			$pp    = sanitize_text_field( wp_unslash( $_POST['vsg_purge_place'] ?? '' ) );
			$pt    = sanitize_text_field( wp_unslash( $_POST['vsg_purge_type'] ?? '' ) );
			$pp    = ( '' === $pp || '-' === $pp || in_array( $pp, vsg_import_places(), true ) ) ? $pp : '';
			$pt    = ( '' === $pt || '-' === $pt || isset( vsg_event_types()[ $pt ] ) ) ? $pt : '';
			$pf    = 'all' === ( $_POST['vsg_purge_period'] ?? '' ) ? '' : vsg_today();
			$ids   = vsg_purge_ids( $pp, $pt, $pf );
			$purge = array(
				'place'  => $pp,
				'type'   => $pt,
				'period' => $pf ? 'future' : 'all',
				'ids'    => $ids,
				'label'  => vsg_purge_label( $pp, $pt, $pf ),
			);
			if ( 'purge-apply' === $action ) {
				$n = 0;
				foreach ( $ids as $id ) {
					if ( current_user_can( 'delete_post', $id ) && wp_trash_post( $id ) ) {
						++$n;
					}
				}
				$notice = sprintf( '%d rendez-vous mis à la corbeille (%s). Ils restent récupérables 30 jours dans Agenda › Corbeille.', $n, $purge['label'] );
			} else {
				$stage = 'purge';
			}
		} elseif ( 'apply' === $action ) {
			$items = get_transient( $key );
			if ( ! $items ) {
				$notice = 'L\'aperçu a expiré : rechargez le fichier.';
			} else {
				list( $c, $u ) = vsg_import_apply( $items );
				delete_transient( $key );
				update_option(
					'vsg_import_log',
					array(
						'when'    => current_time( 'mysql' ),
						'who'     => wp_get_current_user()->display_name,
						'created' => $c,
						'updated' => $u,
					)
				);
				$notice = sprintf( 'Import terminé : %d rendez-vous créés, %d mis à jour.', $c, $u );
				$items  = array();
			}
		}
	}
	$log    = get_option( 'vsg_import_log' );
	$places = vsg_import_places();
	$types  = vsg_event_types();
	$export = wp_nonce_url( admin_url( 'admin-post.php?action=vsg_export' ), 'vsg_export' );
	?>
	<div class="wrap">
		<h1>Importer l'agenda</h1>
		<?php if ( $notice ) : ?><div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>

		<?php if ( 'purge' === $stage ) : ?>
			<h2>Vider l'agenda : confirmation</h2>
			<p>Sélection : <strong><?php echo esc_html( $purge['label'] ); ?></strong>.</p>
			<?php if ( ! $purge['ids'] ) : ?>
				<p>Aucun rendez-vous ne correspond à cette sélection.</p>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=evenement&page=vsg-import' ) ); ?>">Retour</a></p>
			<?php else : ?>
				<p><strong><?php echo count( $purge['ids'] ); ?></strong> rendez-vous, publics et internes, vont partir à la corbeille. Ils restent récupérables 30 jours.</p>
				<details class="vsg-w1100 vsg-mb12b"><summary>Voir la liste</summary>
					<table class="widefat striped vsg-mt8"><thead><tr><th>Date</th><th>Titre</th><th>Lieu</th><th>Visibilité</th></tr></thead><tbody>
					<?php foreach ( $purge['ids'] as $id ) : ?>
						<tr><td><?php echo esc_html( gmdate( 'd/m/Y', strtotime( vsg_meta( $id, 'date' ) . ' 12:00:00' ) ) ); ?></td><td><?php echo esc_html( get_the_title( $id ) ); ?></td><td><?php echo esc_html( vsg_place_label( get_post( $id ) ) ); ?></td><td><?php echo 'private' === get_post_status( $id ) ? 'Interne' : 'Public'; ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				</details>
				<form method="post">
					<?php wp_nonce_field( 'vsg_import', 'vsg_import_nonce' ); ?>
					<input type="hidden" name="vsg_action" value="purge-apply">
					<input type="hidden" name="vsg_purge_place" value="<?php echo esc_attr( $purge['place'] ); ?>">
					<input type="hidden" name="vsg_purge_type" value="<?php echo esc_attr( $purge['type'] ); ?>">
					<input type="hidden" name="vsg_purge_period" value="<?php echo esc_attr( $purge['period'] ); ?>">
					<button class="button button-primary button-hero vsg-btn-danger">Mettre ces <?php echo count( $purge['ids'] ); ?> rendez-vous à la corbeille</button>
					<a class="button button-hero" href="<?php echo esc_url( admin_url( 'edit.php?post_type=evenement&page=vsg-import' ) ); ?>">Annuler</a>
				</form>
			<?php endif; ?>

		<?php elseif ( 'preview' === $stage ) : ?>
			<?php
			$new = 0;
			$upd = 0;
			foreach ( $items as $it ) {
				if ( vsg_import_find( $it['date'], $it['title'] ) ) {
					++$upd;
				} else {
					++$new;
				}
			}
			?>
			<h2>Aperçu</h2>
			<p><strong><?php echo (int) $new; ?></strong> rendez-vous à créer, <strong><?php echo (int) $upd; ?></strong> à mettre à jour, <strong><?php echo count( $errors ); ?></strong> ligne(s) ignorée(s).</p>
			<?php if ( $errors ) : ?>
				<div class="notice notice-warning"><p><strong>Lignes ignorées</strong> (corrigez-les dans le fichier et recommencez, ou importez sans elles) :</p><ul class="vsg-list">
				<?php foreach ( $errors as $e ) : ?><li><?php echo esc_html( $e ); ?></li><?php endforeach; ?></ul></div>
			<?php endif; ?>
			<?php if ( $items ) : ?>
				<table class="widefat striped vsg-w1100">
					<thead><tr><th>Date</th><th>Heure</th><th>Titre</th><th>Lieu</th><th>Type</th><th>Visibilité</th><th>Action</th></tr></thead>
					<tbody>
					<?php foreach ( $items as $it ) : ?>
						<tr>
							<td><?php echo esc_html( gmdate( 'd/m/Y', strtotime( $it['date'] . ' 12:00:00' ) ) ); ?></td>
							<td><?php echo esc_html( $it['time'] ); ?></td>
							<td><?php echo esc_html( $it['title'] ); ?><?php echo $it['lieu'] ? '<br><span class="vsg-help">' . esc_html( $it['lieu'] ) . '</span>' : ''; ?></td>
							<td><?php echo esc_html( array_search( $it['place'], $places, true ) ); ?></td>
							<td><?php echo esc_html( $types[ $it['type'] ][0] ?? '—' ); ?></td>
							<td><?php echo $it['private'] ? 'Interne' : 'Public'; ?></td>
							<td><?php echo vsg_import_find( $it['date'], $it['title'] ) ? 'Mise à jour' : 'Création'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<form class="vsg-mt16" method="post">
					<?php wp_nonce_field( 'vsg_import', 'vsg_import_nonce' ); ?>
					<input type="hidden" name="vsg_action" value="apply">
					<button class="button button-primary button-hero">Importer ces <?php echo count( $items ); ?> rendez-vous</button>
					<a class="button button-hero" href="<?php echo esc_url( admin_url( 'edit.php?post_type=evenement&page=vsg-import' ) ); ?>">Annuler</a>
				</form>
			<?php else : ?>
				<p>Aucune ligne valide dans ce fichier.</p>
			<?php endif; ?>

		<?php else : ?>
			<div class="vsg-twocol">
				<div>
					<h2>1. Choisir le fichier</h2>
					<p>Un fichier Excel (.xlsx) ou .csv, une ligne par rendez-vous, avec la première ligne d'en-têtes. Deux fichiers, jamais mélangés : l'<strong>agenda paroissial</strong> rempli par le secrétariat (modèle « agenda paroissial »), et le <strong>programme de chaque communauté</strong>, rempli par la communauté (modèle « communauté »), importé en la choisissant ci-dessous.</p>
					<form method="post" enctype="multipart/form-data">
						<?php wp_nonce_field( 'vsg_import', 'vsg_import_nonce' ); ?>
						<input type="hidden" name="vsg_action" value="preview">
						<p><label for="vsg_group"><strong>Quel fichier importez-vous ?</strong></label><br>
							<select name="vsg_group" id="vsg_group">
								<option value="">Agenda paroissial (fichier du secrétariat)</option>
								<?php foreach ( vsg_group_types() as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select></p>
						<p><input type="file" name="vsg_file" accept=".xlsx,.csv" required></p>
						<p><button class="button button-primary button-hero">Vérifier le fichier</button></p>
					</form>
					<p class="description">Rien n'est enregistré à cette étape : vous verrez d'abord un aperçu et la liste des lignes à corriger.</p>

					<h2 class="vsg-mt32">Exporter l'agenda</h2>
					<p>Récupère tous les rendez-vous du site dans un fichier que vous pouvez corriger dans Excel puis réimporter, ou utiliser pour le calendrier papier.</p>
					<p><a class="button" href="<?php echo esc_url( $export ); ?>">Télécharger l'agenda (.csv)</a>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'from', current_time( 'Y-m-d' ), $export ) ); ?>">Seulement à venir</a></p>
					<h2 class="vsg-mt32">Vider l'agenda</h2>
					<p>Pour repartir de zéro avant un nouvel import : retire d'un coup les rendez-vous d'un lieu ou d'un groupe, publics et internes. Ils partent à la corbeille (récupérables 30 jours). Le nombre exact est affiché avant confirmation.</p>
					<form method="post">
						<?php wp_nonce_field( 'vsg_import', 'vsg_import_nonce' ); ?>
						<input type="hidden" name="vsg_action" value="purge-count">
						<p><label for="vsg_purge_place"><strong>Lieu</strong></label><br>
							<select name="vsg_purge_place" id="vsg_purge_place">
								<option value="">Tous les lieux</option>
								<?php foreach ( $places as $label => $slug ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
								<option value="-">Sans paroisse ni chapelle</option>
							</select></p>
						<p><label for="vsg_purge_type"><strong>Groupe ou communauté</strong></label><br>
							<select name="vsg_purge_type" id="vsg_purge_type">
								<option value="">Tous les groupes</option>
								<?php echo vsg_event_type_options(); ?>
								<option value="-">Sans groupe</option>
							</select></p>
						<p><label for="vsg_purge_period"><strong>Période</strong></label><br>
							<select name="vsg_purge_period" id="vsg_purge_period">
								<option value="future">Rendez-vous à venir seulement</option>
								<option value="all">Tous, passés compris</option>
							</select></p>
						<p><button class="button">Compter les rendez-vous concernés</button></p>
					</form>
					<?php if ( $log ) : ?>
						<p class="description">Dernier import : <?php echo esc_html( $log['when'] ); ?> par <?php echo esc_html( $log['who'] ); ?> (<?php echo (int) $log['created']; ?> créés, <?php echo (int) $log['updated']; ?> mis à jour).</p>
					<?php endif; ?>
				</div>
				<div class="vsg-box">
					<h2 class="vsg-mt0">Programme d'une communauté</h2>
					<p>Modèle « communauté » : <strong>Date</strong> · Date de fin · Heure · <strong>Activité</strong> · Thème · Lieu. Choisissez la communauté dans la liste : tous les rendez-vous du fichier lui sont rattachés et s'affichent sur sa page, pas dans l'agenda du site.</p>
					<p class="description">Lieu : « Église N.D. Lourdes », « Chapelle St Louis »… sont reconnus et rangés dans la bonne paroisse ou chapelle. Tout autre lieu (En ligne, Chevilly-Larue…) est affiché tel quel. Date de fin : pour une retraite ou une neuvaine, le site affiche « Du 11 au 13 septembre ».</p>
					<p><a class="button" href="<?php echo esc_url( VSG_URI . '/assets/modeles/modele-agenda-groupe.xlsx' ); ?>">Télécharger le modèle « communauté »</a></p>
					<h2>Agenda paroissial</h2>
					<p>Tout ce qui est lié à la paroisse : messes particulières, sacrements, caté, aumônerie, jeunes, chorales, servants, brocante, diocèse, équipes.</p>
					<p><a class="button" href="<?php echo esc_url( VSG_URI . '/assets/modeles/modele-agenda.xlsx' ); ?>">Télécharger le modèle « agenda paroissial »</a></p>
					<ol class="vsg-ml">
						<li><strong>Date</strong> : 15/01/2027</li>
						<li><strong>Date de fin</strong> : pour un rendez-vous sur plusieurs jours (facultatif)</li>
						<li><strong>Heure</strong> : 17h15 (vide si toute la journée)</li>
						<li><strong>Titre</strong></li>
						<li><strong>Paroisse ou chapelle</strong> : <?php echo esc_html( implode( ' · ', array_keys( $places ) ) ); ?></li>
						<li><strong>Lieu précis</strong> : grande salle, cuisine… (facultatif)</li>
						<li><strong>Type</strong> (facultatif, sert au filtre « Activité ») : 
						<?php
						echo esc_html(
							implode(
								' · ',
								array_column(
									array_filter(
										$types,
										function ( $t ) {
											return empty( $t[2] );
										}
									),
									0
								)
							)
						);
						?>
																								</li>
						<li><strong>Interne</strong> : oui / non. « oui » = visible seulement par les personnes connectées.</li>
						<li><strong>Description</strong> (facultatif)</li>
					</ol>
					<p class="description">Un rendez-vous qui existe déjà (même date, même titre) est mis à jour, pas dupliqué. L'import ne supprime jamais rien : pour retirer des rendez-vous, utilisez « Vider l'agenda » ou Agenda › Corbeille.</p>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
