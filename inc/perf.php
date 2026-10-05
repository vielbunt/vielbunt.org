<?php
/**
 * Kleine Stellschrauben für Ladezeit und Seitencache (gleiche Datei in
 * csd-darmstadt.de und vielbunt.org, bitte in beiden Repos gleich halten).
 *
 * Alles hier ist so gebaut, dass es ohne das jeweilige Plugin einfach nix tut
 * (WP-Optimize, ActivityPub usw.), damit der Playground-Test in der Action
 * und lokale Kopien ohne Plugins weiter laufen.
 *
 * @package vielbunt-themes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Bildgröße für Kacheln und Karten ---------- */

/* 600 x 750 ohne Beschneiden: passt für die 4:5-Sharepics in den Kacheln auf
   Handys mit 2- bis 3-facher Pixeldichte. Neue Uploads bekommen sie von selbst,
   ältere Beitragsbilder ergänzt ein einmaliger Schritt (vbperf_once_card_sizes). */
function vbperf_image_sizes() {
	add_image_size( 'vb-card', 600, 750, false );
}
add_action( 'after_setup_theme', 'vbperf_image_sizes' );

/* Zwischengrößen (thumbnail, medium, large, vb-card ...) als WebP speichern,
   nicht mehr als JPG/PNG. Gerade die PNG-Sharepics werden so von ~900 KB auf
   unter 100 KB kleiner. Das hochgeladene Original bleibt wie es ist. Nur wenn
   der Server WebP schreiben kann. Bestehende Bilder rechnet ein einmaliger
   Schritt im Hintergrund um (vbperf_once_webp in inc/once.php). */
function vbperf_webp_subsizes( $formats ) {
	static $ok = null, $busy = false;
	if ( null === $ok ) {
		if ( $busy ) { // wp_image_editor_supports() fragt den Filter selbst nochmal ab
			return $formats;
		}
		$busy = true;
		$ok   = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
		$busy = false;
	}
	if ( $ok ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
	}
	return $formats;
}
add_filter( 'image_editor_output_format', 'vbperf_webp_subsizes' );

/* Kachelbild als <img> mit srcset statt einer festen 1024er-URL.
   Gibt '' zurück, wenn es kein Beitragsbild gibt (dann greift der alte Weg). */
function vbperf_card_image( $post, $alt, $sizes ) {
	$id = get_post_thumbnail_id( $post );
	if ( ! $id ) {
		return '';
	}
	return (string) wp_get_attachment_image(
		$id,
		'vb-card',
		false,
		array(
			'alt'      => $alt,
			'loading'  => 'lazy',
			'decoding' => 'async',
			'sizes'    => $sizes,
		)
	);
}

/* Beitragsbild im Kopf von Beiträgen und Seiten als <img> statt als
   Hintergrund in voller Größe. Gleiche Größe und gleiches srcset wie das Bild
   im Text (der Editor fügt "large" ein), dann lädt der Browser die Datei nur
   einmal. Ist das erste Bild im Text dasselbe, ist es meist das größte
   sichtbare Element, also hohe Priorität, sonst niedrig. */
function vbperf_post_hero_media( $post_id, $fallback ) {
	$tid = (int) get_post_thumbnail_id( $post_id );
	if ( $tid ) {
		$first = function_exists( 'vbthumb_first_image_id' ) ? (int) vbthumb_first_image_id( get_post_field( 'post_content', $post_id ) ) : 0;
		$img   = wp_get_attachment_image(
			$tid,
			'large',
			false,
			array(
				'class'         => 'vb-hero__media',
				'alt'           => '',
				'aria-hidden'   => 'true',
				'loading'       => false,
				'decoding'      => 'async',
				'fetchpriority' => ( ! $first || $first === $tid ) ? 'high' : 'low',
			)
		);
		if ( $img ) {
			return $img;
		}
	}
	return '<div class="vb-hero__media" aria-hidden="true" style="background-image:' . esc_attr( $fallback ) . '"></div>';
}

/* ---------- Emojis ---------- */

/* Der Emoji-Polyfill lädt bei jedem Seitenaufruf ein Skript und danach Bilder
   von s.w.org, obwohl alle aktuellen Geräte Emojis selbst können. Im Backend
   bleibt alles wie es ist. */
function vbperf_no_emoji() {
	foreach ( array( 'wp_head', 'wp_footer', 'wp_print_footer_scripts', 'embed_head' ) as $hook ) {
		$prio = has_action( $hook, 'print_emoji_detection_script' );
		if ( false !== $prio ) {
			remove_action( $hook, 'print_emoji_detection_script', $prio );
		}
	}
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_action( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}
add_action( 'init', 'vbperf_no_emoji' );

/* ---------- Vorladen beim Drüberfahren ---------- */

/* Core lädt Folgeseiten erst beim Klick vor. "moderate" holt das HTML schon,
   wenn der Mauszeiger kurz auf einem Link liegt. Nur prefetch (kein JS, keine
   Bilder), das kommt eh aus dem Seitencache. Für angemeldete Nutzer*innen
   schaltet Core das selbst ab (dann ist $config null). */
function vbperf_speculation( $config ) {
	if ( ! is_array( $config ) ) {
		return $config;
	}
	return array(
		'mode'      => 'prefetch',
		'eagerness' => 'moderate',
	);
}
add_filter( 'wp_speculation_rules_configuration', 'vbperf_speculation' );

/* ---------- ActivityPub ---------- */

/* Der Reaktionen-Block unter Beiträgen gibt ohne Reaktionen nur einen
   HTML-Kommentar aus, lädt aber trotzdem CSS und sechs Skripte. Mit einem
   leeren String räumt WordPress (seit 6.9) die Dateien selbst wieder ab. */
function vbperf_empty_reactions( $html ) {
	return false === strpos( $html, 'wp-block-activitypub-reactions' ) ? '' : $html;
}
add_filter( 'render_block_activitypub/reactions', 'vbperf_empty_reactions' );

/* ---------- Weiterleitungen nie als Seite cachen ---------- */

/* Ist schon eine Weiterleitung (3xx mit Location) unterwegs, sofort aufhören.
   Sonst speichert WP-Optimize die leere Antwort als normale Seite und die
   Weiterleitung ist kaputt (so passiert bei /sepa/, /youtube/ usw.). */
function vbperf_redirect_is_pending() {
	$code = function_exists( 'http_response_code' ) ? http_response_code() : false;
	if ( ! $code || $code < 300 || $code > 399 ) {
		return false;
	}
	foreach ( headers_list() as $h ) {
		if ( 0 === stripos( $h, 'Location:' ) ) {
			return true;
		}
	}
	return false;
}

function vbperf_stop_after_redirect() {
	if ( vbperf_redirect_is_pending() ) {
		exit;
	}
}
add_action( 'template_redirect', 'vbperf_stop_after_redirect', 99 );

/* zweite Absicherung ganz am Ende, falls die Weiterleitung erst später kommt */
function vbperf_no_cache_for_redirects( $can_cache ) {
	return vbperf_redirect_is_pending() ? false : $can_cache;
}
add_filter( 'wpo_can_cache_page', 'vbperf_no_cache_for_redirects' );

/* ---------- WP-Optimize: Seitencache sinnvoll leeren ---------- */

/* WP-Optimize löscht beim Aufräumen ganze Ordner wie /2025/ oder /news/ nach
   dem Datum des Ordners, nicht nach dem Alter der Seiten darin. Damit war der
   nächtliche Vorlade-Lauf jeden Tag wieder weg. Abgelaufene Dateien löscht
   WP-Optimize beim Ausliefern und beim Vorladen sowieso selbst.
   (Wieder rausnehmen, falls WP-Optimize das irgendwann selbst richtig macht.) */
function vbperf_keep_wpo_tree() {
	if ( class_exists( 'WPO_Page_Cache' ) && is_callable( array( 'WPO_Page_Cache', 'instance' ) ) ) {
		remove_action( 'wpo_purge_old_cache', array( WPO_Page_Cache::instance(), 'purge_old' ) );
	}
}
add_action( 'init', 'vbperf_keep_wpo_tree', 20 );

/* eine Adresse aus dem Seitencache werfen, ohne dass ein Fehler die Seite mitreißt */
function vbperf_purge_url( $url, $recursive = false ) {
	if ( ! $url || ! class_exists( 'WPO_Page_Cache' ) || ! is_callable( array( 'WPO_Page_Cache', 'delete_cache_by_url' ) ) ) {
		return;
	}
	try {
		WPO_Page_Cache::delete_cache_by_url( $url, $recursive );
	} catch ( \Throwable $e ) {
		return;
	}
}

/* Startseite und Beitragsseite hängen am heutigen Datum (kommende Termine,
   "Kommende Termine"-Ansicht). Kurz nach Mitternacht einmal neu bauen lassen,
   sonst stehen vergangene Termine bis zum Ablauf des Caches da. */
function vbperf_schedule_midnight() {
	if ( wp_next_scheduled( 'vbperf_midnight_refresh' ) ) {
		return;
	}
	$next = new DateTime( 'tomorrow 00:05', wp_timezone() );
	wp_schedule_event( $next->getTimestamp(), 'daily', 'vbperf_midnight_refresh' );
}
add_action( 'init', 'vbperf_schedule_midnight', 30 );

function vbperf_midnight_refresh() {
	vbperf_purge_url( home_url( '/' ) );
	$blog = (int) get_option( 'page_for_posts' );
	if ( $blog ) {
		vbperf_purge_url( get_permalink( $blog ) );
	}
	vbperf_purge_listing_pages();
}
add_action( 'vbperf_midnight_refresh', 'vbperf_midnight_refresh' );

/* Seiten mit eingebauten Beitragslisten (Neueste Beiträge, Abfrage-Schleife).
   WP-Optimize leert beim Veröffentlichen nur Startseite, Beitragsseite und
   Archive, solche Seiten bleiben sonst bis zum Ablauf alt. */
function vbperf_listing_page_ids() {
	$ids = get_transient( 'vbperf_listing_pages' );
	if ( is_array( $ids ) ) {
		return $ids;
	}
	global $wpdb;
	$ids = array_map(
		'intval',
		$wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'
			 AND ( post_content LIKE '%<!-- wp:latest-posts%' OR post_content LIKE '%<!-- wp:query %' OR post_content LIKE '%<!-- wp:query -->%' )
			 LIMIT 100"
		)
	);
	set_transient( 'vbperf_listing_pages', $ids, DAY_IN_SECONDS );
	return $ids;
}

function vbperf_purge_listing_pages() {
	foreach ( vbperf_listing_page_ids() as $id ) {
		vbperf_purge_url( get_permalink( $id ) );
	}
}

/* Beitrag erscheint, ändert sich oder verschwindet: Listen-Seiten neu bauen */
function vbperf_on_post_status( $new, $old, $post ) {
	if ( 'post' !== $post->post_type || ( 'publish' !== $new && 'publish' !== $old ) ) {
		return;
	}
	vbperf_purge_listing_pages();
}
add_action( 'transition_post_status', 'vbperf_on_post_status', 10, 3 );

/* Seite gespeichert: die Liste der Listen-Seiten kann sich geändert haben */
function vbperf_forget_listing_pages( $post_id, $post ) {
	if ( 'page' === $post->post_type ) {
		delete_transient( 'vbperf_listing_pages' );
	}
}
add_action( 'save_post', 'vbperf_forget_listing_pages', 10, 2 );

/* ---------- Lightbox nur, wo es was zu vergrößern gibt ---------- */

/* Block-Themes rendern das Template vor wp_head, deshalb können wir hier noch
   nachladen. Die Lightbox kommt nur auf Seiten, deren Inhalt Links auf
   Bilddateien hat. */
function vbperf_lightbox_on_demand( $html ) {
	if ( is_admin() || wp_script_is( 'vb-lightbox', 'enqueued' ) ) {
		return $html;
	}
	if ( preg_match( '/<a\s[^>]*href=["\'][^"\']+\.(?:jpe?g|png|gif|webp|avif|bmp)(?:[?#][^"\']*)?["\']/i', $html ) ) {
		wp_enqueue_script(
			'vb-lightbox',
			get_stylesheet_directory_uri() . '/assets/lightbox.js',
			array(),
			wp_get_theme()->get( 'Version' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
	return $html;
}
add_filter( 'render_block_core/post-content', 'vbperf_lightbox_on_demand' );

/* Alte [gallery]-Shortcodes verlinken auf Anhangseiten (eine ganze Seite pro
   Foto). Stattdessen direkt aufs Bild, dann übernimmt die Lightbox. */
function vbperf_gallery_link_file( $out ) {
	if ( isset( $out['link'] ) && 'none' !== $out['link'] ) {
		$out['link'] = 'file';
	}
	return $out;
}
add_filter( 'shortcode_atts_gallery', 'vbperf_gallery_link_file' );

/* ... und zwar auf die Größe "large", die alten Originale sind 0,5 bis 0,8 MB */
function vbperf_gallery_link_large( $attributes, $id ) {
	if ( isset( $attributes['href'] ) && wp_get_attachment_url( $id ) === $attributes['href'] ) {
		$large = wp_get_attachment_image_url( $id, 'large' );
		if ( $large ) {
			$attributes['href'] = $large;
		}
	}
	return $attributes;
}
add_filter( 'wp_get_attachment_link_attributes', 'vbperf_gallery_link_large', 10, 2 );

/* ---------- eigene Links ohne Umweg ---------- */

/* Links im Inhalt auf http:// oder ohne www kosten jeweils eine Weiterleitung.
   Nur die eigene Domain, nur in href, der gespeicherte Inhalt bleibt wie er ist. */
function vbperf_own_links( $html ) {
	$home = untrailingslashit( home_url() );
	$host = preg_replace( '#^www\.#i', '', (string) wp_parse_url( $home, PHP_URL_HOST ) );
	if ( '' === $host || false === stripos( $html, $host ) ) {
		return $html;
	}
	return preg_replace(
		'#(href=["\'])https?://(?:www\.)?' . preg_quote( $host, '#' ) . '(?=[/"\'?\#])#i',
		'$1' . $home,
		$html
	);
}
add_filter( 'the_content', 'vbperf_own_links', 20 );

/* gleiche Regel für die Adressfelder der Startseite (inc/frontpage.php) */
function vbperf_own_url( $url ) {
	$home = untrailingslashit( home_url() );
	$host = preg_replace( '#^www\.#i', '', (string) wp_parse_url( $home, PHP_URL_HOST ) );
	if ( '' === $host ) {
		return $url;
	}
	return preg_replace( '#^https?://(?:www\.)?' . preg_quote( $host, '#' ) . '(?=[/?\#]|$)#i', $home, (string) $url );
}
