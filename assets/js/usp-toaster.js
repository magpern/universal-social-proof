/**
 * USP M3 toaster — single source (Node module.exports + browser boot).
 */
(function (root) {
	'use strict';

	var UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
	var OCCURRED_RE = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/;
	var MAX_SHOWN = 100;
	var MAX_EXCLUDE = 20;
	var FETCH_MS = 8000;

	function num(v, d) {
		return typeof v === 'number' && !isNaN(v) ? v : d;
	}

	function isHttpUrl(v) {
		if (typeof v !== 'string' || !v) return false;
		try {
			var u = new URL(v);
			return u.protocol === 'http:' || u.protocol === 'https:';
		} catch (e) {
			return false;
		}
	}

	function canPresent(e) {
		return !!(e && typeof e.message === 'string' && e.message.trim() !== '');
	}

	function validateDto(raw) {
		if (!raw || typeof raw !== 'object') return null;
		if (typeof raw.public_id !== 'string' || !UUID_RE.test(raw.public_id)) return null;
		if (typeof raw.product_url !== 'string' || !isHttpUrl(raw.product_url)) return null;
		var thumb = raw.thumbnail_url;
		if (thumb != null) {
			if (typeof thumb !== 'string' || !isHttpUrl(thumb)) return null;
		} else {
			thumb = null;
		}
		if (typeof raw.occurred_at !== 'string' || !OCCURRED_RE.test(raw.occurred_at)) return null;
		var out = {
			public_id: raw.public_id,
			product_url: raw.product_url,
			thumbnail_url: thumb,
			occurred_at: raw.occurred_at,
		};
		if (typeof raw.message === 'string') out.message = raw.message;
		return out;
	}

	function formatRelativeTime(occurredAt, i18n, nowMs) {
		i18n = i18n || {};
		var then = Date.parse(occurredAt);
		if (isNaN(then)) return null;
		var now = typeof nowMs === 'number' ? nowMs : Date.now();
		var delta = now - then;
		if (delta < -120000) return null;
		if (delta < 45000) return i18n.justNow || 'just now';
		var minutes = Math.floor(delta / 60000);
		if (minutes < 60) {
			var m = Math.max(1, minutes);
			var mt = m === 1 ? i18n.minuteAgo || i18n.minutesAgo : i18n.minutesAgo;
			return (mt || '%d minutes ago').replace('%d', String(m));
		}
		var hours = Math.floor(delta / 3600000);
		if (hours < 24) {
			var h = Math.max(1, hours);
			var ht = h === 1 ? i18n.hourAgo || i18n.hoursAgo : i18n.hoursAgo;
			return (ht || '%d hours ago').replace('%d', String(h));
		}
		var days = Math.floor(delta / 86400000);
		if (days < 30) {
			var d = Math.max(1, days);
			var dt = d === 1 ? i18n.dayAgo || i18n.daysAgo : i18n.daysAgo;
			return (dt || '%d days ago').replace('%d', String(d));
		}
		return null;
	}

	function createShownStore(storageKey, storage) {
		var order = [];
		var set = Object.create(null);
		var key = (storageKey || 'usp.v1') + '.shown';

		function persist() {
			if (!storage) return;
			try {
				storage.setItem(key, JSON.stringify(order.slice()));
			} catch (e) {}
		}

		if (storage) {
			try {
				var raw = storage.getItem(key);
				var parsed = raw ? JSON.parse(raw) : null;
				if (Array.isArray(parsed)) {
					for (var i = 0; i < parsed.length; i++) {
						var id = parsed[i];
						if (typeof id === 'string' && UUID_RE.test(id) && !set[id]) {
							set[id] = true;
							order.push(id);
						}
					}
					if (order.length > MAX_SHOWN) {
						order = order.slice(order.length - MAX_SHOWN);
						set = Object.create(null);
						for (var j = 0; j < order.length; j++) set[order[j]] = true;
					}
				}
			} catch (e) {}
		}

		return {
			has: function (id) {
				return !!set[id];
			},
			add: function (id) {
				if (typeof id !== 'string' || !UUID_RE.test(id)) return;
				if (set[id]) {
					var idx = order.indexOf(id);
					if (idx !== -1) order.splice(idx, 1);
				}
				set[id] = true;
				order.push(id);
				while (order.length > MAX_SHOWN) delete set[order.shift()];
				persist();
			},
			excludeForWire: function () {
				return order.slice(Math.max(0, order.length - MAX_EXCLUDE));
			},
			size: function () {
				return order.length;
			},
			list: function () {
				return order.slice();
			},
		};
	}

	function filterBatch(items, shown) {
		var seen = Object.create(null);
		var out = [];
		if (!Array.isArray(items)) return out;
		for (var i = 0; i < items.length; i++) {
			var dto = validateDto(items[i]);
			if (!dto || shown.has(dto.public_id) || seen[dto.public_id]) continue;
			seen[dto.public_id] = true;
			out.push(dto);
		}
		return out;
	}

	function classifyBatch(items) {
		var valid = 0;
		var presentable = 0;
		for (var i = 0; i < items.length; i++) {
			valid++;
			if (canPresent(items[i])) presentable++;
		}
		return { valid: valid, presentable: presentable };
	}

	function buildRequestUrl(config, excludeIds) {
		var url = new URL(config.restUrl, root.location ? root.location.href : 'https://example.test');
		url.searchParams.set('limit', String(config.limit || 5));
		url.searchParams.set('page_context', config.pageContext || 'unknown');
		if (config.pageContext === 'product' && config.productId) {
			url.searchParams.set('product_id', String(config.productId));
		}
		var list = excludeIds || [];
		for (var i = 0; i < list.length; i++) url.searchParams.append('exclude[]', list[i]);
		return url.toString();
	}

	function prefersReducedMotion(win) {
		win = win || root;
		try {
			return !!(win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches);
		} catch (e) {
			return false;
		}
	}

	function createRuntime(options) {
		options = options || {};
		var config = options.config || {};
		var doc = options.document || root.document || null;
		var storage = options.storage;
		if (storage === undefined) {
			try {
				storage = root.sessionStorage || null;
			} catch (e) {
				storage = null;
			}
		}
		var fetchImpl = options.fetch || (typeof root.fetch === 'function' ? root.fetch.bind(root) : null);
		var shown = createShownStore(config.storageKey, storage);
		var state = 'idle';
		var queue = [];
		var batches = 0;
		var timers = [];
		var abortCtrl = null;
		var current = null;
		var stoppedReason = null;
		var rootEl, panel, link, media, messageEl, timeEl, dismissBtn;

		function clearTimers() {
			for (var i = 0; i < timers.length; i++) clearTimeout(timers[i]);
			timers = [];
		}

		function later(fn, ms) {
			var id = setTimeout(fn, ms);
			timers.push(id);
			return id;
		}

		function stop(reason) {
			state = 'stopped';
			stoppedReason = reason || 'stopped';
			clearTimers();
			if (abortCtrl) {
				try {
					abortCtrl.abort();
				} catch (e) {}
				abortCtrl = null;
			}
			hidePanel(true);
		}

		function bindDom() {
			if (!doc) return false;
			rootEl = doc.querySelector('[data-usp-toaster]') || doc.getElementById('usp-toaster-root');
			if (!rootEl) return false;
			panel = rootEl.querySelector('.usp-toaster__panel');
			link = rootEl.querySelector('.usp-toaster__link');
			media = rootEl.querySelector('.usp-toaster__media');
			messageEl = rootEl.querySelector('.usp-toaster__message');
			timeEl = rootEl.querySelector('.usp-toaster__time');
			dismissBtn = rootEl.querySelector('.usp-toaster__dismiss');
			if (dismissBtn) {
				dismissBtn.setAttribute('aria-label', (config.i18n && config.i18n.dismiss) || 'Dismiss notification');
				dismissBtn.addEventListener('click', function (ev) {
					ev.preventDefault();
					ev.stopPropagation();
					dismissCurrent();
				});
			}
			if (doc.addEventListener) {
				doc.addEventListener('keydown', function (ev) {
					if (ev.key === 'Escape' && state === 'showing') dismissCurrent();
				});
			}
			return true;
		}

		function hidePanel(immediate) {
			if (!rootEl || !panel) return;
			panel.setAttribute('aria-hidden', 'true');
			panel.classList.remove('usp-toaster__panel--visible');
			function finish() {
				if (state === 'showing') return;
				rootEl.hidden = true;
				if (link) link.hidden = true;
				if (dismissBtn) dismissBtn.hidden = true;
			}
			if (immediate || prefersReducedMotion()) finish();
			else later(finish, num(config.timing && config.timing.motionMs, 280));
		}

		function renderEvent(event) {
			if (!link || !messageEl || !timeEl || !media) return false;
			var rel = formatRelativeTime(event.occurred_at, config.i18n);
			if (!rel) return false;
			link.href = event.product_url;
			link.hidden = false;
			messageEl.textContent = event.message.trim();
			messageEl.hidden = false;
			timeEl.dateTime = event.occurred_at;
			timeEl.textContent = rel;
			media.textContent = '';
			if (event.thumbnail_url) {
				var img = doc.createElement('img');
				img.src = event.thumbnail_url;
				img.width = 64;
				img.height = 64;
				img.alt = '';
				img.loading = 'lazy';
				img.addEventListener('error', function () {
					if (img.parentNode) img.parentNode.removeChild(img);
				});
				media.appendChild(img);
			}
			if (dismissBtn) dismissBtn.hidden = false;
			rootEl.hidden = false;
			panel.setAttribute('aria-hidden', 'false');
			panel.classList.add('usp-toaster__panel--visible');
			return true;
		}

		function dismissCurrent() {
			if (state !== 'showing' || !current) return;
			shown.add(current.public_id);
			current = null;
			state = 'hiding';
			hidePanel(prefersReducedMotion());
			later(function () {
				advance();
			}, prefersReducedMotion() ? 0 : num(config.timing && config.timing.gapMs, 2000));
		}

		function showNext() {
			while (queue.length) {
				var next = queue.shift();
				if (!canPresent(next) || shown.has(next.public_id)) continue;
				if (!renderEvent(next)) continue;
				current = next;
				shown.add(next.public_id);
				state = 'showing';
				later(function () {
					if (state !== 'showing' || current !== next) return;
					state = 'hiding';
					hidePanel(prefersReducedMotion());
					later(function () {
						advance();
					}, prefersReducedMotion() ? 0 : num(config.timing && config.timing.gapMs, 2000));
				}, num(config.timing && config.timing.visibleMs, 6000));
				return;
			}
			advance();
		}

		function advance() {
			current = null;
			if (queue.length) {
				showNext();
				return;
			}
			if (batches >= num(config.maxBatches, 3)) {
				stop('max-batches');
				return;
			}
			fetchBatch();
		}

		function fetchBatch() {
			if (!fetchImpl || !config.restUrl) {
				stop('no-fetch');
				return;
			}
			state = 'loading';
			batches += 1;
			abortCtrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
			var url = buildRequestUrl(config, shown.excludeForWire());
			var timedOut = false;
			var settled = false;
			var timer = setTimeout(function () {
				timedOut = true;
				if (!settled && abortCtrl) abortCtrl.abort();
			}, FETCH_MS);
			timers.push(timer);

			fetchImpl(url, {
				credentials: 'same-origin',
				headers: { Accept: 'application/json' },
				signal: abortCtrl ? abortCtrl.signal : undefined,
			})
				.then(function (res) {
					if (!res || !res.ok) throw new Error('http');
					return res.json();
				})
				.then(function (body) {
					settled = true;
					clearTimeout(timer);
					if (timedOut || state === 'stopped') return;
					var filtered = filterBatch(body, shown);
					var presentable = [];
					for (var i = 0; i < filtered.length; i++) {
						if (canPresent(filtered[i])) presentable.push(filtered[i]);
					}
					if (filtered.length > 0 && presentable.length === 0) {
						stop('m2-inert');
						return;
					}
					if (!presentable.length) {
						stop('empty');
						return;
					}
					queue = queue.concat(presentable);
					showNext();
				})
				.catch(function () {
					settled = true;
					clearTimeout(timer);
					if (state === 'stopped') return;
					stop(timedOut ? 'timeout' : 'network');
				});
		}

		function start() {
			if (!bindDom()) {
				stop('no-dom');
				return;
			}
			later(function () {
				fetchBatch();
			}, num(config.timing && config.timing.initialDelayMs, 3000));
		}

		return {
			start: start,
			stop: stop,
			dismissCurrent: dismissCurrent,
			getState: function () {
				return state;
			},
			getStoppedReason: function () {
				return stoppedReason;
			},
			getShown: function () {
				return shown;
			},
			getBatches: function () {
				return batches;
			},
			getQueueLength: function () {
				return queue.length;
			},
			_filterBatch: function (items) {
				return filterBatch(items, shown);
			},
			_showEvent: function (event) {
				if (!bindDom() || !canPresent(event) || !renderEvent(event)) return false;
				current = event;
				shown.add(event.public_id);
				state = 'showing';
				return true;
			},
		};
	}

	var api = {
		canPresent: canPresent,
		validateDto: validateDto,
		formatRelativeTime: formatRelativeTime,
		createShownStore: createShownStore,
		filterBatch: filterBatch,
		classifyBatch: classifyBatch,
		buildRequestUrl: buildRequestUrl,
		prefersReducedMotion: prefersReducedMotion,
		createRuntime: createRuntime,
		MAX_SHOWN: MAX_SHOWN,
		MAX_EXCLUDE: MAX_EXCLUDE,
		boot: function (config) {
			var runtime = createRuntime({ config: config || root.uspToaster || {} });
			if (root.document && root.document.readyState === 'loading') {
				root.document.addEventListener('DOMContentLoaded', function () {
					runtime.start();
				});
			} else {
				runtime.start();
			}
			return runtime;
		},
	};

	root.uspToasterApi = api;
	if (typeof module !== 'undefined' && module.exports) module.exports = api;
	else if (root.uspToaster && root.uspToaster.restUrl) api.boot(root.uspToaster);
})(typeof globalThis !== 'undefined' ? globalThis : this);
