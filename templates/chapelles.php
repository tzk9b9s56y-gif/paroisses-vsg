<?php
/**
 * Template Name: Paroisse – chapelles
 *
 * Les chapelles d'une paroisse : une carte par chapelle, vers sa page (horaires, infos, histoire).
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$parish = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, get_post() );
}
$chapels = $parish ? vsg_chapels( $parish->ID ) : array();
?>
<section class="band">
	<div class="wrap">
		<h1><?php the_title(); ?></h1>
		<?php if ( trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
		<?php if ( $chapels ) : ?>
			<div class="grid grid--2 chapelcards">
				<?php foreach ( $chapels as $c ) : ?>
					<?php $addr = vsg_meta( $c->ID, 'adresse' ); $h = vsg_hours( $c->ID ); ?>
					<article class="parishcard">
						<div class="parishcard__media"><a href="<?php echo esc_url( get_permalink( $c ) ); ?>" tabindex="-1" aria-hidden="true"><?php echo vsg_thumb( $c, 'vsg-hero', 'Photo de la chapelle' ); // phpcs:ignore ?></a></div>
						<div class="parishcard__body">
							<span class="eyebrow">Chapelle</span>
							<h2><a href="<?php echo esc_url( get_permalink( $c ) ); ?>"><?php echo esc_html( get_the_title( $c ) ); ?></a></h2>
							<?php if ( $addr ) : ?><p class="muted"><?php echo esc_html( $addr ); ?> <?php echo vsg_itineraire( $addr, 'la ' . lcfirst( get_the_title( $c ) ) ); // phpcs:ignore ?></p><?php endif; ?>
							<ul class="places">
								<?php foreach ( array( 'semaine', 'samedi', 'dimanche' ) as $k ) : ?>
									<?php if ( ! empty( $h[ $k ] ) ) : ?><li><strong><?php echo esc_html( $h[ $k ]['label'] ); ?></strong> · <?php echo esc_html( implode( ' ; ', $h[ $k ]['lines'] ) ); ?></li><?php endif; ?>
								<?php endforeach; ?>
							</ul>
							<div class="actions">
								<a class="btn" href="<?php echo esc_url( get_permalink( $c ) ); ?>">Découvrir la chapelle<span class="sr-only"> <?php echo esc_html( get_the_title( $c ) ); ?></span></a>
								<a class="btn btn--ghost" href="<?php echo esc_url( get_permalink( $c ) ); ?>#histoire">Son histoire<span class="sr-only"> : <?php echo esc_html( get_the_title( $c ) ); ?></span></a>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<?php vsg_empty( 'Cette paroisse n\'a pas de chapelle.' ); ?>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
