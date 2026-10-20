<?php
/**
 * Communautés et groupes de prière : pages, menu, programme à part.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Communautés et groupes de prière (pages avec le modèle « Communauté »).
 * Avec une paroisse : seulement celles qui lui sont rattachées.
 */
function vsg_communities( $parish_id = null ) {
	$pages = get_pages(
		array(
			'meta_key'     => '_wp_page_template',
			'meta_value'   => VSG_TPL_COMMUNAUTE,
			'sort_column'  => 'menu_order,post_title',
			'hierarchical' => 0,
		)
	);
	if ( null === $parish_id ) {
		return $pages;
	}
	return array_values(
		array_filter(
			$pages,
			function ( $c ) use ( $parish_id ) {
				$p = vsg_context_parish( $c );
				return $p && (int) $p->ID === (int) $parish_id;
			}
		)
	);
}

/** Communautés communes aux deux paroisses (hors arborescence d'une paroisse). */
function vsg_communities_common() {
	return array_values(
		array_filter(
			vsg_communities(),
			function ( $c ) {
				return ! vsg_context_parish( $c );
			}
		)
	);
}

/**
 * Menu « Groupe ou communauté » : tous les groupes, rangés par paroisse, pour aller droit à l'un d'eux.
 * $current : ID de la page affichée (ou 0 sur la page qui les liste).
 */
function vsg_communities_menu( $current = 0 ) {
	$all = vsg_communities_page();
	if ( ! vsg_communities() ) {
		return;
	}
	$items = array();
	if ( $all ) {
		$items[''] = array( 'Tous les groupes', get_permalink( $all ) );
	}
	$groups = array( 'Pour les deux paroisses' => vsg_communities_common() );
	foreach ( vsg_parishes() as $p ) {
		$groups[ get_the_title( $p ) ] = vsg_communities( $p->ID );
	}
	$seen = array_count_values( wp_list_pluck( vsg_communities(), 'post_title' ) );
	foreach ( $groups as $head => $list ) {
		if ( ! $list ) {
			continue;
		}
		$items[ 'head-' . sanitize_title( $head ) ] = array( $head, '' );
		foreach ( $list as $c ) {
			$label = get_the_title( $c );
			$ctx   = vsg_context_parish( $c );
			if ( $ctx && $seen[ $label ] > 1 ) {
				$label .= ' – ' . get_the_title( $ctx );
			}
			$items[ 'c-' . $c->ID ] = array( $label, get_permalink( $c ) . '#programme' );
		}
	}
	$cur       = $current ? 'c-' . $current : '';
	$cur_label = isset( $items[ $cur ] ) ? $items[ $cur ][0] : '';
	$links     = '';
	foreach ( $items as $slug => $it ) {
		if ( '' === $it[1] ) {
			$links .= '<span class="legend__head">' . esc_html( $it[0] ) . '</span>';
		} else {
			$links .= '<a href="' . esc_url( $it[1] ) . '"' . ( $slug === $cur ? ' aria-current="true"' : '' ) . '>' . esc_html( $it[0] ) . '</a>';
		}
	}
	echo '<details class="menu menu--filters"><summary><span class="menu__label">Groupe ou communauté</span><span class="menu__current">' . esc_html( $cur_label ) . '</span></summary><nav aria-label="Groupe ou communauté">' . $links . '</nav></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- liens construits avec esc_url() et esc_html().
}

/** Page commune qui liste toutes les communautés (modèle « Communautés », hors paroisse). */
function vsg_communities_page() {
	foreach ( get_pages(
		array(
			'meta_key'     => '_wp_page_template',
			'meta_value'   => VSG_TPL_COMMUNAUTES,
			'hierarchical' => 0,
		)
	) as $p ) {
		if ( ! vsg_context_parish( $p ) ) {
			return $p;
		}
	}
	return null;
}

/** Communautés et groupes de prière (programme à part) : slug => libellé. */
function vsg_group_types() {
	$out = array();
	foreach ( vsg_event_types() as $slug => $t ) {
		if ( ! empty( $t[2] ) ) {
			$out[ $slug ] = $t[0];
		}
	}
	return $out;
}

/**
 * Page d'un groupe pour un type d'agenda. Un même groupe peut avoir une page par paroisse
 * (la chorale) : on prend celle de la paroisse indiquée, sinon la première.
 */
function vsg_group_page( $type, $parish = null ) {
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => '_vsg_type',
			'meta_value'     => $type,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		)
	);
	if ( ! $pages ) {
		return null;
	}
	if ( $parish ) {
		foreach ( $pages as $p ) {
			$ctx = vsg_context_parish( $p );
			if ( $ctx && (int) $ctx->ID === (int) $parish->ID ) {
				return $p;
			}
		}
	}
	return $pages[0];
}

/** Carte d'une communauté : nom, paroisse, résumé, premier rendez-vous régulier. */
function vsg_community_card( $page, $show_parish = true ) {
	$parish = vsg_context_parish( $page );
	$prog   = array_values( array_filter( array_map( 'trim', explode( "\n", vsg_meta( $page->ID, 'programme' ) ) ) ) );
	ob_start();
	?>
	<a class="card" href="<?php echo esc_url( get_permalink( $page ) ); ?>"<?php echo vsg_accent_attr( $page ); ?>>
		<span class="card__media"><?php echo vsg_thumb( $page, 'vsg-card', 'Communauté' ); ?></span>
		<span class="card__body">
			<?php if ( $show_parish && $parish ) : ?><span class="chip"><?php echo esc_html( get_the_title( $parish ) ); ?></span><?php endif; ?>
			<span class="card__title"><?php echo esc_html( get_the_title( $page ) ); ?></span>
			<span class="card__text"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $page ) ) ); ?></span>
			<?php if ( $prog ) : ?><span class="card__date"><?php echo esc_html( $prog[0] ); ?></span><?php endif; ?>
		</span>
	</a>
	<?php
	return ob_get_clean();
}
