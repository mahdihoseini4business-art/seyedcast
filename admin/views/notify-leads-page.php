<?php
/**
 * Notify leads admin list.
 *
 * @package Seyedcast
 * @var array $leads
 * @var array $shows
 * @var int   $show_id
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_filter = -1;
if ( isset( $_GET['show_id'] ) && '' !== wp_unslash( $_GET['show_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$current_filter = absint( wp_unslash( $_GET['show_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}
$base_url    = admin_url( 'admin.php?page=seyedcast-notify-leads' );
$export_args = array( 'seyedcast_export' => 'csv' );
if ( -1 !== $current_filter ) {
	$export_args['show_id'] = $current_filter;
}
$export_url = wp_nonce_url( add_query_arg( $export_args, $base_url ), 'seyedcast_notify_export' );
?>
<div class="wrap seyedcast-notify-leads" dir="rtl">
	<h1><?php esc_html_e( 'اطلاع‌رسانی — لیست ثبت‌نام‌ها', 'seyedcast' ); ?></h1>

	<?php if ( ! empty( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'ردیف حذف شد.', 'seyedcast' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! Seyedcast_Notify_Leads::is_enabled() ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				echo esc_html__( 'دکمه اطلاع‌رسانی در تنظیمات خاموش است.', 'seyedcast' );
				echo ' ';
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=seyedcast&tab=page' ) ); ?>"><?php esc_html_e( 'فعال‌سازی در تنظیمات', 'seyedcast' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin:16px 0;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
		<input type="hidden" name="page" value="seyedcast-notify-leads" />
		<label for="seyedcast-notify-show-filter">
			<?php esc_html_e( 'فیلتر پادکست:', 'seyedcast' ); ?>
			<select name="show_id" id="seyedcast-notify-show-filter">
				<option value="" <?php selected( $current_filter, -1 ); ?>><?php esc_html_e( 'همه', 'seyedcast' ); ?></option>
				<option value="0" <?php selected( $current_filter, 0 ); ?>><?php esc_html_e( 'صفحه اصلی (همه پادکست‌ها)', 'seyedcast' ); ?></option>
				<?php foreach ( $shows as $show ) : ?>
					<option value="<?php echo esc_attr( (string) $show->ID ); ?>" <?php selected( $current_filter, (int) $show->ID ); ?>>
						<?php echo esc_html( get_the_title( $show ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php submit_button( __( 'اعمال', 'seyedcast' ), 'secondary', '', false ); ?>
		<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'خروجی CSV', 'seyedcast' ); ?></a>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'نام', 'seyedcast' ); ?></th>
				<th><?php esc_html_e( 'شماره', 'seyedcast' ); ?></th>
				<th><?php esc_html_e( 'پادکست', 'seyedcast' ); ?></th>
				<th><?php esc_html_e( 'تاریخ', 'seyedcast' ); ?></th>
				<th><?php esc_html_e( 'عملیات', 'seyedcast' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $leads ) ) : ?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'هنوز ثبت‌نامی نیست.', 'seyedcast' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $leads as $row ) : ?>
					<?php
					$sid   = isset( $row['show_id'] ) ? (int) $row['show_id'] : 0;
					$lid   = isset( $row['id'] ) ? (int) $row['id'] : 0;
					$title = $sid > 0 ? get_the_title( $sid ) : __( 'همه پادکست‌ها', 'seyedcast' );
					if ( $sid > 0 && ! $title ) {
						$title = sprintf( __( 'شو #%d (حذف‌شده)', 'seyedcast' ), $sid );
					}
					$created = isset( $row['created_at'] ) ? $row['created_at'] : '';
					$ts      = $created ? strtotime( $created ) : false;
					$label   = $ts ? wp_date( 'Y/m/d H:i', $ts ) : $created;
					$del_url = wp_nonce_url(
						add_query_arg(
							array(
								'page'    => 'seyedcast-notify-leads',
								'action'  => 'delete',
								'lead_id' => $lid,
								'show_id' => -1 !== $current_filter ? $current_filter : null,
							),
							admin_url( 'admin.php' )
						),
						'seyedcast_notify_delete_' . $lid
					);
					?>
					<tr>
						<td><?php echo esc_html( isset( $row['name'] ) ? $row['name'] : '' ); ?></td>
						<td dir="ltr" style="text-align:right;"><?php echo esc_html( isset( $row['phone'] ) ? $row['phone'] : '' ); ?></td>
						<td><?php echo esc_html( $title ); ?></td>
						<td><?php echo esc_html( $label ); ?></td>
						<td>
							<a class="button-link-delete" href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'حذف شود؟', 'seyedcast' ) ); ?>');">
								<?php esc_html_e( 'حذف', 'seyedcast' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<p class="description" style="margin-top:12px;">
		<?php echo esc_html( sprintf( __( 'تعداد ردیف‌ها: %s', 'seyedcast' ), number_format_i18n( count( $leads ) ) ) ); ?>
	</p>
</div>
