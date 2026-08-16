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
	<?php Seyedcast_Templates::partial( 'continue-listening' ); ?>
	<?php Seyedcast_Templates::partial( 'latest-episodes' ); ?>

	<section class="seyedcast-section">
		<div class="seyedcast-section-head">
			<h2><?php esc_html_e( 'همه شوها', 'seyedcast' ); ?></h2>
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
							<img src="<?php echo esc_url( $cover ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy" />
							<span class="seyedcast-show-tile__play" aria-hidden="true"><span></span></span>
						</span>
						<span class="seyedcast-show-tile__title"><?php the_title(); ?></span>
						<span class="seyedcast-show-tile__meta"><?php echo esc_html( sprintf( _n( '%s اپیزود', '%s اپیزود', $count, 'seyedcast' ), number_format_i18n( $count ) ) ); ?></span>
					</a>
				<?php endwhile; ?>
			<?php else : ?>
				<p class="seyedcast-empty"><?php esc_html_e( 'هنوز شویی منتشر نشده است.', 'seyedcast' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
Seyedcast_Templates::render_app(
	ob_get_clean(),
	array(
		'show_featured' => true,
		'page_class'    => 'is-archive',
	)
);
