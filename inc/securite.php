<?php
/**
 * Sécurité et discrétion, sans extension :
 * - limitation des tentatives de connexion (les formulaires : voir inc/formulaires.php) ;
 * - XML-RPC désactivé, comptes non énumérables, version de WordPress masquée ;
 * - en-têtes de sécurité HTTP ;
 * - pages de filtres et de confirmation exclues des moteurs de recherche ;
 * - vidéos YouTube intégrées en mode « sans cookie » ;
 * - éditeurs de fichiers de WordPress désactivés.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Pas de modification du code depuis l'administration (Apparence › Éditeur de fichiers de thème,
 * Extensions › Éditeur) : un compte administrateur volé ne peut pas s'en servir pour injecter du code.
 * WordPress lit ce réglage au moment de vérifier les droits, après le chargement du thème.
 * Les mises à jour du thème ne passent pas par là et continuent de fonctionner.
 */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- constante de WordPress, nom imposé.
}

/**
 * Empreinte de l'adresse IP du visiteur (l'adresse elle-même n'est jamais conservée).
 * En IPv6, un abonné dispose de tout un bloc /64 : c'est ce bloc qui compte, sinon
 * il suffirait de changer d'adresse à chaque essai.
 */
function vsg_ip_hash() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( false !== strpos( $ip, ':' ) ) {
		$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- inet_pton signale une adresse illisible par un avertissement ; on teste le résultat.
		if ( false !== $bin && 16 === strlen( $bin ) ) {
			$ip = bin2hex( substr( $bin, 0, 8 ) ) . '::/64';
		}
	}
	return substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 20 );
}

/** Clé d'un compteur par identifiant de connexion (sans conserver l'identifiant). */
function vsg_login_name_key( $username ) {
	return 'vsg_loginu_' . substr( hash_hmac( 'sha256', strtolower( trim( (string) $username ) ), wp_salt( 'nonce' ) ), 0, 20 );
}

/* ---------- Connexion : 5 échecs → 15 minutes de blocage ---------- */

const VSG_LOGIN_MAX   = 5;
const VSG_LOGIN_PAUSE = 15 * MINUTE_IN_SECONDS;

/* Par identifiant : 20 échecs en une heure, toutes adresses confondues → 1 heure de blocage. */
const VSG_LOGIN_USER_MAX = 20;

add_action(
	'wp_login_failed',
	function ( $username ) {
		$key = 'vsg_login_' . vsg_ip_hash();
		set_transient( $key, (int) get_transient( $key ) + 1, VSG_LOGIN_PAUSE );
		$ukey = vsg_login_name_key( $username );
		set_transient( $ukey, (int) get_transient( $ukey ) + 1, HOUR_IN_SECONDS );
	}
);

add_filter(
	'authenticate',
	function ( $user, $username ) {
		if ( '' === (string) $username ) {
			return $user;
		}
		if ( (int) get_transient( 'vsg_login_' . vsg_ip_hash() ) >= VSG_LOGIN_MAX ) {
			return new WP_Error( 'vsg_bloque', 'Trop de tentatives de connexion. Réessayez dans 15 minutes.' );
		}
		if ( (int) get_transient( vsg_login_name_key( $username ) ) >= VSG_LOGIN_USER_MAX ) {
			return new WP_Error( 'vsg_bloque', 'Trop de tentatives de connexion. Réessayez dans une heure.' );
		}
		return $user;
	},
	30,
	2
);

add_action(
	'wp_login',
	function ( $login ) {
		delete_transient( 'vsg_login_' . vsg_ip_hash() );
		delete_transient( vsg_login_name_key( $login ) );
	}
);

/* « Mot de passe oublié » : 3 demandes par heure et par connexion. */
add_action(
	'lostpassword_post',
	function ( $errors ) {
		$key = 'vsg_lostpw_' . vsg_ip_hash();
		$n   = (int) get_transient( $key );
		if ( $n >= 3 ) {
			$errors->add( 'vsg_bloque', 'Trop de demandes. Réessayez dans une heure.' );
			return;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	}
);

/* Message d'erreur identique que l'identifiant existe ou non. */
add_filter(
	'login_errors',
	function ( $error ) {
		return false !== strpos( (string) $error, 'Trop de tentatives' ) ? $error : '<strong>Erreur :</strong> identifiant ou mot de passe incorrect.';
	}
);

/* ---------- Discrétion ---------- */

add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter(
	'wp_headers',
	function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
add_filter( 'the_generator', '__return_empty_string' );

/* Pas d'archives par auteur : elles révèlent les identifiants de connexion. */
add_filter( 'author_rewrite_rules', '__return_empty_array' );
add_action(
	'template_redirect',
	function () {
		if ( is_author() || isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- simple lecture d'un paramètre pour refuser l'énumération des comptes.
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	},
	1
);

/* L'API ne liste pas les comptes aux visiteurs non connectés. */
add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}
);

/* Aperçus (oEmbed) et flux RSS : pas de nom de compte. */
add_filter(
	'oembed_response_data',
	function ( $data ) {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	}
);
add_filter(
	'the_author',
	function ( $name ) {
		return is_feed() ? wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) : $name;
	}
);

/* Plan du site : ni comptes, ni catégories techniques. */
add_filter(
	'wp_sitemaps_add_provider',
	function ( $provider, $name ) {
		return in_array( $name, array( 'users', 'taxonomies' ), true ) ? false : $provider;
	},
	10,
	2
);
/* Plan du site : seulement les rendez-vous à venir. */
add_filter(
	'wp_sitemaps_posts_query_args',
	function ( $args, $post_type ) {
		if ( 'evenement' === $post_type ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_vsg_date',
					'value'   => vsg_today(),
					'compare' => '>=',
					'type'    => 'DATE',
				),
			); // phpcs:ignore WordPress.DB.SlowDBQuery -- au plus une poignée de rendez-vous privés, requête rare.
		}
		return $args;
	},
	10,
	2
);

/* ---------- En-têtes de sécurité ---------- */

add_action(
	'send_headers',
	function () {
		if ( headers_sent() || is_admin() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000' );
		}
	}
);

/* ---------- Moteurs de recherche ---------- */

/* Pages de filtres (agenda, actualités) et de confirmation : pas d'indexation, liens suivis. */
add_filter(
	'wp_robots',
	function ( $robots ) {
		foreach ( array( 'lieu', 'type', 'pg', 'message', 'don', 'messe', 'objet', 'ref' ) as $k ) {
			if ( isset( $_GET[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- simple lecture de paramètres de filtre pour la balise noindex.
				$robots['noindex'] = true;
				$robots['follow']  = true;
				break;
			}
		}
		return $robots;
	}
);

/* ---------- Vidéos YouTube sans cookie ---------- */

/** Remplace les lecteurs youtube.com par youtube-nocookie.com (pas de cookie Google avant la lecture). */
function vsg_youtube_nocookie( $html ) {
	return preg_replace( '#https?://(www\.)?youtube\.com/embed/#i', 'https://www.youtube-nocookie.com/embed/', (string) $html );
}
add_filter( 'embed_oembed_html', 'vsg_youtube_nocookie', 20 );
add_filter( 'render_block_core/embed', 'vsg_youtube_nocookie', 20 );

/* ---------- Adresses e-mail affichées ---------- */

/** Lien mailto lisible par les visiteurs, brouillé pour les robots qui collectent les adresses. */
function vsg_mailto( $email, $css_class = '' ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return '';
	}
	return '<a href="' . esc_attr( 'mailto:' . antispambot( $email, 1 ) ) . '"' . ( $css_class ? ' class="' . esc_attr( $css_class ) . '"' : '' ) . '>' . antispambot( $email ) . '</a>';
}
