/* Apparence › Mises à jour du thème : afficher ou masquer la clé, la copier. */
( function () {
	var f = document.getElementById( 'vsg-au-secret' ),
		eye = document.getElementById( 'vsg-au-eye' ),
		cp = document.getElementById( 'vsg-au-copy' );
	if ( ! f || ! eye || ! cp ) {
		return;
	}
	eye.addEventListener( 'click', function () {
		var show = 'password' === f.type;
		f.type = show ? 'text' : 'password';
		eye.firstChild.classList.toggle( 'dashicons-hidden', show );
		eye.firstChild.classList.toggle( 'dashicons-visibility', ! show );
		eye.setAttribute( 'aria-label', show ? 'Masquer la clé' : 'Afficher la clé' );
		eye.title = eye.getAttribute( 'aria-label' );
	} );
	cp.addEventListener( 'click', function () {
		var done = function () {
			cp.textContent = 'Copiée';
			setTimeout( function () {
				cp.textContent = 'Copier';
			}, 2000 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( f.value ).then( done );
		} else {
			var t = f.type;
			f.type = 'text';
			f.select();
			document.execCommand( 'copy' );
			f.type = t;
			f.blur();
			done();
		}
	} );
}() );

/*
 * Images (photo du curé, visuel du thème, photos des prêtres) : bouton « Choisir une image »
 * qui ouvre la médiathèque (on peut y déposer une nouvelle photo), aperçu et « Retirer ».
 * L'adresse de l'image reste enregistrée dans le champ, comme avant.
 */
var vsgChoisirImage = function ( titre, quandChoisie ) {
	var frame = wp.media( {
		title: titre,
		button: { text: 'Utiliser cette image' },
		library: { type: 'image' },
		multiple: false,
	} );
	frame.on( 'select', function () {
		var a = frame.state().get( 'selection' ).first().toJSON();
		var taille = a.sizes && ( a.sizes.large || a.sizes.medium_large || a.sizes.full );
		quandChoisie( taille ? taille.url : a.url, a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url );
	} );
	frame.open();
};

( function () {
	if ( ! window.wp || ! wp.media ) {
		return;
	}
	document.querySelectorAll( '[data-vsg-image]' ).forEach( function ( box ) {
		var url = box.querySelector( '.vsg-image__url' );
		var img = box.querySelector( '.vsg-image__preview' );
		var actions = document.createElement( 'span' );
		actions.className = 'vsg-image__actions';
		actions.innerHTML = '<button type="button" class="button vsg-image__choose">Choisir une image</button> <button type="button" class="button-link vsg-image__clear">Retirer l\'image</button>';
		box.appendChild( actions );
		var clear = actions.querySelector( '.vsg-image__clear' );
		/* L'adresse brute ne sert plus : le bouton suffit (le champ reste dans le formulaire). */
		url.style.display = 'none';
		function afficher() {
			img.src = url.value;
			img.hidden = ! url.value;
			clear.hidden = ! url.value;
		}
		actions.querySelector( '.vsg-image__choose' ).addEventListener( 'click', function () {
			vsgChoisirImage( 'Choisir une image', function ( adresse ) {
				url.value = adresse;
				afficher();
			} );
		} );
		clear.addEventListener( 'click', function () {
			url.value = '';
			afficher();
		} );
		url.addEventListener( 'change', afficher );
		afficher();
	} );
}() );

/*
 * Autres prêtres : la zone de texte « Nom ; rôle ; adresse de la photo » (une ligne par prêtre)
 * devient un petit formulaire. La zone reste dans la page, masquée, et c'est elle qui est enregistrée :
 * le format ne change pas pour l'affichage du site.
 */
( function () {
	var zone = document.getElementById( 'vsg_pretres' );
	if ( ! zone || ! window.wp || ! wp.media ) {
		return;
	}
	var propre = function ( s ) {
		return String( s || '' ).replace( /[;\n\r]+/g, ',' ).trim();
	};
	var liste = document.createElement( 'div' );
	liste.className = 'vsg-pretres';
	var ajouter = document.createElement( 'button' );
	ajouter.type = 'button';
	ajouter.className = 'button';
	ajouter.textContent = '+ Ajouter un prêtre';
	zone.hidden = true;
	var aide = zone.parentNode.querySelector( '.vsg-hint' );
	if ( aide ) {
		aide.style.setProperty( 'display', 'none', 'important' );
	}
	zone.parentNode.insertBefore( liste, zone );
	zone.parentNode.insertBefore( ajouter, zone );

	function ecrire() {
		var lignes = [];
		liste.querySelectorAll( '.vsg-pretre' ).forEach( function ( l ) {
			var nom = propre( l.querySelector( '.vsg-pretre__nom' ).value );
			if ( ! nom ) {
				return;
			}
			lignes.push( [ nom, propre( l.querySelector( '.vsg-pretre__role' ).value ), propre( l.dataset.photo ) ].join( ' ; ' ).replace( /( ; )+$/, '' ) );
		} );
		zone.value = lignes.join( '\n' );
	}
	function ligne( nom, role, photo ) {
		var n = liste.children.length + 1;
		var l = document.createElement( 'div' );
		l.className = 'vsg-pretre';
		l.dataset.photo = photo || '';
		l.innerHTML = '<img class="vsg-pretre__photo" alt="">'
			+ '<label><span class="vsg-label">Nom</span><input type="text" class="vsg-pretre__nom" placeholder="Ex. : Père Paul Martin"></label>'
			+ '<label><span class="vsg-label">Rôle</span><input type="text" class="vsg-pretre__role" placeholder="Ex. : vicaire"></label>'
			+ '<span class="vsg-pretre__actions"><button type="button" class="button vsg-pretre__choisir">Photo</button> <button type="button" class="button-link vsg-pretre__retirer">Retirer ce prêtre</button></span>';
		l.querySelector( '.vsg-pretre__nom' ).value = nom || '';
		l.querySelector( '.vsg-pretre__role' ).value = role || '';
		l.querySelector( '.vsg-pretre__nom' ).setAttribute( 'aria-label', 'Nom du prêtre ' + n );
		l.querySelector( '.vsg-pretre__role' ).setAttribute( 'aria-label', 'Rôle du prêtre ' + n );
		var img = l.querySelector( '.vsg-pretre__photo' );
		/* Sans photo : rond gris à la même place (la ligne garde sa mise en page). */
		function afficherPhoto() {
			if ( l.dataset.photo ) {
				img.src = l.dataset.photo;
			} else {
				img.removeAttribute( 'src' );
			}
		}
		afficherPhoto();
		l.querySelector( '.vsg-pretre__choisir' ).addEventListener( 'click', function () {
			vsgChoisirImage( 'Photo du prêtre', function ( adresse ) {
				l.dataset.photo = adresse;
				afficherPhoto();
				ecrire();
			} );
		} );
		l.querySelector( '.vsg-pretre__retirer' ).addEventListener( 'click', function () {
			l.remove();
			ecrire();
		} );
		l.addEventListener( 'input', ecrire );
		liste.appendChild( l );
		return l;
	}
	zone.value.split( /\n/ ).forEach( function ( t ) {
		var p = t.split( ';' ).map( function ( s ) {
			return s.trim();
		} );
		if ( p[ 0 ] ) {
			ligne( p[ 0 ], p[ 1 ], p[ 2 ] );
		}
	} );
	ajouter.addEventListener( 'click', function () {
		ligne( '', '', '' ).querySelector( '.vsg-pretre__nom' ).focus();
	} );
}() );
