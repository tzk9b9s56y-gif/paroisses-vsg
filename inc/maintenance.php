<?php
/**
 * Mode maintenance, sans extension.
 *
 * Réglage : Apparence › Maintenance (administrateurs).
 * Quand il est actif, les visiteurs voient une page d'attente (code HTTP 503 :
 * les moteurs de recherche reviennent plus tard sans déréférencer le site).
 * Les personnes connectées qui rédigent du contenu voient le site normalement,
 * avec un rappel rouge dans la barre d'administration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Message proposé par défaut. */
function vsg_maint_default_message() {
	return "Le site est en maintenance et sera de nouveau disponible très bientôt.\n"
		. "Les messes et les permanences ont lieu normalement.\n"
		. 'Accueil paroissial : 01 43 89 02 97 · secretariat@ndl-sg-eglises94.fr';
}

/** Réglages enregistrés, complétés par les valeurs par défaut. */
function vsg_maint_settings() {
	$s = get_option( 'vsg_maintenance', array() );
	return wp_parse_args(
		is_array( $s ) ? $s : array(),
		array(
			'on'      => 0,
			'message' => vsg_maint_default_message(),
			'hours'   => 1,
		)
	);
}

function vsg_maint_is_on() {
	return (bool) vsg_maint_settings()['on'];
}

/* ---------- Page de réglage ---------- */

add_action(
	'admin_menu',
	function () {
		add_theme_page( 'Mode maintenance', 'Maintenance', 'edit_theme_options', 'vsg-maintenance', 'vsg_maint_admin_page' );
	}
);

function vsg_maint_admin_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$saved = false;
	if ( isset( $_POST['vsg_maint_save'] ) && check_admin_referer( 'vsg_maint' ) ) {
		$msg = sanitize_textarea_field( wp_unslash( $_POST['vsg_maint_message'] ?? '' ) );
		update_option(
			'vsg_maintenance',
			array(
				'on'      => empty( $_POST['vsg_maint_on'] ) ? 0 : 1,
				'message' => '' !== trim( $msg ) ? $msg : vsg_maint_default_message(),
				'hours'   => empty( $_POST['vsg_maint_hours'] ) ? 0 : 1,
			)
		);
		$saved = true;
	}
	$s = vsg_maint_settings();
	?>
	<div class="wrap">
		<h1>Mode maintenance</h1>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success"><p>Réglages enregistrés. Le mode maintenance est <strong><?php echo $s['on'] ? 'activé' : 'désactivé'; ?></strong>.</p></div>
		<?php endif; ?>
		<p>Pendant la maintenance, les visiteurs voient seulement la page d'attente ci-dessous. Vous, et toute personne connectée qui rédige du contenu, voyez le site normalement.</p>
		<form method="post">
			<?php wp_nonce_field( 'vsg_maint' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">État</th>
					<td><label><input type="checkbox" name="vsg_maint_on" value="1" <?php checked( $s['on'] ); ?>> Activer le mode maintenance</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="vsg_maint_message">Message</label></th>
					<td>
						<textarea id="vsg_maint_message" name="vsg_maint_message" rows="5" class="large-text"><?php echo esc_textarea( $s['message'] ); ?></textarea>
						<p class="description">Chaque ligne s'affiche sur une ligne. Laissez vide pour revenir au message par défaut.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Horaires</th>
					<td><label><input type="checkbox" name="vsg_maint_hours" value="1" <?php checked( $s['hours'] ); ?>> Afficher les horaires des messes de chaque église et chapelle</label></td>
				</tr>
			</table>
			<p>
				<button class="button button-primary" name="vsg_maint_save" value="1">Enregistrer</button>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'vsg-maintenance', 'apercu', home_url( '/' ) ) ); ?>" target="_blank" rel="noopener">Aperçu de la page d'attente</a>
			</p>
		</form>
	</div>
	<?php
}

/* ---------- Rappel pour les personnes connectées ---------- */

add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( vsg_maint_is_on() && current_user_can( 'edit_posts' ) ) {
			$bar->add_node(
				array(
					'id'    => 'vsg-maintenance',
					'title' => 'Maintenance active',
					'href'  => current_user_can( 'edit_theme_options' ) ? admin_url( 'themes.php?page=vsg-maintenance' ) : false,
					'meta'  => array( 'class' => 'vsg-maint-flag' ),
				)
			);
		}
	},
	100
);

function vsg_maint_flag_css() {
	if ( vsg_maint_is_on() && is_admin_bar_showing() ) {
		echo '<style>#wpadminbar .vsg-maint-flag > .ab-item{background:#B32D2E!important;color:#fff!important;font-weight:600}</style>';
	}
}
add_action( 'wp_head', 'vsg_maint_flag_css' );
add_action( 'admin_head', 'vsg_maint_flag_css' );

/* ---------- API fermée aux visiteurs pendant la maintenance (sauf le signal de mise à jour) ---------- */

add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( null !== $result || ! vsg_maint_is_on() || current_user_can( 'edit_posts' ) ) {
			return $result;
		}
		if ( 0 === strpos( $request->get_route(), '/vsg/v1/github' ) ) {
			return $result;
		}
		return new WP_Error( 'vsg_maintenance', 'Site en maintenance.', array( 'status' => 503 ) );
	},
	10,
	3
);

/* ---------- Page d'attente ---------- */

add_action(
	'template_redirect',
	function () {
		$preview = isset( $_GET['vsg-maintenance'] ) && current_user_can( 'edit_theme_options' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $preview && ( ! vsg_maint_is_on() || current_user_can( 'edit_posts' ) ) ) {
			return;
		}
		if ( ! $preview ) {
			status_header( 503 );
			header( 'Retry-After: 3600' );
		}
		nocache_headers();
		vsg_maint_render();
		exit;
	},
	0
);

function vsg_maint_render() {
	$s     = vsg_maint_settings();
	$lines = array_filter( array_map( 'trim', explode( "\n", $s['message'] ) ) );
	add_filter( 'show_admin_bar', '__return_false' );
	?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Maintenance – <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
<?php wp_head(); ?>
</head>
<body <?php body_class( 'maintenance' ); ?>>
<main id="contenu" class="maint">
	<div class="wrap narrow">
		<div class="maint__brand">
			<?php
			$logo_id = get_theme_mod( 'custom_logo' );
			echo $logo_id
				? wp_get_attachment_image( $logo_id, 'thumbnail', false, array( 'class' => 'maint__logo', 'alt' => '' ) )
				: '<img class="maint__logo" src="' . esc_url( VSG_URI . '/assets/img/logo-mark.svg' ) . '" alt="" width="64" height="56">';
			?>
			<p class="maint__name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
		</div>
		<h1>Site en maintenance</h1>
		<div class="maint__msg">
			<?php foreach ( $lines as $l ) : ?>
				<p><?php echo make_clickable( esc_html( $l ) ); // phpcs:ignore ?></p>
			<?php endforeach; ?>
		</div>
		<?php if ( $s['hours'] && function_exists( 'vsg_parishes' ) && vsg_parishes() ) : ?>
			<h2>Horaires des messes</h2>
			<div class="maint__hours">
				<?php
				foreach ( vsg_parishes() as $p ) {
					echo '<div class="hourscard" style="--accent:' . esc_attr( vsg_parish_color( $p ) ) . '">' . vsg_hours_block( $p->ID, 'Église ' . get_the_title( $p ) ) . '</div>'; // phpcs:ignore
					foreach ( vsg_chapels( $p->ID ) as $c ) {
						echo '<div class="hourscard" style="--accent:' . esc_attr( vsg_parish_color( $p ) ) . '">' . vsg_hours_block( $c->ID, get_the_title( $c ) ) . '</div>'; // phpcs:ignore
					}
				}
				?>
			</div>
		<?php endif; ?>
	</div>
</main>
<?php wp_footer(); ?>
</body>
</html>
	<?php
}
