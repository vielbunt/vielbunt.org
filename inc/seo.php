<?php
/**
 * Suchmaschinen: was Google zu sehen bekommt (gleiche Datei in
 * csd-darmstadt.de und vielbunt.org, Umfang per vbseo_setup()).
 *
 * - Archive (Kategorien, Schlagwörter, Autor*innen, Datum), Suchergebnisse
 *   und Folgeseiten (/page/2/) bekommen noindex und fliegen aus der Sitemap.
 *   Sonst landen Seiten wie "Kategorie: Bühnenprogramm" in den Google-
 *   Unterseiten, obwohl sie nur Listen anderer Beiträge sind.
 * - optional: Schalter "Nicht in Suchmaschinen anzeigen" pro Seite/Beitrag
 *   (Post-Meta _vb_noindex, im Editor unter "Suchmaschinen").
 * - optional: Auszug-Feld für Seiten, daraus wird die Meta-Beschreibung.
 *
 * Kommt ein SEO-Plugin dazu, halten wir uns bei robots und Sitemap raus.
 *
 * @package vielbunt-themes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vbseo_options() {
	return wp_parse_args(
		isset( $GLOBALS['vbseo_options'] ) ? $GLOBALS['vbseo_options'] : array(),
		array(
			'toggle'       => false,
			'page_excerpt' => false,
		)
	);
}

function vbseo_setup( $options = array() ) {
	$GLOBALS['vbseo_options'] = $options;
}

function vbseo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

/* Seiten, die Google nicht indexieren soll */
function vbseo_is_noindex() {
	if ( is_category() || is_tag() || is_tax() || is_author() || is_date() || is_search() || is_paged() ) {
		return true;
	}
	if ( vbseo_options()['toggle'] && is_singular() ) {
		return (bool) get_post_meta( get_queried_object_id(), '_vb_noindex', true );
	}
	return false;
}

function vbseo_robots( $robots ) {
	if ( vbseo_plugin_active() || ! vbseo_is_noindex() ) {
		return $robots;
	}
	// Links darauf darf Google trotzdem folgen, nur die Seite selbst soll nicht in den Index
	$robots['noindex'] = true;
	$robots['follow']  = true;
	unset( $robots['max-image-preview'] );
	return $robots;
}
add_filter( 'wp_robots', 'vbseo_robots', 20 );

/* Sitemap: nur echte Inhalte, keine Kategorien, Schlagwörter oder Autor*innen */
function vbseo_sitemap_providers( $provider, $name ) {
	if ( ! vbseo_plugin_active() && in_array( $name, array( 'taxonomies', 'users' ), true ) ) {
		return false;
	}
	return $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'vbseo_sitemap_providers', 10, 2 );

/* ausgeblendete Seiten/Beiträge auch nicht in der Sitemap */
function vbseo_sitemap_posts( $args ) {
	if ( ! vbseo_options()['toggle'] ) {
		return $args;
	}
	$args['meta_query'] = array(
		'relation' => 'OR',
		array( 'key' => '_vb_noindex', 'compare' => 'NOT EXISTS' ),
		array( 'key' => '_vb_noindex', 'value' => '1', 'compare' => '!=' ),
	);
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'vbseo_sitemap_posts' );

/* Schalter im Editor: Meta registrieren (für REST/Editor) */
function vbseo_register_meta() {
	$opt = vbseo_options();
	if ( $opt['page_excerpt'] ) {
		add_post_type_support( 'page', 'excerpt' );
	}
	if ( ! $opt['toggle'] ) {
		return;
	}
	foreach ( array( 'post', 'page' ) as $type ) {
		register_post_meta(
			$type,
			'_vb_noindex',
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => function ( $allowed, $key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}
}
add_action( 'init', 'vbseo_register_meta' );

/* nur im Beitrags-/Seiten-Editor, nicht im Site-Editor */
function vbseo_editor_assets() {
	if ( ! vbseo_options()['toggle'] ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}
	wp_enqueue_script(
		'vbseo-editor',
		get_stylesheet_directory_uri() . '/assets/seo-editor.js',
		array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-data', 'wp-core-data', 'wp-components', 'wp-element' ),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'vbseo_editor_assets' );
