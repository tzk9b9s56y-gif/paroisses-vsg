<?php
/**
 * Template Name: Prier – vie de saints (menu)
 *
 * Liste les pages enfants (une par saint) sous forme de cartes.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$saints = get_pages(
	array(
		'parent'      => get_the_ID(),
		'sort_column' => 'menu_order',
	)
);
?>
<section class="band band--soft band--tight">
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
	</div>
</section>
<section class="band band--tight">
	<div class="wrap">
		<div class="aelf">
			<div>
				<p class="aelf__kicker">Chaque jour</p>
				<h2>Les textes du jour</h2>
				<p>Les lectures de la messe et la liturgie des heures, sur le site officiel de l'Association épiscopale liturgique pour les pays francophones (AELF).</p>
			</div>
			<p class="aelf__actions">
				<a class="btn" href="<?php echo esc_url( vsg_aelf_url() ); ?>" target="_blank" rel="noopener">Lectures de la messe du jour<span class="sr-only"> (nouvel onglet)</span></a>
				<a class="btn btn--ghost" href="<?php echo esc_url( vsg_aelf_url( 'heures' ) ); ?>" target="_blank" rel="noopener">Liturgie des heures<span class="sr-only"> (nouvel onglet)</span></a>
			</p>
		</div>
	</div>
</section>
<section class="band">
	<div class="wrap">
		<?php if ( $saints ) : ?>
			<div class="grid grid--3">
				<?php foreach ( $saints as $saint ) : ?>
					<a class="card" href="<?php echo esc_url( get_permalink( $saint ) ); ?>">
						<span class="card__media card__media--top"><?php echo vsg_thumb( $saint, 'large', 'Image' ); ?></span>
						<span class="card__body">
							<span class="card__title"><?php echo esc_html( get_the_title( $saint ) ); ?></span>
							<?php if ( $saint->post_excerpt ) : ?><span class="card__text"><?php echo esc_html( $saint->post_excerpt ); ?></span><?php endif; ?>
							<span class="card__date">Sa vie, la vidéo, une prière</span>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php vsg_empty( 'Aucune fiche pour le moment. Ajoutez des pages enfants à cette page : une par saint.' ); ?>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
