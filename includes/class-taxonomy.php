<?php
/**
 * دسته‌بندی و تگ پادکست
 */

if (!defined('ABSPATH')) {
    exit;
}

class SeyedCast_Taxonomy {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_taxonomies'));
        add_action('podcast_category_add_form_fields', array($this, 'add_category_fields'));
        add_action('podcast_category_edit_form_fields', array($this, 'edit_category_fields'));
        add_action('created_podcast_category', array($this, 'save_category_fields'));
        add_action('edited_podcast_category', array($this, 'save_category_fields'));
    }

    /**
     * ثبت تاکسونومی‌ها
     */
    public function register_taxonomies() {
        // دسته‌بندی پادکست
        $category_labels = array(
            'name'              => _x('دسته‌بندی‌ها', 'taxonomy general name', 'seyedcast'),
            'singular_name'     => _x('دسته‌بندی', 'taxonomy singular name', 'seyedcast'),
            'search_items'      => __('جستجوی دسته‌بندی‌ها', 'seyedcast'),
            'all_items'         => __('همه دسته‌بندی‌ها', 'seyedcast'),
            'parent_item'       => __('دسته‌بندی والد', 'seyedcast'),
            'parent_item_colon' => __('دسته‌بندی والد:', 'seyedcast'),
            'edit_item'         => __('ویرایش دسته‌بندی', 'seyedcast'),
            'update_item'       => __('بروزرسانی دسته‌بندی', 'seyedcast'),
            'add_new_item'      => __('افزودن دسته‌بندی جدید', 'seyedcast'),
            'new_item_name'     => __('نام دسته‌بندی جدید', 'seyedcast'),
            'menu_name'         => __('دسته‌بندی‌ها', 'seyedcast'),
        );

        register_taxonomy('podcast_category', array('podcast_episode'), array(
            'hierarchical'      => true,
            'labels'            => $category_labels,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'podcast-category'),
        ));

        // تگ پادکست
        $tag_labels = array(
            'name'              => _x('تگ‌ها', 'taxonomy general name', 'seyedcast'),
            'singular_name'     => _x('تگ', 'taxonomy singular name', 'seyedcast'),
            'search_items'      => __('جستجوی تگ‌ها', 'seyedcast'),
            'all_items'         => __('همه تگ‌ها', 'seyedcast'),
            'edit_item'         => __('ویرایش تگ', 'seyedcast'),
            'update_item'       => __('بروزرسانی تگ', 'seyedcast'),
            'add_new_item'      => __('افزودن تگ جدید', 'seyedcast'),
            'new_item_name'     => __('نام تگ جدید', 'seyedcast'),
            'menu_name'         => __('تگ‌ها', 'seyedcast'),
        );

        register_taxonomy('podcast_tag', array('podcast_episode'), array(
            'hierarchical'      => false,
            'labels'            => $tag_labels,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'podcast-tag'),
        ));
    }

    /**
     * فیلدهای اضافی در فرم اضافه کردن دسته‌بندی
     */
    public function add_category_fields() {
        ?>
        <div class="form-field">
            <label for="category_icon"><?php _e('آیکون', 'seyedcast'); ?></label>
            <input type="text" name="category_icon" id="category_icon" value="" />
            <p><?php _e('کلاس آیکون Dashicons (مثلاً dashicons-microphone)', 'seyedcast'); ?></p>
        </div>
        <div class="form-field">
            <label for="category_color"><?php _e('رنگ', 'seyedcast'); ?></label>
            <input type="color" name="category_color" id="category_color" value="#1DB954" />
        </div>
        <?php
    }

    /**
     * فیلدهای اضافی در فرم ویرایش دسته‌بندی
     */
    public function edit_category_fields($term) {
        $icon = get_term_meta($term->term_id, 'category_icon', true);
        $color = get_term_meta($term->term_id, 'category_color', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="category_icon"><?php _e('آیکون', 'seyedcast'); ?></label></th>
            <td>
                <input type="text" name="category_icon" id="category_icon" value="<?php echo esc_attr($icon); ?>" />
                <p class="description"><?php _e('کلاس آیکون Dashicons', 'seyedcast'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="category_color"><?php _e('رنگ', 'seyedcast'); ?></label></th>
            <td>
                <input type="color" name="category_color" id="category_color" value="<?php echo esc_attr($color); ?>" />
            </td>
        </tr>
        <?php
    }

    /**
     * ذخیره فیلدهای اضافی دسته‌بندی
     */
    public function save_category_fields($term_id) {
        if (isset($_POST['category_icon'])) {
            update_term_meta($term_id, 'category_icon', sanitize_text_field($_POST['category_icon']));
        }
        if (isset($_POST['category_color'])) {
            update_term_meta($term_id, 'category_color', sanitize_hex_color($_POST['category_color']));
        }
    }
}
