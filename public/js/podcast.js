/**
 * اسکریپت فرانت‌اند پادکست
 */

(function($) {
    'use strict';

    var SeyedCastFrontend = {
        init: function() {
            this.bindEvents();
            this.initInfiniteScroll();
        },

        bindEvents: function() {
            // فیلتر دسته‌بندی
            $(document).on('click', '.seyedcast-filter-btn', this.filterByCategory);
            
            // جستجو
            var searchTimer;
            $(document).on('input', '.seyedcast-search-input', function() {
                clearTimeout(searchTimer);
                var $input = $(this);
                searchTimer = setTimeout(function() {
                    SeyedCastFrontend.search($input.val());
                }, 300);
            });
            
            // بارگذاری بیشتر
            $(document).on('click', '.seyedcast-load-more', this.loadMore);
        },

        filterByCategory: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var category = $btn.data('filter');
            
            // آپدیت دکمه فعال
            $('.seyedcast-filter-btn').removeClass('active');
            $btn.addClass('active');
            
            // فیلتر کارت‌ها
            $('.seyedcast-episode-card').each(function() {
                var $card = $(this);
                var cardCategories = $card.data('categories') || '';
                
                if (category === 'all' || cardCategories.indexOf(category) !== -1) {
                    $card.fadeIn(300);
                } else {
                    $card.fadeOut(300);
                }
            });
        },

        search: function(query) {
            if (query.length < 2) {
                // بازگرداندن همه کارت‌ها
                $('.seyedcast-episode-card').fadeIn(300);
                $('.seyedcast-no-results').remove();
                return;
            }
            
            var $container = $('.seyedcast-episodes');
            
            $.ajax({
                url: seyedcast_ajax.url,
                type: 'POST',
                data: {
                    action: 'seyedcast_search',
                    search: query,
                    nonce: seyedcast_ajax.nonce
                },
                beforeSend: function() {
                    $container.css('opacity', '0.5');
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        $container.html(response.data.html);
                    } else {
                        $container.html(
                            '<div class="seyedcast-no-results">' +
                            '<p>نتیجه‌ای یافت نشد.</p>' +
                            '</div>'
                        );
                    }
                },
                complete: function() {
                    $container.css('opacity', '1');
                }
            });
        },

        loadMore: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var page = parseInt($btn.data('page')) || 2;
            var perPage = parseInt($btn.data('per-page')) || 12;
            var category = $('.seyedcast-filter-btn.active').data('filter') || 'all';
            var search = $('.seyedcast-search-input').val() || '';
            
            $btn.prop('disabled', true).text('در حال بارگذاری...');
            
            $.ajax({
                url: seyedcast_ajax.url,
                type: 'POST',
                data: {
                    action: 'seyedcast_load_more',
                    page: page,
                    per_page: perPage,
                    category: category,
                    search: search,
                    nonce: seyedcast_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        $('.seyedcast-episodes').append(response.data.html);
                        
                        // آپدیت شماره صفحه
                        $btn.data('page', page + 1);
                        
                        // بررسی وجود صفحات بیشتر
                        if (page >= response.data.pages) {
                            $btn.remove();
                        } else {
                            $btn.prop('disabled', false).text('بارگذاری بیشتر');
                        }
                    } else {
                        $btn.remove();
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('بارگذاری بیشتر');
                }
            });
        },

        initInfiniteScroll: function() {
            if (!$('.seyedcast-load-more').length) {
                return;
            }
            
            var $btn = $('.seyedcast-load-more');
            var observer = new IntersectionObserver(function(entries) {
                if (entries[0].isIntersecting && !$btn.prop('disabled')) {
                    $btn.trigger('click');
                }
            }, { threshold: 0.5 });
            
            observer.observe($btn[0]);
        }
    };

    $(document).ready(function() {
        SeyedCastFrontend.init();
    });

})(jQuery);
