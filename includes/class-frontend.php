<?php
/**
 * خروجی فرانت‌اند
 */

if (!defined('ABSPATH')) {
    exit;
}

class SeyedCast_Frontend {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_head', array($this, 'add_meta_tags'));
        add_filter('the_content', array($this, 'auto_add_player'));
        add_action('wp_enqueue_scripts', array($this, 'add_schema_markup'));
    }

    /**
     * اضافه کردن متا تگ‌های سئو
     */
    public function add_meta_tags() {
        if (!is_singular('podcast_episode')) {
            return;
        }
        
        $description = get_the_excerpt();
        $description = wp_strip_all_tags($description);
        $description = substr($description, 0, 160);
        
        $image = get_the_post_thumbnail_url(get_the_ID(), 'large') ?: get_option('seyedcast_podcast_cover');
        
        // متا تگ‌ها
        echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
        
        // Open Graph
        echo '<meta property="og:title" content="' . esc_attr(get_the_title()) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_the_permalink()) . '" />' . "\n";
        
        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
        }
        
        // Twitter Card
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr(get_the_title()) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        
        if ($image) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
        }
    }

    /**
     * اضافه کردن خودکار پلیر به اپیزودها
     */
    public function auto_add_player($content) {
        if (!is_singular('podcast_episode')) {
            return $content;
        }
        
        $audio_url = get_post_meta(get_the_ID(), '_seyedcast_audio_url', true);
        if (!$audio_url) {
            return $content;
        }
        
        $player_html = SeyedCast_Shortcode::get_instance()->render_player_shortcode(array('id' => get_the_ID()));
        
        return $player_html . $content;
    }

    /**
     * اضافه کردن Schema Markup
     */
    public function add_schema_markup() {
        if (!is_singular('podcast_episode')) {
            return;
        }
        
        $audio_url = get_post_meta(get_the_ID(), '_seyedcast_audio_url', true);
        if (!$audio_url) {
            return;
        }
        
        $duration = get_post_meta(get_the_ID(), '_seyedcast_duration', true);
        $duration_iso = $this->convert_duration_to_iso($duration);
        
        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'PodcastEpisode',
            'name'        => get_the_title(),
            'description' => wp_strip_all_tags(get_the_excerpt()),
            'datePublished' => get_the_date('c'),
            'url'         => get_the_permalink(),
            'associatedMedia' => array(
                '@type'         => 'AudioObject',
                'contentUrl'    => $audio_url,
                'duration'      => $duration_iso,
            ),
        );
        
        $image = get_the_post_thumbnail_url(get_the_ID(), 'large');
        if ($image) {
            $schema['image'] = $image;
        }
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>' . "\n";
    }

    /**
     * تبدیل مدت زمان به فرمت ISO
     */
    private function convert_duration_to_iso($duration) {
        if (empty($duration)) {
            return 'PT0S';
        }
        
        $parts = explode(':', $duration);
        $hours = intval($parts[0] ?? 0);
        $minutes = intval($parts[1] ?? 0);
        $seconds = intval($parts[2] ?? 0);
        
        $iso = 'PT';
        if ($hours > 0) $iso .= $hours . 'H';
        if ($minutes > 0) $iso .= $minutes . 'M';
        if ($seconds > 0) $iso .= $seconds . 'S';
        
        return $iso === 'PT' ? 'PT0S' : $iso;
    }
}
