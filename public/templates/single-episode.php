<?php
/**
 * Single episode template (app shell).
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

the_post();
$episode = get_post();
$payload = Seyedcast_Templates::episode_payload( $episode );
$show_id = Seyedcast_Meta::get_show_id( $episode->ID );
$show    = $show_id ? get_post( $show_id ) : null;
$json    = esc_attr( wp_json_encode( $payload ) );
$accent  = $show_id ? get_post_meta( $show_id, '_seyedcast_accent_color', true ) : '';
$style   = $accent ? '--sc-show-accent:' . esc_attr( $accent ) . ';' : '';

ob_start();
?>
<main class="seyedcast-app seyedcast-episode" dir="rtl" style="<?php echo esc_attr( $style ); ?>" data-episode-id="<?php echo esc_attr( (string) $payload['id'] ); ?>">
	<section class="seyedcast-hero seyedcast-hero--episode" style="--sc-hero-image:url('<?php echo esc_url( $payload['cover'] ); ?>')">
		<div class="seyedcast-hero__backdrop" aria-hidden="true"></div>
		<div class="seyedcast-hero__content seyedcast-hero__content--row">
			<div class="seyedcast-hero__cover-wrap">
				<img class="seyedcast-hero__cover" src="<?php echo esc_url( $payload['cover'] ); ?>" alt="<?php echo esc_attr( $payload['title'] ); ?>" width="240" height="240" />
			</div>
			<div>
				<?php if ( $show ) : ?>
					<a class="seyedcast-nav" href="<?php echo esc_url( get_permalink( $show ) ); ?>" data-seyedcast-nav>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
						<?php echo esc_html( get_the_title( $show ) ); ?>
					</a>
				<?php endif; ?>
				<p class="seyedcast-brand">Seyedcast</p>
				<h1><?php the_title(); ?></h1>
				<div class="seyedcast-hero__meta">
					<?php if ( $payload['number'] ) : ?>
						<span class="seyedcast-chip"><?php echo esc_html( sprintf( __( 'اپیزود %s', 'seyedcast' ), $payload['number'] ) ); ?></span>
					<?php endif; ?>
					<?php if ( $payload['duration'] ) : ?>
						<span class="seyedcast-chip"><?php echo esc_html( $payload['duration'] ); ?></span>
					<?php endif; ?>
					<span class="seyedcast-chip"><?php echo esc_html( get_the_date() ); ?></span>
				</div>
				<div class="seyedcast-hero__actions">
					<button type="button" class="seyedcast-btn seyedcast-btn--primary seyedcast-btn--lg" data-seyedcast-play="<?php echo $json; ?>">
						<span class="seyedcast-btn__icon" aria-hidden="true"></span>
						<?php esc_html_e( 'پخش اپیزود', 'seyedcast' ); ?>
					</button>
					<?php if ( $show ) : ?>
						<a class="seyedcast-btn seyedcast-btn--ghost" href="<?php echo esc_url( get_permalink( $show ) ); ?>" data-seyedcast-nav><?php esc_html_e( 'همه اپیزودها', 'seyedcast' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>

	<section class="seyedcast-section">
		<div class="seyedcast-section-head">
			<h2><?php esc_html_e( 'درباره این اپیزود', 'seyedcast' ); ?></h2>
		</div>
		<div class="seyedcast-prose">
			<?php the_content(); ?>
		</div>

		<?php
		$topics = get_the_terms( $episode->ID, 'seyedcast_topic' );
		if ( $topics && ! is_wp_error( $topics ) ) :
			?>
			<div class="seyedcast-topics">
				<?php foreach ( $topics as $topic ) : ?>
					<a href="<?php echo esc_url( get_term_link( $topic ) ); ?>" data-seyedcast-nav><?php echo esc_html( $topic->name ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
</main>
<?php
Seyedcast_Templates::render_app(
	ob_get_clean(),
	array(
		'show_featured' => false,
		'page_class'    => 'is-episode',
	)
);
