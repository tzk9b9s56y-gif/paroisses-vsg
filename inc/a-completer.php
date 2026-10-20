<?php
/**
 * Pages « Informations à venir » :
 * - le bloc [vsg_a_venir] affiche un court texte d'attente avec le téléphone
 *   de la paroisse et le lien vers le formulaire de contact ;
 * - un encadré du tableau de bord liste les pages qui l'affichent encore.
 *   Une page sort de la liste dès que le bloc est retiré de son contenu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VSG_A_VENIR = '<!-- wp:shortcode -->[vsg_a_venir]<!-- /wp:shortcode -->';

add_shortcode(
	'vsg_a_venir',
	function () {
		$parish = vsg_context_parish();
		if ( ! $parish && function_exists( 'vsg_parishes' ) ) {
			$all    = vsg_parishes();
			$parish = $all ? $all[0] : null;
		}
		$tel  = $parish ? vsg_meta( $parish->ID, 'telephone' ) : '';
		$link = '<a href="' . esc_url( vsg_page_url( 'contact' ) ) . '">formulaire de contact</a>';
		return '<p><strong>Informations à venir.</strong></p><p>Pour tout renseignement, contactez l\'accueil paroissial'
			. ( $tel ? ' au ' . esc_html( $tel ) . ' ou' : '' ) . ' par le ' . $link . '.</p>';
	}
);

/** Met une page en attente : texte d'attente, résumé vidé, repère « à compléter ». */
function vsg_set_a_venir( $id ) {
	wp_update_post(
		array(
			'ID'           => $id,
			'post_content' => VSG_A_VENIR,
			'post_excerpt' => '',
		)
	);
	update_post_meta( $id, '_vsg_a_completer', 1 );
}

/* La page sort de la liste dès que le texte d'attente est retiré. */
add_action(
	'save_post',
	function ( $id, $post ) {
		if ( wp_is_post_revision( $id ) || ! get_post_meta( $id, '_vsg_a_completer', true ) ) {
			return;
		}
		if ( false === strpos( (string) $post->post_content, '[vsg_a_venir' ) ) {
			delete_post_meta( $id, '_vsg_a_completer' );
		}
	},
	10,
	2
);

/* Encadré « À compléter » du tableau de bord. */
add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'vsg_a_completer',
			'Pages à compléter',
			function () {
				$pages = get_posts(
					array(
						'post_type'      => array( 'page', 'post' ),
						'post_status'    => array( 'publish', 'private', 'draft' ),
						'posts_per_page' => -1,
						'meta_key'       => '_vsg_a_completer',
						'orderby'        => 'parent title',
						'order'          => 'ASC',
					)
				);
				if ( ! $pages ) {
					echo '<p>Toutes les pages sont rédigées.</p>';
					return;
				}
				echo '<p>Ces pages affichent « Informations à venir ». Ouvrez-en une, remplacez le bloc d\'attente par le vrai texte et enregistrez : elle disparaît de la liste.</p><ul>';
				foreach ( $pages as $p ) {
					$parent = $p->post_parent ? get_the_title( $p->post_parent ) : '';
					$ctx    = vsg_context_parish( $p );
					$where  = $ctx ? get_the_title( $ctx ) : 'Commun';
					echo '<li><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a> <span class="vsg-muted">– '
						. esc_html( $parent && $parent !== $where ? $where . ' › ' . $parent : $where ) . '</span></li>';
				}
				echo '</ul><p class="vsg-muted">' . count( $pages ) . ' page' . ( count( $pages ) > 1 ? 's' : '' ) . ' à compléter.</p>';
			}
		);
	}
);
