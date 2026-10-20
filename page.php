<?php
/**
 * Page standard : dans une paroisse, elle reprend le bandeau et les onglets.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$parish = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, get_post() );
}
$parent = wp_get_post_parent_id( get_the_ID() );
?>
<section class="band">
	<article class="wrap narrow">
		<h1><?php the_title(); ?></h1>
		<?php $portrait = $parent && 'templates/prier.php' === get_page_template_slug( $parent ); ?>
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="figure<?php echo $portrait ? ' figure--portrait' : ''; ?>"><?php the_post_thumbnail( ( $portrait || vsg_thumb_is_wide( get_post() ) ) ? 'large' : 'vsg-hero', array( 'alt' => '' ) ); ?></div>
		<?php endif; ?>
		<div class="prose"><?php the_content(); ?></div>
		<?php
		if ( $parish && $parent && $parent !== $parish->ID ) :
			$infos = vsg_parish_child_by_template( $parish->ID, 'templates/infos.php' );
			if ( $infos ) :
				?>
				<aside class="askbox">
					<h2>Faire la demande</h2>
					<p>Contactez l'accueil de la paroisse<?php echo vsg_meta( $parish->ID, 'telephone' ) ? ' au ' . esc_html( vsg_meta( $parish->ID, 'telephone' ) ) : ''; ?>, ou écrivez-nous.</p>
					<a class="btn" href="<?php echo esc_url( get_permalink( $infos ) ); ?>#contact-form">Contacter l'accueil</a>
				</aside>
				<?php
				if ( in_array( get_post_field( 'post_name' ), array( 'mariage', 'reconciliation' ), true ) ) {
					echo vsg_cure_contact_block(); }
				?>
				<?php
			endif;
		endif;
		?>
	</article>
</section>
<?php
get_footer();
