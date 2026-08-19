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
				return (
					'<a class="seyedcast-app-search__item" role="option" href="' +
					escapeAttr(item.url) +
					'" data-seyedcast-nav>' +
					'<img src="' +
					escapeAttr(item.cover) +
					'" alt="" width="40" height="40" />' +
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

		input.addEventListener('input', function () {
			var q = input.value.trim();
			clearTimeout(searchTimer);
			if (q.length < 2) {
				box.hidden = true;
				box.innerHTML = '';
				input.setAttribute('aria-expanded', 'false');
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
				input.setAttribute('aria-expanded', 'false');
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
			return state;
		} catch (e) {
			return null;
		}
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
		if (!root || !state) {
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
				document.dispatchEvent(
					new CustomEvent('seyedcast:play', {
						detail: {
							id: state.id,
							show_id: state.show_id || 0,
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
				document.dispatchEvent(
					new CustomEvent('seyedcast:play', {
						detail: {
							id: state.id,
							show_id: state.show_id || 0,
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
	}

	initSidebar();
	initSearch();
	boot(document);
	document.addEventListener('seyedcast:navigated', function () {
		if (sliderTimer) {
			clearInterval(sliderTimer);
			sliderTimer = null;
		}
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
	});
	initCommentReply();
})();
