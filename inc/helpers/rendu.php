<?php
/**
 * Éléments d'affichage réutilisés par les modèles : cartes, tuiles, filtres, images.
 */

defined( 'ABSPATH' ) || exit;

/** Tuile de menu. */
function vsg_tile( $title, $url, $text = '', $icon = '' ) {
	return '<a class="tile" href="' . esc_url( $url ) . '">' . ( $icon ? '<span class="tile__icon" aria-hidden="true">' . $icon . '</span>' : '' ) . '<span class="tile__title">' . esc_html( $title ) . '</span>' . ( $text ? '<span class="tile__text">' . esc_html( $text ) . '</span>' : '' ) . '</a>';
}

/** Description courte d'une rubrique de paroisse, d'après son modèle. */
function vsg_tile_text( $page ) {
	if ( $page->post_excerpt ) {
		return $page->post_excerpt;
	}
	$map = array(
		'templates/actualites.php'  => 'Rendez-vous et nouvelles de la paroisse',
		'templates/feuille.php'     => 'La feuille de la semaine en PDF',
		'templates/infos.php'       => 'Accueil, contact, plans d\'accès',
		'templates/sacrements.php'  => 'Baptême, mariage, funérailles…',
		'templates/galerie.php'     => 'Photos des événements',
		VSG_TPL_COMMUNAUTES         => 'Qui ils sont, quand les rejoindre',
		'templates/histoire.php'    => 'Les origines, les lieux de culte',
		'templates/chapelles.php'   => 'Saint-Louis et Saint-Joseph : horaires, accès, histoire',
	);
	$tpl = get_page_template_slug( $page );
	if ( isset( $map[ $tpl ] ) ) {
		return $map[ $tpl ];
	}
	if ( 'catechese-et-jeunes' === $page->post_name ) {
		return 'Éveil à la foi, caté, aumônerie';
	}
	return '';
}

/**
 * Albums (actualités avec une galerie d'images) et vidéos d'une paroisse.
 *
 * @return array{0:WP_Post[],1:WP_Post[]}
 */
function vsg_gallery_items( $parish ) {
	static $cache = array();
	$key = $parish ? $parish->ID : 0;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$q      = vsg_news_query(
		array(
			'terms' => $parish ? vsg_parish_term_ids( $parish->ID ) : array(),
			'limit' => 60,
		)
	);
	$albums = array();
	$videos = array();
	foreach ( $q->posts as $p ) {
		if ( has_block( 'core/gallery', $p ) ) {
			$albums[] = $p;
		}
		if ( has_block( 'core/embed', $p ) || has_block( 'core/video', $p ) ) {
			$videos[] = $p;
		}
	}
	$cache[ $key ] = array( $albums, $videos );
	return $cache[ $key ];
}

/** Rubrique à masquer dans les onglets et les tuiles : galerie sans album ni vidéo. */
function vsg_page_is_empty( $page ) {
	if ( 'templates/galerie.php' !== get_page_template_slug( $page ) ) {
		return false;
	}
	list( $albums, $videos ) = vsg_gallery_items( vsg_context_parish( $page ) );
	return ! $albums && ! $videos;
}

/**
 * @param array  $items   slug => array( label, url ).
 * @param string $current slug courant.
 * @param string $label   Libellé du menu.
 */
function vsg_filters( $items, $current, $label = 'Filtrer par lieu' ) {
	$cur_label = isset( $items[ $current ] ) ? $items[ $current ][0] : '';
	echo '<details class="menu menu--filters"><summary><span class="menu__label">' . esc_html( $label ) . '</span><span class="menu__current">' . esc_html( $cur_label ) . '</span></summary><nav aria-label="' . esc_attr( $label ) . '">';
	foreach ( $items as $slug => $it ) {
		echo '<a href="' . esc_url( $it[1] ) . '"' . ( $slug === $current ? ' aria-current="true"' : '' ) . '>' . esc_html( $it[0] ) . '</a>';
	}
	echo '</nav></details>';
}

function vsg_placeholder( $label = 'Photo' ) {
	return '<div class="ph" aria-hidden="true"><span>' . esc_html( $label ) . '</span></div>';
}

/** Vrai si l'image mise en avant est une bannière très large (plus de 2 fois plus large que haute). */
function vsg_thumb_is_wide( $post ) {
	$id = get_post_thumbnail_id( $post );
	if ( ! $id ) {
		return false;
	}
	$src = wp_get_attachment_image_src( $id, 'full' );
	return $src && $src[2] > 0 && $src[1] / $src[2] > 2;
}

function vsg_thumb( $post, $size = 'vsg-card', $label = 'Photo' ) {
	if ( has_post_thumbnail( $post ) ) {
		return get_the_post_thumbnail( $post, $size, array( 'alt' => '' ) );
	}
	return vsg_placeholder( $label );
}

function vsg_date_badge( $ymd ) {
	$p = vsg_date_parts( $ymd );
	if ( ! $p ) {
		return '';
	}
	return '<span class="datebadge" aria-hidden="true"><span class="datebadge__d">' . esc_html( $p['day'] ) . '</span><span class="datebadge__m">' . esc_html( $p['month'] ) . '</span></span>';
}

/** Rendez-vous : « card » (accueil), « row » (listes). */
function vsg_event( $post, $variant = 'card', $show_place = true ) {
	$date  = vsg_meta( $post->ID, 'date' );
	$heure = vsg_time_label( vsg_meta( $post->ID, 'heure' ) );
	$lieu  = vsg_meta( $post->ID, 'lieu' );
	$group = vsg_event_group( $post );
	$type  = vsg_event_type( $post );
	if ( $group ) {
		// Activité d'un groupe : l'étiquette nomme le groupe, le lieu passe dans la ligne de détails.
		$chip  = $group['label'];
		$style = ' style="--accent:' . esc_attr( $group['color'] ) . '"';
		$meta  = array_merge( array( $heure ), vsg_event_where( $post ) );
	} else {
		$chip  = $show_place ? vsg_place_label( $post ) : '';
		$style = vsg_accent_attr( $post );
		$meta  = array( $heure, $lieu, ( $type && 'row' === $variant ) ? $type['label'] : '' );
	}
	$meta  = array_filter( $meta );
	$when  = trim( vsg_date_long( $date ) . ( $heure ? ', ' . $heure : '' ) );
	$img   = ( 'card' === $variant && has_post_thumbnail( $post ) ) ? get_the_post_thumbnail( $post, 'medium_large', array( 'class' => 'event__img', 'alt' => vsg_poster_alt( $post ), 'loading' => 'eager' ) ) : '';
	ob_start();
	?>
	<a class="event event--<?php echo esc_attr( $variant ); ?><?php echo $img ? ' event--img' : ''; ?>" href="<?php echo esc_url( get_permalink( $post ) ); ?>"<?php echo $style; // phpcs:ignore ?>>
		<?php echo $img; // phpcs:ignore ?>
		<?php echo vsg_date_badge( $date ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<span class="event__body">
			<?php if ( $chip ) : ?><span class="chip<?php echo $group ? ' chip--group' : ''; ?>"><?php echo esc_html( $chip ); ?></span><?php endif; ?>
			<?php if ( 'private' === $post->post_status ) : ?><span class="chip chip--private">Interne</span><?php endif; ?>
			<span class="event__title"><?php if ( $type && ! $group ) : ?><span class="dot" style="background:<?php echo esc_attr( $type['color'] ); ?>" title="<?php echo esc_attr( $type['label'] ); ?>"></span><?php endif; ?><?php echo esc_html( get_the_title( $post ) ); ?></span>
			<span class="sr-only"><?php echo esc_html( $when ); ?></span>
			<?php if ( $meta ) : ?><span class="event__meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></span><?php endif; ?>
		</span>
	</a>
	<?php
	return ob_get_clean();
}

function vsg_news_card( $post, $show_place = true, $show_date = false ) {
	ob_start();
	?>
	<?php $wide = vsg_thumb_is_wide( $post ); ?>
	<a class="card" href="<?php echo esc_url( get_permalink( $post ) ); ?>"<?php echo vsg_accent_attr( $post ); // phpcs:ignore ?>>
		<span class="card__media<?php echo $wide ? ' card__media--wide' : ''; ?>"><?php echo vsg_thumb( $post, $wide ? 'large' : 'vsg-card' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<span class="card__body">
			<?php if ( $show_place && vsg_place_label( $post ) ) : ?><span class="chip"><?php echo esc_html( vsg_place_label( $post ) ); ?></span><?php endif; ?>
			<?php if ( $show_date ) : ?><span class="card__date"><?php echo esc_html( vsg_post_date( $post ) ); ?></span><?php endif; ?>
			<span class="card__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
			<span class="card__text"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $post ) ) ); ?></span>
		</span>
	</a>
	<?php
	return ob_get_clean();
}

function vsg_section_head( $title, $link_url = '', $link_label = '', $tag = 'h2' ) {
	echo '<div class="sectionhead"><' . $tag . '>' . esc_html( $title ) . '</' . $tag . '>'; // phpcs:ignore
	if ( $link_url ) {
		echo '<a href="' . esc_url( $link_url ) . '">' . esc_html( $link_label ) . '</a>';
	}
	echo '</div>';
}

function vsg_empty( $text ) {
	echo '<p class="empty">' . esc_html( $text ) . '</p>';
}

/** Bandeau d'une paroisse avec ses onglets. */
function vsg_parish_band( $parish, $current = null ) {
	get_template_part( 'template-parts/parish-band', null, array( 'parish' => $parish, 'current' => $current ) );
}

function vsg_nl2br( $text ) {
	return nl2br( esc_html( $text ) );
}

/**
 * Texte alternatif d'une affiche : celui saisi dans la Médiathèque,
 * sinon « Affiche : » suivi du titre du rendez-vous.
 */
function vsg_poster_alt( $post ) {
	$alt = trim( (string) get_post_meta( get_post_thumbnail_id( $post ), '_wp_attachment_image_alt', true ) );
	return '' !== $alt ? $alt : 'Affiche : ' . wp_strip_all_tags( get_the_title( $post ) );
}
