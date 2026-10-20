<?php
defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="band">
	<div class="wrap narrow">
		<h1>Page introuvable</h1>
		<p class="lead">Cette page n'existe pas ou a été déplacée.</p>
		<p class="actions"><a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Retour à l'accueil</a></p>
	</div>
</section>
<?php
get_footer();
