<?php
/**
 * Template Name: Mot du curé
 *
 * Texte complet du mot du curé. Il se saisit dans la page d'accueil (encadré « Le mot du curé ») :
 * cette page n'a rien à remplir.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$home_id    = (int) get_option( 'page_on_front' );
$cure_paras = vsg_cure_paragraphs( vsg_cure_text() );
$cure_photo = vsg_meta( $home_id, 'cure_photo' );
?>
<section class="band band--tight">
	<div class="wrap narrow">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( $cure_paras ) : ?>
			<div class="curebox">
				<?php if ( $cure_photo ) : ?><div class="curebox__photo"><img src="<?php echo esc_url( $cure_photo ); ?>" alt=""></div><?php endif; ?>
				<div class="curebox__text">
					<?php echo vsg_cure_html( $cure_paras ); ?>
					<?php if ( vsg_meta( $home_id, 'cure_nom' ) ) : ?><p class="curebox__sign"><?php echo esc_html( vsg_meta( $home_id, 'cure_nom' ) ); ?></p><?php endif; ?>
				</div>
			</div>
		<?php else : ?>
			<p>Le mot du curé sera publié prochainement.</p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
