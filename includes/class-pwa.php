<?php
/**
 * PWA manifest, service worker, install prompt.
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
		add_action( 'wp_head', array( $this, 'head_tags' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_prompt' ) );
		add_action( 'wp_footer', array( $this, 'prompt_markup' ), 20 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve_endpoints' ) );
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
	 * Serve manifest / SW.
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
	 * Enqueue install prompt script on podcast pages.
	 */
	public function enqueue_prompt() {
		$settings = Seyedcast_Settings::get();
		if ( ! $this->enabled() || empty( $settings['pwa_prompt'] ) ) {
			return;
		}
		if ( ! Seyedcast_Assets::is_seyedcast_context() ) {
			return;
		}

		wp_enqueue_script(
			'seyedcast-pwa-prompt',
			SEYEDCAST_URL . 'public/js/pwa-prompt.js',
			array(),
			SEYEDCAST_VERSION,
			true
		);

		wp_localize_script(
			'seyedcast-pwa-prompt',
			'seyedcastPwa',
			array(
				'swUrl'      => home_url( '/seyedcast-sw.js' ),
				'storageKey' => 'seyedcast_pwa_prompt_dismissed',
				'isIos'      => $this->is_ios(),
				'i18n'       => array(
					'title'   => __( 'نصب Seyedcast', 'seyedcast' ),
					'message' => __( 'برای دسترسی سریع‌تر، به صفحه اصلی اضافه کنید.', 'seyedcast' ),
					'install' => __( 'افزودن به صفحه اصلی', 'seyedcast' ),
					'later'   => __( 'بعداً', 'seyedcast' ),
					'iosHint' => __( 'در Safari روی Share بزنید و Add to Home Screen را انتخاب کنید.', 'seyedcast' ),
				),
			)
		);
	}

	/**
	 * Prompt shell.
	 */
	public function prompt_markup() {
		$settings = Seyedcast_Settings::get();
		if ( ! $this->enabled() || empty( $settings['pwa_prompt'] ) || ! Seyedcast_Assets::is_seyedcast_context() ) {
			return;
		}
		?>
		<div id="seyedcast-pwa-prompt" class="seyedcast-pwa-prompt" hidden dir="rtl">
			<div class="seyedcast-pwa-prompt__card">
				<div class="seyedcast-pwa-prompt__text">
					<strong class="seyedcast-pwa-prompt__title"></strong>
					<p class="seyedcast-pwa-prompt__message"></p>
				</div>
				<div class="seyedcast-pwa-prompt__actions">
					<button type="button" class="seyedcast-btn seyedcast-btn--primary" data-action="install"></button>
					<button type="button" class="seyedcast-btn" data-action="dismiss"></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Detect iOS UA roughly.
	 *
	 * @return bool
	 */
	private function is_ios() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}
		$ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		return (bool) preg_match( '/iPad|iPhone|iPod/', $ua );
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
		$icon192  = $this->icon_url( 192 );
		$icon512  = $this->icon_url( 512 );

		$manifest = array(
			'name'             => $settings['pwa_name'],
			'short_name'       => $settings['pwa_short_name'],
			'description'      => $settings['archive_title'],
			'start_url'        => $start,
			'scope'            => $start,
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
					'purpose' => 'any maskable',
				),
				array(
					'src'     => $icon512,
					'sizes'   => '512x512',
					'type'    => $this->icon_mime( $icon512 ),
					'purpose' => 'any maskable',
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

		$cache   = 'seyedcast-static-v' . SEYEDCAST_VERSION;
		$assets  = array(
			SEYEDCAST_URL . 'public/css/themes.css',
			SEYEDCAST_URL . 'public/css/player.css',
			SEYEDCAST_URL . 'public/js/player.js',
			$this->icon_url( 192 ),
			SEYEDCAST_URL . 'assets/icons/cover-placeholder.svg',
		);

		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		header( 'Cache-Control: no-cache' );

		$assets_json = wp_json_encode( array_values( $assets ) );
		$cache_js    = wp_json_encode( $cache );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JavaScript payload.
		echo 'const CACHE = ' . $cache_js . ";\n";
		echo 'const ASSETS = ' . $assets_json . ";\n";
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
		echo "  if (url.pathname.indexOf('/wp-content/plugins/seyedcast/') === -1 && url.pathname.indexOf('/seyedcast-') === -1) return;\n";
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
