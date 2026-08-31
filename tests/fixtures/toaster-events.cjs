/**
 * Visual harness fixtures (test-only — not loaded by production PHP).
 *
 * Synthetic presentation inputs with message for renderer verification.
 * Not commerce events; not injected into REST, DB, or wp_localize_script.
 *
 * @package UniversalSocialProof
 */

'use strict';

module.exports = {
	withMessage: {
		public_id: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
		product_url: 'https://example.test/product/demo',
		thumbnail_url: 'https://example.test/thumb.jpg',
		occurred_at: new Date().toISOString().replace(/\.\d{3}Z$/, 'Z'),
		message: 'Someone purchased Demo Product.',
	},
	withoutThumbnail: {
		public_id: 'bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee',
		product_url: 'https://example.test/product/demo-2',
		thumbnail_url: null,
		occurred_at: new Date().toISOString().replace(/\.\d{3}Z$/, 'Z'),
		message: 'Someone purchased Another Product.',
	},
	m2Only: {
		public_id: 'cccccccc-bbbb-4ccc-8ddd-eeeeeeeeeeee',
		product_url: 'https://example.test/product/demo-3',
		thumbnail_url: null,
		occurred_at: new Date().toISOString().replace(/\.\d{3}Z$/, 'Z'),
	},
};
