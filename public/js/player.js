(function () {
	'use strict';

	var cfg = window.seyedcastPlayer || {};
	var storageKey = cfg.storageKey || 'seyedcast_player_state_v1';
	var historyKey = cfg.historyKey || 'seyedcast_listen_history_v1';
	var listenerKey = cfg.listenerKey || 'seyedcast_listener_id_v1';
	var progressKey = cfg.progressKey || 'seyedcast_episode_progress_v1';
	var ajaxUrl = cfg.ajaxUrl || '';
	var progressAction = cfg.progressAction || 'seyedcast_listen_progress';
	var root = document.getElementById('seyedcast-sticky-player');
	var audio = document.getElementById('seyedcast-audio');
	if (!root || !audio) {
		return;
	}

	audio.setAttribute('playsinline', '');
	audio.setAttribute('webkit-playsinline', '');

	var els = {
		cover: root.querySelector('.seyedcast-sticky-player__cover'),
		titleText: root.querySelector('[data-role="title-text"]'),
		show: root.querySelector('.seyedcast-sticky-player__show'),
		range: root.querySelector('[data-action="seek"]'),
		scrubTop: root.querySelector('[data-action="seek-top"]'),
		fill: root.querySelector('[data-role="fill"]'),
		current: root.querySelector('[data-role="current"]'),
		duration: root.querySelector('[data-role="duration"]'),
		currentMobile: root.querySelector('[data-role="current-mobile"]'),
		durationMobile: root.querySelector('[data-role="duration-mobile"]'),
		playIcon: root.querySelector('.seyedcast-icon-play'),
		pauseIcon: root.querySelector('.seyedcast-icon-pause'),
		speeds: root.querySelectorAll('[data-action="speed"]')
	};

	var state = {
		id: 0,
		show_id: 0,
		title: '',
		show: '',
		audio: '',
		cover: '',
		permalink: '',
		position: 0,
		duration: 0,
		rate: 1,
		playing: false
	};

	var lastSentPct = 0;
	var lastSentEpisodeId = 0;
	var progressTimer = null;

	function formatTime(sec) {
		if (!isFinite(sec) || sec < 0) {
			return '0:00';
		}
		sec = Math.floor(sec);
		var h = Math.floor(sec / 3600);
		var m = Math.floor((sec % 3600) / 60);
		var s = sec % 60;
		var mm = h > 0 ? String(m).padStart(2, '0') : String(m);
		var ss = String(s).padStart(2, '0');
		return h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss;
	}

	function save() {
		try {
			var position = audio.currentTime || state.position || 0;
			var duration = isFinite(audio.duration) ? audio.duration : state.duration || 0;
			localStorage.setItem(
				storageKey,
				JSON.stringify({
					id: state.id,
					show_id: state.show_id || 0,
					title: state.title,
					show: state.show,
					audio: state.audio,
					cover: state.cover,
					permalink: state.permalink,
					position: position,
					duration: duration,
					rate: audio.playbackRate || state.rate || 1,
					playing: !audio.paused
				})
			);
			saveEpisodeProgress(state.id, position, duration);
		} catch (e) {
			/* ignore */
		}
	}

	function saveEpisodeProgress(episodeId, position, duration) {
		episodeId = parseInt(episodeId, 10) || 0;
		if (!episodeId) {
			return;
		}
		var pct = 0;
		if (duration > 0 && isFinite(duration)) {
			pct = Math.min(100, Math.max(0, Math.round((position / duration) * 100)));
		}
		try {
			var map = {};
			var raw = localStorage.getItem(progressKey);
			if (raw) {
				map = JSON.parse(raw) || {};
			}
			if (typeof map !== 'object' || Array.isArray(map)) {
				map = {};
			}
			if (pct < 1) {
				delete map[String(episodeId)];
			} else {
				map[String(episodeId)] = {
					pct: pct,
					position: position,
					duration: duration,
					updated: Date.now()
				};
			}
			// Cap map size.
			var keys = Object.keys(map);
			if (keys.length > 80) {
				keys
					.sort(function (a, b) {
						return (map[a].updated || 0) - (map[b].updated || 0);
					})
					.slice(0, keys.length - 80)
					.forEach(function (k) {
						delete map[k];
					});
			}
			localStorage.setItem(progressKey, JSON.stringify(map));
			document.dispatchEvent(
				new CustomEvent('seyedcast:progress', {
					detail: { id: episodeId, pct: pct, map: map }
				})
			);
		} catch (e) {
			/* ignore */
		}
	}

	function generateListenerId() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') {
			return window.crypto.randomUUID();
		}
		var hex = '';
		for (var i = 0; i < 32; i++) {
			hex += Math.floor(Math.random() * 16).toString(16);
		}
		return hex;
	}

	function getListenerId() {
		try {
			var id = localStorage.getItem(listenerKey);
			if (id && /^[a-f0-9-]{32,36}$/i.test(id)) {
				return id;
			}
			id = generateListenerId();
			localStorage.setItem(listenerKey, id);
			return id;
		} catch (e) {
			return generateListenerId();
		}
	}

	function currentPct() {
		if (!isFinite(audio.duration) || audio.duration <= 0) {
			return 0;
		}
		return Math.min(100, Math.max(0, Math.round((audio.currentTime / audio.duration) * 100)));
	}

	function sendProgress(force) {
		if (!ajaxUrl || !state.id) {
			return;
		}

		var pct = currentPct();
		if (!force && state.id === lastSentEpisodeId && pct <= lastSentPct) {
			return;
		}
		if (!force && pct < 1) {
			return;
		}

		lastSentPct = pct;
		lastSentEpisodeId = state.id;

		var params = new URLSearchParams();
		params.set('action', progressAction);
		params.set('episode_id', String(state.id));
		params.set('pct', String(pct));
		params.set('listener_id', getListenerId());

		var body = params.toString();

		if (force && navigator.sendBeacon) {
			var blob = new Blob([body], { type: 'application/x-www-form-urlencoded' });
			if (navigator.sendBeacon(ajaxUrl, blob)) {
				return;
			}
		}

		if (window.fetch) {
			window
				.fetch(ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body,
					credentials: 'same-origin',
					keepalive: true
				})
				.catch(function () {
					/* ignore */
				});
		}
	}

	function scheduleProgressSync() {
		if (progressTimer) {
			return;
		}
		progressTimer = window.setTimeout(function () {
			progressTimer = null;
			sendProgress(false);
		}, 20000);
	}

	function flushProgressSync(force) {
		if (progressTimer) {
			clearTimeout(progressTimer);
			progressTimer = null;
		}
		sendProgress(!!force);
	}

	function resetProgressSync(episodeId) {
		if (episodeId !== lastSentEpisodeId) {
			lastSentPct = 0;
			lastSentEpisodeId = 0;
		}
	}

	function pushListenHistory(showId) {
		showId = parseInt(showId, 10) || 0;
		if (!showId) {
			return;
		}
		try {
			var list = [];
			var raw = localStorage.getItem(historyKey);
			if (raw) {
				list = JSON.parse(raw);
			}
			if (!Array.isArray(list)) {
				list = [];
			}
			list = list.filter(function (id) {
				return parseInt(id, 10) !== showId;
			});
			list.unshift(showId);
			list = list.slice(0, 3);
			localStorage.setItem(historyKey, JSON.stringify(list));
			document.dispatchEvent(
				new CustomEvent('seyedcast:history', {
					detail: { ids: list }
				})
			);
		} catch (e) {
			/* ignore */
		}
	}

	function load() {
		try {
			var raw = localStorage.getItem(storageKey);
			if (!raw) {
				return null;
			}
			return JSON.parse(raw);
		} catch (e) {
			return null;
		}
	}

	function markActiveEpisode(playing) {
		var rows = document.querySelectorAll('.seyedcast-episode-row[data-episode-id]');
		rows.forEach(function (row) {
			var id = parseInt(row.getAttribute('data-episode-id'), 10);
			var isActive = state.id && id === state.id;
			row.classList.toggle('is-playing', !!isActive);
			var btn = row.querySelector('.seyedcast-play-btn');
			if (btn) {
				btn.classList.toggle('is-playing', !!(isActive && playing));
			}
		});
	}

	function setPlayingUi(playing) {
		if (els.playIcon) {
			els.playIcon.hidden = !!playing;
		}
		if (els.pauseIcon) {
			els.pauseIcon.hidden = !playing;
		}
		root.classList.toggle('is-playing', !!playing);
		document.body.classList.toggle('seyedcast-player-open', root.classList.contains('is-visible'));
		markActiveEpisode(playing);
		updateMediaSessionPlayback(playing);
	}

	function setProgress(pct) {
		pct = Math.max(0, Math.min(100, pct || 0));
		if (els.range) {
			els.range.value = String(pct);
		}
		if (els.scrubTop) {
			els.scrubTop.value = String(pct);
		}
		if (els.fill) {
			els.fill.style.width = pct + '%';
		}
	}

	function setTimes(current, duration) {
		var c = formatTime(current);
		var d = formatTime(duration);
		if (els.current) {
			els.current.textContent = c;
		}
		if (els.duration) {
			els.duration.textContent = d;
		}
		if (els.currentMobile) {
			els.currentMobile.textContent = c;
		}
		if (els.durationMobile) {
			els.durationMobile.textContent = d;
		}
	}

	function syncSpeedSelects(rate) {
		els.speeds.forEach(function (el) {
			el.value = String(rate || 1);
		});
	}

	function renderMeta() {
		if (els.cover) {
			els.cover.src = state.cover || '';
			els.cover.alt = state.title || '';
		}
		if (els.titleText) {
			els.titleText.textContent = state.title || '';
		}
		if (els.show) {
			els.show.textContent = state.show || '';
		}
		syncSpeedSelects(state.rate || 1);
		updateMediaSessionMetadata();
		root.querySelectorAll('[data-action="share"]').forEach(function (btn) {
			if (state.permalink) {
				btn.setAttribute('data-share-url', state.permalink);
				btn.setAttribute('data-share-title', state.title || '');
				btn.setAttribute('data-share-text', state.show || '');
				btn.hidden = false;
			} else {
				btn.hidden = true;
			}
		});
	}

	function showPlayer() {
		root.hidden = false;
		requestAnimationFrame(function () {
			root.classList.add('is-visible');
			document.body.classList.add('seyedcast-player-open');
		});
	}

	function hidePlayer() {
		root.classList.remove('is-visible', 'is-playing');
		document.body.classList.remove('seyedcast-player-open');
		audio.pause();
		state.playing = false;
		setPlayingUi(false);
		window.setTimeout(function () {
			if (!root.classList.contains('is-visible')) {
				root.hidden = true;
			}
		}, 400);
		try {
			localStorage.removeItem(storageKey);
		} catch (e) {
			/* ignore */
		}
		markActiveEpisode(false);
		if (navigator.mediaSession) {
			try {
				navigator.mediaSession.playbackState = 'none';
			} catch (e2) {
				/* ignore */
			}
		}
	}

	function updateMediaSessionMetadata() {
		if (!('mediaSession' in navigator)) {
			return;
		}
		try {
			navigator.mediaSession.metadata = new window.MediaMetadata({
				title: state.title || 'Seyedcast',
				artist: state.show || 'Seyedcast',
				album: 'Seyedcast',
				artwork: state.cover
					? [
							{ src: state.cover, sizes: '512x512', type: 'image/jpeg' },
							{ src: state.cover, sizes: '256x256', type: 'image/jpeg' }
					  ]
					: []
			});
		} catch (e) {
			/* ignore */
		}
	}

	function updateMediaSessionPlayback(playing) {
		if (!('mediaSession' in navigator)) {
			return;
		}
		try {
			navigator.mediaSession.playbackState = playing ? 'playing' : 'paused';
		} catch (e) {
			/* ignore */
		}
	}

	function setupMediaSession() {
		if (!('mediaSession' in navigator)) {
			return;
		}
		try {
			navigator.mediaSession.setActionHandler('play', function () {
				audio.play();
			});
			navigator.mediaSession.setActionHandler('pause', function () {
				audio.pause();
			});
			navigator.mediaSession.setActionHandler('seekbackward', function () {
				audio.currentTime = Math.max(0, audio.currentTime - 15);
			});
			navigator.mediaSession.setActionHandler('seekforward', function () {
				audio.currentTime = Math.min(audio.duration || audio.currentTime + 30, audio.currentTime + 30);
			});
			navigator.mediaSession.setActionHandler('stop', function () {
				hidePlayer();
			});
		} catch (e) {
			/* ignore */
		}
	}

	function playEpisode(payload, autoplay) {
		if (!payload || !payload.audio) {
			window.alert((cfg.i18n && cfg.i18n.noAudio) || 'No audio');
			return;
		}

		var same = state.audio === payload.audio && state.id === payload.id;
		var resumeAt = typeof payload.position === 'number' ? payload.position : null;
		state.id = payload.id || 0;
		state.show_id = payload.show_id || 0;
		state.title = payload.title || '';
		state.show = payload.show || '';
		state.cover = payload.cover || '';
		state.permalink = payload.permalink || '';
		state.audio = payload.audio;
		if (payload.rate) {
			state.rate = payload.rate;
		}

		renderMeta();
		showPlayer();
		markActiveEpisode(!audio.paused);
		pushListenHistory(state.show_id);

		if (!same) {
			audio.src = payload.audio;
			audio.load();
			setProgress(0);
			resetProgressSync(payload.id || 0);
		}

		audio.playbackRate = state.rate || 1;

		function maybeResumeAndPlay() {
			if (resumeAt !== null && isFinite(audio.duration) && resumeAt < audio.duration) {
				audio.currentTime = resumeAt;
				setProgress((resumeAt / audio.duration) * 100);
				setTimes(resumeAt, audio.duration);
			}
			if (autoplay !== false) {
				var p = audio.play();
				if (p && typeof p.catch === 'function') {
					p.catch(function () {
						setPlayingUi(false);
					});
				}
			}
		}

		if (resumeAt !== null && !same) {
			audio.addEventListener(
				'loadedmetadata',
				function onResumeMeta() {
					audio.removeEventListener('loadedmetadata', onResumeMeta);
					maybeResumeAndPlay();
				}
			);
		} else {
			maybeResumeAndPlay();
		}
		save();
	}

	function toggle() {
		if (!state.audio) {
			return;
		}
		if (audio.paused) {
			audio.play();
		} else {
			audio.pause();
		}
	}

	function seekFromRange(value) {
		if (!isFinite(audio.duration) || audio.duration <= 0) {
			return;
		}
		audio.currentTime = (parseFloat(value, 10) / 100) * audio.duration;
	}

	root.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-action]');
		if (!btn || btn.matches('input, select')) {
			return;
		}
		var action = btn.getAttribute('data-action');
		if (action === 'toggle') {
			toggle();
		} else if (action === 'seek-back') {
			audio.currentTime = Math.max(0, audio.currentTime - 15);
		} else if (action === 'seek-forward') {
			audio.currentTime = Math.min(audio.duration || audio.currentTime + 30, audio.currentTime + 30);
		} else if (action === 'close') {
			hidePlayer();
		} else if (action === 'share') {
			e.preventDefault();
			var shareUrl = btn.getAttribute('data-share-url') || state.permalink || '';
			if (!shareUrl) {
				return;
			}
			if (window.SeyedcastShare && typeof window.SeyedcastShare.share === 'function') {
				window.SeyedcastShare.share({
					url: shareUrl,
					title: btn.getAttribute('data-share-title') || state.title || '',
					text: btn.getAttribute('data-share-text') || state.show || '',
					button: btn
				});
			} else if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(shareUrl).catch(function () {});
			}
		}
	});

	function bindSeek(el) {
		if (!el) {
			return;
		}
		el.addEventListener('input', function () {
			seekFromRange(el.value);
			setProgress(parseFloat(el.value, 10));
		});
	}

	bindSeek(els.range);
	bindSeek(els.scrubTop);

	els.speeds.forEach(function (el) {
		el.addEventListener('change', function () {
			state.rate = parseFloat(el.value, 10) || 1;
			audio.playbackRate = state.rate;
			syncSpeedSelects(state.rate);
			save();
		});
	});

	audio.addEventListener('play', function () {
		state.playing = true;
		setPlayingUi(true);
		save();
	});

	audio.addEventListener('pause', function () {
		state.playing = false;
		setPlayingUi(false);
		save();
		flushProgressSync(true);
	});

	audio.addEventListener('timeupdate', function () {
		if (!isFinite(audio.duration) || audio.duration <= 0) {
			return;
		}
		var pct = (audio.currentTime / audio.duration) * 100;
		setProgress(pct);
		setTimes(audio.currentTime, audio.duration);
		scheduleProgressSync();
		if (!audio._seyedcastProgTick) {
			audio._seyedcastProgTick = true;
			window.setTimeout(function () {
				audio._seyedcastProgTick = false;
				if (state.id && isFinite(audio.duration) && audio.duration > 0) {
					saveEpisodeProgress(state.id, audio.currentTime, audio.duration);
				}
			}, 4000);
		}
	});

	audio.addEventListener('loadedmetadata', function () {
		setTimes(audio.currentTime || 0, audio.duration || 0);
	});

	audio.addEventListener('ended', function () {
		setPlayingUi(false);
		setProgress(100);
		save();
		flushProgressSync(true);
	});

	window.addEventListener('beforeunload', function () {
		save();
		flushProgressSync(true);
	});
	window.addEventListener('pagehide', function () {
		save();
		flushProgressSync(true);
	});

	document.addEventListener('click', function (e) {
		var trigger = e.target.closest('[data-seyedcast-play]');
		if (!trigger) {
			return;
		}
		e.preventDefault();
		var raw = trigger.getAttribute('data-seyedcast-play');
		if (!raw) {
			return;
		}
		try {
			var payload = JSON.parse(raw);
			if (state.id === payload.id && state.audio === payload.audio && !audio.paused) {
				audio.pause();
				return;
			}
			playEpisode(payload, true);
		} catch (err) {
			/* ignore */
		}
	});

	setupMediaSession();

	document.addEventListener('seyedcast:play', function (e) {
		if (e.detail) {
			playEpisode(e.detail, true);
		}
	});

	var saved = load();
	if (saved && saved.audio) {
		state = Object.assign(state, saved);
		renderMeta();
		showPlayer();
		audio.src = saved.audio;
		audio.playbackRate = saved.rate || 1;
		audio.addEventListener(
			'loadedmetadata',
			function onMeta() {
				audio.removeEventListener('loadedmetadata', onMeta);
				if (saved.position && saved.position < (audio.duration || Infinity)) {
					audio.currentTime = saved.position;
					setProgress((saved.position / audio.duration) * 100);
					setTimes(saved.position, audio.duration);
				}
				if (saved.playing) {
					audio.play().catch(function () {
						setPlayingUi(false);
					});
				} else {
					setPlayingUi(false);
				}
			}
		);
	}

	window.SeyedcastPlayer = {
		play: playEpisode,
		toggle: toggle,
		close: hidePlayer
	};
})();
