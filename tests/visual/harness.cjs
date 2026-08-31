/**
 * Isolated visual harness for presentable toaster fixtures.
 *
 * Open via a local static server or `node tests/visual/render-fixture.cjs` if added.
 * Not enqueued by the plugin. Not reachable by anonymous storefront visitors.
 */
'use strict';

const fixtures = require('../fixtures/toaster-events.cjs');
const api = require('../../assets/js/usp-toaster.js');

module.exports = {
	fixtures,
	api,
	/**
	 * Render a fixture into a minimal DOM-like document using the same API.
	 *
	 * @param {object} doc Document stub from tests.
	 * @param {object} event Fixture event with message.
	 */
	renderFixture(doc, event) {
		const runtime = api.createRuntime({
			config: {
				restUrl: 'https://example.test/wp-json/universal-social-proof/v1/notifications',
				limit: 5,
				pageContext: 'unknown',
				maxBatches: 1,
				timing: { initialDelayMs: 0, visibleMs: 6000, gapMs: 0, motionMs: 0 },
				storageKey: 'usp.v1',
				i18n: { justNow: 'just now', dismiss: 'Dismiss notification' },
			},
			document: doc,
			storage: null,
			fetch: async () => ({ ok: true, json: async () => [] }),
		});
		return runtime._showEvent(api.validateDto(event));
	},
};
