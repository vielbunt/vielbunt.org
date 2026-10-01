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
