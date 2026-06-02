/**
 * Block-Registrierung für den Editor.
 * Ausgabe macht PHP, wir kümmern uns nur um die Seitenleiste
 * und die ServerSideRender-Vorschau. Kein Build-Schritt nötig.
 */
( function ( blocks, element, ssr, i18n, blockEditor, components ) {
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

	/* Hero */
	function heroEdit( props ) {
		var a = props.attributes;
		var set = props.setAttributes;

		return el( Fragment, {},

			el( InspectorControls, {},

				/* --- Hintergrundbild --- */
				el( PanelBody, { title: __( 'Hintergrundbild', 'vielbunt' ), initialOpen: true },
					el( MediaUploadCheck, {},
						el( MediaUpload, {
							allowedTypes: [ 'image' ],
							value: a.bgId,
							onSelect: function ( m ) { set( { bgUrl: m.url, bgId: m.id } ); },
							render: function ( o ) {
								return el( Button, { variant: 'secondary', onClick: o.open },
									a.bgUrl ? __( 'Bild ersetzen', 'vielbunt' ) : __( 'Hintergrundbild wählen', 'vielbunt' ) );
							}
						} )
					),
					a.bgUrl ? el( Button, {
						variant: 'link', isDestructive: true,
						style: { marginTop: '10px', display: 'block' },
						onClick: function () { set( { bgUrl: '', bgId: 0 } ); }
					}, __( 'Bild entfernen', 'vielbunt' ) ) : null
				),

				/* --- Hero-Text --- */
				el( PanelBody, { title: __( 'Hero-Text', 'vielbunt' ), initialOpen: false },
					el( TextControl, {
						label: __( 'Kicker (Zeile über dem Titel)', 'vielbunt' ),
						help:  __( 'Leer lassen für Standard: „QUEERE COMMUNITY DARMSTADT"', 'vielbunt' ),
						value: a.heroKicker || '',
						onChange: function ( v ) { set( { heroKicker: v } ); }
					} ),
					el( TextControl, {
						label: __( 'Titel (H1)', 'vielbunt' ),
						help:  __( 'Leer lassen für Standard: „Schön, dass du da bist."', 'vielbunt' ),
						value: a.heroTitle || '',
						onChange: function ( v ) { set( { heroTitle: v } ); }
					} ),
					el( TextareaControl, {
						label: __( 'Leadtext', 'vielbunt' ),
						help:  __( 'Leer lassen für Standardtext.', 'vielbunt' ),
						value: a.heroLead || '',
						onChange: function ( v ) { set( { heroLead: v } ); }
					} )
				),

				/* --- Buttons --- */
				el( PanelBody, { title: __( 'Buttons', 'vielbunt' ), initialOpen: false },
					el( 'p', { style: { fontWeight: 600, marginBottom: 4 } }, __( 'Weißer Button (links)', 'vielbunt' ) ),
					el( TextControl, {
						label: __( 'Beschriftung', 'vielbunt' ),
						help:  __( 'Leer → „Mitmachen"', 'vielbunt' ),
						value: a.btnSolidLabel || '',
						onChange: function ( v ) { set( { btnSolidLabel: v } ); }
					} ),
					el( TextControl, {
						label: __( 'URL', 'vielbunt' ),
						help:  __( 'Leer → /mitmachen/', 'vielbunt' ),
						value: a.btnSolidUrl || '',
						onChange: function ( v ) { set( { btnSolidUrl: v } ); }
					} ),
					el( 'p', { style: { fontWeight: 600, marginTop: 12, marginBottom: 4 } }, __( 'Transparenter Button (rechts)', 'vielbunt' ) ),
					el( TextControl, {
						label: __( 'Beschriftung', 'vielbunt' ),
						help:  __( 'Leer → „Zum Queeren Zentrum"', 'vielbunt' ),
						value: a.btnGhostLabel || '',
						onChange: function ( v ) { set( { btnGhostLabel: v } ); }
					} ),
					el( TextControl, {
						label: __( 'URL', 'vielbunt' ),
						help:  __( 'Leer → /queeres-zentrum-darmstadt/das-queere-zentrum/', 'vielbunt' ),
						value: a.btnGhostUrl || '',
						onChange: function ( v ) { set( { btnGhostUrl: v } ); }
					} )
				)
			),

			el( ssr, { block: 'vielbunt/hero', attributes: props.attributes } )
		);
	}

	/* Schnellzugriff */
	var QUICK_DEFAULTS = [
		{ label: 'Queeres Zentrum',       url: '/queeres-zentrum-darmstadt/das-queere-zentrum/' },
		{ label: 'Christopher Street Day', url: '/csd-darmstadt/' },
		{ label: 'Treffbunt',             url: '/aktivitaeten/treffbunt/' },
		{ label: 'vielbunt sport*',       url: '/aktivitaeten/sport/' },
		{ label: 'Refugees welcome',      url: '/aktivitaeten/refugees-welcome/' },
		{ label: 'villaQ · Jugend',       url: '/aktivitaeten/villaq/' },
		{ label: 'Beratung',              url: '/queeres-zentrum-darmstadt/beratung/' },
		{ label: 'Mitmachen!',            url: '/mitmachen/' },
	];

	function quicklinksEdit( props ) {
		var a   = props.attributes;
		var set = props.setAttributes;
		var imgs  = a.images || {};
		var tiles = a.tiles  || {};

		/* helper: update one field of one tile override */
		function setTile( i, field, value ) {
			var next = Object.assign( {}, tiles );
			next[ i ] = Object.assign( {}, next[ i ] || {}, { [field]: value } );
			set( { tiles: next } );
		}

		var tileRows = QUICK_DEFAULTS.map( function ( def, i ) {
			var cur     = imgs[ i ] || imgs[ String( i ) ] || null;
			var tileVal = tiles[ i ] || tiles[ String( i ) ] || {};

			return el( 'div', { key: i, style: { marginBottom: 16, paddingBottom: 16, borderBottom: '1px solid #e0e0e0' } },

				/* Tile number + default label as header */
				el( 'strong', { style: { display: 'block', marginBottom: 8, fontSize: 12 } },
					( i + 1 ) + '. ' + def.label ),

				/* Label override */
				el( TextControl, {
					label:       __( 'Beschriftung', 'vielbunt' ),
					placeholder: def.label,
					help:        __( 'Leer → Standard', 'vielbunt' ),
					value:       tileVal.label || '',
					onChange:    function ( v ) { setTile( i, 'label', v ); }
				} ),

				/* URL override */
				el( TextControl, {
					label:       __( 'URL', 'vielbunt' ),
					placeholder: def.url,
					help:        __( 'Leer → Standard', 'vielbunt' ),
					value:       tileVal.url || '',
					onChange:    function ( v ) { setTile( i, 'url', v ); }
				} ),

				/* Background image */
				el( 'div', { style: { display: 'flex', alignItems: 'center', gap: 8, marginTop: 4 } },
					el( MediaUploadCheck, {},
						el( MediaUpload, {
							allowedTypes: [ 'image' ],
							value: cur ? cur.id : 0,
							onSelect: function ( m ) {
								var n = Object.assign( {}, imgs );
								n[ i ] = { url: m.url, id: m.id };
								set( { images: n } );
							},
							render: function ( o ) {
								return el( Button, { variant: 'secondary', onClick: o.open },
									cur ? __( 'Bild ersetzen', 'vielbunt' ) : __( 'Hintergrundbild', 'vielbunt' ) );
							}
						} )
					),
					cur ? el( Button, {
						variant: 'link', isDestructive: true,
						onClick: function () {
							var n = Object.assign( {}, imgs );
							delete n[ i ]; delete n[ String( i ) ];
							set( { images: n } );
						}
					}, __( 'Entfernen', 'vielbunt' ) ) : null
				)
			);
		} );

		return el( Fragment, {},
			el( InspectorControls, {},
				el( PanelBody, { title: __( 'Überschrift', 'vielbunt' ), initialOpen: false },
					el( TextControl, {
						label: __( 'Überschrift', 'vielbunt' ),
						value: a.heading || '',
						onChange: function ( v ) { set( { heading: v } ); }
					} )
				),
				el( PanelBody, { title: __( 'Kacheln (Beschriftung, URL, Bild)', 'vielbunt' ), initialOpen: true },
					tileRows
				)
			),
			el( ssr, { block: 'vielbunt/quicklinks', attributes: props.attributes } )
		);
	}

	/* Blöcke registrieren */
	blocks.registerBlockType( 'vielbunt/hero', {
		apiVersion: 3,
		title:    __( 'vielbunt: Hero', 'vielbunt' ),
		category: 'widgets', icon: 'cover-image',
		supports: { html: false, reusable: false },
		attributes: {
			bgUrl: { type: 'string' }, bgId: { type: 'number' },
			heroKicker: { type: 'string' }, heroTitle: { type: 'string' },
			heroLead:   { type: 'string' },
			btnSolidLabel: { type: 'string' }, btnSolidUrl: { type: 'string' },
			btnGhostLabel: { type: 'string' }, btnGhostUrl: { type: 'string' },
		},
		edit: heroEdit, save: function () { return null; }
	} );

	blocks.registerBlockType( 'vielbunt/quicklinks', {
		apiVersion: 3,
		title:    __( 'vielbunt: Schnellzugriff', 'vielbunt' ),
		category: 'widgets', icon: 'grid-view',
		supports: { html: false, reusable: false },
		attributes: {
			heading: { type: 'string' },
			images:  { type: 'object' },
			tiles:   { type: 'object' },
		},
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

} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender, window.wp.i18n, window.wp.blockEditor, window.wp.components );
