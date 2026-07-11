<?php
/**
 * Plugin Name: SeyedCast
 * Plugin URI: https://example.com/seyedcast
 * Description: یک افزونه حرفه‌ای مدیریت پادکست با پلیر سفارشی و رابط کاربری زیبا
 * Version: 1.0.0
 * Author: Seyed
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: seyedcast
 * Domain Path: /languages
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// ثابت‌های افزونه
define('SEYEDCAST_VERSION', '1.0.0');
define('SEYEDCAST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SEYEDCAST_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SEYEDCAST_PLUGIN_BASENAME', plugin_basename(__FILE__));

// بارگذاری کلاس‌ها
require_once SEYEDCAST_PLUGIN_DIR . 'includes/class-post-type.php';
require_once SEYEDCAST_PLUGIN_DIR . 'includes/class-taxonomy.php';
require_once SEYEDCAST_PLUGIN_DIR . 'includes/class-meta-boxes.php';
require_once SEYEDCAST_PLUGIN_DIR . 'includes/class-settings.php';
require_once SEYEDCAST_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once SEYEDCAST_PLUGIN_DIR . 'includes/class-player.php';
require_once SEYEDCAST_PLUGIN_DIR . 'includes/class-stats.php';
require_once SEYEDCAST_PLUGIN_DIR . 'includes/class-ajax.php';
require_once SEYEDCAST_PLUGIN_DIR . 'includes/class-assets.php';
require_once SEYEDCAST_PLUGIN_DIR . 'includes/class-frontend.php';

/**
 * کلاس اصلی افزونه SeyedCast
 */
final class SeyedCast {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('init', array($this, 'init'));
        add_action('wp_loaded', array($this, 'wp_loaded'));
    }

    /**
     * فعال‌سازی افزونه
     */
    public function activate() {
        // ثبت پست تایپ و تاکسونومی‌ها
        SeyedCast_Post_Type::get_instance();
        SeyedCast_Taxonomy::get_instance();

        // بازنویسی لینک‌ها
        flush_rewrite_rules();

        // ذخیره تنظیمات پیش‌فرض
        $this->default_options();

        // ایجاد جدول آمار
        SeyedCast_Stats::get_instance()->create_table();
    }

    /**
     * غیرفعال‌سازی افزونه
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * مقداردهی اولیه
     */
    public function init() {
        SeyedCast_Post_Type::get_instance();
        SeyedCast_Taxonomy::get_instance();
        SeyedCast_Meta_Boxes::get_instance();
        SeyedCast_Shortcode::get_instance();
        SeyedCast_Player::get_instance();
        SeyedCast_Stats::get_instance();
        SeyedCast_Ajax::get_instance();
        SeyedCast_Assets::get_instance();
        SeyedCast_Frontend::get_instance();
    }

    /**
     * پس از بارگذاری کامل وردپرس
     */
    public function wp_loaded() {
        // تنظیمات فقط در پنل مدیریت
        if (is_admin()) {
            SeyedCast_Settings::get_instance();
        }
    }

    /**
     * تنظیمات پیش‌فرض
     */
    private function default_options() {
        $defaults = array(
            'podcast_title' => 'پادکست من',
            'podcast_description' => 'توضیحات پادکست',
            'podcast_cover' => '',
            'episodes_per_page' => 12,
            'player_color' => '#1DB954',
            'player_bg_color' => '#191414',
            'player_autoplay' => 0,
            'player_mode' => 'advanced',
            'seo_title' => 'پادکست‌ها',
            'seo_description' => 'لیست تمام اپیزودهای پادکست',
            'seo_keywords' => 'پادکست، صوت، آموزش',
            'enable_stats' => 1,
            'show_play_count' => 1,
            'show_download_count' => 1,
        );

        foreach ($defaults as $key => $value) {
            if (get_option('seyedcast_' . $key) === false) {
                update_option('seyedcast_' . $key, $value);
            }
        }
    }
}

// راه‌اندازی افزونه
function seyedcast() {
    return SeyedCast::get_instance();
}

seyedcast();
