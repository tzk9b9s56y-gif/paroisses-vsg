/* Touche Échap : ferme le menu mobile, ou le bloc dépliable où se trouve le curseur. */
document.addEventListener( 'keydown', function ( e ) {
	if ( 'Escape' !== e.key ) {
		return;
	}
	var burger = document.getElementById( 'vsg-burger' );
	if ( burger && burger.checked ) {
		burger.checked = false;
		burger.focus();
		return;
	}
	var d = document.activeElement && document.activeElement.closest ? document.activeElement.closest( 'details[open]' ) : null;
	if ( d ) {
		d.open = false;
		d.querySelector( 'summary' ).focus();
	}
} );

/* Formulaire renvoyé avec des erreurs : le curseur se place sur le message qui les annonce. */
( function () {
	var n = document.querySelector( '.notice--err[role="alert"]' );
	if ( n ) {
		n.setAttribute( 'tabindex', '-1' );
		/* Après le chargement : le saut vers l'ancre du formulaire (#…-form) reprendrait sinon le focus. */
		window.addEventListener( 'load', function () {
			setTimeout( function () {
				n.focus();
			}, 0 );
		} );
	}
}() );
