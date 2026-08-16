<?php
/**
 * Episode meta box view.
 *
 * @package Seyedcast
 * @var WP_Post $post
 * @var int     $show_id
 * @var int     $audio_id
 * @var string  $duration
 * @var string  $number
 * @var string  $audio_url
 * @var string  $audio_title
 * @var WP_Post[] $shows
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="seyedcast-meta-grid">
	<p>
		<label for="seyedcast_show_id"><strong><?php esc_html_e( 'شو / سری', 'seyedcast' ); ?></strong></label><br />
		<select name="seyedcast_show_id" id="seyedcast_show_id" class="widefat">
			<option value="0"><?php esc_html_e( '— انتخاب شو —', 'seyedcast' ); ?></option>
			<?php foreach ( $shows as $show ) : ?>
				<option value="<?php echo esc_attr( $show->ID ); ?>" <?php selected( $show_id, $show->ID ); ?>>
					<?php echo esc_html( $show->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>

	<p>
		<label for="seyedcast_episode_number"><strong><?php esc_html_e( 'شماره اپیزود', 'seyedcast' ); ?></strong></label><br />
		<input type="text" class="widefat" id="seyedcast_episode_number" name="seyedcast_episode_number" value="<?php echo esc_attr( $number ); ?>" placeholder="1" />
	</p>

	<p>
		<label for="seyedcast_duration"><strong><?php esc_html_e( 'مدت زمان', 'seyedcast' ); ?></strong></label><br />
		<input type="text" class="widefat" id="seyedcast_duration" name="seyedcast_duration" value="<?php echo esc_attr( $duration ); ?>" placeholder="45:00" />
	</p>

	<p>
		<label><strong><?php esc_html_e( 'فایل صوتی', 'seyedcast' ); ?></strong></label><br />
		<input type="hidden" id="seyedcast_audio_id" name="seyedcast_audio_id" value="<?php echo esc_attr( $audio_id ); ?>" />
		<span id="seyedcast_audio_preview" class="seyedcast-audio-preview">
			<?php if ( $audio_url ) : ?>
				<a href="<?php echo esc_url( $audio_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $audio_title ? $audio_title : basename( $audio_url ) ); ?></a>
			<?php else : ?>
				<em><?php esc_html_e( 'فایلی انتخاب نشده', 'seyedcast' ); ?></em>
			<?php endif; ?>
		</span><br />
		<button type="button" class="button" id="seyedcast_select_audio"><?php esc_html_e( 'انتخاب از کتابخانه رسانه', 'seyedcast' ); ?></button>
		<button type="button" class="button" id="seyedcast_remove_audio" <?php disabled( ! $audio_id ); ?>><?php esc_html_e( 'حذف', 'seyedcast' ); ?></button>
	</p>
	<p class="description"><?php esc_html_e( 'تصویر شاخص به‌عنوان کاور اپیزود استفاده می‌شود. اگر نباشد، کاور شو نمایش داده می‌شود.', 'seyedcast' ); ?></p>
</div>
