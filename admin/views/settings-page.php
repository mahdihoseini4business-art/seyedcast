<?php
/**
 * Settings page.
 *
 * @package Seyedcast
 * @var array $settings
 * @var array $presets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $tab, array( 'general', 'design', 'page', 'pwa' ), true ) ) {
	$tab = 'general';
}

$icon_192_url = ! empty( $settings['pwa_icon_192'] ) ? wp_get_attachment_image_url( (int) $settings['pwa_icon_192'], 'thumbnail' ) : '';
$icon_512_url = ! empty( $settings['pwa_icon_512'] ) ? wp_get_attachment_image_url( (int) $settings['pwa_icon_512'], 'thumbnail' ) : '';

$shows = get_posts(
	array(
		'post_type'      => 'seyedcast_show',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'post_status'    => 'publish',
	)
);

$episodes = get_posts(
	array(
		'post_type'      => 'seyedcast_episode',
		'posts_per_page' => 100,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => array( 'publish', 'future', 'draft' ),
	)
);

$banners = isset( $settings['sidebar_banners'] ) && is_array( $settings['sidebar_banners'] ) ? $settings['sidebar_banners'] : array();
while ( count( $banners ) < 3 ) {
	$banners[] = array(
		'image_id' => 0,
		'url'      => '',
		'alt'      => '',
	);
}

$events = isset( $settings['upcoming_events'] ) && is_array( $settings['upcoming_events'] ) ? $settings['upcoming_events'] : array();
while ( count( $events ) < 3 ) {
	$events[] = array(
		'title'      => '',
		'episode_id' => 0,
		'starts_at'  => '',
	);
}
?>
<div class="wrap seyedcast-settings" dir="rtl">
	<h1><?php esc_html_e( 'تنظیمات Seyedcast', 'seyedcast' ); ?></h1>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=seyedcast&tab=general' ) ); ?>" class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'عمومی', 'seyedcast' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=seyedcast&tab=design' ) ); ?>" class="nav-tab <?php echo 'design' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'طراحی', 'seyedcast' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=seyedcast&tab=page' ) ); ?>" class="nav-tab <?php echo 'page' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'صفحه پادکست', 'seyedcast' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=seyedcast&tab=pwa' ) ); ?>" class="nav-tab <?php echo 'pwa' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'PWA / هوم‌اسکرین', 'seyedcast' ); ?></a>
	</nav>

	<form method="post" action="options.php">
		<?php settings_fields( 'seyedcast_settings_group' ); ?>
		<input type="hidden" name="seyedcast_settings[_tab]" value="<?php echo esc_attr( $tab ); ?>" />

		<?php if ( 'general' === $tab ) : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="seyedcast_base_slug"><?php esc_html_e( 'اسلاگ پایه URL', 'seyedcast' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="seyedcast_base_slug" name="seyedcast_settings[base_slug]" value="<?php echo esc_attr( $settings['base_slug'] ); ?>" />
						<p class="description"><?php esc_html_e( 'مثال: podcasts → /podcasts/نام-شو/نام-اپیزود/', 'seyedcast' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="seyedcast_archive_title"><?php esc_html_e( 'عنوان صفحه آرشیو', 'seyedcast' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="seyedcast_archive_title" name="seyedcast_settings[archive_title]" value="<?php echo esc_attr( $settings['archive_title'] ); ?>" />
					</td>
				</tr>
			</table>
		<?php endif; ?>

		<?php if ( 'design' === $tab ) : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'قالب طراحی', 'seyedcast' ); ?></th>
					<td>
						<div class="seyedcast-preset-grid">
							<?php foreach ( $presets as $key => $preset ) : ?>
								<label class="seyedcast-preset-card">
									<input type="radio" name="seyedcast_settings[design_preset]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $settings['design_preset'], $key ); ?> />
									<span class="seyedcast-preset-swatch" style="--p:<?php echo esc_attr( $preset['primary'] ); ?>;--bg:<?php echo esc_attr( $preset['background'] ); ?>;--sf:<?php echo esc_attr( $preset['surface'] ); ?>;--tx:<?php echo esc_attr( $preset['text'] ); ?>;"></span>
									<strong><?php echo esc_html( $preset['label'] ); ?></strong>
								</label>
							<?php endforeach; ?>
						</div>
					</td>
				</tr>
				<?php
				$labels = array(
					'primary'    => __( 'رنگ اصلی', 'seyedcast' ),
					'background' => __( 'پس‌زمینه', 'seyedcast' ),
					'surface'    => __( 'سطح', 'seyedcast' ),
					'text'       => __( 'متن', 'seyedcast' ),
					'accent'     => __( 'اکسنت', 'seyedcast' ),
				);
				foreach ( $labels as $key => $label ) :
					?>
					<tr>
						<th scope="row"><label for="seyedcast_color_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<input type="text" class="seyedcast-color-field" id="seyedcast_color_<?php echo esc_attr( $key ); ?>" name="seyedcast_settings[colors][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $settings['colors'][ $key ] ); ?>" />
							<p class="description"><?php esc_html_e( 'خالی بگذارید تا از preset استفاده شود.', 'seyedcast' ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		<?php endif; ?>

		<?php if ( 'page' === $tab ) : ?>
			<h2><?php esc_html_e( 'بنر ویژه بالای صفحه', 'seyedcast' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'حالت بنر', 'seyedcast' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:6px;">
							<input type="radio" name="seyedcast_settings[featured_mode]" value="auto" <?php checked( $settings['featured_mode'], 'auto' ); ?> />
							<?php esc_html_e( 'خودکار — پربازدیدترین شو', 'seyedcast' ); ?>
						</label>
						<label style="display:block;">
							<input type="radio" name="seyedcast_settings[featured_mode]" value="manual" <?php checked( $settings['featured_mode'], 'manual' ); ?> />
							<?php esc_html_e( 'دستی — انتخاب از لیست', 'seyedcast' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="seyedcast_featured_show"><?php esc_html_e( 'شو دستی', 'seyedcast' ); ?></label></th>
					<td>
						<select id="seyedcast_featured_show" name="seyedcast_settings[featured_show_id]">
							<option value="0"><?php esc_html_e( '— انتخاب کنید —', 'seyedcast' ); ?></option>
							<?php foreach ( $shows as $show ) : ?>
								<option value="<?php echo esc_attr( (string) $show->ID ); ?>" <?php selected( (int) $settings['featured_show_id'], (int) $show->ID ); ?>>
									<?php echo esc_html( get_the_title( $show ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="seyedcast_featured_cta"><?php esc_html_e( 'متن دکمه CTA', 'seyedcast' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="seyedcast_featured_cta" name="seyedcast_settings[featured_cta]" value="<?php echo esc_attr( $settings['featured_cta'] ); ?>" />
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'بنرهای سایدبار (حداکثر ۳)', 'seyedcast' ); ?></h2>
			<?php foreach ( $banners as $i => $banner ) : ?>
				<?php
				$preview = ! empty( $banner['image_id'] ) ? wp_get_attachment_image_url( (int) $banner['image_id'], 'thumbnail' ) : '';
				$field   = 'seyedcast_banner_' . $i;
				?>
				<div class="seyedcast-admin-card">
					<strong><?php echo esc_html( sprintf( __( 'بنر %d', 'seyedcast' ), $i + 1 ) ); ?></strong>
					<input type="hidden" id="<?php echo esc_attr( $field ); ?>" name="seyedcast_settings[sidebar_banners][<?php echo esc_attr( (string) $i ); ?>][image_id]" value="<?php echo esc_attr( (string) (int) $banner['image_id'] ); ?>" />
					<div id="<?php echo esc_attr( $field ); ?>_preview" class="seyedcast-icon-preview">
						<?php if ( $preview ) : ?>
							<img src="<?php echo esc_url( $preview ); ?>" alt="" />
						<?php endif; ?>
					</div>
					<p>
						<button type="button" class="button seyedcast-select-icon" data-target="<?php echo esc_attr( $field ); ?>"><?php esc_html_e( 'انتخاب تصویر', 'seyedcast' ); ?></button>
						<button type="button" class="button seyedcast-clear-icon" data-target="<?php echo esc_attr( $field ); ?>"><?php esc_html_e( 'حذف', 'seyedcast' ); ?></button>
					</p>
					<p>
						<label><?php esc_html_e( 'لینک', 'seyedcast' ); ?></label><br />
						<input type="url" class="regular-text" name="seyedcast_settings[sidebar_banners][<?php echo esc_attr( (string) $i ); ?>][url]" value="<?php echo esc_attr( $banner['url'] ); ?>" />
					</p>
					<p>
						<label><?php esc_html_e( 'متن جایگزین', 'seyedcast' ); ?></label><br />
						<input type="text" class="regular-text" name="seyedcast_settings[sidebar_banners][<?php echo esc_attr( (string) $i ); ?>][alt]" value="<?php echo esc_attr( $banner['alt'] ); ?>" />
					</p>
				</div>
			<?php endforeach; ?>

			<h2><?php esc_html_e( 'اعلام پخش اپیزودهای جدید', 'seyedcast' ); ?></h2>
			<p class="description"><?php esc_html_e( 'تاریخ و ساعت را محلی وارد کنید؛ کانتر تا زمان پخش نمایش داده می‌شود.', 'seyedcast' ); ?></p>
			<?php foreach ( $events as $i => $event ) : ?>
				<div class="seyedcast-admin-card">
					<strong><?php echo esc_html( sprintf( __( 'رویداد %d', 'seyedcast' ), $i + 1 ) ); ?></strong>
					<p>
						<label><?php esc_html_e( 'عنوان (اختیاری)', 'seyedcast' ); ?></label><br />
						<input type="text" class="regular-text" name="seyedcast_settings[upcoming_events][<?php echo esc_attr( (string) $i ); ?>][title]" value="<?php echo esc_attr( $event['title'] ); ?>" />
					</p>
					<p>
						<label><?php esc_html_e( 'اپیزود مرتبط', 'seyedcast' ); ?></label><br />
						<select name="seyedcast_settings[upcoming_events][<?php echo esc_attr( (string) $i ); ?>][episode_id]">
							<option value="0"><?php esc_html_e( '— بدون لینک —', 'seyedcast' ); ?></option>
							<?php foreach ( $episodes as $ep ) : ?>
								<option value="<?php echo esc_attr( (string) $ep->ID ); ?>" <?php selected( (int) $event['episode_id'], (int) $ep->ID ); ?>>
									<?php echo esc_html( get_the_title( $ep ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label><?php esc_html_e( 'تاریخ و ساعت پخش', 'seyedcast' ); ?></label><br />
						<input type="datetime-local" name="seyedcast_settings[upcoming_events][<?php echo esc_attr( (string) $i ); ?>][starts_at]" value="<?php echo esc_attr( $event['starts_at'] ); ?>" />
					</p>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php if ( 'pwa' === $tab ) : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'فعال‌سازی PWA', 'seyedcast' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="seyedcast_settings[pwa_enabled]" value="1" <?php checked( ! empty( $settings['pwa_enabled'] ) ); ?> />
							<?php esc_html_e( 'Manifest و Service Worker فعال باشد', 'seyedcast' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'پیشنهاد نصب', 'seyedcast' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="seyedcast_settings[pwa_prompt]" value="1" <?php checked( ! empty( $settings['pwa_prompt'] ) ); ?> />
							<?php esc_html_e( 'نمایش بنر «افزودن به صفحه اصلی» در صفحات پادکست', 'seyedcast' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="seyedcast_pwa_name"><?php esc_html_e( 'نام اپلیکیشن', 'seyedcast' ); ?></label></th>
					<td><input type="text" class="regular-text" id="seyedcast_pwa_name" name="seyedcast_settings[pwa_name]" value="<?php echo esc_attr( $settings['pwa_name'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="seyedcast_pwa_short_name"><?php esc_html_e( 'نام کوتاه', 'seyedcast' ); ?></label></th>
					<td><input type="text" class="regular-text" id="seyedcast_pwa_short_name" name="seyedcast_settings[pwa_short_name]" value="<?php echo esc_attr( $settings['pwa_short_name'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="seyedcast_pwa_theme"><?php esc_html_e( 'رنگ theme', 'seyedcast' ); ?></label></th>
					<td><input type="text" class="seyedcast-color-field" id="seyedcast_pwa_theme" name="seyedcast_settings[pwa_theme_color]" value="<?php echo esc_attr( $settings['pwa_theme_color'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="seyedcast_pwa_bg"><?php esc_html_e( 'رنگ پس‌زمینه', 'seyedcast' ); ?></label></th>
					<td><input type="text" class="seyedcast-color-field" id="seyedcast_pwa_bg" name="seyedcast_settings[pwa_bg_color]" value="<?php echo esc_attr( $settings['pwa_bg_color'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'آیکون ۱۹۲×۱۹۲', 'seyedcast' ); ?></th>
					<td>
						<input type="hidden" id="seyedcast_pwa_icon_192" name="seyedcast_settings[pwa_icon_192]" value="<?php echo esc_attr( $settings['pwa_icon_192'] ); ?>" />
						<div id="seyedcast_pwa_icon_192_preview" class="seyedcast-icon-preview">
							<?php if ( $icon_192_url ) : ?>
								<img src="<?php echo esc_url( $icon_192_url ); ?>" alt="" />
							<?php endif; ?>
						</div>
						<button type="button" class="button seyedcast-select-icon" data-target="seyedcast_pwa_icon_192"><?php esc_html_e( 'انتخاب تصویر', 'seyedcast' ); ?></button>
						<button type="button" class="button seyedcast-clear-icon" data-target="seyedcast_pwa_icon_192"><?php esc_html_e( 'حذف', 'seyedcast' ); ?></button>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'آیکون ۵۱۲×۵۱۲', 'seyedcast' ); ?></th>
					<td>
						<input type="hidden" id="seyedcast_pwa_icon_512" name="seyedcast_settings[pwa_icon_512]" value="<?php echo esc_attr( $settings['pwa_icon_512'] ); ?>" />
						<div id="seyedcast_pwa_icon_512_preview" class="seyedcast-icon-preview">
							<?php if ( $icon_512_url ) : ?>
								<img src="<?php echo esc_url( $icon_512_url ); ?>" alt="" />
							<?php endif; ?>
						</div>
						<button type="button" class="button seyedcast-select-icon" data-target="seyedcast_pwa_icon_512"><?php esc_html_e( 'انتخاب تصویر', 'seyedcast' ); ?></button>
						<button type="button" class="button seyedcast-clear-icon" data-target="seyedcast_pwa_icon_512"><?php esc_html_e( 'حذف', 'seyedcast' ); ?></button>
					</td>
				</tr>
			</table>
		<?php endif; ?>

		<?php submit_button( __( 'ذخیره تنظیمات', 'seyedcast' ) ); ?>
	</form>
</div>
