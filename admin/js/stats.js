(function ($) {
	'use strict';

	var chart = null;

	function getFilters() {
		return {
			show_id: $('#seyedcast_stats_show').val() || '0',
			days: $('#seyedcast_stats_days').val() || '30',
			mode: $('#seyedcast_stats_mode').val() || 'unique'
		};
	}

	function setStatus(message, isError) {
		var $status = $('#seyedcast_stats_status');
		if (!message) {
			$status.prop('hidden', true).text('');
			return;
		}
		$status.prop('hidden', false).text(message);
		if (isError) {
			$status.addClass('is-error');
		} else {
			$status.removeClass('is-error');
		}
	}

	function updatePeriodTotal(total, mode) {
		var label = mode === 'total' ? seyedcastStats.i18n.total : seyedcastStats.i18n.unique;
		var formatted = total.toLocaleString('fa-IR');
		$('#seyedcast_stats_period_total').text(label + ' در این بازه: ' + formatted);
	}

	function loadChart() {
		if (typeof Chart === 'undefined') {
			setStatus(seyedcastStats.i18n.error, true);
			return;
		}

		var filters = getFilters();
		setStatus(seyedcastStats.i18n.loading, false);

		$.getJSON(seyedcastStats.ajaxUrl, $.extend({ action: 'seyedcast_stats_chart', nonce: seyedcastStats.nonce }, filters))
			.done(function (response) {
				if (!response || !response.success || !response.data) {
					setStatus(seyedcastStats.i18n.error, true);
					return;
				}

				var data = response.data;
				setStatus(data.total === 0 ? seyedcastStats.i18n.empty : '', false);
				updatePeriodTotal(data.total || 0, filters.mode);

				var ctx = document.getElementById('seyedcast_stats_chart');
				if (!ctx) {
					return;
				}

				var label = filters.mode === 'total' ? seyedcastStats.i18n.total : seyedcastStats.i18n.unique;

				if (chart) {
					chart.destroy();
				}

				chart = new Chart(ctx, {
					type: 'line',
					data: {
						labels: data.labels || [],
						datasets: [{
							label: label,
							data: data.values || [],
							borderColor: '#1DB954',
							backgroundColor: 'rgba(29, 185, 84, 0.12)',
							fill: true,
							tension: 0.3,
							pointRadius: 3,
							pointHoverRadius: 5
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: true,
						plugins: {
							legend: {
								display: true,
								position: 'top'
							}
						},
						scales: {
							y: {
								beginAtZero: true,
								ticks: {
									precision: 0
								}
							}
						}
					}
				});
			})
			.fail(function () {
				setStatus(seyedcastStats.i18n.error, true);
			});
	}

	$(function () {
		if (!$('#seyedcast_stats_chart').length) {
			return;
		}

		$('#seyedcast_stats_show, #seyedcast_stats_days, #seyedcast_stats_mode').on('change', loadChart);
		loadChart();
	});
}(jQuery));
