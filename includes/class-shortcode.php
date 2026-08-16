<?php
/**
 * Shortcode [seyedcast].
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Shortcode
 */
class Seyedcast_Shortcode {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'seyedcast', array( $this, 'render' ) );
	}

	/**
	 * Render shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'show'  => 0,
				'limit' => 12,
			),
			$atts,
			'seyedcast'
		);

		wp_enqueue_style( 'seyedcast-themes' );
		wp_enqueue_style( 'seyedcast-player' );

		ob_start();
		echo '<div class="seyedcast-app seyedcast-shortcode" dir="rtl">';

		$show_id = absint( $atts['show'] );
		if ( $show_id ) {
			$episodes = Seyedcast_Meta::get_show_episodes( $show_id, (int) $atts['limit'] );
			$show     = get_post( $show_id );
			if ( $show ) {
				echo '<div class="seyedcast-section-head"><h2>' . esc_html( get_the_title( $show ) ) . '</h2></div>';
			}
			$this->render_episode_list( $episodes );
		} else {
			$shows = get_posts(
				array(
					'post_type'      => 'seyedcast_show',
					'posts_per_page' => (int) $atts['limit'],
					'post_status'    => 'publish',
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			echo '<div class="seyedcast-shows-grid">';
			foreach ( $shows as $show ) {
				$cover = Seyedcast_Templates::cover_url( $show->ID );
				echo '<a class="seyedcast-show-tile" href="' . esc_url( get_permalink( $show ) ) . '">';
				echo '<span class="seyedcast-show-tile__art"><img src="' . esc_url( $cover ) . '" alt="" loading="lazy" /><span class="seyedcast-show-tile__play" aria-hidden="true"><span></span></span></span>';
				echo '<span class="seyedcast-show-tile__title">' . esc_html( get_the_title( $show ) ) . '</span>';
				echo '</a>';
			}
			echo '</div>';
		}

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Episode list markup.
	 *
	 * @param WP_Post[] $episodes Episodes.
	 */
	private function render_episode_list( $episodes ) {
		echo '<ul class="seyedcast-episode-list">';
		$i = 0;
		foreach ( $episodes as $episode ) {
			$i++;
			$payload = Seyedcast_Templates::episode_payload( $episode );
			$json    = esc_attr( wp_json_encode( $payload ) );
			echo '<li class="seyedcast-episode-row" data-episode-id="' . esc_attr( (string) $payload['id'] ) . '">';
			echo '<span class="seyedcast-episode-row__index">' . esc_html( (string) $i ) . '</span>';
			echo '<a class="seyedcast-episode-row__cover" href="' . esc_url( $payload['permalink'] ) . '"><img src="' . esc_url( $payload['cover'] ) . '" alt="" /></a>';
			echo '<div class="seyedcast-episode-row__body">';
			echo '<a href="' . esc_url( $payload['permalink'] ) . '"><strong>' . esc_html( $payload['title'] ) . '</strong></a>';
			echo '<div class="seyedcast-episode-row__meta">';
			if ( $payload['number'] ) {
				echo '<span>' . esc_html( sprintf( __( 'اپیزود %s', 'seyedcast' ), $payload['number'] ) ) . '</span>';
			}
			if ( $payload['duration'] ) {
				echo '<span>' . esc_html( $payload['duration'] ) . '</span>';
			}
			echo '</div></div>';
			echo '<button type="button" class="seyedcast-play-btn" data-seyedcast-play="' . $json . '" aria-label="' . esc_attr__( 'پخش', 'seyedcast' ) . '"><span></span></button>';
			echo '</li>';
		}
		echo '</ul>';
	}
}
