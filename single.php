<?php
/**
 * Une actualité.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$parish = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, get_post() );
}
$news = $parish ? vsg_parish_child_by_template( $parish->ID, 'templates/actualites.php' ) : null;
?>
<article class="band">
	<div class="wrap narrow">
		<p class="postmeta">
			<?php if ( vsg_place_label( get_post() ) ) : ?><span class="chip"><?php echo esc_html( vsg_place_label( get_post() ) ); ?></span><?php endif; ?>
			<span>Publié le <?php echo esc_html( vsg_post_date( get_post() ) ); ?></span>
		</p>
		<h1><?php the_title(); ?></h1>
		<?php if ( has_excerpt() ) : ?><p class="lead"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
		<?php if ( has_post_thumbnail() ) : ?>
			<?php $wide = vsg_thumb_is_wide( get_post() ); ?>
			<div class="figure"><?php the_post_thumbnail( $wide ? 'large' : 'vsg-hero', array( 'alt' => '' ) ); ?></div>
		<?php endif; ?>
		<div class="prose"><?php the_content(); ?></div>
	</div>
</article>
<?php
$others = vsg_news_query(
	array(
		'terms'   => $parish ? vsg_parish_term_ids( $parish->ID ) : array(),
		'limit'   => 3,
		'exclude' => array( get_the_ID() ),
	)
);
if ( $others->have_posts() ) :
	?>
	<section class="band band--soft">
		<div class="wrap">
			<?php vsg_section_head( $parish ? 'Autres actualités de la paroisse' : 'Autres actualités' ); ?>
			<div class="grid grid--3">
				<?php
				foreach ( $others->posts as $n ) {
					echo vsg_news_card( $n, true ); // phpcs:ignore
				}
				?>
			</div>
		</div>
	</section>
<?php endif; ?>
<?php
get_footer();
