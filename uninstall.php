<?php
/**
 * Uninstall Seyedcast.
 *
 * @package Seyedcast
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'seyedcast_settings' );

$episode_ids = get_posts(
	array(
		'post_type'      => 'seyedcast_episode',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post_status'    => 'any',
	)
);

foreach ( $episode_ids as $id ) {
	delete_post_meta( $id, '_seyedcast_show_id' );
	delete_post_meta( $id, '_seyedcast_audio_id' );
	delete_post_meta( $id, '_seyedcast_duration' );
	delete_post_meta( $id, '_seyedcast_episode_number' );
}

flush_rewrite_rules();
