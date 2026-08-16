<?php
/**
 * Continue listening card (filled by JS from player localStorage).
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="seyedcast-continue" id="seyedcast-continue" hidden data-seyedcast-continue>
	<div class="seyedcast-continue__inner">
		<img class="seyedcast-continue__cover" data-role="cover" src="" alt="" width="64" height="64" />
		<div class="seyedcast-continue__body">
			<span class="seyedcast-continue__label"><?php esc_html_e( 'ادامه گوش دادن', 'seyedcast' ); ?></span>
			<strong class="seyedcast-continue__title" data-role="title"></strong>
			<span class="seyedcast-continue__meta" data-role="meta"></span>
		</div>
		<button type="button" class="seyedcast-btn seyedcast-btn--primary" data-role="resume">
			<?php esc_html_e( 'ادامه', 'seyedcast' ); ?>
		</button>
	</div>
</section>
