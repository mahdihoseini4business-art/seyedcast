<?php
/**
 * View statistics: daily tracking, admin charts.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Stats
 */
class Seyedcast_Stats {

	const TABLE_VERSION = '2';
	const TABLE_OPTION  = 'seyedcast_stats_db_version';
	const TOTAL_META    = '_seyedcast_total_view_count';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'init', array( $this, 'maybe_create_table' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_seyedcast_stats_chart', array( $this, 'ajax_chart' ) );
	}

	/**
	 * Daily views table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'seyedcast_views';
	}

	/**
	 * Create or upgrade stats table.
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			show_id bigint(20) unsigned NOT NULL,
			episode_id bigint(20) unsigned NOT NULL DEFAULT 0,
			view_date date NOT NULL,
			unique_views int(10) unsigned NOT NULL DEFAULT 0,
			total_views int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY show_episode_date (show_id, episode_id, view_date),
			KEY view_date (view_date),
			KEY show_id (show_id),
			KEY episode_id (episode_id)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::TABLE_OPTION, self::TABLE_VERSION, false );
	}

	/**
	 * Upgrade stats table schema.
	 */
	public static function upgrade_table() {
		if ( ! self::table_exists() ) {
			self::create_table();
			return;
		}

		self::repair_table_schema();

		if ( get_option( self::TABLE_OPTION, '' ) !== self::TABLE_VERSION ) {
			update_option( self::TABLE_OPTION, self::TABLE_VERSION, false );
		}
	}

	/**
	 * Fix legacy indexes/columns (safe to run on every request).
	 */
	private static function repair_table_schema() {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$column = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'episode_id'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $column ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN episode_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER show_id" );
		}

		// Legacy unique key (show_id + view_date) blocks multiple episodes per show/day.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$old_index = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'show_date'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! empty( $old_index ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE {$table} DROP INDEX show_date" );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$new_index = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'show_episode_date'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $new_index ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY show_episode_date (show_id, episode_id, view_date)" );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$episode_index = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'episode_id'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $episode_index ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE {$table} ADD KEY episode_id (episode_id)" );
		}
	}

	/**
	 * Whether the stats table exists.
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
	 * Ensure table exists after install or update.
	 */
	public function maybe_create_table() {
		self::ensure_table();
	}

	/**
	 * Create table when missing (frontend-safe).
	 */
	public static function ensure_table() {
		if ( ! self::table_exists() ) {
			self::create_table();
			return;
		}

		global $wpdb;
		$table  = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$column = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'episode_id'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $column ) ) {
			update_option( self::TABLE_OPTION, '1', false );
		}

		self::upgrade_table();
	}

	/**
	 * Whether daily stats table schema is usable.
	 *
	 * @return bool
	 */
	public static function schema_ready() {
		if ( ! self::table_exists() ) {
			return false;
		}

		global $wpdb;
		$table  = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$column = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'episode_id'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return ! empty( $column );
	}

	/**
	 * Register stats submenu.
	 */
	public function register_menu() {
		add_submenu_page(
			'seyedcast',
			__( 'آمار', 'seyedcast' ),
			__( 'آمار', 'seyedcast' ),
			'manage_options',
			'seyedcast-stats',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue stats page assets.
	 *
	 * @param string $hook Admin hook.
	 */
	public function enqueue( $hook ) {
		if ( 'seyedcast_page_seyedcast-stats' !== $hook ) {
			return;
		}

		$episodes_by_show = self::get_episodes_grouped_by_show();
		$initial_chart    = self::get_daily_chart_data( 0, 30, self::view_mode(), 0 );

		wp_enqueue_style( 'seyedcast-admin', SEYEDCAST_URL . 'admin/css/admin.css', array(), SEYEDCAST_VERSION );
		wp_enqueue_script(
			'chartjs',
			SEYEDCAST_URL . 'admin/js/lib/chart.umd.min.js',
			array(),
			'4.4.1',
			true
		);
		wp_enqueue_script(
			'seyedcast-stats',
			SEYEDCAST_URL . 'admin/js/stats.js',
			array( 'jquery', 'chartjs' ),
			SEYEDCAST_VERSION,
			true
		);

		wp_localize_script(
			'seyedcast-stats',
			'seyedcastStats',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'seyedcast_stats_chart' ),
				'episodes'     => $episodes_by_show,
				'initialChart' => $initial_chart,
				'i18n'         => array(
					'unique'         => __( 'بازدید یکتا', 'seyedcast' ),
					'total'          => __( 'کل بازدید', 'seyedcast' ),
					'loading'        => __( 'در حال بارگذاری…', 'seyedcast' ),
					'error'          => __( 'خطا در بارگذاری آمار.', 'seyedcast' ),
					'chartMissing'   => __( 'کتابخانه نمودار بارگذاری نشد. صفحه را رفرش کنید یا افزونه را دوباره آپلود کنید.', 'seyedcast' ),
					'empty'          => __( 'داده‌ای برای این بازه یافت نشد. چند صفحه پادکست/اپیزود باز کنید تا آمار ثبت شود.', 'seyedcast' ),
					'scopeAllShows'  => __( 'همه پادکست‌ها', 'seyedcast' ),
					'scopeShow'      => __( 'یک پادکست', 'seyedcast' ),
					'scopeEpisode'   => __( 'یک اپیزود', 'seyedcast' ),
					'pickShow'       => __( '— انتخاب پادکست —', 'seyedcast' ),
					'pickEpisode'    => __( '— انتخاب اپیزود —', 'seyedcast' ),
					'noEpisodes'     => __( 'اپیزودی برای این پادکست نیست.', 'seyedcast' ),
				),
			)
		);
	}

	/**
	 * Record a show view.
	 *
	 * @param int  $show_id   Show post ID.
	 * @param bool $is_unique Whether this is a unique view.
	 */
	public static function record_show_view( $show_id, $is_unique ) {
		$show_id = absint( $show_id );
		if ( $show_id < 1 ) {
			return;
		}
		self::increment_meta( $show_id, $is_unique );
		self::increment_daily( $show_id, 0, $is_unique );
	}

	/**
	 * Record an episode view.
	 *
	 * @param int  $episode_id Episode post ID.
	 * @param bool $is_unique  Whether this is a unique view.
	 */
	public static function record_episode_view( $episode_id, $is_unique ) {
		$episode_id = absint( $episode_id );
		if ( $episode_id < 1 ) {
			return;
		}

		$show_id = Seyedcast_Meta::get_show_id( $episode_id );
		if ( $show_id < 1 ) {
			return;
		}

		self::increment_meta( $episode_id, $is_unique );
		self::increment_daily( $show_id, $episode_id, $is_unique );
	}

	/**
	 * Increment cumulative post meta counters.
	 *
	 * @param int  $post_id   Show or episode post ID.
	 * @param bool $is_unique Unique view flag.
	 */
	private static function increment_meta( $post_id, $is_unique ) {
		$total = (int) get_post_meta( $post_id, self::TOTAL_META, true );
		update_post_meta( $post_id, self::TOTAL_META, $total + 1 );

		if ( $is_unique ) {
			$unique = (int) get_post_meta( $post_id, Seyedcast_App::VIEW_META, true );
			update_post_meta( $post_id, Seyedcast_App::VIEW_META, $unique + 1 );
		}
	}

	/**
	 * Site-local calendar date (Y-m-d).
	 *
	 * @param int $days_ago Days before today (0 = today).
	 * @return string
	 */
	public static function local_date( $days_ago = 0 ) {
		$days_ago = max( 0, (int) $days_ago );
		$dt       = new DateTimeImmutable( 'now', wp_timezone() );

		if ( $days_ago > 0 ) {
			$dt = $dt->modify( '-' . $days_ago . ' days' );
		}

		return $dt->format( 'Y-m-d' );
	}

	/**
	 * Normalize DB view_date to Y-m-d.
	 *
	 * @param mixed $raw Raw DB value.
	 * @return string
	 */
	private static function normalize_view_date( $raw ) {
		if ( $raw instanceof DateTimeInterface ) {
			return $raw->format( 'Y-m-d' );
		}

		$raw = trim( (string) $raw );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}/', $raw, $matches ) ) {
			return $matches[0];
		}

		return $raw;
	}

	/**
	 * Chart axis label for a local date.
	 *
	 * @param string $ymd Y-m-d.
	 * @return string
	 */
	private static function format_chart_label( $ymd ) {
		$dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $ymd, wp_timezone() );
		if ( ! $dt ) {
			return $ymd;
		}

		return wp_date( 'Y/m/d', $dt->getTimestamp() );
	}

	/**
	 * Upsert daily view row.
	 *
	 * @param int  $show_id    Show post ID.
	 * @param int  $episode_id Episode post ID (0 for show-level).
	 * @param bool $is_unique  Unique view flag.
	 */
	private static function increment_daily( $show_id, $episode_id, $is_unique, $retry = true ) {
		global $wpdb;

		self::ensure_table();

		$table      = self::table_name();
		$date       = self::local_date( 0 );
		$show_id    = absint( $show_id );
		$episode_id = absint( $episode_id );

		if ( $is_unique ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (show_id, episode_id, view_date, unique_views, total_views)
					VALUES (%d, %d, %s, 1, 1)
					ON DUPLICATE KEY UPDATE unique_views = unique_views + 1, total_views = total_views + 1",
					$show_id,
					$episode_id,
					$date
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (show_id, episode_id, view_date, unique_views, total_views)
					VALUES (%d, %d, %s, 0, 1)
					ON DUPLICATE KEY UPDATE total_views = total_views + 1",
					$show_id,
					$episode_id,
					$date
				)
			);
		}

		if ( false === $result && $retry ) {
			self::repair_table_schema();
			self::increment_daily( $show_id, $episode_id, $is_unique, false );
		}
	}

	/**
	 * Backward-compatible alias for show views.
	 *
	 * @param int  $show_id   Show post ID.
	 * @param bool $is_unique Whether this is a unique view.
	 */
	public static function record_view( $show_id, $is_unique ) {
		self::record_show_view( $show_id, $is_unique );
	}

	/**
	 * View count mode from settings.
	 *
	 * @return string unique|total
	 */
	public static function view_mode() {
		$settings = Seyedcast_Settings::get();
		$mode     = isset( $settings['view_count_mode'] ) ? $settings['view_count_mode'] : 'unique';
		return in_array( $mode, array( 'unique', 'total' ), true ) ? $mode : 'unique';
	}

	/**
	 * Get cumulative view count for a show or episode.
	 *
	 * @param int         $post_id Show or episode post ID.
	 * @param string|null $mode    unique|total or null for settings default.
	 * @return int
	 */
	public static function get_view_count( $post_id, $mode = null ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return 0;
		}

		if ( null === $mode ) {
			$mode = self::view_mode();
		}

		if ( 'total' === $mode ) {
			return (int) get_post_meta( $post_id, self::TOTAL_META, true );
		}

		return (int) get_post_meta( $post_id, Seyedcast_App::VIEW_META, true );
	}

	/**
	 * Label for view count based on mode.
	 *
	 * @param string|null $mode unique|total.
	 * @return string
	 */
	public static function view_label( $mode = null ) {
		if ( null === $mode ) {
			$mode = self::view_mode();
		}
		return 'total' === $mode
			? __( 'کل بازدید', 'seyedcast' )
			: __( 'بازدید یکتا', 'seyedcast' );
	}

	/**
	 * Daily chart data for shows or episodes.
	 *
	 * @param int    $show_id    Show ID (0 for all shows).
	 * @param int    $days       Number of days.
	 * @param string $mode       unique|total.
	 * @param int    $episode_id Episode ID (0 for show-level rows).
	 * @return array{labels:string[],values:int[],total:int}
	 */
	public static function get_daily_chart_data( $show_id, $days, $mode = 'unique', $episode_id = 0 ) {
		global $wpdb;

		self::ensure_table();

		$days       = max( 1, min( 365, (int) $days ) );
		$show_id    = absint( $show_id );
		$episode_id = absint( $episode_id );
		$mode       = 'total' === $mode ? 'total' : 'unique';
		$column     = 'total' === $mode ? 'total_views' : 'unique_views';
		$table      = self::table_name();

		$labels           = array();
		$formatted_labels = array();
		$map              = array();
		$start            = self::local_date( $days - 1 );
		$end              = self::local_date( 0 );

		for ( $i = 0; $i < $days; $i++ ) {
			$day                = self::local_date( $days - 1 - $i );
			$labels[]           = $day;
			$formatted_labels[] = self::format_chart_label( $day );
			$map[ $day ]        = 0;
		}

		if ( $episode_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT view_date, {$column} AS views
					FROM {$table}
					WHERE episode_id = %d AND view_date BETWEEN %s AND %s
					ORDER BY view_date ASC",
					$episode_id,
					$start,
					$end
				),
				ARRAY_A
			);
		} elseif ( $show_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT view_date, SUM({$column}) AS views
					FROM {$table}
					WHERE show_id = %d AND view_date BETWEEN %s AND %s
					GROUP BY view_date
					ORDER BY view_date ASC",
					$show_id,
					$start,
					$end
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT view_date, SUM({$column}) AS views
					FROM {$table}
					WHERE view_date BETWEEN %s AND %s
					GROUP BY view_date
					ORDER BY view_date ASC",
					$start,
					$end
				),
				ARRAY_A
			);
		}

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$date = self::normalize_view_date( isset( $row['view_date'] ) ? $row['view_date'] : '' );
				if ( isset( $map[ $date ] ) ) {
					$map[ $date ] += (int) $row['views'];
				}
			}
		}

		$values = array();
		$total  = 0;
		foreach ( $labels as $label ) {
			$val      = (int) $map[ $label ];
			$values[] = $val;
			$total   += $val;
		}

		return array(
			'labels' => $formatted_labels,
			'values' => $values,
			'total'  => $total,
		);
	}

	/**
	 * Summary totals for stats overview cards.
	 *
	 * @return array{unique:int,total:int,today_unique:int,today_total:int,ep_unique:int,ep_total:int}
	 */
	public static function get_summary() {
		global $wpdb;

		self::ensure_table();

		$table = self::table_name();
		$today = self::local_date( 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$today_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(CASE WHEN episode_id = 0 THEN unique_views ELSE 0 END), 0) AS show_unique,
					COALESCE(SUM(CASE WHEN episode_id = 0 THEN total_views ELSE 0 END), 0) AS show_total,
					COALESCE(SUM(CASE WHEN episode_id > 0 THEN unique_views ELSE 0 END), 0) AS ep_unique,
					COALESCE(SUM(CASE WHEN episode_id > 0 THEN total_views ELSE 0 END), 0) AS ep_total
				FROM {$table}
				WHERE view_date = %s",
				$today
			),
			ARRAY_A
		);

		$show_ids = get_posts(
			array(
				'post_type'      => 'seyedcast_show',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$episode_ids = get_posts(
			array(
				'post_type'      => 'seyedcast_episode',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$unique_sum = 0;
		$total_sum  = 0;
		foreach ( $show_ids as $show_id ) {
			$unique_sum += (int) get_post_meta( $show_id, Seyedcast_App::VIEW_META, true );
			$total_sum  += (int) get_post_meta( $show_id, self::TOTAL_META, true );
		}

		$ep_unique_sum = 0;
		$ep_total_sum  = 0;
		foreach ( $episode_ids as $episode_id ) {
			$ep_unique_sum += (int) get_post_meta( $episode_id, Seyedcast_App::VIEW_META, true );
			$ep_total_sum  += (int) get_post_meta( $episode_id, self::TOTAL_META, true );
		}

		return array(
			'unique'       => $unique_sum,
			'total'        => $total_sum,
			'today_unique' => isset( $today_row['show_unique'] ) ? (int) $today_row['show_unique'] : 0,
			'today_total'  => isset( $today_row['show_total'] ) ? (int) $today_row['show_total'] : 0,
			'ep_unique'    => $ep_unique_sum,
			'ep_total'     => $ep_total_sum,
			'ep_today_unique' => isset( $today_row['ep_unique'] ) ? (int) $today_row['ep_unique'] : 0,
			'ep_today_total'  => isset( $today_row['ep_total'] ) ? (int) $today_row['ep_total'] : 0,
		);
	}

	/**
	 * AJAX: chart data.
	 */
	public function ajax_chart() {
		check_ajax_referer( 'seyedcast_stats_chart', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'seyedcast' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$request    = wp_unslash( $_REQUEST );
		$show_id    = isset( $request['show_id'] ) ? absint( $request['show_id'] ) : 0;
		$episode_id = isset( $request['episode_id'] ) ? absint( $request['episode_id'] ) : 0;
		$days       = isset( $request['days'] ) ? absint( $request['days'] ) : 30;
		$mode       = isset( $request['mode'] ) ? sanitize_key( $request['mode'] ) : 'unique';

		if ( $episode_id > 0 ) {
			$post = get_post( $episode_id );
			if ( ! $post || 'seyedcast_episode' !== $post->post_type ) {
				wp_send_json_error( array( 'message' => __( 'اپیزود نامعتبر.', 'seyedcast' ) ), 400 );
			}
			$show_id = 0;
		} elseif ( $show_id > 0 ) {
			$post = get_post( $show_id );
			if ( ! $post || 'seyedcast_show' !== $post->post_type ) {
				wp_send_json_error( array( 'message' => __( 'پادکست نامعتبر.', 'seyedcast' ) ), 400 );
			}
		}

		$data = self::get_daily_chart_data( $show_id, $days, $mode, $episode_id );
		wp_send_json_success( $data );
	}

	/**
	 * Episodes grouped by show for stats filters.
	 *
	 * @return array<int, array<int, array{id:int,label:string}>>
	 */
	public static function get_episodes_grouped_by_show() {
		$shows = get_posts(
			array(
				'post_type'      => 'seyedcast_show',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'fields'         => 'ids',
			)
		);

		$episodes = array();
		foreach ( $shows as $show_id ) {
			$items = get_posts(
				array(
					'post_type'      => 'seyedcast_episode',
					'posts_per_page' => -1,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'post_status'    => array( 'publish', 'draft', 'private', 'future' ),
					'meta_key'       => '_seyedcast_show_id',
					'meta_value'     => (string) $show_id,
				)
			);

			$episodes[ (string) $show_id ] = array();
			foreach ( $items as $episode ) {
				$number = get_post_meta( $episode->ID, '_seyedcast_episode_number', true );
				$label  = get_the_title( $episode );
				if ( $number ) {
					$label = sprintf( __( 'اپیزود %1$s — %2$s', 'seyedcast' ), $number, $label );
				}
				$episodes[ (string) $show_id ][] = array(
					'id'    => (int) $episode->ID,
					'label' => $label,
				);
			}
		}

		return $episodes;
	}

	/**
	 * Render stats admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$shows   = get_posts(
			array(
				'post_type'      => 'seyedcast_show',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => array( 'publish', 'draft', 'private' ),
			)
		);
		$summary = self::get_summary();

		$listen_show_id = isset( $_GET['listen_show'] ) ? absint( wp_unslash( $_GET['listen_show'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$listen_report  = Seyedcast_Listen_Stats::get_episodes_report( $listen_show_id );

		include SEYEDCAST_PATH . 'admin/views/stats-page.php';
	}

	/**
	 * Drop stats table on uninstall.
	 */
	public static function drop_table() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table_name() );
		delete_option( self::TABLE_OPTION );
	}
}
