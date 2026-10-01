<?php
/**
 * Favicon und App-Icons aus dem Theme (assets/icons).
 *
 * Ersetzt das "Website-Icon" aus dem Customizer, damit die Icons mit dem
 * Theme versioniert sind und überall scharf aussehen: favicon.ico und 32 px
 * für Browser-Tabs, 192 px für Android, 180 px randlos für den iPhone-
 * Homescreen (iOS rundet selbst ab), 512 px für alles Größere.
 *
 * @package vielbunt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vielbunt_icon_url( $file ) {
	return get_stylesheet_directory_uri() . '/assets/icons/' . $file . '?v=' . rawurlencode( (string) wp_get_theme()->get( 'Version' ) );
}

function vielbunt_icon_tags() {
	printf( '<link rel="icon" href="%s" sizes="48x48" />' . "\n", esc_url( vielbunt_icon_url( 'favicon.ico' ) ) );
	printf( '<link rel="icon" href="%s" type="image/png" sizes="32x32" />' . "\n", esc_url( vielbunt_icon_url( 'icon-32.png' ) ) );
	printf( '<link rel="icon" href="%s" type="image/png" sizes="192x192" />' . "\n", esc_url( vielbunt_icon_url( 'icon-192.png' ) ) );
	printf( '<link rel="apple-touch-icon" href="%s" />' . "\n", esc_url( vielbunt_icon_url( 'apple-touch-icon.png' ) ) );
	echo '<meta name="theme-color" content="#ffffff" />' . "\n";
}

/* WordPress' eigene Icon-Ausgabe raus, unsere rein (Frontend, Backend, Login) */
function vielbunt_replace_site_icon() {
	foreach ( array( 'wp_head', 'admin_head', 'login_head' ) as $hook ) {
		remove_action( $hook, 'wp_site_icon', 99 );
		add_action( $hook, 'vielbunt_icon_tags', 99 );
	}
}
add_action( 'init', 'vielbunt_replace_site_icon' );

/* auch /favicon.ico und alles, was get_site_icon_url() fragt, bekommt unser Icon */
function vielbunt_site_icon_url( $url, $size ) {
	if ( $size <= 32 ) {
		return vielbunt_icon_url( 'icon-32.png' );
	}
	if ( $size <= 192 ) {
		return vielbunt_icon_url( 'icon-192.png' );
	}
	return vielbunt_icon_url( 'icon-512.png' );
}
add_filter( 'get_site_icon_url', 'vielbunt_site_icon_url', 10, 2 );
