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

	const TABLE_VERSION = '1';
	const TABLE_OPTION  = 'seyedcast_stats_db_version';
	const TOTAL_META    = '_seyedcast_total_view_count';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_create_table' ) );
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
			view_date date NOT NULL,
			unique_views int(10) unsigned NOT NULL DEFAULT 0,
			total_views int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY show_date (show_id, view_date),
			KEY view_date (view_date),
			KEY show_id (show_id)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::TABLE_OPTION, self::TABLE_VERSION, false );
	}

	/**
	 * Ensure table exists after install or update.
	 */
	public function maybe_create_table() {
		if ( get_option( self::TABLE_OPTION, '' ) === self::TABLE_VERSION ) {
			return;
		}
		self::create_table();
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

		wp_enqueue_style( 'seyedcast-admin', SEYEDCAST_URL . 'admin/css/admin.css', array(), SEYEDCAST_VERSION );
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
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
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'seyedcast_stats_chart' ),
				'i18n'    => array(
					'unique'  => __( 'بازدید یکتا', 'seyedcast' ),
					'total'   => __( 'کل بازدید', 'seyedcast' ),
					'loading' => __( 'در حال بارگذاری…', 'seyedcast' ),
					'error'   => __( 'خطا در بارگذاری آمار.', 'seyedcast' ),
					'empty'   => __( 'داده‌ای برای این بازه یافت نشد.', 'seyedcast' ),
				),
			)
		);
	}

	/**
	 * Record a view for a show.
	 *
	 * @param int  $show_id   Show post ID.
	 * @param bool $is_unique Whether this is a unique view (no cookie yet).
	 */
	public static function record_view( $show_id, $is_unique ) {
		global $wpdb;

		$show_id = absint( $show_id );
		if ( $show_id < 1 ) {
			return;
		}

		$total = (int) get_post_meta( $show_id, self::TOTAL_META, true );
		update_post_meta( $show_id, self::TOTAL_META, $total + 1 );

		if ( $is_unique ) {
			$unique = (int) get_post_meta( $show_id, Seyedcast_App::VIEW_META, true );
			update_post_meta( $show_id, Seyedcast_App::VIEW_META, $unique + 1 );
		}

		$table = self::table_name();
		$date  = current_time( 'Y-m-d' );

		if ( $is_unique ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (show_id, view_date, unique_views, total_views)
					VALUES (%d, %s, 1, 1)
					ON DUPLICATE KEY UPDATE unique_views = unique_views + 1, total_views = total_views + 1",
					$show_id,
					$date
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (show_id, view_date, unique_views, total_views)
					VALUES (%d, %s, 0, 1)
					ON DUPLICATE KEY UPDATE total_views = total_views + 1",
					$show_id,
					$date
				)
			);
		}
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
	 * Get cumulative view count for a show.
	 *
	 * @param int         $show_id Show ID.
	 * @param string|null $mode    unique|total or null for settings default.
	 * @return int
	 */
	public static function get_view_count( $show_id, $mode = null ) {
		$show_id = absint( $show_id );
		if ( $show_id < 1 ) {
			return 0;
		}

		if ( null === $mode ) {
			$mode = self::view_mode();
		}

		if ( 'total' === $mode ) {
			return (int) get_post_meta( $show_id, self::TOTAL_META, true );
		}

		return (int) get_post_meta( $show_id, Seyedcast_App::VIEW_META, true );
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
	 * Daily chart data for one or all shows.
	 *
	 * @param int    $show_id 0 for all shows aggregated.
	 * @param int    $days    Number of days.
	 * @param string $mode    unique|total.
	 * @return array{labels:string[],values:int[],total:int}
	 */
	public static function get_daily_chart_data( $show_id, $days, $mode = 'unique' ) {
		global $wpdb;

		$days    = max( 1, min( 365, (int) $days ) );
		$show_id = absint( $show_id );
		$mode    = 'total' === $mode ? 'total' : 'unique';
		$column  = 'total' === $mode ? 'total_views' : 'unique_views';
		$table   = self::table_name();

		$end   = current_time( 'Y-m-d' );
		$start = gmdate( 'Y-m-d', strtotime( $end . ' -' . ( $days - 1 ) . ' days' ) );

		$labels = array();
		$map    = array();
		$cursor = $start;
		while ( $cursor <= $end ) {
			$labels[]     = $cursor;
			$map[ $cursor ] = 0;
			$cursor       = gmdate( 'Y-m-d', strtotime( $cursor . ' +1 day' ) );
		}

		if ( $show_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT view_date, {$column} AS views
					FROM {$table}
					WHERE show_id = %d AND view_date BETWEEN %s AND %s
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
				$date = isset( $row['view_date'] ) ? (string) $row['view_date'] : '';
				if ( isset( $map[ $date ] ) ) {
					$map[ $date ] = (int) $row['views'];
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

		$formatted_labels = array_map(
			static function ( $date ) {
				return mysql2date( 'Y/m/d', $date, false );
			},
			$labels
		);

		return array(
			'labels' => $formatted_labels,
			'values' => $values,
			'total'  => $total,
		);
	}

	/**
	 * Summary totals for stats overview cards.
	 *
	 * @return array{unique:int,total:int,today_unique:int,today_total:int}
	 */
	public static function get_summary() {
		global $wpdb;

		$table = self::table_name();
		$today = current_time( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$today_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(unique_views), 0) AS unique_views, COALESCE(SUM(total_views), 0) AS total_views
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

		$unique_sum = 0;
		$total_sum  = 0;
		foreach ( $show_ids as $show_id ) {
			$unique_sum += (int) get_post_meta( $show_id, Seyedcast_App::VIEW_META, true );
			$total_sum  += (int) get_post_meta( $show_id, self::TOTAL_META, true );
		}

		return array(
			'unique'       => $unique_sum,
			'total'        => $total_sum,
			'today_unique' => isset( $today_row['unique_views'] ) ? (int) $today_row['unique_views'] : 0,
			'today_total'  => isset( $today_row['total_views'] ) ? (int) $today_row['total_views'] : 0,
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

		$show_id = isset( $_GET['show_id'] ) ? absint( $_GET['show_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$days    = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mode    = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'unique'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $show_id > 0 ) {
			$post = get_post( $show_id );
			if ( ! $post || 'seyedcast_show' !== $post->post_type ) {
				wp_send_json_error( array( 'message' => __( 'پادکست نامعتبر.', 'seyedcast' ) ), 400 );
			}
		}

		$data = self::get_daily_chart_data( $show_id, $days, $mode );
		wp_send_json_success( $data );
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
