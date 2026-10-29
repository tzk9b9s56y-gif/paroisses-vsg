<?php
/**
 * Mot du curé : rédigé dans la page « Le mot du curé » (éditeur normal),
 * affiché en entier sur cette page et en abrégé sur l'accueil.
 */

defined( 'ABSPATH' ) || exit;

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

/**
 * Morceaux de HTML du mot du curé, un par bloc (paragraphe, citation, titre…).
 * Vide si la page est vide ou ne contient qu'un texte modèle entre crochets.
 */
function vsg_cure_pieces( $page ) {
	$content = $page ? trim( (string) $page->post_content ) : '';
	$text    = trim( wp_strip_all_tags( $content ) );
	if ( '' === $text || '[' === $text[0] ) {
		return array();
	}
	$pieces = array();
	if ( has_blocks( $content ) ) {
		foreach ( parse_blocks( $content ) as $block ) {
			if ( ! empty( $block['blockName'] ) ) {
				$pieces[] = render_block( $block );
			}
		}
	} else {
		preg_match_all( '#<p[^>]*>.*?</p>#s', wpautop( $content ), $m );
		$pieces = $m[0];
	}
	return array_values(
		array_filter(
			$pieces,
			function ( $html ) {
				return '' !== trim( wp_strip_all_tags( $html ) );
			}
		)
	);
}

/** Début montré sur l'accueil : les premiers blocs, jusqu'à atteindre environ 40 mots. */
function vsg_cure_intro( $pieces, $words = 40 ) {
	$intro = array();
	$count = 0;
	foreach ( $pieces as $html ) {
		$intro[] = $html;
		$count  += count( preg_split( '/\s+/u', trim( wp_strip_all_tags( $html ) ) ) );
		if ( $count >= $words ) {
			break;
		}
	}
	return $intro;
}

/** Morceaux réunis (HTML venant de l'éditeur, filtré comme le contenu des pages). */
function vsg_cure_html( $pieces ) {
	return wp_kses_post( implode( "\n", $pieces ) );
}
