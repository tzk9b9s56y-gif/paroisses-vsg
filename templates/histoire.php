<?php
/**
 * Template Name: Paroisse – histoire
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$parish = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, get_post() );
}
?>
<section class="band band--tight">
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
	</div>
</section>

<?php if ( trim( get_the_content() ) ) : ?>
<section class="band band--tight">
	<div class="wrap narrow prose"><?php the_content(); ?></div>
</section>
<?php endif; ?>

<?php if ( $parish ) : ?>
<section class="band band--tight">
	<div class="wrap">
		<?php vsg_section_head( 'Nos lieux de culte' ); ?>
		<div class="grid grid--3">
			<div class="placecard">
				<div class="placecard__media"><?php echo vsg_thumb( $parish, 'vsg-card', 'Photo' ); // phpcs:ignore ?></div>
				<div class="placecard__body">
					<h3>Église <?php echo esc_html( get_the_title( $parish ) ); ?></h3>
					<p><?php echo esc_html( vsg_meta( $parish->ID, 'eglise' ) ); ?></p>
					<?php if ( vsg_meta( $parish->ID, 'eglise' ) ) : ?><a href="<?php echo esc_url( vsg_maps_url( vsg_meta( $parish->ID, 'eglise' ) ) ); ?>" target="_blank" rel="noopener">Itinéraire<span class="sr-only"> (nouvel onglet)</span></a><?php endif; ?>
				</div>
			</div>
			<?php foreach ( vsg_chapels( $parish->ID ) as $c ) : ?>
				<a class="placecard" href="<?php echo esc_url( get_permalink( $c ) ); ?>">
					<div class="placecard__media"><?php echo vsg_thumb( $c, 'vsg-card', 'Photo' ); // phpcs:ignore ?></div>
					<div class="placecard__body">
						<h3><?php echo esc_html( get_the_title( $c ) ); ?></h3>
						<p><?php echo esc_html( vsg_meta( $c->ID, 'adresse' ) ); ?></p>
						<span class="link">Voir la chapelle</span>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>
<?php
get_footer();
