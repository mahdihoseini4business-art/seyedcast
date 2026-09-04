/**
 * Search, countdown, sidebar drawer, banner slider, suggestions.
 */
(function () {
	'use strict';

	var cfg = window.seyedcastApp || {};
	var storageKey = cfg.storageKey || 'seyedcast_player_state_v1';
	var historyKey = cfg.historyKey || 'seyedcast_listen_history_v1';
	var ajaxUrl = cfg.ajaxUrl || '';
	var searchTimer = null;
	var sliderTimer = null;
	var countdownTimers = [];

	function escapeHtml(str) {
		return String(str || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function escapeAttr(str) {
		return escapeHtml(str).replace(/'/g, '&#39;');
	}

	function pad(n) {
		return String(n).padStart(2, '0');
	}

	function formatPos(sec) {
		sec = Math.floor(sec || 0);
		var m = Math.floor(sec / 60);
		var s = sec % 60;
		return m + ':' + pad(s);
	}

	function initCountdowns(root) {
		var nodes = (root || document).querySelectorAll('[data-seyedcast-countdown]');
		nodes.forEach(function (el) {
			if (el._seyedcastTimer) {
				return;
			}
			function tick() {
				var start = parseInt(el.getAttribute('data-start'), 10);
				if (!start) {
					return;
				}
				var diff = Math.max(0, Math.floor((start - Date.now()) / 1000));
				var d = Math.floor(diff / 86400);
				var h = Math.floor((diff % 86400) / 3600);
				var m = Math.floor((diff % 3600) / 60);
				var s = diff % 60;
				var ud = el.querySelector('[data-unit="d"]');
				var uh = el.querySelector('[data-unit="h"]');
				var um = el.querySelector('[data-unit="m"]');
				var us = el.querySelector('[data-unit="s"]');
				if (ud) {
					ud.textContent = pad(d);
				}
				if (uh) {
					uh.textContent = pad(h);
				}
				if (um) {
					um.textContent = pad(m);
				}
				if (us) {
					us.textContent = pad(s);
				}
				if (diff <= 0) {
					el.classList.add('is-done');
					if (el._seyedcastTimer) {
						var doneTimer = el._seyedcastTimer;
						clearInterval(doneTimer);
						el._seyedcastTimer = null;
						countdownTimers = countdownTimers.filter(function (t) {
							return t !== doneTimer;
						});
					}
				}
			}
			tick();
			if (!el.classList.contains('is-done')) {
				el._seyedcastTimer = setInterval(tick, 1000);
				countdownTimers.push(el._seyedcastTimer);
			}
		});
	}

	function setSidebar(open) {
		var sidebar = document.getElementById('seyedcast-sidebar');
		var backdrop = document.querySelector('.seyedcast-sidebar-backdrop');
		var toggle = document.querySelector('[data-seyedcast-sidebar-toggle]');
		if (!sidebar) {
			return;
		}
		sidebar.classList.toggle('is-open', open);
		if (backdrop) {
			backdrop.hidden = !open;
		}
		if (toggle) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
		document.body.classList.toggle('seyedcast-sidebar-open', open);
	}

	function initSidebar() {
		document.addEventListener('click', function (e) {
			var openBtn = e.target.closest('[data-seyedcast-sidebar-toggle]');
			if (openBtn) {
				e.preventDefault();
				setSidebar(true);
				return;
			}
			if (e.target.closest('[data-seyedcast-sidebar-close]')) {
				e.preventDefault();
				setSidebar(false);
			}
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && document.body.classList.contains('seyedcast-sidebar-open')) {
				setSidebar(false);
			}
		});
	}

	function showToast(message, ok) {
		var existing = document.querySelector('.seyedcast-toast');
		if (existing) {
			existing.remove();
		}
		var toast = document.createElement('div');
		toast.className = 'seyedcast-toast' + (ok === false ? ' is-error' : ' is-ok');
		toast.setAttribute('role', 'status');
		toast.setAttribute('aria-live', 'polite');
		toast.textContent = message;
		document.body.appendChild(toast);
		requestAnimationFrame(function () {
			toast.classList.add('is-visible');
		});
		window.setTimeout(function () {
			toast.classList.remove('is-visible');
			window.setTimeout(function () {
				if (toast.parentNode) {
					toast.parentNode.removeChild(toast);
				}
			}, 280);
		}, 2600);
	}

	function copyText(text) {
		if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
			return navigator.clipboard.writeText(text);
		}
		return new Promise(function (resolve, reject) {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.setAttribute('readonly', '');
			ta.style.position = 'fixed';
			ta.style.insetInlineStart = '-9999px';
			document.body.appendChild(ta);
			ta.select();
			try {
				var ok = document.execCommand('copy');
				document.body.removeChild(ta);
				if (ok) {
					resolve();
				} else {
					reject(new Error('copy failed'));
				}
			} catch (err) {
				if (ta.parentNode) {
					document.body.removeChild(ta);
				}
				reject(err);
			}
		});
	}

	function canNativeShare() {
		return typeof navigator.share === 'function';
	}

	function markShareButton(btn, shortLabel) {
		if (!btn) {
			return;
		}
		var labelEl = btn.querySelector('[data-role="label"]');
		if (labelEl) {
			if (!btn._seyedcastShareLabel) {
				btn._seyedcastShareLabel = labelEl.textContent;
			}
			labelEl.textContent = shortLabel;
			btn.classList.add('is-copied');
			window.setTimeout(function () {
				btn.classList.remove('is-copied');
				labelEl.textContent = btn._seyedcastShareLabel;
			}, 1800);
		} else {
			btn.classList.add('is-copied');
			window.setTimeout(function () {
				btn.classList.remove('is-copied');
			}, 1200);
		}
	}

	function sharePayload(opts) {
		opts = opts || {};
		var url = opts.url || window.location.href;
		var title = opts.title || document.title || '';
		var text = opts.text || '';
		var btn = opts.button || null;
		var i18n = cfg.i18n || {};

		function afterCopy() {
			showToast(i18n.shareCopied || 'لینک کپی شد — با دوستانت به اشتراک بگذار', true);
			markShareButton(btn, i18n.shareCopiedShort || 'کپی شد ✓');
			return { method: 'copy' };
		}

		function fail() {
			showToast(i18n.shareFail || 'کپی لینک ممکن نشد', false);
			return { method: 'fail' };
		}

		if (canNativeShare()) {
			return navigator
				.share({
					title: title,
					text: text ? text + '\n' + url : url,
					url: url
				})
				.then(function () {
					markShareButton(btn, i18n.shareNative || 'ارسال شد ✓');
					return { method: 'native' };
				})
				.catch(function (err) {
					if (err && err.name === 'AbortError') {
						return { method: 'abort' };
					}
					return copyText(url).then(afterCopy).catch(fail);
				});
		}

		return copyText(url).then(afterCopy).catch(fail);
	}

	function initShare() {
		window.SeyedcastShare = {
			share: sharePayload,
			copy: copyText,
			toast: showToast
		};

		document.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-seyedcast-share]');
			if (!btn) {
				return;
			}
			// Sticky player share uses data-action="share" instead.
			if (btn.closest('#seyedcast-sticky-player')) {
				return;
			}
			e.preventDefault();
			sharePayload({
				url: btn.getAttribute('data-share-url') || window.location.href,
				title: btn.getAttribute('data-share-title') || document.title || '',
				text: btn.getAttribute('data-share-text') || '',
				button: btn
			});
		});
	}

	function loadProgressMap() {
		try {
			var raw = localStorage.getItem(cfg.progressKey || 'seyedcast_episode_progress_v1');
			if (!raw) {
				return {};
			}
			var map = JSON.parse(raw);
			return map && typeof map === 'object' && !Array.isArray(map) ? map : {};
		} catch (e) {
			return {};
		}
	}

	function paintEpisodeProgress(root) {
		var map = loadProgressMap();
		(root || document).querySelectorAll('.seyedcast-episode-row[data-episode-id]').forEach(function (row) {
			var id = row.getAttribute('data-episode-id');
			var entry = map[id];
			var wrap = row.querySelector('[data-role="progress"]');
			var bar = row.querySelector('[data-role="progress-bar"]');
			var label = row.querySelector('[data-role="progress-label"]');
			if (!wrap || !bar) {
				return;
			}
			var pct = entry && entry.pct ? parseInt(entry.pct, 10) : 0;
			if (pct < 2) {
				wrap.hidden = true;
				row.classList.remove('is-progress', 'is-finished');
				if (label) {
					label.hidden = true;
					label.textContent = '';
				}
				return;
			}
			wrap.hidden = false;
			row.classList.add('is-progress');
			var done = pct >= 97;
			row.classList.toggle('is-finished', done);
			bar.style.width = (done ? 100 : pct) + '%';
			if (label) {
				label.hidden = false;
				label.textContent = done
					? (cfg.i18n && cfg.i18n.progressDone) || 'گوش داده‌اید'
					: pct + '%';
			}
		});
	}

	function initEpisodeProgress() {
		document.addEventListener('seyedcast:progress', function () {
			paintEpisodeProgress(document);
		});
	}

	function applyEpisodeSort(list, mode) {
		var rows = Array.prototype.slice.call(list.querySelectorAll('.seyedcast-episode-row'));
		rows.sort(function (a, b) {
			var na = parseFloat(a.getAttribute('data-number')) || 0;
			var nb = parseFloat(b.getAttribute('data-number')) || 0;
			var da = parseInt(a.getAttribute('data-date'), 10) || 0;
			var db = parseInt(b.getAttribute('data-date'), 10) || 0;
			if (mode === 'oldest') {
				return da - db || na - nb;
			}
			if (mode === 'number') {
				return nb - na || db - da;
			}
			// newest (default by date)
			return db - da || nb - na;
		});
		rows.forEach(function (row, i) {
			list.appendChild(row);
			var index = row.querySelector('.seyedcast-episode-row__index');
			if (index) {
				index.textContent = String(i + 1);
			}
		});
	}

	function initEpisodeSort(root) {
		(root || document).querySelectorAll('[data-seyedcast-episode-sort]').forEach(function (wrap) {
			if (wrap._seyedcastBound) {
				return;
			}
			wrap._seyedcastBound = true;
			var list =
				wrap.closest('.seyedcast-section') &&
				wrap.closest('.seyedcast-section').querySelector('[data-seyedcast-episode-list]');
			if (!list) {
				return;
			}
			var stored = 'newest';
			try {
				stored = localStorage.getItem('seyedcast_episode_sort_v1') || 'newest';
			} catch (e) {
				stored = 'newest';
			}
			if (['newest', 'oldest', 'number'].indexOf(stored) === -1) {
				stored = 'newest';
			}
			wrap.querySelectorAll('[data-sort]').forEach(function (btn) {
				btn.classList.toggle('is-active', btn.getAttribute('data-sort') === stored);
			});
			applyEpisodeSort(list, stored);

			wrap.addEventListener('click', function (e) {
				var btn = e.target.closest('[data-sort]');
				if (!btn) {
					return;
				}
				var mode = btn.getAttribute('data-sort');
				wrap.querySelectorAll('[data-sort]').forEach(function (el) {
					el.classList.toggle('is-active', el === btn);
				});
				applyEpisodeSort(list, mode);
				try {
					localStorage.setItem('seyedcast_episode_sort_v1', mode);
				} catch (err) {
					/* ignore */
				}
			});
		});
	}

	function renderSearchResults(box, items) {
		var input = document.getElementById('seyedcast-search-input');
		if (!items.length) {
			box.innerHTML = '<div class="seyedcast-app-search__empty">' + escapeHtml(cfg.i18n && cfg.i18n.noResults ? cfg.i18n.noResults : 'نتیجه‌ای نبود') + '</div>';
			box.hidden = false;
			if (input) {
				input.setAttribute('aria-expanded', 'true');
			}
			return;
		}
		box.innerHTML = items
			.map(function (item) {
				var cover = item.cover
					? '<img src="' + escapeAttr(item.cover) + '" alt="" width="40" height="40" />'
					: '<span class="seyedcast-app-search__thumb" aria-hidden="true"></span>';
				return (
					'<a class="seyedcast-app-search__item" role="option" href="' +
					escapeAttr(item.url) +
					'" data-seyedcast-nav>' +
					cover +
					'<span><strong>' +
					escapeHtml(item.title) +
					'</strong><small>' +
					escapeHtml(item.meta) +
					'</small></span>' +
					'</a>'
				);
			})
			.join('');
		box.hidden = false;
		if (input) {
			input.setAttribute('aria-expanded', 'true');
		}
	}

	function initSearch() {
		var wrap = document.querySelector('[data-seyedcast-search]');
		if (!wrap || !ajaxUrl) {
			return;
		}
		var input = wrap.querySelector('.seyedcast-app-search__input');
		var box = wrap.querySelector('.seyedcast-app-search__results');
		if (!input || !box) {
			return;
		}

		function closeResults() {
			box.hidden = true;
			input.setAttribute('aria-expanded', 'false');
		}

		input.addEventListener('input', function () {
			var q = input.value.trim();
			clearTimeout(searchTimer);
			if (q.length < 2) {
				box.innerHTML = '';
				closeResults();
				return;
			}
			box.innerHTML =
				'<div class="seyedcast-app-search__empty">' +
				escapeHtml((cfg.i18n && cfg.i18n.searching) || 'در حال جستجو…') +
				'</div>';
			box.hidden = false;
			input.setAttribute('aria-expanded', 'true');
			searchTimer = setTimeout(function () {
				fetch(ajaxUrl + '?action=seyedcast_search&q=' + encodeURIComponent(q), {
					credentials: 'same-origin'
				})
					.then(function (r) {
						return r.json();
					})
					.then(function (json) {
						if (input.value.trim() !== q) {
							return;
						}
						var items = json && json.success && json.data ? json.data.items : [];
						renderSearchResults(box, items);
					})
					.catch(function () {
						if (input.value.trim() === q) {
							closeResults();
						}
					});
			}, 280);
		});

		input.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				closeResults();
				input.blur();
			}
		});

		document.addEventListener('click', function (e) {
			if (!wrap.contains(e.target)) {
				closeResults();
			}
		});
	}

	function loadPlayerState() {
		try {
			var raw = localStorage.getItem(storageKey);
			if (!raw) {
				return null;
			}
			var state = JSON.parse(raw);
			if (!state || !state.audio || !state.title) {
				return null;
			}
			// Treat near-complete playback as finished (stale resume).
			if (typeof state.position === 'number' && typeof state.duration === 'number' && state.duration > 0) {
				if (state.position / state.duration >= 0.97) {
					return null;
				}
			}
			return state;
		} catch (e) {
			return null;
		}
	}

	function resumePayloadFromState(state) {
		if (!state) {
			return null;
		}
		return {
			id: state.id,
			show_id: state.show_id || 0,
			title: state.title,
			show: state.show,
			audio: state.audio,
			cover: state.cover,
			permalink: state.permalink,
			position: state.position || 0,
			rate: state.rate || 1
		};
	}

	function dispatchResume() {
		var fresh = loadPlayerState();
		var payload = resumePayloadFromState(fresh);
		if (!payload) {
			return;
		}
		document.dispatchEvent(
			new CustomEvent('seyedcast:play', {
				detail: payload
			})
		);
	}

	function loadListenHistory() {
		try {
			var raw = localStorage.getItem(historyKey);
			if (!raw) {
				return [];
			}
			var list = JSON.parse(raw);
			if (!Array.isArray(list)) {
				return [];
			}
			return list
				.map(function (id) {
					return parseInt(id, 10) || 0;
				})
				.filter(Boolean)
				.slice(0, 3);
		} catch (e) {
			return [];
		}
	}

	function fillResumeSlide(root, state) {
		if (!root) {
			return false;
		}
		if (!state) {
			root.hidden = true;
			return false;
		}
		var cover = root.querySelector('[data-role="cover"]');
		var title = root.querySelector('[data-role="title"]');
		var meta = root.querySelector('[data-role="meta"]');
		var link = root.querySelector('[data-role="link"]');
		var linkSecondary = root.querySelector('[data-role="link-secondary"]');
		var btn = root.querySelector('[data-role="resume"]');

		if (cover && state.cover) {
			cover.src = state.cover;
			cover.alt = state.title || '';
		}
		if (title) {
			title.textContent = state.title;
		}
		if (meta) {
			var parts = [];
			if (state.show) {
				parts.push(state.show);
			}
			if (state.position) {
				parts.push((cfg.i18n && cfg.i18n.from ? cfg.i18n.from : 'از') + ' ' + formatPos(state.position));
			}
			meta.textContent = parts.join(' · ');
		}
		if (link && state.permalink) {
			link.href = state.permalink;
		}
		if (linkSecondary && state.permalink) {
			linkSecondary.href = state.permalink;
		}
		if (btn && !btn._seyedcastBound) {
			btn._seyedcastBound = true;
			btn.addEventListener('click', function () {
				dispatchResume();
			});
		}
		root.hidden = false;
		return true;
	}

	function initBannerSlider(root) {
		var slider = (root || document).querySelector('[data-seyedcast-banner-slider]');
		if (!slider || slider._seyedcastSlider) {
			return;
		}

		var resume = slider.querySelector('[data-seyedcast-resume-slide]');
		var state = loadPlayerState();
		if (resume) {
			fillResumeSlide(resume, state);
		}

		var slides = Array.prototype.slice.call(slider.querySelectorAll('.seyedcast-featured__slide')).filter(function (el) {
			return !el.hasAttribute('hidden');
		});

		var nav = slider.querySelector('[data-role="nav"]');
		var dotsWrap = slider.querySelector('[data-role="dots"]');
		var prev = slider.querySelector('[data-role="prev"]');
		var next = slider.querySelector('[data-role="next"]');

		if (slides.length < 2) {
			slider._seyedcastSlider = true;
			return;
		}

		var index = 0;

		function show(i) {
			index = (i + slides.length) % slides.length;
			slides.forEach(function (slide, n) {
				var on = n === index;
				slide.classList.toggle('is-active', on);
				if (on) {
					slide.removeAttribute('hidden');
				} else {
					slide.setAttribute('hidden', '');
				}
			});
			if (dotsWrap) {
				Array.prototype.forEach.call(dotsWrap.children, function (dot, n) {
					dot.classList.toggle('is-active', n === index);
					dot.setAttribute('aria-selected', n === index ? 'true' : 'false');
				});
			}
		}

		function schedule() {
			if (sliderTimer) {
				clearInterval(sliderTimer);
			}
			sliderTimer = setInterval(function () {
				show(index + 1);
			}, 6500);
		}

		if (nav) {
			nav.hidden = false;
		}
		if (prev) {
			prev.hidden = false;
		}
		if (next) {
			next.hidden = false;
		}
		if (dotsWrap) {
			dotsWrap.innerHTML = '';
			slides.forEach(function (_slide, n) {
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'seyedcast-featured__dot' + (n === 0 ? ' is-active' : '');
				btn.setAttribute('role', 'tab');
				btn.setAttribute('aria-label', String(n + 1));
				btn.addEventListener('click', function () {
					show(n);
					schedule();
				});
				dotsWrap.appendChild(btn);
			});
		}
		if (prev) {
			prev.addEventListener('click', function () {
				show(index - 1);
				schedule();
			});
		}
		if (next) {
			next.addEventListener('click', function () {
				show(index + 1);
				schedule();
			});
		}

		show(0);
		schedule();
		slider.addEventListener('mouseenter', function () {
			if (sliderTimer) {
				clearInterval(sliderTimer);
			}
		});
		slider.addEventListener('mouseleave', schedule);
		slider._seyedcastSlider = true;
	}

	function renderSuggestions(section, items) {
		var track = section.querySelector('[data-role="track"]');
		if (!track) {
			return;
		}
		if (!items.length) {
			section.hidden = true;
			track.innerHTML = '';
			return;
		}
		track.innerHTML = items
			.map(function (item) {
				return (
					'<a class="seyedcast-show-tile seyedcast-suggest__item" role="listitem" href="' +
					escapeAttr(item.url) +
					'" data-seyedcast-nav>' +
					'<span class="seyedcast-show-tile__art">' +
					'<img src="' +
					escapeAttr(item.cover) +
					'" alt="' +
					escapeAttr(item.title || '') +
					'" width="300" height="300" loading="lazy" />' +
					'<span class="seyedcast-show-tile__play" aria-hidden="true"><span></span></span>' +
					'</span>' +
					'<span class="seyedcast-show-tile__title">' +
					escapeHtml(item.title) +
					'</span>' +
					'<span class="seyedcast-show-tile__meta">' +
					escapeHtml(item.meta || '') +
					'</span>' +
					'</a>'
				);
			})
			.join('');
		section.hidden = false;
	}

	function initSuggestions(root) {
		var section = (root || document).querySelector('[data-seyedcast-suggest]');
		if (!section || !ajaxUrl) {
			return;
		}
		var ids = loadListenHistory();
		if (!ids.length) {
			section.hidden = true;
			return;
		}
		fetch(ajaxUrl + '?action=seyedcast_suggest&ids=' + encodeURIComponent(ids.join(',')), {
			credentials: 'same-origin'
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (json) {
				var items = json && json.success && json.data ? json.data.items : [];
				renderSuggestions(section, items);
			})
			.catch(function () {
				section.hidden = true;
			});
	}

	function initContinueListening(root) {
		var section = (root || document).querySelector('[data-seyedcast-continue]');
		if (!section) {
			return;
		}
		var state = loadPlayerState();
		if (!state) {
			section.hidden = true;
			return;
		}
		var cover = section.querySelector('[data-role="cover"]');
		var title = section.querySelector('[data-role="title"]');
		var meta = section.querySelector('[data-role="meta"]');
		var btn = section.querySelector('[data-role="resume"]');
		if (cover && state.cover) {
			cover.src = state.cover;
			cover.alt = state.title || '';
		}
		if (title) {
			title.textContent = state.title || '';
		}
		if (meta) {
			var parts = [];
			if (state.show) {
				parts.push(state.show);
			}
			if (state.position) {
				parts.push((cfg.i18n && cfg.i18n.from ? cfg.i18n.from : 'از') + ' ' + formatPos(state.position));
			}
			meta.textContent = parts.join(' · ');
		}
		if (btn && !btn._seyedcastBound) {
			btn._seyedcastBound = true;
			btn.addEventListener('click', function () {
				dispatchResume();
			});
		}
		section.hidden = false;
	}

	function initCommentReply() {
		if (typeof window.addComment !== 'undefined' && window.addComment.init) {
			window.addComment.init();
		}
	}

	function boot(root) {
		var scope = root || document;
		initCountdowns(scope);
		initBannerSlider(scope);
		initSuggestions(scope);
		initContinueListening(scope);
		initEpisodeSort(scope);
		paintEpisodeProgress(scope);
	}

	initSidebar();
	initSearch();
	initShare();
	initEpisodeProgress();
	boot(document);
	document.addEventListener('seyedcast:navigated', function () {
		if (sliderTimer) {
			clearInterval(sliderTimer);
			sliderTimer = null;
		}
		// Sidebar countdowns stay mounted; only clear timers for detached nodes.
		document.querySelectorAll('[data-seyedcast-countdown]').forEach(function (el) {
			if (!document.body.contains(el) && el._seyedcastTimer) {
				clearInterval(el._seyedcastTimer);
				el._seyedcastTimer = null;
			}
		});
		var stage = document.getElementById('seyedcast-app-stage');
		if (stage) {
			var oldSlider = stage.querySelector('[data-seyedcast-banner-slider]');
			if (oldSlider) {
				oldSlider._seyedcastSlider = false;
			}
		}
		boot(document);
		initCommentReply();
	});
	document.addEventListener('seyedcast:history', function () {
		initSuggestions(document);
		initContinueListening(document);
		var resume = document.querySelector('[data-seyedcast-resume-slide]');
		if (resume) {
			fillResumeSlide(resume, loadPlayerState());
		}
	});
	initCommentReply();
})();
