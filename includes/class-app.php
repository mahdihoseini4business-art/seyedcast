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

	const VIEW_META           = '_seyedcast_view_count';
	const VIEW_COOKIE         = 'seyedcast_viewed_';
	const EPISODE_VIEW_COOKIE = 'seyedcast_viewed_ep_';
	const VIEW_THROTTLE       = 6 * HOUR_IN_SECONDS;
	const BOARD_OPTION        = 'seyedcast_comments_board_id';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_track_view' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'admin_ensure_comments_board' ) );
		add_action( 'wp_ajax_seyedcast_search', array( $this, 'ajax_search' ) );
		add_action( 'wp_ajax_nopriv_seyedcast_search', array( $this, 'ajax_search' ) );
		add_action( 'wp_ajax_seyedcast_suggest', array( $this, 'ajax_suggest' ) );
		add_action( 'wp_ajax_nopriv_seyedcast_suggest', array( $this, 'ajax_suggest' ) );
		add_filter( 'comment_post_redirect', array( $this, 'filter_comment_redirect' ), 10, 2 );
		add_filter( 'comments_open', array( $this, 'filter_comments_open' ), 10, 2 );
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
	 * Increment show/episode view counts (unique throttled per visitor).
	 */
	public function maybe_track_view() {
		if ( is_admin() || wp_doing_ajax() || self::is_partial_request() ) {
			return;
		}

		$board = (int) get_option( self::BOARD_OPTION, 0 );
		if ( $board && is_page( $board ) ) {
			$archive = get_post_type_archive_link( 'seyedcast_show' );
			if ( $archive ) {
				wp_safe_redirect( $archive . '#seyedcast-comments', 301 );
				exit;
			}
		}

		$show_id    = 0;
		$episode_id = 0;

		if ( is_singular( 'seyedcast_show' ) ) {
			$show_id = get_queried_object_id();
		} elseif ( is_singular( 'seyedcast_episode' ) ) {
			$episode_id = get_queried_object_id();
			$show_id    = Seyedcast_Meta::get_show_id( $episode_id );
		}

		// Episode page: count episode view only (show views are tracked on show pages).
		if ( $episode_id > 0 ) {
			$is_unique_ep = ! self::has_recent_unique_view( 'episode', $episode_id );
			Seyedcast_Stats::record_episode_view( $episode_id, $is_unique_ep );
			if ( $is_unique_ep ) {
				self::mark_unique_view( 'episode', $episode_id );
			}
			return;
		}

		if ( $show_id > 0 ) {
			$is_unique_show = ! self::has_recent_unique_view( 'show', $show_id );
			Seyedcast_Stats::record_show_view( $show_id, $is_unique_show );
			if ( $is_unique_show ) {
				self::mark_unique_view( 'show', $show_id );
			}
		}
	}

	/**
	 * Cookie name for unique-view throttle.
	 *
	 * @param string $type show|episode.
	 * @param int    $id   Post ID.
	 * @return string
	 */
	private static function unique_cookie_name( $type, $id ) {
		$prefix = ( 'episode' === $type ) ? self::EPISODE_VIEW_COOKIE : self::VIEW_COOKIE;
		return $prefix . absint( $id );
	}

	/**
	 * Server-side fingerprint key (cookie fallback).
	 *
	 * @param string $type show|episode.
	 * @param int    $id   Post ID.
	 * @return string
	 */
	private static function unique_fingerprint_key( $type, $id ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		$hash = md5( $type . '|' . absint( $id ) . '|' . $ip . '|' . substr( $ua, 0, 120 ) );
		return 'seyedcast_uv_' . $hash;
	}

	/**
	 * Whether this visitor already counted as unique recently.
	 *
	 * Uses cookie first, then a short-lived transient fingerprint so unique
	 * counting still works when cookies are blocked or Set-Cookie fails.
	 *
	 * @param string $type show|episode.
	 * @param int    $id   Post ID.
	 * @return bool
	 */
	private static function has_recent_unique_view( $type, $id ) {
		$cookie = self::unique_cookie_name( $type, $id );
		if ( ! empty( $_COOKIE[ $cookie ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return true;
		}

		return (bool) get_transient( self::unique_fingerprint_key( $type, $id ) );
	}

	/**
	 * Mark visitor as already counted for unique views.
	 *
	 * @param string $type show|episode.
	 * @param int    $id   Post ID.
	 */
	private static function mark_unique_view( $type, $id ) {
		$cookie  = self::unique_cookie_name( $type, $id );
		$expire  = time() + self::VIEW_THROTTLE;
		$path    = '/';
		$secure  = is_ssl();
		$domain  = '';

		if ( ! headers_sent() ) {
			if ( PHP_VERSION_ID >= 70300 ) {
				setcookie(
					$cookie,
					'1',
					array(
						'expires'  => $expire,
						'path'     => $path,
						'domain'   => $domain,
						'secure'   => $secure,
						'httponly' => true,
						'samesite' => 'Lax',
					)
				);
			} else {
				setcookie( $cookie, '1', $expire, $path, $domain, $secure, true );
			}
		}

		// Available for the rest of this request.
		$_COOKIE[ $cookie ] = '1';

		set_transient( self::unique_fingerprint_key( $type, $id ), 1, self::VIEW_THROTTLE );
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

		return self::get_newest_show();
	}

	/**
	 * Newest published podcast show.
	 *
	 * @return WP_Post|null
	 */
	public static function get_newest_show() {
		$shows = get_posts(
			array(
				'post_type'      => 'seyedcast_show',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		return ! empty( $shows ) ? $shows[0] : null;
	}

	/**
	 * Banner slide payload for a show.
	 *
	 * @param WP_Post $show Show post.
	 * @param string  $badge Badge label.
	 * @param string  $key   Slide key.
	 * @return array|null
	 */
	public static function banner_slide_from_show( $show, $badge, $key = '' ) {
		if ( ! $show instanceof WP_Post ) {
			return null;
		}
		$episodes = Seyedcast_Meta::get_show_episodes( $show->ID );
		$first    = ! empty( $episodes ) ? Seyedcast_Templates::episode_payload( $episodes[0] ) : null;
		$excerpt  = has_excerpt( $show ) ? get_the_excerpt( $show ) : wp_trim_words( wp_strip_all_tags( $show->post_content ), 28 );
		return array(
			'key'      => $key,
			'badge'    => $badge,
			'id'       => (int) $show->ID,
			'title'    => get_the_title( $show ),
			'url'      => get_permalink( $show ),
			'cover'    => Seyedcast_Templates::cover_url( $show->ID ),
			'excerpt'  => $excerpt,
			'views'    => Seyedcast_Stats::get_view_count( $show->ID ),
			'count'    => count( $episodes ),
			'payload'  => $first,
		);
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
		self::rate_limit_ajax( 'search' );

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
				'title' => wp_strip_all_tags( get_the_title( $show ) ),
				'url'   => esc_url_raw( get_permalink( $show ) ),
				'cover' => esc_url_raw( Seyedcast_Templates::cover_url( $show->ID ) ),
				'meta'  => __( 'پادکست', 'seyedcast' ),
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
				'title' => wp_strip_all_tags( $payload['title'] ),
				'url'   => esc_url_raw( $payload['permalink'] ),
				'cover' => esc_url_raw( $payload['cover'] ),
				'meta'  => wp_strip_all_tags( $payload['show'] ? $payload['show'] : __( 'اپیزود', 'seyedcast' ) ),
			);
		}

		wp_send_json_success( array( 'items' => $items ) );
	}

	/**
	 * Suggest related shows from recent listen history (show IDs).
	 */
	public function ajax_suggest() {
		self::rate_limit_ajax( 'suggest' );

		$raw = isset( $_GET['ids'] ) ? sanitize_text_field( wp_unslash( $_GET['ids'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ids = array_values(
			array_filter(
				array_map( 'absint', explode( ',', $raw ) ),
				static function ( $id ) {
					return $id > 0;
				}
			)
		);
		$ids = array_slice( array_unique( $ids ), 0, 3 );

		$items = self::get_suggested_shows( $ids, 5 );
		wp_send_json_success( array( 'items' => $items ) );
	}

	/**
	 * Build suggested show cards related to seed show IDs.
	 *
	 * @param int[] $seed_ids Recent show IDs.
	 * @param int   $limit    Max items.
	 * @return array
	 */
	public static function get_suggested_shows( array $seed_ids, $limit = 5 ) {
		$limit   = max( 1, (int) $limit );
		$exclude = $seed_ids;
		$term_ids = array();

		foreach ( $seed_ids as $show_id ) {
			$terms = get_the_terms( $show_id, 'seyedcast_topic' );
			if ( ! $terms || is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$term_ids[] = (int) $term->term_id;
			}
		}
		$term_ids = array_values( array_unique( array_filter( $term_ids ) ) );

		$related = array();
		if ( $term_ids ) {
			$related = get_posts(
				array(
					'post_type'      => 'seyedcast_show',
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'post__not_in'   => $exclude ? $exclude : array( 0 ),
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'seyedcast_topic',
							'field'    => 'term_id',
							'terms'    => $term_ids,
						),
					),
					'meta_key'       => self::VIEW_META,
					'orderby'        => 'meta_value_num',
					'order'          => 'DESC',
				)
			);
		}

		if ( count( $related ) < $limit ) {
			$found_ids = wp_list_pluck( $related, 'ID' );
			$fallback  = get_posts(
				array(
					'post_type'      => 'seyedcast_show',
					'post_status'    => 'publish',
					'posts_per_page' => $limit - count( $related ),
					'post__not_in'   => array_merge( $exclude, $found_ids, array( 0 ) ),
					'meta_key'       => self::VIEW_META,
					'orderby'        => array(
						'meta_value_num' => 'DESC',
						'date'           => 'DESC',
					),
					'order'          => 'DESC',
				)
			);
			$related = array_merge( $related, $fallback );
		}

		$items = array();
		foreach ( $related as $show ) {
			$count   = count( Seyedcast_Meta::get_show_episodes( $show->ID ) );
			$items[] = array(
				'id'    => (int) $show->ID,
				'title' => wp_strip_all_tags( get_the_title( $show ) ),
				'url'   => esc_url_raw( get_permalink( $show ) ),
				'cover' => esc_url_raw( Seyedcast_Templates::cover_url( $show->ID ) ),
				'meta'  => sprintf( _n( '%s اپیزود', '%s اپیزود', $count, 'seyedcast' ), number_format_i18n( $count ) ),
			);
		}
		return $items;
	}

	/**
	 * Page ID used for homepage / archive comments board.
	 *
	 * @return int
	 */
	public static function get_comments_board_id() {
		$settings = Seyedcast_Settings::get();
		if ( empty( $settings['comments_enabled'] ) ) {
			return 0;
		}

		$id = (int) get_option( self::BOARD_OPTION, 0 );
		if ( ! $id ) {
			return 0;
		}

		$post = get_post( $id );
		if ( ! $post || 'trash' === $post->post_status ) {
			return 0;
		}

		return $id;
	}

	/**
	 * Create comments board page (admin / activation only).
	 *
	 * @return int
	 */
	public static function ensure_comments_board() {
		$settings = Seyedcast_Settings::get();
		if ( empty( $settings['comments_enabled'] ) ) {
			return 0;
		}

		$existing = self::get_comments_board_id();
		if ( $existing ) {
			if ( 'closed' === get_post( $existing )->comment_status ) {
				wp_update_post(
					array(
						'ID'             => $existing,
						'comment_status' => 'open',
					)
				);
			}
			return $existing;
		}

		if ( ! current_user_can( 'edit_pages' ) ) {
			return 0;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => __( 'گفتگوی پادکست‌ها', 'seyedcast' ),
				'post_content'   => __( 'نظرات عمومی صفحه اصلی پادکست‌ها.', 'seyedcast' ),
				'post_name'      => 'seyedcast-comments-board',
				'comment_status' => 'open',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		update_post_meta( (int) $page_id, '_seyedcast_comments_board', 1 );
		update_option( self::BOARD_OPTION, (int) $page_id, false );
		return (int) $page_id;
	}

	/**
	 * Ensure comments board exists when an admin loads wp-admin.
	 */
	public static function admin_ensure_comments_board() {
		if ( current_user_can( 'edit_pages' ) ) {
			self::ensure_comments_board();
		}
	}

	/**
	 * Force comments open/closed for podcast surfaces based on settings.
	 *
	 * @param bool $open    Whether open.
	 * @param int  $post_id Post ID.
	 * @return bool
	 */
	public function filter_comments_open( $open, $post_id ) {
		$settings = Seyedcast_Settings::get();
		$type     = get_post_type( $post_id );
		$board    = (int) get_option( self::BOARD_OPTION, 0 );
		$is_ours  = in_array( $type, array( 'seyedcast_show', 'seyedcast_episode' ), true ) || ( $board && (int) $post_id === $board );

		if ( ! $is_ours ) {
			return $open;
		}

		if ( empty( $settings['comments_enabled'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Simple AJAX rate limit per IP.
	 *
	 * @param string $action Action key.
	 * @param int    $limit  Max requests.
	 * @param int    $window Window in seconds.
	 */
	private static function rate_limit_ajax( $action, $limit = 40, $window = 60 ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : 'unknown';
		$key = 'seyedcast_rl_' . md5( $action . '|' . $ip );
		$hit = (int) get_transient( $key );

		if ( $hit >= $limit ) {
			wp_send_json_error( array( 'message' => __( 'درخواست‌های زیاد. کمی بعد دوباره تلاش کنید.', 'seyedcast' ) ), 429 );
		}

		set_transient( $key, $hit + 1, $window );
	}

	/**
	 * Keep comment submitters inside the podcast app after posting.
	 *
	 * @param string     $location Redirect URL.
	 * @param WP_Comment $comment  Comment.
	 * @return string
	 */
	public function filter_comment_redirect( $location, $comment ) {
		if ( ! $comment instanceof WP_Comment ) {
			return $location;
		}

		$post_id = (int) $comment->comment_post_ID;
		$board   = (int) get_option( self::BOARD_OPTION, 0 );

		if ( $board && $post_id === $board ) {
			$archive = get_post_type_archive_link( 'seyedcast_show' );
			if ( $archive ) {
				return add_query_arg( 'comment', $comment->comment_ID, $archive ) . '#seyedcast-comments';
			}
		}

		if ( in_array( get_post_type( $post_id ), array( 'seyedcast_show', 'seyedcast_episode' ), true ) ) {
			$permalink = get_permalink( $post_id );
			if ( $permalink ) {
				return add_query_arg( 'comment', $comment->comment_ID, $permalink ) . '#seyedcast-comments';
			}
		}

		return $location;
	}

	/**
	 * Custom comment list item markup.
	 *
	 * @param WP_Comment $comment Comment.
	 * @param array      $args    Args.
	 * @param int        $depth   Depth.
	 */
	public static function render_comment( $comment, $args, $depth ) {
		$tag = ( 'div' === $args['style'] ) ? 'div' : 'li';
		?>
		<<?php echo esc_html( $tag ); ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( 'seyedcast-comment', $comment ); ?>>
			<article class="seyedcast-comment__inner">
				<div class="seyedcast-comment__avatar">
					<?php echo get_avatar( $comment, (int) $args['avatar_size'] ); ?>
				</div>
				<div class="seyedcast-comment__body">
					<header class="seyedcast-comment__meta">
						<strong class="seyedcast-comment__author"><?php comment_author( $comment ); ?></strong>
						<time class="seyedcast-comment__date" datetime="<?php echo esc_attr( get_comment_date( DATE_W3C, $comment ) ); ?>">
							<?php echo esc_html( get_comment_date( '', $comment ) ); ?>
						</time>
					</header>
					<div class="seyedcast-comment__content">
						<?php comment_text( $comment ); ?>
					</div>
					<?php
					comment_reply_link(
						array_merge(
							$args,
							array(
								'depth'     => $depth,
								'max_depth' => $args['max_depth'],
								'before'    => '<div class="seyedcast-comment__reply">',
								'after'     => '</div>',
							)
						)
					);
					?>
				</div>
			</article>
		</<?php echo esc_html( $tag ); ?>>
		<?php
	}
}
