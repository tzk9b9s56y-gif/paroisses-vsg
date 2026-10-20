<?php
/**
 * Une feuille paroissiale : un encadré simple pour télécharger ou ouvrir le PDF.
 * Le bloc « Fichier » du contenu n'est pas affiché (son aperçu prend tout l'écran) ;
 * le reste du contenu (une phrase de sommaire, par exemple) l'est.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$parish = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, get_post() );
}
$pdf   = vsg_feuille_pdf( get_post() );
$rest  = vsg_feuille_body( get_post() );
$index = $parish ? vsg_parish_child_by_template( $parish->ID, 'templates/feuille.php' ) : null;
?>
<article class="band">
	<div class="wrap narrow">
		<div class="featurefile">
			<span class="eyebrow">Feuille paroissiale<?php echo $parish ? ' · ' . esc_html( get_the_title( $parish ) ) : ''; ?></span>
			<h1 class="featurefile__title"><?php echo esc_html( vsg_feuille_label( get_post() ) ); ?></h1>
			<?php if ( trim( wp_strip_all_tags( $rest ) ) ) : ?>
				<div class="prose"><?php echo vsg_feuille_body( get_post() ); ?></div>
			<?php endif; ?>
			<?php if ( $pdf ) : ?>
				<div class="actions">
					<a class="btn" href="<?php echo esc_url( $pdf ); ?>" download>Télécharger le PDF</a>
					<a class="btn btn--ghost" href="<?php echo esc_url( $pdf ); ?>" target="_blank" rel="noopener">Ouvrir dans le navigateur<span class="sr-only"> (nouvel onglet)</span></a>
				</div>
			<?php else : ?>
				<p class="muted">Le fichier PDF n'est pas encore disponible.</p>
			<?php endif; ?>
		</div>
		<?php if ( $index ) : ?>
			<p class="backlink"><a href="<?php echo esc_url( get_permalink( $index ) ); ?>">Toutes les feuilles de la paroisse</a></p>
		<?php endif; ?>
	</div>
</article>
<?php
get_footer();
