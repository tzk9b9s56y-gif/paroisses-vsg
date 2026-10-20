<?php
/**
 * Template Name: Paroisse – galerie
 *
 * Les albums sont les actualités de la paroisse qui contiennent une galerie
 * d'images ; les vidéos, celles qui contiennent une vidéo intégrée.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$parish = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, get_post() );
}
list( $albums, $videos ) = vsg_gallery_items( $parish );
?>
<section class="band">
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
		<h2>Albums photos</h2>
		<?php if ( $albums ) : ?>
			<div class="grid grid--3">
				<?php
				foreach ( $albums as $a ) :
					$blocks = parse_blocks( $a->post_content );
					$count  = 0;
					foreach ( $blocks as $b ) {
						if ( 'core/gallery' === $b['blockName'] ) {
							$count += count( $b['innerBlocks'] );
						}
					}
					?>
					<a class="album" href="<?php echo esc_url( get_permalink( $a ) ); ?>">
						<span class="album__media"><?php echo vsg_thumb( $a ); // phpcs:ignore ?></span>
						<span class="album__title"><?php echo esc_html( get_the_title( $a ) ); ?></span>
						<span class="album__meta"><?php echo esc_html( vsg_post_date( $a ) . ( $count ? ' · ' . $count . ' photos' : '' ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php vsg_empty( 'Aucun album pour le moment.' ); ?>
		<?php endif; ?>
	</div>
</section>
<section class="band band--soft">
	<div class="wrap">
		<h2>Vidéos</h2>
		<?php if ( $videos ) : ?>
			<div class="grid grid--3">
				<?php foreach ( $videos as $v ) : ?>
					<?php echo vsg_news_card( $v, false, true ); // phpcs:ignore ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php vsg_empty( 'Les vidéos publiées sur la chaîne YouTube de la paroisse apparaîtront ici.' ); ?>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
