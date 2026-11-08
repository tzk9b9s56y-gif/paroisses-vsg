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

/*
 * Visionneuse des galeries (galerie de blocs ou galerie de l'éditeur classique) :
 * un clic ouvre la photo en grand ; flèches ‹ ›, clavier (← → Échap) et glissement du doigt
 * pour passer d'une photo à l'autre. Fonctionne quel que soit le réglage « Lier à » de la galerie.
 */
(function () {
	var galleries = document.querySelectorAll('.gallery, .wp-block-gallery');
	if (!galleries.length) { return; }

	function fullSrc(img, link) {
		if (link && /\.(jpe?g|png|gif|webp|avif)(\?.*)?$/i.test(link.getAttribute('href') || '')) { return link.href; }
		var best = img.currentSrc || img.src, w = 0;
		(img.getAttribute('srcset') || '').split(',').forEach(function (c) {
			var p = c.trim().split(/\s+/), n = parseInt(p[1], 10) || 0;
			if (p[0] && n > w) { w = n; best = p[0]; }
		});
		return best;
	}

	var box, pic, cap, count, prevBtn, nextBtn, items = [], index = 0, opener = null, startX = null;

	function build() {
		box = document.createElement('div');
		box.className = 'lightbox';
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-modal', 'true');
		box.setAttribute('aria-label', 'Photos en grand');
		box.hidden = true;
		box.innerHTML =
			'<button type="button" class="lightbox__close" aria-label="Fermer">×</button>' +
			'<button type="button" class="lightbox__nav lightbox__prev" aria-label="Photo précédente">‹</button>' +
			'<figure class="lightbox__fig"><img alt=""><figcaption></figcaption></figure>' +
			'<button type="button" class="lightbox__nav lightbox__next" aria-label="Photo suivante">›</button>' +
			'<p class="lightbox__count" aria-live="polite"></p>';
		document.body.appendChild(box);
		pic = box.querySelector('img');
		cap = box.querySelector('figcaption');
		count = box.querySelector('.lightbox__count');
		prevBtn = box.querySelector('.lightbox__prev');
		nextBtn = box.querySelector('.lightbox__next');
		box.querySelector('.lightbox__close').addEventListener('click', close);
		prevBtn.addEventListener('click', function () { show(index - 1); });
		nextBtn.addEventListener('click', function () { show(index + 1); });
		box.addEventListener('click', function (e) { if (e.target === box) { close(); } });
		box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
		box.addEventListener('touchend', function (e) {
			if (startX === null) { return; }
			var dx = e.changedTouches[0].clientX - startX;
			startX = null;
			if (Math.abs(dx) > 40) { show(index + (dx < 0 ? 1 : -1)); }
		});
		document.addEventListener('keydown', function (e) {
			if (box.hidden) { return; }
			if (e.key === 'Escape') { close(); }
			else if (e.key === 'ArrowRight') { show(index + 1); }
			else if (e.key === 'ArrowLeft') { show(index - 1); }
			else if (e.key === 'Tab') {
				var f = box.querySelectorAll('button:not([hidden])'), first = f[0], last = f[f.length - 1];
				if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
				else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
			}
		});
	}

	function show(i) {
		index = (i + items.length) % items.length;
		var it = items[index];
		pic.src = it.src;
		pic.alt = it.alt;
		cap.textContent = it.caption;
		cap.hidden = !it.caption;
		count.textContent = items.length > 1 ? (index + 1) + ' / ' + items.length : '';
		prevBtn.hidden = nextBtn.hidden = items.length < 2;
		[index + 1, index - 1].forEach(function (j) { var n = items[(j + items.length) % items.length]; if (n) { (new Image()).src = n.src; } });
	}

	function open(list, i, from) {
		if (!box) { build(); }
		items = list;
		opener = from;
		box.hidden = false;
		document.documentElement.classList.add('lightbox-open');
		show(i);
		box.querySelector('.lightbox__close').focus();
	}

	function close() {
		box.hidden = true;
		pic.removeAttribute('src');
		document.documentElement.classList.remove('lightbox-open');
		if (opener) { opener.focus(); }
	}

	galleries.forEach(function (g) {
		var imgs = g.querySelectorAll('img'), list = [];
		imgs.forEach(function (img, i) {
			var link = img.closest('a'), fig = img.closest('figure, .gallery-item'), c = fig ? fig.querySelector('figcaption, .gallery-caption') : null;
			list.push({ src: fullSrc(img, link), alt: img.alt || '', caption: c ? c.textContent.trim() : '' });
			var target = link || img;
			if (!link) { img.setAttribute('tabindex', '0'); img.setAttribute('role', 'button'); }
			target.setAttribute('aria-label', 'Agrandir la photo ' + (i + 1) + ' sur ' + imgs.length);
			target.classList.add('lightbox-trigger');
			target.addEventListener('click', function (e) { e.preventDefault(); open(list, i, target); });
			if (!link) {
				img.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(list, i, img); } });
			}
		});
	});
})();
