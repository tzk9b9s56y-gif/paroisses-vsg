<?php
/**
 * Export de l'agenda en .csv (Agenda › Importer / exporter).
 */

defined( 'ABSPATH' ) || exit;

/** Cellule CSV sans risque de formule à l'ouverture dans Excel (=, +, -, @). */
function vsg_csv_cell( $v ) {
	$v = (string) $v;
	/* Une cellule commençant par = + - @ (ou tabulation, retour chariot) serait lue comme une formule par le tableur. */
	return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $v : $v;
}

add_action(
	'admin_post_vsg_export',
	function () {
		if ( ! current_user_can( 'edit_others_posts' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'vsg_export' ) ) {
			wp_die( 'Accès refusé.' );
		}
		$from   = sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) );
		$to     = sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) );
		$places = array_flip( vsg_import_places() );
		$types  = vsg_event_types();
		$q      = new WP_Query(
			array(
				'post_type'      => 'evenement',
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'meta_key'       => '_vsg_date',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			)
		);
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="agenda-paroissial.csv"' );
		echo "\xEF\xBB\xBF";
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- flux de sortie HTTP, pas un fichier.
		fputcsv( $out, array( 'Date', 'Heure', 'Titre', 'Paroisse ou chapelle', 'Lieu précis', 'Type', 'Interne', 'Description' ), ';', '"', '' );
		foreach ( $q->posts as $p ) {
			$date = vsg_meta( $p->ID, 'date' );
			if ( ( $from && $date < $from ) || ( $to && $date > $to ) ) {
				continue;
			}
			$cats  = wp_get_post_categories( $p->ID, array( 'fields' => 'slugs' ) );
			$place = '';
			foreach ( $cats as $slug ) {
				if ( isset( $places[ $slug ] ) ) {
					$place = $places[ $slug ];
					break;
				}
			}
			$type = vsg_meta( $p->ID, 'type' );
			fputcsv(
				$out,
				array_map(
					'vsg_csv_cell',
					array(
						gmdate( 'd/m/Y', strtotime( $date . ' 12:00:00' ) ),
						vsg_meta( $p->ID, 'heure' ),
						$p->post_title,
						$place,
						vsg_meta( $p->ID, 'lieu' ),
						$types[ $type ][0] ?? '',
						'private' === $p->post_status ? 'oui' : 'non',
						trim( wp_strip_all_tags( $p->post_content ) ),
					)
				),
				';',
				'"',
				''
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- flux de sortie HTTP.
		exit;
	}
);
