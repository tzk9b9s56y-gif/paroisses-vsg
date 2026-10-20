<?php
/**
 * Thème Paroisses de Villeneuve-Saint-Georges.
 *
 * Thème classique, sans dépendance à une extension : l'agenda, les feuilles
 * paroissiales et le formulaire de contact sont gérés ici.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VSG_VERSION', wp_get_theme( get_template() )->get( 'Version' ) ); // une seule source : style.css
define( 'VSG_DIR', get_template_directory() );
define( 'VSG_URI', get_template_directory_uri() );

require VSG_DIR . '/inc/helpers.php';
require VSG_DIR . '/inc/securite.php';
require VSG_DIR . '/inc/htaccess.php';
require VSG_DIR . '/inc/seo.php';
require VSG_DIR . '/inc/content-types.php';
require VSG_DIR . '/inc/meta-boxes.php';
require VSG_DIR . '/inc/contact.php';
require VSG_DIR . '/inc/intentions.php';
require VSG_DIR . '/inc/admin.php';
require VSG_DIR . '/inc/import.php';
require VSG_DIR . '/inc/dons.php';
require VSG_DIR . '/inc/messes.php';
require VSG_DIR . '/inc/liens.php';
require VSG_DIR . '/inc/maintenance.php';
require VSG_DIR . '/inc/a-completer.php';
require VSG_DIR . '/inc/mail.php';
require VSG_DIR . '/inc/updater.php';
require VSG_DIR . '/inc/auto-update.php';
require VSG_DIR . '/inc/upgrade.php';

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_post_type_support( 'page', 'excerpt' ); // Résumé affiché sur les cartes des communautés.
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 104,
				'width'       => 104,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/editor.css' );
		add_image_size( 'vsg-card', 720, 450, true );
		add_image_size( 'vsg-hero', 1200, 720, true );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'vsg', VSG_URI . '/style.css', array(), VSG_VERSION );
		wp_enqueue_script( 'vsg', VSG_URI . '/assets/js/site.js', array(), VSG_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}
);

/* Favicon : le logo, tant qu'aucune icône de site n'est définie dans Apparence › Personnaliser. */
add_action(
	'wp_head',
	function () {
		if ( has_site_icon() ) {
			return;
		}
		echo '<link rel="icon" href="' . esc_url( VSG_URI . '/assets/img/favicon.svg' ) . '" type="image/svg+xml">' . "\n";
		echo '<link rel="icon" href="' . esc_url( VSG_URI . '/assets/img/favicon-192.png' ) . '" type="image/png" sizes="192x192">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( VSG_URI . '/assets/img/favicon-192.png' ) . '">' . "\n";
	}
);

/* Pas de commentaires sur un site paroissial : moins de modération, moins de spam. */
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );
add_action(
	'admin_menu',
	function () {
		remove_menu_page( 'edit-comments.php' );
	}
);
add_action(
	'wp_before_admin_bar_render',
	function () {
		global $wp_admin_bar;
		$wp_admin_bar->remove_menu( 'comments' );
	}
);

/* Extraits courts et sans « […] ». */
add_filter(
	'excerpt_length',
	function () {
		return 26;
	}
);
add_filter(
	'excerpt_more',
	function () {
		return '…';
	}
);
