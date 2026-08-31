(function () {
	'use strict';

	var cfg = window.seyedcastPwa || {};
	var root = document.getElementById('seyedcast-pwa-prompt');
	if (!root) {
		return;
	}

	var storageKey = cfg.storageKey || 'seyedcast_pwa_prompt_dismissed';
	var deferredPrompt = null;
	var toastVisible = false;
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
			window.matchMedia('(display-mode: fullscreen)').matches
		);
	}

	function isMobile() {
		var ua = navigator.userAgent || '';
		if (/Android|iPhone|iPad|iPod|Mobile|IEMobile|Opera Mini/i.test(ua)) {
			return true;
		}
		return window.matchMedia('(max-width: 900px) and (pointer: coarse)').matches;
	}

	function isIos() {
		if (cfg.isIos) {
			return true;
		}
		return /iPad|iPhone|iPod/.test(navigator.userAgent || '');
	}

	function isAndroid() {
		return /Android/i.test(navigator.userAgent || '');
	}

	function dismissed() {
		try {
			return localStorage.getItem(storageKey) === '1';
		} catch (e) {
			return false;
		}
	}

	function dismiss() {
		try {
			localStorage.setItem(storageKey, '1');
		} catch (e) {
			/* ignore */
		}
		root.hidden = true;
		toastVisible = false;
	}

	function bindLabels() {
		if (titleEl) {
			titleEl.textContent = (cfg.i18n && cfg.i18n.title) || 'Install';
		}
		if (installBtn) {
			installBtn.textContent = (cfg.i18n && cfg.i18n.install) || 'Install';
		}
		if (dismissBtn) {
			dismissBtn.textContent = (cfg.i18n && cfg.i18n.later) || 'Later';
		}
		if (closeBtn) {
			closeBtn.setAttribute('aria-label', (cfg.i18n && cfg.i18n.close) || 'Close');
		}
		if (iconEl && cfg.iconUrl) {
			iconEl.src = cfg.iconUrl;
			iconEl.alt = (cfg.i18n && cfg.i18n.title) || '';
		} else if (iconEl) {
			iconEl.hidden = true;
		}
	}

	function showToast(messageOverride) {
		if (dismissed() || isStandalone() || !isMobile()) {
			return;
		}
		bindLabels();
		if (msgEl) {
			msgEl.textContent = messageOverride || (cfg.i18n && cfg.i18n.message) || '';
		}
		root.hidden = false;
		toastVisible = true;
	}

	function showPlatformHint() {
		var hint = '';
		if (isIos()) {
			hint = (cfg.i18n && cfg.i18n.iosHint) || '';
		} else if (isAndroid()) {
			hint = (cfg.i18n && cfg.i18n.androidHint) || '';
		}
		if (hint && msgEl) {
			msgEl.textContent = hint;
			if (!toastVisible) {
				showToast(hint);
			}
		}
	}

	function scheduleToast() {
		if (dismissed() || isStandalone() || !isMobile()) {
			return;
		}
		window.setTimeout(function () {
			if (dismissed() || toastVisible) {
				return;
			}
			showToast();
		}, cfg.delayMs || 1500);
	}

	if ('serviceWorker' in navigator && cfg.swUrl) {
		navigator.serviceWorker.register(cfg.swUrl).catch(function () {
			/* ignore */
		});
	}

	if (!isMobile() || isStandalone()) {
		return;
	}

	window.addEventListener('beforeinstallprompt', function (e) {
		e.preventDefault();
		deferredPrompt = e;
		showToast();
	});

	scheduleToast();

	if (installBtn) {
		installBtn.addEventListener('click', function () {
			if (deferredPrompt) {
				deferredPrompt.prompt();
				deferredPrompt.userChoice.finally(function () {
					deferredPrompt = null;
					dismiss();
				});
				return;
			}
			showPlatformHint();
		});
	}

	[dismissBtn, closeBtn].forEach(function (btn) {
		if (!btn) {
			return;
		}
		btn.addEventListener('click', dismiss);
	});
})();
