<?php
/**
 * پست تایپ سفارشی اپیزود پادکست
 */

if (!defined('ABSPATH')) {
    exit;
}

class SeyedCast_Post_Type {

    private static $instance = null;
    private $post_type = 'podcast_episode';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_filter('manage_' . $this->post_type . '_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_' . $this->post_type . '_posts_custom_column', array($this, 'custom_columns_content'), 10, 2);
        add_action('manage_edit-' . $this->post_type . '_sortable_columns', array($this, 'sortable_columns'));
    }

    /**
     * ثبت پست تایپ اپیزود
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('اپیزودها', 'post type general name', 'seyedcast'),
            'singular_name'         => _x('اپیزود', 'post type singular name', 'seyedcast'),
            'menu_name'             => _x('پادکست', 'admin menu text', 'seyedcast'),
            'add_new'               => __('افزودن اپیزود جدید', 'seyedcast'),
            'add_new_item'          => __('افزودن اپیزود جدید', 'seyedcast'),
            'edit_item'             => __('ویرایش اپیزود', 'seyedcast'),
            'new_item'              => __('اپیزود جدید', 'seyedcast'),
            'view_item'             => __('مشاهده اپیزود', 'seyedcast'),
            'search_items'          => __('جستجوی اپیزود', 'seyedcast'),
            'not_found'             => __('اپیزودی یافت نشد', 'seyedcast'),
            'not_found_in_trash'    => __('اپیزودی در زباله‌دان یافت نشد', 'seyedcast'),
            'all_items'             => __('همه اپیزودها', 'seyedcast'),
            'archives'              => __('بایگانی اپیزودها', 'seyedcast'),
            'featured_image'        => __('تصویر کاور اپیزود', 'seyedcast'),
            'set_featured_image'    => __('تنظیم تصویر کاور', 'seyedcast'),
            'remove_featured_image' => __('حذف تصویر کاور', 'seyedcast'),
            'use_featured_image'    => __('استفاده به عنوان تصویر کاور', 'seyedcast'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'radio', 'with_front' => false),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 26,
            'menu_icon'          => 'dashicons-microphone',
            'supports'           => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions'),
        );

        register_post_type($this->post_type, $args);
    }

    /**
     * ستون‌های سفارشی در لیست
     */
    public function set_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['episode_number'] = __('شماره اپیزود', 'seyedcast');
        $new_columns['duration'] = __('مدت زمان', 'seyedcast');
        $new_columns['category'] = __('دسته‌بندی', 'seyedcast');
        $new_columns['stats'] = __('آمار', 'seyedcast');
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    /**
     * محتوای ستون‌های سفارشی
     */
    public function custom_columns_content($column, $post_id) {
        switch ($column) {
            case 'episode_number':
                $number = get_post_meta($post_id, '_seyedcast_episode_number', true);
                echo $number ? '#' . esc_html($number) : '-';
                break;

            case 'duration':
                $duration = get_post_meta($post_id, '_seyedcast_duration', true);
                echo $duration ? esc_html($duration) : '-';
                break;

            case 'category':
                $categories = get_the_terms($post_id, 'podcast_category');
                if ($categories && !is_wp_error($categories)) {
                    foreach ($categories as $cat) {
                        echo '<span class="seyedcast-badge">' . esc_html($cat->name) . '</span> ';
                    }
                } else {
                    echo '-';
                }
                break;

            case 'stats':
                $stats = SeyedCast_Stats::get_instance()->get_episode_stats($post_id);
                echo '<span title="پخش: ' . esc_attr($stats['play_count']) . '">';
                echo '<span class="dashicons dashicons-controls-play"></span> ' . esc_html($stats['play_count']);
                echo '</span>';
                break;
        }
    }

    /**
     * ستون‌های قابل مرتب‌سازی
     */
    public function sortable_columns($columns) {
        $columns['episode_number'] = 'episode_number';
        $columns['duration'] = 'duration';
        return $columns;
    }

    /**
     * دریافت شناسه پست تایپ
     */
    public function get_post_type() {
        return $this->post_type;
    }
}
