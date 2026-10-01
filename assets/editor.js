/**
 * Block-Registrierung für den Editor.
 * Ausgabe macht PHP, wir kümmern uns nur um die Seitenleiste
 * und die ServerSideRender-Vorschau. Kein Build-Schritt nötig.
 *
 * Hero und Schnellzugriff speichern ihre Inhalte NICHT mehr in
 * Block-Attributen, sondern in der Option "vielbunt_frontpage"
 * (siehe inc/frontpage.php). Bearbeitet wird die über die Core-Entity
 * "site". Heißt:
 *  - gespeichert wird ganz normal mit „Speichern", zusammen mit dem Template
 *  - nach Theme-Updates, Template-Reset oder Neu-Upload ist nichts weg
 *  - Bilder liegen als einfache Liste vor, WordPress kann sie nicht mehr verbiegen
 */
( function ( blocks, element, ssr, i18n, blockEditor, components, coreData ) {
	var el            = element.createElement;
	var Fragment      = element.Fragment;
	var __            = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var MediaUpload       = blockEditor.MediaUpload;
	var MediaUploadCheck  = blockEditor.MediaUploadCheck;
	var PanelBody      = components.PanelBody;
	var Button         = components.Button;
	var TextControl    = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var Notice         = components.Notice;
	var Spinner        = components.Spinner;

	var CONFIG   = window.vielbuntFrontpage || { option: 'vielbunt_frontpage', defaults: { hero: {}, heading: 'Schnellzugriff', tiles: [] } };
	var OPTION   = CONFIG.option;
	var DEFAULTS = CONFIG.defaults;
	var TILES    = 8;

	/* Immer die komplette Form zurückgeben, auch wenn die Option noch leer ist */
	function normalize( raw ) {
		var d    = JSON.parse( JSON.stringify( raw || {} ) );
		var hero = d.hero || {};
		[ 'kicker', 'title', 'lead', 'btn1Label', 'btn1Url', 'btn2Label', 'btn2Url', 'bgUrl' ].forEach( function ( k ) {
			if ( typeof hero[ k ] !== 'string' ) { hero[ k ] = ''; }
		} );
		hero.bgId = parseInt( hero.bgId, 10 ) || 0;

		var ql    = d.quicklinks || {};
		var tiles = Array.isArray( ql.tiles ) ? ql.tiles : [];
		var list  = [];
		for ( var i = 0; i < TILES; i++ ) {
			var t = tiles[ i ] || {};
			list.push( {
				label:  typeof t.label === 'string' ? t.label : '',
				url:    typeof t.url === 'string' ? t.url : '',
				imgId:  parseInt( t.imgId, 10 ) || 0,
				imgUrl: typeof t.imgUrl === 'string' ? t.imgUrl : ''
			} );
		}
		return {
			hero: hero,
			quicklinks: { heading: typeof ql.heading === 'string' ? ql.heading : '', tiles: list }
		};
	}

	/* Liest die Option aus der site-Entity, gibt [ data, update ] zurück.
	   update( fn ) bekommt eine frische Kopie, fn ändert sie, danach geht sie
	   zurück in die Entity. Der Editor merkt sich das als ungespeichert und
	   „Speichern" schreibt es weg. */
	function useFrontpage() {
		var prop   = coreData.useEntityProp( 'root', 'site', OPTION );
		var raw    = prop[ 0 ];
		var setRaw = prop[ 1 ];
		var data   = raw === undefined ? null : normalize( raw );
		function update( fn ) {
			var next = normalize( raw );
			fn( next );
			setRaw( next );
		}
		return [ data, update ];
	}

	function loading() {
		return el( 'div', { style: { padding: '0 16px 16px' } }, el( Spinner ) );
	}

	function saveHint() {
		return el( Notice, { status: 'info', isDismissible: false },
			__( 'Änderungen siehst du sofort in der Vorschau. Übernommen werden sie mit „Speichern" oben rechts, und sie bleiben auch bei Theme-Updates erhalten.', 'vielbunt' )
		);
	}

	/* Bildauswahl mit kleinem Vorschaubild, damit man sieht was gewählt ist */
	function imagePicker( id, url, onPick, onRemove, labelPick ) {
		return el( 'div', {},
			url ? el( 'img', { src: url, alt: '', style: { display: 'block', width: '100%', maxHeight: 120, objectFit: 'cover', marginBottom: 8 } } ) : null,
			el( 'div', { style: { display: 'flex', alignItems: 'center', gap: 8 } },
				el( MediaUploadCheck, {},
					el( MediaUpload, {
						allowedTypes: [ 'image' ],
						value: id || 0,
						onSelect: function ( m ) { onPick( m ); },
						render: function ( o ) {
							return el( Button, { variant: 'secondary', onClick: o.open },
								url ? __( 'Bild ersetzen', 'vielbunt' ) : labelPick );
						}
					} )
				),
				url ? el( Button, { variant: 'link', isDestructive: true, onClick: onRemove },
					__( 'Entfernen', 'vielbunt' ) ) : null
			)
		);
	}

	/* Hero */
	function heroEdit() {
		var fp     = useFrontpage();
		var data   = fp[ 0 ];
		var update = fp[ 1 ];
		var def    = DEFAULTS.hero || {};

		if ( ! data ) {
			return el( Fragment, {},
				el( InspectorControls, {}, loading() ),
				el( ssr, { block: 'vielbunt/hero', attributes: {}, httpMethod: 'POST' } )
			);
		}
		var h = data.hero;

		function field( Control, key, label, help ) {
			return el( Control, {
				label: label,
				help: help,
				value: h[ key ],
				placeholder: def[ key ] || '',
				onChange: function ( v ) { update( function ( d ) { d.hero[ key ] = v; } ); }
			} );
		}

		return el( Fragment, {},
			el( InspectorControls, {},

				el( 'div', { style: { padding: '0 16px' } }, saveHint() ),

				/* --- Hintergrundbild --- */
				el( PanelBody, { title: __( 'Hintergrundbild', 'vielbunt' ), initialOpen: true },
					imagePicker( h.bgId, h.bgUrl,
						function ( m ) { update( function ( d ) { d.hero.bgId = m.id; d.hero.bgUrl = m.url; } ); },
						function () { update( function ( d ) { d.hero.bgId = 0; d.hero.bgUrl = ''; } ); },
						__( 'Hintergrundbild wählen', 'vielbunt' )
					)
				),

				/* --- Hero-Text --- */
				el( PanelBody, { title: __( 'Hero-Text', 'vielbunt' ), initialOpen: false },
					field( TextControl, 'kicker', __( 'Kicker (Zeile über dem Titel)', 'vielbunt' ), __( 'Leer lassen für den grauen Standardtext.', 'vielbunt' ) ),
					field( TextControl, 'title', __( 'Titel (H1)', 'vielbunt' ) ),
					field( TextareaControl, 'lead', __( 'Leadtext', 'vielbunt' ) )
				),

				/* --- Buttons --- */
				el( PanelBody, { title: __( 'Buttons', 'vielbunt' ), initialOpen: false },
					el( 'p', { style: { fontWeight: 600, marginBottom: 4 } }, __( 'Weißer Button (links)', 'vielbunt' ) ),
					field( TextControl, 'btn1Label', __( 'Beschriftung', 'vielbunt' ) ),
					field( TextControl, 'btn1Url', __( 'URL', 'vielbunt' ) ),
					el( 'p', { style: { fontWeight: 600, marginTop: 12, marginBottom: 4 } }, __( 'Transparenter Button (rechts)', 'vielbunt' ) ),
					field( TextControl, 'btn2Label', __( 'Beschriftung', 'vielbunt' ) ),
					field( TextControl, 'btn2Url', __( 'URL', 'vielbunt' ) )
				)
			),

			el( ssr, { block: 'vielbunt/hero', attributes: { preview: data }, httpMethod: 'POST' } )
		);
	}

	/* Schnellzugriff */
	function quicklinksEdit() {
		var fp     = useFrontpage();
		var data   = fp[ 0 ];
		var update = fp[ 1 ];

		if ( ! data ) {
			return el( Fragment, {},
				el( InspectorControls, {}, loading() ),
				el( ssr, { block: 'vielbunt/quicklinks', attributes: {}, httpMethod: 'POST' } )
			);
		}
		var ql = data.quicklinks;

		function setTile( i, patch ) {
			update( function ( d ) { Object.assign( d.quicklinks.tiles[ i ], patch ); } );
		}

		var tileRows = ql.tiles.map( function ( tile, i ) {
			var def = DEFAULTS.tiles[ i ] || { label: '', url: '' };

			return el( 'div', { key: i, style: { marginBottom: 16, paddingBottom: 16, borderBottom: '1px solid #e0e0e0' } },

				/* Nummer + Standard-Beschriftung als Überschrift */
				el( 'strong', { style: { display: 'block', marginBottom: 8, fontSize: 12 } },
					( i + 1 ) + '. ' + def.label ),

				el( TextControl, {
					label:       __( 'Beschriftung', 'vielbunt' ),
					placeholder: def.label,
					value:       tile.label,
					onChange:    function ( v ) { setTile( i, { label: v } ); }
				} ),

				el( TextControl, {
					label:       __( 'URL', 'vielbunt' ),
					placeholder: def.url,
					value:       tile.url,
					onChange:    function ( v ) { setTile( i, { url: v } ); }
				} ),

				imagePicker( tile.imgId, tile.imgUrl,
					function ( m ) { setTile( i, { imgId: m.id, imgUrl: m.url } ); },
					function () { setTile( i, { imgId: 0, imgUrl: '' } ); },
					__( 'Hintergrundbild', 'vielbunt' )
				)
			);
		} );

		return el( Fragment, {},
			el( InspectorControls, {},
				el( 'div', { style: { padding: '0 16px' } }, saveHint() ),
				el( PanelBody, { title: __( 'Überschrift', 'vielbunt' ), initialOpen: false },
					el( TextControl, {
						label: __( 'Überschrift', 'vielbunt' ),
						placeholder: DEFAULTS.heading || 'Schnellzugriff',
						value: ql.heading,
						onChange: function ( v ) { update( function ( d ) { d.quicklinks.heading = v; } ); }
					} )
				),
				el( PanelBody, { title: __( 'Kacheln (Beschriftung, URL, Bild)', 'vielbunt' ), initialOpen: true },
					tileRows
				)
			),
			el( ssr, { block: 'vielbunt/quicklinks', attributes: { preview: data }, httpMethod: 'POST' } )
		);
	}

	/* Blöcke registrieren. Die alten Inhalts-Attribute sind mit Absicht weg,
	   was davon noch in einem alten Template steht fliegt beim nächsten Speichern raus. */
	blocks.registerBlockType( 'vielbunt/hero', {
		apiVersion: 3,
		title:    __( 'vielbunt: Hero', 'vielbunt' ),
		category: 'widgets', icon: 'cover-image',
		supports: { html: false, reusable: false },
		attributes: { preview: { type: 'object' } },
		edit: heroEdit, save: function () { return null; }
	} );

	blocks.registerBlockType( 'vielbunt/quicklinks', {
		apiVersion: 3,
		title:    __( 'vielbunt: Schnellzugriff', 'vielbunt' ),
		category: 'widgets', icon: 'grid-view',
		supports: { html: false, reusable: false },
		attributes: { preview: { type: 'object' } },
		edit: quicklinksEdit, save: function () { return null; }
	} );

	function registerPlain( name, title, icon, attrs ) {
		blocks.registerBlockType( name, {
			apiVersion: 3, title: title, category: 'widgets', icon: icon,
			supports: { html: false, reusable: false }, attributes: attrs || {},
			edit: function ( props ) { return el( ssr, { block: name, attributes: props.attributes } ); },
			save: function () { return null; }
		} );
	}
	registerPlain( 'vielbunt/events',      __( 'vielbunt: Aktuelle Termine', 'vielbunt' ), 'calendar-alt' );
	registerPlain( 'vielbunt/feed',        __( 'vielbunt: News-Feed', 'vielbunt' ),        'list-view' );
	registerPlain( 'vielbunt/logo',        __( 'vielbunt: Logo', 'vielbunt' ),             'flag', { variant: { type: 'string' } } );
	registerPlain( 'vielbunt/footerlinks', __( 'vielbunt: Footer-Links', 'vielbunt' ),     'editor-ul' );

} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender, window.wp.i18n, window.wp.blockEditor, window.wp.components, window.wp.coreData );
