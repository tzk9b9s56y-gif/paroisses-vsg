<?php
/**
 * Bandeau d'une paroisse : nom et onglets de ses rubriques.
 *
 * @var array $args parish (WP_Post), current (WP_Post|null)
 */

defined( 'ABSPATH' ) || exit;

$parish  = $args['parish'];
$current = $args['current'] ?? get_queried_object();
$tabs    = array( $parish );
foreach ( get_pages( array( 'parent' => $parish->ID, 'sort_column' => 'menu_order' ) ) as $child ) {
	if ( VSG_TPL_CHAPELLE !== get_page_template_slug( $child ) && ! vsg_page_is_empty( $child ) ) {
		$tabs[] = $child;
	}
}

$active = $parish->ID;
if ( $current instanceof WP_Post ) {
	if ( 'page' === $current->post_type ) {
		$chain = array_merge( array( $current->ID ), get_post_ancestors( $current ) );
		foreach ( $tabs as $t ) {
			if ( $t->ID !== $parish->ID && in_array( $t->ID, $chain, true ) ) {
				$active = $t->ID;
			}
		}
		if ( VSG_TPL_CHAPELLE === get_page_template_slug( $current ) ) {
			$cp = vsg_parish_child_by_template( $parish->ID, 'templates/chapelles.php' );
			if ( $cp ) {
				$active = $cp->ID;
			}
		}
	} else {
		$tpl = 'feuille' === $current->post_type ? 'templates/feuille.php' : 'templates/actualites.php';
		$p   = vsg_parish_child_by_template( $parish->ID, $tpl );
		if ( $p ) {
			$active = $p->ID;
		}
	}
}
?>
<?php
$active_label = 'Présentation';
foreach ( $tabs as $t ) {
	if ( $t->ID === $active ) {
		$active_label = $t->ID === $parish->ID ? 'Présentation' : get_the_title( $t );
	}
}
?>
<div class="parishband" style="--accent:<?php echo esc_attr( vsg_parish_color( $parish ) ); ?>">
	<div class="wrap">
		<a class="parishband__name" href="<?php echo esc_url( get_permalink( $parish ) ); ?>">
			<span class="eyebrow">Paroisse</span>
			<span class="parishband__title"><?php echo esc_html( get_the_title( $parish ) ); ?></span>
		</a>
		<details class="menu menu--tabs">
			<summary><span class="menu__label">Rubriques</span><span class="menu__current"><?php echo esc_html( $active_label ); ?></span></summary>
			<nav aria-label="Rubriques de la paroisse <?php echo esc_attr( get_the_title( $parish ) ); ?>">
				<?php foreach ( $tabs as $t ) : ?>
					<a href="<?php echo esc_url( get_permalink( $t ) ); ?>"<?php echo $t->ID === $active ? ( ( $current instanceof WP_Post && $current->ID === $t->ID ) ? ' aria-current="page"' : ' aria-current="true"' ) : ''; ?>><?php echo esc_html( $t->ID === $parish->ID ? 'Présentation' : get_the_title( $t ) ); ?></a>
				<?php endforeach; ?>
			</nav>
		</details>
	</div>
</div>
