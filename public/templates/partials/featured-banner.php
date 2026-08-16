<?php
/**
 * Featured banner slider: most viewed, newest, last listened.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = Seyedcast_Settings::get();
$cta      = ! empty( $settings['featured_cta'] ) ? $settings['featured_cta'] : __( 'گوش دهید', 'seyedcast' );
$mode     = isset( $settings['featured_mode'] ) ? $settings['featured_mode'] : 'auto';

$featured_badge = ( 'manual' === $mode ) ? __( 'انتخاب سردبیر', 'seyedcast' ) : __( 'پربازدیدترین', 'seyedcast' );
$slides         = array();

$featured = Seyedcast_App::banner_slide_from_show(
	Seyedcast_App::get_featured_show(),
	$featured_badge,
	'featured'
);
if ( $featured ) {
	$slides[] = $featured;
}

$newest = Seyedcast_App::banner_slide_from_show(
	Seyedcast_App::get_newest_show(),
	__( 'جدیدترین', 'seyedcast' ),
	'newest'
);
if ( $newest ) {
	$slides[] = $newest;
}

if ( ! $slides ) {
	return;
}

/**
 * Render one show-based slide.
 *
 * @param array  $slide Slide data.
 * @param string $cta   CTA label.
 * @param bool   $active Whether active.
 */
$render_show_slide = static function ( $slide, $cta, $active ) {
	$payload      = ! empty( $slide['payload'] ) ? $slide['payload'] : null;
	$payload_json = $payload ? esc_attr( wp_json_encode( $payload ) ) : '';
	?>
	<article class="seyedcast-featured__slide<?php echo $active ? ' is-active' : ''; ?>" data-slide="<?php echo esc_attr( $slide['key'] ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
		<div class="seyedcast-featured__media">
			<img class="seyedcast-featured__cover" src="<?php echo esc_url( $slide['cover'] ); ?>" alt="<?php echo esc_attr( $slide['title'] ); ?>" width="280" height="280" />
		</div>
		<div class="seyedcast-featured__body">
			<span class="seyedcast-featured__badge"><?php echo esc_html( $slide['badge'] ); ?></span>
			<h2 class="seyedcast-featured__title">
				<a href="<?php echo esc_url( $slide['url'] ); ?>" data-seyedcast-nav><?php echo esc_html( $slide['title'] ); ?></a>
			</h2>
			<?php if ( ! empty( $slide['excerpt'] ) ) : ?>
				<p class="seyedcast-featured__sub"><?php echo esc_html( $slide['excerpt'] ); ?></p>
			<?php endif; ?>
			<div class="seyedcast-featured__meta">
				<span class="seyedcast-chip"><?php echo esc_html( sprintf( _n( '%s اپیزود', '%s اپیزود', (int) $slide['count'], 'seyedcast' ), number_format_i18n( (int) $slide['count'] ) ) ); ?></span>
				<?php if ( ! empty( $slide['views'] ) ) : ?>
					<span class="seyedcast-chip"><?php echo esc_html( sprintf( __( '%s بازدید', 'seyedcast' ), number_format_i18n( (int) $slide['views'] ) ) ); ?></span>
				<?php endif; ?>
			</div>
			<div class="seyedcast-featured__actions">
				<?php if ( $payload && ! empty( $payload['audio'] ) ) : ?>
					<button type="button" class="seyedcast-btn seyedcast-btn--primary seyedcast-btn--lg" data-seyedcast-play="<?php echo $payload_json; ?>">
						<span class="seyedcast-btn__icon" aria-hidden="true"></span>
						<?php echo esc_html( $cta ); ?>
					</button>
				<?php endif; ?>
				<a class="seyedcast-btn seyedcast-btn--ghost" href="<?php echo esc_url( $slide['url'] ); ?>" data-seyedcast-nav>
					<?php esc_html_e( 'مشاهده پادکست', 'seyedcast' ); ?>
				</a>
			</div>
		</div>
	</article>
	<?php
};
?>
<section class="seyedcast-featured seyedcast-featured--slider" data-seyedcast-banner-slider aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'بنرهای ویژه پادکست', 'seyedcast' ); ?>">
	<button type="button" class="seyedcast-featured__arrow seyedcast-featured__arrow--prev" data-role="prev" hidden aria-label="<?php esc_attr_e( 'اسلاید قبلی', 'seyedcast' ); ?>">
		<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
	</button>
	<button type="button" class="seyedcast-featured__arrow seyedcast-featured__arrow--next" data-role="next" hidden aria-label="<?php esc_attr_e( 'اسلاید بعدی', 'seyedcast' ); ?>">
		<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
	</button>

	<div class="seyedcast-featured__viewport">
		<?php
		$i = 0;
		foreach ( $slides as $slide ) {
			$render_show_slide( $slide, $cta, 0 === $i );
			$i++;
		}
		?>

		<article class="seyedcast-featured__slide seyedcast-featured__slide--resume" data-slide="resume" data-seyedcast-resume-slide hidden>
			<div class="seyedcast-featured__media">
				<img class="seyedcast-featured__cover" data-role="cover" src="" alt="" width="280" height="280" />
			</div>
			<div class="seyedcast-featured__body">
				<span class="seyedcast-featured__badge"><?php esc_html_e( 'آخرین پادکستی که گوش دادید', 'seyedcast' ); ?></span>
				<h2 class="seyedcast-featured__title">
					<a href="#" data-role="link" data-seyedcast-nav><span data-role="title"></span></a>
				</h2>
				<p class="seyedcast-featured__sub" data-role="meta"></p>
				<div class="seyedcast-featured__actions">
					<button type="button" class="seyedcast-btn seyedcast-btn--primary seyedcast-btn--lg" data-role="resume">
						<span class="seyedcast-btn__icon" aria-hidden="true"></span>
						<?php esc_html_e( 'ادامه پخش', 'seyedcast' ); ?>
					</button>
					<a class="seyedcast-btn seyedcast-btn--ghost" href="#" data-role="link-secondary" data-seyedcast-nav>
						<?php esc_html_e( 'مشاهده اپیزود', 'seyedcast' ); ?>
					</a>
				</div>
			</div>
		</article>
	</div>

	<div class="seyedcast-featured__nav" hidden data-role="nav">
		<div class="seyedcast-featured__dots" data-role="dots" role="tablist" aria-label="<?php esc_attr_e( 'اسلایدها', 'seyedcast' ); ?>"></div>
	</div>
</section>
