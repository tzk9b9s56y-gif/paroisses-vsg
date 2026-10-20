<?php
/**
 * Import de l'agenda : lecture des fichiers .csv et .xlsx (sans bibliothèque), dates et heures.
 */

defined( 'ABSPATH' ) || exit;

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
	$mois = array(
		'janvier'   => 1,
		'fevrier'   => 2,
		'mars'      => 3,
		'avril'     => 4,
		'mai'       => 5,
		'juin'      => 6,
		'juillet'   => 7,
		'aout'      => 8,
		'septembre' => 9,
		'octobre'   => 10,
		'novembre'  => 11,
		'decembre'  => 12,
	);
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
	$h    = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- fichier temporaire reçu par le formulaire, lu ligne à ligne.
	if ( ! $h ) {
		return $rows;
	}
	$first = fgets( $h );
	rewind( $h );
	$sep = substr_count( $first, ';' ) >= substr_count( $first, ',' ) ? ';' : ',';
	$r   = fgetcsv( $h, 0, $sep, '"', '' );
	while ( false !== $r ) {
		if ( $r && isset( $r[0] ) ) {
			$r[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $r[0] );
		}
		$rows[] = array_map(
			function ( $c ) {
				return mb_check_encoding( $c, 'UTF-8' ) ? $c : mb_convert_encoding( $c, 'UTF-8', 'Windows-1252' );
			},
			$r
		);
		$r      = fgetcsv( $h, 0, $sep, '"', '' );
	}
	fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- voir fopen ci-dessus.
	return $rows;
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
		$wb  = vsg_import_xml( $wbxml );
		$rl  = vsg_import_xml( $rels );
		$map = array();
		if ( $rl ) {
			foreach ( $rl->Relationship as $r ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- balise du fichier .xlsx.
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
	foreach ( $sx->sheetData->row as $row ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- balise du fichier .xlsx.
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
