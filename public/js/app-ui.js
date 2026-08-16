/**
 * Search, countdown, sidebar drawer, continue listening.
 */
(function () {
	'use strict';

	var cfg = window.seyedcastApp || {};
	var storageKey = cfg.storageKey || 'seyedcast_player_state_v1';
	var ajaxUrl = cfg.ajaxUrl || '';
	var searchTimer = null;

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
				}
			}
			tick();
			el._seyedcastTimer = setInterval(tick, 1000);
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
	}

	function renderSearchResults(box, items) {
		if (!items.length) {
			box.innerHTML = '<div class="seyedcast-app-search__empty">' + (cfg.i18n && cfg.i18n.noResults ? cfg.i18n.noResults : 'نتیجه‌ای نبود') + '</div>';
			box.hidden = false;
			return;
		}
		box.innerHTML = items
			.map(function (item) {
				return (
					'<a class="seyedcast-app-search__item" role="option" href="' +
					item.url +
					'" data-seyedcast-nav>' +
					'<img src="' +
					item.cover +
					'" alt="" width="40" height="40" />' +
					'<span><strong>' +
					item.title +
					'</strong><small>' +
					item.meta +
					'</small></span>' +
					'</a>'
				);
			})
			.join('');
		box.hidden = false;
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

		input.addEventListener('input', function () {
			var q = input.value.trim();
			clearTimeout(searchTimer);
			if (q.length < 2) {
				box.hidden = true;
				box.innerHTML = '';
				return;
			}
			searchTimer = setTimeout(function () {
				fetch(ajaxUrl + '?action=seyedcast_search&q=' + encodeURIComponent(q), {
					credentials: 'same-origin'
				})
					.then(function (r) {
						return r.json();
					})
					.then(function (json) {
						var items = json && json.success && json.data ? json.data.items : [];
						renderSearchResults(box, items);
					})
					.catch(function () {
						box.hidden = true;
					});
			}, 280);
		});

		document.addEventListener('click', function (e) {
			if (!wrap.contains(e.target)) {
				box.hidden = true;
			}
		});
	}

	function initContinue() {
		var root = document.querySelector('[data-seyedcast-continue]');
		if (!root) {
			return;
		}
		var raw;
		try {
			raw = localStorage.getItem(storageKey);
		} catch (e) {
			return;
		}
		if (!raw) {
			return;
		}
		var state;
		try {
			state = JSON.parse(raw);
		} catch (e2) {
			return;
		}
		if (!state || !state.audio || !state.title) {
			return;
		}

		var cover = root.querySelector('[data-role="cover"]');
		var title = root.querySelector('[data-role="title"]');
		var meta = root.querySelector('[data-role="meta"]');
		var btn = root.querySelector('[data-role="resume"]');
		if (cover && state.cover) {
			cover.src = state.cover;
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
		root.hidden = false;

		if (btn) {
			btn.addEventListener('click', function () {
				document.dispatchEvent(
					new CustomEvent('seyedcast:play', {
						detail: {
							id: state.id,
							title: state.title,
							show: state.show,
							audio: state.audio,
							cover: state.cover,
							permalink: state.permalink,
							position: state.position || 0,
							rate: state.rate || 1
						}
					})
				);
			});
		}
	}

	function boot() {
		initCountdowns(document);
		initContinue();
	}

	initSidebar();
	initSearch();
	boot();
	document.addEventListener('seyedcast:navigated', boot);
})();
