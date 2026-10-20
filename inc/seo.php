<?php
/**
 * Titres de page distincts et description pour les moteurs de recherche.
 * Deux pages « Histoire » ou « Horaires » (une par paroisse) ne doivent pas avoir le même titre.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( ! is_singular() || is_front_page() ) {
			return $parts;
		}
		$id = get_queried_object_id();
		if ( VSG_TPL_HORAIRES === get_page_template_slug( $id ) && vsg_hours_current() ) {
			$parts['title'] = 'Horaires – ' . vsg_hours_places()[ vsg_hours_current() ][1];
			return $parts;
		}
		/* Rendez-vous répétés (catéchisme, chapelet…) : la date distingue chaque page. */
		if ( 'evenement' === get_post_type( $id ) && vsg_meta( $id, 'date' ) ) {
			$parts['title'] .= ' – ' . vsg_date_long( vsg_meta( $id, 'date' ) );
			return $parts;
		}
		$parish = vsg_context_parish( $id );
		if ( $parish && (int) $parish->ID !== $id ) {
			$parts['title'] .= ' – ' . get_the_title( $parish );
		}
		return $parts;
	}
);

/** Texte de description : extrait, sinon début du contenu, sinon slogan du site. */
function vsg_meta_description() {
	if ( is_singular() && ! is_front_page() ) {
		$post = get_queried_object();
		$text = has_excerpt( $post ) ? $post->post_excerpt : strip_shortcodes( $post->post_content );
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( excerpt_remove_blocks( $text ) ) ) );
		/* Texte encore « à compléter » entre crochets : rien plutôt qu'une description fausse. */
		if ( '' !== $text && false === strpos( $text, '[' ) ) {
			return wp_html_excerpt( $text, 155, '…' );
		}
	}
	return get_bloginfo( 'description' );
}

add_action(
	'wp_head',
	function () {
		$d = vsg_meta_description();
		if ( '' !== $d ) {
			echo '<meta name="description" content="' . esc_attr( $d ) . '">' . "\n";
		}
	},
	2
);

/* CSS des blocs chargée seulement pour les blocs présents sur la page. */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );
