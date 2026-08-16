<?php
/**
 * SEO meta and JSON-LD.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Seo
 */
class Seyedcast_Seo {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_meta' ), 5 );
		add_action( 'wp_head', array( $this, 'output_schema' ), 20 );
		add_filter( 'document_title_parts', array( $this, 'title_parts' ) );
	}

	/**
	 * Open Graph / description meta.
	 */
	public function output_meta() {
		if ( is_singular( 'seyedcast_episode' ) ) {
			$post    = get_queried_object();
			$show_id = Seyedcast_Meta::get_show_id( $post->ID );
			$desc    = $post->post_excerpt ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
			$image   = get_the_post_thumbnail_url( $post, 'large' );
			if ( ! $image && $show_id ) {
				$image = get_the_post_thumbnail_url( $show_id, 'large' );
			}
			$this->print_og( get_the_title( $post ), $desc, get_permalink( $post ), $image, 'article' );
			return;
		}

		if ( is_singular( 'seyedcast_show' ) ) {
			$post  = get_queried_object();
			$desc  = $post->post_excerpt ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
			$image = get_the_post_thumbnail_url( $post, 'large' );
			$this->print_og( get_the_title( $post ), $desc, get_permalink( $post ), $image, 'website' );
			return;
		}

		if ( is_post_type_archive( 'seyedcast_show' ) ) {
			$settings = Seyedcast_Settings::get();
			$title    = ! empty( $settings['archive_title'] ) ? $settings['archive_title'] : __( 'پادکست‌ها', 'seyedcast' );
			$this->print_og( $title, $title, get_post_type_archive_link( 'seyedcast_show' ), '', 'website' );
		}
	}

	/**
	 * Print OG tags.
	 *
	 * @param string $title Title.
	 * @param string $desc  Description.
	 * @param string $url   URL.
	 * @param string $image Image.
	 * @param string $type  OG type.
	 */
	private function print_og( $title, $desc, $url, $image, $type ) {
		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		echo '<meta property="og:type" content="' . esc_attr( $type ) . '" />' . "\n";
		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
		}
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
	}

	/**
	 * JSON-LD schema.
	 */
	public function output_schema() {
		if ( is_singular( 'seyedcast_episode' ) ) {
			$episode = get_queried_object();
			$show_id = Seyedcast_Meta::get_show_id( $episode->ID );
			$show    = $show_id ? get_post( $show_id ) : null;
			$audio   = Seyedcast_Meta::get_audio_url( $episode->ID );
			$image   = get_the_post_thumbnail_url( $episode, 'large' );
			if ( ! $image && $show ) {
				$image = get_the_post_thumbnail_url( $show, 'large' );
			}
			$duration = get_post_meta( $episode->ID, '_seyedcast_duration', true );
			$number   = get_post_meta( $episode->ID, '_seyedcast_episode_number', true );

			$data = array(
				'@context'      => 'https://schema.org',
				'@type'         => 'PodcastEpisode',
				'name'          => get_the_title( $episode ),
				'description'   => $episode->post_excerpt ? $episode->post_excerpt : wp_trim_words( wp_strip_all_tags( $episode->post_content ), 40 ),
				'datePublished' => get_the_date( 'c', $episode ),
				'url'           => get_permalink( $episode ),
			);
			if ( $image ) {
				$data['image'] = $image;
			}
			if ( $number ) {
				$data['episodeNumber'] = $number;
			}
			if ( $duration ) {
				$data['timeRequired'] = $this->iso_duration( $duration );
			}
			if ( $audio ) {
				$data['associatedMedia'] = array(
					'@type'      => 'MediaObject',
					'contentUrl' => $audio,
				);
			}
			if ( $show ) {
				$data['partOfSeries'] = array(
					'@type' => 'PodcastSeries',
					'name'  => get_the_title( $show ),
					'url'   => get_permalink( $show ),
				);
			}
			$this->print_jsonld( $data );
			return;
		}

		if ( is_singular( 'seyedcast_show' ) ) {
			$show     = get_queried_object();
			$image    = get_the_post_thumbnail_url( $show, 'large' );
			$episodes = Seyedcast_Meta::get_show_episodes( $show->ID );
			$data     = array(
				'@context'    => 'https://schema.org',
				'@type'       => 'PodcastSeries',
				'name'        => get_the_title( $show ),
				'description' => $show->post_excerpt ? $show->post_excerpt : wp_trim_words( wp_strip_all_tags( $show->post_content ), 40 ),
				'url'         => get_permalink( $show ),
			);
			if ( $image ) {
				$data['image'] = $image;
			}
			if ( $episodes ) {
				$data['numberOfEpisodes'] = count( $episodes );
			}
			$this->print_jsonld( $data );
		}
	}

	/**
	 * Convert mm:ss or hh:mm:ss to ISO 8601 duration.
	 *
	 * @param string $duration Duration string.
	 * @return string
	 */
	private function iso_duration( $duration ) {
		$parts = array_map( 'intval', explode( ':', $duration ) );
		if ( 3 === count( $parts ) ) {
			return sprintf( 'PT%dH%dM%dS', $parts[0], $parts[1], $parts[2] );
		}
		if ( 2 === count( $parts ) ) {
			return sprintf( 'PT%dM%dS', $parts[0], $parts[1] );
		}
		return 'PT' . absint( $duration ) . 'S';
	}

	/**
	 * Print JSON-LD script.
	 *
	 * @param array $data Data.
	 */
	private function print_jsonld( $data ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}

	/**
	 * Archive document title.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function title_parts( $parts ) {
		if ( is_post_type_archive( 'seyedcast_show' ) ) {
			$settings        = Seyedcast_Settings::get();
			$parts['title']  = ! empty( $settings['archive_title'] ) ? $settings['archive_title'] : __( 'پادکست‌ها', 'seyedcast' );
		}
		return $parts;
	}
}
