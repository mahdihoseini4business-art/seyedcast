<?php
/**
 * مدیریت دارایی‌ها (CSS/JS)
 */

if (!defined('ABSPATH')) {
    exit;
}

class SeyedCast_Assets {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * بارگذاری دارایی‌های فرانت‌اند
     */
    public function enqueue_frontend_assets() {
        global $post;
        
        // بررسی اینکه آیا شورتکد در صفحه استفاده شده
        $is_podcast_page = is_singular() && get_post_type() === 'podcast_episode';
        $is_archive = is_post_type_archive('podcast_episode');
        $has_shortcode = is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'seyedcast');
        
        if (!$is_podcast_page && !$is_archive && !$has_shortcode) {
            return;
        }
        
        // استایل اصلی
        wp_enqueue_style(
            'seyedcast-frontend',
            SEYEDCAST_PLUGIN_URL . 'public/css/podcast.css',
            array(),
            SEYEDCAST_VERSION
        );
        
        // استایل پلیر
        wp_enqueue_style(
            'seyedcast-player',
            SEYEDCAST_PLUGIN_URL . 'public/css/player.css',
            array('seyedcast-frontend'),
            SEYEDCAST_VERSION
        );
        
        // آیکون‌های Dashicons
        wp_enqueue_style('dashicons');
        
        // اسکریپت اصلی
        wp_enqueue_script(
            'seyedcast-frontend',
            SEYEDCAST_PLUGIN_URL . 'public/js/podcast.js',
            array('jquery'),
            SEYEDCAST_VERSION,
            true
        );
        
        // ارسال متغیرها به JS
        wp_localize_script('seyedcast-frontend', 'seyedcast_ajax', array(
            'url'   => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seyedcast_nonce'),
        ));
    }

    /**
     * بارگذاری دارایی‌های ادمین
     */
    public function enqueue_admin_assets($hook) {
        global $post_type;
        
        // فقط در صفحات پادکست
        if ($post_type !== 'podcast_episode' && strpos($hook, 'seyedcast') === false) {
            return;
        }
        
        // استایل ادمین
        wp_enqueue_style(
            'seyedcast-admin',
            SEYEDCAST_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            SEYEDCAST_VERSION
        );
        
        // آیکون‌های Dashicons
        wp_enqueue_style('dashicons');
        
        // اسکریپت ادمین
        wp_enqueue_script(
            'seyedcast-admin',
            SEYEDCAST_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            SEYEDCAST_VERSION,
            true
        );
        
        // رسانه وردپرس
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_media();
        }
    }
}
