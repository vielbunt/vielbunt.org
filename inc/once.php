<?php
/**
 * Einmalige Server-Schritte nach Updates (gleiche Datei in csd-darmstadt.de
 * und vielbunt.org). Ausgeführt von Vielbunt_Theme_Deploy::run_once(),
 * Ergebnis unter Design > Theme-Updates.
 *
 * @package vielbunt-themes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Das alte FancyBox-Plugin (Kevin Sylvestre, zuletzt getestet mit WP 3.5)
   abschalten. Die Lightbox macht jetzt das Theme selbst (assets/lightbox.js),
   damit fallen jQuery-Fancybox und Easing auf jeder Seite weg. */
function vielbunt_once_disable_fancybox() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$hits = array();
	foreach ( array_keys( get_plugins() ) as $file ) {
		if ( 0 === strpos( $file, 'fancy-box/' ) && is_plugin_active( $file ) ) {
			deactivate_plugins( $file );
			$hits[] = $file;
		}
	}
	return $hits ? 'FancyBox-Plugin deaktiviert (' . implode( ', ', $hits ) . '), nicht gelöscht' : 'FancyBox-Plugin war nicht aktiv';
}

/* Autoptimize soll keine Google Fonts mehr anfassen oder vorladen. Die Schrift
   kommt aus dem Theme, jede Verbindung zu fonts.gstatic.com wäre nur noch
   Datenschutz-Risiko ohne Nutzen. "2" = "Google Fonts entfernen". */
function vielbunt_once_autoptimize_no_gfonts() {
	$opt = get_option( 'autoptimize_extra_settings' );
	if ( ! is_array( $opt ) ) {
		return 'Autoptimize-Extra-Einstellungen nicht gefunden, nichts geändert';
	}
	$before = isset( $opt['autoptimize_extra_radio_field_4'] ) ? $opt['autoptimize_extra_radio_field_4'] : '?';
	$opt['autoptimize_extra_radio_field_4'] = '2';

	$removed = array();
	if ( ! empty( $opt['autoptimize_extra_text_field_2'] ) ) {
		$keep = array();
		foreach ( explode( ',', (string) $opt['autoptimize_extra_text_field_2'] ) as $domain ) {
			$domain = trim( $domain );
			if ( '' === $domain ) {
				continue;
			}
			if ( false !== stripos( $domain, 'fonts.gstatic.com' ) || false !== stripos( $domain, 'fonts.googleapis.com' ) ) {
				$removed[] = $domain;
				continue;
			}
			$keep[] = $domain;
		}
		$opt['autoptimize_extra_text_field_2'] = implode( ', ', $keep );
	}
	update_option( 'autoptimize_extra_settings', $opt );

	return 'Google Fonts: Einstellung ' . $before . ' auf 2 (entfernen)'
		. ( $removed ? ', Preconnect entfernt: ' . implode( ', ', $removed ) : '' );
}

/* Untermenü in allen Navigationen bearbeiten. $label = aktuelle Beschriftung
   des Untermenüs, $edit bekommt den Block (Array aus parse_blocks) und gibt
   ihn verändert zurück. Gespeichert wird direkt per $wpdb (kein Filter fasst
   den Inhalt an), der alte Inhalt landet als Backup in $backup_option. */
function vielbunt_once_edit_submenu( $label, $edit, $backup_option ) {
	global $wpdb;
	$navs    = get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'posts_per_page' => -1 ) );
	$changed = array();
	$backup  = array();
	$walk    = function ( $blocks ) use ( &$walk, $label, $edit ) {
		foreach ( $blocks as $i => $block ) {
			$name = isset( $block['attrs']['label'] ) ? trim( wp_strip_all_tags( html_entity_decode( $block['attrs']['label'], ENT_QUOTES, 'UTF-8' ) ) ) : '';
			if ( 'core/navigation-submenu' === $block['blockName'] && $name === $label ) {
				$blocks[ $i ] = $edit( $block );
			} elseif ( ! empty( $block['innerBlocks'] ) ) {
				$blocks[ $i ]['innerBlocks'] = $walk( $block['innerBlocks'] );
			}
		}
		return $blocks;
	};
	foreach ( $navs as $nav ) {
		$content = serialize_blocks( $walk( parse_blocks( $nav->post_content ) ) );
		if ( $content !== serialize_blocks( parse_blocks( $nav->post_content ) ) ) {
			$backup[ $nav->ID ] = $nav->post_content;
			$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $nav->ID ) );
			clean_post_cache( $nav->ID );
			$changed[] = $nav->post_title;
		}
	}
	if ( $backup ) {
		update_option( $backup_option, $backup, false );
	}
	return $changed;
}

/* Hilfsfunktion: Menüpunkt-Block bauen */
function vielbunt_once_nav_link( $label, $url, $page_id = 0 ) {
	$attrs = array( 'label' => $label, 'url' => $url, 'kind' => $page_id ? 'post-type' : 'custom', 'type' => $page_id ? 'page' : 'custom' );
	if ( $page_id ) {
		$attrs['id'] = (int) $page_id;
	}
	return array( 'blockName' => 'core/navigation-link', 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
}

/* innere Blöcke ersetzen und innerContent passend dazu setzen */
function vielbunt_once_set_children( $block, $children ) {
	$block['innerBlocks']  = array_values( $children );
	$block['innerContent'] = array_fill( 0, count( $children ), null );
	$block['innerHTML']    = '';
	return $block;
}
