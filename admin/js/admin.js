(function ($) {
	'use strict';

	function initColorPickers() {
		$('.seyedcast-color-field').each(function () {
			var $el = $(this);
			if ($el.hasClass('wp-color-picker') || $el.data('wp-color-picker-bound')) {
				return;
			}
			$el.wpColorPicker();
			$el.data('wp-color-picker-bound', true);
		});
	}

	$(function () {
		initColorPickers();

		var audioFrame;
		$('#seyedcast_select_audio').on('click', function (e) {
			e.preventDefault();
			if (audioFrame) {
				audioFrame.open();
				return;
			}
			audioFrame = wp.media({
				title: 'انتخاب فایل صوتی',
				button: { text: 'استفاده از این فایل' },
				library: { type: 'audio' },
				multiple: false
			});
			audioFrame.on('select', function () {
				var attachment = audioFrame.state().get('selection').first().toJSON();
				$('#seyedcast_audio_id').val(attachment.id);
				$('#seyedcast_audio_preview').html(
					'<a href="' + attachment.url + '" target="_blank" rel="noopener noreferrer">' +
					(attachment.title || attachment.filename) +
					'</a>'
				);
				$('#seyedcast_remove_audio').prop('disabled', false);
			});
			audioFrame.open();
		});

		$('#seyedcast_remove_audio').on('click', function (e) {
			e.preventDefault();
			$('#seyedcast_audio_id').val('');
			$('#seyedcast_audio_preview').html('<em>فایلی انتخاب نشده</em>');
			$(this).prop('disabled', true);
		});

		var iconFrame;
		$('.seyedcast-select-icon').on('click', function (e) {
			e.preventDefault();
			var target = $(this).data('target');
			iconFrame = wp.media({
				title: 'انتخاب آیکون',
				button: { text: 'انتخاب' },
				library: { type: 'image' },
				multiple: false
			});
			iconFrame.on('select', function () {
				var attachment = iconFrame.state().get('selection').first().toJSON();
				$('#' + target).val(attachment.id);
				var url = (attachment.sizes && attachment.sizes.thumbnail)
					? attachment.sizes.thumbnail.url
					: attachment.url;
				$('#' + target + '_preview').html('<img src="' + url + '" alt="" />');
			});
			iconFrame.open();
		});

		$('.seyedcast-clear-icon').on('click', function (e) {
			e.preventDefault();
			var target = $(this).data('target');
			$('#' + target).val('');
			$('#' + target + '_preview').empty();
		});
	});
})(jQuery);
