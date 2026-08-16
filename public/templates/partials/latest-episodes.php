<?php
/**
 * Latest episodes horizontal strip.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$episodes = Seyedcast_App::get_latest_episodes( 8 );
if ( ! $episodes ) {
	return;
}
?>
<section class="seyedcast-section seyedcast-latest">
	<div class="seyedcast-section-head">
		<h2><?php esc_html_e( 'جدیدترین اپیزودها', 'seyedcast' ); ?></h2>
		<span class="seyedcast-section-head__hint"><?php esc_html_e( 'تازه‌منتشرشده‌ها', 'seyedcast' ); ?></span>
	</div>
	<div class="seyedcast-latest__track">
		<?php foreach ( $episodes as $episode ) : ?>
			<?php
			$payload = Seyedcast_Templates::episode_payload( $episode );
			$json    = esc_attr( wp_json_encode( $payload ) );
			?>
			<article class="seyedcast-latest-card">
				<a class="seyedcast-latest-card__art" href="<?php echo esc_url( $payload['permalink'] ); ?>" data-seyedcast-nav>
					<img src="<?php echo esc_url( $payload['cover'] ); ?>" alt="" loading="lazy" />
				</a>
				<a class="seyedcast-latest-card__title" href="<?php echo esc_url( $payload['permalink'] ); ?>" data-seyedcast-nav>
					<?php echo esc_html( $payload['title'] ); ?>
				</a>
				<span class="seyedcast-latest-card__show"><?php echo esc_html( $payload['show'] ); ?></span>
				<?php if ( ! empty( $payload['audio'] ) ) : ?>
					<button type="button" class="seyedcast-play-btn seyedcast-latest-card__play" data-seyedcast-play="<?php echo $json; ?>" aria-label="<?php esc_attr_e( 'پخش', 'seyedcast' ); ?>">
						<span></span>
					</button>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
</section>
