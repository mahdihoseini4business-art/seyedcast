<?php
/**
 * Topic taxonomy archive (app shell).
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$term = get_queried_object();

ob_start();
?>
<main class="seyedcast-app seyedcast-archive seyedcast-topic-archive" dir="rtl">
	<section class="seyedcast-section">
		<div class="seyedcast-section-head">
			<h2><?php echo esc_html( $term && isset( $term->name ) ? $term->name : __( 'موضوع', 'seyedcast' ) ); ?></h2>
			<?php if ( $term && ! empty( $term->description ) ) : ?>
				<span class="seyedcast-section-head__hint"><?php echo esc_html( $term->description ); ?></span>
			<?php else : ?>
				<span class="seyedcast-section-head__hint"><?php esc_html_e( 'شوها و اپیزودهای این موضوع', 'seyedcast' ); ?></span>
			<?php endif; ?>
		</div>

		<div class="seyedcast-shows-grid">
			<?php if ( have_posts() ) : ?>
				<?php
				while ( have_posts() ) :
					the_post();
					$post_type = get_post_type();
					if ( 'seyedcast_show' === $post_type ) {
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
						<?php
					} elseif ( 'seyedcast_episode' === $post_type ) {
						$payload = Seyedcast_Templates::episode_payload( get_post() );
						$json    = esc_attr( wp_json_encode( $payload ) );
						?>
						<article class="seyedcast-show-tile seyedcast-show-tile--episode">
							<a class="seyedcast-show-tile__art" href="<?php echo esc_url( $payload['permalink'] ); ?>" data-seyedcast-nav>
								<img src="<?php echo esc_url( $payload['cover'] ); ?>" alt="" loading="lazy" />
							</a>
							<a class="seyedcast-show-tile__title" href="<?php echo esc_url( $payload['permalink'] ); ?>" data-seyedcast-nav><?php echo esc_html( $payload['title'] ); ?></a>
							<span class="seyedcast-show-tile__meta"><?php echo esc_html( $payload['show'] ); ?></span>
							<?php if ( ! empty( $payload['audio'] ) ) : ?>
								<button type="button" class="seyedcast-play-btn" data-seyedcast-play="<?php echo $json; ?>" aria-label="<?php esc_attr_e( 'پخش', 'seyedcast' ); ?>"><span></span></button>
							<?php endif; ?>
						</article>
						<?php
					}
				endwhile;
				?>
			<?php else : ?>
				<p class="seyedcast-empty"><?php esc_html_e( 'موردی در این موضوع پیدا نشد.', 'seyedcast' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
Seyedcast_Templates::render_app(
	ob_get_clean(),
	array(
		'show_featured' => true,
		'page_class'    => 'is-topic',
	)
);
