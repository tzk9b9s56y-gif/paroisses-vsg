<?php
/**
 * Template Name: Paroisse – informations pratiques
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
		<?php if ( $parish ) : ?>
			<div class="grid grid--2">
				<div class="infocard infocard--soft">
					<h2>Horaires</h2>
					<?php echo vsg_hours_block( $parish->ID, 'Église ' . get_the_title( $parish ), true ); // phpcs:ignore ?>
					<?php foreach ( vsg_chapels( $parish->ID ) as $c ) : ?>
						<?php echo vsg_hours_block( $c->ID, get_the_title( $c ), true ); // phpcs:ignore ?>
					<?php endforeach; ?>
				</div>
				<div class="infocard infocard--soft">
					<h2>Accueil et secrétariat</h2>
					<p><?php echo esc_html( vsg_meta( $parish->ID, 'adresse' ) ); ?> <?php echo vsg_itineraire( vsg_meta( $parish->ID, 'adresse' ), 'l\'accueil' ); // phpcs:ignore ?></p>
					<p><?php echo vsg_nl2br( vsg_meta( $parish->ID, 'permanences' ) ); // phpcs:ignore ?></p>
					<p><?php echo esc_html( vsg_meta( $parish->ID, 'telephone' ) ); ?><?php if ( vsg_meta( $parish->ID, 'email' ) ) : ?> · <?php echo vsg_mailto( vsg_meta( $parish->ID, 'email' ) ); // phpcs:ignore ?><?php endif; ?></p>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php if ( $parish ) : ?>
<section class="band band--tight">
	<div class="wrap narrow"><?php echo vsg_priest_block( $parish->ID ); // phpcs:ignore ?></div>
</section>
<?php endif; ?>

<?php if ( trim( get_the_content() ) ) : ?>
<section class="band band--tight">
	<div class="wrap narrow prose"><?php the_content(); ?></div>
</section>
<?php endif; ?>

<?php if ( $parish ) : ?>
<section class="band band--tight">
	<div class="wrap">
		<h2>Plans d'accès</h2>
		<div class="grid grid--3">
			<?php
			$lieux = array( array( 'Église ' . get_the_title( $parish ), vsg_meta( $parish->ID, 'eglise' ), '' ) );
			foreach ( vsg_chapels( $parish->ID ) as $c ) {
				$lieux[] = array( get_the_title( $c ), vsg_meta( $c->ID, 'adresse' ), vsg_meta( $c->ID, 'acces' ) );
			}
			foreach ( $lieux as $l ) :
				?>
				<div class="infocard">
					<h3><?php echo esc_html( $l[0] ); ?></h3>
					<p><?php echo esc_html( $l[1] ); ?></p>
					<?php if ( $l[2] ) : ?><p class="muted"><?php echo esc_html( $l[2] ); ?></p><?php endif; ?>
					<p><?php echo vsg_itineraire( $l[1], $l[0], 'btn btn--ghost btn--sm' ); // phpcs:ignore ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="band band--soft">
	<div class="wrap narrow">
		<h2>Écrire à la paroisse</h2>
		<p class="muted">Pour une demande de sacrement, merci de passer par l'accueil ou par téléphone.</p>
		<?php echo vsg_contact_form( $parish->ID ); // phpcs:ignore ?>
	</div>
</section>
<?php endif; ?>
<?php
get_footer();
