<?php
/**
 * Un rendez-vous de l'agenda.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$parish = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, get_post() );
}
$date  = vsg_meta( get_the_ID(), 'date' );
$heure = vsg_time_label( vsg_meta( get_the_ID(), 'heure' ) );
$lieu  = vsg_meta( get_the_ID(), 'lieu' );
$parts = vsg_date_parts( $date );
?>
<article class="band">
	<div class="wrap narrow">
		<?php $group = vsg_event_group( get_post() ); ?>
		<?php if ( $group ) : ?>
			<p class="postmeta" style="--accent:<?php echo esc_attr( $group['color'] ); ?>"><span class="chip chip--group"><?php echo esc_html( $group['label'] ); ?></span></p>
		<?php else : ?>
			<p class="postmeta">
			<?php if ( vsg_place_label( get_post() ) ) : ?><span class="chip"><?php echo esc_html( vsg_place_label( get_post() ) ); ?></span><?php endif; ?></p>
		<?php endif; ?>
		<h1><?php the_title(); ?></h1>
		<div class="eventfacts">
			<?php echo vsg_date_badge( $date ); ?>
			<div>
				<p class="eventfacts__when"><?php echo esc_html( ucfirst( vsg_date_long( $date ) ) . ( $parts ? ' ' . $parts['year'] : '' ) . ( $heure ? ' à ' . $heure : '' ) ); ?></p>
				<p class="muted"><?php echo esc_html( implode( ' · ', $group ? vsg_event_where( get_post() ) : array_filter( array( vsg_place_label( get_post() ), $lieu ) ) ) ); ?></p>
				<?php if ( $group ) : ?>
					<p class="muted">Proposé par : <?php echo $group['url'] ? '<a href="' . esc_url( $group['url'] ) . '">' . esc_html( $group['label'] ) . '</a>' : esc_html( $group['label'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="figure figure--poster"><?php the_post_thumbnail( 'large', array( 'alt' => vsg_poster_alt( get_post() ) ) ); ?></div>
		<?php endif; ?>
		<div class="prose"><?php the_content(); ?></div>
		<?php if ( $group && $group['url'] ) : ?>
			<p><a href="<?php echo esc_url( $group['url'] . '#programme' ); ?>">Retour au programme : <?php echo esc_html( $group['label'] ); ?></a></p>
		<?php else : ?>
			<p><a href="<?php echo esc_url( vsg_page_url( 'agenda' ) ); ?>">Retour à l'agenda</a></p>
		<?php endif; ?>
	</div>
</article>
<?php
get_footer();
