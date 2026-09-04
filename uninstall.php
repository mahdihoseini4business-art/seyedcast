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
require_once plugin_dir_path( __FILE__ ) . 'includes/class-listen-stats.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-notify-leads.php';

global $wpdb;

$board_id = (int) get_option( 'seyedcast_comments_board_id', 0 );

delete_option( 'seyedcast_settings' );
delete_option( 'seyedcast_comments_board_id' );

if ( $board_id ) {
	wp_delete_post( $board_id, true );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_seyedcast_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_seyedcast_' ) . '%'
	)
);

Seyedcast_Stats::drop_table();
Seyedcast_Listen_Stats::drop_table();
Seyedcast_Notify_Leads::drop_table();

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
	delete_post_meta( $id, '_seyedcast_listen_sum_pct' );
	delete_post_meta( $id, '_seyedcast_listen_count' );
}

flush_rewrite_rules();
