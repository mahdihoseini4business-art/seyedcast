<?php
/**
 * Share / publish CTA — copies the current page (or given) URL.
 *
 * @package Seyedcast
 * @var string $seyedcast_share_url   Optional absolute URL to copy.
 * @var string $seyedcast_share_label Optional button label.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$url = '';
if ( ! empty( $seyedcast_share_url ) ) {
	$url = esc_url_raw( $seyedcast_share_url );
} elseif ( is_singular() ) {
	$permalink = get_permalink();
	$url       = $permalink ? $permalink : '';
} else {
	$archive = get_post_type_archive_link( 'seyedcast_show' );
	$url     = $archive ? $archive : home_url( '/' );
}

if ( ! $url ) {
	return;
}

$label = ! empty( $seyedcast_share_label )
	? $seyedcast_share_label
	: __( 'انتشار پادکست', 'seyedcast' );
?>
<button
	type="button"
	class="seyedcast-btn seyedcast-btn--ghost seyedcast-share-btn"
	data-seyedcast-share
	data-share-url="<?php echo esc_url( $url ); ?>"
	aria-label="<?php echo esc_attr( $label ); ?>"
>
	<svg class="seyedcast-share-btn__icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
		<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
		<path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/>
	</svg>
	<span data-role="label"><?php echo esc_html( $label ); ?></span>
</button>
