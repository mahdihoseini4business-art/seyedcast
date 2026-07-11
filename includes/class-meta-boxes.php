<?php
/**
 * متا باکس‌های اپیزود پادکست
 */

if (!defined('ABSPATH')) {
    exit;
}

class SeyedCast_Meta_Boxes {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_podcast_episode', array($this, 'save_meta_boxes'));
    }

    /**
     * اضافه کردن متا باکس‌ها
     */
    public function add_meta_boxes() {
        add_meta_box(
            'seyedcast_episode_info',
            __('اطلاعات اپیزود', 'seyedcast'),
            array($this, 'render_episode_info_meta_box'),
            'podcast_episode',
            'normal',
            'high'
        );

        add_meta_box(
            'seyedcast_episode_media',
            __('رسانه اپیزود', 'seyedcast'),
            array($this, 'render_episode_media_meta_box'),
            'podcast_episode',
            'normal',
            'high'
        );

        add_meta_box(
            'seyedcast_episode_links',
            __('لینک‌ها و اشتراک‌گذاری', 'seyedcast'),
            array($this, 'render_episode_links_meta_box'),
            'podcast_episode',
            'side',
            'default'
        );
    }

    /**
     * رندر متا باکس اطلاعات اپیزود
     */
    public function render_episode_info_meta_box($post) {
        wp_nonce_field('seyedcast_episode_info', 'seyedcast_episode_info_nonce');

        $episode_number = get_post_meta($post->ID, '_seyedcast_episode_number', true);
        $duration = get_post_meta($post->ID, '_seyedcast_duration', true);
        $season = get_post_meta($post->ID, '_seyedcast_season', true);
        $explicit = get_post_meta($post->ID, '_seyedcast_explicit', true);
        ?>
        <div class="seyedcast-meta-box-content">
            <table class="form-table">
                <tr>
                    <th><label for="seyedcast_episode_number"><?php _e('شماره اپیزود', 'seyedcast'); ?></label></th>
                    <td>
                        <input type="number" id="seyedcast_episode_number" name="seyedcast_episode_number" 
                               value="<?php echo esc_attr($episode_number); ?>" min="1" style="width: 100px;" />
                    </td>
                </tr>
                <tr>
                    <th><label for="seyedcast_duration"><?php _e('مدت زمان', 'seyedcast'); ?></label></th>
                    <td>
                        <input type="text" id="seyedcast_duration" name="seyedcast_duration" 
                               value="<?php echo esc_attr($duration); ?>" placeholder="00:00:00" style="width: 150px;" />
                        <p class="description"><?php _e('فرمت: HH:MM:SS', 'seyedcast'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="seyedcast_season"><?php _e('فصل', 'seyedcast'); ?></label></th>
                    <td>
                        <input type="number" id="seyedcast_season" name="seyedcast_season" 
                               value="<?php echo esc_attr($season); ?>" min="1" style="width: 100px;" />
                    </td>
                </tr>
                <tr>
                    <th><label for="seyedcast_explicit"><?php _e('محتوای صریح', 'seyedcast'); ?></label></th>
                    <td>
                        <select id="seyedcast_explicit" name="seyedcast_explicit">
                            <option value="no" <?php selected($explicit, 'no'); ?>><?php _e('خیر', 'seyedcast'); ?></option>
                            <option value="yes" <?php selected($explicit, 'yes'); ?>><?php _e('بله', 'seyedcast'); ?></option>
                            <option value="clean" <?php selected($explicit, 'clean'); ?>><?php _e('مناسب همه', 'seyedcast'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * رندر متا باکس رسانه
     */
    public function render_episode_media_meta_box($post) {
        wp_nonce_field('seyedcast_episode_media', 'seyedcast_episode_media_nonce');

        $audio_url = get_post_meta($post->ID, '_seyedcast_audio_url', true);
        $download_url = get_post_meta($post->ID, '_seyedcast_download_url', true);
        ?>
        <div class="seyedcast-meta-box-content">
            <table class="form-table">
                <tr>
                    <th><label for="seyedcast_audio_url"><?php _e('فایل صوتی', 'seyedcast'); ?></label></th>
                    <td>
                        <input type="url" id="seyedcast_audio_url" name="seyedcast_audio_url" 
                               value="<?php echo esc_url($audio_url); ?>" class="large-text" />
                        <br />
                        <button type="button" class="button seyedcast-upload-audio" data-target="seyedcast_audio_url">
                            <?php _e('انتخاب فایل صوتی', 'seyedcast'); ?>
                        </button>
                        <p class="description"><?php _e('فرمت‌های پشتیبانی شده: MP3, M4A, OGG, WAV', 'seyedcast'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="seyedcast_download_url"><?php _e('لینک دانلود', 'seyedcast'); ?></label></th>
                    <td>
                        <input type="url" id="seyedcast_download_url" name="seyedcast_download_url" 
                               value="<?php echo esc_url($download_url); ?>" class="large-text" />
                        <p class="description"><?php _e('خالی بگذارید تا از فایل صوتی استفاده شود', 'seyedcast'); ?></p>
                    </td>
                </tr>
            </table>

            <?php if ($audio_url): ?>
            <div class="seyedcast-audio-preview">
                <p><strong><?php _e('پیش‌نمایش:', 'seyedcast'); ?></strong></p>
                <audio controls style="width: 100%;">
                    <source src="<?php echo esc_url($audio_url); ?>" />
                    <?php _e('مرورگر شما از پخش صوتی پشتیبانی نمی‌کند.', 'seyedcast'); ?>
                </audio>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * رندر متا باکس لینک‌ها
     */
    public function render_episode_links_meta_box($post) {
        wp_nonce_field('seyedcast_episode_links', 'seyedcast_episode_links_nonce');

        $website_url = get_post_meta($post->ID, '_seyedcast_website_url', true);
        $telegram_url = get_post_meta($post->ID, '_seyedcast_telegram_url', true);
        $instagram_url = get_post_meta($post->ID, '_seyedcast_instagram_url', true);
        $twitter_url = get_post_meta($post->ID, '_seyedcast_twitter_url', true);
        ?>
        <div class="seyedcast-meta-box-content">
            <p>
                <label for="seyedcast_website_url"><?php _e('وبسایت', 'seyedcast'); ?></label><br />
                <input type="url" id="seyedcast_website_url" name="seyedcast_website_url" 
                       value="<?php echo esc_url($website_url); ?>" class="widefat" />
            </p>
            <p>
                <label for="seyedcast_telegram_url"><?php _e('تلگرام', 'seyedcast'); ?></label><br />
                <input type="url" id="seyedcast_telegram_url" name="seyedcast_telegram_url" 
                       value="<?php echo esc_url($telegram_url); ?>" class="widefat" />
            </p>
            <p>
                <label for="seyedcast_instagram_url"><?php _e('اینستاگرام', 'seyedcast'); ?></label><br />
                <input type="url" id="seyedcast_instagram_url" name="seyedcast_instagram_url" 
                       value="<?php echo esc_url($instagram_url); ?>" class="widefat" />
            </p>
            <p>
                <label for="seyedcast_twitter_url"><?php _e('توییتر', 'seyedcast'); ?></label><br />
                <input type="url" id="seyedcast_twitter_url" name="seyedcast_twitter_url" 
                       value="<?php echo esc_url($twitter_url); ?>" class="widefat" />
            </p>
        </div>
        <?php
    }

    /**
     * ذخیره متا داده‌ها
     */
    public function save_meta_boxes($post_id) {
        // بررسی nonce
        if (!isset($_POST['seyedcast_episode_info_nonce']) || 
            !wp_verify_nonce($_POST['seyedcast_episode_info_nonce'], 'seyedcast_episode_info')) {
            return;
        }

        if (!isset($_POST['seyedcast_episode_media_nonce']) || 
            !wp_verify_nonce($_POST['seyedcast_episode_media_nonce'], 'seyedcast_episode_media')) {
            return;
        }

        if (!isset($_POST['seyedcast_episode_links_nonce']) || 
            !wp_verify_nonce($_POST['seyedcast_episode_links_nonce'], 'seyedcast_episode_links')) {
            return;
        }

        // بررسی خودکار
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // بررسی سطح دسترسی
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // ذخیره اطلاعات اپیزود
        if (isset($_POST['seyedcast_episode_number'])) {
            update_post_meta($post_id, '_seyedcast_episode_number', 
                intval($_POST['seyedcast_episode_number']));
        }

        if (isset($_POST['seyedcast_duration'])) {
            update_post_meta($post_id, '_seyedcast_duration', 
                sanitize_text_field($_POST['seyedcast_duration']));
        }

        if (isset($_POST['seyedcast_season'])) {
            update_post_meta($post_id, '_seyedcast_season', 
                intval($_POST['seyedcast_season']));
        }

        if (isset($_POST['seyedcast_explicit'])) {
            update_post_meta($post_id, '_seyedcast_explicit', 
                sanitize_text_field($_POST['seyedcast_explicit']));
        }

        // ذخیره رسانه
        if (isset($_POST['seyedcast_audio_url'])) {
            update_post_meta($post_id, '_seyedcast_audio_url', 
                esc_url_raw($_POST['seyedcast_audio_url']));
        }

        if (isset($_POST['seyedcast_download_url'])) {
            update_post_meta($post_id, '_seyedcast_download_url', 
                esc_url_raw($_POST['seyedcast_download_url']));
        }

        // ذخیره لینک‌ها
        $link_fields = array('website', 'telegram', 'instagram', 'twitter');
        foreach ($link_fields as $field) {
            $key = 'seyedcast_' . $field . '_url';
            if (isset($_POST[$key])) {
                update_post_meta($post_id, '_' . $key, esc_url_raw($_POST[$key]));
            }
        }
    }
}
