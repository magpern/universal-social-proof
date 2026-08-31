'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const api = require(path.join(__dirname, '../../assets/js/usp-toaster.js'));

const UUID_A = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
const UUID_B = 'bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee';
const UUID_C = 'cccccccc-bbbb-4ccc-8ddd-eeeeeeeeeeee';

function m2Event(overrides) {
	return Object.assign(
		{
			public_id: UUID_A,
			product_url: 'https://example.test/p/1',
			thumbnail_url: null,
			occurred_at: '2026-08-30T18:42:11Z',
		},
		overrides || {}
	);
}

function fixtureEvent(overrides) {
	return m2Event(Object.assign({ message: 'Someone purchased a product.' }, overrides || {}));
}

describe('canPresent', () => {
	it('is false without message', () => {
		assert.equal(api.canPresent(m2Event()), false);
		assert.equal(api.canPresent(m2Event({ message: null })), false);
		assert.equal(api.canPresent(m2Event({ message: '' })), false);
		assert.equal(api.canPresent(m2Event({ message: '   ' })), false);
	});

	it('is true with non-empty message', () => {
		assert.equal(api.canPresent(fixtureEvent()), true);
	});
});

describe('validateDto', () => {
	it('accepts valid M2 DTO without message', () => {
		const dto = api.validateDto(m2Event());
		assert.ok(dto);
		assert.equal(dto.public_id, UUID_A);
		assert.equal(dto.message, undefined);
	});

	it('rejects malformed fields', () => {
		assert.equal(api.validateDto(null), null);
		assert.equal(api.validateDto(m2Event({ public_id: 'nope' })), null);
		assert.equal(api.validateDto(m2Event({ product_url: 'javascript:alert(1)' })), null);
		assert.equal(api.validateDto(m2Event({ occurred_at: 'yesterday' })), null);
		assert.equal(api.validateDto(m2Event({ thumbnail_url: 'ftp://x' })), null);
	});

	it('keeps optional message string', () => {
		const dto = api.validateDto(fixtureEvent());
		assert.equal(dto.message, 'Someone purchased a product.');
	});

	it('ignores unknown fields without failing', () => {
		const dto = api.validateDto(m2Event({ country_code: 'SE', extra: 1 }));
		assert.ok(dto);
	});
});

describe('formatRelativeTime', () => {
	const i18n = {
		justNow: 'just now',
		minuteAgo: '%d minute ago',
		minutesAgo: '%d minutes ago',
		hourAgo: '%d hour ago',
		hoursAgo: '%d hours ago',
		dayAgo: '%d day ago',
		daysAgo: '%d days ago',
	};
	const base = Date.parse('2026-08-30T18:42:11Z');

	it('formats buckets', () => {
		assert.equal(api.formatRelativeTime('2026-08-30T18:42:11Z', i18n, base + 1000), 'just now');
		assert.equal(api.formatRelativeTime('2026-08-30T18:42:11Z', i18n, base + 120000), '2 minutes ago');
		assert.equal(api.formatRelativeTime('2026-08-30T18:42:11Z', i18n, base + 7200000), '2 hours ago');
		assert.equal(api.formatRelativeTime('2026-08-30T18:42:11Z', i18n, base + 2 * 86400000), '2 days ago');
	});

	it('rejects invalid and future skew', () => {
		assert.equal(api.formatRelativeTime('not-a-date', i18n, base), null);
		assert.equal(api.formatRelativeTime('2026-08-30T18:42:11Z', i18n, base - 200000), null);
		assert.equal(api.formatRelativeTime('2026-08-30T18:42:11Z', i18n, base + 40 * 86400000), null);
	});
});

describe('shown store', () => {
	it('seeds from sessionStorage and persists', () => {
		const mem = {};
		const storage = {
			getItem: (k) => (k in mem ? mem[k] : null),
			setItem: (k, v) => {
				mem[k] = String(v);
			},
		};
		storage.setItem('usp.v1.shown', JSON.stringify([UUID_A]));
		const store = api.createShownStore('usp.v1', storage);
		assert.equal(store.has(UUID_A), true);
		store.add(UUID_B);
		assert.equal(store.has(UUID_B), true);
		const parsed = JSON.parse(mem['usp.v1.shown']);
		assert.ok(parsed.includes(UUID_B));
	});

	it('continues when storage unavailable', () => {
		const store = api.createShownStore('usp.v1', null);
		store.add(UUID_A);
		assert.equal(store.has(UUID_A), true);
		assert.deepEqual(store.excludeForWire(), [UUID_A]);
	});

	it('tolerates corrupt storage', () => {
		const storage = {
			getItem: () => '{not-json',
			setItem: () => {
				throw new Error('quota');
			},
		};
		const store = api.createShownStore('usp.v1', storage);
		store.add(UUID_A);
		assert.equal(store.has(UUID_A), true);
	});

	it('caps retain at 100 and wire exclude at 20', () => {
		const store = api.createShownStore('usp.v1', null);
		for (let i = 0; i < 105; i++) {
			const id = `aaaaaaaa-bbbb-4ccc-8ddd-${String(i).padStart(12, '0')}`;
			store.add(id);
		}
		assert.equal(store.size(), api.MAX_SHOWN);
		assert.equal(store.excludeForWire().length, api.MAX_EXCLUDE);
	});
});

describe('filterBatch and >20 history', () => {
	it('filters older returned ids against full shown set', () => {
		const store = api.createShownStore('usp.v1', null);
		const ids = [];
		for (let i = 0; i < 25; i++) {
			const id = `aaaaaaaa-bbbb-4ccc-8ddd-${String(i).padStart(12, '0')}`;
			ids.push(id);
			store.add(id);
		}
		const oldest = ids[0];
		const filtered = api.filterBatch(
			[m2Event({ public_id: oldest }), m2Event({ public_id: UUID_B, message: 'x' })],
			store
		);
		assert.equal(filtered.length, 1);
		assert.equal(filtered[0].public_id, UUID_B);
		assert.ok(!store.excludeForWire().includes(oldest));
	});

	it('dedupes current batch', () => {
		const store = api.createShownStore('usp.v1', null);
		const filtered = api.filterBatch([m2Event(), m2Event(), m2Event({ public_id: UUID_B })], store);
		assert.equal(filtered.length, 2);
	});
});

describe('runtime inert M2 path', async () => {
	it('stops after first successful non-presentable batch', async () => {
		let fetches = 0;
		const fetchImpl = async () => {
			fetches += 1;
			return {
				ok: true,
				json: async () => [m2Event(), m2Event({ public_id: UUID_B })],
			};
		};
		const runtime = api.createRuntime({
			config: {
				restUrl: 'https://example.test/wp-json/universal-social-proof/v1/notifications',
				limit: 5,
				pageContext: 'unknown',
				maxBatches: 3,
				timing: { initialDelayMs: 0, visibleMs: 10, gapMs: 0, motionMs: 0 },
				storageKey: 'usp.v1',
				i18n: {},
			},
			document: null,
			storage: null,
			fetch: fetchImpl,
		});
		// Force fetch without DOM by calling internal path via start failure then manual fetch:
		// createRuntime.start needs DOM; call fetch through advance by using start after stubbing bind.
		runtime.start();
		assert.equal(runtime.getStoppedReason(), 'no-dom');

		const runtime2 = api.createRuntime({
			config: {
				restUrl: 'https://example.test/wp-json/universal-social-proof/v1/notifications',
				limit: 5,
				pageContext: 'unknown',
				maxBatches: 3,
				timing: { initialDelayMs: 0, visibleMs: 10, gapMs: 0, motionMs: 0 },
				storageKey: 'usp.v1',
				i18n: { justNow: 'just now', dismiss: 'Dismiss' },
			},
			document: makeDom(),
			storage: null,
			fetch: fetchImpl,
		});
		runtime2.start();
		await waitFor(() => runtime2.getState() === 'stopped');
		assert.equal(runtime2.getStoppedReason(), 'm2-inert');
		assert.equal(fetches, 1);
		assert.equal(runtime2.getBatches(), 1);
	});
});

describe('runtime presentable fixture path', async () => {
	it('shows fixture message and records shown', async () => {
		const fetchImpl = async () => ({
			ok: true,
			json: async () => [fixtureEvent({ occurred_at: new Date().toISOString().replace(/\.\d{3}Z$/, 'Z') })],
		});
		const runtime = api.createRuntime({
			config: {
				restUrl: 'https://example.test/wp-json/universal-social-proof/v1/notifications',
				limit: 5,
				pageContext: 'unknown',
				maxBatches: 3,
				timing: { initialDelayMs: 0, visibleMs: 50, gapMs: 0, motionMs: 0 },
				storageKey: 'usp.v1',
				i18n: { justNow: 'just now', dismiss: 'Dismiss notification' },
			},
			document: makeDom(),
			storage: null,
			fetch: fetchImpl,
		});
		runtime.start();
		await waitFor(() => runtime.getState() === 'showing' || runtime.getState() === 'stopped');
		assert.equal(runtime.getState(), 'showing');
		assert.equal(runtime.getShown().has(UUID_A), true);
		runtime.dismissCurrent();
		await waitFor(() => runtime.getState() !== 'showing');
		assert.ok(['hiding', 'waiting', 'loading', 'stopped'].includes(runtime.getState()));
	});

	it('network failure stops', async () => {
		const runtime = api.createRuntime({
			config: {
				restUrl: 'https://example.test/wp-json/universal-social-proof/v1/notifications',
				limit: 5,
				pageContext: 'unknown',
				maxBatches: 3,
				timing: { initialDelayMs: 0, visibleMs: 10, gapMs: 0, motionMs: 0 },
				storageKey: 'usp.v1',
				i18n: {},
			},
			document: makeDom(),
			storage: null,
			fetch: async () => {
				throw new Error('offline');
			},
		});
		runtime.start();
		await waitFor(() => runtime.getState() === 'stopped');
		assert.equal(runtime.getStoppedReason(), 'network');
	});
});

describe('buildRequestUrl', () => {
	it('includes exclude and PDP params', () => {
		const url = api.buildRequestUrl(
			{
				restUrl: 'https://example.test/wp-json/universal-social-proof/v1/notifications',
				limit: 5,
				pageContext: 'product',
				productId: 42,
			},
			[UUID_A]
		);
		assert.match(url, /limit=5/);
		assert.match(url, /page_context=product/);
		assert.match(url, /product_id=42/);
		assert.match(url, /exclude(%5B%5D|\[\])=/);
	});
});

function makeDom() {
	const root = {
		hidden: true,
		querySelector(sel) {
			return this._nodes[sel] || null;
		},
		_nodes: {},
	};
	const panel = {
		attrs: { 'aria-hidden': 'true' },
		classList: {
			_c: new Set(),
			add(c) {
				this._c.add(c);
			},
			remove(c) {
				this._c.delete(c);
			},
		},
		setAttribute(k, v) {
			this.attrs[k] = v;
		},
	};
	const link = { href: '#', hidden: true };
	const media = {
		_children: [],
		textContent: '',
		appendChild(n) {
			this._children.push(n);
		},
	};
	const messageEl = { textContent: '', hidden: true };
	const timeEl = { dateTime: '', textContent: '' };
	const dismissBtn = {
		hidden: true,
		attrs: {},
		listeners: {},
		setAttribute(k, v) {
			this.attrs[k] = v;
		},
		addEventListener(type, fn) {
			this.listeners[type] = fn;
		},
	};
	root._nodes = {
		'.usp-toaster__panel': panel,
		'.usp-toaster__link': link,
		'.usp-toaster__media': media,
		'.usp-toaster__message': messageEl,
		'.usp-toaster__time': timeEl,
		'.usp-toaster__dismiss': dismissBtn,
	};
	return {
		getElementById: (id) => (id === 'usp-toaster-root' ? root : null),
		querySelector: (sel) => (sel === '[data-usp-toaster]' ? root : null),
		createElement: (tag) => {
			const el = {
				tag,
				attrs: {},
				listeners: {},
				setAttribute(k, v) {
					this.attrs[k] = v;
				},
				addEventListener(type, fn) {
					this.listeners[type] = fn;
				},
			};
			Object.defineProperty(el, 'src', {
				set(v) {
					this._src = v;
				},
				get() {
					return this._src;
				},
			});
			return el;
		},
		addEventListener() {},
	};
}

function waitFor(pred, timeoutMs = 1000) {
	const start = Date.now();
	return new Promise((resolve, reject) => {
		const tick = () => {
			if (pred()) {
				resolve();
				return;
			}
			if (Date.now() - start > timeoutMs) {
				reject(new Error('timeout waiting for condition'));
				return;
			}
			setTimeout(tick, 10);
		};
		tick();
	});
}
