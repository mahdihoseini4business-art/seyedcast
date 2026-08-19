<?php
/**
 * Uninstall Seyedcast.
 *
 * @package Seyedcast
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-stats.php';

delete_option( 'seyedcast_settings' );

Seyedcast_Stats::drop_table();

$show_ids = get_posts(
	array(
		'post_type'      => 'seyedcast_show',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post_status'    => 'any',
	)
);

foreach ( $show_ids as $id ) {
	delete_post_meta( $id, '_seyedcast_view_count' );
	delete_post_meta( $id, '_seyedcast_total_view_count' );
	delete_post_meta( $id, '_seyedcast_accent_color' );
}

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
	delete_post_meta( $id, '_seyedcast_view_count' );
	delete_post_meta( $id, '_seyedcast_total_view_count' );
}

flush_rewrite_rules();
