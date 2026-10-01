<?php
/**
 * Beitragsübersichten: Alle Beiträge, Kategorien, Schlagwörter, Suche
 * (gleiche Datei in csd-darmstadt.de und vielbunt.org).
 *
 * Vorher griff die Vorlage von Twenty Twenty-Five und zeigte jeden Beitrag
 * komplett mit allen Galerien, dazu eine winzige Seitenauswahl. Bei
 * vielbunt.org waren das über 55.000 Pixel pro Seite.
 *
 * Jetzt: Kacheln im Sharepic-Format (4:5, Bild wird nie abgeschnitten),
 * Filter nach Kategorie, 12 Beiträge pro Seite und eine Seitenauswahl mit
 * großen Knöpfen. Ein serverseitiger Block ({prefix}/archive), den die
 * Vorlagen archive.html, home.html und search.html einbinden.
 *
 * @package vielbunt-themes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VBARCHIVE_PER_PAGE', 12 );

function vbarchive_setup( $prefix ) {
	$GLOBALS['vbarchive_prefix'] = $prefix;
}

function vbarchive_prefix() {
	return isset( $GLOBALS['vbarchive_prefix'] ) ? $GLOBALS['vbarchive_prefix'] : 'vielbunt';
}

/* 12 pro Seite: passt in 4, 3 und 2 Spalten ohne angebrochene Reihe */
function vbarchive_per_page( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( $query->is_home() || $query->is_archive() || $query->is_search() ) {
		$query->set( 'posts_per_page', VBARCHIVE_PER_PAGE );
	}
}
add_action( 'pre_get_posts', 'vbarchive_per_page' );

/* Seite mit allen Beiträgen (Einstellungen > Lesen > Beitragsseite) */
function vbarchive_posts_page_url() {
	$id = (int) get_option( 'page_for_posts' );
	return $id ? get_permalink( $id ) : '';
}

/* Kategorien, die an fast allen Beiträgen hängen ("Aktuelles", "Allgemein",
   bei CSD "News"), sagen nichts aus. Die tauchen weder als Filter noch als
   Etikett auf der Kachel auf. Grenze: mehr als 70 % aller Beiträge. */
function vbarchive_broad_ids() {
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	$ids   = array( (int) get_option( 'default_category' ) );
	$total = (int) wp_count_posts( 'post' )->publish;
	if ( $total > 20 ) {
		foreach ( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true ) ) as $term ) {
			if ( $term->count > 0.7 * $total ) {
				$ids[] = (int) $term->term_id;
			}
		}
	}
	return $ids;
}

/* Zusätzliche Ansichten auf der Beitragsseite, z. B. bei vielbunt
   "Kommende Termine" (/beitraege/?ansicht=termine). Ein Theme meldet sie per
   Filter an: slug => array( 'label' => ..., 'callback' => ... ). Der Callback
   liefert eine Liste aus array( 'post' => WP_Post, 'badge' => '18.10.' ). */
function vbarchive_views() {
	return (array) apply_filters( 'vbarchive_views', array() );
}

function vbarchive_current_view() {
	if ( ! is_home() || empty( $_GET['ansicht'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '';
	}
	$view = sanitize_key( wp_unslash( $_GET['ansicht'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	return isset( vbarchive_views()[ $view ] ) ? $view : '';
}

function vbarchive_view_url( $view ) {
	$all = vbarchive_posts_page_url();
	return $all ? add_query_arg( 'ansicht', $view, $all ) : '';
}

function vbarchive_title() {
	$view = vbarchive_current_view();
	if ( $view ) {
		return vbarchive_views()[ $view ]['label'];
	}
	if ( is_home() ) {
		$id = (int) get_option( 'page_for_posts' );
		return $id ? get_the_title( $id ) : 'Alle Beiträge';
	}
	if ( is_search() ) {
		return sprintf( 'Suche: „%s“', get_search_query() );
	}
	if ( is_category() || is_tag() || is_tax() ) {
		return single_term_title( '', false );
	}
	if ( is_author() ) {
		return 'Beiträge von ' . get_the_author_meta( 'display_name', get_query_var( 'author' ) );
	}
	if ( is_date() ) {
		return 'Archiv ' . ( is_month() ? get_the_date( 'F Y' ) : get_the_date( 'Y' ) );
	}
	return 'Beiträge';
}

/* Filter-Knöpfe: "Alle" plus die meistgenutzten Kategorien */
function vbarchive_filters() {
	$current = ( is_category() ) ? get_queried_object_id() : 0;
	$all_url = vbarchive_posts_page_url();
	$out     = '';
	$view    = vbarchive_current_view();
	if ( $all_url ) {
		$all_active = is_home() && ! $view;
		$out       .= sprintf(
			'<a class="vba-chip%s" href="%s"%s>Alle</a>',
			$all_active ? ' is-active' : '',
			esc_url( $all_url ),
			$all_active ? ' aria-current="page"' : ''
		);
		foreach ( vbarchive_views() as $slug => $v ) {
			$active = $view === $slug;
			$out   .= sprintf(
				'<a class="vba-chip vba-chip--view%s" href="%s"%s>%s</a>',
				$active ? ' is-active' : '',
				esc_url( vbarchive_view_url( $slug ) ),
				$active ? ' aria-current="page"' : '',
				esc_html( $v['label'] )
			);
		}
	}
	$skip  = vbarchive_broad_ids();
	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'parent'     => 0,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => 14,
			'hide_empty' => true,
		)
	);
	$shown = 0;
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			if ( in_array( (int) $term->term_id, $skip, true ) || in_array( $term->slug, array( 'uncategorized', 'allgemein' ), true ) ) {
				continue;
			}
			if ( $shown >= 7 && $term->term_id !== $current ) {
				continue;
			}
			$active = $term->term_id === $current;
			$out   .= sprintf(
				'<a class="vba-chip%s" href="%s"%s>%s</a>',
				$active ? ' is-active' : '',
				esc_url( get_term_link( $term ) ),
				$active ? ' aria-current="page"' : '',
				esc_html( $term->name )
			);
			$shown++;
		}
	}
	// aktuelle Kategorie, falls sie nicht unter den häufigsten ist
	if ( $current && false === strpos( $out, 'is-active' ) ) {
		$term = get_term( $current );
		if ( $term && ! is_wp_error( $term ) ) {
			$out .= sprintf( '<a class="vba-chip is-active" href="%s" aria-current="page">%s</a>', esc_url( get_term_link( $term ) ), esc_html( $term->name ) );
		}
	}
	return $out ? '<nav class="vba-filters" aria-label="Nach Kategorie filtern">' . $out . '</nav>' : '';
}

/* Bild: Beitragsbild (mit srcset), sonst erstes Bild im Inhalt */
function vbarchive_image( $post ) {
	$id = get_post_thumbnail_id( $post );
	if ( $id ) {
		return wp_get_attachment_image(
			$id,
			'medium_large',
			false,
			array(
				'alt'     => '',
				'loading' => 'lazy',
				'sizes'   => '(max-width: 600px) 50vw, (max-width: 1100px) 33vw, 300px',
			)
		);
	}
	if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $post->post_content, $m ) ) {
		return '<img src="' . esc_url( $m[1] ) . '" alt="" loading="lazy" />';
	}
	return '';
}

function vbarchive_card( $post, $i, $badge = '' ) {
	$url   = get_permalink( $post );
	$title = get_the_title( $post );
	// Etikett: die spezifischste Kategorie (die mit den wenigsten Beiträgen)
	$cat   = '';
	$best  = PHP_INT_MAX;
	foreach ( get_the_category( $post->ID ) as $c ) {
		if ( in_array( (int) $c->term_id, vbarchive_broad_ids(), true ) || in_array( $c->slug, array( 'uncategorized', 'allgemein' ), true ) ) {
			continue;
		}
		if ( $c->count < $best ) {
			$best = $c->count;
			$cat  = $c->name;
		}
	}
	$img    = vbarchive_image( $post );
	$colors = array( 'pink', 'blue', 'green', 'purple', 'orange', 'ink' );
	$color  = $colors[ $i % count( $colors ) ];

	if ( $img ) {
		$media = '<a class="vba-card__media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>';
	} else {
		// ohne Bild: farbige Kachel mit Titel, damit das Raster ruhig bleibt
		$media = sprintf(
			'<a class="vba-card__media vba-card__media--text is-%1$s" href="%2$s" tabindex="-1" aria-hidden="true"><span>%3$s</span></a>',
			esc_attr( $color ),
			esc_url( $url ),
			esc_html( wp_trim_words( $title, 12 ) )
		);
	}

	if ( '' !== $badge ) {
		$media = str_replace( '" tabindex="-1" aria-hidden="true">', '" tabindex="-1" aria-hidden="true"><span class="vba-card__badge">' . esc_html( $badge ) . '</span>', $media );
	}

	return '<article class="vba-card">' . $media
		. '<div class="vba-card__body">'
		. ( $cat ? '<span class="vba-card__cat">' . esc_html( $cat ) . '</span>' : '' )
		. '<h2 class="vba-card__title"><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></h2>'
		// bei Terminen steht das Termindatum schon oben auf dem Bild, das Veröffentlichungsdatum würde nur verwirren
		. ( '' === $badge ? '<time class="vba-card__date" datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( 'j. F Y', $post ) ) . '</time>' : '' )
		. '</div></article>';
}

function vbarchive_pagination( $query ) {
	if ( $query->max_num_pages < 2 ) {
		return '';
	}
	$links = paginate_links(
		array(
			'total'     => $query->max_num_pages,
			'current'   => max( 1, (int) get_query_var( 'paged' ) ),
			'mid_size'  => 1,
			'end_size'  => 1,
			'type'      => 'array',
			'prev_text' => '← Neuere',
			'next_text' => 'Ältere →',
		)
	);
	if ( empty( $links ) ) {
		return '';
	}
	return '<nav class="vba-pages" aria-label="Seiten"><ul><li>' . implode( '</li><li>', $links ) . '</li></ul></nav>';
}

function vbarchive_search_form() {
	return '<form class="vba-search" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '">'
		. '<label class="screen-reader-text" for="vba-s">Beiträge durchsuchen</label>'
		. '<input id="vba-s" type="search" name="s" placeholder="Beiträge durchsuchen" value="' . esc_attr( get_search_query() ) . '" />'
		. '<button type="submit">Suchen</button></form>';
}

function vbarchive_render() {
	global $wp_query;
	// Im Editor gibt es keine Archiv-Abfrage, da zeigen wir einfach die neusten Beiträge
	$preview = ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_admin();
	$view    = $preview ? '' : vbarchive_current_view();
	$items   = array();
	if ( $view ) {
		$items = (array) call_user_func( vbarchive_views()[ $view ]['callback'] );
		$query = null;
	} else {
		$query = $preview
			? new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 8, 'ignore_sticky_posts' => true, 'no_found_rows' => true ) )
			: $wp_query;
		foreach ( $query->posts as $post ) {
			$items[] = array( 'post' => $post, 'badge' => '' );
		}
	}
	$count = $view ? count( $items ) : ( $preview ? 0 : (int) $query->found_posts );

	$out  = '<div class="vba">';
	$out .= '<header class="vba-head"><div class="vba-head__top"><div>';
	$out .= '<h1 class="vba-title">' . esc_html( $preview ? 'Beiträge' : vbarchive_title() ) . '</h1>';
	if ( $count ) {
		$out .= '<p class="vba-count">' . esc_html( sprintf( 1 === $count ? '%s Beitrag' : '%s Beiträge', number_format_i18n( $count ) ) ) . '</p>';
	}
	$out .= '</div>';
	if ( ! $preview ) {
		$out .= vbarchive_search_form();
	}
	$out .= '</div>';
	if ( ! $preview && ( is_category() || is_tag() ) && term_description() ) {
		$out .= '<div class="vba-desc">' . wp_kses_post( term_description() ) . '</div>';
	}
	$out .= vbarchive_filters();
	$out .= '</header>';

	if ( empty( $items ) ) {
		$out .= '<p class="vb-empty">' . ( $view ? 'Gerade stehen keine Termine an.' : 'Hier gibt es leider keine Beiträge.' ) . '</p></div>';
		return $out;
	}

	$out .= '<div class="vba-grid">';
	foreach ( $items as $i => $item ) {
		$out .= vbarchive_card( $item['post'], $i, isset( $item['badge'] ) ? $item['badge'] : '' );
	}
	$out .= '</div>';
	if ( ! $preview && ! $view ) {
		$out .= vbarchive_pagination( $query );
	}
	$out .= '</div>';
	return $out;
}

function vbarchive_register_block() {
	register_block_type(
		vbarchive_prefix() . '/archive',
		array(
			'api_version'     => 3,
			'render_callback' => 'vbarchive_render',
		)
	);
}
add_action( 'init', 'vbarchive_register_block' );

/* einmaliger Schritt: Seite "Alle Beiträge" anlegen und als Beitragsseite setzen,
   damit es überhaupt eine Übersicht über alle Kategorien hinweg gibt */
function vbarchive_once_posts_page() {
	$existing = (int) get_option( 'page_for_posts' );
	if ( $existing && 'publish' === get_post_status( $existing ) ) {
		return 'Beitragsseite gab es schon: ' . get_permalink( $existing );
	}
	$page = get_page_by_path( 'beitraege' );
	$id   = $page ? $page->ID : wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_title'  => 'Alle Beiträge',
			'post_name'   => 'beitraege',
			'post_status' => 'publish',
		)
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 'Seite konnte nicht angelegt werden';
	}
	update_option( 'page_for_posts', $id );
	return 'Seite "Alle Beiträge" (' . get_permalink( $id ) . ') als Beitragsseite gesetzt';
}

/* einmaliger Schritt: WordPress nutzt die Beitragsseite nur, wenn unter
   Einstellungen > Lesen eine statische Startseite eingestellt ist. Auf den
   Live-Seiten stand das auf "Deine neuesten Beiträge" (die Startseite kam nur
   über front-page.html). Wir stellen auf eine leere Seite "Startseite" um,
   was auf / zu sehen ist bleibt gleich, die Vorlage front-page.html greift
   in beiden Fällen. */
function vbarchive_once_static_front() {
	if ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) ) {
		return 'Statische Startseite war schon eingestellt';
	}
	$page = get_page_by_path( 'startseite' );
	$id   = $page ? $page->ID : wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_title'  => 'Startseite',
			'post_name'   => 'startseite',
			'post_status' => 'publish',
		)
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 'Startseite konnte nicht angelegt werden';
	}
	update_option( 'page_on_front', $id );
	update_option( 'show_on_front', 'page' );
	return 'Einstellungen > Lesen: statische Startseite (Seite ' . $id . ' "Startseite"), Beitragsseite "Alle Beiträge"';
}

/* Links auf der Startseite ("Alle Beiträge →", "Zum Blog →") zeigten auf eine
   einzelne Kategorie. Sobald es die Beitragsseite gibt, führen sie dorthin.
   Per render_block, damit es auch im angepassten Startseiten-Template greift. */
function vbarchive_frontpage_links( $content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/paragraph' !== $block['blockName'] || ! is_front_page() ) {
		return $content;
	}
	$url = vbarchive_posts_page_url();
	if ( ! $url ) {
		return $content;
	}
	if ( isset( vbarchive_views()['termine'] ) && preg_match( '/>\s*Alle Termine\s*→\s*</u', $content ) ) {
		$url = vbarchive_view_url( 'termine' );
	} elseif ( ! preg_match( '/>\s*(Alle Beiträge|Zum Blog)\s*→\s*</u', $content ) ) {
		return $content;
	}
	return preg_replace( '/href="[^"]*"/', 'href="' . esc_url( $url ) . '"', $content, 1 );
}
add_filter( 'render_block', 'vbarchive_frontpage_links', 10, 2 );
