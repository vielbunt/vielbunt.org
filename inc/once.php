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
