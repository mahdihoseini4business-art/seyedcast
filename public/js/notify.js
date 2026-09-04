/**
 * Notify-me modal: open/close + AJAX submit.
 */
(function () {
	'use strict';

	var cfg = window.seyedcastNotify || {};
	var ajaxUrl = cfg.ajaxUrl || '';
	var action = cfg.action || 'seyedcast_notify_lead';
	var nonce = cfg.nonce || '';

	function t(key, fallback) {
		return (cfg.i18n && cfg.i18n[key]) || fallback;
	}

	function setMsg(form, text, isError) {
		var msg = form.querySelector('[data-seyedcast-notify-msg]');
		if (!msg) {
			return;
		}
		msg.hidden = !text;
		msg.textContent = text || '';
		msg.classList.toggle('is-error', !!isError);
		msg.classList.toggle('is-ok', !!text && !isError);
	}

	function openModal(root) {
		var modal = root.querySelector('[data-seyedcast-notify-modal]');
		if (!modal) {
			return;
		}
		modal.hidden = false;
		document.body.classList.add('seyedcast-notify-open');
		var input = modal.querySelector('input[name="name"]');
		if (input) {
			window.setTimeout(function () {
				input.focus();
			}, 30);
		}
	}

	function closeModal(root) {
		var modal = root.querySelector('[data-seyedcast-notify-modal]');
		if (!modal) {
			return;
		}
		modal.hidden = true;
		if (!document.querySelector('[data-seyedcast-notify-modal]:not([hidden])')) {
			document.body.classList.remove('seyedcast-notify-open');
		}
		var form = root.querySelector('[data-seyedcast-notify-form]');
		if (form) {
			setMsg(form, '', false);
		}
	}

	function submitForm(form, root) {
		if (!ajaxUrl) {
			return;
		}
		var nameInput = form.querySelector('input[name="name"]');
		var phoneInput = form.querySelector('input[name="phone"]');
		var showInput = form.querySelector('input[name="show_id"]');
		var btn = form.querySelector('button[type="submit"]');
		var name = nameInput ? nameInput.value.trim() : '';
		var phone = phoneInput ? phoneInput.value.trim() : '';
		var showId = showInput ? showInput.value : '0';

		if (name.length < 2) {
			setMsg(form, t('nameRequired', 'نام را وارد کنید.'), true);
			return;
		}
		if (!phone) {
			setMsg(form, t('phoneRequired', 'شماره موبایل را وارد کنید.'), true);
			return;
		}

		if (btn) {
			btn.disabled = true;
		}
		setMsg(form, t('sending', 'در حال ثبت…'), false);

		var body = new URLSearchParams();
		body.set('action', action);
		body.set('nonce', nonce);
		body.set('name', name);
		body.set('phone', phone);
		body.set('show_id', showId);

		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (res) {
				return res.json().then(function (json) {
					return { ok: res.ok, json: json };
				});
			})
			.then(function (result) {
				var json = result.json || {};
				if (json.success) {
					setMsg(form, (json.data && json.data.message) || t('success', 'ثبت شد.'), false);
					form.reset();
					window.setTimeout(function () {
						closeModal(root);
					}, 1400);
					return;
				}
				var err =
					(json.data && json.data.message) ||
					t('error', 'ثبت انجام نشد. دوباره تلاش کنید.');
				setMsg(form, err, true);
			})
			.catch(function () {
				setMsg(form, t('error', 'ثبت انجام نشد. دوباره تلاش کنید.'), true);
			})
			.finally(function () {
				if (btn) {
					btn.disabled = false;
				}
			});
	}

	function bindRoot(root) {
		if (!root || root._seyedcastNotifyBound) {
			return;
		}
		root._seyedcastNotifyBound = true;

		root.addEventListener('click', function (e) {
			if (e.target.closest('[data-seyedcast-notify-open]')) {
				e.preventDefault();
				openModal(root);
				return;
			}
			if (e.target.closest('[data-seyedcast-notify-close]')) {
				e.preventDefault();
				closeModal(root);
			}
		});

		var form = root.querySelector('[data-seyedcast-notify-form]');
		if (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				submitForm(form, root);
			});
		}
	}

	function boot(scope) {
		(scope || document).querySelectorAll('[data-seyedcast-notify]').forEach(bindRoot);
	}

	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') {
			return;
		}
		document.querySelectorAll('[data-seyedcast-notify-modal]:not([hidden])').forEach(function (modal) {
			var root = modal.closest('[data-seyedcast-notify]');
			if (root) {
				closeModal(root);
			}
		});
	});

	boot(document);
	document.addEventListener('seyedcast:navigated', function () {
		boot(document);
	});
})();
