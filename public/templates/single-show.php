<?php
/**
 * Single show template (app shell).
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

the_post();
$show_id    = get_the_ID();
$cover      = Seyedcast_Templates::cover_url( $show_id );
$accent     = get_post_meta( $show_id, '_seyedcast_accent_color', true );
$episodes   = Seyedcast_Meta::get_show_episodes( $show_id );
$style      = $accent ? '--sc-show-accent:' . esc_attr( $accent ) . ';' : '';
$archive    = get_post_type_archive_link( 'seyedcast_show' );
$first      = ! empty( $episodes ) ? Seyedcast_Templates::episode_payload( $episodes[0] ) : null;
$first_json = $first ? esc_attr( wp_json_encode( $first ) ) : '';

ob_start();
?>
<main class="seyedcast-app seyedcast-show" dir="rtl" style="<?php echo esc_attr( $style ); ?>">
	<section class="seyedcast-hero seyedcast-hero--show" style="--sc-hero-image:url('<?php echo esc_url( $cover ); ?>')">
		<div class="seyedcast-hero__backdrop" aria-hidden="true"></div>
		<div class="seyedcast-hero__content seyedcast-hero__content--row">
			<div class="seyedcast-hero__cover-wrap">
				<img class="seyedcast-hero__cover" src="<?php echo esc_url( $cover ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" width="240" height="240" />
			</div>
			<div>
				<?php if ( $archive ) : ?>
					<a class="seyedcast-nav" href="<?php echo esc_url( $archive ); ?>" data-seyedcast-nav>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
						<?php esc_html_e( 'همه پادکست‌ها', 'seyedcast' ); ?>
					</a>
				<?php endif; ?>
				<p class="seyedcast-brand">Seyedcast</p>
				<h1><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p class="seyedcast-hero__sub"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
				<div class="seyedcast-hero__meta">
					<span class="seyedcast-chip"><?php echo esc_html( sprintf( _n( '%s اپیزود', '%s اپیزود', count( $episodes ), 'seyedcast' ), number_format_i18n( count( $episodes ) ) ) ); ?></span>
					<?php
					$view_count = Seyedcast_Stats::get_view_count( $show_id );
					if ( $view_count > 0 ) :
						?>
						<span class="seyedcast-chip"><?php echo esc_html( sprintf( '%s %s', number_format_i18n( $view_count ), Seyedcast_Stats::view_label() ) ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $first && ! empty( $first['audio'] ) ) : ?>
					<div class="seyedcast-hero__actions">
						<button type="button" class="seyedcast-btn seyedcast-btn--primary seyedcast-btn--lg" data-seyedcast-play="<?php echo $first_json; ?>">
							<span class="seyedcast-btn__icon" aria-hidden="true"></span>
							<?php esc_html_e( 'شروع پخش', 'seyedcast' ); ?>
						</button>
						<?php if ( ! empty( $first['permalink'] ) ) : ?>
							<a class="seyedcast-btn seyedcast-btn--ghost" href="<?php echo esc_url( $first['permalink'] ); ?>" data-seyedcast-nav><?php esc_html_e( 'آخرین اپیزود', 'seyedcast' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="seyedcast-section">
		<?php if ( get_the_content() ) : ?>
			<div class="seyedcast-prose">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>

		<div class="seyedcast-section-head">
			<h2><?php esc_html_e( 'اپیزودها', 'seyedcast' ); ?></h2>
			<span class="seyedcast-section-head__hint"><?php echo esc_html( sprintf( _n( '%s مورد', '%s مورد', count( $episodes ), 'seyedcast' ), number_format_i18n( count( $episodes ) ) ) ); ?></span>
		</div>

		<?php if ( $episodes ) : ?>
			<ul class="seyedcast-episode-list">
				<?php
				$i = 0;
				foreach ( $episodes as $episode ) :
					$i++;
					$payload = Seyedcast_Templates::episode_payload( $episode );
					$json    = esc_attr( wp_json_encode( $payload ) );
					?>
					<li class="seyedcast-episode-row" data-episode-id="<?php echo esc_attr( (string) $payload['id'] ); ?>">
						<span class="seyedcast-episode-row__index"><?php echo esc_html( (string) $i ); ?></span>
						<a class="seyedcast-episode-row__cover" href="<?php echo esc_url( $payload['permalink'] ); ?>" data-seyedcast-nav>
							<img src="<?php echo esc_url( $payload['cover'] ); ?>" alt="" loading="lazy" />
						</a>
						<div class="seyedcast-episode-row__body">
							<a href="<?php echo esc_url( $payload['permalink'] ); ?>" data-seyedcast-nav><strong><?php echo esc_html( $payload['title'] ); ?></strong></a>
							<div class="seyedcast-episode-row__meta">
								<?php if ( $payload['number'] ) : ?>
									<span><?php echo esc_html( sprintf( __( 'اپیزود %s', 'seyedcast' ), $payload['number'] ) ); ?></span>
								<?php endif; ?>
								<?php if ( $payload['duration'] ) : ?>
									<span><?php echo esc_html( $payload['duration'] ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<button type="button" class="seyedcast-play-btn" data-seyedcast-play="<?php echo $json; ?>" aria-label="<?php esc_attr_e( 'پخش', 'seyedcast' ); ?>">
							<span></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="seyedcast-empty"><?php esc_html_e( 'هنوز اپیزودی برای این پادکست منتشر نشده.', 'seyedcast' ); ?></p>
		<?php endif; ?>
	</section>

	<?php Seyedcast_Templates::partial( 'comments' ); ?>
</main>
<?php
Seyedcast_Templates::render_app(
	ob_get_clean(),
	array(
		'show_featured' => false,
		'page_class'    => 'is-show',
	)
);
