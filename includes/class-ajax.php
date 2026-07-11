<?php
/**
 * درخواست‌های AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}

class SeyedCast_Ajax {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_seyedcast_play_episode', array($this, 'ajax_play_episode'));
        add_action('wp_ajax_nopriv_seyedcast_play_episode', array($this, 'ajax_play_episode'));
        
        add_action('wp_ajax_seyedcast_download_episode', array($this, 'ajax_download_episode'));
        add_action('wp_ajax_nopriv_seyedcast_download_episode', array($this, 'ajax_download_episode'));
        
        add_action('wp_ajax_seyedcast_load_more', array($this, 'ajax_load_more'));
        add_action('wp_ajax_nopriv_seyedcast_load_more', array($this, 'ajax_load_more'));
        
        add_action('wp_ajax_seyedcast_search', array($this, 'ajax_search'));
        add_action('wp_ajax_nopriv_seyedcast_search', array($this, 'ajax_search'));
        
        add_action('wp_ajax_seyedcast_clear_stats', array($this, 'ajax_clear_stats'));
    }

    /**
     * ثبت آمار پخش
     */
    public function ajax_play_episode() {
        check_ajax_referer('seyedcast_nonce', 'nonce');
        
        $episode_id = intval($_POST['episode_id']);
        if (!$episode_id) {
            wp_send_json_error(array('message' => 'شناسه اپیزود نامعتبر'));
        }
        
        $user_ip = $this->get_user_ip();
        SeyedCast_Stats::get_instance()->record_play($episode_id, $user_ip);
        
        wp_send_json_success(array('message' => 'آمار ثبت شد'));
    }

    /**
     * ثبت آمار دانلود
     */
    public function ajax_download_episode() {
        check_ajax_referer('seyedcast_nonce', 'nonce');
        
        $episode_id = intval($_POST['episode_id']);
        if (!$episode_id) {
            wp_send_json_error(array('message' => 'شناسه اپیزود نامعتبر'));
        }
        
        $audio_url = get_post_meta($episode_id, '_seyedcast_audio_url', true);
        $download_url = get_post_meta($episode_id, '_seyedcast_download_url', true);
        $url = $download_url ?: $audio_url;
        
        if (!$url) {
            wp_send_json_error(array('message' => 'فایل صوتی یافت نشد'));
        }
        
        $user_ip = $this->get_user_ip();
        SeyedCast_Stats::get_instance()->record_download($episode_id, $user_ip);
        
        wp_send_json_success(array('url' => $url));
    }

    /**
     * بارگذاری بیشتر
     */
    public function ajax_load_more() {
        check_ajax_referer('seyedcast_nonce', 'nonce');
        
        $page = intval($_POST['page']) ?: 1;
        $per_page = intval($_POST['per_page']) ?: get_option('seyedcast_episodes_per_page', 12);
        $category = sanitize_text_field($_POST['category'] ?? '');
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        $args = array(
            'post_type'      => 'podcast_episode',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        
        if ($category && $category !== 'all') {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'podcast_category',
                    'field'    => 'slug',
                    'terms'    => $category,
                ),
            );
        }
        
        if ($search) {
            $args['s'] = $search;
        }
        
        $query = new WP_Query($args);
        
        if (!$query->have_posts()) {
            wp_send_json_success(array('html' => '', 'found' => 0));
        }
        
        ob_start();
        
        while ($query->have_posts()) {
            $query->the_post();
            SeyedCast_Shortcode::get_instance()->render_episode_card(get_the_ID(), 'grid');
        }
        
        wp_reset_postdata();
        
        wp_send_json_success(array(
            'html'  => ob_get_clean(),
            'found' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ));
    }

    /**
     * جستجوی اپیزودها
     */
    public function ajax_search() {
        check_ajax_referer('seyedcast_nonce', 'nonce');
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        if (strlen($search) < 2) {
            wp_send_json_success(array('html' => ''));
        }
        
        $args = array(
            'post_type'      => 'podcast_episode',
            'posts_per_page' => 20,
            'post_status'    => 'publish',
            's'              => $search,
        );
        
        $query = new WP_Query($args);
        
        ob_start();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                SeyedCast_Shortcode::get_instance()->render_episode_card(get_the_ID(), 'grid');
            }
        } else {
            echo '<div class="seyedcast-no-results">';
            echo '<p>' . __('نتیجه‌ای یافت نشد.', 'seyedcast') . '</p>';
            echo '</div>';
        }
        
        wp_reset_postdata();
        
        wp_send_json_success(array('html' => ob_get_clean()));
    }

    /**
     * پاکسازی آمار
     */
    public function ajax_clear_stats() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'دسترسی غیرمجاز'));
        }
        
        check_ajax_referer('seyedcast_nonce', 'nonce');
        
        SeyedCast_Stats::get_instance()->cleanup_old_stats(90);
        
        wp_send_json_success(array('message' => 'آمار قدیمی پاکسازی شد'));
    }

    /**
     * دریافت IP کاربر
     */
    private function get_user_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }
}
