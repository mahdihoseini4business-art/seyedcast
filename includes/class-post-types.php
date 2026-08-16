<?php
/**
 * Custom post types and taxonomies.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seyedcast_Post_Types
 */
class Seyedcast_Post_Types {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'after_setup_theme', array( __CLASS__, 'register_image_sizes' ), 20 );
	}

	/**
	 * Square cover crop size.
	 */
	public static function register_image_sizes() {
		add_image_size( 'seyedcast_cover', 600, 600, true );
	}

	/**
	 * Register CPTs and taxonomies.
	 */
	public static function register() {
		$settings = Seyedcast_Settings::get();
		$base     = ! empty( $settings['base_slug'] ) ? sanitize_title( $settings['base_slug'] ) : 'podcasts';

		register_post_type(
			'seyedcast_show',
			array(
				'labels'              => array(
					'name'               => __( 'پادکست‌ها', 'seyedcast' ),
					'singular_name'      => __( 'پادکست', 'seyedcast' ),
					'add_new'            => __( 'افزودن پادکست', 'seyedcast' ),
					'add_new_item'       => __( 'افزودن پادکست جدید', 'seyedcast' ),
					'edit_item'          => __( 'ویرایش پادکست', 'seyedcast' ),
					'new_item'           => __( 'پادکست جدید', 'seyedcast' ),
					'view_item'          => __( 'مشاهده پادکست', 'seyedcast' ),
					'search_items'       => __( 'جستجوی پادکست', 'seyedcast' ),
					'not_found'          => __( 'پادکستی یافت نشد', 'seyedcast' ),
					'not_found_in_trash' => __( 'در زباله‌دان پادکستی نیست', 'seyedcast' ),
					'menu_name'          => __( 'پادکست‌ها', 'seyedcast' ),
				),
				'public'              => true,
				'has_archive'         => $base,
				'rewrite'             => array(
					'slug'       => $base,
					'with_front' => false,
				),
				'menu_icon'           => 'dashicons-microphone',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments' ),
				'show_in_rest'        => true,
				'exclude_from_search' => false,
				'publicly_queryable'  => true,
				'show_in_menu'        => 'seyedcast',
			)
		);

		register_post_type(
			'seyedcast_episode',
			array(
				'labels'              => array(
					'name'               => __( 'اپیزودها', 'seyedcast' ),
					'singular_name'      => __( 'اپیزود', 'seyedcast' ),
					'add_new'            => __( 'افزودن اپیزود', 'seyedcast' ),
					'add_new_item'       => __( 'افزودن اپیزود جدید', 'seyedcast' ),
					'edit_item'          => __( 'ویرایش اپیزود', 'seyedcast' ),
					'new_item'           => __( 'اپیزود جدید', 'seyedcast' ),
					'view_item'          => __( 'مشاهده اپیزود', 'seyedcast' ),
					'search_items'       => __( 'جستجوی اپیزود', 'seyedcast' ),
					'not_found'          => __( 'اپیزودی یافت نشد', 'seyedcast' ),
					'not_found_in_trash' => __( 'در زباله‌دان اپیزودی نیست', 'seyedcast' ),
					'menu_name'          => __( 'اپیزودها', 'seyedcast' ),
				),
				'public'              => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'menu_icon'           => 'dashicons-playlist-audio',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments' ),
				'show_in_rest'        => true,
				'exclude_from_search' => false,
				'publicly_queryable'  => true,
				'show_in_menu'        => 'seyedcast',
			)
		);

		register_taxonomy(
			'seyedcast_topic',
			array( 'seyedcast_show', 'seyedcast_episode' ),
			array(
				'labels'            => array(
					'name'          => __( 'موضوعات', 'seyedcast' ),
					'singular_name' => __( 'موضوع', 'seyedcast' ),
					'search_items'  => __( 'جستجوی موضوع', 'seyedcast' ),
					'all_items'     => __( 'همه موضوعات', 'seyedcast' ),
					'edit_item'     => __( 'ویرایش موضوع', 'seyedcast' ),
					'add_new_item'  => __( 'افزودن موضوع', 'seyedcast' ),
					'menu_name'     => __( 'موضوعات', 'seyedcast' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'         => $base . '/topic',
					'with_front'   => false,
					'hierarchical' => true,
				),
				'show_in_menu'      => true,
			)
		);
	}
}
