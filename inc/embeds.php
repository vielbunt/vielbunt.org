<?php
/**
 * Videos, Karten und Formulare von Google, YouTube und Vimeo erst auf Klick
 * laden (gleiche Datei in csd-darmstadt.de und vielbunt.org).
 *
 * Statt des Players steht erst eine schlichte Fläche mit "Video abspielen"
 * bzw. "Karte laden" im iframe (srcdoc). Solange srcdoc gesetzt ist, lädt der
 * Browser nichts von der eigentlichen Adresse. Der Link darin öffnet dann den
 * echten Player im selben Rahmen. Spart auf /videos/ (CSD) mehrere MB und es
 * geht vor dem Klick keine Anfrage an Google oder Vimeo raus.
 *
 * Google Kalender bleibt absichtlich direkt sichtbar, auf /kalender/ ist er
 * der eigentliche Inhalt der Seite.
 *
 * @package vielbunt-themes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Art des eingebetteten Inhalts anhand der Adresse, '' = nicht anfassen */
function vbembed_kind( $src ) {
	if ( preg_match( '#^https?://(?:www\.)?youtube(?:-nocookie)?\.com/embed/#i', $src ) || preg_match( '#^https?://player\.vimeo\.com/video/#i', $src ) ) {
		return 'video';
	}
	if ( preg_match( '#^https?://(?:www\.)?google\.[a-z.]+/maps|^https?://maps\.google\.[a-z.]+/#i', $src ) ) {
		return 'map';
	}
	if ( preg_match( '#^https?://docs\.google\.com/forms/#i', $src ) ) {
		return 'form';
	}
	return '';
}

function vbembed_facade( $html ) {
	if ( is_admin() || is_feed() || wp_is_json_request() || false === stripos( $html, '<iframe' ) ) {
		return $html;
	}
	$labels = array(
		'video' => 'Video abspielen',
		'map'   => 'Karte laden',
		'form'  => 'Formular laden',
	);
	$p = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag( 'IFRAME' ) ) {
		$src = $p->get_attribute( 'src' );
		if ( ! is_string( $src ) || null !== $p->get_attribute( 'srcdoc' ) ) {
			continue;
		}
		$kind = vbembed_kind( $src );
		if ( '' === $kind ) {
			continue;
		}
		$url = $src;
		if ( 'video' === $kind ) {
			// nach dem Klick soll das Video gleich laufen, nicht nochmal getippt werden müssen
			$url   = preg_replace( '#^https?://(?:www\.)?youtube\.com/#i', 'https://www.youtube-nocookie.com/', $url );
			$url   = add_query_arg( 'autoplay', '1', $url );
			$allow = (string) $p->get_attribute( 'allow' );
			if ( false === stripos( $allow, 'autoplay' ) ) {
				$p->set_attribute( 'allow', trim( 'autoplay; fullscreen; picture-in-picture; encrypted-media; ' . $allow, '; ' ) );
			}
		}
		$title = $p->get_attribute( 'title' );
		$label = $labels[ $kind ];
		$host  = (string) wp_parse_url( $src, PHP_URL_HOST );
		$doc   = '<!doctype html><meta charset="utf-8"><style>html,body{margin:0;height:100%}a{display:flex;flex-direction:column;gap:.4em;align-items:center;justify-content:center;height:100%;padding:1em;box-sizing:border-box;background:#2e312f;color:#fff;font:700 18px/1.3 system-ui,sans-serif;text-align:center;text-decoration:none}b{font-size:2.6em;line-height:1}small{font-weight:400;font-size:13px;opacity:.8;max-width:30em}a:hover b,a:focus b{transform:scale(1.1)}</style>'
			. '<a href="' . esc_url( $url ) . '">' . ( 'video' === $kind ? '<b>&#9654;</b>' : '' ) . esc_html( $label )
			. '<small>Erst nach dem Klick werden Inhalte von ' . esc_html( $host ) . ' geladen.</small></a>';
		$p->set_attribute( 'srcdoc', $doc );
		$p->set_attribute( 'loading', 'lazy' );
		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			$p->set_attribute( 'title', $label );
		}
	}
	return $p->get_updated_html();
}
/* nach do_blocks (9) und wp_filter_content_tags (12) */
add_filter( 'the_content', 'vbembed_facade', 20 );

/* Spectra-Karte (vielbunt Sport-Seiten): Spectra schreibt ein <embed>, das
   kennt kein loading=lazy und lädt die Karte immer sofort. Als iframe läuft
   sie durch den Platzhalter oben. Klassen bleiben, damit Spectras CSS greift. */
function vbembed_spectra_map( $html ) {
	if ( is_admin() || wp_is_json_request() || false === stripos( $html, '<embed' ) ) {
		return $html;
	}
	$html = preg_replace( '#<embed\b([^>]*?)\s*/?>(?:\s*</embed>)?#i', '<iframe$1></iframe>', $html );
	$html = str_replace( 'hl=en', 'hl=de', $html );
	return vbembed_facade( $html );
}
add_filter( 'render_block_uagb/google-map', 'vbembed_spectra_map' );
