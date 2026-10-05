<?php
/**
 * Startseiten-Inhalte (Hero + Schnellzugriff) als EINE wp_options-Zeile.
 *
 * Warum: Bis 2.1.x lagen Hero-Texte und Kachelbilder als Block-Attribute im
 * front-page-Template (plus eine Kopie in vielbunt_block_settings).
 * WordPress serialisiert Templates beim Speichern in PHP neu und macht dabei
 * aus {"0":{…},"1":{…}} eine JSON-Liste [{…},{…}]. Der Editor verwirft so eine
 * Liste beim nächsten Öffnen für ein "object"-Attribut kommentarlos, und
 * schon sind ALLE Kachelbilder weg. Theme-Neu-Upload oder Template-Reset
 * haben die Attribute zusätzlich jedes Mal mitgerissen.
 *
 * Jetzt nutzen wir für die Inhalte gar keine Block-Attribute mehr. Alles
 * liegt in der Option "vielbunt_frontpage", die
 *  - mit einem strengen REST-Schema registriert ist (wp/v2/settings),
 *  - im Site-Editor über die Core-Entity "site" bearbeitet wird, also ganz
 *    normal mit „Speichern" zusammen mit allem anderen gespeichert wird,
 *  - weder am Theme-Ordner noch am Template oder an Updates hängt.
 *
 * @package vielbunt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VIELBUNT_FRONTPAGE_OPTION', 'vielbunt_frontpage' );
define( 'VIELBUNT_FRONTPAGE_TILES', 8 );

/* Leerer, vollständiger Wert. Gespeichert wird immer genau diese Form. */
function vielbunt_frontpage_empty() {
	$tiles = array();
	for ( $i = 0; $i < VIELBUNT_FRONTPAGE_TILES; $i++ ) {
		$tiles[] = array( 'label' => '', 'url' => '', 'imgId' => 0, 'imgUrl' => '' );
	}
	return array(
		'hero'       => array(
			'kicker'    => '',
			'title'     => '',
			'lead'      => '',
			'btn1Label' => '',
			'btn1Url'   => '',
			'btn2Label' => '',
			'btn2Url'   => '',
			'bgId'      => 0,
			'bgUrl'     => '',
		),
		'quicklinks' => array(
			'heading' => '',
			'tiles'   => $tiles,
		),
	);
}

/* REST-Schema für wp/v2/settings, muss exakt zu vielbunt_frontpage_empty() passen */
function vielbunt_frontpage_schema() {
	$str  = array( 'type' => 'string' );
	$int  = array( 'type' => 'integer' );
	$hero = array();
	foreach ( vielbunt_frontpage_empty()['hero'] as $key => $default ) {
		$hero[ $key ] = is_int( $default ) ? $int : $str;
	}
	return array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'properties'           => array(
			'hero'       => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => $hero,
			),
			'quicklinks' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'heading' => $str,
					'tiles'   => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'label'  => $str,
								'url'    => $str,
								'imgId'  => $int,
								'imgUrl' => $str,
							),
						),
					),
				),
			),
		),
	);
}

/* Kacheln kommen mal als Liste, mal als Map mit Zahlen-Keys ("0", "1", …).
   Beides geht, raus kommt immer eine Liste mit VIELBUNT_FRONTPAGE_TILES Einträgen. */
function vielbunt_frontpage_pick( $list, $i ) {
	if ( ! is_array( $list ) ) {
		return array();
	}
	if ( isset( $list[ $i ] ) && is_array( $list[ $i ] ) ) {
		return $list[ $i ];
	}
	if ( isset( $list[ (string) $i ] ) && is_array( $list[ (string) $i ] ) ) {
		return $list[ (string) $i ];
	}
	return array();
}

function vielbunt_frontpage_clean_field( $key, $value ) {
	if ( is_array( $value ) || is_object( $value ) ) {
		return '';
	}
	if ( 'Url' === substr( $key, -3 ) || 'url' === $key ) {
		$value = trim( (string) $value );
		// eigene Adressen immer als https://www..., sonst kostet jeder Klick eine Weiterleitung (inc/perf.php)
		return esc_url_raw( function_exists( 'vbperf_own_url' ) ? vbperf_own_url( $value ) : $value );
	}
	if ( 'lead' === $key ) {
		return sanitize_textarea_field( (string) $value );
	}
	return sanitize_text_field( (string) $value );
}

/* Bringt jede Eingabe (REST, Migration, Editor-Vorschau) in die richtige Form */
function vielbunt_frontpage_sanitize( $value ) {
	if ( is_object( $value ) ) {
		$value = json_decode( wp_json_encode( $value ), true );
	}
	$value = is_array( $value ) ? $value : array();
	$out   = vielbunt_frontpage_empty();

	$hero = ( isset( $value['hero'] ) && is_array( $value['hero'] ) ) ? $value['hero'] : array();
	foreach ( $out['hero'] as $key => $default ) {
		if ( ! array_key_exists( $key, $hero ) ) {
			continue;
		}
		$out['hero'][ $key ] = is_int( $default ) ? absint( $hero[ $key ] ) : vielbunt_frontpage_clean_field( $key, $hero[ $key ] );
	}

	$ql = ( isset( $value['quicklinks'] ) && is_array( $value['quicklinks'] ) ) ? $value['quicklinks'] : array();
	if ( isset( $ql['heading'] ) ) {
		$out['quicklinks']['heading'] = vielbunt_frontpage_clean_field( 'heading', $ql['heading'] );
	}
	$tiles = isset( $ql['tiles'] ) ? $ql['tiles'] : array();
	for ( $i = 0; $i < VIELBUNT_FRONTPAGE_TILES; $i++ ) {
		$t = vielbunt_frontpage_pick( $tiles, $i );
		foreach ( $out['quicklinks']['tiles'][ $i ] as $key => $default ) {
			if ( ! array_key_exists( $key, $t ) ) {
				continue;
			}
			$out['quicklinks']['tiles'][ $i ][ $key ] = is_int( $default ) ? absint( $t[ $key ] ) : vielbunt_frontpage_clean_field( $key, $t[ $key ] );
		}
	}
	return $out;
}

function vielbunt_frontpage_register_setting() {
	register_setting(
		'vielbunt_frontpage',
		VIELBUNT_FRONTPAGE_OPTION,
		array(
			'type'              => 'object',
			'label'             => 'Startseite: Hero und Schnellzugriff',
			'description'       => 'Startseite: Hero und Schnellzugriff',
			'default'           => vielbunt_frontpage_empty(),
			'sanitize_callback' => 'vielbunt_frontpage_sanitize',
			'show_in_rest'      => array(
				'name'   => VIELBUNT_FRONTPAGE_OPTION,
				'schema' => vielbunt_frontpage_schema(),
			),
		)
	);
}
add_action( 'init', 'vielbunt_frontpage_register_setting' );

/* Gespeicherter Wert, immer vollständig */
function vielbunt_frontpage_get() {
	return vielbunt_frontpage_sanitize( get_option( VIELBUNT_FRONTPAGE_OPTION, array() ) );
}

/* Das nutzen die Render-Callbacks. Im Editor schickt die Seitenleiste den
   noch nicht gespeicherten Stand als "preview" mit, damit die Vorschau sofort
   stimmt. Eine Vorschau bekommen nur Leute, die die Seite bearbeiten dürfen,
   alle anderen sehen den gespeicherten Stand. */
function vielbunt_frontpage_for_render( $attributes ) {
	if ( ! empty( $attributes['preview'] ) && is_array( $attributes['preview'] )
		&& defined( 'REST_REQUEST' ) && REST_REQUEST
		&& current_user_can( 'edit_theme_options' ) ) {
		return vielbunt_frontpage_sanitize( $attributes['preview'] );
	}
	return vielbunt_frontpage_get();
}

/* Bild-URL: lieber über die Anhang-ID (überlebt neu erzeugte Bildgrößen und
   Domainwechsel), sonst die gespeicherte URL */
function vielbunt_frontpage_image( $id, $url, $size = 'large' ) {
	if ( $id ) {
		$src = wp_get_attachment_image_url( (int) $id, $size );
		if ( $src ) {
			return $src;
		}
	}
	return (string) $url;
}

/* Erster nicht-leerer Wert gewinnt */
function vielbunt_frontpage_first() {
	foreach ( func_get_args() as $v ) {
		if ( is_scalar( $v ) && '' !== (string) $v && 0 !== $v && '0' !== $v ) {
			return $v;
		}
	}
	return '';
}

/* Attribute vom ersten Block namens $name, auch in verschachtelten Blöcken */
function vielbunt_frontpage_find_block_attrs( $blocks, $name ) {
	foreach ( $blocks as $block ) {
		if ( isset( $block['blockName'] ) && $name === $block['blockName'] ) {
			return is_array( $block['attrs'] ) ? $block['attrs'] : array();
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$found = vielbunt_frontpage_find_block_attrs( $block['innerBlocks'], $name );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

/* Einmalige Übernahme aus 2.1.x: Inhalte aus dem angepassten front-page-
   Template (Block-Attribute) und aus der alten Option vielbunt_block_settings.
   Block-Attribute gewinnen, weil die Startseite genau die angezeigt hat.
   Die alte Option bleibt als Backup unangetastet liegen. */
function vielbunt_frontpage_migrate() {
	if ( false !== get_option( VIELBUNT_FRONTPAGE_OPTION, false ) ) {
		return;
	}

	$hero_attrs = array();
	$ql_attrs   = array();
	$template   = function_exists( 'get_block_template' ) ? get_block_template( get_stylesheet() . '//front-page' ) : null;
	if ( $template && ! empty( $template->content ) ) {
		$blocks     = parse_blocks( $template->content );
		$hero_attrs = (array) vielbunt_frontpage_find_block_attrs( $blocks, 'vielbunt/hero' );
		$ql_attrs   = (array) vielbunt_frontpage_find_block_attrs( $blocks, 'vielbunt/quicklinks' );
	}
	$old      = (array) get_option( 'vielbunt_block_settings', array() );
	$hero_old = isset( $old['hero'] ) && is_array( $old['hero'] ) ? $old['hero'] : array();
	$ql_old   = isset( $old['quicklinks'] ) && is_array( $old['quicklinks'] ) ? $old['quicklinks'] : array();

	// alte Attributnamen => neue
	$map = array(
		'kicker'    => 'heroKicker',
		'title'     => 'heroTitle',
		'lead'      => 'heroLead',
		'btn1Label' => 'btnSolidLabel',
		'btn1Url'   => 'btnSolidUrl',
		'btn2Label' => 'btnGhostLabel',
		'btn2Url'   => 'btnGhostUrl',
		'bgId'      => 'bgId',
		'bgUrl'     => 'bgUrl',
	);
	$new = vielbunt_frontpage_empty();
	foreach ( $map as $key => $legacy ) {
		$new['hero'][ $key ] = vielbunt_frontpage_first(
			isset( $hero_attrs[ $legacy ] ) ? $hero_attrs[ $legacy ] : '',
			isset( $hero_old[ $legacy ] ) ? $hero_old[ $legacy ] : ''
		);
	}

	$heading = vielbunt_frontpage_first(
		isset( $ql_attrs['heading'] ) ? $ql_attrs['heading'] : '',
		isset( $ql_old['heading'] ) ? $ql_old['heading'] : ''
	);
	$new['quicklinks']['heading'] = ( 'Schnellzugriff' === $heading ) ? '' : $heading;

	$attr_tiles  = isset( $ql_attrs['tiles'] ) ? $ql_attrs['tiles'] : array();
	$old_tiles   = isset( $ql_old['tiles'] ) ? $ql_old['tiles'] : array();
	$attr_images = isset( $ql_attrs['images'] ) ? $ql_attrs['images'] : array();
	$old_images  = isset( $ql_old['images'] ) ? $ql_old['images'] : array();
	for ( $i = 0; $i < VIELBUNT_FRONTPAGE_TILES; $i++ ) {
		$at = vielbunt_frontpage_pick( $attr_tiles, $i );
		$ot = vielbunt_frontpage_pick( $old_tiles, $i );
		$ai = vielbunt_frontpage_pick( $attr_images, $i );
		$oi = vielbunt_frontpage_pick( $old_images, $i );
		// Bild immer als Paar übernehmen, sonst kommen ID und URL evtl. aus verschiedenen Quellen
		$img = ! empty( $ai['url'] ) || ! empty( $ai['id'] ) ? $ai : $oi;

		$new['quicklinks']['tiles'][ $i ] = array(
			'label'  => vielbunt_frontpage_first( isset( $at['label'] ) ? $at['label'] : '', isset( $ot['label'] ) ? $ot['label'] : '' ),
			'url'    => vielbunt_frontpage_first( isset( $at['url'] ) ? $at['url'] : '', isset( $ot['url'] ) ? $ot['url'] : '' ),
			'imgId'  => isset( $img['id'] ) ? (int) $img['id'] : 0,
			'imgUrl' => isset( $img['url'] ) ? (string) $img['url'] : '',
		);
	}

	update_option( VIELBUNT_FRONTPAGE_OPTION, vielbunt_frontpage_sanitize( $new ) );

	if ( $template && 'custom' === $template->source && ! empty( $template->wp_id ) ) {
		vielbunt_frontpage_clean_template( (int) $template->wp_id, $template->content );
	}
}
add_action( 'init', 'vielbunt_frontpage_migrate', 20 );

/* Die alte front-page.html hatte normale HTML-Kommentare ("<!-- HERO … -->")
   in der <main>-Gruppe. Deswegen hat der Editor die ganze Gruppe als
   „ungültiger Inhalt" markiert. Wir nehmen genau diese alten Kommentare
   einmalig aus dem angepassten Template raus, sonst nichts. Direkt per
   $wpdb, damit kein Inhaltsfilter am Template rumfummelt (das kann auch bei
   einem normalen Seitenaufruf ohne Login laufen). */
function vielbunt_frontpage_clean_template( $post_id, $content ) {
	$cleaned = preg_replace(
		'/[ \t]*<!--\s*(?:HERO \(|Die Spendenkampagne \(Zielmesser|SCHNELLZUGRIFF|AKTUELLES\s|CTA-BAND|AUS DEM VEREIN)(?:(?!-->|<!--)[\s\S])*-->[ \t]*\n?/u',
		'',
		$content
	);
	if ( ! is_string( $cleaned ) || $cleaned === $content ) {
		return;
	}
	global $wpdb;
	$wpdb->update( $wpdb->posts, array( 'post_content' => $cleaned ), array( 'ID' => $post_id ) );
	clean_post_cache( $post_id );
}

/* Standardwerte für die Editor-Seitenleiste (Platzhalter), damit sie nur einmal in PHP stehen */
function vielbunt_frontpage_editor_data() {
	$tiles = array();
	foreach ( vielbunt_default_tiles() as $t ) {
		$tiles[] = array( 'label' => $t[0], 'url' => $t[1] );
	}
	return array(
		'option'   => VIELBUNT_FRONTPAGE_OPTION,
		'defaults' => array(
			'hero'    => vielbunt_hero_defaults(),
			'heading' => 'Schnellzugriff',
			'tiles'   => $tiles,
		),
	);
}
