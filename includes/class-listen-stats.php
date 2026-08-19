<?php
/**
 * Anonymous listen progress tracking and admin reports.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Listen_Stats
 */
class Seyedcast_Listen_Stats {

	const TABLE_VERSION = '1';
	const TABLE_OPTION  = 'seyedcast_listen_db_version';
	const SUM_META      = '_seyedcast_listen_sum_pct';
	const COUNT_META    = '_seyedcast_listen_count';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'maybe_create_table' ), 5 );
		add_action( 'wp_ajax_seyedcast_listen_progress', array( $this, 'ajax_progress' ) );
		add_action( 'wp_ajax_nopriv_seyedcast_listen_progress', array( $this, 'ajax_progress' ) );
	}

	/**
	 * Listen progress table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'seyedcast_listen_progress';
	}

	/**
	 * Whether the listen progress table exists.
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Create listen progress table.
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			episode_id bigint(20) unsigned NOT NULL,
			listener_id varchar(64) NOT NULL,
			max_pct tinyint(3) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY episode_listener (episode_id, listener_id),
			KEY episode_id (episode_id)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::TABLE_OPTION, self::TABLE_VERSION, false );
	}

	/**
	 * Ensure table exists after install or update.
	 */
	public static function ensure_table() {
		if ( ! self::table_exists() ) {
			self::create_table();
		}
	}

	/**
	 * Create table on init when missing.
	 */
	public function maybe_create_table() {
		self::ensure_table();
	}

	/**
	 * Drop listen progress table on uninstall.
	 */
	public static function drop_table() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table_name() );
		delete_option( self::TABLE_OPTION );
	}

	/**
	 * Validate anonymous listener ID format.
	 *
	 * @param string $listener_id Listener UUID or hex id.
	 * @return bool
	 */
	public static function is_valid_listener_id( $listener_id ) {
		$listener_id = (string) $listener_id;
		if ( preg_match( '/^[a-f0-9]{32}$/i', $listener_id ) ) {
			return true;
		}
		return (bool) preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $listener_id );
	}

	/**
	 * Record max listen progress for an episode/listener pair.
	 *
	 * @param int    $episode_id  Episode post ID.
	 * @param string $listener_id Anonymous listener ID.
	 * @param int    $pct         Progress percent (0-100).
	 * @return bool Whether data was updated.
	 */
	public static function record_progress( $episode_id, $listener_id, $pct ) {
		global $wpdb;

		$episode_id = absint( $episode_id );
		$pct        = max( 0, min( 100, (int) $pct ) );

		if ( $episode_id < 1 || ! self::is_valid_listener_id( $listener_id ) ) {
			return false;
		}

		$post = get_post( $episode_id );
		if ( ! $post || 'seyedcast_episode' !== $post->post_type || 'publish' !== $post->post_status ) {
			return false;
		}

		self::ensure_table();

		$table = self::table_name();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, max_pct FROM {$table} WHERE episode_id = %d AND listener_id = %s",
				$episode_id,
				$listener_id
			)
		);

		if ( $existing ) {
			$old_max = (int) $existing->max_pct;
			if ( $pct <= $old_max ) {
				return false;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'max_pct'    => $pct,
					'updated_at' => $now,
				),
				array(
					'id' => (int) $existing->id,
				),
				array( '%d', '%s' ),
				array( '%d' )
			);

			self::adjust_episode_meta( $episode_id, $pct - $old_max, false );
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$table,
			array(
				'episode_id'  => $episode_id,
				'listener_id' => substr( $listener_id, 0, 64 ),
				'max_pct'     => $pct,
				'updated_at'  => $now,
			),
			array( '%d', '%s', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return false;
		}

		self::adjust_episode_meta( $episode_id, $pct, true );
		return true;
	}

	/**
	 * Update cached aggregate meta for an episode.
	 *
	 * @param int  $episode_id Episode post ID.
	 * @param int  $sum_delta  Amount to add to sum_pct.
	 * @param bool $new_listener Whether this is a new listener.
	 */
	private static function adjust_episode_meta( $episode_id, $sum_delta, $new_listener ) {
		$sum   = (int) get_post_meta( $episode_id, self::SUM_META, true );
		$count = (int) get_post_meta( $episode_id, self::COUNT_META, true );

		update_post_meta( $episode_id, self::SUM_META, $sum + max( 0, (int) $sum_delta ) );

		if ( $new_listener ) {
			update_post_meta( $episode_id, self::COUNT_META, $count + 1 );
		}
	}

	/**
	 * Average listen percent and listener count for one episode.
	 *
	 * @param int $episode_id Episode post ID.
	 * @return array{avg:int,count:int}
	 */
	public static function get_episode_avg( $episode_id ) {
		$episode_id = absint( $episode_id );
		$sum        = (int) get_post_meta( $episode_id, self::SUM_META, true );
		$count      = (int) get_post_meta( $episode_id, self::COUNT_META, true );

		if ( $count < 1 ) {
			return array(
				'avg'   => 0,
				'count' => 0,
			);
		}

		return array(
			'avg'   => (int) round( $sum / $count ),
			'count' => $count,
		);
	}

	/**
	 * Episodes with listen data for admin report.
	 *
	 * @param int $show_id Optional show filter (0 = all).
	 * @return array<int, array{episode_id:int,episode_title:string,show_id:int,show_title:string,avg:int,count:int}>
	 */
	public static function get_episodes_report( $show_id = 0 ) {
		$show_id = absint( $show_id );

		$args = array(
			'post_type'      => 'seyedcast_episode',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => self::COUNT_META,
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		);

		if ( $show_id > 0 ) {
			$args['meta_query'][] = array(
				'key'   => '_seyedcast_show_id',
				'value' => $show_id,
			);
		}

		$episodes = get_posts( $args );
		$rows     = array();

		foreach ( $episodes as $episode ) {
			$stats   = self::get_episode_avg( $episode->ID );
			$ep_show = Seyedcast_Meta::get_show_id( $episode->ID );
			$show    = $ep_show ? get_post( $ep_show ) : null;

			$rows[] = array(
				'episode_id'    => (int) $episode->ID,
				'episode_title' => get_the_title( $episode ),
				'show_id'       => (int) $ep_show,
				'show_title'    => $show ? get_the_title( $show ) : '',
				'avg'           => (int) $stats['avg'],
				'count'         => (int) $stats['count'],
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				if ( $a['avg'] === $b['avg'] ) {
					return $b['count'] <=> $a['count'];
				}
				return $b['avg'] <=> $a['avg'];
			}
		);

		return $rows;
	}

	/**
	 * AJAX: record listen progress from the sticky player.
	 */
	public function ajax_progress() {
		self::rate_limit_ajax( 'listen_progress', 60, 60 );

		$episode_id  = isset( $_POST['episode_id'] ) ? absint( wp_unslash( $_POST['episode_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$pct         = isset( $_POST['pct'] ) ? absint( wp_unslash( $_POST['pct'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$listener_id = isset( $_POST['listener_id'] ) ? sanitize_text_field( wp_unslash( $_POST['listener_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $episode_id < 1 || ! self::is_valid_listener_id( $listener_id ) ) {
			wp_send_json_error( array( 'message' => __( 'درخواست نامعتبر.', 'seyedcast' ) ), 400 );
		}

		$updated = self::record_progress( $episode_id, $listener_id, $pct );

		wp_send_json_success(
			array(
				'updated' => $updated,
			)
		);
	}

	/**
	 * Simple IP rate limit for public AJAX.
	 *
	 * @param string $action Action slug.
	 * @param int    $limit  Max hits per window.
	 * @param int    $window Window seconds.
	 */
	private static function rate_limit_ajax( $action, $limit = 60, $window = 60 ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : 'unknown';
		$key = 'seyedcast_rl_' . md5( $action . '|' . $ip );
		$hit = (int) get_transient( $key );

		if ( $hit >= $limit ) {
			wp_send_json_error( array( 'message' => __( 'درخواست‌های زیاد. کمی بعد دوباره تلاش کنید.', 'seyedcast' ) ), 429 );
		}

		set_transient( $key, $hit + 1, $window );
	}
}
