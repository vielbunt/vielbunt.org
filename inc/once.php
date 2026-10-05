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

/* Untermenü in allen Navigationen bearbeiten. $label = aktuelle Beschriftung
   des Untermenüs, $edit bekommt den Block (Array aus parse_blocks) und gibt
   ihn verändert zurück. Gespeichert wird direkt per $wpdb (kein Filter fasst
   den Inhalt an), der alte Inhalt landet als Backup in $backup_option. */
function vielbunt_once_edit_submenu( $label, $edit, $backup_option ) {
	global $wpdb;
	$navs    = get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'posts_per_page' => -1 ) );
	$changed = array();
	$backup  = array();
	$walk    = function ( $blocks ) use ( &$walk, $label, $edit ) {
		foreach ( $blocks as $i => $block ) {
			$name = isset( $block['attrs']['label'] ) ? trim( wp_strip_all_tags( html_entity_decode( $block['attrs']['label'], ENT_QUOTES, 'UTF-8' ) ) ) : '';
			if ( 'core/navigation-submenu' === $block['blockName'] && $name === $label ) {
				$blocks[ $i ] = $edit( $block );
			} elseif ( ! empty( $block['innerBlocks'] ) ) {
				$blocks[ $i ]['innerBlocks'] = $walk( $block['innerBlocks'] );
			}
		}
		return $blocks;
	};
	foreach ( $navs as $nav ) {
		$content = serialize_blocks( $walk( parse_blocks( $nav->post_content ) ) );
		if ( $content !== serialize_blocks( parse_blocks( $nav->post_content ) ) ) {
			$backup[ $nav->ID ] = $nav->post_content;
			$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $nav->ID ) );
			clean_post_cache( $nav->ID );
			$changed[] = $nav->post_title;
		}
	}
	if ( $backup ) {
		update_option( $backup_option, $backup, false );
	}
	return $changed;
}

/* Hilfsfunktion: Menüpunkt-Block bauen */
function vielbunt_once_nav_link( $label, $url, $page_id = 0 ) {
	$attrs = array( 'label' => $label, 'url' => $url, 'kind' => $page_id ? 'post-type' : 'custom', 'type' => $page_id ? 'page' : 'custom' );
	if ( $page_id ) {
		$attrs['id'] = (int) $page_id;
	}
	return array( 'blockName' => 'core/navigation-link', 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
}

/* innere Blöcke ersetzen und innerContent passend dazu setzen */
function vielbunt_once_set_children( $block, $children ) {
	$block['innerBlocks']  = array_values( $children );
	$block['innerContent'] = array_fill( 0, count( $children ), null );
	$block['innerHTML']    = '';
	return $block;
}

/* ---------- Ladezeit (Oktober 2026) ---------- */

/* Kachelgröße vb-card (inc/perf.php) für vorhandene Bilder nachziehen:
   Beitragsbilder der neuesten 60 Beiträge plus Hero- und Kachelbilder der
   Startseite. Läuft nicht im Seitenaufruf, sondern in kleinen Häppchen per
   Cron, damit niemand auf das Umrechnen warten muss. */
function vbperf_once_card_sizes() {
	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 60,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	$todo = array();
	foreach ( $ids as $post_id ) {
		$todo[] = (int) get_post_thumbnail_id( $post_id );
	}
	foreach ( array( 'csd_frontpage', 'vielbunt_frontpage' ) as $opt ) {
		$fp = get_option( $opt );
		if ( ! is_array( $fp ) ) {
			continue;
		}
		if ( ! empty( $fp['hero']['bgId'] ) ) {
			$todo[] = (int) $fp['hero']['bgId'];
		}
		if ( ! empty( $fp['quicklinks']['tiles'] ) && is_array( $fp['quicklinks']['tiles'] ) ) {
			foreach ( $fp['quicklinks']['tiles'] as $t ) {
				if ( ! empty( $t['imgId'] ) ) {
					$todo[] = (int) $t['imgId'];
				}
			}
		}
	}
	$todo = array_values( array_unique( array_filter( $todo ) ) );
	update_option( 'vbperf_card_queue', $todo, false );
	if ( ! wp_next_scheduled( 'vbperf_card_sizes_batch' ) ) {
		wp_schedule_single_event( time() + 30, 'vbperf_card_sizes_batch' );
	}
	return count( $todo ) . ' Bilder bekommen im Hintergrund die neue Kachelgröße';
}

function vbperf_card_sizes_batch() {
	$todo = get_option( 'vbperf_card_queue', array() );
	if ( ! is_array( $todo ) || ! $todo ) {
		delete_option( 'vbperf_card_queue' );
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$batch = array_splice( $todo, 0, 4 );
	// Queue vorher speichern, ein Bild das den Speicher sprengt soll nicht ewig wiederkommen
	update_option( 'vbperf_card_queue', $todo, false );
	foreach ( $batch as $id ) {
		if ( wp_attachment_is_image( $id ) ) {
			wp_update_image_subsizes( $id );
		}
	}
	if ( $todo ) {
		wp_schedule_single_event( time() + 60, 'vbperf_card_sizes_batch' );
	} else {
		delete_option( 'vbperf_card_queue' );
	}
}
add_action( 'vbperf_card_sizes_batch', 'vbperf_card_sizes_batch' );

/* WP-Optimize: Seiten 7 Tage statt 24 Stunden im Cache, jede Nacht vorladen,
   keine extra Kopie für Handys (das Theme liefert überall das gleiche HTML).
   Geht erst, seit das Theme beim Speichern im Website-Editor selbst leert
   (inc/deploy.php) und die Startseite nach Mitternacht neu baut (inc/perf.php).
   Ausnahmen (z. B. /qr/ und die Weiterleitungs-Seiten) bleiben unangetastet. */
function vbperf_once_wpo_settings() {
	if ( ! class_exists( 'WPO_Cache_Config' ) || ! is_callable( array( 'WPO_Cache_Config', 'instance' ) ) ) {
		return 'WP-Optimize nicht aktiv, nichts geändert';
	}
	$conf = WPO_Cache_Config::instance();
	$prev = $conf->get();
	if ( empty( $prev['enable_page_caching'] ) ) {
		return 'Seitencache ist aus, nichts geändert';
	}
	$new = $prev;

	$new['page_cache_length_value'] = 7;
	$new['page_cache_length_unit']  = 'days';
	$new['enable_mobile_caching']   = false;
	$new['enable_schedule_preload'] = true;
	if ( empty( $new['preload_schedule_type'] ) ) {
		$schedules                    = wp_get_schedules();
		$new['preload_schedule_type'] = isset( $schedules['wpo_daily'] ) ? 'wpo_daily' : 'daily';
	}

	$r = $conf->update( $new );
	if ( is_wp_error( $r ) ) {
		return 'Fehler beim Schreiben: ' . $r->get_error_message();
	}
	// was WP-Optimize sonst beim Speichern der Einstellungen selbst macht
	if ( class_exists( 'WP_Optimize_Page_Cache_Preloader' ) && is_callable( array( 'WP_Optimize_Page_Cache_Preloader', 'instance' ) ) ) {
		WP_Optimize_Page_Cache_Preloader::instance()->cache_settings_updated( $conf->get(), $prev );
	}
	return sprintf(
		'Lebensdauer %s %s -> 7 days, Handy-Kopie %s -> aus, Vorladen %s (%s)',
		$prev['page_cache_length_value'],
		$prev['page_cache_length_unit'],
		empty( $prev['enable_mobile_caching'] ) ? 'aus' : 'an',
		empty( $prev['enable_schedule_preload'] ) ? 'neu an' : 'war schon an',
		$new['preload_schedule_type']
	);
}

/* ---------- .htaccess ---------- */

/* Regeln für Apache, die das Theme mitbringt. Kaputte .htaccess = ganze Seite
   weg, deshalb nur sehr einfache Regeln, alles in IfModule, und nach dem
   Schreiben ein Test. Klappt der nicht, kommt die alte Datei zurück und die
   Regeln bleiben aus (Option vbperf_htaccess_off). */
function vbperf_htaccess_lines() {
	return array(
		'# JavaScript hat bisher gar keine Cache-Dauer, CSS schon. Alle Dateien haben ?ver= oder einen Hash im Namen.',
		'<IfModule mod_expires.c>',
		'ExpiresActive On',
		'ExpiresByType application/x-javascript "access plus 1 year"',
		'ExpiresByType application/javascript "access plus 1 year"',
		'ExpiresByType text/javascript "access plus 1 year"',
		'</IfModule>',
		'# Brotli, falls der Server es kann (sonst passiert hier nix)',
		'<IfModule mod_brotli.c>',
		'AddOutputFilterByType BROTLI_COMPRESS text/html text/css text/plain text/xml application/x-javascript application/javascript text/javascript application/json image/svg+xml',
		'</IfModule>',
		'# HTTPS merken (erstmal kurz, spaeter hochsetzen)',
		'<IfModule mod_headers.c>',
		'Header always set Strict-Transport-Security "max-age=300"',
		'</IfModule>',
	);
}

/* Adressen ohne Schrägstrich am Ende (vielbunt.org/spenden) hängt bisher
   WordPress den Schrägstrich an, das kostet einen ganzen Seitenaufbau. Apache
   kann das in Millisekunden. Kurzlinks wie /qr und /wl-... bleiben bei
   WordPress, genauso alles mit Punkt, Parametern, Dateien und Ordnern. */
function vbperf_slash_rules( $rules ) {
	if ( get_option( 'vbperf_htaccess_off' ) || ! is_string( $rules ) || false === strpos( $rules, "RewriteBase /\n" ) ) {
		return $rules;
	}
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	if ( '' === $host || ! preg_match( '/^[a-z0-9.-]+$/i', $host ) ) {
		return $rules;
	}
	$add = "RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$\n"
		. "RewriteCond %{QUERY_STRING} ^$\n"
		. "RewriteCond %{REQUEST_URI} !^/(wp-admin|wp-includes|wp-content|wp-json)(/|$)\n"
		. "RewriteCond %{REQUEST_URI} !^/(qr|wl|anmeldungen|@)\n"
		. "RewriteCond %{REQUEST_FILENAME} !-f\n"
		. "RewriteCond %{REQUEST_FILENAME} !-d\n"
		. 'RewriteRule ^([^.]*[^./])$ https://' . $host . "/$1/ [R=301,L]\n";
	return str_replace( "RewriteBase /\n", "RewriteBase /\n" . $add, $rules );
}
add_filter( 'mod_rewrite_rules', 'vbperf_slash_rules' );

/* Seite noch erreichbar? Startseite (an WP-Optimize vorbei) und eine statische Datei */
function vbperf_site_answers() {
	$urls = array(
		add_query_arg( 'vbperf-check', time(), home_url( '/' ) ),
		get_stylesheet_uri() . '?vbperf-check=' . time(),
	);
	foreach ( $urls as $url ) {
		$res = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 0, 'sslverify' => false ) );
		if ( is_wp_error( $res ) ) {
			return 'Test nicht möglich: ' . $res->get_error_message();
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			return 'HTTP ' . $code . ' für ' . $url;
		}
	}
	return '';
}

function vbperf_once_htaccess() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	$file = get_home_path() . '.htaccess';
	if ( ! file_exists( $file ) || ! is_writable( $file ) ) {
		return '.htaccess nicht beschreibbar, nichts geändert';
	}
	$before = file_get_contents( $file );
	if ( false === $before || '' === trim( $before ) ) {
		return '.htaccess leer oder nicht lesbar, nichts geändert';
	}
	update_option( 'vbperf_htaccess_backup', $before, false );
	delete_option( 'vbperf_htaccess_off' );

	$ok_markers = insert_with_markers( $file, 'vielbunt Ladezeit', vbperf_htaccess_lines() );
	$ok_rewrite = save_mod_rewrite_rules(); // schreibt den WordPress-Block neu, inkl. vbperf_slash_rules()
	clearstatcache();

	$problem = vbperf_site_answers();
	if ( '' !== $problem ) {
		file_put_contents( $file, $before );
		update_option( 'vbperf_htaccess_off', 1, false );
		return 'Zurückgerollt (' . $problem . '), alte .htaccess wiederhergestellt';
	}

	// Stichprobe: /impressum muss jetzt von Apache weitergeleitet werden
	$res  = wp_remote_head( home_url( '/impressum' ), array( 'timeout' => 20, 'redirection' => 0, 'sslverify' => false ) );
	$slash = is_wp_error( $res ) ? 'nicht prüfbar' : ( wp_remote_retrieve_header( $res, 'x-redirect-by' ) ? 'noch WordPress' : 'Apache' );

	return sprintf(
		'Cache-Regeln %s, Schrägstrich-Regel %s (Weiterleitung kommt von: %s), Backup in vbperf_htaccess_backup',
		$ok_markers ? 'drin' : 'NICHT geschrieben',
		$ok_rewrite ? 'drin' : 'NICHT geschrieben',
		$slash
	);
}
