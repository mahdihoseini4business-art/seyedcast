(function ($) {
	'use strict';

	var chart = null;

	function getScope() {
		return $('#seyedcast_stats_scope').val() || 'all_shows';
	}

	function populateEpisodes(showId) {
		var $episode = $('#seyedcast_stats_episode');
		$episode.empty();

		if (!showId || showId === '0') {
			$episode.append(
				$('<option>', { value: '0', text: seyedcastStats.i18n.pickEpisode })
			);
			$episode.prop('disabled', true);
			return;
		}

		var episodesMap = seyedcastStats.episodes || {};
		var list = episodesMap[showId] || episodesMap[String(showId)] || [];
		if (!list.length) {
			$episode.append(
				$('<option>', { value: '0', text: seyedcastStats.i18n.noEpisodes })
			);
			$episode.prop('disabled', true);
			return;
		}

		$episode.append(
			$('<option>', { value: '0', text: seyedcastStats.i18n.pickEpisode })
		);
		list.forEach(function (item) {
			$episode.append(
				$('<option>', { value: String(item.id), text: item.label })
			);
		});
		$episode.prop('disabled', false);
	}

	function syncScopeUi() {
		var scope = getScope();
		var $showWrap = $('#seyedcast_stats_show_wrap');
		var $episodeWrap = $('#seyedcast_stats_episode_wrap');

		if (scope === 'show') {
			$showWrap.prop('hidden', false);
			$episodeWrap.prop('hidden', true);
		} else if (scope === 'episode') {
			$showWrap.prop('hidden', false);
			$episodeWrap.prop('hidden', false);
			populateEpisodes($('#seyedcast_stats_show').val());
		} else {
			$showWrap.prop('hidden', true);
			$episodeWrap.prop('hidden', true);
		}
	}

	function getFilters() {
		var scope = getScope();
		var showId = '0';
		var episodeId = '0';

		if (scope === 'show') {
			showId = $('#seyedcast_stats_show').val() || '0';
		} else if (scope === 'episode') {
			showId = $('#seyedcast_stats_show').val() || '0';
			episodeId = $('#seyedcast_stats_episode').val() || '0';
		}

		return {
			show_id: showId,
			episode_id: episodeId,
			days: $('#seyedcast_stats_days').val() || '30',
			mode: $('#seyedcast_stats_mode').val() || 'unique'
		};
	}

	function canLoadChart(filters) {
		var scope = getScope();
		if (scope === 'show' && filters.show_id === '0') {
			return false;
		}
		if (scope === 'episode' && (filters.show_id === '0' || filters.episode_id === '0')) {
			return false;
		}
		return true;
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
		var formatted = Number(total || 0).toLocaleString('fa-IR');
		$('#seyedcast_stats_period_total').text(label + ' در این بازه: ' + formatted);
	}

	function getChartConstructor() {
		if (typeof window.Chart === 'function') {
			return window.Chart;
		}
		if (window.chart && typeof window.chart === 'function') {
			return window.chart;
		}
		return null;
	}

	function renderChart(data, mode) {
		var ChartCtor = getChartConstructor();
		if (!ChartCtor) {
			setStatus(seyedcastStats.i18n.chartMissing, true);
			return false;
		}

		var canvas = document.getElementById('seyedcast_stats_chart');
		if (!canvas) {
			return false;
		}

		var ctx = canvas.getContext('2d');
		if (!ctx) {
			setStatus(seyedcastStats.i18n.error, true);
			return false;
		}

		var label = mode === 'total' ? seyedcastStats.i18n.total : seyedcastStats.i18n.unique;
		var labels = (data && data.labels) ? data.labels : [];
		var values = (data && data.values) ? data.values : [];

		if (chart) {
			chart.destroy();
			chart = null;
		}

		chart = new ChartCtor(ctx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [{
					label: label,
					data: values,
					borderColor: '#1DB954',
					backgroundColor: 'rgba(29, 185, 84, 0.12)',
					fill: true,
					tension: 0.3,
					pointRadius: values.length > 14 ? 0 : 3,
					pointHoverRadius: 5,
					borderWidth: 2
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: {
					mode: 'index',
					intersect: false
				},
				plugins: {
					legend: {
						display: true,
						position: 'top'
					},
					tooltip: {
						enabled: true
					}
				},
				scales: {
					x: {
						display: true,
						ticks: {
							maxRotation: 0,
							autoSkip: true,
							maxTicksLimit: 12
						}
					},
					y: {
						beginAtZero: true,
						display: true,
						ticks: {
							precision: 0
						},
						suggestedMax: Math.max(5, Math.max.apply(null, values.concat([0])) + 1)
					}
				}
			}
		});

		return true;
	}

	function loadChart() {
		var filters = getFilters();

		if (!canLoadChart(filters)) {
			if (chart) {
				chart.destroy();
				chart = null;
			}
			setStatus(getScope() === 'episode' ? seyedcastStats.i18n.pickEpisode : seyedcastStats.i18n.pickShow, false);
			$('#seyedcast_stats_period_total').text('');
			return;
		}

		setStatus(seyedcastStats.i18n.loading, false);

		$.ajax({
			url: seyedcastStats.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: $.extend({ action: 'seyedcast_stats_chart', nonce: seyedcastStats.nonce }, filters)
		})
			.done(function (response) {
				if (!response || !response.success || !response.data) {
					setStatus(seyedcastStats.i18n.error, true);
					return;
				}

				var data = response.data;
				setStatus(Number(data.total) === 0 ? seyedcastStats.i18n.empty : '', false);
				updatePeriodTotal(data.total || 0, filters.mode);

				if (!renderChart(data, filters.mode)) {
					return;
				}
			})
			.fail(function (xhr) {
				var message = seyedcastStats.i18n.error;
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					message = xhr.responseJSON.data.message;
				}
				setStatus(message, true);
			});
	}

	$(function () {
		if (!$('#seyedcast_stats_chart').length) {
			return;
		}

		syncScopeUi();

		if (seyedcastStats.initialChart && getScope() === 'all_shows') {
			updatePeriodTotal(seyedcastStats.initialChart.total || 0, $('#seyedcast_stats_mode').val() || 'unique');
			renderChart(seyedcastStats.initialChart, $('#seyedcast_stats_mode').val() || 'unique');
			if (Number(seyedcastStats.initialChart.total) === 0) {
				setStatus(seyedcastStats.i18n.empty, false);
			}
		}

		$('#seyedcast_stats_scope').on('change', function () {
			syncScopeUi();
			loadChart();
		});

		$('#seyedcast_stats_show').on('change', function () {
			if (getScope() === 'episode') {
				populateEpisodes($(this).val());
			}
			loadChart();
		});

		$('#seyedcast_stats_episode, #seyedcast_stats_days, #seyedcast_stats_mode').on('change', loadChart);

		if (!seyedcastStats.initialChart || getScope() !== 'all_shows') {
			loadChart();
		}
	});
}(jQuery));
