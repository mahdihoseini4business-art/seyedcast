<?php
/**
 * Plugin settings.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Settings
 */
class Seyedcast_Settings {

	const OPTION_KEY = 'seyedcast_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'update_option_' . self::OPTION_KEY, array( $this, 'maybe_flush_rewrites' ), 10, 2 );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'base_slug'         => 'podcasts',
			'archive_title'     => 'پادکست‌های آموزشی',
			'design_preset'     => 'spotify',
			'colors'            => array(
				'primary'    => '',
				'background' => '',
				'surface'    => '',
				'text'       => '',
				'accent'     => '',
			),
			'featured_mode'        => 'auto',
			'featured_show_id'     => 0,
			'featured_cta'         => 'گوش دهید',
			'sidebar_banners'      => array(),
			'upcoming_events'      => array(),
			'suggestions_enabled'  => 1,
			'comments_enabled'     => 1,
			'pwa_enabled'          => 1,
			'pwa_prompt'        => 1,
			'pwa_name'          => 'Seyedcast',
			'pwa_short_name'    => 'Seyedcast',
			'pwa_theme_color'   => '#121212',
			'pwa_bg_color'      => '#121212',
			'pwa_icon_192'      => 0,
			'pwa_icon_512'      => 0,
		);
	}

	/**
	 * Design presets (CSS variable values).
	 *
	 * @return array
	 */
	public static function presets() {
		return array(
			'spotify' => array(
				'label'      => 'اسپاتیفای تیره',
				'primary'    => '#1ED760',
				'background' => '#0B0B0B',
				'surface'    => '#181818',
				'text'       => '#F5F5F5',
				'accent'     => '#1DB954',
			),
			'castbox' => array(
				'label'      => 'کست‌باکس',
				'primary'    => '#FF6A3D',
				'background' => '#0B1118',
				'surface'    => '#16202B',
				'text'       => '#F7F8FA',
				'accent'     => '#FF9A6B',
			),
			'apple'   => array(
				'label'      => 'اپل پادکست',
				'primary'    => '#8B5CF6',
				'background' => '#F4F2F8',
				'surface'    => '#FFFFFF',
				'text'       => '#1C1C1E',
				'accent'     => '#A78BFA',
			),
			'ytmusic' => array(
				'label'      => 'یوتیوب میوزیک',
				'primary'    => '#FF0033',
				'background' => '#000000',
				'surface'    => '#1A1A1A',
				'text'       => '#FFFFFF',
				'accent'     => '#FF4D6D',
			),
			'soundcloud' => array(
				'label'      => 'ساندکلاد',
				'primary'    => '#FF5500',
				'background' => '#F6F6F6',
				'surface'    => '#FFFFFF',
				'text'       => '#222222',
				'accent'     => '#FF7A33',
			),
		);
	}

	/**
	 * Get merged settings.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$defaults = self::defaults();
		$merged   = wp_parse_args( $stored, $defaults );
		$merged['colors'] = wp_parse_args( isset( $stored['colors'] ) && is_array( $stored['colors'] ) ? $stored['colors'] : array(), $defaults['colors'] );
		if ( empty( $merged['sidebar_banners'] ) || ! is_array( $merged['sidebar_banners'] ) ) {
			$merged['sidebar_banners'] = array();
		}
		if ( empty( $merged['upcoming_events'] ) || ! is_array( $merged['upcoming_events'] ) ) {
			$merged['upcoming_events'] = array();
		}
		return $merged;
	}

	/**
	 * Resolved theme colors (preset + overrides).
	 *
	 * @return array
	 */
	public static function resolved_colors() {
		$settings = self::get();
		$presets  = self::presets();
		$key      = isset( $settings['design_preset'] ) ? $settings['design_preset'] : 'spotify';
		$base     = isset( $presets[ $key ] ) ? $presets[ $key ] : $presets['spotify'];
		$out      = array();
		foreach ( array( 'primary', 'background', 'surface', 'text', 'accent' ) as $field ) {
			$custom = isset( $settings['colors'][ $field ] ) ? $settings['colors'][ $field ] : '';
			$out[ $field ] = $custom ? $custom : $base[ $field ];
		}
		return $out;
	}

	/**
	 * Admin menu.
	 */
	public function register_menu() {
		add_menu_page(
			'Seyedcast',
			'Seyedcast',
			'manage_options',
			'seyedcast',
			array( $this, 'render_page' ),
			'dashicons-controls-volumeon',
			26
		);

		add_submenu_page(
			'seyedcast',
			__( 'تنظیمات', 'seyedcast' ),
			__( 'تنظیمات', 'seyedcast' ),
			'manage_options',
			'seyedcast',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings API.
	 */
	public function register_settings() {
		register_setting(
			'seyedcast_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = self::defaults();
		$existing = self::get();
		$out      = $defaults;
		$presets  = array_keys( self::presets() );

		if ( ! is_array( $input ) ) {
			return $existing;
		}

		$out['base_slug']     = isset( $input['base_slug'] ) ? sanitize_title( $input['base_slug'] ) : $existing['base_slug'];
		if ( '' === $out['base_slug'] ) {
			$out['base_slug'] = 'podcasts';
		}
		$out['archive_title'] = isset( $input['archive_title'] ) ? sanitize_text_field( $input['archive_title'] ) : $existing['archive_title'];
		$preset               = isset( $input['design_preset'] ) ? sanitize_key( $input['design_preset'] ) : $existing['design_preset'];
		$out['design_preset'] = in_array( $preset, $presets, true ) ? $preset : 'spotify';

		$out['colors'] = array();
		foreach ( array( 'primary', 'background', 'surface', 'text', 'accent' ) as $field ) {
			if ( isset( $input['colors'][ $field ] ) ) {
				$val = sanitize_hex_color( $input['colors'][ $field ] );
				$out['colors'][ $field ] = $val ? $val : '';
			} else {
				$out['colors'][ $field ] = isset( $existing['colors'][ $field ] ) ? $existing['colors'][ $field ] : '';
			}
		}

		$active_tab = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : '';

		$out['pwa_enabled']    = (int) $existing['pwa_enabled'];
		$out['pwa_prompt']     = (int) $existing['pwa_prompt'];
		$out['pwa_name']       = isset( $input['pwa_name'] ) ? sanitize_text_field( $input['pwa_name'] ) : $existing['pwa_name'];
		$out['pwa_short_name'] = isset( $input['pwa_short_name'] ) ? sanitize_text_field( $input['pwa_short_name'] ) : $existing['pwa_short_name'];
		$out['pwa_theme_color'] = $existing['pwa_theme_color'];
		if ( isset( $input['pwa_theme_color'] ) ) {
			$theme_color = sanitize_hex_color( $input['pwa_theme_color'] );
			if ( $theme_color ) {
				$out['pwa_theme_color'] = $theme_color;
			}
		}
		$out['pwa_bg_color'] = $existing['pwa_bg_color'];
		if ( isset( $input['pwa_bg_color'] ) ) {
			$bg_color = sanitize_hex_color( $input['pwa_bg_color'] );
			if ( $bg_color ) {
				$out['pwa_bg_color'] = $bg_color;
			}
		}
		$out['pwa_icon_192'] = isset( $input['pwa_icon_192'] ) ? absint( $input['pwa_icon_192'] ) : (int) $existing['pwa_icon_192'];
		$out['pwa_icon_512'] = isset( $input['pwa_icon_512'] ) ? absint( $input['pwa_icon_512'] ) : (int) $existing['pwa_icon_512'];

		if ( 'pwa' === $active_tab ) {
			$out['pwa_enabled'] = ! empty( $input['pwa_enabled'] ) ? 1 : 0;
			$out['pwa_prompt']  = ! empty( $input['pwa_prompt'] ) ? 1 : 0;
		}

		if ( 'page' === $active_tab ) {
			$mode = isset( $input['featured_mode'] ) ? sanitize_key( $input['featured_mode'] ) : 'auto';
			$out['featured_mode']    = in_array( $mode, array( 'auto', 'manual' ), true ) ? $mode : 'auto';
			$out['featured_show_id'] = isset( $input['featured_show_id'] ) ? absint( $input['featured_show_id'] ) : 0;
			$out['featured_cta']     = isset( $input['featured_cta'] ) ? sanitize_text_field( $input['featured_cta'] ) : $defaults['featured_cta'];
			$out['suggestions_enabled'] = ! empty( $input['suggestions_enabled'] ) ? 1 : 0;
			$out['comments_enabled']    = ! empty( $input['comments_enabled'] ) ? 1 : 0;

			$out['sidebar_banners'] = array();
			if ( ! empty( $input['sidebar_banners'] ) && is_array( $input['sidebar_banners'] ) ) {
				$count = 0;
				foreach ( $input['sidebar_banners'] as $banner ) {
					if ( $count >= 3 || ! is_array( $banner ) ) {
						break;
					}
					$image_id = isset( $banner['image_id'] ) ? absint( $banner['image_id'] ) : 0;
					if ( ! $image_id ) {
						continue;
					}
					$out['sidebar_banners'][] = array(
						'image_id' => $image_id,
						'url'      => isset( $banner['url'] ) ? esc_url_raw( $banner['url'] ) : '',
						'alt'      => isset( $banner['alt'] ) ? sanitize_text_field( $banner['alt'] ) : '',
					);
					$count++;
				}
			}

			$out['upcoming_events'] = array();
			if ( ! empty( $input['upcoming_events'] ) && is_array( $input['upcoming_events'] ) ) {
				$count = 0;
				foreach ( $input['upcoming_events'] as $event ) {
					if ( $count >= 5 || ! is_array( $event ) ) {
						break;
					}
					$starts = isset( $event['starts_at'] ) ? sanitize_text_field( $event['starts_at'] ) : '';
					if ( ! $starts ) {
						continue;
					}
					$out['upcoming_events'][] = array(
						'title'      => isset( $event['title'] ) ? sanitize_text_field( $event['title'] ) : '',
						'episode_id' => isset( $event['episode_id'] ) ? absint( $event['episode_id'] ) : 0,
						'starts_at'  => $starts,
					);
					$count++;
				}
			}
		} else {
			$out['featured_mode']       = $existing['featured_mode'];
			$out['featured_show_id']    = (int) $existing['featured_show_id'];
			$out['featured_cta']        = $existing['featured_cta'];
			$out['sidebar_banners']     = $existing['sidebar_banners'];
			$out['upcoming_events']     = $existing['upcoming_events'];
			$out['suggestions_enabled'] = isset( $existing['suggestions_enabled'] ) ? (int) $existing['suggestions_enabled'] : 1;
			$out['comments_enabled']    = isset( $existing['comments_enabled'] ) ? (int) $existing['comments_enabled'] : 1;
		}

		return $out;
	}

	/**
	 * Flush rewrites when slug changes.
	 *
	 * @param mixed $old Old value.
	 * @param mixed $new New value.
	 */
	public function maybe_flush_rewrites( $old, $new ) {
		$old_slug = is_array( $old ) && isset( $old['base_slug'] ) ? $old['base_slug'] : '';
		$new_slug = is_array( $new ) && isset( $new['base_slug'] ) ? $new['base_slug'] : '';
		if ( $old_slug !== $new_slug ) {
			Seyedcast_Rewrite::add_rules();
			flush_rewrite_rules();
		}
	}

	/**
	 * Enqueue settings assets.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue( $hook ) {
		if ( 'toplevel_page_seyedcast' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'seyedcast-admin', SEYEDCAST_URL . 'admin/css/admin.css', array(), SEYEDCAST_VERSION );
		wp_enqueue_script( 'seyedcast-admin', SEYEDCAST_URL . 'admin/js/admin.js', array( 'jquery', 'wp-color-picker' ), SEYEDCAST_VERSION, true );
	}

	/**
	 * Render settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = self::get();
		$presets  = self::presets();
		include SEYEDCAST_PATH . 'admin/views/settings-page.php';
	}
}
