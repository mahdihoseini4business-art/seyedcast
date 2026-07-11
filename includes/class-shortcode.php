<?php
/**
 * شورتکد اصلی پادکست
 */

if (!defined('ABSPATH')) {
    exit;
}

class SeyedCast_Shortcode {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('seyedcast', array($this, 'render_shortcode'));
        add_shortcode('seyedcast_player', array($this, 'render_player_shortcode'));
    }

    /**
     * رندر شورتکد اصلی
     * [seyedcast style="grid" posts_per_page="12" category="" tag="" orderby="date" order="DESC"]
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'style'            => 'grid',
            'posts_per_page'   => get_option('seyedcast_episodes_per_page', 12),
            'category'         => '',
            'tag'              => '',
            'orderby'          => 'date',
            'order'            => 'DESC',
            'show_search'      => true,
            'show_filters'     => true,
        ), $atts, 'seyedcast');

        // پارامترهای کوئری
        $args = array(
            'post_type'      => 'podcast_episode',
            'posts_per_page' => intval($atts['posts_per_page']),
            'orderby'        => sanitize_text_field($atts['orderby']),
            'order'          => sanitize_text_field($atts['order']),
            'post_status'    => 'publish',
        );

        // فیلتر دسته‌بندی
        if (!empty($atts['category'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'podcast_category',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($atts['category']),
                ),
            );
        }

        // فیلتر تگ
        if (!empty($atts['tag'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'podcast_tag',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($atts['tag']),
                ),
            );
        }

        ob_start();
        
        // شروع کنترلر پادکست
        echo '<div class="seyedcast-container" data-style="' . esc_attr($atts['style']) . '">';
        
        // هدر
        $this->render_header($atts);
        
        // فیلترها و جستجو
        if ($atts['show_filters'] || $atts['show_search']) {
            $this->render_filters($atts);
        }
        
        // لیست اپیزودها
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            echo '<div class="seyedcast-episodes seyedcast-' . esc_attr($atts['style']) . '-layout">';
            
            while ($query->have_posts()) {
                $query->the_post();
                $this->render_episode_card(get_the_ID(), $atts['style']);
            }
            
            echo '</div>';
            
            // پجینیشن
            if ($query->max_num_pages > 1) {
                $this->render_pagination($query);
            }
        } else {
            echo '<div class="seyedcast-no-episodes">';
            echo '<p>' . __('اپیزودی یافت نشد.', 'seyedcast') . '</p>';
            echo '</div>';
        }
        
        wp_reset_postdata();
        
        // پلیر چسبناک
        $this->render_sticky_player();
        
        echo '</div>';
        
        return ob_get_clean();
    }

    /**
     * رندر هدر پادکست
     */
    private function render_header($atts) {
        $title = get_option('seyedcast_podcast_title', 'پادکست من');
        $description = get_option('seyedcast_podcast_description', '');
        $cover = get_option('seyedcast_podcast_cover', '');
        
        // دریافت آمار کلی
        $stats = SeyedCast_Stats::get_instance()->get_total_stats();
        ?>
        <div class="seyedcast-header">
            <?php if ($cover): ?>
            <div class="seyedcast-cover">
                <img src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr($title); ?>" />
            </div>
            <?php endif; ?>
            
            <div class="seyedcast-info">
                <h1 class="seyedcast-title"><?php echo esc_html($title); ?></h1>
                <?php if ($description): ?>
                <p class="seyedcast-description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
                
                <div class="seyedcast-stats">
                    <span class="seyedcast-stat">
                        <span class="dashicons dashicons-microphone"></span>
                        <?php printf(__(' %d اپیزود', 'seyedcast'), $stats['total_episodes']); ?>
                    </span>
                    <span class="seyedcast-stat">
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php printf(__(' %d پخش', 'seyedcast'), $stats['total_plays']); ?>
                    </span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * رندر فیلترها و جستجو
     */
    private function render_filters($atts) {
        $categories = get_terms(array(
            'taxonomy'   => 'podcast_category',
            'hide_empty' => true,
        ));
        
        if (is_wp_error($categories) || empty($categories)) {
            return;
        }
        ?>
        <div class="seyedcast-filters">
            <?php if ($atts['show_search']): ?>
            <div class="seyedcast-search">
                <input type="text" class="seyedcast-search-input" 
                       placeholder="<?php _e('جستجوی اپیزود...', 'seyedcast'); ?>" />
                <span class="dashicons dashicons-search"></span>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_filters'] && !empty($categories)): ?>
            <div class="seyedcast-filter-buttons">
                <button class="seyedcast-filter-btn active" data-filter="all">
                    <?php _e('همه', 'seyedcast'); ?>
                </button>
                <?php foreach ($categories as $category): ?>
                <button class="seyedcast-filter-btn" data-filter="<?php echo esc_attr($category->slug); ?>">
                    <?php echo esc_html($category->name); ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * رندر کارت اپیزود
     */
    private function render_episode_card($post_id, $style = 'grid') {
        $audio_url = get_post_meta($post_id, '_seyedcast_audio_url', true);
        $duration = get_post_meta($post_id, '_seyedcast_duration', true);
        $episode_number = get_post_meta($post_id, '_seyedcast_episode_number', true);
        $stats = SeyedCast_Stats::get_instance()->get_episode_stats($post_id);
        
        $categories = get_the_terms($post_id, 'podcast_category');
        $category = (!empty($categories) && !is_wp_error($categories)) ? $categories[0] : null;
        
        $cover = get_the_post_thumbnail_url($post_id, 'medium') ?: get_option('seyedcast_podcast_cover');
        ?>
        <div class="seyedcast-episode-card" 
             data-episode-id="<?php echo esc_attr($post_id); ?>"
             data-audio-url="<?php echo esc_url($audio_url); ?>"
             data-categories="<?php echo esc_attr($category ? $category->slug : ''); ?>">
            
            <div class="seyedcast-card-cover">
                <?php if ($cover): ?>
                <img src="<?php echo esc_url($cover); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
                <?php endif; ?>
                
                <?php if ($audio_url): ?>
                <button class="seyedcast-play-btn" data-audio="<?php echo esc_url($audio_url); ?>">
                    <span class="dashicons dashicons-controls-play"></span>
                </button>
                <?php endif; ?>
            </div>
            
            <div class="seyedcast-card-content">
                <div class="seyedcast-card-meta">
                    <?php if ($episode_number): ?>
                    <span class="seyedcast-episode-number">#<?php echo esc_html($episode_number); ?></span>
                    <?php endif; ?>
                    
                    <?php if ($category): ?>
                    <span class="seyedcast-category" style="background-color: <?php echo esc_attr(get_term_meta($category->term_id, 'category_color', true) ?: '#1DB954'); ?>">
                        <?php echo esc_html($category->name); ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <h3 class="seyedcast-card-title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h3>
                
                <?php if ($style === 'list'): ?>
                <p class="seyedcast-card-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 20); ?></p>
                <?php endif; ?>
                
                <div class="seyedcast-card-footer">
                    <span class="seyedcast-date">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php echo get_the_date(); ?>
                    </span>
                    
                    <?php if ($duration): ?>
                    <span class="seyedcast-duration">
                        <span class="dashicons dashicons-clock"></span>
                        <?php echo esc_html($duration); ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if (get_option('seyedcast_show_play_count', 1)): ?>
                    <span class="seyedcast-plays">
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php echo esc_html($stats['play_count']); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * رندر پجینیشن
     */
    private function render_pagination($query) {
        echo '<div class="seyedcast-pagination">';
        
        echo paginate_links(array(
            'total'     => $query->max_num_pages,
            'prev_text' => '<span class="dashicons dashicons-arrow-right"></span>',
            'next_text' => '<span class="dashicons dashicons-arrow-left"></span>',
        ));
        
        echo '</div>';
    }

    /**
     * رندر پلیر چسبناک
     */
    private function render_sticky_player() {
        $player_color = get_option('seyedcast_player_color', '#1DB954');
        $player_bg = get_option('seyedcast_player_bg_color', '#191414');
        ?>
        <div class="seyedcast-sticky-player" style="background-color: <?php echo esc_attr($player_bg); ?>;">
            <div class="seyedcast-player-inner">
                <div class="seyedcast-player-info">
                    <img class="seyedcast-player-cover" src="" alt="" />
                    <div class="seyedcast-player-details">
                        <span class="seyedcast-player-title"></span>
                        <span class="seyedcast-player-subtitle"></span>
                    </div>
                </div>
                
                <div class="seyedcast-player-controls">
                    <button class="seyedcast-player-btn" id="seyedcast-prev">
                        <span class="dashicons dashicons-controls-back"></span>
                    </button>
                    <button class="seyedcast-player-btn seyedcast-play-pause" id="seyedcast-play">
                        <span class="dashicons dashicons-controls-play"></span>
                    </button>
                    <button class="seyedcast-player-btn" id="seyedcast-next">
                        <span class="dashicons dashicons-controls-forward"></span>
                    </button>
                </div>
                
                <div class="seyedcast-player-progress">
                    <span class="seyedcast-current-time">0:00</span>
                    <div class="seyedcast-progress-bar">
                        <div class="seyedcast-progress" style="background-color: <?php echo esc_attr($player_color); ?>;"></div>
                    </div>
                    <span class="seyedcast-duration-time">0:00</span>
                </div>
                
                <div class="seyedcast-player-extra">
                    <button class="seyedcast-player-btn" id="seyedcast-volume">
                        <span class="dashicons dashicons-controls-volumeon"></span>
                    </button>
                    <div class="seyedcast-volume-slider">
                        <input type="range" min="0" max="100" value="100" />
                    </div>
                    <button class="seyedcast-player-btn" id="seyedcast-download">
                        <span class="dashicons dashicons-download"></span>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * شورتکد پلیر تکی
     * [seyedcast_player id="123"]
     */
    public function render_player_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'seyedcast_player');

        $post_id = intval($atts['id']);
        if (!$post_id) {
            return '';
        }

        $audio_url = get_post_meta($post_id, '_seyedcast_audio_url', true);
        if (!$audio_url) {
            return '';
        }

        $cover = get_the_post_thumbnail_url($post_id, 'medium') ?: get_option('seyedcast_podcast_cover');
        $duration = get_post_meta($post_id, '_seyedcast_duration', true);
        $episode_number = get_post_meta($post_id, '_seyedcast_episode_number', true);
        
        ob_start();
        ?>
        <div class="seyedcast-single-player" data-audio="<?php echo esc_url($audio_url); ?>">
            <?php if ($cover): ?>
            <img class="seyedcast-single-cover" src="<?php echo esc_url($cover); ?>" alt="" />
            <?php endif; ?>
            
            <div class="seyedcast-single-info">
                <?php if ($episode_number): ?>
                <span class="seyedcast-single-number">#<?php echo esc_html($episode_number); ?></span>
                <?php endif; ?>
                <h4 class="seyedcast-single-title"><?php echo get_the_title($post_id); ?></h4>
                <?php if ($duration): ?>
                <span class="seyedcast-single-duration"><?php echo esc_html($duration); ?></span>
                <?php endif; ?>
            </div>
            
            <button class="seyedcast-single-play-btn">
                <span class="dashicons dashicons-controls-play"></span>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
}
