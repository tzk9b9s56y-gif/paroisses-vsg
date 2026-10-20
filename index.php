<?php
/**
 * Liste par défaut (archives, recherche).
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="band">
	<div class="wrap">
		<h1><?php echo esc_html( is_search() ? 'Résultats de recherche' : ( is_archive() ? wp_strip_all_tags( get_the_archive_title() ) : 'Actualités' ) ); ?></h1>
		<?php if ( have_posts() ) : ?>
			<div class="grid grid--3">
				<?php
				while ( have_posts() ) {
					the_post();
					echo vsg_news_card( get_post(), true, true );
				}
				?>
			</div>
			<?php
			the_posts_pagination(
				array(
					'prev_text' => 'Précédent',
					'next_text' => 'Suivant',
				)
			);
			?>
		<?php else : ?>
			<?php vsg_empty( 'Aucun contenu pour le moment.' ); ?>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
