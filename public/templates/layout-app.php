<?php
/**
 * Standalone podcast app shell (no theme header/footer).
 *
 * @package Seyedcast
 * @var string $main_html
 * @var array  $args
 * @var array  $settings
 * @var string $preset
 * @var array  $colors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
	<meta name="theme-color" content="<?php echo esc_attr( $colors['background'] ); ?>" />
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'seyedcast-app-shell seyedcast-preset-' . $preset ); ?>>
	<div class="seyedcast-shell" id="seyedcast-shell" dir="rtl">
		<?php Seyedcast_Templates::partial( 'app-header' ); ?>

		<div class="seyedcast-shell__body">
			<div class="seyedcast-shell__primary">
				<div id="seyedcast-app-stage" class="seyedcast-app-stage" data-title="<?php echo esc_attr( wp_get_document_title() ); ?>">
					<?php if ( ! empty( $args['show_featured'] ) ) : ?>
						<?php Seyedcast_Templates::partial( 'featured-banner' ); ?>
					<?php endif; ?>
					<div id="seyedcast-app-main" class="seyedcast-app-main <?php echo esc_attr( $args['page_class'] ); ?>">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped templates.
						echo $main_html;
						?>
					</div>
				</div>
			</div>
			<?php Seyedcast_Templates::partial( 'app-sidebar' ); ?>
		</div>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
