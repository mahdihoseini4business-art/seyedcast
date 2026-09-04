/**
 * PWA: install toast (mobile browser) + new-episode toast (installed/standalone).
 */
(function () {
	'use strict';

	function boot() {
		var cfg = window.seyedcastPwa || {};
		var root = document.getElementById('seyedcast-pwa-prompt');
		if (!root) {
			return;
		}

		var storageKey = cfg.storageKey || 'seyedcast_pwa_prompt_dismissed';
		var snoozeKey = cfg.snoozeKey || 'seyedcast_pwa_prompt_snooze';
		var seenKey = cfg.seenKey || 'seyedcast_last_seen_episode';
		var deferredPrompt = null;
		var toastVisible = false;
		var mode = 'install'; // install | update
		var latestItem = null;

		var titleEl = root.querySelector('.seyedcast-pwa-prompt__title');
		var msgEl = root.querySelector('.seyedcast-pwa-prompt__message');
		var iconEl = root.querySelector('.seyedcast-pwa-prompt__icon');
		var installBtn = root.querySelector('[data-action="install"]');
		var dismissBtn = root.querySelector('[data-action="dismiss-secondary"]');
		var closeBtn = root.querySelector('.seyedcast-pwa-prompt__close');

		function isStandalone() {
			return (
				window.navigator.standalone === true ||
				window.matchMedia('(display-mode: standalone)').matches ||
				window.matchMedia('(display-mode: fullscreen)').matches ||
				window.matchMedia('(display-mode: minimal-ui)').matches
			);
		}

		function isIos() {
			var ua = navigator.userAgent || '';
			if (/iPad|iPhone|iPod/.test(ua)) {
				return true;
			}
			// iPadOS 13+ desktop UA
			return /Macintosh/.test(ua) && navigator.maxTouchPoints > 1;
		}

		function isAndroid() {
			return /Android/i.test(navigator.userAgent || '');
		}

		function isMobile() {
			var ua = navigator.userAgent || '';
			if (/Android|iPhone|iPad|iPod|Mobile|IEMobile|Opera Mini|webOS|BlackBerry/i.test(ua)) {
				return true;
			}
			if (isIos()) {
				return true;
			}
			if (navigator.maxTouchPoints > 1 && window.matchMedia('(max-width: 1024px)').matches) {
				return true;
			}
			return window.matchMedia('(max-width: 900px) and (pointer: coarse)').matches;
		}

		function storageGet(key) {
			try {
				return localStorage.getItem(key);
			} catch (e) {
				return null;
			}
		}

		function storageSet(key, value) {
			try {
				localStorage.setItem(key, value);
			} catch (e) {
				/* ignore */
			}
		}

		function hardDismissed() {
			return storageGet(storageKey) === '1';
		}

		function isSnoozed() {
			var until = parseInt(storageGet(snoozeKey) || '0', 10);
			return until > Date.now();
		}

		function snooze(days) {
			var ms = (days || 3) * 24 * 60 * 60 * 1000;
			storageSet(snoozeKey, String(Date.now() + ms));
			hideToast();
		}

		function hardDismiss() {
			storageSet(storageKey, '1');
			try {
				localStorage.removeItem(snoozeKey);
			} catch (e) {
				/* ignore */
			}
			hideToast();
		}

		function hideToast() {
			root.hidden = true;
			toastVisible = false;
			root.classList.remove('is-update');
		}

		function setIcon(url, alt) {
			if (!iconEl) {
				return;
			}
			if (url) {
				iconEl.hidden = false;
				iconEl.src = url;
				iconEl.alt = alt || '';
			} else if (cfg.iconUrl) {
				iconEl.hidden = false;
				iconEl.src = cfg.iconUrl;
				iconEl.alt = alt || '';
			} else {
				iconEl.hidden = true;
			}
		}

		function showInstallToast(messageOverride) {
			if (!cfg.promptEnabled) {
				return;
			}
			if (hardDismissed() || isSnoozed() || isStandalone() || !isMobile()) {
				return;
			}
			mode = 'install';
			root.classList.remove('is-update');
			if (titleEl) {
				titleEl.textContent = (cfg.i18n && cfg.i18n.title) || 'نصب روی موبایل';
			}
			if (installBtn) {
				installBtn.hidden = false;
				installBtn.textContent = (cfg.i18n && cfg.i18n.install) || 'افزودن به صفحه اصلی';
			}
			if (dismissBtn) {
				dismissBtn.hidden = false;
				dismissBtn.textContent = (cfg.i18n && cfg.i18n.later) || 'بعداً';
			}
			if (closeBtn) {
				closeBtn.setAttribute('aria-label', (cfg.i18n && cfg.i18n.close) || 'بستن');
			}
			setIcon(cfg.iconUrl, (cfg.i18n && cfg.i18n.title) || '');
			if (msgEl) {
				msgEl.textContent = messageOverride || (cfg.i18n && cfg.i18n.message) || '';
			}
			root.hidden = false;
			toastVisible = true;
		}

		function showUpdateToast(item) {
			if (!item || !item.id) {
				return;
			}
			mode = 'update';
			latestItem = item;
			root.classList.add('is-update');
			if (titleEl) {
				titleEl.textContent = (cfg.i18n && cfg.i18n.updateTitle) || 'پادکست جدید اومد';
			}
			if (msgEl) {
				var label = item.showTitle ? item.showTitle + ' — ' + item.title : item.title;
				msgEl.textContent = label || ((cfg.i18n && cfg.i18n.updateMessage) || '');
			}
			if (installBtn) {
				installBtn.hidden = false;
				installBtn.textContent = (cfg.i18n && cfg.i18n.listen) || 'گوش بده';
			}
			if (dismissBtn) {
				dismissBtn.hidden = false;
				dismissBtn.textContent = (cfg.i18n && cfg.i18n.dismissUpdate) || 'باشه';
			}
			if (closeBtn) {
				closeBtn.setAttribute('aria-label', (cfg.i18n && cfg.i18n.close) || 'بستن');
			}
			setIcon(item.cover || cfg.iconUrl, item.title || '');
			root.hidden = false;
			toastVisible = true;
		}

		function showPlatformHint() {
			var hint = '';
			if (isIos()) {
				hint = (cfg.i18n && cfg.i18n.iosHint) || '';
			} else if (isAndroid()) {
				hint = (cfg.i18n && cfg.i18n.androidHint) || '';
			} else {
				hint =
					(cfg.i18n && cfg.i18n.genericHint) ||
					'از منوی مرورگر گزینه «Add to Home Screen» یا «نصب برنامه» را انتخاب کنید.';
			}
			if (hint) {
				showInstallToast(hint);
			}
		}

		function scheduleInstallToast() {
			if (!cfg.promptEnabled || hardDismissed() || isSnoozed() || isStandalone() || !isMobile()) {
				return;
			}
			window.setTimeout(function () {
				if (hardDismissed() || isSnoozed() || toastVisible || isStandalone()) {
					return;
				}
				showInstallToast();
			}, cfg.delayMs || 1500);
		}

		function getSeen() {
			return storageGet(seenKey);
		}

		function checkForUpdates() {
			if (!isStandalone()) {
				return;
			}
			if (!cfg.ajaxUrl) {
				return;
			}

			var body = new URLSearchParams();
			body.set('action', cfg.latestAction || 'seyedcast_latest_episode');

			fetch(cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: body.toString()
			})
				.then(function (res) {
					return res.json();
				})
				.then(function (json) {
					if (!json || !json.success || !json.data || !json.data.id) {
						return;
					}
					var item = json.data;
					var id = String(item.id);
					var published = parseInt(item.published || '0', 10);
					var seenId = getSeen();
					var seenPublished = parseInt(storageGet(seenKey + '_ts') || '0', 10);

					if (!seenId) {
						// First open after install: seed without toasting existing content.
						markSeen(id, published);
						return;
					}

					// Already viewing this latest episode.
					if (String(cfg.currentEpisodeId || '') === id) {
						markSeen(id, published);
						return;
					}

					var isNewer =
						(published > 0 && published > seenPublished) ||
						(published <= 0 && parseInt(id, 10) > parseInt(seenId, 10));

					if (isNewer && id !== seenId) {
						showUpdateToast(item);
					}
				})
				.catch(function () {
					/* ignore network errors */
				});
		}

		function markSeen(id, published) {
			if (!id) {
				return;
			}
			storageSet(seenKey, String(id));
			if (published) {
				storageSet(seenKey + '_ts', String(published));
			}
		}

		function openLatest() {
			if (!latestItem || !latestItem.url) {
				hideToast();
				return;
			}
			markSeen(latestItem.id, latestItem.published);
			hideToast();
			var url = latestItem.url;
			var anchor = document.createElement('a');
			anchor.href = url;
			anchor.setAttribute('data-seyedcast-nav', '');
			anchor.style.display = 'none';
			document.body.appendChild(anchor);
			anchor.dispatchEvent(
				new MouseEvent('click', {
					bubbles: true,
					cancelable: true,
					view: window,
					button: 0
				})
			);
			anchor.remove();
		}

		function dismissUpdate() {
			if (latestItem && latestItem.id) {
				markSeen(latestItem.id, latestItem.published);
			}
			hideToast();
		}

		// Register SW whenever PWA is enabled (not only when install prompt is on).
		if ('serviceWorker' in navigator && cfg.swUrl) {
			var swOpts = cfg.swScope ? { scope: cfg.swScope } : undefined;
			navigator.serviceWorker.register(cfg.swUrl, swOpts).catch(function () {
				/* ignore */
			});
		}

		if (installBtn) {
			installBtn.addEventListener('click', function () {
				if (mode === 'update') {
					openLatest();
					return;
				}
				if (deferredPrompt) {
					deferredPrompt.prompt();
					deferredPrompt.userChoice.finally(function () {
						deferredPrompt = null;
						hardDismiss();
					});
					return;
				}
				showPlatformHint();
			});
		}

		if (dismissBtn) {
			dismissBtn.addEventListener('click', function () {
				if (mode === 'update') {
					dismissUpdate();
					return;
				}
				snooze(cfg.snoozeDays || 3);
			});
		}

		if (closeBtn) {
			closeBtn.addEventListener('click', function () {
				if (mode === 'update') {
					dismissUpdate();
					return;
				}
				hardDismiss();
			});
		}

		if (isStandalone()) {
			checkForUpdates();
			document.addEventListener('seyedcast:navigated', function () {
				var epEl = document.querySelector('#seyedcast-app-stage [data-episode-id]');
				if (epEl) {
					cfg.currentEpisodeId = epEl.getAttribute('data-episode-id');
				}
				checkForUpdates();
			});
			return;
		}

		if (!isMobile() || !cfg.promptEnabled) {
			return;
		}

		window.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault();
			deferredPrompt = e;
			showInstallToast();
		});

		scheduleInstallToast();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
