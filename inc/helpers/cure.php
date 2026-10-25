<?php
/**
 * Mot du curé : un seul champ (page d'accueil), affiché en entier sur sa page
 * et en abrégé sur l'accueil.
 */

defined( 'ABSPATH' ) || exit;

/** Texte saisi ; le texte modèle entre crochets (« [Mot du curé à rédiger…] ») compte pour vide. */
function vsg_cure_text() {
	$text = trim( (string) vsg_meta( (int) get_option( 'page_on_front' ), 'cure_mot' ) );
	return ( '' !== $text && '[' === $text[0] ) ? '' : $text;
}

/** Paragraphes : un par ligne, les lignes vides sont ignorées (texte collé depuis Word ou tapé à la main). */
function vsg_cure_paragraphs( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R+/u', $text ) ) ) );
}

/** Début montré sur l'accueil : les premiers paragraphes, jusqu'à atteindre environ 40 mots. */
function vsg_cure_intro( $paragraphs, $words = 40 ) {
	$intro = array();
	$count = 0;
	foreach ( $paragraphs as $p ) {
		$intro[] = $p;
		$count  += count( preg_split( '/\s+/u', $p ) );
		if ( $count >= $words ) {
			break;
		}
	}
	return $intro;
}

/** Paragraphes en HTML. */
function vsg_cure_html( $paragraphs ) {
	$html = '';
	foreach ( $paragraphs as $p ) {
		$html .= '<p>' . esc_html( $p ) . '</p>';
	}
	return $html;
}

/** Page « Le mot du curé » (modèle dédié), ou null. */
function vsg_cure_page() {
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_wp_page_template',
			'meta_value'     => VSG_TPL_MOT_CURE,
		)
	);
	return $pages ? $pages[0] : null;
}
