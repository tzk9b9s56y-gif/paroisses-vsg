<?php
/**
 * Rôle « Secrétariat » : faire vivre le contenu du site, sans accès technique.
 *
 * Peut : pages (dont le mot du curé et les fiches des lieux), actualités, agenda (et son import),
 * feuilles paroissiales, médias.
 * Ne peut pas : thèmes, extensions, comptes, réglages (mails, dons, liens utiles), maintenance,
 * mises à jour du thème, ni modifier les catégories « Paroisses et lieux » (structure du site).
 * Pas de HTML libre (unfiltered_html), contrairement au rôle « Éditeur » de WordPress :
 * un compte volé ne peut pas injecter de script dans les pages.
 *
 * Pour l'utiliser : Comptes › le compte de la secrétaire › Rôle « Secrétariat ».
 */

defined( 'ABSPATH' ) || exit;

const VSG_ROLE_SECRETARIAT = 'vsg_secretariat';
const VSG_ROLES_VERSION    = '1';

/**
 * Capacités du secrétariat.
 *
 * @return array<string,bool>
 */
function vsg_secretariat_caps() {
	$caps = array( 'read', 'upload_files' );
	foreach ( array( 'posts', 'pages' ) as $t ) {
		foreach ( array( 'edit_%s', 'edit_others_%s', 'edit_published_%s', 'edit_private_%s', 'publish_%s', 'delete_%s', 'delete_others_%s', 'delete_published_%s', 'delete_private_%s', 'read_private_%s' ) as $c ) {
			$caps[] = sprintf( $c, $t );
		}
	}
	return array_fill_keys( $caps, true );
}

/* Rôle créé ou remis à jour une fois par version des rôles (et à l'activation du thème). */
function vsg_roles_install() {
	remove_role( VSG_ROLE_SECRETARIAT );
	add_role( VSG_ROLE_SECRETARIAT, 'Secrétariat', vsg_secretariat_caps() );
	update_option( 'vsg_roles_version', VSG_ROLES_VERSION );
}
add_action( 'after_switch_theme', 'vsg_roles_install' );
add_action(
	'init',
	function () {
		if ( get_option( 'vsg_roles_version' ) !== VSG_ROLES_VERSION ) {
			vsg_roles_install();
		}
	}
);

/** L'utilisateur connecté gère le contenu sans être administrateur (Secrétariat, ou Éditeur de WordPress). */
function vsg_is_secretariat() {
	return current_user_can( 'edit_pages' ) && ! current_user_can( 'manage_options' );
}

/* Administrateur : rappel discret sur la page des comptes, tant qu'un compte « Éditeur » existe encore. */
add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || 'users' !== $screen->id || ! current_user_can( 'promote_users' ) ) {
			return;
		}
		$editors = get_users(
			array(
				'role'   => 'editor',
				'fields' => array( 'user_login' ),
			)
		);
		if ( ! $editors ) {
			return;
		}
		echo '<div class="notice notice-info"><p>Le rôle <strong>Secrétariat</strong> donne accès à tout le contenu du site, sans pouvoir y insérer de code. Il est plus sûr que le rôle « Éditeur » de WordPress pour le compte du secrétariat : '
			. esc_html( implode( ', ', wp_list_pluck( $editors, 'user_login' ) ) )
			. ' (Modifier › Rôle › Secrétariat).</p></div>';
	}
);
