<?php
/**
 * App sidebar: ad banners + upcoming premiere countdowns.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = Seyedcast_Settings::get();
$banners  = isset( $settings['sidebar_banners'] ) && is_array( $settings['sidebar_banners'] ) ? $settings['sidebar_banners'] : array();
$events   = isset( $settings['upcoming_events'] ) && is_array( $settings['upcoming_events'] ) ? $settings['upcoming_events'] : array();

$banners = array_filter(
	$banners,
	static function ( $b ) {
		return ! empty( $b['image_id'] );
	}
);

$now    = time();
$events = array_values(
	array_filter(
		$events,
		static function ( $e ) use ( $now ) {
			if ( empty( $e['starts_at'] ) ) {
				return false;
			}
			$ts = Seyedcast_Settings::parse_event_datetime( $e['starts_at'] );
			return $ts && $ts > ( $now - DAY_IN_SECONDS );
		}
	)
);

// Hide empty sidebar from visitors (no useless drawer / column).
if ( ! $events && ! $banners ) {
	return;
}
?>
<aside class="seyedcast-sidebar" id="seyedcast-sidebar" aria-label="<?php esc_attr_e( 'سایدبار پادکست', 'seyedcast' ); ?>">
	<?php if ( $events ) : ?>
		<section class="seyedcast-sidebar__block">
			<h3 class="seyedcast-sidebar__title"><?php esc_html_e( 'پخش‌های آینده', 'seyedcast' ); ?></h3>
			<ul class="seyedcast-premiere-list">
				<?php foreach ( $events as $event ) : ?>
					<?php
					$ts    = Seyedcast_Settings::parse_event_datetime( $event['starts_at'] );
					$title = ! empty( $event['title'] ) ? $event['title'] : '';
					$url   = '';
					if ( ! empty( $event['episode_id'] ) ) {
						$ep = get_post( (int) $event['episode_id'] );
						if ( $ep && 'seyedcast_episode' === $ep->post_type ) {
							if ( ! $title ) {
								$title = get_the_title( $ep );
							}
							$url = get_permalink( $ep );
						}
					}
					if ( ! $title ) {
						$title = __( 'اپیزود جدید', 'seyedcast' );
					}
					$past = $ts <= $now;
					?>
					<li class="seyedcast-premiere<?php echo $past ? ' is-live' : ''; ?>">
						<?php if ( $url ) : ?>
							<a class="seyedcast-premiere__title" href="<?php echo esc_url( $url ); ?>" data-seyedcast-nav><?php echo esc_html( $title ); ?></a>
						<?php else : ?>
							<span class="seyedcast-premiere__title"><?php echo esc_html( $title ); ?></span>
						<?php endif; ?>
						<time class="seyedcast-premiere__when" datetime="<?php echo esc_attr( gmdate( 'c', $ts ) ); ?>">
							<?php echo esc_html( wp_date( 'Y/m/d H:i', $ts ) ); ?>
						</time>
						<?php if ( ! $past ) : ?>
							<div class="seyedcast-countdown" data-seyedcast-countdown data-start="<?php echo esc_attr( (string) ( $ts * 1000 ) ); ?>" aria-live="polite">
								<span data-unit="d">00</span><small><?php esc_html_e( 'روز', 'seyedcast' ); ?></small>
								<span data-unit="h">00</span><small><?php esc_html_e( 'ساعت', 'seyedcast' ); ?></small>
								<span data-unit="m">00</span><small><?php esc_html_e( 'دقیقه', 'seyedcast' ); ?></small>
								<span data-unit="s">00</span><small><?php esc_html_e( 'ثانیه', 'seyedcast' ); ?></small>
							</div>
						<?php else : ?>
							<span class="seyedcast-premiere__live"><?php esc_html_e( 'در دسترس', 'seyedcast' ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( $banners ) : ?>
		<section class="seyedcast-sidebar__block">
			<h3 class="seyedcast-sidebar__title"><?php esc_html_e( 'پیشنهادها', 'seyedcast' ); ?></h3>
			<div class="seyedcast-ad-stack">
				<?php foreach ( $banners as $banner ) : ?>
					<?php
					$img = wp_get_attachment_image_url( (int) $banner['image_id'], 'medium_large' );
					if ( ! $img ) {
						continue;
					}
					$alt  = ! empty( $banner['alt'] ) ? $banner['alt'] : __( 'بنر تبلیغاتی', 'seyedcast' );
					$link = ! empty( $banner['url'] ) ? $banner['url'] : '';
					?>
					<?php if ( $link ) : ?>
						<a class="seyedcast-ad" href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener sponsored" data-seyedcast-external>
							<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy" />
						</a>
					<?php else : ?>
						<div class="seyedcast-ad">
							<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy" />
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
</aside>
