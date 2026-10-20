<?php
/**
 * Template Name: Paroisse – sacrements (liste)
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$parish = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, get_post() );
}
$children = get_pages(
	array(
		'parent'      => get_the_ID(),
		'sort_column' => 'menu_order',
	)
);
$infos    = $parish ? vsg_parish_child_by_template( $parish->ID, 'templates/infos.php' ) : null;
?>
<section class="band">
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
		<div class="grid grid--3 tiles">
			<?php foreach ( $children as $c ) : ?>
				<a class="tile" href="<?php echo esc_url( get_permalink( $c ) ); ?>">
					<span class="tile__title"><?php echo esc_html( get_the_title( $c ) ); ?></span>
					<?php if ( $c->post_excerpt ) : ?><span class="tile__text"><?php echo esc_html( $c->post_excerpt ); ?></span><?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php if ( $infos ) : ?>
<section class="band band--soft band--tight">
	<div class="wrap cta cta--soft">
		<div>
			<h2>Une question avant de commencer ?</h2>
			<p>L'accueil paroissial vous oriente<?php echo $parish && vsg_meta( $parish->ID, 'telephone' ) ? ' : ' . esc_html( vsg_meta( $parish->ID, 'telephone' ) ) : ''; ?>.</p>
		</div>
		<a class="btn" href="<?php echo esc_url( get_permalink( $infos ) ); ?>#contact-form">Contacter l'accueil</a>
	</div>
</section>
<?php endif; ?>
<?php
get_footer();
