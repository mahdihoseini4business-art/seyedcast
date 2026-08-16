<?php
/**
 * Featured / most-viewed show banner.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show = Seyedcast_App::get_featured_show();
if ( ! $show ) {
	return;
}

$settings   = Seyedcast_Settings::get();
$cta        = ! empty( $settings['featured_cta'] ) ? $settings['featured_cta'] : __( 'گوش دهید', 'seyedcast' );
$cover      = Seyedcast_Templates::cover_url( $show->ID );
$episodes   = Seyedcast_Meta::get_show_episodes( $show->ID );
$first      = ! empty( $episodes ) ? Seyedcast_Templates::episode_payload( $episodes[0] ) : null;
$first_json = $first ? esc_attr( wp_json_encode( $first ) ) : '';
$views      = (int) get_post_meta( $show->ID, Seyedcast_App::VIEW_META, true );
$excerpt    = has_excerpt( $show ) ? get_the_excerpt( $show ) : wp_trim_words( wp_strip_all_tags( $show->post_content ), 28 );
$mode       = isset( $settings['featured_mode'] ) ? $settings['featured_mode'] : 'auto';
$badge      = ( 'manual' === $mode ) ? __( 'انتخاب سردبیر', 'seyedcast' ) : __( 'پربازدیدترین', 'seyedcast' );
?>
<section class="seyedcast-featured" aria-label="<?php echo esc_attr( $badge ); ?>">
	<div class="seyedcast-featured__media" style="--sc-featured-image:url('<?php echo esc_url( $cover ); ?>')">
		<img class="seyedcast-featured__cover" src="<?php echo esc_url( $cover ); ?>" alt="<?php echo esc_attr( get_the_title( $show ) ); ?>" width="280" height="280" />
	</div>
	<div class="seyedcast-featured__body">
		<span class="seyedcast-featured__badge"><?php echo esc_html( $badge ); ?></span>
		<h2 class="seyedcast-featured__title">
			<a href="<?php echo esc_url( get_permalink( $show ) ); ?>" data-seyedcast-nav><?php echo esc_html( get_the_title( $show ) ); ?></a>
		</h2>
		<?php if ( $excerpt ) : ?>
			<p class="seyedcast-featured__sub"><?php echo esc_html( $excerpt ); ?></p>
		<?php endif; ?>
		<div class="seyedcast-featured__meta">
			<span class="seyedcast-chip"><?php echo esc_html( sprintf( _n( '%s اپیزود', '%s اپیزود', count( $episodes ), 'seyedcast' ), number_format_i18n( count( $episodes ) ) ) ); ?></span>
			<?php if ( $views > 0 ) : ?>
				<span class="seyedcast-chip"><?php echo esc_html( sprintf( __( '%s بازدید', 'seyedcast' ), number_format_i18n( $views ) ) ); ?></span>
			<?php endif; ?>
		</div>
		<div class="seyedcast-featured__actions">
			<?php if ( $first && ! empty( $first['audio'] ) ) : ?>
				<button type="button" class="seyedcast-btn seyedcast-btn--primary seyedcast-btn--lg" data-seyedcast-play="<?php echo $first_json; ?>">
					<span class="seyedcast-btn__icon" aria-hidden="true"></span>
					<?php echo esc_html( $cta ); ?>
				</button>
			<?php endif; ?>
			<a class="seyedcast-btn seyedcast-btn--ghost" href="<?php echo esc_url( get_permalink( $show ) ); ?>" data-seyedcast-nav>
				<?php esc_html_e( 'مشاهده شو', 'seyedcast' ); ?>
			</a>
		</div>
	</div>
</section>
