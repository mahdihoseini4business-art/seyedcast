<?php
/**
 * Custom rewrite rules for nested episode URLs.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Rewrite
 */
class Seyedcast_Rewrite {

	/**
	 * Reserved first-segment slugs under the base archive.
	 *
	 * @return string[]
	 */
	public static function reserved_slugs() {
		return array( 'topic', 'page', 'feed', 'embed', 'trackback', 'attachment', 'episode' );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'add_rules' ), 20 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'post_type_link', array( $this, 'episode_permalink' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'parse_request' ) );
		add_filter( 'request', array( $this, 'map_episode_request' ) );
	}

	/**
	 * Base slug from settings.
	 *
	 * @return string
	 */
	public static function base_slug() {
		$settings = Seyedcast_Settings::get();
		$slug     = ! empty( $settings['base_slug'] ) ? sanitize_title( $settings['base_slug'] ) : 'podcasts';
		return $slug ? $slug : 'podcasts';
	}

	/**
	 * Add rewrite rules.
	 *
	 * Note: rules added with 'top' are prepended, so reserved paths are registered
	 * last so they match before show/episode catch-alls.
	 */
	public static function add_rules() {
		$base = self::base_slug();

		// Catch-alls first (end up lower priority).
		add_rewrite_rule(
			'^' . $base . '/([^/]+)/([^/]+)/?$',
			'index.php?seyedcast_episode=$matches[2]&seyedcast_show_slug=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/([^/]+)/?$',
			'index.php?seyedcast_show=$matches[1]',
			'top'
		);

		// Reserved paths last (end up highest priority).
		add_rewrite_rule(
			'^' . $base . '/episode/([^/]+)/?$',
			'index.php?seyedcast_episode=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/feed/?$',
			'index.php?post_type=seyedcast_show&feed=rss2',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/page/([0-9]+)/?$',
			'index.php?post_type=seyedcast_show&paged=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/topic/(.+?)/?$',
			'index.php?seyedcast_topic=$matches[1]',
			'top'
		);
	}

	/**
	 * Register query vars.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[] = 'seyedcast_show_slug';
		return $vars;
	}

	/**
	 * Resolve a CPT by slug without deprecated get_page_by_path().
	 *
	 * @param string $slug      Post name.
	 * @param string $post_type Post type.
	 * @return WP_Post|null
	 */
	public static function get_by_slug( $slug, $post_type ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return null;
		}

		$posts = get_posts(
			array(
				'name'             => $slug,
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);

		return ! empty( $posts[0] ) ? $posts[0] : null;
	}

	/**
	 * Custom episode permalink.
	 *
	 * @param string  $post_link Link.
	 * @param WP_Post $post      Post.
	 * @return string
	 */
	public function episode_permalink( $post_link, $post ) {
		if ( 'seyedcast_episode' !== $post->post_type ) {
			return $post_link;
		}

		$show_id = Seyedcast_Meta::get_show_id( $post->ID );
		$show    = $show_id ? get_post( $show_id ) : null;
		if ( ! $show || 'seyedcast_show' !== $show->post_type ) {
			return home_url( user_trailingslashit( self::base_slug() . '/episode/' . $post->post_name ) );
		}

		$show_slug = $show->post_name;
		if ( in_array( $show_slug, self::reserved_slugs(), true ) ) {
			return home_url( user_trailingslashit( self::base_slug() . '/episode/' . $post->post_name ) );
		}

		return home_url( user_trailingslashit( self::base_slug() . '/' . $show_slug . '/' . $post->post_name ) );
	}

	/**
	 * Ensure episode query resolves correctly when show slug present.
	 *
	 * @param array $query_vars Request vars.
	 * @return array
	 */
	public function map_episode_request( $query_vars ) {
		// Never treat reserved segments as a show slug.
		if ( ! empty( $query_vars['seyedcast_show'] ) && empty( $query_vars['seyedcast_episode'] ) ) {
			$maybe = sanitize_title( $query_vars['seyedcast_show'] );
			if ( in_array( $maybe, self::reserved_slugs(), true ) ) {
				$query_vars['error'] = '404';
				return $query_vars;
			}
		}

		if ( empty( $query_vars['seyedcast_episode'] ) || empty( $query_vars['seyedcast_show_slug'] ) ) {
			return $query_vars;
		}

		$show_slug = sanitize_title( $query_vars['seyedcast_show_slug'] );
		if ( in_array( $show_slug, self::reserved_slugs(), true ) ) {
			$query_vars['error'] = '404';
			return $query_vars;
		}

		$show = self::get_by_slug( $show_slug, 'seyedcast_show' );
		if ( ! $show ) {
			$query_vars['error'] = '404';
			return $query_vars;
		}

		$episode = self::get_by_slug( sanitize_title( $query_vars['seyedcast_episode'] ), 'seyedcast_episode' );
		if ( ! $episode ) {
			$query_vars['error'] = '404';
			return $query_vars;
		}

		if ( (int) Seyedcast_Meta::get_show_id( $episode->ID ) !== (int) $show->ID ) {
			$query_vars['error'] = '404';
			return $query_vars;
		}

		return $query_vars;
	}

	/**
	 * Archive query defaults.
	 *
	 * @param WP_Query $query Query.
	 */
	public function parse_request( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->is_post_type_archive( 'seyedcast_show' ) ) {
			$query->set( 'posts_per_page', 24 );
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
		}
	}
}
