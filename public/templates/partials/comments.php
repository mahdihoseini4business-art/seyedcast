<?php
/**
 * Comments section for archive / single surfaces.
 *
 * @package Seyedcast
 * @var int|null $seyedcast_comment_post_id Optional post ID override (archive board).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = Seyedcast_Settings::get();
if ( empty( $settings['comments_enabled'] ) ) {
	return;
}

$post_id = 0;
if ( ! empty( $seyedcast_comment_post_id ) ) {
	$post_id = (int) $seyedcast_comment_post_id;
} elseif ( is_singular( array( 'seyedcast_show', 'seyedcast_episode' ) ) ) {
	$post_id = get_queried_object_id();
}

if ( $post_id < 1 ) {
	return;
}

$post = get_post( $post_id );
if ( ! $post || 'trash' === $post->post_status ) {
	return;
}

$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
setup_postdata( $post );

$comments = get_comments(
	array(
		'post_id' => $post_id,
		'status'  => 'approve',
		'orderby' => 'comment_date_gmt',
		'order'   => 'ASC',
	)
);
$count = count( $comments );
?>
<section class="seyedcast-section seyedcast-comments" id="seyedcast-comments" data-seyedcast-comments>
	<div class="seyedcast-section-head">
		<h2><?php esc_html_e( 'نظرات', 'seyedcast' ); ?></h2>
		<span class="seyedcast-section-head__hint">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: comment count */
					_n( '%s نظر', '%s نظر', $count, 'seyedcast' ),
					number_format_i18n( $count )
				)
			);
			?>
		</span>
	</div>

	<?php if ( $comments ) : ?>
		<ol class="seyedcast-comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 44,
					'callback'    => array( 'Seyedcast_App', 'render_comment' ),
				),
				$comments
			);
			?>
		</ol>
	<?php else : ?>
		<p class="seyedcast-comments__empty"><?php esc_html_e( 'هنوز نظری ثبت نشده؛ اولین نفر باشید.', 'seyedcast' ); ?></p>
	<?php endif; ?>

	<?php if ( comments_open( $post_id ) ) : ?>
		<div class="seyedcast-comment-form">
			<?php
			comment_form(
				array(
					'title_reply'          => __( 'نظر خود را بنویسید', 'seyedcast' ),
					'title_reply_to'       => __( 'پاسخ به %s', 'seyedcast' ),
					'cancel_reply_link'    => __( 'لغو پاسخ', 'seyedcast' ),
					'label_submit'         => __( 'ارسال نظر', 'seyedcast' ),
					'comment_notes_before' => '',
					'comment_notes_after'  => '',
					'class_form'           => 'seyedcast-comment-form__fields',
					'class_submit'         => 'seyedcast-btn seyedcast-btn--primary',
					'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>',
					'submit_field'         => '<p class="form-submit">%1$s %2$s</p>',
					'logged_in_as'         => '',
					'must_log_in'          => '<p class="seyedcast-comments__empty">' . esc_html__( 'برای ارسال نظر وارد شوید.', 'seyedcast' ) . '</p>',
				),
				$post_id
			);
			?>
		</div>
	<?php else : ?>
		<p class="seyedcast-comments__empty"><?php esc_html_e( 'ارسال نظر برای این بخش بسته است.', 'seyedcast' ); ?></p>
	<?php endif; ?>
</section>
<?php
wp_reset_postdata();
