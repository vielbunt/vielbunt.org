<?php
/**
 * Favicon und App-Icons aus dem Theme (assets/icons).
 *
 * Ersetzt das "Website-Icon" aus dem Customizer, damit die Icons mit dem
 * Theme versioniert sind und überall scharf aussehen: favicon.ico (48 px) und
 * 32 px für Browser-Tabs, 192 px für Android und Google, 180 px randlos für
 * den iPhone-Homescreen (iOS rundet selbst ab), 512 px für alles Größere.
 *
 * @package vielbunt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Version im Link = Prüfsumme der Datei, nicht die Theme-Version. So bleibt
   die URL stabil, bis sich das Icon wirklich ändert. Google holt Favicons nur
   selten neu und mag ständig wechselnde Adressen nicht. */
function vielbunt_icon_url( $file ) {
	static $hash = array();
	if ( ! isset( $hash[ $file ] ) ) {
		$path           = get_stylesheet_directory() . '/assets/icons/' . $file;
		$hash[ $file ] = is_readable( $path ) ? substr( md5_file( $path ), 0, 8 ) : '0';
	}
	return get_stylesheet_directory_uri() . '/assets/icons/' . $file . '?v=' . $hash[ $file ];
}

/* bewusst ohne type="image/png": ShortPixel schreibt die URLs um und hat dabei
   das Anführungszeichen vor dem type-Attribut verschluckt. data-spai-excluded
   hält ShortPixel ganz raus, sonst macht es aus jedem Icon ein 180-px-WebP */
function vielbunt_icon_tags() {
	printf( '<link data-spai-excluded="true" rel="icon" sizes="48x48" href="%s" />' . "\n", esc_url( vielbunt_icon_url( 'favicon.ico' ) ) );
	printf( '<link data-spai-excluded="true" rel="icon" sizes="32x32" href="%s" />' . "\n", esc_url( vielbunt_icon_url( 'icon-32.png' ) ) );
	printf( '<link data-spai-excluded="true" rel="icon" sizes="192x192" href="%s" />' . "\n", esc_url( vielbunt_icon_url( 'icon-192.png' ) ) );
	printf( '<link data-spai-excluded="true" rel="apple-touch-icon" href="%s" />' . "\n", esc_url( vielbunt_icon_url( 'apple-touch-icon.png' ) ) );
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
