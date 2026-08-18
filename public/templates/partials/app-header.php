<?php
/**
 * Dedicated podcast app header with search and topic filters.
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$archive = get_post_type_archive_link( 'seyedcast_show' );
$topics  = Seyedcast_App::get_topics();
$current = is_tax( 'seyedcast_topic' ) ? get_queried_object() : null;
$settings = Seyedcast_Settings::get();
$title    = ! empty( $settings['archive_title'] ) ? $settings['archive_title'] : __( 'پادکست‌ها', 'seyedcast' );
?>
<header class="seyedcast-app-header" role="banner">
	<div class="seyedcast-app-header__inner">
		<a class="seyedcast-app-header__brand" href="<?php echo esc_url( $archive ? $archive : home_url( '/' ) ); ?>" data-seyedcast-nav>
			<?php
			$logo_id    = ! empty( $settings['header_logo'] ) ? (int) $settings['header_logo'] : 0;
			$logo_url   = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
			$brand_text = ! empty( $settings['header_brand_text'] ) ? $settings['header_brand_text'] : 'MahdiHoseiny.ir';
			if ( $logo_url ) :
				?>
				<img class="seyedcast-app-header__logo-img" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $brand_text ); ?>" />
			<?php else : ?>
				<span class="seyedcast-app-header__logo"><?php echo esc_html( $brand_text ); ?></span>
			<?php endif; ?>
			<span class="seyedcast-app-header__tag"><?php echo esc_html( $title ); ?></span>
		</a>

		<nav class="seyedcast-app-header__nav" aria-label="<?php esc_attr_e( 'منوی پادکست', 'seyedcast' ); ?>">
			<a class="seyedcast-app-header__link<?php echo is_post_type_archive( 'seyedcast_show' ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( $archive ? $archive : '#' ); ?>" data-seyedcast-nav>
				<?php esc_html_e( 'همه پادکست‌ها', 'seyedcast' ); ?>
			</a>
		</nav>

		<div class="seyedcast-app-search" data-seyedcast-search>
			<label class="screen-reader-text" for="seyedcast-search-input"><?php esc_html_e( 'جستجو', 'seyedcast' ); ?></label>
			<input
				type="search"
				id="seyedcast-search-input"
				class="seyedcast-app-search__input"
				placeholder="<?php esc_attr_e( 'جستجوی پادکست یا اپیزود…', 'seyedcast' ); ?>"
				autocomplete="off"
				enterkeyhint="search"
			/>
			<div class="seyedcast-app-search__results" id="seyedcast-search-results" hidden role="listbox"></div>
		</div>

		<button type="button" class="seyedcast-app-header__sidebar-toggle" data-seyedcast-sidebar-toggle aria-expanded="false" aria-controls="seyedcast-sidebar">
			<?php esc_html_e( 'اطلاعیه‌ها', 'seyedcast' ); ?>
		</button>
	</div>

	<?php if ( $topics ) : ?>
		<div class="seyedcast-topic-filters" role="navigation" aria-label="<?php esc_attr_e( 'فیلتر موضوع', 'seyedcast' ); ?>">
			<a
				class="seyedcast-topic-chip<?php echo ! $current ? ' is-active' : ''; ?>"
				href="<?php echo esc_url( $archive ? $archive : '#' ); ?>"
				data-seyedcast-nav
			><?php esc_html_e( 'همه', 'seyedcast' ); ?></a>
			<?php foreach ( $topics as $topic ) : ?>
				<?php
				$link   = get_term_link( $topic );
				$active = $current && (int) $current->term_id === (int) $topic->term_id;
				if ( is_wp_error( $link ) ) {
					continue;
				}
				?>
				<a
					class="seyedcast-topic-chip<?php echo $active ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( $link ); ?>"
					data-seyedcast-nav
				><?php echo esc_html( $topic->name ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</header>
