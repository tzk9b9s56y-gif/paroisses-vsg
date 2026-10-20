<?php
/**
 * Template Name: Contact
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
?>
<section class="band band--tight">
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
		<div class="grid grid--2">
			<?php foreach ( vsg_parishes() as $p ) : ?>
				<div class="infocard infocard--soft">
					<h2>Paroisse <?php echo esc_html( get_the_title( $p ) ); ?></h2>
					<?php $ch = vsg_chapels( $p->ID ); ?>
					<?php if ( $ch ) : ?><p class="muted">Avec les chapelles <?php echo esc_html( implode( ' et ', array_map( function ( $c ) { return trim( preg_replace( '/^Chapelle\s+/u', '', get_the_title( $c ) ) ); }, $ch ) ) ); ?></p><?php endif; ?>
					<p><?php echo esc_html( vsg_meta( $p->ID, 'adresse' ) ); ?> <?php echo vsg_itineraire( vsg_meta( $p->ID, 'adresse' ), 'l\'accueil' ); // phpcs:ignore ?></p>
					<?php foreach ( $ch as $c ) : ?><p class="muted"><?php echo esc_html( get_the_title( $c ) ); ?> · <?php echo esc_html( vsg_meta( $c->ID, 'adresse' ) ); ?> <?php echo vsg_itineraire( vsg_meta( $c->ID, 'adresse' ), 'la ' . lcfirst( get_the_title( $c ) ) ); // phpcs:ignore ?></p><?php endforeach; ?>
					<p><?php echo vsg_nl2br( vsg_meta( $p->ID, 'permanences' ) ); // phpcs:ignore ?></p>
					<p><?php echo esc_html( vsg_meta( $p->ID, 'telephone' ) ); ?><?php if ( vsg_meta( $p->ID, 'email' ) ) : ?> · <?php echo vsg_mailto( vsg_meta( $p->ID, 'email' ) ); // phpcs:ignore ?><?php endif; ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<section class="band band--tight">
	<div class="wrap narrow">
		<h2>Nous écrire</h2>
		<p class="muted">Pour une demande de sacrement, merci de passer par l'accueil ou par téléphone.</p>
		<?php echo vsg_contact_form( null ); // phpcs:ignore ?>
	</div>
</section>
<?php
get_footer();
