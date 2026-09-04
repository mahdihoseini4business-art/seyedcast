<?php
/**
 * Notify-me CTA button + modal form.
 *
 * @package Seyedcast
 * @var int $seyedcast_notify_show_id Optional show ID (0 = archive/site-wide).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Seyedcast_Notify_Leads', false ) || ! Seyedcast_Notify_Leads::is_enabled() ) {
	return;
}

$show_id = isset( $seyedcast_notify_show_id ) ? absint( $seyedcast_notify_show_id ) : 0;
$label   = Seyedcast_Notify_Leads::button_text();
$uid     = 'seyedcast-notify-' . ( $show_id > 0 ? (string) $show_id : 'all' ) . '-' . wp_unique_id();
?>
<div class="seyedcast-notify" data-seyedcast-notify data-show-id="<?php echo esc_attr( (string) $show_id ); ?>">
	<button type="button" class="seyedcast-btn seyedcast-btn--outline seyedcast-notify__trigger" data-seyedcast-notify-open aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $uid ); ?>">
		<?php echo esc_html( $label ); ?>
	</button>

	<div id="<?php echo esc_attr( $uid ); ?>" class="seyedcast-notify-modal" data-seyedcast-notify-modal hidden role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $uid ); ?>-title">
		<button type="button" class="seyedcast-notify-modal__backdrop" data-seyedcast-notify-close tabindex="-1" aria-label="<?php esc_attr_e( 'بستن', 'seyedcast' ); ?>"></button>
		<div class="seyedcast-notify-modal__card">
			<button type="button" class="seyedcast-notify-modal__close" data-seyedcast-notify-close aria-label="<?php esc_attr_e( 'بستن', 'seyedcast' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
			<h3 id="<?php echo esc_attr( $uid ); ?>-title" class="seyedcast-notify-modal__title"><?php echo esc_html( $label ); ?></h3>
			<p class="seyedcast-notify-modal__sub"><?php esc_html_e( 'اسم و شماره‌ت رو بذار تا وقتی اپیزود جدید اومد خبرت کنیم.', 'seyedcast' ); ?></p>
			<form class="seyedcast-notify-modal__form seyedcast-comment-form" data-seyedcast-notify-form novalidate>
				<input type="hidden" name="show_id" value="<?php echo esc_attr( (string) $show_id ); ?>" />
				<p>
					<label for="<?php echo esc_attr( $uid ); ?>-name"><?php esc_html_e( 'نام', 'seyedcast' ); ?> <span class="required">*</span></label>
					<input type="text" id="<?php echo esc_attr( $uid ); ?>-name" name="name" required maxlength="100" autocomplete="name" />
				</p>
				<p>
					<label for="<?php echo esc_attr( $uid ); ?>-phone"><?php esc_html_e( 'شماره موبایل', 'seyedcast' ); ?> <span class="required">*</span></label>
					<input type="tel" id="<?php echo esc_attr( $uid ); ?>-phone" name="phone" required maxlength="20" inputmode="tel" autocomplete="tel" dir="ltr" placeholder="09121234567" />
				</p>
				<p class="seyedcast-notify-modal__msg" data-seyedcast-notify-msg hidden role="status" aria-live="polite"></p>
				<p class="form-submit">
					<button type="submit" class="seyedcast-btn seyedcast-btn--primary"><?php esc_html_e( 'ثبت', 'seyedcast' ); ?></button>
					<button type="button" class="seyedcast-btn seyedcast-btn--ghost" data-seyedcast-notify-close><?php esc_html_e( 'انصراف', 'seyedcast' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>
