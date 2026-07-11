<?php
/**
 * حذف افزونه SeyedCast
 */

// جلوگیری از دسترسی مستقیم
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// حذف تنظیمات
$options = array(
    'podcast_title',
    'podcast_description',
    'podcast_cover',
    'episodes_per_page',
    'player_color',
    'player_bg_color',
    'player_autoplay',
    'player_mode',
    'seo_title',
    'seo_description',
    'seo_keywords',
    'enable_stats',
    'show_play_count',
    'show_download_count',
);

foreach ($options as $option) {
    delete_option('seyedcast_' . $option);
}

// حذف جدول آمار
global $wpdb;
$table_name = $wpdb->prefix . 'seyedcast_stats';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// پاکسازی متا داده‌ها
$episodes = get_posts(array(
    'post_type'      => 'podcast_episode',
    'posts_per_page' => -1,
    'post_status'    => 'any',
));

foreach ($episodes as $episode) {
    delete_post_meta($episode->ID, '_seyedcast_audio_url');
    delete_post_meta($episode->ID, '_seyedcast_download_url');
    delete_post_meta($episode->ID, '_seyedcast_duration');
    delete_post_meta($episode->ID, '_seyedcast_episode_number');
    delete_post_meta($episode->ID, '_seyedcast_season');
    delete_post_meta($episode->ID, '_seyedcast_explicit');
    delete_post_meta($episode->ID, '_seyedcast_website_url');
    delete_post_meta($episode->ID, '_seyedcast_telegram_url');
    delete_post_meta($episode->ID, '_seyedcast_instagram_url');
    delete_post_meta($episode->ID, '_seyedcast_twitter_url');
}

// حذف بازنویسی لینک‌ها
flush_rewrite_rules();
