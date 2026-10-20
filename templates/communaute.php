<?php
/**
 * Template Name: Communauté
 *
 * Une communauté ou un groupe de prière. À placer sous la page « Communautés »
 * d'une paroisse : la page hérite alors du bandeau et des onglets de la paroisse.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$community = get_post();
$parish    = vsg_context_parish();
if ( $parish ) {
	vsg_parish_band( $parish, $community );
}
$vue = vsg_communaute_page_data( $community, $parish );
?>
<section class="band">
	<div class="wrap">
		<div class="split">
			<div class="split__text">
				<span class="eyebrow">Communauté</span>
				<h1><?php echo esc_html( get_the_title() ); ?></h1>
				<?php if ( has_excerpt() ) : ?><p class="lead"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
				<?php if ( $parish ) : ?><p>Présente à la <a href="<?php echo esc_url( get_permalink( $parish ) ); ?>">paroisse <?php echo esc_html( get_the_title( $parish ) ); ?></a>.</p>
					<?php else : ?><p>Pour les deux paroisses, Notre-Dame de Lourdes et Sainte-Geneviève.</p><?php endif; ?>
				<?php if ( $vue['site'] || $vue['links'] ) : ?>
					<div class="actions">
						<?php if ( $vue['site'] ) : ?><a class="btn" href="<?php echo esc_url( $vue['site'] ); ?>" target="_blank" rel="noopener">Site de la communauté<span class="sr-only"> (nouvel onglet)</span></a><?php endif; ?>
						<?php foreach ( $vue['links'] as $label => $url ) : ?>
							<a class="btn btn--ghost" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $label ); ?><span class="sr-only"> (nouvel onglet)</span></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
			<div class="split__media"><?php echo vsg_thumb( $community, 'vsg-hero', 'Photo de la communauté' ); ?></div>
		</div>
	</div>
</section>

<section class="band band--tight">
	<div class="wrap twocol">
		<div class="twocol__side">
			<div class="infocard infocard--soft">
				<h2>Rendez-vous réguliers</h2>
				<?php if ( $vue['prog'] ) : ?>
					<ul class="plainlist">
						<?php foreach ( $vue['prog'] as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?>
					</ul>
				<?php elseif ( $vue['gtype'] ) : ?>
					<p>Toutes les dates sont dans le <a href="#programme">programme</a> ci-dessous.</p>
				<?php else : ?>
					<p>Les dates sont annoncées dans l'<a href="<?php echo esc_url( vsg_page_url( 'agenda' ) ); ?>">agenda</a> et la feuille paroissiale.</p>
				<?php endif; ?>
				<?php if ( $vue['lieu'] ) : ?><p><strong>Lieu habituel</strong><br><?php echo esc_html( $vue['lieu'] ); ?></p><?php endif; ?>
			</div>
			<?php if ( $vue['resp'] || $vue['tel'] || $vue['mail'] ) : ?>
				<div class="infocard">
					<h2>Contact</h2>
					<?php if ( $vue['resp'] ) : ?><p><?php echo esc_html( $vue['resp'] ); ?></p><?php endif; ?>
					<?php if ( $vue['tel'] ) : ?><p><?php echo esc_html( $vue['tel'] ); ?></p><?php endif; ?>
					<?php if ( $vue['mail'] ) : ?><p><?php echo vsg_mailto( $vue['mail'] ); ?></p><?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<div class="twocol__main prose">
			<?php the_content(); ?>
		</div>
	</div>
</section>

<?php if ( $vue['gtype'] || $vue['term'] ) : ?>
<section class="band band--soft programme" id="programme">
	<div class="wrap narrow">
		<?php vsg_section_head( 'Programme' ); ?>
		<?php vsg_communities_menu( $community->ID ); ?>
		<?php if ( $vue['months'] ) : ?>
			<p class="muted agenda__hint">Touchez un mois pour l'ouvrir ou le replier.</p>
			<?php vsg_months_list( $vue['months'] ); ?>
		<?php else : ?>
			<?php vsg_empty( 'Aucun rendez-vous annoncé pour le moment.' ); ?>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>
<?php if ( $vue['news'] && $vue['news']->have_posts() ) : ?>
<section class="band">
	<div class="wrap">
		<?php vsg_section_head( 'Actualités de la communauté' ); ?>
		<div class="grid grid--3">
			<?php foreach ( $vue['news']->posts as $n ) : ?>
				<?php echo vsg_news_card( $n, false, true ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>
<?php
get_footer();
