<?php
/**
 * Stats admin page.
 *
 * @package Seyedcast
 * @var WP_Post[] $shows
 * @var array     $summary
 * @var int       $listen_show_id
 * @var array     $listen_report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$default_mode = Seyedcast_Stats::view_mode();
?>
<div class="wrap seyedcast-settings seyedcast-stats" dir="rtl">
	<h1><?php esc_html_e( 'آمار بازدید', 'seyedcast' ); ?></h1>

	<div class="seyedcast-stats__cards">
		<div class="seyedcast-stats-card">
			<span class="seyedcast-stats-card__label"><?php esc_html_e( 'بازدید یکتای پادکست‌ها', 'seyedcast' ); ?></span>
			<strong class="seyedcast-stats-card__value"><?php echo esc_html( number_format_i18n( (int) $summary['unique'] ) ); ?></strong>
		</div>
		<div class="seyedcast-stats-card">
			<span class="seyedcast-stats-card__label"><?php esc_html_e( 'کل بازدید پادکست‌ها', 'seyedcast' ); ?></span>
			<strong class="seyedcast-stats-card__value"><?php echo esc_html( number_format_i18n( (int) $summary['total'] ) ); ?></strong>
		</div>
		<div class="seyedcast-stats-card">
			<span class="seyedcast-stats-card__label"><?php esc_html_e( 'بازدید یکتای اپیزودها', 'seyedcast' ); ?></span>
			<strong class="seyedcast-stats-card__value"><?php echo esc_html( number_format_i18n( (int) $summary['ep_unique'] ) ); ?></strong>
		</div>
		<div class="seyedcast-stats-card">
			<span class="seyedcast-stats-card__label"><?php esc_html_e( 'کل بازدید اپیزودها', 'seyedcast' ); ?></span>
			<strong class="seyedcast-stats-card__value"><?php echo esc_html( number_format_i18n( (int) $summary['ep_total'] ) ); ?></strong>
		</div>
	</div>

	<div class="seyedcast-admin-card seyedcast-stats__filters">
		<strong><?php esc_html_e( 'فیلتر نمودار', 'seyedcast' ); ?></strong>
		<div class="seyedcast-stats__filter-row">
			<label for="seyedcast_stats_scope">
				<?php esc_html_e( 'نوع محتوا', 'seyedcast' ); ?>
				<select id="seyedcast_stats_scope">
					<option value="all_shows"><?php esc_html_e( 'همه پادکست‌ها', 'seyedcast' ); ?></option>
					<option value="show"><?php esc_html_e( 'یک پادکست', 'seyedcast' ); ?></option>
					<option value="episode"><?php esc_html_e( 'یک اپیزود', 'seyedcast' ); ?></option>
				</select>
			</label>

			<label for="seyedcast_stats_show" id="seyedcast_stats_show_wrap" hidden>
				<?php esc_html_e( 'پادکست', 'seyedcast' ); ?>
				<select id="seyedcast_stats_show">
					<option value="0"><?php esc_html_e( '— انتخاب پادکست —', 'seyedcast' ); ?></option>
					<?php foreach ( $shows as $show ) : ?>
						<option value="<?php echo esc_attr( (string) $show->ID ); ?>">
							<?php echo esc_html( get_the_title( $show ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<label for="seyedcast_stats_episode" id="seyedcast_stats_episode_wrap" hidden>
				<?php esc_html_e( 'اپیزود', 'seyedcast' ); ?>
				<select id="seyedcast_stats_episode" disabled>
					<option value="0"><?php esc_html_e( '— ابتدا پادکست را انتخاب کنید —', 'seyedcast' ); ?></option>
				</select>
			</label>

			<label for="seyedcast_stats_days">
				<?php esc_html_e( 'بازه زمانی', 'seyedcast' ); ?>
				<select id="seyedcast_stats_days">
					<option value="7"><?php esc_html_e( '۷ روز گذشته', 'seyedcast' ); ?></option>
					<option value="30" selected><?php esc_html_e( '۳۰ روز گذشته', 'seyedcast' ); ?></option>
					<option value="90"><?php esc_html_e( '۹۰ روز گذشته', 'seyedcast' ); ?></option>
				</select>
			</label>

			<label for="seyedcast_stats_mode">
				<?php esc_html_e( 'نوع بازدید', 'seyedcast' ); ?>
				<select id="seyedcast_stats_mode">
					<option value="unique" <?php selected( $default_mode, 'unique' ); ?>><?php esc_html_e( 'بازدید یکتا', 'seyedcast' ); ?></option>
					<option value="total" <?php selected( $default_mode, 'total' ); ?>><?php esc_html_e( 'کل بازدید', 'seyedcast' ); ?></option>
				</select>
			</label>
		</div>
	</div>

	<div class="seyedcast-admin-card seyedcast-stats__chart-wrap">
		<div class="seyedcast-stats__chart-head">
			<strong id="seyedcast_stats_chart_title"><?php esc_html_e( 'نمودار بازدید روزانه', 'seyedcast' ); ?></strong>
			<span id="seyedcast_stats_period_total" class="seyedcast-stats__period-total"></span>
		</div>
		<p id="seyedcast_stats_status" class="seyedcast-stats__status" hidden></p>
		<div class="seyedcast-stats__canvas">
			<canvas id="seyedcast_stats_chart" height="120"></canvas>
		</div>
	</div>

	<div class="seyedcast-admin-card seyedcast-stats__listen">
		<div class="seyedcast-stats__listen-head">
			<strong><?php esc_html_e( 'میانگین درصد گوش داده‌شده', 'seyedcast' ); ?></strong>
			<form method="get" action="" class="seyedcast-stats__listen-filter">
				<input type="hidden" name="page" value="seyedcast-stats" />
				<label for="seyedcast_listen_show">
					<?php esc_html_e( 'فیلتر پادکست', 'seyedcast' ); ?>
					<select id="seyedcast_listen_show" name="listen_show" onchange="this.form.submit()">
						<option value="0" <?php selected( $listen_show_id, 0 ); ?>><?php esc_html_e( 'همه پادکست‌ها', 'seyedcast' ); ?></option>
						<?php foreach ( $shows as $show ) : ?>
							<option value="<?php echo esc_attr( (string) $show->ID ); ?>" <?php selected( $listen_show_id, $show->ID ); ?>>
								<?php echo esc_html( get_the_title( $show ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</form>
		</div>

		<?php if ( empty( $listen_report ) ) : ?>
			<p class="description"><?php esc_html_e( 'هنوز داده‌ای ثبت نشده. پس از گوش دادن کاربران به اپیزودها، میانگین درصد اینجا نمایش داده می‌شود.', 'seyedcast' ); ?></p>
		<?php else : ?>
			<div class="seyedcast-stats__listen-table-wrap">
				<table class="widefat striped seyedcast-stats__listen-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'اپیزود', 'seyedcast' ); ?></th>
							<th><?php esc_html_e( 'پادکست', 'seyedcast' ); ?></th>
							<th><?php esc_html_e( 'شنونده', 'seyedcast' ); ?></th>
							<th><?php esc_html_e( 'میانگین', 'seyedcast' ); ?></th>
							<th><?php esc_html_e( 'پیشرفت', 'seyedcast' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $listen_report as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['episode_title'] ); ?></td>
								<td><?php echo esc_html( $row['show_title'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $row['count'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $row['avg'] ) ); ?>%</td>
								<td>
									<div class="seyedcast-stats__listen-bar" aria-hidden="true">
										<span style="width: <?php echo esc_attr( (string) min( 100, max( 0, (int) $row['avg'] ) ) ); ?>%"></span>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<p class="description">
		<?php esc_html_e( 'برای تنظیم نمایش بازدید در صفحه پادکست، به تب «صفحه پادکست» در تنظیمات Seyedcast بروید.', 'seyedcast' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=seyedcast&tab=page' ) ); ?>"><?php esc_html_e( 'رفتن به تنظیمات', 'seyedcast' ); ?></a>
	</p>
</div>
