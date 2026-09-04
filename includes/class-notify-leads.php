<?php
/**
 * Notify-me lead capture: table, AJAX, admin list.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Notify_Leads
 */
class Seyedcast_Notify_Leads {

	const TABLE_VERSION = '1';
	const TABLE_OPTION  = 'seyedcast_notify_db_version';
	const NONCE_ACTION  = 'seyedcast_notify_lead';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'maybe_create_table' ), 5 );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
		add_action( 'wp_ajax_seyedcast_notify_lead', array( $this, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_seyedcast_notify_lead', array( $this, 'ajax_submit' ) );
	}

	/**
	 * Whether the feature is enabled in settings.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = Seyedcast_Settings::get();
		return ! empty( $settings['notify_enabled'] );
	}

	/**
	 * Button label from settings.
	 *
	 * @return string
	 */
	public static function button_text() {
		$settings = Seyedcast_Settings::get();
		$text     = isset( $settings['notify_button_text'] ) ? trim( (string) $settings['notify_button_text'] ) : '';
		if ( '' === $text ) {
			$text = __( 'پادکست جدید اومد خبرم کن', 'seyedcast' );
		}
		return $text;
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'seyedcast_notify_leads';
	}

	/**
	 * Whether table exists.
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
	 * Create leads table.
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			phone varchar(32) NOT NULL DEFAULT '',
			show_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY phone_show (phone, show_id),
			KEY show_id (show_id),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::TABLE_OPTION, self::TABLE_VERSION, false );
	}

	/**
	 * Ensure table exists.
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
	 * Drop table on uninstall.
	 */
	public static function drop_table() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table_name() );
		delete_option( self::TABLE_OPTION );
	}

	/**
	 * Register admin submenu.
	 */
	public function register_menu() {
		add_submenu_page(
			'seyedcast',
			__( 'اطلاع‌رسانی', 'seyedcast' ),
			__( 'اطلاع‌رسانی', 'seyedcast' ),
			'manage_options',
			'seyedcast-notify-leads',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle delete / CSV export before headers are sent further.
	 */
	public function handle_admin_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'seyedcast-notify-leads' !== $page ) {
			return;
		}

		if ( isset( $_GET['seyedcast_export'] ) && 'csv' === sanitize_key( wp_unslash( $_GET['seyedcast_export'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'seyedcast_notify_export' );
			$show_id = isset( $_GET['show_id'] ) ? absint( $_GET['show_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->export_csv( $show_id );
		}

		if ( isset( $_GET['action'], $_GET['lead_id'] ) && 'delete' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$lead_id = absint( $_GET['lead_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'seyedcast_notify_delete_' . $lead_id );
			self::delete_lead( $lead_id );
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'seyedcast-notify-leads',
						'deleted' => '1',
						'show_id' => isset( $_GET['show_id'] ) ? absint( $_GET['show_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	/**
	 * Render admin list page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::ensure_table();

		if ( ! isset( $_GET['show_id'] ) || '' === wp_unslash( $_GET['show_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$show_id = -1;
		} else {
			$show_id = absint( wp_unslash( $_GET['show_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$leads = self::get_leads( $show_id );
		$shows   = get_posts(
			array(
				'post_type'      => 'seyedcast_show',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		include SEYEDCAST_PATH . 'admin/views/notify-leads-page.php';
	}

	/**
	 * Fetch leads, optionally filtered by show.
	 *
	 * @param int $show_id -1 = all, 0 = archive only, >0 = specific show.
	 * @return array
	 */
	public static function get_leads( $show_id = -1 ) {
		global $wpdb;

		self::ensure_table();
		$table = self::table_name();

		if ( -1 === (int) $show_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE show_id = %d ORDER BY created_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $show_id
				),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete a lead by ID.
	 *
	 * @param int $lead_id Lead ID.
	 * @return bool
	 */
	public static function delete_lead( $lead_id ) {
		global $wpdb;
		$lead_id = absint( $lead_id );
		if ( $lead_id < 1 ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete( self::table_name(), array( 'id' => $lead_id ), array( '%d' ) );
	}

	/**
	 * Stream CSV export and exit.
	 *
	 * @param int $show_id Filter show (-1 all when 0 and missing — use GET).
	 */
	private function export_csv( $show_id ) {
		if ( ! isset( $_GET['show_id'] ) || '' === wp_unslash( $_GET['show_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$filter = -1;
		} else {
			$filter = absint( $show_id );
		}
		$leads = self::get_leads( $filter );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=seyedcast-notify-leads.csv' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $out ) {
			exit;
		}

		// UTF-8 BOM for Excel.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, array( 'ID', 'Name', 'Phone', 'Show ID', 'Show Title', 'Created At' ) );

		foreach ( $leads as $row ) {
			$sid   = isset( $row['show_id'] ) ? (int) $row['show_id'] : 0;
			$title = $sid > 0 ? get_the_title( $sid ) : __( 'همه پادکست‌ها', 'seyedcast' );
			fputcsv(
				$out,
				array(
					isset( $row['id'] ) ? $row['id'] : '',
					isset( $row['name'] ) ? $row['name'] : '',
					isset( $row['phone'] ) ? $row['phone'] : '',
					$sid,
					$title,
					isset( $row['created_at'] ) ? $row['created_at'] : '',
				)
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Normalize Persian/Arabic digits and strip separators.
	 *
	 * @param string $phone Raw phone.
	 * @return string
	 */
	public static function normalize_phone( $phone ) {
		$phone = (string) $phone;
		$map   = array(
			'۰' => '0',
			'۱' => '1',
			'۲' => '2',
			'۳' => '3',
			'۴' => '4',
			'۵' => '5',
			'۶' => '6',
			'۷' => '7',
			'۸' => '8',
			'۹' => '9',
			'٠' => '0',
			'١' => '1',
			'٢' => '2',
			'٣' => '3',
			'٤' => '4',
			'٥' => '5',
			'٦' => '6',
			'٧' => '7',
			'٨' => '8',
			'٩' => '9',
		);
		$phone = strtr( $phone, $map );
		$phone = preg_replace( '/[\s\-\(\)]+/', '', $phone );
		$phone = preg_replace( '/[^\d\+]/', '', $phone );

		if ( 0 === strpos( $phone, '0098' ) ) {
			$phone = '+98' . substr( $phone, 4 );
		} elseif ( 0 === strpos( $phone, '98' ) && 12 === strlen( $phone ) ) {
			$phone = '+' . $phone;
		}

		if ( 0 === strpos( $phone, '+98' ) ) {
			$rest = substr( $phone, 3 );
			if ( 0 === strpos( $rest, '9' ) && 10 === strlen( $rest ) ) {
				$phone = '0' . $rest;
			}
		}

		return $phone;
	}

	/**
	 * Validate Iranian mobile after normalize.
	 *
	 * @param string $phone Normalized phone.
	 * @return bool
	 */
	public static function is_valid_phone( $phone ) {
		return (bool) preg_match( '/^09\d{9}$/', $phone );
	}

	/**
	 * Resolve show_id for storage.
	 *
	 * @param int $show_id Requested show ID.
	 * @return int
	 */
	public static function resolve_show_id( $show_id ) {
		$show_id = absint( $show_id );
		if ( $show_id < 1 ) {
			return 0;
		}
		$show = get_post( $show_id );
		if ( ! $show || 'seyedcast_show' !== $show->post_type || 'publish' !== $show->post_status ) {
			return 0;
		}
		return $show_id;
	}

	/**
	 * Public AJAX submit handler.
	 */
	public function ajax_submit() {
		self::rate_limit_ajax( 'notify_lead', 20, 60 );

		if ( ! self::is_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'این قابلیت فعال نیست.', 'seyedcast' ) ), 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'نشست نامعتبر است. صفحه را تازه کنید.', 'seyedcast' ) ), 403 );
		}

		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? self::normalize_phone( sanitize_text_field( wp_unslash( $_POST['phone'] ) ) ) : '';
		$show  = isset( $_POST['show_id'] ) ? self::resolve_show_id( absint( $_POST['show_id'] ) ) : 0;

		$name = trim( $name );
		$name_len = function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name );
		if ( '' === $name || $name_len < 2 ) {
			wp_send_json_error( array( 'message' => __( 'نام را درست وارد کنید.', 'seyedcast' ) ), 400 );
		}
		if ( $name_len > 100 ) {
			$name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 100 ) : substr( $name, 0, 100 );
		}

		if ( ! self::is_valid_phone( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'شماره موبایل معتبر نیست (مثال: ۰۹۱۲۱۲۳۴۵۶۷).', 'seyedcast' ) ), 400 );
		}

		$result = self::insert_lead( $name, $phone, $show );
		if ( 'duplicate' === $result ) {
			wp_send_json_error( array( 'message' => __( 'این شماره قبلاً برای همین بخش ثبت شده است.', 'seyedcast' ) ), 409 );
		}
		if ( true !== $result ) {
			wp_send_json_error( array( 'message' => __( 'ثبت انجام نشد. دوباره تلاش کنید.', 'seyedcast' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'message' => __( 'ثبت شد. وقتی پادکست جدید بیاد خبرت می‌کنیم.', 'seyedcast' ),
			)
		);
	}

	/**
	 * Insert a lead row.
	 *
	 * @param string $name  Name.
	 * @param string $phone Normalized phone.
	 * @param int    $show_id Show ID.
	 * @return true|string true on success, 'duplicate' or 'error'.
	 */
	public static function insert_lead( $name, $phone, $show_id = 0 ) {
		global $wpdb;

		self::ensure_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			self::table_name(),
			array(
				'name'       => $name,
				'phone'      => $phone,
				'show_id'    => absint( $show_id ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s' )
		);

		if ( false === $ok ) {
			if ( $wpdb->last_error && false !== stripos( $wpdb->last_error, 'Duplicate' ) ) {
				return 'duplicate';
			}
			// Fallback check for duplicate.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . self::table_name() . ' WHERE phone = %s AND show_id = %d LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$phone,
					absint( $show_id )
				)
			);
			if ( $exists ) {
				return 'duplicate';
			}
			return 'error';
		}

		return true;
	}

	/**
	 * Simple IP rate limit for public AJAX.
	 *
	 * @param string $action Action key.
	 * @param int    $limit  Max hits.
	 * @param int    $window Window seconds.
	 */
	private static function rate_limit_ajax( $action, $limit = 20, $window = 60 ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : 'unknown';
		$key = 'seyedcast_rl_' . md5( $action . '|' . $ip );
		$hit = (int) get_transient( $key );

		if ( $hit >= $limit ) {
			wp_send_json_error( array( 'message' => __( 'درخواست‌های زیاد. کمی بعد دوباره تلاش کنید.', 'seyedcast' ) ), 429 );
		}

		set_transient( $key, $hit + 1, $window );
	}
}
