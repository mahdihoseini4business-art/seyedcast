(function () {
	'use strict';

	var cfg = window.seyedcastPush || {};
	var root = document.getElementById('seyedcast-push-prompt');
	if (!root || !cfg.vapidPublic || !('serviceWorker' in navigator) || !('PushManager' in window)) {
		return;
	}

	var storageKey = cfg.storageKey || 'seyedcast_push_prompt_dismissed';
	var subscribedKey = cfg.subscribedKey || 'seyedcast_push_subscribed';
	var titleEl = root.querySelector('.seyedcast-pwa-prompt__title');
	var msgEl = root.querySelector('.seyedcast-pwa-prompt__message');
	var iconEl = root.querySelector('.seyedcast-pwa-prompt__icon');
	var enableBtn = root.querySelector('[data-action="enable"]');
	var dismissBtn = root.querySelector('[data-action="dismiss-secondary"]');
	var closeBtn = root.querySelector('.seyedcast-pwa-prompt__close');

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

	function dismissed() {
		return lsGet(storageKey) === '1';
	}

	function alreadySubscribed() {
		return lsGet(subscribedKey) === '1';
	}

	function dismiss() {
		lsSet(storageKey, '1');
		root.hidden = true;
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
			closeBtn.setAttribute('aria-label', (cfg.i18n && cfg.i18n.close) || 'Close');
		}
		if (iconEl && cfg.iconUrl) {
			iconEl.src = cfg.iconUrl;
			iconEl.alt = (cfg.i18n && cfg.i18n.title) || '';
		} else if (iconEl) {
			iconEl.hidden = true;
		}
	}

	function showPrompt(messageOverride) {
		if (dismissed() || alreadySubscribed()) {
			return;
		}
		if (typeof Notification !== 'undefined' && Notification.permission === 'denied') {
			return;
		}
		if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
			ensureSubscription().then(function (ok) {
				if (!ok) {
					bindLabels(messageOverride);
					root.hidden = false;
				}
			});
			return;
		}
		bindLabels(messageOverride);
		root.hidden = false;
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

	function ensureSubscription() {
		return navigator.serviceWorker.register(cfg.swUrl).then(function (reg) {
			return reg.pushManager.getSubscription().then(function (existing) {
				if (existing) {
					return postSubscription(existing).then(function (ok) {
						if (ok) {
							lsSet(subscribedKey, '1');
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
				lsSet(storageKey, '1');
				return;
			}
			return ensureSubscription().then(function (ok) {
				enableBtn.disabled = false;
				if (ok) {
					bindLabels((cfg.i18n && cfg.i18n.success) || '');
					window.setTimeout(dismiss, 1200);
				} else {
					enableBtn.disabled = false;
				}
			});
		});
	}

	if (alreadySubscribed() || dismissed()) {
		if (alreadySubscribed() || (typeof Notification !== 'undefined' && Notification.permission === 'granted')) {
			navigator.serviceWorker.register(cfg.swUrl).then(function () {
				return ensureSubscription();
			}).catch(function () {
				/* ignore */
			});
		}
		return;
	}

	window.setTimeout(function () {
		showPrompt();
	}, cfg.delayMs || 3500);

	if (enableBtn) {
		enableBtn.addEventListener('click', enable);
	}
	[dismissBtn, closeBtn].forEach(function (btn) {
		if (btn) {
			btn.addEventListener('click', dismiss);
		}
	});
})();
