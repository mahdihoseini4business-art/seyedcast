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
require_once plugin_dir_path( __FILE__ ) . 'includes/class-push.php';

global $wpdb;

$board_id = (int) get_option( 'seyedcast_comments_board_id', 0 );

delete_option( 'seyedcast_settings' );
delete_option( 'seyedcast_comments_board_id' );
delete_option( 'seyedcast_vapid_keys' );

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
Seyedcast_Push::drop_table();

$show_ids = get_posts(
	array(
		'post_type'      => 'seyedcast_show',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post_status'    => 'any',
	)
);

$episode_ids = get_posts(
	array(
		'post_type'      => 'seyedcast_episode',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post_status'    => 'any',
	)
);

foreach ( $episode_ids as $id ) {
	wp_delete_post( (int) $id, true );
}

foreach ( $show_ids as $id ) {
	wp_delete_post( (int) $id, true );
}

$terms = get_terms(
	array(
		'taxonomy'   => 'seyedcast_topic',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);
if ( ! is_wp_error( $terms ) ) {
	foreach ( $terms as $term_id ) {
		wp_delete_term( (int) $term_id, 'seyedcast_topic' );
	}
}

delete_option( 'seyedcast_db_version' );
delete_option( 'seyedcast_push_db_version' );
delete_option( 'seyedcast_listen_db_version' );

flush_rewrite_rules();
