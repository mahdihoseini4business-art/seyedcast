(function () {
	'use strict';

	var cfg = window.seyedcastPlayer || {};
	var storageKey = cfg.storageKey || 'seyedcast_player_state_v1';
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
		title: '',
		show: '',
		audio: '',
		cover: '',
		permalink: '',
		position: 0,
		rate: 1,
		playing: false
	};

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
			localStorage.setItem(
				storageKey,
				JSON.stringify({
					id: state.id,
					title: state.title,
					show: state.show,
					audio: state.audio,
					cover: state.cover,
					permalink: state.permalink,
					position: audio.currentTime || state.position || 0,
					rate: audio.playbackRate || state.rate || 1,
					playing: !audio.paused
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

		if (!same) {
			audio.src = payload.audio;
			audio.load();
			setProgress(0);
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
	});

	audio.addEventListener('timeupdate', function () {
		if (!isFinite(audio.duration) || audio.duration <= 0) {
			return;
		}
		var pct = (audio.currentTime / audio.duration) * 100;
		setProgress(pct);
		setTimes(audio.currentTime, audio.duration);
	});

	audio.addEventListener('loadedmetadata', function () {
		setTimes(audio.currentTime || 0, audio.duration || 0);
	});

	audio.addEventListener('ended', function () {
		setPlayingUi(false);
		setProgress(100);
		save();
	});

	window.addEventListener('beforeunload', save);
	window.addEventListener('pagehide', save);

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
