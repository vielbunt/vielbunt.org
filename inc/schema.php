<?php
/**
 * Strukturierte Daten (JSON-LD) auf der Startseite: WebSite und der Verein
 * als Organisation, damit Google Name, Logo, Adresse und Social-Media-Konten
 * sauber zuordnen kann.
 *
 * @package vielbunt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function vielbunt_schema_output() {
	if ( ! is_front_page() ) {
		return;
	}
	$home  = home_url( '/' );
	$graph = array(
		array(
			'@type'      => 'WebSite',
			'@id'        => $home . '#website',
			'url'        => $home,
			'name'       => 'vielbunt e.V.',
			'inLanguage' => 'de-DE',
			'publisher'  => array( '@id' => $home . '#organization' ),
		),
		array(
			'@type'         => 'NGO',
			'@id'           => $home . '#organization',
			'name'          => 'vielbunt e.V.',
			'alternateName' => 'Queeres Zentrum Darmstadt',
			'description'   => 'Queere Community Darmstadt',
			'url'           => $home,
			'logo'          => get_stylesheet_directory_uri() . '/assets/icons/icon-512.png',
			'email'         => 'info@vielbunt.org',
			'telephone'     => '+49 6151 9715632',
			'address'       => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => 'Kranichsteiner Straße 81',
				'postalCode'      => '64289',
				'addressLocality' => 'Darmstadt',
				'addressCountry'  => 'DE',
			),
			'sameAs'        => array( 'https://instagram.com/vielbunt' ),
		),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'vielbunt_schema_output', 5 );

/* Termine: Beiträge mit Datum im Titel ("18.10.: Pubquiz") sagen Google, dass
   es eine Veranstaltung ist. Abgesagt/entfällt/fällt aus = abgesagt, online/
   Zoom = Online-Termin. Den Ort kennen wir sicher nur beim Queeren Zentrum
   (und der villaQ im selben Haus), sonst bekommt Google nur "Darmstadt",
   raten wollen wir keinen Ort. */
function vielbunt_schema_event_time( $text ) {
	if ( preg_match( '/(?:^|\D)(\d{1,2})[:.](\d{2})\s*Uhr|(?:^|\D)(\d{1,2}):(\d{2})(?!\d)|(?:um|ab)\s*(\d{1,2})\s*Uhr/u', $text, $m ) ) {
		$h   = (int) ( $m[1] ?: ( $m[3] ?: $m[5] ) );
		$min = (int) ( $m[2] ?: ( $m[4] ?: 0 ) );
		if ( $h < 24 && $min < 60 ) {
			return sprintf( '%02d:%02d', $h, $min );
		}
	}
	return '';
}

function vielbunt_schema_event_output() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$post   = get_queried_object();
	$parsed = vielbunt_parse_event_date( $post->post_title, $post );
	if ( ! $parsed ) {
		return;
	}
	$title = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
	$text  = mb_strtolower( $title . ' ' . mb_substr( wp_strip_all_tags( $post->post_content ), 0, 400 ) );
	$date  = wp_date( 'Y-m-d', $parsed['timestamp'], new DateTimeZone( 'UTC' ) );
	$time  = vielbunt_schema_event_time( preg_replace( '/^\s*[\d.\s\-\x{2013}]+/u', '', $title ) );
	if ( $time ) {
		$dt   = new DateTime( $date . ' ' . $time, wp_timezone() );
		$date = $dt->format( 'c' );
	}
	$online    = (bool) preg_match( '/\bonline\b|zoom/u', mb_strtolower( $title ) );
	$cancelled = (bool) preg_match( '/abgesagt|entfällt|entfaellt|fällt aus/u', mb_strtolower( $title ) );
	$zentrum   = (bool) preg_match( '/queere[nms]? zentrum|villa ?q|kranichsteiner/u', $text );

	if ( $online ) {
		$location = array( '@type' => 'VirtualLocation', 'url' => get_permalink( $post ) );
	} elseif ( $zentrum ) {
		$location = array(
			'@type'   => 'Place',
			'name'    => 'Queeres Zentrum Darmstadt',
			'address' => array( '@type' => 'PostalAddress', 'streetAddress' => 'Kranichsteiner Straße 81', 'postalCode' => '64289', 'addressLocality' => 'Darmstadt', 'addressCountry' => 'DE' ),
		);
	} else {
		$location = array(
			'@type'   => 'Place',
			'name'    => 'Darmstadt',
			'address' => array( '@type' => 'PostalAddress', 'addressLocality' => 'Darmstadt', 'addressRegion' => 'Hessen', 'addressCountry' => 'DE' ),
		);
	}
	$img   = get_the_post_thumbnail_url( $post, 'large' );
	$event = array(
		'@context'            => 'https://schema.org',
		'@type'               => 'Event',
		'name'                => '' !== $parsed['clean_title'] ? $parsed['clean_title'] : $title,
		'startDate'           => $date,
		'eventStatus'         => $cancelled ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
		'eventAttendanceMode' => $online ? 'https://schema.org/OnlineEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode',
		'location'            => $location,
		'image'               => array( $img ? $img : get_stylesheet_directory_uri() . '/assets/share.png' ),
		'description'         => wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 40 ),
		'url'                 => get_permalink( $post ),
		'organizer'           => array( '@type' => 'NGO', 'name' => 'vielbunt e.V.', 'url' => home_url( '/' ) ),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'vielbunt_schema_event_output', 6 );
