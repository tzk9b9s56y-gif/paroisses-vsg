<?php defined( 'ABSPATH' ) || exit; ?>
</main>
<?php if ( ! is_front_page() ) : ?>
<div class="wrap backhome"><a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">← Retour à l'accueil</a></div>
<?php endif; ?>
<footer class="sitefooter">
	<div class="wrap diocese">
		<a class="diocese__link diocese__link--self brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<img class="brand__logo brand__logo--mark" src="<?php echo esc_url( VSG_URI . '/assets/img/logo-mark.svg' ); ?>" alt="" width="64" height="56">
			<span class="brand__text">
				<span class="brand__kicker">Ensemble paroissial</span>
				<span class="brand__name"><?php echo esc_html( str_replace( '-', "\u{2011}", preg_replace( '/^Ensemble paroissial\s+/u', '', get_bloginfo( 'name' ) ) ) ); ?></span>
				<span class="brand__sub">Villeneuve-Saint-Georges · Villeneuve-Triage</span>
			</span>
		</a>
		<a class="diocese__link diocese__link--dio" href="https://catholiques-val-de-marne.cef.fr/" target="_blank" rel="noopener">
			<img class="diocese__logo" src="<?php echo esc_url( VSG_URI . '/assets/img/logo-diocese-creteil.png' ); ?>" alt="" width="120" height="99">
			<span>Fait partie du <strong>diocèse de Créteil</strong><span class="sr-only"> (site du diocèse, nouvel onglet)</span></span>
		</a>
	</div>
	<div class="wrap sitefooter__in">
		<?php foreach ( vsg_parishes() as $p ) : ?>
			<div class="sitefooter__col">
				<h2>Paroisse <?php echo esc_html( get_the_title( $p ) ); ?></h2>
				<?php if ( vsg_meta( $p->ID, 'adresse' ) ) : ?><p><?php echo esc_html( vsg_meta( $p->ID, 'adresse' ) ); ?></p><?php endif; ?>
				<?php if ( vsg_meta( $p->ID, 'telephone' ) ) : ?><p><?php echo esc_html( vsg_meta( $p->ID, 'telephone' ) ); ?></p><?php endif; ?>
				<?php if ( vsg_meta( $p->ID, 'email' ) ) : ?><p><?php echo vsg_mailto( vsg_meta( $p->ID, 'email' ) ); // phpcs:ignore ?></p><?php endif; ?>
				<?php
				$chapels = vsg_chapels( $p->ID );
				if ( $chapels ) :
					?>
					<p><?php echo esc_html( implode( ' et ', array_map( 'get_the_title', $chapels ) ) ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		<div class="sitefooter__col">
			<h2>Liens</h2>
			<?php if ( vsg_hours_url() ) : ?><p><a href="<?php echo esc_url( vsg_hours_url() ); ?>">Horaires</a></p><?php endif; ?>
			<?php $vsg_all = vsg_communities_page(); ?>
			<?php if ( $vsg_all ) : ?><p><a href="<?php echo esc_url( get_permalink( $vsg_all ) ); ?>">Groupes et communautés</a></p><?php endif; ?>
			<p><a href="<?php echo esc_url( vsg_page_url( 'contact' ) ); ?>">Contact</a></p>
			<p><a href="<?php echo esc_url( vsg_page_url( 'mentions-legales' ) ); ?>">Mentions légales et données personnelles</a></p>
			<?php if ( vsg_liens_html() ) : ?>
				<h2 class="sitefooter__sub">Liens utiles</h2>
				<?php echo vsg_liens_html(); // phpcs:ignore ?>
			<?php endif; ?>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
