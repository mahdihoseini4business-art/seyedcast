(function () {
	'use strict';

	var cfg = window.seyedcastPush || {};
	var root = document.getElementById('seyedcast-push-prompt');
	if (!root || !cfg.vapidPublic || !('serviceWorker' in navigator) || !('PushManager' in window)) {
		return;
	}

	var storageKey = cfg.storageKey || 'seyedcast_push_prompt_dismissed';
	var snoozeKey = cfg.snoozeKey || 'seyedcast_push_prompt_snooze';
	var subscribedKey = cfg.subscribedKey || 'seyedcast_push_subscribed';
	var snoozeDays = cfg.snoozeDays || 3;
	var titleEl = root.querySelector('.seyedcast-pwa-prompt__title');
	var msgEl = root.querySelector('.seyedcast-pwa-prompt__message');
	var iconEl = root.querySelector('.seyedcast-pwa-prompt__icon');
	var enableBtn = root.querySelector('[data-action="enable"]');
	var dismissBtn = root.querySelector('[data-action="dismiss-secondary"]');
	var closeBtn = root.querySelector('.seyedcast-pwa-prompt__close');
	var otherPromptOpen = false;
	var pendingShow = false;
	var shownOnce = false;

	function lsGet(key) {
		try {
			return localStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function lsSet(key, val) {
		try {
			localStorage.setItem(key, val);
		} catch (e) {
			/* ignore */
		}
	}

	function lsRemove(key) {
		try {
			localStorage.removeItem(key);
		} catch (e) {
			/* ignore */
		}
	}

	function hardDismissed() {
		return lsGet(storageKey) === '1';
	}

	function isSnoozed() {
		var until = parseInt(lsGet(snoozeKey) || '0', 10);
		return until > Date.now();
	}

	function alreadySubscribed() {
		return lsGet(subscribedKey) === '1';
	}

	function hidePrompt() {
		root.hidden = true;
		try {
			document.dispatchEvent(
				new CustomEvent('seyedcast:bottom-prompt', {
					detail: { source: 'push', open: false }
				})
			);
		} catch (e) {
			/* ignore */
		}
	}

	function snooze(days) {
		var ms = (days || snoozeDays) * 24 * 60 * 60 * 1000;
		lsSet(snoozeKey, String(Date.now() + ms));
		hidePrompt();
	}

	function hardDismiss() {
		lsSet(storageKey, '1');
		lsRemove(snoozeKey);
		hidePrompt();
	}

	function urlBase64ToUint8Array(base64String) {
		var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
		var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
		var raw = window.atob(base64);
		var output = new Uint8Array(raw.length);
		for (var i = 0; i < raw.length; i++) {
			output[i] = raw.charCodeAt(i);
		}
		return output;
	}

	function bindLabels(messageOverride) {
		if (titleEl) {
			titleEl.textContent = (cfg.i18n && cfg.i18n.title) || 'Notifications';
		}
		if (msgEl) {
			msgEl.textContent = messageOverride || (cfg.i18n && cfg.i18n.message) || '';
		}
		if (enableBtn) {
			enableBtn.textContent = (cfg.i18n && cfg.i18n.enable) || 'Enable';
		}
		if (dismissBtn) {
			dismissBtn.textContent = (cfg.i18n && cfg.i18n.later) || 'Later';
		}
		if (closeBtn) {
			var forever = (cfg.i18n && cfg.i18n.close) || 'دیگر نشان نده';
			closeBtn.setAttribute('aria-label', forever);
			closeBtn.setAttribute('title', forever);
		}
		if (iconEl && cfg.iconUrl) {
			iconEl.src = cfg.iconUrl;
			iconEl.alt = (cfg.i18n && cfg.i18n.title) || '';
		} else if (iconEl) {
			iconEl.hidden = true;
		}
	}

	function pwaPromptVisible() {
		var pwa = document.getElementById('seyedcast-pwa-prompt');
		return !!(pwa && !pwa.hidden);
	}

	function revealPrompt(messageOverride) {
		bindLabels(messageOverride);
		root.hidden = false;
		shownOnce = true;
		try {
			document.dispatchEvent(
				new CustomEvent('seyedcast:bottom-prompt', {
					detail: { source: 'push', open: true }
				})
			);
		} catch (e) {
			/* ignore */
		}
	}

	function showPrompt(messageOverride) {
		if (hardDismissed() || isSnoozed() || alreadySubscribed() || shownOnce) {
			return;
		}
		if (typeof Notification !== 'undefined' && Notification.permission === 'denied') {
			return;
		}
		if (otherPromptOpen || pwaPromptVisible()) {
			pendingShow = true;
			return;
		}
		if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
			ensureSubscription().then(function (ok) {
				if (!ok && !hardDismissed() && !isSnoozed() && !alreadySubscribed()) {
					if (otherPromptOpen || pwaPromptVisible()) {
						pendingShow = true;
						return;
					}
					revealPrompt(messageOverride);
				}
			});
			return;
		}
		revealPrompt(messageOverride);
	}

	function flushPending() {
		if (!pendingShow || otherPromptOpen || pwaPromptVisible()) {
			return;
		}
		pendingShow = false;
		window.setTimeout(function () {
			showPrompt();
		}, 600);
	}

	function postSubscription(subscription) {
		var body = new FormData();
		body.append('action', 'seyedcast_push_subscribe');
		body.append('nonce', cfg.nonce || '');
		body.append('subscription', JSON.stringify(subscription.toJSON ? subscription.toJSON() : subscription));

		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (res) {
			return res.json();
		}).then(function (json) {
			return !!(json && json.success);
		}).catch(function () {
			return false;
		});
	}

	function registerSw() {
		var opts = cfg.swScope ? { scope: cfg.swScope } : undefined;
		return navigator.serviceWorker.register(cfg.swUrl, opts).then(function () {
			return navigator.serviceWorker.ready;
		});
	}

	function ensureSubscription() {
		return registerSw().then(function (reg) {
			return reg.pushManager.getSubscription().then(function (existing) {
				if (existing) {
					return postSubscription(existing).then(function (ok) {
						if (ok) {
							lsSet(subscribedKey, '1');
							lsRemove(snoozeKey);
						}
						return ok;
					});
				}
				return reg.pushManager.subscribe({
					userVisibleOnly: true,
					applicationServerKey: urlBase64ToUint8Array(cfg.vapidPublic)
				}).then(function (sub) {
					return postSubscription(sub).then(function (ok) {
						if (ok) {
							lsSet(subscribedKey, '1');
							lsRemove(snoozeKey);
						}
						return ok;
					});
				});
			});
		}).catch(function () {
			return false;
		});
	}

	function enable() {
		if (!enableBtn) {
			return;
		}
		enableBtn.disabled = true;

		var permissionPromise;
		if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
			permissionPromise = Promise.resolve('granted');
		} else if (typeof Notification !== 'undefined' && Notification.requestPermission) {
			permissionPromise = Notification.requestPermission();
		} else {
			permissionPromise = Promise.resolve('denied');
		}

		permissionPromise.then(function (permission) {
			if (permission !== 'granted') {
				bindLabels((cfg.i18n && cfg.i18n.denied) || '');
				enableBtn.disabled = false;
				hardDismiss();
				return;
			}
			return ensureSubscription().then(function (ok) {
				enableBtn.disabled = false;
				if (ok) {
					bindLabels((cfg.i18n && cfg.i18n.success) || '');
					window.setTimeout(hardDismiss, 1200);
				} else {
					bindLabels((cfg.i18n && cfg.i18n.failed) || '');
					root.hidden = false;
				}
			});
		});
	}

	document.addEventListener('seyedcast:bottom-prompt', function (e) {
		var detail = e && e.detail ? e.detail : {};
		if (detail.source === 'push') {
			return;
		}
		otherPromptOpen = !!detail.open;
		if (!otherPromptOpen) {
			flushPending();
		} else if (!root.hidden) {
			hidePrompt();
			pendingShow = true;
			shownOnce = false;
		}
	});

	if (alreadySubscribed() || hardDismissed() || isSnoozed()) {
		if (alreadySubscribed() || (typeof Notification !== 'undefined' && Notification.permission === 'granted')) {
			ensureSubscription().catch(function () {
				/* ignore */
			});
		}
		return;
	}

	if (pwaPromptVisible()) {
		otherPromptOpen = true;
	}

	window.setTimeout(function () {
		showPrompt();
	}, cfg.delayMs || 8000);

	if (enableBtn) {
		enableBtn.addEventListener('click', enable);
	}
	if (dismissBtn) {
		dismissBtn.addEventListener('click', function () {
			snooze(snoozeDays);
		});
	}
	if (closeBtn) {
		closeBtn.addEventListener('click', hardDismiss);
	}
})();
