<?php
/**
 * Kategorien, die Sinn ergeben.
 *
 * 1. Neue Beiträge (von Hand, vom Postergenerator, von jedem anderen Tool)
 *    bekommen beim Veröffentlichen oder Einplanen automatisch passende
 *    Kategorien: Art (Veranstaltung oder News) und Thema (Treffbunt,
 *    villaQ/Jugend, Queerbar, Sport …), erkannt am Titel. Es wird nur ergänzt,
 *    nie etwas entfernt, was jemand bewusst gesetzt hat.
 * 2. Einmalige Aufräumaktion (Oktober 2026) nach denselben Regeln, genau so
 *    wie vorher geprüft (inc/data/kategorien-2026-10.json). Die alten
 *    Zuordnungen liegen als Backup in der Option vielbunt_kategorien_backup.
 *
 * Kategorien werden nicht gelöscht oder zusammengelegt: Menüs, Seiten mit
 * "Neueste Beiträge" und der Postergenerator hängen an den IDs.
 *
 * @package vielbunt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Kategorie-IDs auf vielbunt.org (Stand Oktober 2026) */
function vielbunt_cat( $key ) {
	$ids = array(
		'aktuelles'      => 24,
		'allgemein'      => 1,
		'news'           => 5,
		'veranstaltung'  => 465,
		'aktivitaeten'   => 6,
		'kultur'         => 15,
		'treffbunt'      => 28,
		'sport_akt'      => 427,
		'sport'          => 201,
		'verein'         => 7,
		'jugend'         => 18,
		'ausfluege'      => 190,
		'csd'            => 26,
		'presse'         => 23,
		'zentrum'        => 397,
		'jugendvorstand' => 555,
		'sul'            => 25,
		'lauftreff'      => 29,
		'aidsgala'       => 34,
		'queerbar'       => 558,
		'agtrans'        => 17,
		'refugees'       => 406,
		'seitrans'       => 32,
		'polittalk'      => 571,
	);
	return isset( $ids[ $key ] ) ? $ids[ $key ] : 0;
}

/* Thema am Titel erkennen (Kleinbuchstaben) */
function vielbunt_cat_topic_rules() {
	return array(
		'treffbunt' => 'treffbunt',
		'queerbar'  => 'queerbar',
		'sul'       => 'schrill\s*(und|&)\s*laut',
		'jugend'    => 'villa\s?q|jugend|young|kochabend',
		'sport'     => 'sport\*|lauftreff|radtour|volleyball|badminton|tanzen|tanzkurs|yoga|bouldern|schwimm|salsa|wanderung|laufen\b',
		'lauftreff' => 'lauftreff',
		'ausfluege' => 'besuch des csd|ausflug|wanderreise|wanderung|radtour|exkursion|mit vielbunt (zum|zur|nach|in)|gemeinsam (zum|nach)',
		'aidsgala'  => 'aids-?gala',
		'agtrans'   => '\btrans\*|\btrans\b|\btrans-|transgender|sei trans du|tdor|day of (visibility|remembrance)|selbstbestimmungsgesetz',
		'seitrans'  => 'sei trans du',
		'refugees'  => 'refugee',
		'polittalk' => 'polit-?talk',
		'zentrum'   => 'queere[nms]? zentrum|büro des queeren',
		'csd'       => 'csd[- ]?darmstadt|christopher street day darmstadt|csd 20\d\d|csd-aktionswoche|pride week|csd-schnupper|\bcsd\b(?!.*(mainz|frankfurt|mannheim|hanau|wiesbaden|heidelberg|köln|berlin|rhein|offenbach|aschaffenburg|bensheim))',
		'presse'    => 'pressemitteilung',
		'verein'    => 'mitgliederversammlung|vorstand|stellenausschreibung|satzung|jahresbericht|beitragsordnung',
		'kultur'    => 'poly and more|verstrickt|familienbunt|kleidertausch|afterwork|ü49|lesbian|spieleabend|queeriosity|kitchenswitchen|filmnacht|filmfest|lesung|pubquiz|open ?mic|schreibtreff|schreibtisch|kunstkurs|bastelkurs|\bkino\b|little italy|pasta|vino|treffbunt|brunch|feelbunt|weihnachtsmarkt|ladies night|info\*?bar|ostereiersuche|picknick|stammtisch|tanzabend',
	);
}

function vielbunt_cat_is_dated( $title ) {
	return (bool) preg_match( '/^\s*\d{1,2}\.(\d{1,2}\.|\s*[-\x{2013}])/u', $title );
}

/**
 * Neue Kategorienliste für einen Beitrag. Ergänzt nur, entfernt höchstens
 * "Veranstaltung" bei eindeutigen Nicht-Terminen (Rückblick, geschlossen …).
 */
function vielbunt_categorize( $title, $current ) {
	$t   = mb_strtolower( wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) ) );
	$raw = trim( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) );
	$new = array_map( 'intval', (array) $current );

	foreach ( vielbunt_cat_topic_rules() as $key => $rx ) {
		if ( preg_match( '/' . $rx . '/u', $t ) ) {
			$new[] = vielbunt_cat( $key );
		}
	}
	// zusammengehörige Kategorien gleich mitsetzen
	$pairs = array( array( 'sport', 'sport_akt' ), array( 'sport_akt', 'sport' ), array( 'jugendvorstand', 'jugend' ), array( 'lauftreff', 'sport' ), array( 'treffbunt', 'kultur' ) );
	for ( $round = 0; $round < 2; $round++ ) {
		foreach ( $pairs as $p ) {
			if ( in_array( vielbunt_cat( $p[0] ), $new, true ) ) {
				$new[] = vielbunt_cat( $p[1] );
			}
		}
	}
	// Unterkategorien von "Aktivitäten" bringen ihre Oberkategorie mit
	foreach ( array( 'treffbunt', 'sport_akt', 'ausfluege', 'csd', 'jugendvorstand', 'sul', 'lauftreff', 'queerbar' ) as $child ) {
		if ( in_array( vielbunt_cat( $child ), $new, true ) ) {
			$new[] = vielbunt_cat( 'aktivitaeten' );
			break;
		}
	}
	$not_event = preg_match( '/^(rückblick|pressemitteilung|stellenausschreibung|offener brief|update zur|statement|stellungnahme|nachruf)|geschlossen|wir suchen|gesucht/u', $t );
	if ( $not_event ) {
		$new = array_diff( $new, array( vielbunt_cat( 'veranstaltung' ) ) );
	} elseif ( vielbunt_cat_is_dated( $raw ) ) {
		$new[] = vielbunt_cat( 'veranstaltung' );
	}
	if ( in_array( vielbunt_cat( 'presse' ), $new, true ) ) {
		$new[] = vielbunt_cat( 'news' );
	}
	if ( ! in_array( vielbunt_cat( 'veranstaltung' ), $new, true ) && ! in_array( vielbunt_cat( 'news' ), $new, true ) ) {
		$new[] = vielbunt_cat( 'news' );
	}
	$new = array_values( array_unique( array_filter( array_map( 'intval', $new ) ) ) );
	sort( $new );
	return $new;
}

/* Neue Beiträge: beim ersten Veröffentlichen/Einplanen einordnen. Läuft nach
   dem Speichern von Kategorien und Meta (auch bei REST, also der Routine). */
function vielbunt_categorize_on_publish( $post_id, $post, $update, $post_before ) {
	if ( 'post' !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
		return;
	}
	if ( $post_before && in_array( $post_before->post_status, array( 'publish', 'future' ), true ) ) {
		return; // schon eingeordnet, spätere Änderungen von Hand bleiben, wie sie sind
	}
	$current = wp_get_post_categories( $post_id );
	$new     = vielbunt_categorize( $post->post_title, $current );
	sort( $current );
	if ( $new !== array_map( 'intval', $current ) ) {
		wp_set_post_categories( $post_id, $new );
	}
}
add_action( 'wp_after_insert_post', 'vielbunt_categorize_on_publish', 10, 4 );

/* Einmalige Aufräumaktion, genau nach der geprüften Liste. Wurde ein Beitrag
   seitdem geändert, bekommt er die Regeln auf seinen aktuellen Stand. */
function vielbunt_once_categories() {
	$file = __DIR__ . '/data/kategorien-2026-10.json';
	$map  = is_readable( $file ) ? json_decode( file_get_contents( $file ), true ) : null;
	if ( ! is_array( $map ) ) {
		return 'Datendatei fehlt, nichts geändert';
	}
	wp_defer_term_counting( true );
	$backup  = array();
	$exact   = 0;
	$rules   = 0;
	foreach ( $map as $id => $entry ) {
		$post = get_post( (int) $id );
		if ( ! $post || 'post' !== $post->post_type ) {
			continue;
		}
		$current = array_map( 'intval', wp_get_post_categories( $post->ID ) );
		sort( $current );
		$backup[ $post->ID ] = $current;
		if ( $current === $entry['old'] ) {
			$new = $entry['new'];
			$exact++;
		} else {
			$new = vielbunt_categorize( $post->post_title, $current );
			$rules++;
		}
		wp_set_post_categories( $post->ID, $new );
	}
	wp_defer_term_counting( false );
	update_option( 'vielbunt_kategorien_backup', $backup, false );
	return ( $exact + $rules ) . ' Beiträge neu eingeordnet (' . $exact . ' wie geprüft, ' . $rules . ' seitdem geändert und nach Regeln), Backup in vielbunt_kategorien_backup';
}

/* Menü: "News" (lauter Kategorie-Archive, "Aktuelles" ist praktisch alles)
   wird zu "Neuigkeiten" mit Kommende Termine, Alle Beiträge,
   Pressemitteilungen und Vereinsnews. Backup: vielbunt_menu_backup */
function vielbunt_once_menu() {
	$posts_page = (int) get_option( 'page_for_posts' );
	if ( ! $posts_page ) {
		return 'Keine Beitragsseite, Menü nicht geändert';
	}
	$all     = get_permalink( $posts_page );
	$changed = vielbunt_once_edit_submenu(
		'News',
		function ( $block ) use ( $posts_page, $all ) {
			$keep = array();
			foreach ( $block['innerBlocks'] as $child ) {
				$label = isset( $child['attrs']['label'] ) ? trim( wp_strip_all_tags( html_entity_decode( $child['attrs']['label'], ENT_QUOTES, 'UTF-8' ) ) ) : '';
				if ( 'Pressemitteilungen' === $label ) {
					$keep['presse'] = $child;
				} elseif ( 'Verein' === $label ) {
					$child['attrs']['label'] = 'Vereinsnews';
					$keep['verein']          = $child;
				}
			}
			$children = array(
				vielbunt_once_nav_link( 'Kommende Termine', add_query_arg( 'ansicht', 'termine', $all ) ),
				vielbunt_once_nav_link( 'Alle Beiträge', $all, $posts_page ),
			);
			if ( isset( $keep['presse'] ) ) {
				$children[] = $keep['presse'];
			}
			if ( isset( $keep['verein'] ) ) {
				$children[] = $keep['verein'];
			}
			$block['attrs'] = array_merge( $block['attrs'], array( 'label' => 'Neuigkeiten', 'url' => $all, 'id' => $posts_page, 'type' => 'page', 'kind' => 'post-type' ) );
			return vielbunt_once_set_children( $block, $children );
		},
		'vielbunt_menu_backup'
	);
	return $changed ? 'Menü "' . implode( '", "', $changed ) . '": "News" ist jetzt "Neuigkeiten" (Kommende Termine, Alle Beiträge, Pressemitteilungen, Vereinsnews)' : 'Kein Untermenü "News" gefunden, nichts geändert';
}

/* Menü: "Vereinsnews" (Kategorie Verein) unter "Neuigkeiten" ergänzen */
function vielbunt_once_menu_vereinsnews() {
	$url     = get_category_link( vielbunt_cat( 'verein' ) );
	$changed = vielbunt_once_edit_submenu(
		'Neuigkeiten',
		function ( $block ) use ( $url ) {
			foreach ( $block['innerBlocks'] as $child ) {
				if ( isset( $child['attrs']['url'] ) && untrailingslashit( $child['attrs']['url'] ) === untrailingslashit( $url ) ) {
					return $block; // schon drin
				}
			}
			$link                  = vielbunt_once_nav_link( 'Vereinsnews', $url );
			$link['attrs']['type'] = 'category';
			$link['attrs']['kind'] = 'taxonomy';
			$link['attrs']['id']   = vielbunt_cat( 'verein' );
			$children              = $block['innerBlocks'];
			$children[]            = $link;
			return vielbunt_once_set_children( $block, $children );
		},
		'vielbunt_menu_backup_2'
	);
	return $changed ? 'Menü "' . implode( '", "', $changed ) . '": "Vereinsnews" unter "Neuigkeiten" ergänzt' : 'Kein Untermenü "Neuigkeiten" gefunden';
}
