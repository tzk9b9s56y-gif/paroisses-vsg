<?php
/**
 * Import et export de l'agenda (Agenda › Importer / exporter).
 *
 * Fichier attendu : .xlsx ou .csv, une ligne par rendez-vous, colonnes (dans cet ordre, la 1re ligne = en-têtes) :
 * Date | Heure | Titre | Paroisse ou chapelle | Lieu précis | Type | Interne | Description
 *
 * Rapprochement sur date + titre : un rendez-vous déjà présent est mis à jour, jamais dupliqué. Rien n'est supprimé.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VSG_IMPORT_MAX', 2 * 1024 * 1024 );

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=evenement', 'Importer / exporter l\'agenda', 'Importer / exporter', 'edit_others_posts', 'vsg-import', 'vsg_import_page' );
	}
);

/** Libellés acceptés pour la colonne « Paroisse ou chapelle » → slug de catégorie. */
function vsg_import_places() {
	$out = array();
	foreach ( vsg_parishes() as $p ) {
		$t = vsg_page_term( $p->ID );
		if ( $t ) {
			$out[ get_the_title( $p ) ] = $t->slug;
		}
		foreach ( vsg_chapels( $p->ID ) as $c ) {
			$ct = vsg_page_term( $c->ID );
			if ( $ct ) {
				$out[ get_the_title( $c ) ] = $ct->slug;
			}
		}
	}
	$out['Les deux paroisses'] = VSG_COMMON_CAT;
	return $out;
}

function vsg_import_norm( $s ) {
	$s = remove_accents( trim( (string) $s ) );
	return strtolower( preg_replace( '/\s+/', ' ', $s ) );
}

/** Trouve une valeur dans une liste de libellés, sans tenir compte des accents, de la casse ni de « Chapelle ». */
function vsg_import_match( $value, $choices ) {
	$v = vsg_import_norm( $value );
	if ( '' === $v ) {
		return null;
	}
	foreach ( $choices as $label => $slug ) {
		$l = vsg_import_norm( $label );
		if ( $v === $l || $v === $slug || str_replace( 'chapelle ', '', $v ) === str_replace( 'chapelle ', '', $l ) ) {
			return $slug;
		}
	}
	return null;
}

/* ---------- Lecture des fichiers ---------- */

/** Date : « 15/01/2027 », « 2027-01-15 », « 15 janvier 2027 » ou numéro de série Excel. Retourne Y-m-d ou ''. */
function vsg_import_date( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return '';
	}
	if ( is_numeric( $v ) && (float) $v > 20000 ) {
		return gmdate( 'Y-m-d', (int) round( ( (float) $v - 25569 ) * 86400 ) );
	}
	if ( preg_match( '/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2,4})/', $v, $m ) ) {
		$y = strlen( $m[3] ) === 2 ? '20' . $m[3] : $m[3];
		return checkdate( (int) $m[2], (int) $m[1], (int) $y ) ? sprintf( '%04d-%02d-%02d', $y, $m[2], $m[1] ) : '';
	}
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $v, $m ) ) {
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $m[0] : '';
	}
	$mois = array( 'janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12 );
	if ( preg_match( '/^(?:\w+\s+)?(\d{1,2})(?:er)?\s+([a-z]+)\s+(\d{4})$/', vsg_import_norm( $v ), $m ) && isset( $mois[ $m[2] ] ) ) {
		return sprintf( '%04d-%02d-%02d', $m[3], $mois[ $m[2] ], $m[1] );
	}
	return '';
}

/** Heure : « 17h15 », « 17:15 », « 17 h », « 9h » ou fraction de jour Excel. Retourne HH:MM ou ''. */
function vsg_import_time( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return '';
	}
	if ( is_numeric( $v ) && (float) $v < 1 ) {
		$mins = (int) round( (float) $v * 1440 );
		return sprintf( '%02d:%02d', intdiv( $mins, 60 ), $mins % 60 );
	}
	if ( preg_match( '/^(\d{1,2})\s*[h:H]\s*(\d{2})?/', $v, $m ) ) {
		return sprintf( '%02d:%02d', $m[1], isset( $m[2] ) ? $m[2] : 0 );
	}
	return '';
}

function vsg_import_read_csv( $path ) {
	$rows = array();
	$h    = fopen( $path, 'r' ); // phpcs:ignore
	if ( ! $h ) {
		return $rows;
	}
	$first = fgets( $h );
	rewind( $h );
	$sep = substr_count( $first, ';' ) >= substr_count( $first, ',' ) ? ';' : ',';
	while ( ( $r = fgetcsv( $h, 0, $sep, '"', '' ) ) !== false ) { // phpcs:ignore
		if ( $r && isset( $r[0] ) ) {
			$r[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $r[0] );
		}
		$rows[] = array_map( function ( $c ) { return mb_check_encoding( $c, 'UTF-8' ) ? $c : mb_convert_encoding( $c, 'UTF-8', 'Windows-1252' ); }, $r );
	}
	fclose( $h ); // phpcs:ignore
	return $rows;
}

/** Cellule CSV sans risque de formule à l'ouverture dans Excel (=, +, -, @). */
function vsg_csv_cell( $v ) {
	$v = (string) $v;
	/* Une cellule commençant par = + - @ (ou tabulation, retour chariot) serait lue comme une formule par le tableur. */
	return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $v : $v;
}

/** XML d'un .xlsx lu sans accès réseau ni entités externes. */
function vsg_import_xml( $xml ) {
	libxml_use_internal_errors( true );
	if ( false !== stripos( (string) $xml, '<!DOCTYPE' ) ) {
		return false; // un classeur Excel n'en contient jamais
	}
	return simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET );
}

/** Contenu d'un fichier de l'archive, refusé s'il dépasse 20 Mo une fois décompressé. */
function vsg_import_zip_get( $zip, $name ) {
	$st = $zip->statName( $name );
	if ( ! $st || $st['size'] > 20 * MB_IN_BYTES ) {
		return false;
	}
	return $zip->getFromName( $name );
}

/** Lit la première feuille d'un .xlsx (archive zip) sans bibliothèque externe. */
function vsg_import_read_xlsx( $path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'zip', 'Ce serveur ne sait pas ouvrir les fichiers .xlsx (ZipArchive absent). Enregistrez le fichier en .csv.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return new WP_Error( 'zip', 'Fichier .xlsx illisible.' );
	}
	$shared = array();
	$xml    = vsg_import_zip_get( $zip, 'xl/sharedStrings.xml' );
	if ( $xml ) {
		$sx = vsg_import_xml( $xml );
		if ( $sx ) {
			foreach ( $sx->si as $si ) {
				$txt = '';
				foreach ( $si->xpath( './/*[local-name()="t"]' ) as $t ) {
					$txt .= (string) $t;
				}
				$shared[] = $txt;
			}
		}
	}
	// Feuille « Agenda » si elle existe, sinon la première du classeur.
	$target = 'xl/worksheets/sheet1.xml';
	$wbxml  = vsg_import_zip_get( $zip, 'xl/workbook.xml' );
	$rels   = vsg_import_zip_get( $zip, 'xl/_rels/workbook.xml.rels' );
	if ( $wbxml && $rels ) {
		$wb = vsg_import_xml( $wbxml );
		$rl = vsg_import_xml( $rels );
		$map = array();
		if ( $rl ) {
			foreach ( $rl->Relationship as $r ) {
				$map[ (string) $r['Id'] ] = 'xl/' . ltrim( str_replace( '/xl/', '', (string) $r['Target'] ), '/' );
			}
		}
		if ( $wb ) {
			$first = '';
			foreach ( $wb->sheets->sheet as $sh ) {
				$rid  = (string) $sh->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )->id;
				$file = $map[ $rid ] ?? '';
				if ( ! $first && $file ) {
					$first = $file;
				}
				if ( $file && 'agenda' === vsg_import_norm( (string) $sh['name'] ) ) {
					$first = $file;
					break;
				}
			}
			if ( $first ) {
				$target = $first;
			}
		}
	}
	$sheet = vsg_import_zip_get( $zip, $target );
	$zip->close();
	if ( ! $sheet ) {
		return new WP_Error( 'zip', 'Feuille introuvable dans le fichier .xlsx.' );
	}
	$sx = vsg_import_xml( $sheet );
	if ( ! $sx ) {
		return new WP_Error( 'zip', 'Feuille illisible.' );
	}
	$rows = array();
	foreach ( $sx->sheetData->row as $row ) {
		$r = array();
		foreach ( $row->c as $c ) {
			$ref = (string) $c['r'];
			if ( ! preg_match( '/^([A-Z]{1,3})[0-9]/', $ref, $m ) ) {
				continue;
			}
			$col = 0;
			foreach ( str_split( $m[1] ) as $ch ) {
				$col = $col * 26 + ( ord( $ch ) - 64 );
			}
			$type = (string) $c['t'];
			if ( 's' === $type ) {
				$val = $shared[ (int) $c->v ] ?? '';
			} elseif ( 'inlineStr' === $type ) {
				$val = (string) $c->is->t;
			} else {
				$val = (string) $c->v;
			}
			$r[ $col - 1 ] = $val;
		}
		if ( $r ) {
			$max = max( array_keys( $r ) );
			$out = array();
			for ( $i = 0; $i <= $max; $i++ ) {
				$out[] = $r[ $i ] ?? '';
			}
			$rows[] = $out;
		}
	}
	return $rows;
}

/** Associe chaque colonne à un champ d'après les en-têtes ; null si le fichier n'a pas d'en-têtes reconnus. */
function vsg_import_columns( $header ) {
	$names = array(
		'date'                 => 'date',
		'dates'                => 'date',
		'date de debut'        => 'date',
		'date de fin'          => 'fin',
		'fin'                  => 'fin',
		'heure'                => 'heure',
		'titre'                => 'title',
		'activite'             => 'title',
		'activites'            => 'title',
		'paroisse ou chapelle' => 'place',
		'lieu precis'          => 'lieu',
		'lieu'                 => 'lieu_libre',
		'type'                 => 'type',
		'interne'              => 'interne',
		'description'          => 'text',
		'theme'                => 'text',
		'themes'               => 'text',
	);
	$map = array();
	foreach ( (array) $header as $i => $h ) {
		$k = trim( vsg_import_norm( $h ), " :\t" );
		if ( isset( $names[ $k ] ) && ! isset( $map[ $names[ $k ] ] ) ) {
			$map[ $names[ $k ] ] = $i;
		}
	}
	return isset( $map['date'], $map['title'] ) ? $map : null;
}

/** Reconnaît une paroisse ou une chapelle dans un lieu écrit librement (« Église N.D. Lourdes », « Chapelle St Louis »…). */
function vsg_import_guess_place( $text ) {
	$t = ' ' . preg_replace( '/[^a-z0-9]+/', ' ', vsg_import_norm( $text ) ) . ' ';
	if ( '  ' === $t ) {
		return null;
	}
	foreach ( vsg_import_places() as $label => $slug ) {
		if ( VSG_COMMON_CAT === $slug ) {
			continue;
		}
		$words = explode( ' ', trim( preg_replace( '/[^a-z0-9]+/', ' ', vsg_import_norm( $label ) ) ) );
		$key   = end( $words ); // lourdes, genevieve, louis, joseph
		if ( strlen( $key ) > 3 && false !== strpos( $t, ' ' . $key . ' ' ) ) {
			return $slug;
		}
	}
	return null;
}

/**
 * Transforme les lignes brutes en rendez-vous validés.
 *
 * Les colonnes sont reconnues par leur en-tête : format complet du site (Date, Heure, Titre, Paroisse ou chapelle,
 * Lieu précis, Type, Interne, Description) ou format « groupe » (Date, Date de fin, Heure, Activité, Thème, Lieu).
 * $group : type imposé à toutes les lignes (agenda d'un groupe) ; vide = colonne Type du fichier.
 */
function vsg_import_parse( $rows, $group = '' ) {
	$places = vsg_import_places();
	$types  = array();
	foreach ( vsg_event_types() as $slug => $t ) {
		$types[ $t[0] ] = $slug;
	}
	$types += vsg_event_type_aliases();
	$map = vsg_import_columns( $rows[0] ?? array() );
	if ( ! $map ) {
		$map = array( 'date' => 0, 'heure' => 1, 'title' => 2, 'place' => 3, 'lieu' => 4, 'type' => 5, 'interne' => 6, 'text' => 7 );
	}
	$get    = function ( $r, $k ) use ( $map ) {
		return isset( $map[ $k ] ) ? trim( (string) ( $r[ $map[ $k ] ] ?? '' ) ) : '';
	};
	$items  = array();
	$errors = array();
	foreach ( $rows as $i => $r ) {
		$n = $i + 1;
		if ( 1 === $n ) {
			continue; // en-têtes
		}
		$r = array_map( 'trim', array_map( 'strval', (array) $r ) );
		if ( '' === implode( '', $r ) ) {
			continue;
		}
		$rawdate = $get( $r, 'date' );
		$date    = vsg_import_date( $rawdate );
		$fin     = vsg_import_date( $get( $r, 'fin' ) );
		$title   = $get( $r, 'title' );
		$lieu    = $get( $r, 'lieu' );
		$prob    = array();
		if ( isset( $map['place'] ) ) {
			$place = vsg_import_match( $get( $r, 'place' ), $places );
			if ( null === $place ) {
				$prob[] = 'paroisse ou chapelle inconnue (« ' . $get( $r, 'place' ) . ' »)';
			}
		} else {
			$libre = $get( $r, 'lieu_libre' );
			$place = vsg_import_guess_place( $libre );
			if ( null === $place ) {
				$place = VSG_COMMON_CAT;
				$lieu  = $lieu ? $lieu : $libre;
			}
		}
		if ( $group ) {
			$type = $group;
		} else {
			$type = '' === $get( $r, 'type' ) ? '' : vsg_import_match( $get( $r, 'type' ), $types );
			if ( null === $type ) {
				$prob[] = 'type inconnu (« ' . $get( $r, 'type' ) . ' »)';
			}
		}
		if ( ! $date ) {
			$prob[] = 'date illisible (« ' . $rawdate . ' »)';
		}
		if ( '' === $title ) {
			$prob[] = 'titre vide';
		}
		if ( '' !== $get( $r, 'fin' ) && ! $fin ) {
			$prob[] = 'date de fin illisible (« ' . $get( $r, 'fin' ) . ' »)';
		}
		if ( '' !== $get( $r, 'heure' ) && '' === vsg_import_time( $get( $r, 'heure' ) ) ) {
			$prob[] = 'heure illisible (« ' . $get( $r, 'heure' ) . ' »)';
		}
		if ( $prob ) {
			$errors[] = 'Ligne ' . $n . ' : ' . implode( ', ', $prob ) . '.';
			continue;
		}
		$text = $get( $r, 'text' );
		if ( $fin && $fin > $date ) {
			$text = trim( vsg_import_range( $date, $fin ) . "\n" . $text );
		}
		$items[] = array(
			'date'    => $date,
			'time'    => vsg_import_time( $get( $r, 'heure' ) ),
			'title'   => $title,
			'place'   => $place,
			'lieu'    => $lieu,
			'type'    => (string) $type,
			'private' => in_array( vsg_import_norm( $get( $r, 'interne' ) ), array( 'oui', 'o', 'x', '1', 'vrai', 'yes', 'true' ), true ),
			'text'    => $text,
		);
	}
	return array( $items, $errors );
}

/** « Du 11 au 13 septembre. » / « Du 30 avril au 2 mai. » */
function vsg_import_range( $from, $to ) {
	$a = strtotime( $from . ' 12:00:00' );
	$b = strtotime( $to . ' 12:00:00' );
	$m = array( '', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' );
	$d = function ( $ts ) {
		$j = (int) gmdate( 'j', $ts );
		return 1 === $j ? '1er' : (string) $j;
	};
	$same = gmdate( 'Y-m', $a ) === gmdate( 'Y-m', $b );
	$sy   = gmdate( 'Y', $a ) === gmdate( 'Y', $b );
	return 'Du ' . $d( $a ) . ( $same ? '' : ' ' . $m[ (int) gmdate( 'n', $a ) ] . ( $sy ? '' : ' ' . gmdate( 'Y', $a ) ) ) . ' au ' . $d( $b ) . ' ' . $m[ (int) gmdate( 'n', $b ) ] . '.';
}

/** Rendez-vous existant pour date + titre. */
function vsg_import_find( $date, $title ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'evenement',
			'post_status'    => array( 'publish', 'private', 'draft' ),
			'posts_per_page' => 1,
			'title'          => $title,
			'meta_query'     => array( array( 'key' => '_vsg_date', 'value' => $date ) ),
			'fields'         => 'ids',
		)
	);
	return $q->posts ? (int) $q->posts[0] : 0;
}

/** Texte de la description en paragraphes (une ligne = un paragraphe). */
function vsg_import_content( $text ) {
	$out = '';
	foreach ( array_filter( array_map( 'trim', explode( "\n", (string) $text ) ) ) as $line ) {
		$out .= "<!-- wp:paragraph -->\n<p>" . esc_html( $line ) . "</p>\n<!-- /wp:paragraph -->\n\n";
	}
	return trim( $out );
}

function vsg_import_apply( $items ) {
	$created = 0;
	$updated = 0;
	foreach ( $items as $it ) {
		$cat  = get_term_by( 'slug', $it['place'], 'category' );
		$args = array(
			'post_type'     => 'evenement',
			'post_title'    => $it['title'],
			'post_content'  => vsg_import_content( $it['text'] ),
			'post_status'   => $it['private'] ? 'private' : 'publish',
			'post_category' => $cat ? array( $cat->term_id ) : array(),
		);
		$id = vsg_import_find( $it['date'], $it['title'] );
		if ( $id ) {
			$args['ID'] = $id;
			wp_update_post( wp_slash( $args ) );
			$updated++;
		} else {
			$id = wp_insert_post( wp_slash( $args ) );
			$created++;
		}
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_vsg_date', $it['date'] );
			update_post_meta( $id, '_vsg_heure', $it['time'] );
			update_post_meta( $id, '_vsg_lieu', $it['lieu'] );
			update_post_meta( $id, '_vsg_type', $it['type'] );
		}
	}
	return array( $created, $updated );
}

/* ---------- Export ---------- */

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
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore
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
				array_map( 'vsg_csv_cell', array(
					gmdate( 'd/m/Y', strtotime( $date . ' 12:00:00' ) ),
					vsg_meta( $p->ID, 'heure' ),
					$p->post_title,
					$place,
					vsg_meta( $p->ID, 'lieu' ),
					$types[ $type ][0] ?? '',
					'private' === $p->post_status ? 'oui' : 'non',
					trim( wp_strip_all_tags( $p->post_content ) ),
				) ),
				';',
				'"',
				''
			);
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
);

/* ---------- Vider l'agenda ---------- */

/**
 * Rendez-vous à retirer, publics et internes.
 * $place : '' = tous les lieux, '-' = sans paroisse ni chapelle, sinon slug de catégorie.
 * $type  : '' = tous les groupes, '-' = sans groupe, sinon slug de type.
 * $from  : '' = tout, passé compris ; sinon date AAAA-MM-JJ (rendez-vous à partir de ce jour).
 */
function vsg_purge_ids( $place, $type, $from ) {
	$args = array(
		'post_type'      => 'evenement',
		'post_status'    => array( 'publish', 'private', 'draft', 'future', 'pending' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(),
	);
	if ( $from ) {
		$args['meta_query'][] = array( 'key' => '_vsg_date', 'value' => $from, 'compare' => '>=' );
	}
	if ( '-' === $type ) {
		$args['meta_query'][] = array(
			'relation' => 'OR',
			array( 'key' => '_vsg_type', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_vsg_type', 'value' => '' ),
		);
	} elseif ( $type ) {
		$args['meta_query'][] = array( 'key' => '_vsg_type', 'value' => $type );
	}
	$known = array_values( vsg_import_places() );
	if ( '-' === $place ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => $known, 'operator' => 'NOT IN' ) );
	} elseif ( $place ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => array( $place ), 'include_children' => false ) );
	}
	return get_posts( $args );
}

/* Un rendez-vous sorti de la corbeille retrouve son statut d'avant (public ou interne), et non « brouillon ». */
add_filter(
	'wp_untrash_post_status',
	function ( $status, $post_id, $previous ) {
		return 'evenement' === get_post_type( $post_id ) && $previous ? $previous : $status;
	},
	10,
	3
);

/** Description lisible de la sélection, pour l'écran de confirmation. */
function vsg_purge_label( $place, $type, $from ) {
	$places = array_flip( vsg_import_places() );
	$types  = vsg_event_types();
	$p      = '' === $place ? 'tous les lieux' : ( '-' === $place ? 'sans paroisse ni chapelle' : ( $places[ $place ] ?? $place ) );
	$t      = '' === $type ? 'tous les groupes' : ( '-' === $type ? 'sans groupe' : ( $types[ $type ][0] ?? $type ) );
	return $p . ' · ' . $t . ' · ' . ( $from ? 'à venir seulement' : 'passés et à venir' );
}

/* ---------- Page d'admin ---------- */

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
			$f = $_FILES['vsg_file']; // phpcs:ignore
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
					$group = sanitize_key( $_POST['vsg_group'] ?? '' );
					$group = isset( vsg_event_types()[ $group ] ) ? $group : '';
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
			$purge = array( 'place' => $pp, 'type' => $pt, 'period' => $pf ? 'future' : 'all', 'ids' => $ids, 'label' => vsg_purge_label( $pp, $pt, $pf ) );
			if ( 'purge-apply' === $action ) {
				$n = 0;
				foreach ( $ids as $id ) {
					if ( current_user_can( 'delete_post', $id ) && wp_trash_post( $id ) ) {
						$n++;
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
				update_option( 'vsg_import_log', array( 'when' => current_time( 'mysql' ), 'who' => wp_get_current_user()->display_name, 'created' => $c, 'updated' => $u ) );
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
					$upd++;
				} else {
					$new++;
				}
			}
			?>
			<h2>Aperçu</h2>
			<p><strong><?php echo (int) $new; ?></strong> rendez-vous à créer, <strong><?php echo (int) $upd; ?></strong> à mettre à jour, <strong><?php echo count( $errors ); ?></strong> ligne(s) ignorée(s).</p>
			<?php if ( $errors ) : ?>
				<div class="notice notice-warning"><p><strong>Lignes ignorées</strong> (corrigez-les dans le fichier et recommencez, ou importez sans elles) :</p><ul class="vsg-list"><?php foreach ( $errors as $e ) : ?><li><?php echo esc_html( $e ); ?></li><?php endforeach; ?></ul></div>
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
								<?php echo vsg_event_type_options(); // phpcs:ignore ?>
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
						<li><strong>Type</strong> (facultatif, sert au filtre « Activité ») : <?php echo esc_html( implode( ' · ', array_column( array_filter( $types, function ( $t ) { return empty( $t[2] ); } ), 0 ) ) ); ?></li>
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
