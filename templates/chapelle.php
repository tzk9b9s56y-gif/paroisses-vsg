<?php
/**
 * Template Name: Chapelle
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$chapel = get_post();
$parish = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, $chapel );
}
$place_term = vsg_page_term( $chapel->ID );
$addr       = vsg_meta( $chapel->ID, 'adresse' );
?>
<section class="band">
	<div class="wrap">
		<div class="split">
			<div class="split__text">
				<span class="eyebrow">Chapelle</span>
				<h1><?php echo esc_html( get_the_title() ); ?></h1>
				<?php if ( $parish ) : ?><p class="lead">Rattachée à la <a href="<?php echo esc_url( get_permalink( $parish ) ); ?>">paroisse <?php echo esc_html( get_the_title( $parish ) ); ?></a>.</p><?php endif; ?>
				<div class="actions">
					<?php echo vsg_messes_button( $chapel->ID ); ?>
					<?php if ( $addr ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( vsg_maps_url( $addr ) ); ?>" target="_blank" rel="noopener">Itinéraire<span class="sr-only"> (nouvel onglet)</span></a><?php endif; ?>
				</div>
			</div>
			<div class="split__media"><?php echo vsg_thumb( $chapel, 'vsg-hero', 'Photo de la chapelle' ); ?></div>
		</div>
	</div>
</section>

<section class="band band--tight" id="histoire">
	<div class="wrap">
		<div class="infocard prose">
			<?php the_content(); ?>
		</div>
	</div>
</section>

<section class="band band--tight">
	<div class="wrap grid grid--2">
		<div class="infocard">
			<h2>Infos pratiques</h2>
			<p><strong>Adresse</strong><br><?php echo esc_html( $addr ); ?></p>
			<?php if ( vsg_meta( $chapel->ID, 'acces' ) ) : ?><p><strong>Accès</strong><br><?php echo vsg_nl2br( vsg_meta( $chapel->ID, 'acces' ) ); ?></p><?php endif; ?>
			<?php if ( vsg_meta( $chapel->ID, 'permanences' ) ) : ?><p><strong>Accueil</strong><br><?php echo vsg_nl2br( vsg_meta( $chapel->ID, 'permanences' ) ); ?></p><?php endif; ?>
			<?php if ( vsg_meta( $chapel->ID, 'telephone' ) ) : ?><p><strong>Téléphone de la chapelle</strong><br><?php echo esc_html( vsg_meta( $chapel->ID, 'telephone' ) ); ?></p><?php endif; ?>
			<?php if ( $parish ) : ?><p><strong>Secrétariat</strong><br>Paroisse <?php echo esc_html( get_the_title( $parish ) ); ?><?php echo vsg_meta( $parish->ID, 'telephone' ) ? ' · ' . esc_html( vsg_meta( $parish->ID, 'telephone' ) ) : ''; ?></p><?php endif; ?>
		</div>
		<div class="infocard infocard--soft">
			<h2>Messes à la chapelle</h2>
			<?php echo vsg_hours_block( $chapel->ID, '', true ); ?>
		</div>
	</div>
</section>
<section class="band band--soft">
	<div class="wrap">
		<?php
		vsg_section_head( 'Prochains rendez-vous à la chapelle' );
		$q = $place_term ? vsg_events_query(
			array(
				'terms' => array( $place_term->term_id ),
				'limit' => 4,
				'kind'  => 'parish',
			)
		) : null;
		if ( $q && $q->have_posts() ) {
			echo '<div class="grid grid--2">';
			foreach ( $q->posts as $ev ) {
				echo vsg_event( $ev, 'card', false );
			}
			echo '</div>';
		} else {
			vsg_empty( 'Aucun rendez-vous prévu à la chapelle pour le moment.' );
		}
		?>
	</div>
</section>
<?php
get_footer();
