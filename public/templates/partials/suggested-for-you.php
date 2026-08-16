<?php
/**
 * Suggested podcasts row (filled by JS from listen history).
 *
 * @package Seyedcast
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = Seyedcast_Settings::get();
if ( empty( $settings['suggestions_enabled'] ) ) {
	return;
}
?>
<section class="seyedcast-section seyedcast-suggest" data-seyedcast-suggest hidden>
	<div class="seyedcast-section-head">
		<h2><?php esc_html_e( 'پیشنهاد برای شما', 'seyedcast' ); ?></h2>
		<span class="seyedcast-section-head__hint"><?php esc_html_e( 'بر اساس ۳ پادکست اخیر شنیده‌شده', 'seyedcast' ); ?></span>
	</div>
	<div class="seyedcast-suggest__track" data-role="track" role="list"></div>
</section>
