<?php
/**
 * PWA manifest, service worker, install prompt, new-episode toast.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Pwa
 */
class Seyedcast_Pwa {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_endpoints' ) );
		add_action( 'parse_request', array( $this, 'maybe_serve_from_path' ), 1 );
		add_action( 'wp_head', array( $this, 'head_tags' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_prompt' ) );
		// Markup must print BEFORE footer scripts (priority 20), otherwise JS exits with no root.
		add_action( 'wp_footer', array( $this, 'prompt_markup' ), 5 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve_endpoints' ) );
		add_action( 'wp_ajax_seyedcast_latest_episode', array( $this, 'ajax_latest_episode' ) );
		add_action( 'wp_ajax_nopriv_seyedcast_latest_episode', array( $this, 'ajax_latest_episode' ) );
	}

	/**
	 * Register rewrite endpoints.
	 */
	public function register_endpoints() {
		add_rewrite_rule( '^seyedcast-manifest\.webmanifest$', 'index.php?seyedcast_manifest=1', 'top' );
		add_rewrite_rule( '^seyedcast-sw\.js$', 'index.php?seyedcast_sw=1', 'top' );
	}

	/**
	 * Query vars.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[] = 'seyedcast_manifest';
		$vars[] = 'seyedcast_sw';
		return $vars;
	}

	/**
	 * Serve SW/manifest by path when rewrite rules are missing/unflushed.
	 *
	 * @param WP $wp WP request.
	 */
	public function maybe_serve_from_path( $wp ) {
		if ( ! $this->enabled() ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		if ( '' === $path ) {
			return;
		}
		if ( preg_match( '#/seyedcast-manifest\.webmanifest$#', $path ) ) {
			$this->output_manifest();
			exit;
		}
		if ( preg_match( '#/seyedcast-sw\.js$#', $path ) ) {
			$this->output_sw();
			exit;
		}
	}

	/**
	 * Serve manifest / SW via query vars.
	 */
	public function serve_endpoints() {
		if ( get_query_var( 'seyedcast_manifest' ) ) {
			$this->output_manifest();
			exit;
		}
		if ( get_query_var( 'seyedcast_sw' ) ) {
			$this->output_sw();
			exit;
		}
	}

	/**
	 * Whether PWA is enabled.
	 *
	 * @return bool
	 */
	private function enabled() {
		$settings = Seyedcast_Settings::get();
		return ! empty( $settings['pwa_enabled'] );
	}

	/**
	 * Head tags.
	 */
	public function head_tags() {
		if ( ! $this->enabled() ) {
			return;
		}
		$settings = Seyedcast_Settings::get();
		echo '<link rel="manifest" href="' . esc_url( home_url( '/seyedcast-manifest.webmanifest' ) ) . '" />' . "\n";
		echo '<meta name="theme-color" content="' . esc_attr( $settings['pwa_theme_color'] ) . '" />' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes" />' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes" />' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr( $settings['pwa_short_name'] ) . '" />' . "\n";

		$icon = $this->icon_url( 192 );
		if ( $icon ) {
			echo '<link rel="apple-touch-icon" href="' . esc_url( $icon ) . '" />' . "\n";
		}
	}

	/**
	 * Enqueue PWA script on podcast pages (install prompt + update check).
	 */
	public function enqueue_prompt() {
		if ( ! $this->enabled() ) {
			return;
		}
		if ( ! Seyedcast_Assets::is_seyedcast_context() ) {
			return;
		}

		$settings = Seyedcast_Settings::get();

		wp_enqueue_script(
			'seyedcast-pwa-prompt',
			SEYEDCAST_URL . 'public/js/pwa-prompt.js',
			array(),
			SEYEDCAST_VERSION,
			true
		);

		$current_episode = 0;
		if ( is_singular( 'seyedcast_episode' ) ) {
			$current_episode = (int) get_queried_object_id();
		}

		wp_localize_script(
			'seyedcast-pwa-prompt',
			'seyedcastPwa',
			array(
				'swUrl'            => home_url( '/seyedcast-sw.js' ),
				'swScope'          => trailingslashit( (string) ( wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ?: '/' ) ),
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'latestAction'     => 'seyedcast_latest_episode',
				'promptEnabled'    => ! empty( $settings['pwa_prompt'] ) ? 1 : 0,
				'storageKey'       => 'seyedcast_pwa_prompt_dismissed',
				'snoozeKey'        => 'seyedcast_pwa_prompt_snooze',
				'seenKey'          => 'seyedcast_last_seen_episode',
				'snoozeDays'       => 3,
				'iconUrl'          => $this->icon_url( 192 ),
				'delayMs'          => 1500,
				'isIos'            => $this->is_ios(),
				'currentEpisodeId' => $current_episode,
				'i18n'             => array(
					'title'          => __( 'نصب روی موبایل', 'seyedcast' ),
					'message'        => __( 'برای دسترسی سریع‌تر، پادکست را به صفحه اصلی گوشی اضافه کنید.', 'seyedcast' ),
					'install'        => __( 'افزودن به صفحه اصلی', 'seyedcast' ),
					'later'          => __( 'بعداً', 'seyedcast' ),
					'close'          => __( 'بستن', 'seyedcast' ),
					'iosHint'        => __( 'در Safari دکمه Share (مربع با فلش) را بزنید، سپس «Add to Home Screen» را انتخاب کنید.', 'seyedcast' ),
					'androidHint'    => __( 'منوی مرورگر (⋮) را باز کنید و «نصب برنامه» یا «افزودن به صفحه اصلی» را بزنید.', 'seyedcast' ),
					'genericHint'    => __( 'از منوی مرورگر گزینه «Add to Home Screen» یا «نصب برنامه» را انتخاب کنید.', 'seyedcast' ),
					'updateTitle'    => __( 'پادکست جدید اومد', 'seyedcast' ),
					'updateMessage'  => __( 'یک اپیزود تازه منتشر شده است.', 'seyedcast' ),
					'listen'         => __( 'گوش بده', 'seyedcast' ),
					'dismissUpdate'  => __( 'باشه', 'seyedcast' ),
				),
			)
		);
	}

	/**
	 * Prompt / update toast shell.
	 */
	public function prompt_markup() {
		if ( ! $this->enabled() || ! Seyedcast_Assets::is_seyedcast_context() ) {
			return;
		}
		?>
		<div id="seyedcast-pwa-prompt" class="seyedcast-pwa-prompt" hidden dir="rtl" role="status" aria-live="polite">
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
					<button type="button" class="seyedcast-btn seyedcast-btn--primary" data-action="install"></button>
					<button type="button" class="seyedcast-btn seyedcast-btn--ghost" data-action="dismiss-secondary"></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Latest published episode for installed-PWA toast.
	 */
	public function ajax_latest_episode() {
		if ( ! $this->enabled() ) {
			wp_send_json_error( array( 'message' => 'disabled' ), 403 );
		}

		$episodes = Seyedcast_App::get_latest_episodes( 1 );
		if ( empty( $episodes ) ) {
			wp_send_json_success( null );
		}

		$episode = $episodes[0];
		$payload = Seyedcast_Templates::episode_payload( $episode );
		$show_id = ! empty( $payload['show_id'] ) ? (int) $payload['show_id'] : 0;

		wp_send_json_success(
			array(
				'id'        => (int) $episode->ID,
				'title'     => get_the_title( $episode ),
				'url'       => get_permalink( $episode ),
				'cover'     => ! empty( $payload['cover'] ) ? $payload['cover'] : $this->icon_url( 192 ),
				'showId'    => $show_id,
				'showTitle' => $show_id ? get_the_title( $show_id ) : '',
				'published' => (int) get_post_time( 'U', true, $episode ),
			)
		);
	}

	/**
	 * Detect iOS / iPadOS UA roughly.
	 *
	 * @return bool
	 */
	private function is_ios() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}
		$ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		if ( preg_match( '/iPad|iPhone|iPod/', $ua ) ) {
			return true;
		}
		// iPadOS 13+ may send Macintosh UA; touch heuristic is client-side.
		return false;
	}

	/**
	 * Icon URL by size.
	 *
	 * @param int $size Size.
	 * @return string
	 */
	private function icon_url( $size ) {
		$settings = Seyedcast_Settings::get();
		$key      = 512 === (int) $size ? 'pwa_icon_512' : 'pwa_icon_192';
		$id       = ! empty( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;
		if ( $id ) {
			$url = wp_get_attachment_image_url( $id, 'full' );
			if ( $url ) {
				return $url;
			}
		}

		$png = SEYEDCAST_PATH . 'assets/icons/icon-' . (int) $size . '.png';
		if ( is_readable( $png ) ) {
			return SEYEDCAST_URL . 'assets/icons/icon-' . (int) $size . '.png';
		}

		return SEYEDCAST_URL . 'assets/icons/cover-placeholder.svg';
	}

	/**
	 * MIME type for manifest icon URL.
	 *
	 * @param string $url Icon URL.
	 * @return string
	 */
	private function icon_mime( $url ) {
		return ( false !== strpos( $url, '.svg' ) ) ? 'image/svg+xml' : 'image/png';
	}

	/**
	 * Output web manifest JSON.
	 */
	private function output_manifest() {
		if ( ! $this->enabled() ) {
			status_header( 404 );
			return;
		}
		$settings = Seyedcast_Settings::get();
		$base     = Seyedcast_Rewrite::base_slug();
		$start    = home_url( user_trailingslashit( $base ) );
		// Scope must cover podcast pages; use home path so show/episode URLs stay in-app.
		$scope    = trailingslashit( (string) ( wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ?: '/' ) );
		$icon192  = $this->icon_url( 192 );
		$icon512  = $this->icon_url( 512 );

		$manifest = array(
			'name'             => $settings['pwa_name'],
			'short_name'       => $settings['pwa_short_name'],
			'description'      => $settings['archive_title'],
			'start_url'        => $start,
			'scope'            => home_url( $scope ),
			'display'          => 'standalone',
			'orientation'      => 'portrait-primary',
			'background_color' => $settings['pwa_bg_color'],
			'theme_color'      => $settings['pwa_theme_color'],
			'lang'             => 'fa',
			'dir'              => 'rtl',
			'icons'            => array(
				array(
					'src'     => $icon192,
					'sizes'   => '192x192',
					'type'    => $this->icon_mime( $icon192 ),
					'purpose' => 'any',
				),
				array(
					'src'     => $icon192,
					'sizes'   => '192x192',
					'type'    => $this->icon_mime( $icon192 ),
					'purpose' => 'maskable',
				),
				array(
					'src'     => $icon512,
					'sizes'   => '512x512',
					'type'    => $this->icon_mime( $icon512 ),
					'purpose' => 'any',
				),
				array(
					'src'     => $icon512,
					'sizes'   => '512x512',
					'type'    => $this->icon_mime( $icon512 ),
					'purpose' => 'maskable',
				),
			),
		);

		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo wp_json_encode( $manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	}

	/**
	 * Output service worker JS.
	 */
	private function output_sw() {
		if ( ! $this->enabled() ) {
			status_header( 404 );
			return;
		}

		$cache  = 'seyedcast-static-v' . SEYEDCAST_VERSION;
		$assets = array(
			SEYEDCAST_URL . 'public/css/themes.css',
			SEYEDCAST_URL . 'public/css/player.css',
			SEYEDCAST_URL . 'public/js/player.js',
			SEYEDCAST_URL . 'public/js/pwa-prompt.js',
			$this->icon_url( 192 ),
			SEYEDCAST_URL . 'assets/icons/cover-placeholder.svg',
		);

		$plugin_path = (string) wp_parse_url( SEYEDCAST_URL, PHP_URL_PATH );
		$plugin_path = $plugin_path ? untrailingslashit( $plugin_path ) : '/wp-content/plugins/seyedcast';
		$home_path   = (string) ( wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ?: '/' );
		$home_path   = trailingslashit( $home_path );

		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . $home_path );
		header( 'Cache-Control: no-cache' );

		$assets_json = wp_json_encode( array_values( $assets ) );
		$cache_js    = wp_json_encode( $cache );
		$plugin_js   = wp_json_encode( $plugin_path );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript payload.
		echo 'const CACHE = ' . $cache_js . ";\n";
		echo 'const ASSETS = ' . $assets_json . ";\n";
		echo 'const PLUGIN_PATH = ' . $plugin_js . ";\n";
		echo "self.addEventListener('install', function(event) {\n";
		echo "  event.waitUntil(caches.open(CACHE).then(function(cache) {\n";
		echo "    return Promise.allSettled(ASSETS.map(function(url) { return cache.add(url); }));\n";
		echo "  }).then(function() { return self.skipWaiting(); }));\n";
		echo "});\n";
		echo "self.addEventListener('activate', function(event) {\n";
		echo "  event.waitUntil(caches.keys().then(function(keys) {\n";
		echo "    return Promise.all(keys.filter(function(k) { return k !== CACHE; }).map(function(k) { return caches.delete(k); }));\n";
		echo "  }).then(function() { return self.clients.claim(); }));\n";
		echo "});\n";
		echo "self.addEventListener('fetch', function(event) {\n";
		echo "  var req = event.request;\n";
		echo "  if (req.method !== 'GET') return;\n";
		echo "  var url = new URL(req.url);\n";
		echo "  if (url.origin !== self.location.origin) return;\n";
		echo "  var path = url.pathname;\n";
		echo "  if (path.indexOf(PLUGIN_PATH) !== 0 && path.indexOf('/seyedcast-') === -1) return;\n";
		echo "  event.respondWith(caches.match(req).then(function(cached) {\n";
		echo "    return cached || fetch(req).then(function(res) {\n";
		echo "      var copy = res.clone();\n";
		echo "      caches.open(CACHE).then(function(cache) { cache.put(req, copy); });\n";
		echo "      return res;\n";
		echo "    }).catch(function() { return cached; });\n";
		echo "  }));\n";
		echo "});\n";
	}
}
