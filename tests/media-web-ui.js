'use strict';

const assert = require('node:assert/strict');
const {
    MediaApiClient,
    MediaApiError,
    MediaEditorSession,
    buildMediaQuery
} = require('../public/admin/media-library/media-library-core.js');

function fakeResponse(status, payload) {
    return {
        ok: status >= 200 && status < 300,
        status,
        async json() { return payload; }
    };
}

(async function () {
    const query = buildMediaQuery('media_library', {
        query: 'arbre',
        mime: 'image/png',
        usage: 'unused',
        alt: 'missing',
        created_from: '2026-10-01',
        created_to: '2026-10-08',
        min_bytes: 1000,
        max_bytes: 5000000,
        sort: 'title',
        direction: 'asc',
        limit: 24,
        offset: 48
    });
    const params = new URLSearchParams(query);
    assert.equal(params.get('module_id'), 'media_library');
    assert.equal(params.get('alt'), 'missing');
    assert.equal(params.get('sort'), 'title');
    assert.equal(params.get('offset'), '48');

    const calls = [];
    const client = new MediaApiClient({
        apiBase: '../../',
        documentUrl: 'https://example.test/admin/media-library/',
        token: 'a'.repeat(64),
        fetchImpl: async function (url, init) {
            calls.push({ url, init });
            if (url.includes('/v1/media?')) {
                return fakeResponse(200, {
                    items: [{ id: 'm_' + '1'.repeat(32), parent_id: 'media_library', revision: 4, title: 'Arbre' }],
                    total: 1, limit: 24, offset: 0, has_more: false, sort: 'date', direction: 'desc'
                });
            }
            if (url.endsWith('/v1/changes')) {
                return fakeResponse(200, { status: 'applied', result: { revision: 5 } });
            }
            if (url.endsWith('/v1/auth/start')) {
                return fakeResponse(200, { status: 'mfa_required', challenge_id: 'challenge_123456', methods: ['totp'], expires_in: 120 });
            }
            return fakeResponse(404, { error: { code: 'not_found', message: 'Missing' } });
        }
    });

    const page = await client.listMedia('media_library', { query: 'arbre', limit: 24, offset: 0 });
    assert.equal(page.total, 1);
    assert.equal(new URL(calls[0].url).pathname, '/v1/media');
    assert.equal(new URL(calls[0].url).searchParams.get('query'), 'arbre');
    assert.equal(calls[0].init.headers.get('X-Lepotager-Device-Token'), 'a'.repeat(64));
    assert.equal(calls[0].url.includes('a'.repeat(64)), false, 'Device token must never be placed in the URL.');

    const item = page.items[0];
    const fields = [
        { id: 'title', type: 'text', label: 'Titre' },
        { id: 'alt', type: 'multiline', label: 'Description alternative' }
    ];
    const editor = new MediaEditorSession(Object.assign({}, item, { alt: 'Ancien texte' }), fields);
    editor.setValue('alt', 'Texte local non enregistré');
    const payload = editor.payload();
    assert.equal(payload.expected_revision, 4);
    assert.equal(payload.alt, 'Texte local non enregistré');

    await client.change('media_library', 'update_item', payload, 'request-test-001');
    const changeBody = JSON.parse(calls[1].init.body);
    assert.equal(changeBody.payload.expected_revision, 4);
    assert.equal(changeBody.client_request_id, 'request-test-001');

    const beforeConflict = editor.values.alt;
    editor.markConflict(Object.assign({}, item, { revision: 5, alt: 'Texte serveur' }));
    assert.equal(editor.values.alt, beforeConflict, 'Conflict must preserve local input.');
    editor.rebaseLocalAfterReview();
    assert.equal(editor.expectedRevision, 5);
    assert.equal(editor.values.alt, beforeConflict, 'Explicit rebase keeps local input after review.');

    editor.markConflict(Object.assign({}, item, { revision: 6, alt: 'Valeur serveur retenue' }));
    editor.adoptServer();
    assert.equal(editor.expectedRevision, 6);
    assert.equal(editor.values.alt, 'Valeur serveur retenue');

    await client.startAuthentication('owner@example.test', 'secret', 'Navigateur web');
    assert.equal(calls[2].init.headers.get('X-Lepotager-Device-Token'), null,
        'Authentication request must not leak an existing device token.');

    const failing = new MediaApiClient({
        apiBase: '/',
        documentUrl: 'https://example.test/admin/media-library/',
        token: 'b'.repeat(64),
        fetchImpl: async function () {
            return fakeResponse(409, { error: { code: 'conflict', message: 'Révision obsolète.' } });
        }
    });
    await assert.rejects(
        () => failing.change('media_library', 'update_item', payload, 'request-test-002'),
        (error) => error instanceof MediaApiError && error.status === 409 && error.code === 'conflict'
    );

    console.log('Media web interface client and conflict contracts passed.');
})().catch(function (error) {
    console.error(error);
    process.exitCode = 1;
});
