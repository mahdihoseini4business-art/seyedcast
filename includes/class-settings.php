<?php
/**
 * صفحه تنظیمات افزونه
 */

if (!defined('ABSPATH')) {
    exit;
}

class SeyedCast_Settings {

    private static $instance = null;
    private $option_name = 'seyedcast_';
    private $settings_page;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_settings_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
    }

    /**
     * اضافه کردن منوی تنظیمات
     */
    public function add_settings_menu() {
        $this->settings_page = add_submenu_page(
            'edit.php?post_type=podcast_episode',
            __('تنظیمات پادکست', 'seyedcast'),
            __('تنظیمات', 'seyedcast'),
            'manage_options',
            'seyedcast-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * بارگذاری اسکریپت‌های ادمین
     */
    public function admin_scripts($hook) {
        if ($hook !== $this->settings_page) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        wp_enqueue_style(
            'seyedcast-admin',
            SEYEDCAST_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            SEYEDCAST_VERSION
        );

        wp_enqueue_script(
            'seyedcast-admin',
            SEYEDCAST_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'wp-color-picker'),
            SEYEDCAST_VERSION,
            true
        );
    }

    /**
     * ثبت تنظیمات
     */
    public function register_settings() {
        // تب عمومی
        register_setting('seyedcast_settings', $this->option_name . 'podcast_title', 'sanitize_text_field');
        register_setting('seyedcast_settings', $this->option_name . 'podcast_description', 'sanitize_textarea_field');
        register_setting('seyedcast_settings', $this->option_name . 'podcast_cover', 'esc_url_raw');
        register_setting('seyedcast_settings', $this->option_name . 'episodes_per_page', 'intval');

        // تب پلیر
        register_setting('seyedcast_settings', $this->option_name . 'player_color', 'sanitize_hex_color');
        register_setting('seyedcast_settings', $this->option_name . 'player_bg_color', 'sanitize_hex_color');
        register_setting('seyedcast_settings', $this->option_name . 'player_autoplay', 'intval');
        register_setting('seyedcast_settings', $this->option_name . 'player_mode', 'sanitize_text_field');

        // تب سئو
        register_setting('seyedcast_settings', $this->option_name . 'seo_title', 'sanitize_text_field');
        register_setting('seyedcast_settings', $this->option_name . 'seo_description', 'sanitize_textarea_field');
        register_setting('seyedcast_settings', $this->option_name . 'seo_keywords', 'sanitize_text_field');

        // تب آمار
        register_setting('seyedcast_settings', $this->option_name . 'enable_stats', 'intval');
        register_setting('seyedcast_settings', $this->option_name . 'show_play_count', 'intval');
        register_setting('seyedcast_settings', $this->option_name . 'show_download_count', 'intval');
    }

    /**
     * رندر صفحه تنظیمات
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1><?php _e('تنظیمات پادکست', 'seyedcast'); ?></h1>

            <?php settings_errors('seyedcast_settings'); ?>

            <nav class="nav-tab-wrapper">
                <a href="?post_type=podcast_episode&page=seyedcast-settings&tab=general" 
                   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                   <?php _e('عمومی', 'seyedcast'); ?>
                </a>
                <a href="?post_type=podcast_episode&page=seyedcast-settings&tab=player" 
                   class="nav-tab <?php echo $active_tab === 'player' ? 'nav-tab-active' : ''; ?>">
                   <?php _e('پلیر', 'seyedcast'); ?>
                </a>
                <a href="?post_type=podcast_episode&page=seyedcast-settings&tab=seo" 
                   class="nav-tab <?php echo $active_tab === 'seo' ? 'nav-tab-active' : ''; ?>">
                   <?php _e('سئو', 'seyedcast'); ?>
                </a>
                <a href="?post_type=podcast_episode&page=seyedcast-settings&tab=stats" 
                   class="nav-tab <?php echo $active_tab === 'stats' ? 'nav-tab-active' : ''; ?>">
                   <?php _e('آمار', 'seyedcast'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('seyedcast_settings');

                    switch ($active_tab) {
                        case 'player':
                            $this->render_player_tab();
                            break;
                        case 'seo':
                            $this->render_seo_tab();
                            break;
                        case 'stats':
                            $this->render_stats_tab();
                            break;
                        default:
                            $this->render_general_tab();
                            break;
                    }

                    submit_button();
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * تب تنظیمات عمومی
     */
    private function render_general_tab() {
        ?>
        <table class="form-table">
            <tr>
                <th><label for="podcast_title"><?php _e('عنوان پادکست', 'seyedcast'); ?></label></th>
                <td>
                    <input type="text" id="podcast_title" name="<?php echo $this->option_name; ?>podcast_title" 
                           value="<?php echo esc_attr(get_option($this->option_name . 'podcast_title')); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="podcast_description"><?php _e('توضیحات پادکست', 'seyedcast'); ?></label></th>
                <td>
                    <textarea id="podcast_description" name="<?php echo $this->option_name; ?>podcast_description" 
                              rows="5" class="large-text"><?php echo esc_textarea(get_option($this->option_name . 'podcast_description')); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="podcast_cover"><?php _e('تصویر کاور پیش‌فرض', 'seyedcast'); ?></label></th>
                <td>
                    <input type="url" id="podcast_cover" name="<?php echo $this->option_name; ?>podcast_cover" 
                           value="<?php echo esc_url(get_option($this->option_name . 'podcast_cover')); ?>" 
                           class="regular-text" />
                    <button type="button" class="button seyedcast-upload-image" data-target="podcast_cover">
                        <?php _e('انتخاب تصویر', 'seyedcast'); ?>
                    </button>
                    <?php if (get_option($this->option_name . 'podcast_cover')): ?>
                    <p>
                        <img src="<?php echo esc_url(get_option($this->option_name . 'podcast_cover')); ?>" 
                             style="max-width: 200px; margin-top: 10px;" />
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="episodes_per_page"><?php _e('تعداد اپیزود در هر صفحه', 'seyedcast'); ?></label></th>
                <td>
                    <input type="number" id="episodes_per_page" name="<?php echo $this->option_name; ?>episodes_per_page" 
                           value="<?php echo esc_attr(get_option($this->option_name . 'episodes_per_page')); ?>" 
                           min="1" max="50" style="width: 100px;" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * تب تنظیمات پلیر
     */
    private function render_player_tab() {
        ?>
        <table class="form-table">
            <tr>
                <th><label for="player_color"><?php _e('رنگ اصلی پلیر', 'seyedcast'); ?></label></th>
                <td>
                    <input type="text" id="player_color" name="<?php echo $this->option_name; ?>player_color" 
                           value="<?php echo esc_attr(get_option($this->option_name . 'player_color')); ?>" 
                           class="seyedcast-color-picker" />
                </td>
            </tr>
            <tr>
                <th><label for="player_bg_color"><?php _e('رنگ پس‌زمینه پلیر', 'seyedcast'); ?></label></th>
                <td>
                    <input type="text" id="player_bg_color" name="<?php echo $this->option_name; ?>player_bg_color" 
                           value="<?php echo esc_attr(get_option($this->option_name . 'player_bg_color')); ?>" 
                           class="seyedcast-color-picker" />
                </td>
            </tr>
            <tr>
                <th><label for="player_autoplay"><?php _e('پخش خودکار', 'seyedcast'); ?></label></th>
                <td>
                    <select id="player_autoplay" name="<?php echo $this->option_name; ?>player_autoplay">
                        <option value="0" <?php selected(get_option($this->option_name . 'player_autoplay'), 0); ?>>
                            <?php _e('غیرفعال', 'seyedcast'); ?>
                        </option>
                        <option value="1" <?php selected(get_option($this->option_name . 'player_autoplay'), 1); ?>>
                            <?php _e('فعال', 'seyedcast'); ?>
                        </option>
                    </select>
                    <p class="description"><?php _e('توجه: اکثر مرورگرها پخش خودکار را بلاک می‌کنند', 'seyedcast'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="player_mode"><?php _e('حالت پلیر', 'seyedcast'); ?></label></th>
                <td>
                    <select id="player_mode" name="<?php echo $this->option_name; ?>player_mode">
                        <option value="simple" <?php selected(get_option($this->option_name . 'player_mode'), 'simple'); ?>>
                            <?php _e('ساده', 'seyedcast'); ?>
                        </option>
                        <option value="advanced" <?php selected(get_option($this->option_name . 'player_mode'), 'advanced'); ?>>
                            <?php _e('پیشرفته', 'seyedcast'); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * تب تنظیمات سئو
     */
    private function render_seo_tab() {
        ?>
        <table class="form-table">
            <tr>
                <th><label for="seo_title"><?php _e('عنوان صفحه پادکست', 'seyedcast'); ?></label></th>
                <td>
                    <input type="text" id="seo_title" name="<?php echo $this->option_name; ?>seo_title" 
                           value="<?php echo esc_attr(get_option($this->option_name . 'seo_title')); ?>" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="seo_description"><?php _e('توضیحات متا', 'seyedcast'); ?></label></th>
                <td>
                    <textarea id="seo_description" name="<?php echo $this->option_name; ?>seo_description" 
                              rows="3" class="large-text"><?php echo esc_textarea(get_option($this->option_name . 'seo_description')); ?></textarea>
                    <p class="description"><?php _e('حداکثر 160 کاراکتر', 'seyedcast'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="seo_keywords"><?php _e('کلمات کلیدی', 'seyedcast'); ?></label></th>
                <td>
                    <input type="text" id="seo_keywords" name="<?php echo $this->option_name; ?>seo_keywords" 
                           value="<?php echo esc_attr(get_option($this->option_name . 'seo_keywords')); ?>" 
                           class="large-text" />
                    <p class="description"><?php _e('با کاما جدا کنید', 'seyedcast'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * تب تنظیمات آمار
     */
    private function render_stats_tab() {
        ?>
        <table class="form-table">
            <tr>
                <th><?php _e('فعال‌سازی آمار', 'seyedcast'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $this->option_name; ?>enable_stats" value="1" 
                               <?php checked(get_option($this->option_name . 'enable_stats'), 1); ?> />
                        <?php _e('فعال‌سازی سیستم آمار', 'seyedcast'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><?php _e('نمایش آمار', 'seyedcast'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $this->option_name; ?>show_play_count" value="1" 
                               <?php checked(get_option($this->option_name . 'show_play_count'), 1); ?> />
                        <?php _e('نمایش تعداد پخش', 'seyedcast'); ?>
                    </label>
                    <br />
                    <label>
                        <input type="checkbox" name="<?php echo $this->option_name; ?>show_download_count" value="1" 
                               <?php checked(get_option($this->option_name . 'show_download_count'), 1); ?> />
                        <?php _e('نمایش تعداد دانلود', 'seyedcast'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><?php _e('پاکسازی آمار', 'seyedcast'); ?></th>
                <td>
                    <button type="button" class="button button-secondary" id="seyedcast-clear-stats">
                        <?php _e('پاکسازی آمار قدیمی', 'seyedcast'); ?>
                    </button>
                    <p class="description"><?php _e('آمار بیش از 90 روز پاکسازی می‌شود', 'seyedcast'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
}
