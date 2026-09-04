<?php
/**
 * Share / publish CTA — native share when available, otherwise copy URL.
 *
 * @package Seyedcast
 * @var string $seyedcast_share_url   Optional absolute URL to share.
 * @var string $seyedcast_share_label Optional button label.
 * @var string $seyedcast_share_title Optional share title.
 * @var string $seyedcast_share_text  Optional share text.
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

$title = ! empty( $seyedcast_share_title )
	? $seyedcast_share_title
	: ( is_singular() ? get_the_title() : wp_get_document_title() );

$text = ! empty( $seyedcast_share_text ) ? $seyedcast_share_text : '';
?>
<button
	type="button"
	class="seyedcast-btn seyedcast-btn--ghost seyedcast-share-btn"
	data-seyedcast-share
	data-share-url="<?php echo esc_url( $url ); ?>"
	data-share-title="<?php echo esc_attr( $title ); ?>"
	data-share-text="<?php echo esc_attr( $text ); ?>"
	aria-label="<?php echo esc_attr( $label ); ?>"
>
	<svg class="seyedcast-share-btn__icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
		<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
		<path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/>
	</svg>
	<span data-role="label"><?php echo esc_html( $label ); ?></span>
</button>
