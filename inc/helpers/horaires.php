<?php
/**
 * Horaires des messes de chaque lieu, page commune des horaires, contacts des prêtres.
 */

defined( 'ABSPATH' ) || exit;

/** Lignes d'horaires d'un lieu, par moment de la semaine. */
function vsg_hours( $page_id ) {
	$out = array();
	foreach ( array(
		'semaine'     => 'En semaine',
		'samedi'      => 'Samedi',
		'dimanche'    => 'Dimanche',
		'confessions' => 'Confessions et adoration',
	) as $k => $label ) {
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", vsg_meta( $page_id, 'messes_' . $k ) ) ) ) );
		if ( $lines ) {
			$out[ $k ] = array(
				'label' => $label,
				'lines' => $lines,
			);
		}
	}
	return $out;
}

/** Messes du dimanche en une ligne (« messe à 9 h 30, messe à 11 h »). */
function vsg_hours_sunday_line( $hours ) {
	return implode(
		', ',
		array_map(
			function ( $l ) {
				return preg_replace( '/^Messe (à )?/u', 'messe à ', $l );
			},
			$hours['dimanche']['lines']
		)
	);
}

/** Bloc d'horaires d'un lieu (titre facultatif). */
function vsg_hours_block( $page_id, $title = '', $compact = false ) {
	$hours = vsg_hours( $page_id );
	ob_start();
	echo '<div class="hours' . ( $compact ? ' hours--compact' : '' ) . '">';
	if ( $title ) {
		echo '<h3 class="hours__title">' . esc_html( $title ) . '</h3>';
	}
	if ( ! $hours ) {
		echo '<p class="empty">Horaires à compléter.</p>';
	} else {
		echo '<dl class="hours__list">';
		foreach ( $hours as $h ) {
			echo '<div class="hours__row"><dt>' . esc_html( $h['label'] ) . '</dt><dd>';
			foreach ( $h['lines'] as $l ) {
				echo '<span>' . esc_html( $l ) . '</span>';
			}
			echo '</dd></div>';
		}
		echo '</dl>';
	}
	echo '</div>';
	return ob_get_clean();
}

/** Page commune des horaires (modèle « Horaires »). */
function vsg_hours_page() {
	$pages = get_pages(
		array(
			'meta_key'     => '_wp_page_template',
			'meta_value'   => VSG_TPL_HORAIRES,
			'hierarchical' => 0,
		)
	);
	return $pages ? $pages[0] : null;
}

/**
 * Les lieux qui ont une page d'horaires : slug => array( post, libellé, adresse, paroisse, page du lieu ).
 * Ordre : chaque église suivie de ses chapelles.
 */
function vsg_hours_places() {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$out = array();
	foreach ( vsg_parishes() as $p ) {
		$out[ $p->post_name ] = array( $p, 'Église ' . get_the_title( $p ), vsg_meta( $p->ID, 'eglise' ), $p, get_permalink( $p ) );
		foreach ( vsg_chapels( $p->ID ) as $c ) {
			$out[ $c->post_name ] = array( $c, get_the_title( $c ), vsg_meta( $c->ID, 'adresse' ), $p, get_permalink( $c ) );
		}
	}
	return $out;
}

/** Lieu demandé par ?lieu= sur la page des horaires (ou ''). */
function vsg_hours_current() {
	$slug = isset( $_GET['lieu'] ) ? sanitize_title( wp_unslash( $_GET['lieu'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- simple filtre d'affichage.
	return ( $slug && isset( vsg_hours_places()[ $slug ] ) ) ? $slug : '';
}

/** Page des horaires, filtrée sur un lieu (église ou chapelle) quand $page_id est donné. */
function vsg_hours_url( $page_id = 0 ) {
	$page = vsg_hours_page();
	if ( ! $page ) {
		return '';
	}
	$url = get_permalink( $page );
	if ( $page_id ) {
		$p = get_post( $page_id );
		if ( $p && isset( vsg_hours_places()[ $p->post_name ] ) && (int) vsg_hours_places()[ $p->post_name ][0]->ID === (int) $p->ID ) {
			$url = add_query_arg( 'lieu', $p->post_name, $url );
		}
	}
	return $url;
}

/** Bouton « Horaires » vers la page des horaires du lieu. */
function vsg_messes_button( $page_id, $css_class = 'btn' ) {
	$url = vsg_hours_url( $page_id );
	if ( ! $url ) {
		$url = vsg_page_url( 'horaires-des-messes' );
	}
	return '<a class="' . esc_attr( $css_class ) . '" href="' . esc_url( $url ) . '">Horaires</a>';
}

/** Bloc « Rencontrer un prêtre ». */
function vsg_priest_block( $parish_id = 0 ) {
	$tel = $parish_id ? vsg_meta( $parish_id, 'telephone' ) : '';
	ob_start();
	?>
	<div class="infocard infocard--soft">
		<h2>Rencontrer un prêtre</h2>
		<p>Pour un temps d'écoute, un accompagnement spirituel ou une confession en dehors des horaires, un prêtre peut vous recevoir sur rendez-vous.</p>
		<?php if ( $tel ) : ?><p>Par téléphone à l'accueil : <strong><?php echo esc_html( $tel ); ?></strong></p><?php endif; ?>
		<p><a class="btn" href="<?php echo esc_url( add_query_arg( 'objet', 'pretre', vsg_page_url( 'contact' ) ) . '#contact-form' ); ?>">Demander un rendez-vous</a></p>
	</div>
	<?php echo vsg_cure_contact_block(); ?>
	<?php
	return ob_get_clean();
}

/** Contact direct du curé (réglé sur la page d'accueil). */
function vsg_cure_contact_block() {
	$home = get_option( 'page_on_front' );
	$nom  = vsg_meta( $home, 'cure_nom' );
	$tel  = vsg_meta( $home, 'cure_tel' );
	$mail = vsg_meta( $home, 'cure_email' );
	if ( ! $tel && ! $mail ) {
		return '';
	}
	$h  = '<div class="infocard infocard--soft curecontact"><h2>Joindre directement le curé</h2>';
	$h .= '<p>Pour ce type de demande, vous pouvez aussi contacter ' . ( $nom ? 'le ' . esc_html( preg_replace( '/,.*$/', '', $nom ) ) : 'le curé' ) . ' :</p><ul class="plainlist">';
	if ( $tel ) {
		$h .= '<li><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ) . '">' . esc_html( $tel ) . '</a></li>';
	}
	if ( $mail ) {
		$h .= '<li>' . vsg_mailto( $mail ) . '</li>';
	}
	return $h . '</ul></div>';
}
