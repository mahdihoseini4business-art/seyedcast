<?php
/**
 * Archive of podcast shows (app shell).
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

ob_start();
?>
<main class="seyedcast-app seyedcast-archive" dir="rtl">
	<section class="seyedcast-section">
		<div class="seyedcast-section-head">
			<h2><?php esc_html_e( 'همه پادکست‌ها', 'seyedcast' ); ?></h2>
			<span class="seyedcast-section-head__hint"><?php esc_html_e( 'برای شروع روی کاور بزنید', 'seyedcast' ); ?></span>
		</div>
		<div class="seyedcast-shows-grid">
			<?php if ( have_posts() ) : ?>
				<?php
				while ( have_posts() ) :
					the_post();
					$cover = Seyedcast_Templates::cover_url( get_the_ID() );
					$count = count( Seyedcast_Meta::get_show_episodes( get_the_ID() ) );
					?>
					<a class="seyedcast-show-tile" href="<?php the_permalink(); ?>" data-seyedcast-nav>
						<span class="seyedcast-show-tile__art">
							<img src="<?php echo esc_url( $cover ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" width="300" height="300" loading="lazy" />
							<span class="seyedcast-show-tile__play" aria-hidden="true"><span></span></span>
						</span>
						<span class="seyedcast-show-tile__title"><?php the_title(); ?></span>
						<span class="seyedcast-show-tile__meta"><?php echo esc_html( sprintf( _n( '%s اپیزود', '%s اپیزود', $count, 'seyedcast' ), number_format_i18n( $count ) ) ); ?></span>
					</a>
				<?php endwhile; ?>
			<?php else : ?>
				<div class="seyedcast-empty-state seyedcast-empty-state--wide">
					<div class="seyedcast-empty-state__art" aria-hidden="true">
						<svg viewBox="0 0 96 96" width="88" height="88" fill="none">
							<rect x="14" y="22" width="68" height="52" rx="14" stroke="currentColor" stroke-width="2.2" opacity=".3"/>
							<circle cx="48" cy="48" r="14" stroke="currentColor" stroke-width="2.2" opacity=".45"/>
							<circle cx="48" cy="48" r="4" fill="currentColor" opacity=".55"/>
							<path d="M30 18h12M54 18h12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" opacity=".4"/>
						</svg>
					</div>
					<h3 class="seyedcast-empty-state__title"><?php esc_html_e( 'هنوز پادکستی منتشر نشده', 'seyedcast' ); ?></h3>
					<p class="seyedcast-empty-state__text"><?php esc_html_e( 'به‌زودی مجموعه‌های صوتی اینجا می‌آیند. برای خبر شدن از انتشار اول می‌توانید ثبت‌نام کنید.', 'seyedcast' ); ?></p>
					<?php
					Seyedcast_Templates::partial(
						'notify-cta',
						array(
							'seyedcast_notify_show_id' => 0,
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<?php Seyedcast_Templates::partial( 'latest-episodes' ); ?>
	<?php Seyedcast_Templates::partial( 'continue-listening' ); ?>

	<?php Seyedcast_Templates::partial( 'suggested-for-you' ); ?>

	<?php
	Seyedcast_Templates::partial(
		'comments',
		array(
			'seyedcast_comment_post_id' => Seyedcast_App::get_comments_board_id(),
			'seyedcast_comments_title'  => __( 'دیدگاه‌های عمومی', 'seyedcast' ),
			'seyedcast_comments_intro'  => __( 'این بخش برای گفت‌وگوی عمومی درباره همه پادکست‌هاست؛ برای نظر درباره یک اپیزود خاص، به صفحه همان اپیزود بروید.', 'seyedcast' ),
		)
	);
	?>
</main>
<?php
Seyedcast_Templates::render_app(
	ob_get_clean(),
	array(
		'show_featured' => true,
		'page_class'    => 'is-archive',
	)
);
