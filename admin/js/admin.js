/**
 * اسکریپت پنل مدیریت
 */

(function($) {
    'use strict';

    var SeyedCastAdmin = {
        init: function() {
            this.bindEvents();
            this.initColorPickers();
        },

        bindEvents: function() {
            // آپلود تصویر
            $(document).on('click', '.seyedcast-upload-image', this.uploadImage);
            
            // آپلود فایل صوتی
            $(document).on('click', '.seyedcast-upload-audio', this.uploadAudio);
            
            // پاکسازی آمار
            $(document).on('click', '#seyedcast-clear-stats', this.clearStats);
        },

        initColorPickers: function() {
            $('.seyedcast-color-picker').wpColorPicker();
        },

        uploadImage: function(e) {
            e.preventDefault();
            
            var targetId = $(this).data('target');
            var frame = wp.media({
                title: 'انتخاب تصویر',
                button: {
                    text: 'انتخاب'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#' + targetId).val(attachment.url);
            });

            frame.open();
        },

        uploadAudio: function(e) {
            e.preventDefault();
            
            var targetId = $(this).data('target');
            var frame = wp.media({
                title: 'انتخاب فایل صوتی',
                button: {
                    text: 'انتخاب'
                },
                multiple: false,
                library: {
                    type: 'audio'
                }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#' + targetId).val(attachment.url);
            });

            frame.open();
        },

        clearStats: function(e) {
            e.preventDefault();
            
            if (!confirm('آیا مطمئن هستید؟ آمار بیش از 90 روز پاکسازی خواهد شد.')) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('در حال پاکسازی...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'seyedcast_clear_stats',
                    nonce: seyedcast_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                    } else {
                        alert(response.data.message || 'خطا در پاکسازی آمار');
                    }
                },
                error: function() {
                    alert('خطا در ارتباط با سرور');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('پاکسازی آمار قدیمی');
                }
            });
        }
    };

    $(document).ready(function() {
        SeyedCastAdmin.init();
    });

})(jQuery);
