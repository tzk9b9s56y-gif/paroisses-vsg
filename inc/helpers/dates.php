<?php
/**
 * Dates en français, indépendantes de la langue du site, et lecture des champs.
 */

defined( 'ABSPATH' ) || exit;

function vsg_meta( $post_id, $key ) {
	return (string) get_post_meta( $post_id, '_vsg_' . $key, true );
}

function vsg_months() {
	return array( 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' );
}

function vsg_months_short() {
	return array( 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.' );
}

function vsg_days() {
	return array( 'dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi' );
}

/** Découpe une date AAAA-MM-JJ en éléments affichables. */
function vsg_date_parts( $ymd ) {
	$t = strtotime( $ymd . ' 12:00:00' );
	if ( ! $t ) {
		return null;
	}
	$m = (int) gmdate( 'n', $t ) - 1;
	return array(
		'day'        => gmdate( 'j', $t ),
		'month'      => vsg_months_short()[ $m ],
		'month_full' => vsg_months()[ $m ],
		'year'       => gmdate( 'Y', $t ),
		'dow'        => vsg_days()[ (int) gmdate( 'w', $t ) ],
		'ym'         => gmdate( 'Y-m', $t ),
	);
}

function vsg_date_long( $ymd ) {
	$p = vsg_date_parts( $ymd );
	return $p ? sprintf( '%s %s %s', $p['dow'], '1' === $p['day'] ? '1er' : $p['day'], $p['month_full'] ) : '';
}

/** Date de publication d'un article, en français. */
function vsg_post_date( $post ) {
	$p = vsg_date_parts( get_the_date( 'Y-m-d', $post ) );
	return $p ? sprintf( '%s %s %s', '1' === $p['day'] ? '1er' : $p['day'], $p['month_full'], $p['year'] ) : '';
}

function vsg_time_label( $hm ) {
	if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $hm, $m ) ) {
		return '';
	}
	return (int) $m[1] . ' h' . ( '00' === $m[2] ? '' : ' ' . $m[2] );
}

function vsg_today() {
	return current_time( 'Y-m-d' );
}
