<?php
/**
 * سیستم آمار پخش و دانلود
 */

if (!defined('ABSPATH')) {
    exit;
}

class SeyedCast_Stats {

    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'seyedcast_stats';
    }

    /**
     * ایجاد جدول آمار
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            episode_id bigint(20) NOT NULL,
            play_count int(11) DEFAULT 0,
            download_count int(11) DEFAULT 0,
            last_played datetime DEFAULT NULL,
            user_ip varchar(45) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY episode_id (episode_id),
            KEY last_played (last_played)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * ثبت پخش
     */
    public function record_play($episode_id, $user_ip = '') {
        if (!get_option('seyedcast_enable_stats', 1)) {
            return;
        }
        
        global $wpdb;
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $this->table_name WHERE episode_id = %d AND user_ip = %s",
            $episode_id,
            $user_ip
        ));
        
        if ($existing) {
            $wpdb->update(
                $this->table_name,
                array(
                    'play_count'   => $wpdb->get_var($wpdb->prepare(
                        "SELECT play_count FROM $this->table_name WHERE id = %d",
                        $existing->id
                    )) + 1,
                    'last_played'  => current_time('mysql'),
                ),
                array('id' => $existing->id),
                array('%d', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $this->table_name,
                array(
                    'episode_id'   => $episode_id,
                    'play_count'   => 1,
                    'download_count' => 0,
                    'last_played'  => current_time('mysql'),
                    'user_ip'      => $user_ip,
                ),
                array('%d', '%d', '%d', '%s', '%s')
            );
        }
    }

    /**
     * ثبت دانلود
     */
    public function record_download($episode_id, $user_ip = '') {
        if (!get_option('seyedcast_enable_stats', 1)) {
            return;
        }
        
        global $wpdb;
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $this->table_name WHERE episode_id = %d AND user_ip = %s",
            $episode_id,
            $user_ip
        ));
        
        if ($existing) {
            $wpdb->update(
                $this->table_name,
                array(
                    'download_count' => $wpdb->get_var($wpdb->prepare(
                        "SELECT download_count FROM $this->table_name WHERE id = %d",
                        $existing->id
                    )) + 1,
                ),
                array('id' => $existing->id),
                array('%d'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $this->table_name,
                array(
                    'episode_id'     => $episode_id,
                    'play_count'     => 0,
                    'download_count' => 1,
                    'last_played'    => current_time('mysql'),
                    'user_ip'        => $user_ip,
                ),
                array('%d', '%d', '%d', '%s', '%s')
            );
        }
    }

    /**
     * دریافت آمار یک اپیزود
     */
    public function get_episode_stats($episode_id) {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT play_count, download_count FROM $this->table_name WHERE episode_id = %d",
            $episode_id
        ));
        
        return array(
            'play_count'     => $result ? intval($result->play_count) : 0,
            'download_count' => $result ? intval($result->download_count) : 0,
        );
    }

    /**
     * دریافت آمار کلی
     */
    public function get_total_stats() {
        global $wpdb;
        
        $total_episodes = wp_count_posts('podcast_episode')->publish;
        
        $result = $wpdb->get_row(
            "SELECT SUM(play_count) as total_plays, SUM(download_count) as total_downloads 
             FROM $this->table_name"
        );
        
        return array(
            'total_episodes' => $total_episodes,
            'total_plays'    => $result ? intval($result->total_plays) : 0,
            'total_downloads'=> $result ? intval($result->total_downloads) : 0,
        );
    }

    /**
     * دریافت محبوب‌ترین اپیزودها
     */
    public function get_popular_episodes($limit = 10) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT episode_id, play_count, download_count 
             FROM $this->table_name 
             ORDER BY play_count DESC 
             LIMIT %d",
            $limit
        ));
        
        return $results;
    }

    /**
     * پاکسازی آمار قدیمی
     */
    public function cleanup_old_stats($days = 90) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $this->table_name WHERE last_played < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}
