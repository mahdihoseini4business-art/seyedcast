<?php
/**
 * Front-end assets and sticky player shell.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Assets
 */
class Seyedcast_Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render_player' ), 5 );
		add_action( 'wp_head', array( $this, 'inline_theme_vars' ), 30 );
	}

	/**
	 * Whether current request is a Seyedcast surface.
	 *
	 * @return bool
	 */
	public static function is_seyedcast_context() {
		return is_singular( array( 'seyedcast_show', 'seyedcast_episode' ) )
			|| is_post_type_archive( 'seyedcast_show' )
			|| is_tax( 'seyedcast_topic' );
	}

	/**
	 * Enqueue styles/scripts.
	 */
	public function enqueue() {
		$colors  = Seyedcast_Settings::resolved_colors();
		$preset  = Seyedcast_Settings::get()['design_preset'];
		$context = self::is_seyedcast_context();

		wp_register_style(
			'seyedcast-themes',
			SEYEDCAST_URL . 'public/css/themes.css',
			array(),
			SEYEDCAST_VERSION
		);

		wp_register_style(
			'seyedcast-player',
			SEYEDCAST_URL . 'public/css/player.css',
			array( 'seyedcast-themes' ),
			SEYEDCAST_VERSION
		);

		wp_register_script(
			'seyedcast-player',
			SEYEDCAST_URL . 'public/js/player.js',
			array(),
			SEYEDCAST_VERSION,
			true
		);

		wp_register_script(
			'seyedcast-app-nav',
			SEYEDCAST_URL . 'public/js/app-nav.js',
			array(),
			SEYEDCAST_VERSION,
			true
		);

		wp_register_script(
			'seyedcast-app-ui',
			SEYEDCAST_URL . 'public/js/app-ui.js',
			array( 'seyedcast-player' ),
			SEYEDCAST_VERSION,
			true
		);

		// Player available site-wide so playback continues across non-app pages via restore.
		wp_enqueue_style( 'seyedcast-player' );
		wp_enqueue_script( 'seyedcast-player' );

		if ( $context ) {
			wp_enqueue_style( 'seyedcast-themes' );
			wp_enqueue_script( 'seyedcast-app-nav' );
			wp_enqueue_script( 'seyedcast-app-ui' );
			wp_localize_script(
				'seyedcast-app-ui',
				'seyedcastApp',
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'storageKey' => 'seyedcast_player_state_v1',
					'i18n'       => array(
						'noResults' => __( 'نتیجه‌ای پیدا نشد', 'seyedcast' ),
						'from'      => __( 'از', 'seyedcast' ),
					),
				)
			);
		}

		wp_localize_script(
			'seyedcast-player',
			'seyedcastPlayer',
			array(
				'storageKey' => 'seyedcast_player_state_v1',
				'i18n'       => array(
					'play'    => __( 'پخش', 'seyedcast' ),
					'pause'   => __( 'توقف', 'seyedcast' ),
					'close'   => __( 'بستن', 'seyedcast' ),
					'noAudio' => __( 'فایل صوتی موجود نیست', 'seyedcast' ),
				),
				'colors'     => $colors,
				'preset'     => $preset,
			)
		);
	}

	/**
	 * CSS variables on front.
	 */
	public function inline_theme_vars() {
		$c      = Seyedcast_Settings::resolved_colors();
		$preset = sanitize_html_class( Seyedcast_Settings::get()['design_preset'] );
		echo '<style id="seyedcast-theme-vars">:root{';
		echo '--sc-primary:' . esc_attr( $c['primary'] ) . ';';
		echo '--sc-bg:' . esc_attr( $c['background'] ) . ';';
		echo '--sc-surface:' . esc_attr( $c['surface'] ) . ';';
		echo '--sc-text:' . esc_attr( $c['text'] ) . ';';
		echo '--sc-accent:' . esc_attr( $c['accent'] ) . ';';
		echo '}body.seyedcast-preset-' . esc_attr( $preset ) . '{--sc-preset:' . esc_attr( $preset ) . ';}</style>' . "\n";

		if ( self::is_seyedcast_context() ) {
			echo '<meta name="format-detection" content="telephone=no" />' . "\n";
			echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />' . "\n";
		}
	}

	/**
	 * Sticky player markup.
	 */
	public function render_player() {
		?>
		<div id="seyedcast-sticky-player" class="seyedcast-sticky-player" hidden dir="rtl" aria-live="polite">
			<div class="seyedcast-sticky-player__fill" data-role="fill"></div>
			<input type="range" class="seyedcast-sticky-player__scrub" data-action="seek-top" min="0" max="100" value="0" step="0.1" aria-label="<?php esc_attr_e( 'پیشرفت پخش', 'seyedcast' ); ?>" />
			<div class="seyedcast-sticky-player__inner">
				<img class="seyedcast-sticky-player__cover" src="" alt="" width="58" height="58" />
				<div class="seyedcast-sticky-player__meta">
					<div class="seyedcast-sticky-player__title">
						<span class="seyedcast-eq" aria-hidden="true"><i></i><i></i><i></i></span>
						<span data-role="title-text"></span>
					</div>
					<div class="seyedcast-sticky-player__show"></div>
				</div>
				<div class="seyedcast-sticky-player__controls">
					<button type="button" class="seyedcast-btn seyedcast-btn--icon" data-action="seek-back" aria-label="<?php esc_attr_e( '۱۵ ثانیه عقب', 'seyedcast' ); ?>">−۱۵</button>
					<button type="button" class="seyedcast-btn seyedcast-btn--play" data-action="toggle" aria-label="<?php esc_attr_e( 'پخش / توقف', 'seyedcast' ); ?>">
						<span class="seyedcast-icon-play"></span>
						<span class="seyedcast-icon-pause" hidden></span>
					</button>
					<button type="button" class="seyedcast-btn seyedcast-btn--icon" data-action="seek-forward" aria-label="<?php esc_attr_e( '۳۰ ثانیه جلو', 'seyedcast' ); ?>">+۳۰</button>
				</div>
				<div class="seyedcast-sticky-player__progress">
					<input type="range" class="seyedcast-range" data-action="seek" min="0" max="100" value="0" step="0.1" aria-label="<?php esc_attr_e( 'پیشرفت', 'seyedcast' ); ?>" />
					<div class="seyedcast-sticky-player__time">
						<span data-role="current">0:00</span>
						<span data-role="duration">0:00</span>
					</div>
				</div>
				<select class="seyedcast-speed seyedcast-speed--desktop" data-action="speed" aria-label="<?php esc_attr_e( 'سرعت پخش', 'seyedcast' ); ?>">
					<option value="0.75">۰.۷۵×</option>
					<option value="1" selected>۱×</option>
					<option value="1.25">۱.۲۵×</option>
					<option value="1.5">۱.۵×</option>
					<option value="2">۲×</option>
				</select>
				<button type="button" class="seyedcast-btn seyedcast-btn--icon seyedcast-sticky-player__close--desktop" data-action="close" aria-label="<?php esc_attr_e( 'بستن پلیر', 'seyedcast' ); ?>">×</button>

				<div class="seyedcast-sticky-player__tools">
					<button type="button" class="seyedcast-btn seyedcast-btn--icon" data-action="seek-back" aria-label="<?php esc_attr_e( '۱۵ ثانیه عقب', 'seyedcast' ); ?>">−۱۵</button>
					<div class="seyedcast-sticky-player__tools-time">
						<span data-role="current-mobile">0:00</span>
						<span>/</span>
						<span data-role="duration-mobile">0:00</span>
					</div>
					<button type="button" class="seyedcast-btn seyedcast-btn--icon" data-action="seek-forward" aria-label="<?php esc_attr_e( '۳۰ ثانیه جلو', 'seyedcast' ); ?>">+۳۰</button>
					<select class="seyedcast-speed" data-action="speed" aria-label="<?php esc_attr_e( 'سرعت پخش', 'seyedcast' ); ?>">
						<option value="0.75">۰.۷۵×</option>
						<option value="1" selected>۱×</option>
						<option value="1.25">۱.۲۵×</option>
						<option value="1.5">۱.۵×</option>
						<option value="2">۲×</option>
					</select>
					<button type="button" class="seyedcast-btn seyedcast-btn--icon" data-action="close" aria-label="<?php esc_attr_e( 'بستن پلیر', 'seyedcast' ); ?>">×</button>
				</div>
			</div>
			<audio id="seyedcast-audio" preload="metadata" playsinline></audio>
		</div>
		<?php
	}
}
