<?php
/**
 * Beschreibung und Link-Vorschau (Open Graph) für Suchmaschinen, WhatsApp,
 * Signal, Instagram & Co.
 *
 * Beschreibung: Startseite = Leadtext aus dem Hero, Beiträge und Seiten =
 * Auszug oder Textanfang, sonst der Untertitel der Website.
 * Vorschaubild: Beitragsbild, sonst erstes Bild im Beitrag, sonst
 * assets/share.png (Logo auf Weiß mit Regenbogenstreifen).
 *
 * Kommt mal ein SEO- oder OG-Plugin dazu, halten wir uns automatisch raus,
 * damit nichts doppelt im <head> steht.
 *
 * @package vielbunt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vielbunt_meta_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

function vielbunt_meta_og_plugin_active() {
	return vielbunt_meta_seo_plugin_active() || class_exists( 'iworks_opengraph' ) || class_exists( 'WebDados_FB' );
}

function vielbunt_meta_description() {
	$text = '';
	if ( is_front_page() ) {
		$hero = vielbunt_frontpage_get()['hero'];
		$text = '' !== $hero['lead'] ? $hero['lead'] : vielbunt_hero_defaults()['lead'];
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$text = $post->post_excerpt ? $post->post_excerpt : strip_shortcodes( $post->post_content );
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = term_description();
	}
	if ( '' === trim( wp_strip_all_tags( (string) $text ) ) ) {
		$text = get_bloginfo( 'description' );
	}
	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
	// Suchmaschinen zeigen gut 150 Zeichen, danach wird abgeschnitten
	if ( mb_strlen( $text ) > 160 ) {
		$text = rtrim( mb_substr( $text, 0, 157 ), " ,.;:-" ) . '…';
	}
	return $text;
}

/* Bild für die Vorschau: array( url, breite, höhe ) */
function vielbunt_meta_image() {
	if ( is_singular() ) {
		$id = get_post_thumbnail_id( get_queried_object_id() );
		if ( $id ) {
			$src = wp_get_attachment_image_src( $id, 'large' );
			// Zwischengrößen sind jetzt WebP, für die Vorschau in Messengern lieber das Original
			if ( $src && preg_match( '/\.webp$/i', $src[0] ) ) {
				$orig = wp_get_original_image_url( $id );
				if ( $orig && ! preg_match( '/\.webp$/i', $orig ) ) {
					return array( $orig, 0, 0 );
				}
			}
			if ( $src ) {
				return array( $src[0], (int) $src[1], (int) $src[2] );
			}
		}
		$post = get_queried_object();
		if ( $post instanceof WP_Post && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $post->post_content, $m ) ) {
			return array( $m[1], 0, 0 );
		}
	}
	return array( get_stylesheet_directory_uri() . '/assets/share.png', 1200, 630 );
}

function vielbunt_meta_head() {
	if ( is_404() ) {
		return;
	}
	$desc = vielbunt_meta_description();
	if ( ! vielbunt_meta_seo_plugin_active() && ! is_search() && '' !== $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
	}
	if ( vielbunt_meta_og_plugin_active() ) {
		return;
	}

	$title = is_front_page() ? get_bloginfo( 'name' ) : wp_get_document_title();
	global $wp;
	$url   = is_singular() ? get_permalink() : home_url( $wp->request ? user_trailingslashit( $wp->request ) : '/' );
	$img   = vielbunt_meta_image();

	$tags = array(
		'og:type'        => is_singular( 'post' ) ? 'article' : 'website',
		'og:site_name'   => get_bloginfo( 'name' ),
		'og:locale'      => 'de_DE',
		'og:title'       => $title,
		'og:description' => $desc,
		'og:url'         => $url,
		'og:image'       => $img[0],
	);
	if ( $img[1] && $img[2] ) {
		$tags['og:image:width']  = $img[1];
		$tags['og:image:height'] = $img[2];
	}
	foreach ( $tags as $prop => $val ) {
		if ( '' === (string) $val ) {
			continue;
		}
		$attr = in_array( $prop, array( 'og:url', 'og:image' ), true ) ? esc_url( $val ) : esc_attr( $val );
		echo '<meta property="' . esc_attr( $prop ) . '" content="' . $attr . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- oben escaped
	}
	echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
}
add_action( 'wp_head', 'vielbunt_meta_head', 1 );
