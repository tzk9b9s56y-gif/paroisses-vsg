<?php
/**
 * Template Name: Choix de la paroisse (sacrements)
 *
 * Page commune : on choisit d'abord sa paroisse, puis le sacrement.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
?>
<section class="band band--soft band--tight">
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( trim( get_the_content() ) ) : ?><div class="prose lead"><?php the_content(); ?></div><?php endif; ?>
	</div>
</section>
<section class="band">
	<div class="wrap">
		<h2>Dans quelle paroisse ?</h2>
		<div class="grid grid--2">
			<?php foreach ( vsg_parishes() as $p ) : ?>
				<?php $sac = vsg_parish_child_by_template( $p->ID, 'templates/sacrements.php' ); ?>
				<?php
				if ( ! $sac ) {
					continue; }
				?>
				<div class="infocard" style="--accent:<?php echo esc_attr( vsg_parish_color( $p ) ); ?>;border-top:4px solid var(--accent)">
					<span class="eyebrow">Paroisse</span>
					<h3 style="color:var(--accent);font-size:24px"><?php echo esc_html( get_the_title( $p ) ); ?></h3>
					<?php $chap = vsg_chapels( $p->ID ); ?>
					<?php if ( $chap ) : ?><p class="muted">Avec <?php echo esc_html( implode( ' et ', array_map( 'get_the_title', $chap ) ) ); ?></p><?php endif; ?>
					<p class="actions"><a class="btn" style="background:var(--accent);border-color:var(--accent)" href="<?php echo esc_url( get_permalink( $sac ) ); ?>">Choisir cette paroisse</a></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php
get_footer();
