<?php
/**
 * Import de l'agenda : reconnaissance des colonnes, des lieux et des types, validation ligne par ligne.
 */

defined( 'ABSPATH' ) || exit;

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
	$map   = array();
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
	$map    = vsg_import_columns( $rows[0] ?? array() );
	if ( ! $map ) {
		$map = array(
			'date'    => 0,
			'heure'   => 1,
			'title'   => 2,
			'place'   => 3,
			'lieu'    => 4,
			'type'    => 5,
			'interne' => 6,
			'text'    => 7,
		);
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
	$a    = strtotime( $from . ' 12:00:00' );
	$b    = strtotime( $to . ' 12:00:00' );
	$m    = array( '', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' );
	$d    = function ( $ts ) {
		$j = (int) gmdate( 'j', $ts );
		return 1 === $j ? '1er' : (string) $j;
	};
	$same = gmdate( 'Y-m', $a ) === gmdate( 'Y-m', $b );
	$sy   = gmdate( 'Y', $a ) === gmdate( 'Y', $b );
	return 'Du ' . $d( $a ) . ( $same ? '' : ' ' . $m[ (int) gmdate( 'n', $a ) ] . ( $sy ? '' : ' ' . gmdate( 'Y', $a ) ) ) . ' au ' . $d( $b ) . ' ' . $m[ (int) gmdate( 'n', $b ) ] . '.';
}
