<?php
/**
 * Podcast app shell helpers: views, featured, search, partials.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_App
 */
class Seyedcast_App {

	const VIEW_META = '_seyedcast_view_count';
	const VIEW_COOKIE = 'seyedcast_viewed_';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_track_view' ), 20 );
		add_action( 'wp_ajax_seyedcast_search', array( $this, 'ajax_search' ) );
		add_action( 'wp_ajax_nopriv_seyedcast_search', array( $this, 'ajax_search' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_theme_chrome' ), 999 );
	}

	/**
	 * Whether request wants a PJAX partial fragment.
	 *
	 * @return bool
	 */
	public static function is_partial_request() {
		$header = isset( $_SERVER['HTTP_X_SEYEDCAST_PARTIAL'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_SEYEDCAST_PARTIAL'] ) ) : '';
		if ( '1' === $header ) {
			return true;
		}
		return isset( $_GET['seyedcast_partial'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['seyedcast_partial'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Strip only active-theme chrome so plugin assets (e.g. Direct SMS Contact) keep working.
	 */
	public function dequeue_theme_chrome() {
		if ( ! Seyedcast_Assets::is_seyedcast_context() ) {
			return;
		}

		global $wp_styles, $wp_scripts;

		$theme_uris = array_filter(
			array(
				trailingslashit( get_template_directory_uri() ),
				trailingslashit( get_stylesheet_directory_uri() ),
			)
		);

		if ( $wp_styles instanceof WP_Styles ) {
			foreach ( (array) $wp_styles->queue as $handle ) {
				if ( 0 === strpos( $handle, 'seyedcast-' ) || in_array( $handle, array( 'admin-bar', 'dashicons' ), true ) ) {
					continue;
				}
				$src = isset( $wp_styles->registered[ $handle ]->src ) ? (string) $wp_styles->registered[ $handle ]->src : '';
				if ( ! $src ) {
					continue;
				}
				foreach ( $theme_uris as $uri ) {
					if ( 0 === strpos( $src, $uri ) ) {
						wp_dequeue_style( $handle );
						break;
					}
				}
			}
		}

		if ( $wp_scripts instanceof WP_Scripts ) {
			foreach ( (array) $wp_scripts->queue as $handle ) {
				if ( 0 === strpos( $handle, 'seyedcast-' ) || in_array( $handle, array( 'admin-bar', 'hoverintent-js' ), true ) ) {
					continue;
				}
				if ( 0 === strpos( $handle, 'wp-' ) || 'jquery' === $handle || 0 === strpos( $handle, 'jquery-' ) ) {
					continue;
				}
				$src = isset( $wp_scripts->registered[ $handle ]->src ) ? (string) $wp_scripts->registered[ $handle ]->src : '';
				if ( ! $src ) {
					continue;
				}
				foreach ( $theme_uris as $uri ) {
					if ( 0 === strpos( $src, $uri ) ) {
						wp_dequeue_script( $handle );
						break;
					}
				}
			}
		}
	}

	/**
	 * Increment show view count (throttled per visitor).
	 */
	public function maybe_track_view() {
		if ( is_admin() || wp_doing_ajax() || self::is_partial_request() ) {
			return;
		}

		$show_id = 0;
		if ( is_singular( 'seyedcast_show' ) ) {
			$show_id = get_queried_object_id();
		} elseif ( is_singular( 'seyedcast_episode' ) ) {
			$show_id = Seyedcast_Meta::get_show_id( get_queried_object_id() );
		}

		if ( $show_id < 1 ) {
			return;
		}

		$cookie = self::VIEW_COOKIE . $show_id;
		if ( isset( $_COOKIE[ $cookie ] ) ) {
			return;
		}

		$count = (int) get_post_meta( $show_id, self::VIEW_META, true );
		update_post_meta( $show_id, self::VIEW_META, $count + 1 );

		// 6 hours throttle.
		setcookie( $cookie, '1', time() + 6 * HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
	}

	/**
	 * Resolve featured show for the top banner.
	 *
	 * @return WP_Post|null
	 */
	public static function get_featured_show() {
		$settings = Seyedcast_Settings::get();
		$mode     = isset( $settings['featured_mode'] ) ? $settings['featured_mode'] : 'auto';

		if ( 'manual' === $mode ) {
			$manual_id = isset( $settings['featured_show_id'] ) ? (int) $settings['featured_show_id'] : 0;
			if ( $manual_id ) {
				$post = get_post( $manual_id );
				if ( $post && 'seyedcast_show' === $post->post_type && 'publish' === $post->post_status ) {
					return $post;
				}
			}
		}

		$shows = get_posts(
			array(
				'post_type'      => 'seyedcast_show',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => self::VIEW_META,
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
			)
		);

		if ( ! empty( $shows ) ) {
			return $shows[0];
		}

		$fallback = get_posts(
			array(
				'post_type'      => 'seyedcast_show',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return ! empty( $fallback ) ? $fallback[0] : null;
	}

	/**
	 * Latest published episodes.
	 *
	 * @param int $limit Limit.
	 * @return WP_Post[]
	 */
	public static function get_latest_episodes( $limit = 8 ) {
		return get_posts(
			array(
				'post_type'      => 'seyedcast_episode',
				'post_status'    => 'publish',
				'posts_per_page' => (int) $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Topic terms for filter chips.
	 *
	 * @return WP_Term[]
	 */
	public static function get_topics() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'seyedcast_topic',
				'hide_empty' => true,
			)
		);
		return ( ! is_wp_error( $terms ) && is_array( $terms ) ) ? $terms : array();
	}

	/**
	 * AJAX search shows and episodes by title.
	 */
	public function ajax_search() {
		$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$q = trim( $q );

		if ( strlen( $q ) < 2 ) {
			wp_send_json_success( array( 'items' => array() ) );
		}

		$items = array();

		$shows = get_posts(
			array(
				'post_type'      => 'seyedcast_show',
				'post_status'    => 'publish',
				's'              => $q,
				'posts_per_page' => 8,
			)
		);
		foreach ( $shows as $show ) {
			$items[] = array(
				'type'  => 'show',
				'title' => get_the_title( $show ),
				'url'   => get_permalink( $show ),
				'cover' => Seyedcast_Templates::cover_url( $show->ID ),
				'meta'  => __( 'شو', 'seyedcast' ),
			);
		}

		$episodes = get_posts(
			array(
				'post_type'      => 'seyedcast_episode',
				'post_status'    => 'publish',
				's'              => $q,
				'posts_per_page' => 8,
			)
		);
		foreach ( $episodes as $episode ) {
			$payload = Seyedcast_Templates::episode_payload( $episode );
			$items[] = array(
				'type'  => 'episode',
				'title' => $payload['title'],
				'url'   => $payload['permalink'],
				'cover' => $payload['cover'],
				'meta'  => $payload['show'] ? $payload['show'] : __( 'اپیزود', 'seyedcast' ),
			);
		}

		wp_send_json_success( array( 'items' => $items ) );
	}
}
