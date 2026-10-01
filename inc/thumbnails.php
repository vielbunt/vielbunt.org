<?php
/**
 * Beitragsbilder automatisch setzen (gleiche Datei in csd-darmstadt.de und
 * vielbunt.org).
 *
 * Viele Beiträge haben ihr Sharepic nur im Text, aber kein Beitragsbild.
 * Dann fehlen sie in Übersichten und Link-Vorschauen. Wir nehmen das erste
 * Bild aus der eigenen Mediathek im Text als Beitragsbild. Der Inhalt des
 * Beitrags bleibt unverändert, fremde Bilder (z. B. alte Facebook-Links)
 * nehmen wir nie.
 *
 * - neue Beiträge: beim ersten Veröffentlichen/Einplanen
 * - bestehende Beiträge: einmaliger Schritt, Liste der geänderten Beiträge
 *   als Backup in der Option {prefix}_beitragsbilder_backup
 *
 * @package vielbunt-themes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Anhang-ID des ersten eigenen Bildes im Inhalt, 0 wenn keins */
function vbthumb_first_image_id( $content ) {
	if ( preg_match_all( '/<img[^>]+>/i', (string) $content, $tags ) ) {
		foreach ( $tags[0] as $tag ) {
			if ( preg_match( '/wp-image-(\d+)/', $tag, $m ) ) {
				$id = (int) $m[1];
				if ( $id && 'attachment' === get_post_type( $id ) ) {
					return $id;
				}
			}
			if ( preg_match( '/\ssrc=["\']([^"\']+)["\']/i', $tag, $m ) && false !== strpos( $m[1], '/wp-content/uploads/' ) ) {
				// Größen-Endung (-1024x768) weg, sonst findet WordPress den Anhang nicht
				$url = preg_replace( '/-\d+x\d+(?=\.[a-z]{3,4}$)/i', '', strtok( $m[1], '?' ) );
				$id  = attachment_url_to_postid( $url );
				if ( $id ) {
					return (int) $id;
				}
			}
		}
	}
	return 0;
}

function vbthumb_on_publish( $post_id, $post, $update, $post_before ) {
	if ( 'post' !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
		return;
	}
	if ( $post_before && in_array( $post_before->post_status, array( 'publish', 'future' ), true ) ) {
		return;
	}
	if ( has_post_thumbnail( $post_id ) ) {
		return;
	}
	$id = vbthumb_first_image_id( $post->post_content );
	if ( $id ) {
		set_post_thumbnail( $post_id, $id );
	}
}
add_action( 'wp_after_insert_post', 'vbthumb_on_publish', 20, 4 );

/* einmaliger Schritt für alle bestehenden Beiträge ohne Beitragsbild */
function vbthumb_once_backfill( $prefix ) {
	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'future' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ) ),
		)
	);
	$set = array();
	foreach ( $ids as $post_id ) {
		$img = vbthumb_first_image_id( get_post_field( 'post_content', $post_id ) );
		if ( $img ) {
			set_post_thumbnail( $post_id, $img );
			$set[] = (int) $post_id;
		}
	}
	update_option( $prefix . '_beitragsbilder_backup', $set, false );
	return count( $set ) . ' von ' . count( $ids ) . ' Beiträgen ohne Beitragsbild haben jetzt eins (erstes eigenes Bild im Text). Liste in ' . $prefix . '_beitragsbilder_backup';
}
