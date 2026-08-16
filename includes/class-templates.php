<?php
/**
 * Template loading and app shell rendering.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Templates
 */
class Seyedcast_Templates {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'template_include', array( $this, 'template_include' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Body classes for theming.
	 *
	 * @param array $classes Classes.
	 * @return array
	 */
	public function body_class( $classes ) {
		$settings  = Seyedcast_Settings::get();
		$classes[] = 'seyedcast-preset-' . sanitize_html_class( $settings['design_preset'] );
		if ( Seyedcast_Assets::is_seyedcast_context() ) {
			$classes[] = 'seyedcast-app';
			$classes[] = 'seyedcast-app-shell';
		}
		return $classes;
	}

	/**
	 * Swap templates for CPT views.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function template_include( $template ) {
		if ( is_singular( 'seyedcast_episode' ) ) {
			return $this->locate( 'single-episode.php', $template );
		}
		if ( is_singular( 'seyedcast_show' ) ) {
			return $this->locate( 'single-show.php', $template );
		}
		if ( is_post_type_archive( 'seyedcast_show' ) ) {
			return $this->locate( 'archive-shows.php', $template );
		}
		if ( is_tax( 'seyedcast_topic' ) ) {
			return $this->locate( 'taxonomy-topic.php', $template );
		}
		return $template;
	}

	/**
	 * Locate plugin or theme override template.
	 *
	 * @param string $name     File name.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private function locate( $name, $fallback ) {
		$theme = locate_template( array( 'seyedcast/' . $name ) );
		if ( $theme ) {
			return $theme;
		}
		$plugin = SEYEDCAST_PATH . 'public/templates/' . $name;
		return is_readable( $plugin ) ? $plugin : $fallback;
	}

	/**
	 * Load a template partial.
	 *
	 * @param string $name Partial file name under partials/.
	 * @param array  $vars Variables to extract.
	 */
	public static function partial( $name, $vars = array() ) {
		$path = SEYEDCAST_PATH . 'public/templates/partials/' . $name . '.php';
		$theme = locate_template( array( 'seyedcast/partials/' . $name . '.php' ) );
		if ( $theme ) {
			$path = $theme;
		}
		if ( ! is_readable( $path ) ) {
			return;
		}
		if ( $vars ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $vars, EXTR_SKIP );
		}
		include $path;
	}

	/**
	 * Render app shell around main HTML, or emit partial only.
	 *
	 * @param string $main_html Inner main column HTML.
	 * @param array  $args {
	 *     @type bool   $show_featured Whether to show featured banner.
	 *     @type string $page_class    Extra class on main.
	 * }
	 */
	public static function render_app( $main_html, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'show_featured' => false,
				'page_class'    => '',
			)
		);

		if ( Seyedcast_App::is_partial_request() ) {
			nocache_headers();
			header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
			echo '<div id="seyedcast-app-stage" class="seyedcast-app-stage" data-title="' . esc_attr( wp_get_document_title() ) . '">';
			if ( ! empty( $args['show_featured'] ) ) {
				self::partial( 'featured-banner' );
			}
			echo '<div id="seyedcast-app-main" class="seyedcast-app-main ' . esc_attr( $args['page_class'] ) . '">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped templates.
			echo $main_html;
			echo '</div></div>';
			exit;
		}

		$settings = Seyedcast_Settings::get();
		$preset   = sanitize_html_class( $settings['design_preset'] );
		$colors   = Seyedcast_Settings::resolved_colors();

		include SEYEDCAST_PATH . 'public/templates/layout-app.php';
	}

	/**
	 * Cover URL helper.
	 *
	 * @param int $post_id Post ID.
	 * @param int $fallback_id Fallback post ID.
	 * @return string
	 */
	public static function cover_url( $post_id, $fallback_id = 0 ) {
		$url = get_the_post_thumbnail_url( $post_id, 'seyedcast_cover' );
		if ( ! $url ) {
			$url = get_the_post_thumbnail_url( $post_id, 'medium' );
		}
		if ( ! $url && $fallback_id ) {
			$url = get_the_post_thumbnail_url( $fallback_id, 'seyedcast_cover' );
			if ( ! $url ) {
				$url = get_the_post_thumbnail_url( $fallback_id, 'medium' );
			}
		}
		if ( ! $url ) {
			$url = SEYEDCAST_URL . 'assets/icons/cover-placeholder.svg';
		}
		return $url;
	}

	/**
	 * Episode payload for player buttons.
	 *
	 * @param WP_Post $episode Episode.
	 * @return array
	 */
	public static function episode_payload( $episode ) {
		$show_id = Seyedcast_Meta::get_show_id( $episode->ID );
		$show    = $show_id ? get_post( $show_id ) : null;
		return array(
			'id'        => (int) $episode->ID,
			'show_id'   => (int) $show_id,
			'title'     => get_the_title( $episode ),
			'show'      => $show ? get_the_title( $show ) : '',
			'audio'     => Seyedcast_Meta::get_audio_url( $episode->ID ),
			'cover'     => self::cover_url( $episode->ID, $show_id ),
			'permalink' => get_permalink( $episode ),
			'duration'  => (string) get_post_meta( $episode->ID, '_seyedcast_duration', true ),
			'number'    => (string) get_post_meta( $episode->ID, '_seyedcast_episode_number', true ),
		);
	}
}
