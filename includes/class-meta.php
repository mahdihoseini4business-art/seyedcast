<?php
/**
 * Episode and show meta boxes.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Meta
 */
class Seyedcast_Meta {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'save_post_seyedcast_episode', array( $this, 'save_episode' ) );
		add_action( 'save_post_seyedcast_show', array( $this, 'save_show' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	/**
	 * Enqueue media scripts on edit screens.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'seyedcast_episode', 'seyedcast_show' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'seyedcast-admin', SEYEDCAST_URL . 'admin/css/admin.css', array(), SEYEDCAST_VERSION );
		wp_enqueue_script( 'seyedcast-admin', SEYEDCAST_URL . 'admin/js/admin.js', array( 'jquery', 'wp-color-picker' ), SEYEDCAST_VERSION, true );
	}

	/**
	 * Register meta boxes.
	 */
	public function add_boxes() {
		add_meta_box(
			'seyedcast_episode_details',
			__( 'جزئیات اپیزود', 'seyedcast' ),
			array( $this, 'render_episode_box' ),
			'seyedcast_episode',
			'normal',
			'high'
		);

		add_meta_box(
			'seyedcast_show_details',
			__( 'جزئیات پادکست', 'seyedcast' ),
			array( $this, 'render_show_box' ),
			'seyedcast_show',
			'side',
			'default'
		);
	}

	/**
	 * Episode meta box markup.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_episode_box( $post ) {
		wp_nonce_field( 'seyedcast_save_episode', 'seyedcast_episode_nonce' );

		$show_id     = (int) get_post_meta( $post->ID, '_seyedcast_show_id', true );
		$audio_id    = (int) get_post_meta( $post->ID, '_seyedcast_audio_id', true );
		$duration    = get_post_meta( $post->ID, '_seyedcast_duration', true );
		$number      = get_post_meta( $post->ID, '_seyedcast_episode_number', true );
		$audio_url   = $audio_id ? wp_get_attachment_url( $audio_id ) : '';
		$audio_title = $audio_id ? get_the_title( $audio_id ) : '';

		$shows = get_posts(
			array(
				'post_type'      => 'seyedcast_show',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => array( 'publish', 'draft', 'private' ),
			)
		);

		include SEYEDCAST_PATH . 'admin/views/meta-boxes.php';
	}

	/**
	 * Show meta box markup.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_show_box( $post ) {
		wp_nonce_field( 'seyedcast_save_show', 'seyedcast_show_nonce' );
		$accent = get_post_meta( $post->ID, '_seyedcast_accent_color', true );
		$views  = (int) get_post_meta( $post->ID, Seyedcast_App::VIEW_META, true );
		?>
		<p>
			<label for="seyedcast_accent_color"><strong><?php esc_html_e( 'رنگ اختصاصی پادکست', 'seyedcast' ); ?></strong></label>
			<input type="text" class="seyedcast-color-field" id="seyedcast_accent_color" name="seyedcast_accent_color" value="<?php echo esc_attr( $accent ); ?>" data-default-color="#1DB954" />
		</p>
		<p class="description"><?php esc_html_e( 'برای هیرو و گرادیان صفحه پادکست استفاده می‌شود.', 'seyedcast' ); ?></p>
		<p>
			<strong><?php esc_html_e( 'بازدیدها', 'seyedcast' ); ?>:</strong>
			<?php echo esc_html( number_format_i18n( $views ) ); ?>
		</p>
		<?php
	}

	/**
	 * Save episode meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_episode( $post_id ) {
		if ( ! isset( $_POST['seyedcast_episode_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['seyedcast_episode_nonce'] ) ), 'seyedcast_save_episode' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$show_id = isset( $_POST['seyedcast_show_id'] ) ? absint( $_POST['seyedcast_show_id'] ) : 0;
		$audio   = isset( $_POST['seyedcast_audio_id'] ) ? absint( $_POST['seyedcast_audio_id'] ) : 0;
		$number  = isset( $_POST['seyedcast_episode_number'] ) ? sanitize_text_field( wp_unslash( $_POST['seyedcast_episode_number'] ) ) : '';
		$duration = isset( $_POST['seyedcast_duration'] ) ? sanitize_text_field( wp_unslash( $_POST['seyedcast_duration'] ) ) : '';

		update_post_meta( $post_id, '_seyedcast_show_id', $show_id );
		update_post_meta( $post_id, '_seyedcast_audio_id', $audio );
		update_post_meta( $post_id, '_seyedcast_episode_number', $number );
		update_post_meta( $post_id, '_seyedcast_duration', $duration );
	}

	/**
	 * Save show meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_show( $post_id ) {
		if ( ! isset( $_POST['seyedcast_show_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['seyedcast_show_nonce'] ) ), 'seyedcast_save_show' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$color = isset( $_POST['seyedcast_accent_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['seyedcast_accent_color'] ) ) : '';
		update_post_meta( $post_id, '_seyedcast_accent_color', $color ? $color : '' );
	}

	/**
	 * Helper: get show ID for episode.
	 *
	 * @param int $episode_id Episode ID.
	 * @return int
	 */
	public static function get_show_id( $episode_id ) {
		return (int) get_post_meta( $episode_id, '_seyedcast_show_id', true );
	}

	/**
	 * Helper: get audio URL for episode.
	 *
	 * @param int $episode_id Episode ID.
	 * @return string
	 */
	public static function get_audio_url( $episode_id ) {
		$audio_id = (int) get_post_meta( $episode_id, '_seyedcast_audio_id', true );
		return $audio_id ? (string) wp_get_attachment_url( $audio_id ) : '';
	}

	/**
	 * Episodes for a show.
	 *
	 * @param int $show_id Show ID.
	 * @param int $limit   Limit.
	 * @return WP_Post[]
	 */
	public static function get_show_episodes( $show_id, $limit = -1 ) {
		$episodes = get_posts(
			array(
				'post_type'      => 'seyedcast_episode',
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'   => '_seyedcast_show_id',
						'value' => (int) $show_id,
					),
				),
			)
		);

		usort(
			$episodes,
			static function ( $a, $b ) {
				$na = (float) get_post_meta( $a->ID, '_seyedcast_episode_number', true );
				$nb = (float) get_post_meta( $b->ID, '_seyedcast_episode_number', true );
					if ( $na === $nb ) {
					return ( strtotime( $b->post_date ) > strtotime( $a->post_date ) ) ? 1 : -1;
				}
				return ( $nb > $na ) ? 1 : -1;
			}
		);

		return $episodes;
	}
}
