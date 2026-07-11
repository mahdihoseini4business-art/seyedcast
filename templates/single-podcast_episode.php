<?php
/**
 * قالب صفحه تکی اپیزود پادکست
 */

get_header();

$audio_url = get_post_meta(get_the_ID(), '_seyedcast_audio_url', true);
$duration = get_post_meta(get_the_ID(), '_seyedcast_duration', true);
$episode_number = get_post_meta(get_the_ID(), '_seyedcast_episode_number', true);
$download_url = get_post_meta(get_the_ID(), '_seyedcast_download_url', true) ?: $audio_url;
$show_notes = get_post_meta(get_the_ID(), '_seyedcast_show_notes', true);

$cover = get_the_post_thumbnail_url(get_the_ID(), 'large') ?: get_option('seyedcast_podcast_cover');

$stats = SeyedCast_Stats::get_instance()->get_episode_stats(get_the_ID());
?>

<article class="seyedcast-single-episode">
    <header class="seyedcast-single-header">
        <?php if ($cover): ?>
        <div class="seyedcast-single-cover">
            <img src="<?php echo esc_url($cover); ?>" alt="<?php the_title_attribute(); ?>" />
        </div>
        <?php endif; ?>
        
        <div class="seyedcast-single-info">
            <?php if ($episode_number): ?>
            <span class="seyedcast-single-number">#<?php echo esc_html($episode_number); ?></span>
            <?php endif; ?>
            
            <h1 class="seyedcast-single-title"><?php the_title(); ?></h1>
            
            <div class="seyedcast-single-meta">
                <span class="seyedcast-single-date">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php echo get_the_date(); ?>
                </span>
                
                <?php if ($duration): ?>
                <span class="seyedcast-single-duration">
                    <span class="dashicons dashicons-clock"></span>
                    <?php echo esc_html($duration); ?>
                </span>
                <?php endif; ?>
                
                <?php if (get_option('seyedcast_show_play_count', 1)): ?>
                <span class="seyedcast-single-plays">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php echo esc_html($stats['play_count']); ?> پخش
                </span>
                <?php endif; ?>
            </div>
            
            <div class="seyedcast-single-actions">
                <?php if ($audio_url): ?>
                <a href="<?php echo esc_url($download_url); ?>" class="seyedcast-btn seyedcast-download" 
                   data-episode-id="<?php echo esc_attr(get_the_ID()); ?>">
                    <span class="dashicons dashicons-download"></span>
                    دانلود
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <div class="seyedcast-single-content">
        <?php if ($audio_url): ?>
        <div class="seyedcast-single-player-wrapper">
            <?php echo SeyedCast_Shortcode::get_instance()->render_player_shortcode(array('id' => get_the_ID())); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($show_notes): ?>
        <div class="seyedcast-single-show-notes">
            <h2>یادداشت‌های اپیزود</h2>
            <div class="seyedcast-show-notes-content">
                <?php echo wp_kses_post($show_notes); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="seyedcast-single-description">
            <h2>توضیحات</h2>
            <div class="seyedcast-description-content">
                <?php the_content(); ?>
            </div>
        </div>
        
        <?php
        $tags = get_the_terms(get_the_ID(), 'podcast_tag');
        if ($tags && !is_wp_error($tags)):
        ?>
        <div class="seyedcast-single-tags">
            <h2>تگ‌ها</h2>
            <div class="seyedcast-tags-list">
                <?php foreach ($tags as $tag): ?>
                <a href="<?php echo esc_url(get_term_link($tag)); ?>" class="seyedcast-tag">
                    <?php echo esc_html($tag->name); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php
        $website_url = get_post_meta(get_the_ID(), '_seyedcast_website_url', true);
        $telegram_url = get_post_meta(get_the_ID(), '_seyedcast_telegram_url', true);
        $instagram_url = get_post_meta(get_the_ID(), '_seyedcast_instagram_url', true);
        $twitter_url = get_post_meta(get_the_ID(), '_seyedcast_twitter_url', true);
        
        if ($website_url || $telegram_url || $instagram_url || $twitter_url):
        ?>
        <div class="seyedcast-single-share">
            <h2>اشتراک‌گذاری</h2>
            <div class="seyedcast-share-links">
                <?php if ($website_url): ?>
                <a href="<?php echo esc_url($website_url); ?>" target="_blank" class="seyedcast-share-link">
                    <span class="dashicons dashicons-admin-site"></span>
                </a>
                <?php endif; ?>
                
                <?php if ($telegram_url): ?>
                <a href="<?php echo esc_url($telegram_url); ?>" target="_blank" class="seyedcast-share-link seyedcast-telegram">
                    <span class="dashicons dashicons-facebook"></span>
                </a>
                <?php endif; ?>
                
                <?php if ($instagram_url): ?>
                <a href="<?php echo esc_url($instagram_url); ?>" target="_blank" class="seyedcast-share-link seyedcast-instagram">
                    <span class="dashicons dashicons-instagram"></span>
                </a>
                <?php endif; ?>
                
                <?php if ($twitter_url): ?>
                <a href="<?php echo esc_url($twitter_url); ?>" target="_blank" class="seyedcast-share-link seyedcast-twitter">
                    <span class="dashicons dashicons-twitter"></span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</article>

<?php
get_footer();
