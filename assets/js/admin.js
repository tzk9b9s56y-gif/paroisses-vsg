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
