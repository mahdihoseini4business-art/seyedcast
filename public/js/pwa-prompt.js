(function () {
	'use strict';

	var cfg = window.seyedcastPwa || {};
	var root = document.getElementById('seyedcast-pwa-prompt');
	if (!root || !cfg) {
		return;
	}

	var storageKey = cfg.storageKey || 'seyedcast_pwa_prompt_dismissed';
	var deferredPrompt = null;
	var titleEl = root.querySelector('.seyedcast-pwa-prompt__title');
	var msgEl = root.querySelector('.seyedcast-pwa-prompt__message');
	var installBtn = root.querySelector('[data-action="install"]');
	var dismissBtn = root.querySelector('[data-action="dismiss"]');

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
	}

	function showPrompt(messageOverride) {
		if (dismissed()) {
			return;
		}
		if (titleEl) {
			titleEl.textContent = (cfg.i18n && cfg.i18n.title) || 'Install';
		}
		if (msgEl) {
			msgEl.textContent = messageOverride || (cfg.i18n && cfg.i18n.message) || '';
		}
		if (installBtn) {
			installBtn.textContent = (cfg.i18n && cfg.i18n.install) || 'Install';
		}
		if (dismissBtn) {
			dismissBtn.textContent = (cfg.i18n && cfg.i18n.later) || 'Later';
		}
		root.hidden = false;
	}

	if ('serviceWorker' in navigator && cfg.swUrl) {
		navigator.serviceWorker.register(cfg.swUrl).catch(function () {
			/* ignore */
		});
	}

	window.addEventListener('beforeinstallprompt', function (e) {
		e.preventDefault();
		deferredPrompt = e;
		showPrompt();
	});

	if (cfg.isIos) {
		var isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
		if (!isStandalone) {
			window.setTimeout(function () {
				showPrompt((cfg.i18n && cfg.i18n.iosHint) || '');
			}, 1800);
		}
	} else {
		window.setTimeout(function () {
			if (!deferredPrompt && !dismissed()) {
				showPrompt();
			}
		}, 2500);
	}

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
			if (cfg.isIos && msgEl) {
				msgEl.textContent = (cfg.i18n && cfg.i18n.iosHint) || msgEl.textContent;
				return;
			}
			dismiss();
		});
	}

	if (dismissBtn) {
		dismissBtn.addEventListener('click', dismiss);
	}
})();
