<?php
/**
 * Accueil : portail des paroisses.
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$intro = trim( get_the_content() );
?>
<section class="band band--soft">
	<div class="wrap hero">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( $intro ) : ?>
			<div class="lead prose"><?php the_content(); ?></div>
		<?php endif; ?>
	</div>
	<div class="wrap grid grid--2 parishcards">
		<?php foreach ( vsg_parishes() as $p ) : ?>
			<article class="parishcard" style="--accent:<?php echo esc_attr( vsg_parish_color( $p ) ); ?>">
				<div class="parishcard__media"><?php echo vsg_thumb( $p, 'vsg-hero', 'Photo de l\'église' ); // phpcs:ignore ?></div>
				<div class="parishcard__body">
					<span class="eyebrow">Paroisse</span>
					<h2><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></h2>
					<ul class="places">
						<li><strong>Église <?php echo esc_html( get_the_title( $p ) ); ?></strong><?php echo vsg_meta( $p->ID, 'eglise' ) ? ' · ' . esc_html( vsg_meta( $p->ID, 'eglise' ) ) : ''; ?> <?php echo vsg_itineraire( vsg_meta( $p->ID, 'eglise' ), 'l\'église ' . get_the_title( $p ) ); // phpcs:ignore ?></li>
						<?php foreach ( vsg_chapels( $p->ID ) as $c ) : ?>
							<li><a href="<?php echo esc_url( get_permalink( $c ) ); ?>"><?php echo esc_html( get_the_title( $c ) ); ?></a><?php echo vsg_meta( $c->ID, 'adresse' ) ? ' · ' . esc_html( vsg_meta( $c->ID, 'adresse' ) ) : ''; ?> <?php echo vsg_itineraire( vsg_meta( $c->ID, 'adresse' ), 'la ' . lcfirst( get_the_title( $c ) ) ); // phpcs:ignore ?></li>
						<?php endforeach; ?>
					</ul>
					<?php $sun = vsg_hours( $p->ID ); ?>
					<?php if ( ! empty( $sun['dimanche'] ) ) : ?><p class="parishcard__hours"><strong>Dimanche</strong> · <?php echo esc_html( implode( ', ', array_map( function ( $l ) { return preg_replace( '/^Messe (à )?/u', 'messe à ', $l ); }, $sun['dimanche']['lines'] ) ) ); ?></p><?php endif; ?>
					<div class="actions">
						<a class="btn" href="<?php echo esc_url( get_permalink( $p ) ); ?>">Accéder à la paroisse<span class="sr-only"> <?php echo esc_html( get_the_title( $p ) ); ?></span></a>
						<?php echo vsg_messes_button( $p->ID, 'btn btn--ghost' ); // phpcs:ignore ?>
					</div>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<?php
$home_id  = get_the_ID();
$cure_mot = trim( vsg_meta( $home_id, 'cure_mot' ) );
/* Texte modèle entre crochets (« [Mot du curé à rédiger…] ») : non affiché. */
if ( '' !== $cure_mot && '[' === $cure_mot[0] ) {
	$cure_mot = '';
}
?>
<?php if ( $cure_mot || vsg_meta( $home_id, 'cure_nom' ) ) : ?>
<section class="band band--tight">
	<div class="wrap">
		<div class="curebox">
			<div class="curebox__photo"><?php echo vsg_meta( $home_id, 'cure_photo' ) ? '<img src="' . esc_url( vsg_meta( $home_id, 'cure_photo' ) ) . '" alt="">' : '<div class="ph"><span>Photo</span></div>'; ?></div>
			<div class="curebox__text">
				<h2><?php echo $cure_mot ? 'Le mot du curé' : 'Votre curé'; ?></h2>
				<?php echo $cure_mot ? wpautop( esc_html( $cure_mot ) ) : ''; // phpcs:ignore ?>
				<?php if ( vsg_meta( $home_id, 'cure_nom' ) ) : ?><p class="curebox__sign"><?php echo esc_html( vsg_meta( $home_id, 'cure_nom' ) ); ?></p><?php endif; ?>
			</div>
		<?php
		$pretres = array_values( array_filter( array_map( 'trim', explode( "\n", vsg_meta( $home_id, 'pretres' ) ) ) ) );
		if ( $pretres ) :
			?>
			<div class="pretres">
				<h3 class="pretres__title">Vous accueillent aussi</h3>
				<div class="team team--inline">
					<?php
					foreach ( $pretres as $line ) {
						$parts = array_map( 'trim', explode( ';', $line ) );
						$src   = isset( $parts[2] ) ? $parts[2] : '';
						echo '<div class="team__card">' . ( $src ? '<img src="' . esc_url( $src ) . '" alt="" loading="lazy">' : '<div class="team__ph"></div>' ) . '<div class="team__txt"><strong>' . esc_html( $parts[0] ) . '</strong>' . ( isset( $parts[1] ) && $parts[1] ? '<span>' . esc_html( $parts[1] ) . '</span>' : '' ) . '</div></div>';
					}
					?>
				</div>
			</div>
		<?php endif; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="band">
	<div class="wrap">
		<?php vsg_section_head( 'Que cherchez-vous ?' ); ?>
		<div class="grid grid--3 tiles tiles--menu">
			<?php
			$all = vsg_communities_page();
			echo vsg_tile( 'Horaires', vsg_hours_url() ?: 'https://messes.info/', 'Messes, confessions et adoration dans nos quatre lieux de culte' ); // phpcs:ignore
			echo vsg_tile( 'Agenda', vsg_page_url( 'agenda' ), 'Les prochains rendez-vous' ); // phpcs:ignore
			echo vsg_tile( 'Vivre en Église', vsg_page_url( 'vivre-en-eglise' ), 'Baptême, mariage, obsèques, certificat, salle…' ); // phpcs:ignore
			echo vsg_tile( 'Groupes et communautés', $all ? get_permalink( $all ) : vsg_page_url( 'communautes' ), 'Qui ils sont, quand les rejoindre' ); // phpcs:ignore
			echo vsg_tile( 'Textes du jour et vie des saints', vsg_page_url( 'prier' ), 'Lectures de la messe, vies de saints et prières' ); // phpcs:ignore
			echo vsg_tile( 'Nous contacter', vsg_page_url( 'contact' ), 'Accueils paroissiaux, rencontrer un prêtre' ); // phpcs:ignore
			?>
		</div>
	</div>
</section>

<section class="band band--soft">
	<div class="wrap">
		<?php vsg_section_head( 'Prochains rendez-vous', vsg_page_url( 'agenda' ), 'Voir l\'agenda complet' ); ?>
		<?php
		$q = vsg_events_query( array( 'limit' => 30, 'kind' => 'parish' ) );
		if ( $q->have_posts() ) {
			echo '<div class="grid grid--3">';
			foreach ( vsg_events_unique( $q->posts ) as $ev ) {
				echo vsg_event( $ev, 'card' ); // phpcs:ignore
			}
			echo '</div>';
		} else {
			vsg_empty( 'Aucun rendez-vous à venir pour le moment.' );
		}
		?>
	</div>
</section>

<section class="band" id="actualites">
	<div class="wrap">
		<?php vsg_section_head( 'Actualités' ); ?>
		<?php
		$q = vsg_news_query( array( 'limit' => 3 ) );
		if ( $q->have_posts() ) {
			echo '<div class="grid grid--3">';
			foreach ( $q->posts as $n ) {
				echo vsg_news_card( $n ); // phpcs:ignore
			}
			echo '</div>';
		} else {
			vsg_empty( 'Aucune actualité pour le moment.' );
		}
		?>
	</div>
</section>


<section class="band band--soft" id="feuilles">
	<div class="wrap">
		<?php vsg_section_head( 'Feuilles paroissiales de la semaine' ); ?>
		<div class="grid grid--2">
			<?php
			foreach ( vsg_parishes() as $p ) :
				$t = vsg_page_term( $p->ID );
				$f = $t ? vsg_feuilles_query( array( $t->term_id ), 1 ) : null;
				$doc = ( $f && $f->have_posts() ) ? $f->posts[0] : null;
				$pdf = $doc ? vsg_feuille_pdf( $doc ) : '';
				?>
				<div class="filecard">
					<div>
						<strong><?php echo esc_html( get_the_title( $p ) ); ?></strong>
						<span><?php echo $doc ? esc_html( vsg_feuille_label( $doc ) ) . ' · PDF' : 'Pas encore de feuille'; ?></span>
					</div>
					<?php if ( $pdf ) : ?>
						<a class="btn" href="<?php echo esc_url( $pdf ); ?>">Télécharger<span class="sr-only"> la feuille de <?php echo esc_html( get_the_title( $p ) ); ?></span></a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="band band--tight">
	<div class="wrap">
		<div class="cta cta--slim">
			<div>
				<h2>Soutenir vos paroisses</h2>
				<p>Le denier et la quête font vivre nos communautés.</p>
			</div>
			<a class="btn btn--light" href="<?php echo esc_url( vsg_page_url( 'soutenir' ) ); ?>">Comment donner</a>
		</div>
	</div>
</section>
<?php
get_footer();
