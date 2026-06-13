<?php
/**
 * vielbunt functions.php
 *
 * Stylesheets, Fonts, Datumserkennung für Termin-Posts und
 * die dynamsichen Blöcke (Hero, Schnellzugriff, Termine, Feed).
 * Keine Shortcodes, keine rohen HTML-Blöcke im Template, das hat
 * in der ersten Version alles zerlegt.
 *
 * @package vielbunt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Stylesheets laden */
function vielbunt_enqueue_styles() {
	wp_enqueue_style(
		'twentytwentyfive-style',
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme( get_template() )->get( 'Version' )
	);
	wp_enqueue_style(
		'vielbunt-style',
		get_stylesheet_uri(),
		array( 'twentytwentyfive-style' ),
		wp_get_theme()->get( 'Version' )
	);
	wp_enqueue_script(
		'vielbunt-nav',
		get_stylesheet_directory_uri() . '/assets/nav.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'vielbunt_enqueue_styles' );

// Eigene Klassen auch im Editor-Canvas sichtbar machen.
function vielbunt_editor_styles() {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
}
add_action( 'after_setup_theme', 'vielbunt_editor_styles' );

/* Cera Pro per @font-face
   Wichtig: keine Schriftdateien im Theme-Ordner (Lizenz!), die liegen
   in der Mediathek unter /wp-content/uploads/2021/01/
   enqueue_block_assets läuft im Frontend und im Editor-Iframe,
   desswegen klappt die Vorschau auch dort */
function vielbunt_font_face_css() {
	$base = content_url( '/uploads/2021/01' );
	$faces = array(
		array( 'normal', '400', 'Cera-Pro-Regular' ),
		array( 'italic', '400', 'Cera-Pro-Regular-Italic' ),
		array( 'normal', '800', 'Cera-Pro-Bold' ),
		array( 'italic', '800', 'Cera-Pro-Bold-Italic' ),
	);
	$css = '';
	foreach ( $faces as $f ) {
		$css .= sprintf(
			'@font-face{font-family:"Cera Pro";font-style:%1$s;font-weight:%2$s;font-display:swap;src:url("%3$s/%4$s.woff2") format("woff2"),url("%3$s/%4$s.woff") format("woff");}',
			$f[0],
			$f[1],
			esc_url( $base ),
			$f[2]
		);
	}
	return $css;
}
function vielbunt_enqueue_fonts() {
	wp_register_style( 'vielbunt-fonts', false );
	wp_enqueue_style( 'vielbunt-fonts' );
	wp_add_inline_style( 'vielbunt-fonts', vielbunt_font_face_css() );
}
add_action( 'enqueue_block_assets', 'vielbunt_enqueue_fonts' );

/* Datum aus dem Beitragstitel parsen
   "06.06.: Museumsbesuch"    -> 6. Juni, Titel "Museumsbesuch"
   "28.05. · 19:00 treffbunt" -> 28. Mai
   "01.06.-05.06.2026 Woche"  -> Startdatum 1. Juni */
function vielbunt_parse_event_date( $title, $post = null ) {
	$title   = trim( wp_strip_all_tags( $title ) );
	$pattern = '/^\s*(\d{1,2})\.(\d{1,2})\.(?:\s*[-\x{2013}]\s*\d{1,2}\.\d{1,2}\.)?(\d{4})?/u';

	if ( ! preg_match( $pattern, $title, $m ) ) {
		return null;
	}

	$day   = (int) $m[1];
	$month = (int) $m[2];
	if ( $month < 1 || $month > 12 || $day < 1 || $day > 31 ) {
		return null;
	}

	if ( ! empty( $m[3] ) ) {
		$year = (int) $m[3];
	} else {
		// Veröffentlichungsdatum als Referenz nehmen, nicht das heutige Datum.
		// Sonst würde ein alter Post mit "22.10." vom Oktober 2025 plötzlich
		// als Oktober 2026 auftauchen sobald das datum in diesem Jahr vorbei ist.
		// Ausnahme: wenn das Datum vor der Veröffentlichung liegt (z.B. "03.01."
		// in einem Dezember-Post) dann muss es das Folgejahr sein.
		if ( $post && ! empty( $post->post_date ) ) {
			$ref_ts = strtotime( $post->post_date );
		} else {
			$ref_ts = current_time( 'timestamp' );
		}
		$year   = (int) date( 'Y', $ref_ts );
		$try_ts = mktime( 0, 0, 0, $month, $day, $year );
		if ( $try_ts < ( $ref_ts - DAY_IN_SECONDS ) ) {
			$year++;
		}
	}

	$timestamp = mktime( 0, 0, 0, $month, $day, $year );
	if ( false === $timestamp ) {
		return null;
	}

	$clean = preg_replace(
		'/^\s*\d{1,2}\.\d{1,2}\.(?:\s*[-\x{2013}]\s*\d{1,2}\.\d{1,2}\.)?(?:\d{4})?\s*(?:[:\x{00B7}\-\x{2013}|]\s*)?/u',
		'',
		$title
	);
	$clean = preg_replace( '/^\s*\d{1,2}:\d{2}\s*(?:Uhr)?\s*(?:[:\x{00B7}\-\x{2013}|]\s*)?/u', '', $clean );

	return array(
		'timestamp'   => $timestamp,
		'date_label'  => sprintf( '%02d.%02d.', $day, $month ),
		'clean_title' => trim( $clean ),
	);
}

function vielbunt_get_sorted_posts( $event_limit = 8, $feed_limit = 6 ) {
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 150,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	$events_upcoming = array();
	$events_recent   = array(); // vergangene Events der letzten 14 Tage als Reserve
	$feed            = array();
	$today           = strtotime( 'today', current_time( 'timestamp' ) );

	foreach ( $query->posts as $post ) {
		$parsed = vielbunt_parse_event_date( $post->post_title, $post );
		if ( $parsed ) {
			if ( $parsed['timestamp'] >= $today ) {
				$events_upcoming[] = array( 'post' => $post, 'meta' => $parsed );
			} elseif ( $parsed['timestamp'] >= $today - 14 * DAY_IN_SECONDS ) {
				$events_recent[] = array( 'post' => $post, 'meta' => $parsed );
			}
		} else {
			$feed[] = $post;
		}
	}

	// aufsteigend sortieren, nächstes Event zuerst
	usort(
		$events_upcoming,
		static function ( $a, $b ) {
			return $a['meta']['timestamp'] <=> $b['meta']['timestamp'];
		}
	);

	// Wir wollen immer genau 8 oder genau 4 Kacheln. Vergangene Events
	// nutzen wir nur als Lückenfüller wenn es nicht mal 4 zukünftige gibt.
	// Sobald wir 4+ zukünftige haben, zeigen wir die ersten 4 oder 8
	// ohne irgendwas aufzufüllen. Sonst würden vergangene Termine
	// auftauchen obwohl genug aktuelle da sind, das sieht komisch aus.
	$upcoming_count = count( $events_upcoming );

	if ( $upcoming_count >= $event_limit ) {
		// mehr als genug zukünftige, einfach die ersten 8
		$events = array_slice( $events_upcoming, 0, $event_limit );
	} elseif ( $upcoming_count >= 4 ) {
		// zwischen 4 und 7 zukünftige, wir zeigen genau 4 ohne Füller
		$events = array_slice( $events_upcoming, 0, 4 );
	} else {
		// weniger als 4 zukünftige, jetzt kommt der Füller aus vergangenen
		$events = $events_upcoming; // alle zukünftigen erstmal nehmen
		$count  = count( $events );

		if ( ! empty( $events_recent ) ) {
			// jüngste vergangene zuerst sortieren
			usort(
				$events_recent,
				static function ( $a, $b ) {
					return $b['meta']['timestamp'] <=> $a['meta']['timestamp'];
				}
			);

			$total_available = $count + count( $events_recent );

			if ( $total_available >= 4 ) {
				// reicht für eine volle Reihe mit 4
				$needed = 4 - $count;
				$events = array_merge( $events, array_slice( $events_recent, 0, $needed ) );
			} else {
				// kommt halt nicht auf 4, wir zeigen was da ist
				$events = array_merge( $events, $events_recent );
			}
		}
	}

	// nochmal chronologisch sortieren damit Fill-Events nicht komisch reinragen
	usort(
		$events,
		static function ( $a, $b ) {
			return $a['meta']['timestamp'] <=> $b['meta']['timestamp'];
		}
	);

	return array(
		'events' => $events,
		'feed'   => array_slice( $feed, 0, $feed_limit ),
	);
}

function vielbunt_card_color( $index ) {
	$colors = array( 'pink', 'blue', 'green', 'purple', 'orange', 'ink' );
	return $colors[ $index % count( $colors ) ];
}

/* Icon-Set als Inline-SVG, Farbe kommt von currentColor */
function vielbunt_icon( $name ) {
	$o = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
	$c = '</svg>';
	$paths = array(
		'community' => '<circle cx="9" cy="7" r="3"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><circle cx="17.5" cy="7.5" r="2"/><path d="M21 21v-1a3 3 0 0 0-3-3"/>',
		'flag'      => '<path d="M5 21V4"/><path d="M5 4h13l-2.5 4L18 12H5"/>',
		'coffee'    => '<path d="M4 9h13v4a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z"/><path d="M17 10h2a2 2 0 0 1 0 4h-2"/><path d="M7 3v2.5M11 3v2.5"/>',
		'run'       => '<circle cx="15" cy="5" r="2"/><path d="M9.5 8.5 14 11l1 4 3.5 2.5"/><path d="M8 21l2.5-5L8 13l-3 2"/>',
		'heart'     => '<path d="M12 20s-6.5-4-8.5-8.2A4.6 4.6 0 0 1 12 6.5a4.6 4.6 0 0 1 8.5 5.3C18.5 16 12 20 12 20z"/>',
		'smile'     => '<circle cx="12" cy="12" r="9"/><path d="M9 10h.01M15 10h.01M8.5 14.5a4 4 0 0 0 7 0"/>',
		'chat'      => '<path d="M4 5h13a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H9l-4 3v-3a2 2 0 0 1-1-2V7a2 2 0 0 1 2-2z"/>',
		'hand'      => '<path d="M8 13V6.5a1.5 1.5 0 0 1 3 0V11M11 11V5a1.5 1.5 0 0 1 3 0v6M14 11V6.5a1.5 1.5 0 0 1 3 0V14a6 6 0 0 1-6 6 6 6 0 0 1-5.2-3l-2-3.4a1.5 1.5 0 0 1 2.5-1.6L8 13"/>',
		'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
	);
	if ( 'heart-fill' === $name ) {
		return '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 20s-6.5-4-8.5-8.2A4.6 4.6 0 0 1 12 6.5a4.6 4.6 0 0 1 8.5 5.3C18.5 16 12 20 12 20z"/></svg>';
	}
	$p = isset( $paths[ $name ] ) ? $paths[ $name ] : '';
	return $o . $p . $c;
}

/* ------------------------------------------------------------------ *
 * Persistente Block-Inhalte (wp_options)
 *
 * Problem: Hero-/Schnellzugriff-Inhalte werden im Site-Editor als
 * Block-Attribute gepflegt. Die liegen aber nur in der DB-Kopie des
 * front-page-Templates und gehen bei einem Theme-Re-Upload, einem
 * geänderten Theme-Ordnernamen oder „Anpassungen löschen" verloren.
 *
 * Lösung: Wir spiegeln die Inhalte zusätzlich in eine wp_options-Zeile.
 * Optionen überleben Theme-Updates und Template-Resets. Der Editor
 * schreibt jede Änderung per REST (csd/v1/settings) dorthin und holt
 * sich die Werte beim Laden zurück. Die Render-Callbacks lesen in der
 * Reihenfolge: Block-Attribut -> wp_options -> Hardcoded-Default.
 * ------------------------------------------------------------------ */

if ( ! defined( 'VIELBUNT_SETTINGS_OPTION' ) ) {
	define( 'VIELBUNT_SETTINGS_OPTION', 'vielbunt_block_settings' );
}

/* Welche Blöcke/Attribute dürfen persistiert werden und wie werden
   sie bereinigt. Alles was hier nicht steht wird ignoriert. */
function vielbunt_settings_schema() {
	return array(
		'hero' => array(
			'bgUrl'         => 'url',
			'bgId'          => 'int',
			'heroKicker'    => 'text',
			'heroTitle'     => 'text',
			'heroLead'      => 'textarea',
			'btnSolidLabel' => 'text',
			'btnSolidUrl'   => 'url',
			'btnGhostLabel' => 'text',
			'btnGhostUrl'   => 'url',
		),
		'quicklinks' => array(
			'heading' => 'text',
			'images'  => 'images', // { index: { url, id } }
			'tiles'   => 'tiles',  // { index: { label, url } }
		),
	);
}

function vielbunt_filled( $v ) {
	if ( is_array( $v ) ) {
		return ! empty( $v );
	}
	return null !== $v && '' !== $v;
}

/* Wert auflösen: Block-Attribut -> wp_options -> Default. */
function vielbunt_setting( $attrs, $block, $key, $default ) {
	if ( is_array( $attrs ) && isset( $attrs[ $key ] ) && vielbunt_filled( $attrs[ $key ] ) ) {
		return $attrs[ $key ];
	}
	$store = get_option( VIELBUNT_SETTINGS_OPTION, array() );
	if ( isset( $store[ $block ][ $key ] ) && vielbunt_filled( $store[ $block ][ $key ] ) ) {
		return $store[ $block ][ $key ];
	}
	return $default;
}

function vielbunt_sanitize_image_map( $images ) {
	if ( ! is_array( $images ) ) {
		return array();
	}
	$out = array();
	foreach ( $images as $i => $img ) {
		if ( ! is_array( $img ) ) {
			continue;
		}
		$url = isset( $img['url'] ) ? esc_url_raw( (string) $img['url'] ) : '';
		$id  = isset( $img['id'] ) ? (int) $img['id'] : 0;
		if ( '' === $url && 0 === $id ) {
			continue;
		}
		$out[ (string) (int) $i ] = array( 'url' => $url, 'id' => $id );
	}
	return $out;
}

function vielbunt_sanitize_tile_map( $tiles ) {
	if ( ! is_array( $tiles ) ) {
		return array();
	}
	$out = array();
	foreach ( $tiles as $i => $tile ) {
		if ( ! is_array( $tile ) ) {
			continue;
		}
		$entry = array();
		if ( isset( $tile['label'] ) && '' !== $tile['label'] ) {
			$entry['label'] = sanitize_text_field( (string) $tile['label'] );
		}
		if ( isset( $tile['url'] ) && '' !== $tile['url'] ) {
			$entry['url'] = esc_url_raw( (string) $tile['url'] );
		}
		if ( ! empty( $entry ) ) {
			$out[ (string) (int) $i ] = $entry;
		}
	}
	return $out;
}

/* Eingehende Attribute gemäß Schema bereinigen. */
function vielbunt_sanitize_settings( $block, $attrs ) {
	$schema = vielbunt_settings_schema();
	if ( ! isset( $schema[ $block ] ) || ! is_array( $attrs ) ) {
		return array();
	}
	$clean = array();
	foreach ( $schema[ $block ] as $key => $type ) {
		if ( ! array_key_exists( $key, $attrs ) ) {
			continue;
		}
		$val = $attrs[ $key ];
		switch ( $type ) {
			case 'url':
				$clean[ $key ] = esc_url_raw( (string) $val );
				break;
			case 'int':
				$clean[ $key ] = (int) $val;
				break;
			case 'textarea':
				$clean[ $key ] = sanitize_textarea_field( (string) $val );
				break;
			case 'images':
				$clean[ $key ] = vielbunt_sanitize_image_map( $val );
				break;
			case 'tiles':
				$clean[ $key ] = vielbunt_sanitize_tile_map( $val );
				break;
			case 'text':
			default:
				$clean[ $key ] = sanitize_text_field( (string) $val );
				break;
		}
	}
	return $clean;
}

/* REST: csd/v1/settings (GET + POST).
   Nur wer das Theme bearbeiten darf (edit_theme_options) kommt ran. */
function vielbunt_rest_permission() {
	return current_user_can( 'edit_theme_options' );
}

function vielbunt_rest_get_settings() {
	$store = get_option( VIELBUNT_SETTINGS_OPTION, array() );
	if ( ! is_array( $store ) ) {
		$store = array();
	}
	return rest_ensure_response( $store );
}

function vielbunt_rest_save_settings( WP_REST_Request $request ) {
	$block  = sanitize_key( (string) $request->get_param( 'block' ) );
	$attrs  = $request->get_param( 'attributes' );
	$schema = vielbunt_settings_schema();

	if ( ! isset( $schema[ $block ] ) ) {
		return new WP_Error( 'vielbunt_bad_block', __( 'Unbekannter Block.', 'vielbunt' ), array( 'status' => 400 ) );
	}

	$store = get_option( VIELBUNT_SETTINGS_OPTION, array() );
	if ( ! is_array( $store ) ) {
		$store = array();
	}
	$store[ $block ] = vielbunt_sanitize_settings( $block, is_array( $attrs ) ? $attrs : array() );
	update_option( VIELBUNT_SETTINGS_OPTION, $store );

	return rest_ensure_response( $store );
}

function vielbunt_register_rest() {
	register_rest_route(
		'csd/v1',
		'/settings',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'vielbunt_rest_get_settings',
				'permission_callback' => 'vielbunt_rest_permission',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'vielbunt_rest_save_settings',
				'permission_callback' => 'vielbunt_rest_permission',
			),
		)
	);
}
add_action( 'rest_api_init', 'vielbunt_register_rest' );

/* Dynamische Blöcke */

/* Hero */
function vielbunt_block_hero( $attributes = array() ) {
	// Reihenfolge je Wert: Block-Attribut -> wp_options -> Filter/Default.
	$a       = $attributes;
	$kicker  = vielbunt_setting( $a, 'hero', 'heroKicker',    apply_filters( 'vielbunt_hero_kicker', 'QUEERE COMMUNITY DARMSTADT' ) );
	$title   = vielbunt_setting( $a, 'hero', 'heroTitle',     apply_filters( 'vielbunt_hero_title',  'Schön, dass du da bist.' ) );
	$lead    = vielbunt_setting( $a, 'hero', 'heroLead',      apply_filters( 'vielbunt_hero_lead',   'Ob Beratung, Begegnung oder einfach abhängen. Bei vielbunt ist Platz für dich. Komm vorbei im Queeren Zentrum Darmstadt.' ) );
	$btn1_l  = vielbunt_setting( $a, 'hero', 'btnSolidLabel', 'Mitmachen' );
	$btn1_u  = vielbunt_setting( $a, 'hero', 'btnSolidUrl',   home_url( '/mitmachen/' ) );
	$btn2_l  = vielbunt_setting( $a, 'hero', 'btnGhostLabel', 'Zum Queeren Zentrum' );
	$btn2_u  = vielbunt_setting( $a, 'hero', 'btnGhostUrl',   home_url( '/queeres-zentrum-darmstadt/das-queere-zentrum/' ) );

	$bg = vielbunt_setting( $a, 'hero', 'bgUrl', '' );
	if ( '' !== $bg ) {
		$media = 'url(' . esc_url( $bg ) . ')';
	} else {
		$media = apply_filters( 'vielbunt_hero_media', 'linear-gradient(120deg,#2a2350,#5a2b6b)' );
	}

	ob_start();
	?>
	<section class="vb-hero">
		<div class="vb-hero__media" aria-hidden="true" style="background-image:<?php echo esc_attr( $media ); ?>"></div>
		<div class="vb-bars-anim" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
		<div class="vb-hero__text">
			<?php if ( $kicker ) : ?><p class="vb-kicker"><?php echo esc_html( $kicker ); ?></p><?php endif; ?>
			<h1><?php echo esc_html( $title ); ?></h1>
			<?php if ( $lead ) : ?><p class="vb-lead"><?php echo esc_html( $lead ); ?></p><?php endif; ?>
			<div class="vb-hero__btns">
				<a class="vb-btn-solid" href="<?php echo esc_url( $btn1_u ); ?>"><?php echo esc_html( $btn1_l ); ?> <?php echo vielbunt_icon( 'arrow' ); ?></a>
				<a class="vb-btn-ghost" href="<?php echo esc_url( $btn2_u ); ?>"><?php echo esc_html( $btn2_l ); ?></a>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/* Schnellzugriff */
function vielbunt_block_quicklinks( $attributes = array() ) {
	$tiles = array(
		array( 'Queeres Zentrum',      '/queeres-zentrum-darmstadt/das-queere-zentrum/', 'pink',   'community' ),
		array( 'Christopher Street Day', '/csd-darmstadt/',                              'green',  'flag' ),
		array( 'Treffbunt',            '/aktivitaeten/treffbunt/',                       'yellow', 'coffee' ),
		array( 'vielbunt sport*',      '/aktivitaeten/sport/',                           'blue',   'run' ),
		array( 'Refugees welcome',     '/aktivitaeten/refugees-welcome/',                'purple', 'heart' ),
		array( 'villaQ · Jugend',      '/aktivitaeten/villaq/',                          'orange', 'smile' ),
		array( 'Beratung',             '/queeres-zentrum-darmstadt/beratung/',           'green',  'chat' ),
		array( 'Mitmachen!',           '/mitmachen/',                                    'pink',   'hand' ),
	);
	$hex = array(
		'pink' => '#E6175F', 'green' => '#41B73D', 'yellow' => '#FFCB03',
		'blue' => '#13A3DC', 'purple' => '#6546B4', 'orange' => '#F59C00',
	);

	// Reihenfolge je Wert: Block-Attribut -> wp_options -> Default.
	$heading        = vielbunt_setting( $attributes, 'quicklinks', 'heading', 'Schnellzugriff' );
	$images         = vielbunt_setting( $attributes, 'quicklinks', 'images', array() );
	$tile_overrides = vielbunt_setting( $attributes, 'quicklinks', 'tiles', array() );
	if ( ! is_array( $images ) ) {
		$images = array();
	}
	if ( ! is_array( $tile_overrides ) ) {
		$tile_overrides = array();
	}

	$grid = '<div class="vb-grid vb-grid--quick">';
	foreach ( $tiles as $i => $t ) {
		list( $label, $url, $color, $icon ) = $t;
		// Per-tile label / URL can be overridden in the Site Editor.
		if ( isset( $tile_overrides[ $i ]['label'] ) && '' !== $tile_overrides[ $i ]['label'] ) {
			$label = $tile_overrides[ $i ]['label'];
		}
		if ( isset( $tile_overrides[ $i ]['url'] ) && '' !== $tile_overrides[ $i ]['url'] ) {
			$url = $tile_overrides[ $i ]['url'];
		}
		$hexc = $hex[ $color ];

		// Optionales Kachel-Hintergrundbild (im Editor je Kachel wählbar).
		$img_url = '';
		if ( isset( $images[ $i ]['url'] ) && '' !== $images[ $i ]['url'] ) {
			$img_url = $images[ $i ]['url'];
		}
		$layers = '';
		if ( $img_url ) {
			// Bild + Farb-Schleier (damit Icon und Text lesbar bleiben).
			$layers = sprintf(
				'<span class="vb-tile__bg" style="background-image:url(%1$s)"></span><span class="vb-tile__shade" style="background:%2$s"></span>',
				esc_url( $img_url ),
				esc_attr( $hexc )
			);
		}

		$grid .= sprintf(
			'<a class="vb-tile is-%1$s%2$s" href="%3$s" style="background:%4$s">%5$s<span class="vb-tile__icon">%6$s</span><span class="vb-tile__label">%7$s</span></a>',
			esc_attr( $color ),
			$img_url ? ' has-img' : '',
			esc_url( home_url( $url ) ),
			esc_attr( $hexc ),
			$layers,
			vielbunt_icon( $icon ),
			esc_html( $label )
		);
	}
	$grid .= '</div>';

	return sprintf(
		'<section class="vb-quick"><div class="vb-quick__inner"><h2 class="vb-quick__title">%1$s</h2>%2$s</div></section>',
		esc_html( $heading ),
		$grid
	);
}

/* Aktuelle Termine */
// Sharepic holen: erstmal Beitragsbild, sonst erstes img im Content
function vielbunt_event_image( $post ) {
	$thumb = get_the_post_thumbnail_url( $post, 'large' );
	if ( $thumb ) {
		return $thumb;
	}
	if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $post->post_content, $m ) ) {
		return $m[1];
	}
	return '';
}

function vielbunt_block_events( $attributes = array() ) {
	$limit = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 8;
	$data  = vielbunt_get_sorted_posts( $limit, 0 );
	$items = $data['events'];

	if ( empty( $items ) ) {
		return '<p class="vb-empty">' . esc_html__( 'Aktuell sind keine Termine eingetragen.', 'vielbunt' ) . '</p>';
	}

	$out = '<div class="vb-grid vb-grid--events">';
	$i   = 0;
	foreach ( $items as $item ) {
		$post  = $item['post'];
		$meta  = $item['meta'];
		$title = '' !== $meta['clean_title'] ? $meta['clean_title'] : get_the_title( $post );
		$url   = get_permalink( $post );
		$img   = vielbunt_event_image( $post );

		if ( $img ) {
			// Sharepic vorhanden: Originalbild unverändert zeigen, kein Overlay.
			// Alt-Text = Datum + Titel (für Screenreader).
			$alt  = trim( $meta['date_label'] . ' ' . $title );
			$out .= sprintf(
				'<a class="vb-card vb-card--img" href="%1$s"><img src="%2$s" alt="%3$s" loading="lazy" /></a>',
				esc_url( $url ),
				esc_url( $img ),
				esc_attr( $alt )
			);
		} else {
			// Kein Bild: farbige Kachel mit Datum + Titel (bisherige Variante).
			$color = vielbunt_card_color( $i );
			$out  .= sprintf(
				'<a class="vb-card is-%1$s" href="%2$s" style="background:var(--wp--preset--color--%1$s)"><span class="vb-card__date" style="color:var(--wp--preset--color--%1$s)">%3$s</span><span class="vb-card__title">%4$s</span></a>',
				esc_attr( $color ),
				esc_url( $url ),
				esc_html( $meta['date_label'] ),
				esc_html( $title )
			);
		}
		$i++;
	}
	$out .= '</div>';
	return $out;
}

/* News-Feed (Beiträge ohne Datum im Titel) */
function vielbunt_block_feed( $attributes = array() ) {
	$limit = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 6;
	$data  = vielbunt_get_sorted_posts( 0, $limit );
	$items = $data['feed'];

	if ( empty( $items ) ) {
		return '<p class="vb-empty">' . esc_html__( 'Noch keine Beiträge.', 'vielbunt' ) . '</p>';
	}

	$out = '<div class="vb-feed">';
	foreach ( $items as $post ) {
		$url     = get_permalink( $post );
		$cats    = get_the_category( $post->ID );
		$cat     = ! empty( $cats ) ? $cats[0]->name : '';
		$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 24 );
		$thumb   = get_the_post_thumbnail( $post, array( 72, 72 ) );

		$out .= '<article class="vb-feed__row">';
		if ( $thumb ) {
			$out .= '<a class="vb-feed__thumb" href="' . esc_url( $url ) . '">' . $thumb . '</a>';
		}
		$out .= '<div class="vb-feed__body">';
		if ( $cat ) {
			$out .= '<span class="vb-feed__cat">' . esc_html( $cat ) . '</span>';
		}
		$out .= '<h4 class="vb-feed__title"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h4>';
		$out .= '<p class="vb-feed__excerpt">' . esc_html( $excerpt ) . '</p>';
		$out .= '<p class="vb-feed__meta">' . esc_html( get_the_date( '', $post ) ) . '</p>';
		$out .= '</div></article>';
	}
	$out .= '</div>';
	return $out;
}

/* Logo in zwei Varianten:
   'color' für den Header, 'white-claim' für den Footer.
   Inline eingebunden damit CSS die Größe steuern kann. */
function vielbunt_block_logo( $attributes = array() ) {
	$variant = ( isset( $attributes['variant'] ) && 'white-claim' === $attributes['variant'] ) ? 'white-claim' : 'color';
	$white   = ( 'white-claim' === $variant );

	$file = $white ? 'logo-white.svg' : 'logo-color.svg';
	$path = get_stylesheet_directory() . '/assets/logo/' . $file;
	$svg  = is_readable( $path ) ? file_get_contents( $path ) : '';

	// XML-Deklaration raus sonst mag der Browser das inline nicht
	$svg = preg_replace( '/<\?xml.*?\?>/is', '', $svg );
	$svg = preg_replace( '/<!DOCTYPE.*?>/is', '', $svg );
	$svg = trim( $svg );

	$claim = $white ? '<span class="vb-logo__claim">Queere Community Darmstadt</span>' : '';

	return sprintf(
		'<a class="vb-logo-link vb-logo--%1$s" href="%2$s" aria-label="vielbunt – Startseite">%3$s%4$s</a>',
		esc_attr( $variant ),
		esc_url( home_url( '/' ) ),
		$svg,
		$claim
	);
}

/* Post-Hero für single/page: Beitragsbild mit pinkem Overlay wie auf der Startseite.
   Kein Bild vorhanden? Dann halt vollflächig pink. */
function vielbunt_block_post_hero( $attributes = array() ) {
	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return '';
	}

	$img = get_the_post_thumbnail_url( $post_id, 'full' );
	if ( $img ) {
		$media = 'url(' . esc_url( $img ) . ')';
	} else {
		$media = 'linear-gradient(135deg,#c0104a,#e6175f)';
	}

	$title  = get_the_title( $post_id );
	$kicker = '';
	if ( is_singular( 'post' ) ) {
		$cats = get_the_category( $post_id );
		$kicker = ! empty( $cats ) ? $cats[0]->name : 'Beitrag';
	}

	ob_start();
	?>
	<section class="vb-hero vb-hero--post">
		<div class="vb-hero__media" aria-hidden="true" style="background-image:<?php echo esc_attr( $media ); ?>"></div>
		<div class="vb-bars-anim" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
		<div class="vb-hero__text">
			<?php if ( $kicker ) : ?>
			<p class="vb-kicker"><?php echo esc_html( $kicker ); ?></p>
			<?php endif; ?>
			<h1><?php echo esc_html( $title ); ?></h1>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/* Footer-Links */
function vielbunt_block_footerlinks( $attributes = array() ) {
	$links = array(
		array( 'Datenschutzerklärung', 'https://www.vielbunt.org/datenschutzerklaerung/' ),
		array( 'Impressum',            'https://www.vielbunt.org/impressum/' ),
		array( 'Login',                'http://www.vielbunt.org/wp-login.php' ),
		array( 'Kontakt',              'https://www.vielbunt.org/kontakt-zu-vielbunt/' ),
		array( 'vielbunt Shop',        'http://shop.spreadshirt.de/vielbunt/' ),
		array( 'Spenden',              'https://www.vielbunt.org/spenden/' ),
		array( 'Karriere',             'https://vielbunt.jacando.io/career/' ),
	);
	$out = '<nav class="vb-footerlinks" aria-label="' . esc_attr__( 'Rechtliches und mehr', 'vielbunt' ) . '">';
	foreach ( $links as $l ) {
		$out .= sprintf( '<a href="%1$s">%2$s</a>', esc_url( $l[1] ), esc_html( $l[0] ) );
	}
	$out .= '</nav>';
	return $out;
}

/* Blöcke registrieren */
function vielbunt_register_blocks() {
	$common = array( 'api_version' => 3 );

	register_block_type( 'vielbunt/hero', array_merge( $common, array(
		'attributes'      => array(
			'bgUrl'         => array( 'type' => 'string', 'default' => '' ),
			'bgId'          => array( 'type' => 'number', 'default' => 0 ),
			'heroKicker'    => array( 'type' => 'string', 'default' => '' ),
			'heroTitle'     => array( 'type' => 'string', 'default' => '' ),
			'heroLead'      => array( 'type' => 'string', 'default' => '' ),
			'btnSolidLabel' => array( 'type' => 'string', 'default' => '' ),
			'btnSolidUrl'   => array( 'type' => 'string', 'default' => '' ),
			'btnGhostLabel' => array( 'type' => 'string', 'default' => '' ),
			'btnGhostUrl'   => array( 'type' => 'string', 'default' => '' ),
		),
		'render_callback' => 'vielbunt_block_hero',
	) ) );
	register_block_type( 'vielbunt/quicklinks', array_merge( $common, array(
		'attributes'      => array(
			'heading' => array( 'type' => 'string', 'default' => 'Schnellzugriff' ),
			'images'  => array( 'type' => 'object', 'default' => array() ),
			'tiles'   => array( 'type' => 'object', 'default' => array() ),
		),
		'render_callback' => 'vielbunt_block_quicklinks',
	) ) );
	register_block_type( 'vielbunt/events', array_merge( $common, array(
		'attributes'      => array( 'limit' => array( 'type' => 'number', 'default' => 8 ) ),
		'render_callback' => 'vielbunt_block_events',
	) ) );
	register_block_type( 'vielbunt/feed', array_merge( $common, array(
		'attributes'      => array( 'limit' => array( 'type' => 'number', 'default' => 6 ) ),
		'render_callback' => 'vielbunt_block_feed',
	) ) );
	register_block_type( 'vielbunt/logo', array_merge( $common, array(
		'attributes'      => array( 'variant' => array( 'type' => 'string', 'default' => 'color' ) ),
		'render_callback' => 'vielbunt_block_logo',
	) ) );
	register_block_type( 'vielbunt/footerlinks', array_merge( $common, array(
		'render_callback' => 'vielbunt_block_footerlinks',
	) ) );
	register_block_type( 'vielbunt/post-hero', array_merge( $common, array(
		'render_callback' => 'vielbunt_block_post_hero',
		'uses_context'    => array( 'postId', 'postType' ),
	) ) );
}
add_action( 'init', 'vielbunt_register_blocks' );

/* Editor-Script laden */
function vielbunt_block_editor_assets() {
	wp_enqueue_script(
		'vielbunt-blocks',
		get_stylesheet_directory_uri() . '/assets/editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-i18n', 'wp-block-editor', 'wp-components', 'wp-api-fetch' ),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'vielbunt_block_editor_assets' );

/* WP rendert manchmal „&“ in Navtiteln als literals „&“.
   Dieser Filter repariert das, betrifft nur den Nav-Block. */
function vielbunt_fix_nav_entities( $content, $block ) {
	if ( isset( $block['blockName'] ) && 'core/navigation' === $block['blockName'] ) {
		$content = str_replace( '\u0026', '&', $content );
	}
	return $content;
}
add_filter( 'render_block', 'vielbunt_fix_nav_entities', 10, 2 );

/* oEmbed-Vorschau: charset im <head> der Embed-Vorlage setzen.
   Ohne diese Deklaration interpretiert der Browser Umlaute im
   eingebetteten Iframe falsch (Mojibake). Früh (-100) ausgeben,
   damit es vor allem anderen im <head> steht. */
add_action( 'embed_head', function () {
	echo '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '" />' . "\n";
}, -100 );
