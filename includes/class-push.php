<?php
/**
 * Web Push subscriptions and episode notifications.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Push
 */
class Seyedcast_Push {

	const TABLE_VERSION   = '1';
	const TABLE_OPTION    = 'seyedcast_push_db_version';
	const VAPID_OPTION    = 'seyedcast_vapid_keys';
	const SENT_META       = '_seyedcast_push_sent';
	const CRON_HOOK       = 'seyedcast_send_episode_push';
	const BATCH_SIZE      = 50;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'maybe_create_table' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'prompt_markup' ), 25 );

		add_action( 'wp_ajax_seyedcast_push_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_seyedcast_push_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_seyedcast_push_unsubscribe', array( $this, 'ajax_unsubscribe' ) );
		add_action( 'wp_ajax_nopriv_seyedcast_push_unsubscribe', array( $this, 'ajax_unsubscribe' ) );

		add_action( 'transition_post_status', array( $this, 'on_episode_status' ), 10, 3 );
		add_action( self::CRON_HOOK, array( $this, 'cron_send_episode' ), 10, 2 );

		add_action( 'admin_notices', array( $this, 'admin_library_notice' ) );
		add_action( 'wp_ajax_seyedcast_push_test', array( $this, 'ajax_test_send' ) );
	}

	/**
	 * Whether push feature is enabled in settings and PWA is on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$settings = Seyedcast_Settings::get();
		return ! empty( $settings['pwa_enabled'] ) && ! empty( $settings['push_enabled'] );
	}

	/**
	 * Whether the web-push library is available.
	 *
	 * @return bool
	 */
	public static function library_ready() {
		self::maybe_load_autoload();
		return class_exists( '\Minishlink\WebPush\WebPush' );
	}

	/**
	 * Load Composer autoload once.
	 */
	public static function maybe_load_autoload() {
		static $tried = false;
		if ( $tried ) {
			return;
		}
		$tried = true;
		$file  = SEYEDCAST_PATH . 'vendor/autoload.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Subscriptions table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'seyedcast_push_subscriptions';
	}

	/**
	 * Whether the subscriptions table exists.
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
	 * Create subscriptions table.
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			endpoint text NOT NULL,
			endpoint_hash char(64) NOT NULL,
			p256dh varchar(255) NOT NULL,
			auth varchar(255) NOT NULL,
			user_agent varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY endpoint_hash (endpoint_hash),
			KEY updated_at (updated_at)
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
		delete_option( self::VAPID_OPTION );
	}

	/**
	 * Count stored subscriptions.
	 *
	 * @return int
	 */
	public static function subscriber_count() {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return 0;
		}
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Get or generate VAPID key pair.
	 *
	 * @return array{publicKey:string,privateKey:string}|null
	 */
	public static function get_vapid_keys() {
		$stored = get_option( self::VAPID_OPTION, null );
		if ( is_array( $stored ) && ! empty( $stored['publicKey'] ) && ! empty( $stored['privateKey'] ) ) {
			return $stored;
		}

		self::maybe_load_autoload();
		if ( ! class_exists( '\Minishlink\WebPush\VAPID' ) ) {
			return null;
		}

		$keys = null;
		try {
			$keys = \Minishlink\WebPush\VAPID::createVapidKeys();
		} catch ( Exception $e ) {
			$keys = self::generate_vapid_keys_openssl();
		} catch ( Error $e ) {
			$keys = self::generate_vapid_keys_openssl();
		}

		if ( ! is_array( $keys ) || empty( $keys['publicKey'] ) || empty( $keys['privateKey'] ) ) {
			$keys = self::generate_vapid_keys_openssl();
		}

		if ( ! is_array( $keys ) || empty( $keys['publicKey'] ) || empty( $keys['privateKey'] ) ) {
			return null;
		}

		$keys = array(
			'publicKey'  => $keys['publicKey'],
			'privateKey' => $keys['privateKey'],
		);
		update_option( self::VAPID_OPTION, $keys, false );
		return $keys;
	}

	/**
	 * Fallback VAPID key generation via OpenSSL (P-256).
	 *
	 * @return array{publicKey:string,privateKey:string}|null
	 */
	private static function generate_vapid_keys_openssl() {
		if ( ! function_exists( 'openssl_pkey_new' ) ) {
			return null;
		}

		$key = openssl_pkey_new(
			array(
				'private_key_type' => OPENSSL_KEYTYPE_EC,
				'curve_name'       => 'prime256v1',
			)
		);
		if ( ! $key ) {
			return null;
		}

		$details = openssl_pkey_get_details( $key );
		if ( empty( $details['ec']['x'] ) || empty( $details['ec']['y'] ) || empty( $details['ec']['d'] ) ) {
			return null;
		}

		$x = $details['ec']['x'];
		$y = $details['ec']['y'];
		$d = $details['ec']['d'];

		// Uncompressed public key: 0x04 || X || Y.
		$public  = "\x04" . str_pad( $x, 32, "\0", STR_PAD_LEFT ) . str_pad( $y, 32, "\0", STR_PAD_LEFT );
		$private = str_pad( $d, 32, "\0", STR_PAD_LEFT );

		return array(
			'publicKey'  => self::base64url_encode( $public ),
			'privateKey' => self::base64url_encode( $private ),
		);
	}

	/**
	 * Base64 URL-safe encode without padding.
	 *
	 * @param string $data Binary data.
	 * @return string
	 */
	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Ensure VAPID keys exist (activation / first enable).
	 */
	public static function ensure_vapid_keys() {
		self::get_vapid_keys();
	}

	/**
	 * Admin notice when push is on but library missing.
	 */
	public function admin_library_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! self::enabled() ) {
			return;
		}
		if ( self::library_ready() ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Seyedcast: برای ارسال پوش نوتیفیکیشن باید وابستگی‌های Composer نصب شوند (پوشه vendor). در ریشه افزونه دستور composer install --no-dev را اجرا کنید.', 'seyedcast' );
		echo '</p></div>';
	}

	/**
	 * Enqueue push prompt script.
	 */
	public function enqueue() {
		if ( ! self::enabled() || ! Seyedcast_Assets::is_seyedcast_context() ) {
			return;
		}

		$keys = self::get_vapid_keys();
		if ( ! $keys ) {
			return;
		}

		wp_enqueue_script(
			'seyedcast-push',
			SEYEDCAST_URL . 'public/js/push.js',
			array(),
			SEYEDCAST_VERSION,
			true
		);

		$settings = Seyedcast_Settings::get();
		wp_localize_script(
			'seyedcast-push',
			'seyedcastPush',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'seyedcast_push' ),
				'swUrl'         => home_url( '/seyedcast-sw.js' ),
				'vapidPublic'   => $keys['publicKey'],
				'storageKey'    => 'seyedcast_push_prompt_dismissed',
				'subscribedKey' => 'seyedcast_push_subscribed',
				'iconUrl'       => $this->icon_url(),
				'delayMs'       => 3500,
				'i18n'          => array(
					'title'   => __( 'اعلان اپیزودهای جدید', 'seyedcast' ),
					'message' => __( 'با فعال‌سازی اعلان‌ها، هر وقت اپیزود جدیدی منتشر شود خبردار می‌شوید.', 'seyedcast' ),
					'enable'  => __( 'فعال کردن اعلان‌ها', 'seyedcast' ),
					'later'   => __( 'بعداً', 'seyedcast' ),
					'close'   => __( 'بستن', 'seyedcast' ),
					'success' => __( 'اعلان‌ها فعال شد.', 'seyedcast' ),
					'denied'  => __( 'اجازه اعلان داده نشد. از تنظیمات مرورگر می‌توانید فعال کنید.', 'seyedcast' ),
				),
				'appName'       => ! empty( $settings['pwa_name'] ) ? $settings['pwa_name'] : 'Seyedcast',
			)
		);
	}

	/**
	 * Notification / prompt icon URL.
	 *
	 * @return string
	 */
	private function icon_url() {
		$settings = Seyedcast_Settings::get();
		$id       = ! empty( $settings['pwa_icon_192'] ) ? (int) $settings['pwa_icon_192'] : 0;
		if ( $id ) {
			$url = wp_get_attachment_image_url( $id, 'full' );
			if ( $url ) {
				return $url;
			}
		}
		$png = SEYEDCAST_PATH . 'assets/icons/icon-192.png';
		if ( is_readable( $png ) ) {
			return SEYEDCAST_URL . 'assets/icons/icon-192.png';
		}
		return SEYEDCAST_URL . 'assets/icons/cover-placeholder.svg';
	}

	/**
	 * Prompt markup in footer.
	 */
	public function prompt_markup() {
		if ( ! self::enabled() || ! Seyedcast_Assets::is_seyedcast_context() ) {
			return;
		}
		if ( ! self::get_vapid_keys() ) {
			return;
		}
		?>
		<div id="seyedcast-push-prompt" class="seyedcast-pwa-prompt seyedcast-push-prompt" hidden dir="rtl" role="status" aria-live="polite">
			<div class="seyedcast-pwa-prompt__card">
				<img class="seyedcast-pwa-prompt__icon" src="" alt="" width="44" height="44" loading="lazy" decoding="async" />
				<div class="seyedcast-pwa-prompt__text">
					<strong class="seyedcast-pwa-prompt__title"></strong>
					<p class="seyedcast-pwa-prompt__message"></p>
				</div>
				<button type="button" class="seyedcast-pwa-prompt__close" data-action="dismiss" aria-label="<?php esc_attr_e( 'بستن', 'seyedcast' ); ?>">
					<span aria-hidden="true">&times;</span>
				</button>
				<div class="seyedcast-pwa-prompt__actions">
					<button type="button" class="seyedcast-btn seyedcast-btn--primary" data-action="enable"></button>
					<button type="button" class="seyedcast-btn" data-action="dismiss-secondary"></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: save push subscription.
	 */
	public function ajax_subscribe() {
		check_ajax_referer( 'seyedcast_push', 'nonce' );

		if ( ! self::enabled() ) {
			wp_send_json_error( array( 'message' => 'disabled' ), 403 );
		}

		$raw = isset( $_POST['subscription'] ) ? wp_unslash( $_POST['subscription'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_string( $raw ) ) {
			$data = json_decode( $raw, true );
		} elseif ( is_array( $raw ) ) {
			$data = $raw;
		} else {
			$data = null;
		}

		if ( ! is_array( $data ) || empty( $data['endpoint'] ) ) {
			wp_send_json_error( array( 'message' => 'invalid' ), 400 );
		}

		$endpoint = esc_url_raw( $data['endpoint'] );
		$keys     = isset( $data['keys'] ) && is_array( $data['keys'] ) ? $data['keys'] : array();
		$p256dh   = isset( $keys['p256dh'] ) ? sanitize_text_field( $keys['p256dh'] ) : '';
		$auth     = isset( $keys['auth'] ) ? sanitize_text_field( $keys['auth'] ) : '';

		if ( ! $endpoint || ! $p256dh || ! $auth ) {
			wp_send_json_error( array( 'message' => 'invalid' ), 400 );
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';
		$this->upsert_subscription( $endpoint, $p256dh, $auth, $ua );

		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * AJAX: remove push subscription.
	 */
	public function ajax_unsubscribe() {
		check_ajax_referer( 'seyedcast_push', 'nonce' );

		$endpoint = isset( $_POST['endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['endpoint'] ) ) : '';
		if ( ! $endpoint ) {
			wp_send_json_error( array( 'message' => 'invalid' ), 400 );
		}

		$this->delete_by_endpoint( $endpoint );
		wp_send_json_success( array( 'removed' => true ) );
	}

	/**
	 * Insert or update a subscription row.
	 *
	 * @param string $endpoint Endpoint URL.
	 * @param string $p256dh   p256dh key.
	 * @param string $auth     Auth secret.
	 * @param string $ua       User agent.
	 */
	private function upsert_subscription( $endpoint, $p256dh, $auth, $ua ) {
		global $wpdb;

		self::ensure_table();
		$table = self::table_name();
		$hash  = hash( 'sha256', $endpoint );
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE endpoint_hash = %s", $hash )
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'endpoint'   => $endpoint,
					'p256dh'     => $p256dh,
					'auth'       => $auth,
					'user_agent' => $ua,
					'updated_at' => $now,
				),
				array( 'id' => (int) $existing ),
				array( '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'endpoint'      => $endpoint,
				'endpoint_hash' => $hash,
				'p256dh'        => $p256dh,
				'auth'          => $auth,
				'user_agent'    => $ua,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Delete subscription by endpoint URL.
	 *
	 * @param string $endpoint Endpoint.
	 */
	public function delete_by_endpoint( $endpoint ) {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return;
		}
		$table = self::table_name();
		$hash  = hash( 'sha256', $endpoint );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'endpoint_hash' => $hash ), array( '%s' ) );
	}

	/**
	 * Schedule push when episode is first published.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public function on_episode_status( $new_status, $old_status, $post ) {
		if ( ! $post || 'seyedcast_episode' !== $post->post_type ) {
			return;
		}
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( ! self::enabled() ) {
			return;
		}
		if ( ! self::library_ready() ) {
			return;
		}
		if ( get_post_meta( $post->ID, self::SENT_META, true ) ) {
			return;
		}

		update_post_meta( $post->ID, self::SENT_META, time() );

		if ( ! wp_next_scheduled( self::CRON_HOOK, array( (int) $post->ID, 0 ) ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( (int) $post->ID, 0 ) );
		}
	}

	/**
	 * Cron: send push for an episode in batches.
	 *
	 * @param int $episode_id Episode ID.
	 * @param int $offset     Batch offset.
	 */
	public function cron_send_episode( $episode_id, $offset = 0 ) {
		$episode_id = (int) $episode_id;
		$offset     = (int) $offset;
		$post       = get_post( $episode_id );

		if ( ! $post || 'seyedcast_episode' !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		if ( ! self::enabled() || ! self::library_ready() ) {
			return;
		}

		$payload = $this->build_payload( $post );
		$sent    = $this->send_batch( $payload, $offset, self::BATCH_SIZE );

		if ( $sent >= self::BATCH_SIZE ) {
			wp_schedule_single_event( time() + 15, self::CRON_HOOK, array( $episode_id, $offset + self::BATCH_SIZE ) );
		}
	}

	/**
	 * Build notification payload for an episode.
	 *
	 * @param WP_Post $post Episode.
	 * @return array
	 */
	private function build_payload( $post ) {
		$show_id = Seyedcast_Meta::get_show_id( $post->ID );
		$show    = $show_id ? get_the_title( $show_id ) : '';
		$title   = $show ? sprintf(
			/* translators: %s: show title */
			__( 'اپیزود جدید از %s', 'seyedcast' ),
			$show
		) : __( 'اپیزود جدید', 'seyedcast' );

		$body = get_the_title( $post );
		$url  = get_permalink( $post );
		$icon = get_the_post_thumbnail_url( $post, 'thumbnail' );
		if ( ! $icon && $show_id ) {
			$icon = get_the_post_thumbnail_url( $show_id, 'thumbnail' );
		}
		if ( ! $icon ) {
			$icon = $this->icon_url();
		}

		return array(
			'title' => $title,
			'body'  => $body,
			'url'   => $url ? $url : home_url( '/' ),
			'icon'  => $icon,
			'tag'   => 'seyedcast-episode-' . (int) $post->ID,
		);
	}

	/**
	 * Send a batch of notifications.
	 *
	 * @param array $payload Payload.
	 * @param int   $offset  Offset.
	 * @param int   $limit   Limit.
	 * @return int Number of rows processed.
	 */
	private function send_batch( array $payload, $offset, $limit ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		self::maybe_load_autoload();
		$keys = self::get_vapid_keys();
		if ( ! $keys || ! class_exists( '\Minishlink\WebPush\WebPush' ) ) {
			return 0;
		}

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, endpoint, p256dh, auth FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$auth = array(
			'VAPID' => array(
				'subject'    => $this->vapid_subject(),
				'publicKey'  => $keys['publicKey'],
				'privateKey' => $keys['privateKey'],
			),
		);

		try {
			$web_push = new \Minishlink\WebPush\WebPush( $auth );
			$web_push->setReuseVAPIDHeaders( true );
		} catch ( Exception $e ) {
			return 0;
		}

		$json = wp_json_encode( $payload );
		foreach ( $rows as $row ) {
			$subscription = \Minishlink\WebPush\Subscription::create(
				array(
					'endpoint'        => $row->endpoint,
					'contentEncoding' => 'aes128gcm',
					'keys'            => array(
						'p256dh' => $row->p256dh,
						'auth'   => $row->auth,
					),
				)
			);
			$web_push->queueNotification( $subscription, $json );
		}

		foreach ( $web_push->flush() as $report ) {
			if ( $report->isSubscriptionExpired() ) {
				$this->delete_by_endpoint( $report->getEndpoint() );
				continue;
			}
			if ( $report->isSuccess() ) {
				continue;
			}
			$code = $report->getResponse() ? $report->getResponse()->getStatusCode() : 0;
			if ( in_array( (int) $code, array( 404, 410 ), true ) ) {
				$this->delete_by_endpoint( $report->getEndpoint() );
			}
		}

		return count( $rows );
	}

	/**
	 * VAPID subject (mailto or site URL).
	 *
	 * @return string
	 */
	private function vapid_subject() {
		$admin_email = get_option( 'admin_email' );
		if ( is_email( $admin_email ) ) {
			return 'mailto:' . $admin_email;
		}
		return home_url( '/' );
	}

	/**
	 * Admin AJAX: send a test notification to all subscribers.
	 */
	public function ajax_test_send() {
		check_ajax_referer( 'seyedcast_push_test', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		if ( ! self::enabled() ) {
			wp_send_json_error( array( 'message' => __( 'پوش نوتیفیکیشن غیرفعال است.', 'seyedcast' ) ), 400 );
		}
		if ( ! self::library_ready() ) {
			wp_send_json_error( array( 'message' => __( 'کتابخانه web-push نصب نیست.', 'seyedcast' ) ), 500 );
		}

		$count = self::subscriber_count();
		if ( $count < 1 ) {
			wp_send_json_error( array( 'message' => __( 'هنوز مشترکی ثبت نشده است.', 'seyedcast' ) ), 400 );
		}

		$settings = Seyedcast_Settings::get();
		$payload  = array(
			'title' => ! empty( $settings['pwa_name'] ) ? $settings['pwa_name'] : 'Seyedcast',
			'body'  => __( 'این یک اعلان آزمایشی است.', 'seyedcast' ),
			'url'   => home_url( user_trailingslashit( Seyedcast_Rewrite::base_slug() ) ),
			'icon'  => $this->icon_url(),
			'tag'   => 'seyedcast-test-' . time(),
		);

		$processed = $this->send_batch( $payload, 0, self::BATCH_SIZE );
		if ( $processed >= self::BATCH_SIZE && $count > self::BATCH_SIZE ) {
			// Continue remaining via a fake episode cron is awkward; loop remaining synchronously in chunks with time check.
			$offset = self::BATCH_SIZE;
			while ( $offset < $count ) {
				$n = $this->send_batch( $payload, $offset, self::BATCH_SIZE );
				if ( $n < 1 ) {
					break;
				}
				$offset += $n;
			}
		}

		wp_send_json_success(
			array(
				/* translators: %d: subscriber count */
				'message' => sprintf( __( 'اعلان آزمایشی برای %d مشترک ارسال شد.', 'seyedcast' ), $count ),
			)
		);
	}
}
