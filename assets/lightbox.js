/**
 * Kleine Lightbox ohne jQuery (gleiche Datei in csd-darmstadt.de und vielbunt.org).
 *
 * Ersetzt das alte FancyBox-Plugin (jQuery + Fancybox 1.3 + Easing, zuletzt
 * mit WordPress 3.5 getestet). Greift wie das Plugin bei jedem Link, der auf
 * eine Bilddatei zeigt. Bilder im selben Beitrag bzw. in derselben Galerie
 * lassen sich mit Pfeilen, Pfeiltasten oder Wischen durchblättern.
 * Bildblöcke ohne Link übernimmt die eingebaute Lightbox von WordPress
 * (theme.json), die kommen sich nicht in die Quere.
 */
( function () {
	'use strict';

	var IMG_LINK = /\.(jpe?g|png|gif|webp|avif|bmp)(\?[^#]*)?(#.*)?$/i;
	var GROUP    = '.wp-block-gallery, .gallery, .vb-feed__row, .entry-content, .wp-block-post-content, main';

	var box, imgEl, capEl, countEl, prevBtn, nextBtn, closeBtn;
	var items = [], index = 0, lastFocus = null, touchX = null;

	function isImageLink( a ) {
		return a && a.href && IMG_LINK.test( a.href ) && ! a.hasAttribute( 'download' ) && ! a.closest( '.vb-lb' );
	}

	function captionFor( a ) {
		var fig = a.closest( 'figure' );
		var cap = fig && fig.querySelector( 'figcaption' );
		if ( cap && cap.textContent.trim() ) {
			return cap.textContent.trim();
		}
		var img = a.querySelector( 'img' );
		return img && img.alt ? img.alt : '';
	}

	function button( cls, label, text ) {
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = 'vb-lb__btn ' + cls;
		b.setAttribute( 'aria-label', label );
		b.textContent = text;
		return b;
	}

	function build() {
		box = document.createElement( 'div' );
		box.className = 'vb-lb';
		box.setAttribute( 'role', 'dialog' );
		box.setAttribute( 'aria-modal', 'true' );
		box.setAttribute( 'aria-label', 'Bildansicht' );
		box.hidden = true;

		var figure = document.createElement( 'figure' );
		figure.className = 'vb-lb__figure';
		imgEl = document.createElement( 'img' );
		imgEl.className = 'vb-lb__img';
		imgEl.alt = '';
		capEl = document.createElement( 'figcaption' );
		capEl.className = 'vb-lb__caption';
		figure.appendChild( imgEl );
		figure.appendChild( capEl );

		countEl  = document.createElement( 'span' );
		countEl.className = 'vb-lb__count';
		closeBtn = button( 'vb-lb__close', 'Schließen', '×' );
		prevBtn  = button( 'vb-lb__prev', 'Vorheriges Bild', '‹' );
		nextBtn  = button( 'vb-lb__next', 'Nächstes Bild', '›' );

		box.appendChild( figure );
		box.appendChild( countEl );
		box.appendChild( prevBtn );
		box.appendChild( nextBtn );
		box.appendChild( closeBtn );
		document.body.appendChild( box );

		closeBtn.addEventListener( 'click', close );
		prevBtn.addEventListener( 'click', function () { show( index - 1 ); } );
		nextBtn.addEventListener( 'click', function () { show( index + 1 ); } );
		// Klick neben das Bild schließt
		box.addEventListener( 'click', function ( e ) {
			if ( e.target === box || e.target === figure ) {
				close();
			}
		} );
		box.addEventListener( 'touchstart', function ( e ) {
			touchX = e.touches.length === 1 ? e.touches[ 0 ].clientX : null;
		}, { passive: true } );
		box.addEventListener( 'touchend', function ( e ) {
			if ( null === touchX ) {
				return;
			}
			var dx = e.changedTouches[ 0 ].clientX - touchX;
			touchX = null;
			if ( Math.abs( dx ) > 50 ) {
				show( index + ( dx < 0 ? 1 : -1 ) );
			}
		} );
	}

	function show( i ) {
		if ( ! items.length ) {
			return;
		}
		index = ( i + items.length ) % items.length;
		var a = items[ index ];
		var many = items.length > 1;
		// Nächstes Bild erst vorladen, wenn das aktuelle da ist. Vorher liefen
		// drei große Dateien gleichzeitig und das sichtbare Bild kam später.
		imgEl.onload = many ? function () {
			new Image().src = items[ ( index + 1 ) % items.length ].href;
		} : null;
		imgEl.src = a.href;
		capEl.textContent = captionFor( a );
		capEl.hidden = ! capEl.textContent;
		prevBtn.hidden = nextBtn.hidden = countEl.hidden = ! many;
		countEl.textContent = ( index + 1 ) + ' / ' + items.length;
	}

	function open( a ) {
		if ( ! box ) {
			build();
		}
		var group = a.closest( GROUP ) || document.body;
		items = Array.prototype.filter.call( group.querySelectorAll( 'a[href]' ), isImageLink );
		if ( items.indexOf( a ) < 0 ) {
			items = [ a ];
		}
		lastFocus = document.activeElement;
		box.hidden = false;
		document.documentElement.classList.add( 'vb-lb-open' );
		show( items.indexOf( a ) );
		closeBtn.focus();
		document.addEventListener( 'keydown', onKey );
	}

	function close() {
		box.hidden = true;
		imgEl.onload = null;
		imgEl.removeAttribute( 'src' );
		document.documentElement.classList.remove( 'vb-lb-open' );
		document.removeEventListener( 'keydown', onKey );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	function onKey( e ) {
		if ( 'Escape' === e.key ) {
			close();
		} else if ( 'ArrowRight' === e.key ) {
			show( index + 1 );
		} else if ( 'ArrowLeft' === e.key ) {
			show( index - 1 );
		} else if ( 'Tab' === e.key ) {
			// Fokus bleibt in der Lightbox
			var f = [ closeBtn, prevBtn, nextBtn ].filter( function ( b ) { return ! b.hidden; } );
			var pos = f.indexOf( document.activeElement );
			e.preventDefault();
			f[ ( pos + ( e.shiftKey ? -1 : 1 ) + f.length ) % f.length ].focus();
		}
	}

	document.addEventListener( 'click', function ( e ) {
		if ( e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
			return;
		}
		var a = e.target.closest && e.target.closest( 'a[href]' );
		if ( ! isImageLink( a ) ) {
			return;
		}
		e.preventDefault();
		open( a );
	} );
} )();
