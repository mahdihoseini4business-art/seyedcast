/**
 * Soft navigation within Seyedcast app shell (keeps sticky player alive).
 */
(function () {
	'use strict';

	var shell = document.getElementById('seyedcast-shell');
	if (!shell) {
		return;
	}

	var stage = document.getElementById('seyedcast-app-stage');
	var navigating = false;

	function sameDocumentUrl(a, b) {
		try {
			var left = new URL(a, window.location.href);
			var right = new URL(b, window.location.href);
			return left.origin === right.origin && left.pathname === right.pathname && left.search === right.search;
		} catch (e) {
			return false;
		}
	}

	function scrollToHash(url) {
		var hash = '';
		try {
			hash = new URL(url, window.location.href).hash;
		} catch (e) {
			hash = '';
		}
		if (!hash || hash === '#') {
			window.scrollTo(0, 0);
			return;
		}
		var id = decodeURIComponent(hash.slice(1));
		var target = document.getElementById(id) || document.querySelector('[name="' + id.replace(/"/g, '\\"') + '"]');
		if (target && typeof target.scrollIntoView === 'function') {
			target.scrollIntoView();
			return;
		}
		window.scrollTo(0, 0);
	}

	function shouldIntercept(anchor) {
		if (!anchor || anchor.target === '_blank' || anchor.hasAttribute('download')) {
			return false;
		}
		var hrefAttr = anchor.getAttribute('href');
		if (!hrefAttr || hrefAttr.charAt(0) === '#') {
			return false;
		}
		if (anchor.hasAttribute('data-seyedcast-external')) {
			return false;
		}
		try {
			var url = new URL(anchor.href, window.location.href);
			if (url.origin !== window.location.origin) {
				return false;
			}
			// Same path with only a hash change: let the browser handle it.
			if (sameDocumentUrl(url.href, window.location.href) && url.hash) {
				return false;
			}
		} catch (e) {
			return false;
		}
		if (anchor.hasAttribute('data-seyedcast-nav')) {
			return true;
		}
		if (stage && stage.contains(anchor)) {
			return true;
		}
		return false;
	}

	function setBusy(on) {
		shell.classList.toggle('is-navigating', !!on);
		if (!stage) {
			return;
		}
		if (on) {
			if (!document.getElementById('seyedcast-nav-skeleton')) {
				var sk = document.createElement('div');
				sk.id = 'seyedcast-nav-skeleton';
				sk.className = 'seyedcast-skeleton';
				sk.setAttribute('aria-hidden', 'true');
				sk.innerHTML =
					'<div class="seyedcast-skeleton__hero">' +
					'<div class="seyedcast-skeleton__block seyedcast-skeleton__cover"></div>' +
					'<div class="seyedcast-skeleton__lines">' +
					'<div class="seyedcast-skeleton__block seyedcast-skeleton__line seyedcast-skeleton__line--lg"></div>' +
					'<div class="seyedcast-skeleton__block seyedcast-skeleton__line"></div>' +
					'<div class="seyedcast-skeleton__block seyedcast-skeleton__line seyedcast-skeleton__line--sm"></div>' +
					'<div class="seyedcast-skeleton__actions">' +
					'<div class="seyedcast-skeleton__block seyedcast-skeleton__pill"></div>' +
					'<div class="seyedcast-skeleton__block seyedcast-skeleton__pill seyedcast-skeleton__pill--ghost"></div>' +
					'</div></div></div>' +
					'<div class="seyedcast-skeleton__grid">' +
					'<div class="seyedcast-skeleton__block seyedcast-skeleton__card"></div>' +
					'<div class="seyedcast-skeleton__block seyedcast-skeleton__card"></div>' +
					'<div class="seyedcast-skeleton__block seyedcast-skeleton__card"></div>' +
					'<div class="seyedcast-skeleton__block seyedcast-skeleton__card"></div>' +
					'</div>';
				stage.appendChild(sk);
			}
			stage.classList.add('is-skeleton');
		} else {
			stage.classList.remove('is-skeleton');
			var old = document.getElementById('seyedcast-nav-skeleton');
			if (old && old.parentNode) {
				old.parentNode.removeChild(old);
			}
		}
	}

	function syncHeaderActive(url) {
		var current;
		try {
			current = new URL(url, window.location.href);
		} catch (e) {
			return;
		}
		var path = current.pathname.replace(/\/$/, '') || '/';
		document.querySelectorAll('.seyedcast-topic-chip, .seyedcast-app-header__link').forEach(function (el) {
			try {
				var p = new URL(el.href, window.location.href).pathname.replace(/\/$/, '') || '/';
				el.classList.toggle('is-active', p === path);
			} catch (err) {
				el.classList.remove('is-active');
			}
		});
	}

	function swapStage(html, url) {
		var tmp = document.createElement('div');
		tmp.innerHTML = html.trim();
		var next = tmp.querySelector('#seyedcast-app-stage') || tmp.firstElementChild;
		if (!next || !stage) {
			window.location.href = url;
			return;
		}
		stage.replaceWith(next);
		stage = document.getElementById('seyedcast-app-stage');
		var title = stage && stage.getAttribute('data-title');
		if (title) {
			document.title = title;
		}
		syncHeaderActive(url);
		scrollToHash(url);
		document.dispatchEvent(new CustomEvent('seyedcast:navigated'));
	}

	function navigate(url, push) {
		if (navigating) {
			return;
		}

		// Same document + hash only: update history and scroll, no PJAX.
		if (sameDocumentUrl(url, window.location.href)) {
			var nextHash = '';
			try {
				nextHash = new URL(url, window.location.href).hash;
			} catch (e) {
				nextHash = '';
			}
			if (push && nextHash !== window.location.hash) {
				history.pushState({ seyedcast: true }, '', url);
			}
			scrollToHash(url);
			return;
		}

		navigating = true;
		setBusy(true);

		fetch(url, {
			headers: {
				'X-Seyedcast-Partial': '1',
				Accept: 'text/html'
			},
			credentials: 'same-origin'
		})
			.then(function (res) {
				if (!res.ok) {
					throw new Error('bad status');
				}
				return res.text();
			})
			.then(function (html) {
				swapStage(html, url);
				if (push) {
					history.pushState({ seyedcast: true }, '', url);
				}
			})
			.catch(function () {
				window.location.href = url;
			})
			.finally(function () {
				navigating = false;
				setBusy(false);
			});
	}

	document.addEventListener('click', function (e) {
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
			return;
		}
		var anchor = e.target.closest('a');
		if (!anchor || !shouldIntercept(anchor)) {
			return;
		}
		var href = anchor.href;
		if (!href) {
			return;
		}
		if (href === window.location.href) {
			e.preventDefault();
			scrollToHash(href);
			return;
		}
		e.preventDefault();
		navigate(href, true);
	});

	window.addEventListener('popstate', function () {
		navigate(window.location.href, false);
	});

	// Initial load with a hash (e.g. comment redirect).
	if (window.location.hash) {
		window.setTimeout(function () {
			scrollToHash(window.location.href);
		}, 0);
	}
})();
