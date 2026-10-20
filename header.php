<?php
/**
 * En-tête : barre des pages communes, puis logo et paroisses.
 */

defined( 'ABSPATH' ) || exit;

$context = is_singular() ? vsg_context_parish() : null;
$common  = array(
	'horaires-des-messes' => 'Horaires',
	'agenda'   => 'Agenda',
	'vivre-en-eglise' => 'Vivre en Église',
	'communautes' => 'Groupes et communautés',
	'prier'    => 'Textes du jour et vie des saints',
	'intention-de-priere' => 'Intention de prière',
	'demande-de-messe' => 'Demander une messe',
	'contact'  => 'Contact',
);
$long = array( 'intention-de-priere' => 'Déposer une intention de prière' );
$current_common = '';
foreach ( array_keys( $common ) as $slug ) {
	if ( is_page( $slug ) ) {
		$current_common = $slug;
	}
}
if ( is_singular( 'evenement' ) && ! $context ) {
	$current_common = 'agenda';
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#contenu">Aller au contenu</a>
<header class="siteheader">
	<div class="wrap mainbar">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>>
			<?php
			$logo_id = get_theme_mod( 'custom_logo' );
			if ( $logo_id ) {
				echo wp_get_attachment_image( $logo_id, 'thumbnail', false, array( 'class' => 'brand__logo', 'alt' => '' ) );
			} else {
				echo '<img class="brand__logo brand__logo--mark" src="' . esc_url( VSG_URI . '/assets/img/logo-mark.svg' ) . '" alt="" width="64" height="56">';
			}
			?>
			<span class="brand__text">
				<?php
				$site_name = get_bloginfo( 'name' );
				$prefix    = 'Ensemble paroissial ';
				if ( 0 === strpos( $site_name, $prefix ) ) {
					echo '<span class="brand__kicker">Ensemble paroissial</span>';
					$site_name = substr( $site_name, strlen( $prefix ) );
				}
				?>
				<span class="brand__name"><?php echo esc_html( str_replace( '-', "\u{2011}", $site_name ) ); ?></span>
				<span class="brand__sub">Villeneuve-Saint-Georges · Villeneuve-Triage</span>
			</span>
		</a>
		<div class="burger">
			<input type="checkbox" id="vsg-burger" class="burger__check" aria-controls="vsg-burger-nav">
			<label for="vsg-burger" class="burger__btn" aria-label="Menu"><span class="burger__icon" aria-hidden="true"><i></i><i></i><i></i></span><span class="sr-only">Menu</span></label>
			<label for="vsg-burger" class="burger__backdrop" aria-hidden="true"></label>
			<nav class="burger__nav" id="vsg-burger-nav" aria-label="Pages communes">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>>Accueil</a>
				<?php foreach ( $common as $slug => $label ) : ?>
					<a href="<?php echo esc_url( vsg_page_url( $slug ) ); ?>"<?php echo $slug === $current_common ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $long[ $slug ] ?? $label ); ?></a>
				<?php endforeach; ?>
				<a href="<?php echo esc_url( vsg_page_url( 'soutenir' ) ); ?>"<?php echo is_page( 'soutenir' ) ? ' aria-current="page"' : ''; ?>>Soutenir la paroisse</a>
				<a class="topbar__don" href="<?php echo esc_url( vsg_denier_url() ); ?>" target="_blank" rel="noopener">Donner au denier<span class="sr-only"> (nouvel onglet)</span></a>
			</nav>
		</div>
		<?php
		$home_id   = (int) get_option( 'page_on_front' );
		$theme_t   = $home_id ? vsg_meta( $home_id, 'theme_titre' ) : '';
		$theme_img = $home_id ? vsg_meta( $home_id, 'theme_image' ) : '';
		$theme_url = $home_id ? vsg_meta( $home_id, 'theme_lien' ) : '';
		if ( is_front_page() && ( $theme_t || $theme_img ) ) :
			$tag = $theme_url ? 'a' : 'div';
			?>
			<<?php echo $tag; ?> class="yeartheme<?php echo $theme_img ? ' yeartheme--img' : ''; ?>"<?php echo $theme_url ? ' href="' . esc_url( $theme_url ) . '"' : ''; ?> title="Thème de l'année">
				<?php if ( $theme_img ) : ?>
					<img src="<?php echo esc_url( $theme_img ); ?>" alt="<?php echo esc_attr( $theme_t ? 'Thème de l\'année : ' . $theme_t : 'Thème de l\'année' ); ?>">
				<?php else : ?>
					<span class="yeartheme__kicker">Thème de l'année</span>
					<span class="yeartheme__title"><?php echo esc_html( $theme_t ); ?></span>
				<?php endif; ?>
			</<?php echo $tag; ?>>
		<?php endif; ?>
		<?php if ( vsg_parishes() && is_front_page() ) : ?>
			<nav class="parishnav" aria-label="Nos paroisses">
				<?php foreach ( vsg_parishes() as $p ) : ?>
					<a href="<?php echo esc_url( get_permalink( $p ) ); ?>" style="--accent:<?php echo esc_attr( vsg_parish_color( $p ) ); ?>"<?php echo ( $context && $context->ID === $p->ID ) ? ' aria-current="true"' : ''; ?>><?php echo esc_html( get_the_title( $p ) ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
		<a class="diocese-badge" href="https://catholiques-val-de-marne.cef.fr/" target="_blank" rel="noopener">
			<img src="<?php echo esc_url( VSG_URI . '/assets/img/logo-diocese-creteil.png' ); ?>" alt="Diocèse de Créteil" width="120" height="99">
			<span class="sr-only"> (site du diocèse, nouvel onglet)</span>
		</a>
	</div>
</header>
<?php vsg_auto_breadcrumb(); ?>
<main id="contenu" tabindex="-1"<?php echo $context ? ' class="has-parish" style="--accent:' . esc_attr( vsg_parish_color( $context ) ) . '"' : ''; ?>>
