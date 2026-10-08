'use strict';
// Optional local integration test: requires Playwright and its Chromium browser.
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../public');
const item = { id: 'm_' + '1'.repeat(32), parent_id: 'media_library', revision: 1,
    title: 'Arbre', alt: '', filename: 'arbre.png', bytes: 100,
    original_width: 1, original_height: 1, updated_at: '2026-10-08T00:00:00Z' };
const fields = [{ id: 'title', type: 'text', label: 'Titre' }, { id: 'alt', type: 'text', label: 'Description' }];
const config = { modules: [{ id: 'media_library', kind: 'media_library', fields,
    options: { allow_update: true, allow_rotate: true, allow_delete: true },
    media: { upload_enabled: true, accepted_mime_types: ['image/png'], max_bytes: 10000, fields } }] };
function deferred() { let resolve; const promise = new Promise(r => { resolve = r; }); return { promise, resolve }; }
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_EXECUTABLE || undefined });
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const calls = [];
        let mutationGate = null, uploadGate = null;
        let oldGate = null, oldSeen = null, oldDone = null;
        let uploadAttempts = 0;
        await page.route('https://media.test/**', async route => {
            const req = route.request();
            const url = new URL(req.url());
            const json = body => route.fulfill({ json: body });
            if (url.pathname === '/.well-known/lepotager-site-manager.json') {
                return json({
                    schema_version: 1,
                    site_id: 'pilot',
                    display_name: 'Site pilote',
                    api_base_url: 'https://media.test/',
                    auth_methods: ['pairing_code']
                });
            }
            if (url.pathname === '/v1/config') return json(config);
            if (url.pathname === '/v1/media' && req.method() === 'GET') {
                const query = url.searchParams.get('query');
                if (query === 'ancienne') { oldSeen.resolve(); await oldGate.promise; }
                await json({ items: [Object.assign({}, item, query ? { title: query } : {})], total: 1,
                    offset: 0, limit: 24, has_more: false });
                if (query === 'ancienne') oldDone.resolve();
                return;
            }
            if (url.pathname === '/v1/changes') {
                const body = req.postDataJSON(); calls.push(body);
                if (mutationGate) await mutationGate.promise;
                if (body.action === 'update_item') Object.assign(item, { title: body.payload.title, alt: body.payload.alt });
                item.revision++;
                return json({ status: 'applied' });
            }
            if (url.pathname === '/v1/media' && req.method() === 'POST') {
                calls.push({ upload: req.postData() });
                uploadAttempts++;
                if (uploadGate) await uploadGate.promise;
                if (uploadAttempts === 1) return route.abort('failed');
                return json({ status: 'applied' });
            }
            const relative = url.pathname === '/admin/media-library/' ? '/admin/media-library/index.html' : url.pathname;
            const file = path.join(root, relative);
            const type = file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : 'text/html';
            return route.fulfill({ body: await fs.readFile(file), contentType: type });
        });
        await page.addInitScript(() => sessionStorage.setItem('dendrila.media.device_token', 'test-only-token'));
        await page.goto('https://media.test/admin/media-library/');
        assert.equal(await page.locator('#password-panel').isHidden(), true);
        assert.equal(await page.locator('#pair-panel').isHidden(), false);
        await page.locator('.card-button').click();
        await page.locator('#edit-fields [data-field-id="title"]').fill('Saisie conservée');
        await page.locator('#rotate-right').click();
        assert.match(await page.locator('#edit-message').textContent(), /Enregistrez/);
        assert.equal(calls.length, 0, 'Rotation must not discard unsaved metadata.');
        mutationGate = deferred();
        await page.locator('#save-media').click();
        await page.waitForFunction(() => document.querySelector('#edit-form').getAttribute('aria-busy') === 'true');
        assert.equal(await page.locator('#edit-close').isDisabled(), true);
        assert.equal(await page.locator('#edit-fields input').first().isDisabled(), true);
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#edit-dialog').evaluate(d => d.open), true);
        mutationGate.resolve(); mutationGate = null;
        await page.waitForFunction(() => document.querySelector('#edit-form').getAttribute('aria-busy') === 'false');
        assert.equal(await page.locator('#edit-fields [data-field-id="title"]').inputValue(), 'Saisie conservée');
        await page.locator('#edit-close').click();
        assert.equal(await page.evaluate(() => document.activeElement.id), 'grid-view');
        // A slow earlier search must not replace the newer results.
        oldGate = deferred(); oldSeen = deferred(); oldDone = deferred();
        await page.locator('[name=query]').fill('ancienne');
        await page.locator('#filters button[type=submit]').click();
        await oldSeen.promise;
        await page.locator('[name=query]').fill('nouvelle');
        await page.locator('#filters button[type=submit]').click();
        await page.waitForFunction(() => document.querySelector('.media-title')?.textContent === 'nouvelle');
        oldGate.resolve(); await oldDone.promise;
        await page.evaluate(() => new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r))));
        assert.equal(await page.locator('.media-title').textContent(), 'nouvelle');
        // File, metadata and request identifier survive a failed transfer.
        await page.locator('#upload-open').click();
        await page.locator('#upload-file').setInputFiles({ name: 'test.png', mimeType: 'image/png',
            buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j5ioAAAAASUVORK5CYII=', 'base64') });
        uploadGate = deferred();
        await page.locator('#upload-submit').click();
        assert.equal(await page.locator('#upload-file').isDisabled(), true);
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#upload-dialog').evaluate(d => d.open), true);
        uploadGate.resolve(); uploadGate = null;
        await page.waitForFunction(() => document.querySelector('#upload-form').getAttribute('aria-busy') === 'false');
        assert.equal(await page.locator('#upload-file').evaluate(e => e.files.length), 1);
        await page.locator('#upload-submit').click();
        await page.waitForFunction(() => !document.querySelector('#upload-dialog').open);
        const uploads = calls.filter(c => c.upload).map(c => c.upload.match(/name="client_request_id"\r\n\r\n([^\r]+)/)[1]);
        assert.equal(uploads.length, 2);
        assert.equal(uploads[0], uploads[1]);
        for (const width of [390, 1280]) {
            await page.setViewportSize({ width, height: 900 });
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        }
        assert.deepEqual(errors, []);
        console.log('Browser regressions passed: manifest auth selection, input preservation, in-flight locks, search ordering, upload retry, focus and responsive widths.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
