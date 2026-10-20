<?php
/**
 * Template Name: Paroisse – feuille paroissiale
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$parish = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, get_post() );
}
$term = $parish ? vsg_page_term( $parish->ID ) : false;
$q    = $term ? vsg_feuilles_query( array( $term->term_id ), 13 ) : null;
$all  = $q ? $q->posts : array();
$cur  = $all ? array_shift( $all ) : null;
?>
<section class="band">
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
		<?php if ( $cur && vsg_feuille_pdf( $cur ) ) : ?>
			<div class="featurefile">
				<span class="eyebrow">Cette semaine</span>
				<strong class="featurefile__title"><?php echo esc_html( vsg_feuille_label( $cur ) ); ?></strong>
				<?php if ( trim( $cur->post_content ) ) : ?><p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $cur->post_content ), 30 ) ); ?></p><?php endif; ?>
				<div class="actions">
					<a class="btn" href="<?php echo esc_url( vsg_feuille_pdf( $cur ) ); ?>" download>Télécharger le PDF</a>
					<a class="btn btn--ghost" href="<?php echo esc_url( vsg_feuille_pdf( $cur ) ); ?>" target="_blank" rel="noopener">Ouvrir dans le navigateur<span class="sr-only"> (nouvel onglet)</span></a>
				</div>
			</div>
		<?php else : ?>
			<?php vsg_empty( 'La feuille de la semaine n\'a pas encore été déposée.' ); ?>
		<?php endif; ?>
	</div>
</section>
<?php if ( $all ) : ?>
<section class="band band--soft">
	<div class="wrap narrow">
		<h2>Archives</h2>
		<ul class="filelist">
			<?php foreach ( $all as $f ) : ?>
				<?php $pdf = vsg_feuille_pdf( $f ); ?>
				<li>
					<span><?php echo esc_html( vsg_feuille_label( $f ) ); ?></span>
					<?php if ( $pdf ) : ?><a href="<?php echo esc_url( $pdf ); ?>" download>Télécharger<span class="sr-only"> <?php echo esc_html( vsg_feuille_label( $f ) ); ?></span></a><?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
<?php endif; ?>
<?php
get_footer();
