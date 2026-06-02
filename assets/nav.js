/**
 * Mobile nav
 *
 * Wir nutzen capture+bubble damit WP's eigener Handler zuerst läuft
 * bevor wir den State auslesen. Wenn WP nichts gemacht hat übernehemen
 * wir selbst (Fallback für ältere Installationen).
 * Außerdem wird hier der Spenden-Button ins Overlay injiziert.
 */
( function () {
	'use strict';

	/* State kurz vor dem Klick festhalten */
	var preClickState = new WeakMap();

	function init() {
		var nav = document.querySelector( '.wp-block-navigation__responsive-container' );
		if ( ! nav ) { return; }

		/* Capture-Phase: State vor WP's Handler festhalten */
		nav.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.wp-block-navigation-submenu__toggle' );
			if ( ! btn ) { return; }
			var item    = btn.closest( '.has-child' );
			var submenu = item && item.querySelector( ':scope > .wp-block-navigation__submenu-container' );
			if ( ! item || ! submenu ) { return; }
			preClickState.set( btn, {
				wpOpen: submenu.classList.contains( 'open' ) || item.classList.contains( 'open' )
			} );
		}, true /* capture */ );

		/* Bubble-Phase: WP hat bereits reagiert, jetzt sync'en */
		nav.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.wp-block-navigation-submenu__toggle' );
			if ( ! btn ) { return; }

			var pre = preClickState.get( btn );
			preClickState.delete( btn );
			if ( ! pre ) { return; }

			var item    = btn.closest( '.has-child' );
			var submenu = item && item.querySelector( ':scope > .wp-block-navigation__submenu-container' );
			if ( ! item || ! submenu ) { return; }

			var nowWpOpen = submenu.classList.contains( 'open' ) || item.classList.contains( 'open' );

			if ( nowWpOpen !== pre.wpOpen ) {
				/* WP hat getoggelt, wir spiegeln das in vb-open */
				if ( nowWpOpen && item.parentElement ) {
					[].forEach.call(
						item.parentElement.querySelectorAll( ':scope > .has-child.vb-open' ),
						function ( s ) { s.classList.remove( 'vb-open' ); }
					);
				}
				item.classList.toggle( 'vb-open', nowWpOpen );
			} else {
				/* WP hat nichts gemacht, wir übernehmen selbst */
				var willOpen = ! item.classList.contains( 'vb-open' );
				if ( item.parentElement ) {
					[].forEach.call(
						item.parentElement.querySelectorAll( ':scope > .has-child.vb-open' ),
						function ( s ) {
							s.classList.remove( 'vb-open' );
							var sb = s.querySelector( ':scope > .wp-block-navigation-submenu__toggle' );
							if ( sb ) { sb.setAttribute( 'aria-expanded', 'false' ); }
						}
					);
				}
				item.classList.toggle( 'vb-open', willOpen );
				btn.setAttribute( 'aria-expanded', willOpen ? 'true' : 'false' );
			}
		}, false /* bubble */ );

		/* Untermenüs zurücksetzen wenn das Overlay neu geöffnet wird */
		var openBtn = document.querySelector( '.wp-block-navigation__responsive-container-open' );
		if ( openBtn ) {
			openBtn.addEventListener( 'click', function () {
				[].forEach.call( nav.querySelectorAll( '.vb-open' ), function ( el ) {
					el.classList.remove( 'vb-open' );
				} );
			} );
		}

		/* Spenden-Button ins Overlay packen */
		var content = nav.querySelector( '.wp-block-navigation__responsive-container-content' );
		if ( content && ! nav.querySelector( '.vb-mobile-spenden' ) ) {
			var wrapper = document.createElement( 'div' );
			wrapper.className = 'vb-mobile-spenden';
			var link = document.createElement( 'a' );
			link.href = '/spenden/';
			link.className = 'vb-mobile-spenden__link';
			link.textContent = 'Spenden';
			wrapper.appendChild( link );
			content.appendChild( wrapper );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
