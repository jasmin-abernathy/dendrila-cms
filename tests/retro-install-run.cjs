/* Run with Node, Playwright, openssl, python3 + Pillow, and PHP 8.4 or newer. */
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const {strict: assert} = require('node:assert');
const {execFileSync, spawn} = require('node:child_process');
const {once} = require('node:events');
const fs = require('node:fs');
const http = require('node:http');
const https = require('node:https');
const os = require('node:os');
const path = require('node:path');

const php = process.env.PHP_BIN || 'php';
const python = process.env.PYTHON_BIN || 'python3';
const assets = process.argv[3];
const archive = path.resolve(process.argv[2] || 'downloads/Dendrila_Retro.zip');
const cwd = fs.mkdtempSync(path.join(os.tmpdir(), 'retro-http-'));
const web = path.join(cwd, 'web');
const themeSpecs = [
    ['canal57', 'portal-content', 'demo/', 'canal57'],
    ['borne-02', 'borne-02-content', 'demo/borne-02/', 'borne-02'],
    ['signal-96', 'signal-96-content', 'demo/signal-96/', 'signal-96'],
];
const password = 'Test-application-Retro-2026!';
const username = 'astro.admin';
const gif = {name: 'sample.gif', mimeType: 'image/gif', buffer: Buffer.from('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==', 'base64')};
const run = (bin, args) => execFileSync(bin, args, {encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe']});
const mkdir = directory => fs.mkdirSync(directory, {recursive: true, mode: 0o700});

function extract(name, zip = archive) {
    const directory = path.join(web, name);
    mkdir(directory);
    mkdir(path.join(cwd, 'private', name));
    run('unzip', ['-oq', zip, '-d', directory]);
    return path.join(directory, 'dendrila-retro');
}

function dataPath(name) {
    const home = path.join(cwd, 'private', name);
    if (!fs.existsSync(home)) return undefined;
    const dir = fs.readdirSync(home).find(item => item.startsWith('.dendrila-retro-'));
    return dir ? path.join(home, dir, 'appearance.json') : undefined;
}

function proofPath(name) {
    return path.join(cwd, 'private', name, 'dendrila-cle-' +
        require('node:crypto').createHash('sha256').update(path.join(web, name, 'dendrila-retro')).digest('hex').slice(0, 24) + '.txt');
}

async function stable(render, frame = false) {
    await render.evaluate(async (embedded) => {
        await document.fonts.ready;
        await Promise.all([...document.images].map(image => image.decode()));
        window.scrollTo(0,0);
        if (!embedded) await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    }, frame);
}

async function port() {
    const probe = http.createServer().listen(0, '127.0.0.1');
    await once(probe, 'listening');
    const value = probe.address().port;
    await new Promise(resolve => probe.close(resolve));
    return value;
}

function compare(paths) {
    run(python, ['-c', 'from PIL import Image,ImageChops; import sys\n'
        + 'a,b=map(lambda p:Image.open(p).convert("RGB"),sys.argv[1:]); '
        + 'assert a.size==b.size,(a.size,b.size); diff=ImageChops.difference(a,b).getbbox(); assert diff is None,diff', ...paths]);
}

async function start() {
    mkdir(web);
    mkdir(path.join(cwd, 'sessions'));
    for (const [id] of themeSpecs) extract(id);
    for (const id of ['locked', 'legacy-proof', 'race', 'precreated', 'insecure']) extract(id);
    const precreated = proofPath('precreated').match(/dendrila-cle-([a-f0-9]+)\.txt$/)[1];
    mkdir(path.join(cwd, 'private', 'precreated', '.dendrila-retro-' + precreated));
    fs.writeFileSync(proofPath('legacy-proof'), 'preuve incorrecte');
    const personal = path.join(cwd, 'personal.zip');
    run(python, ['bin/package-retro.py', personal, '--personal']);
    const personalKey = run('unzip', ['-p', personal, 'LIRE-MOI.txt']).match(/^([a-f0-9]{32})$/m)[1];
    const inner = path.join(cwd, 'inner.zip');
    fs.writeFileSync(inner, execFileSync('unzip', ['-p', personal, 'Site_a_envoyer.zip']));
    extract('personal', inner);
    const previewRoot = path.join(web, 'static');
    mkdir(previewRoot);
    run(python, ['bin/export-retro-demos.py', archive, previewRoot, '--source', 'test', '--php', php]);
    const router = path.join(cwd, 'router.php');
    fs.writeFileSync(router, '<?php\n'
        + '$site = explode("/", $_SERVER["REQUEST_URI"])[1];\n'
        + 'if (preg_match("/^[a-z0-9-]+$/D",$site)) {\n'
        + '    putenv("DENDRILA_RETRO_PRIVATE_HOME=" . ' + JSON.stringify(path.join(cwd, 'private') + '/') + ' . $site);\n'
        + '    putenv("DENDRILA_RETRO_REQUIRE_KEY=" . ($site === "locked" ? "1" : "0"));\n'
        + '}\nif ($site !== "insecure") $_SERVER["HTTPS"] = "on";\nreturn false;\n');

    run('openssl', ['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1', '-subj', '/CN=localhost',
        '-keyout', path.join(cwd, 'key.pem'), '-out', path.join(cwd, 'cert.pem')]);
    const phpPort = await port();
    const server = spawn(php, ['-d', 'session.save_path=' + path.join(cwd, 'sessions'), '-S', '127.0.0.1:' + phpPort, '-t', web, router],
        {detached: true, env: {...process.env, PHP_CLI_SERVER_WORKERS: '4'}, stdio: 'ignore'});
    const proxy = https.createServer({key: fs.readFileSync(path.join(cwd, 'key.pem')), cert: fs.readFileSync(path.join(cwd, 'cert.pem'))},
        (req, res) => {
            const upstream = http.request({hostname: '127.0.0.1', port: phpPort, method: req.method, path: req.url, headers: req.headers},
                incoming => { res.writeHead(incoming.statusCode, incoming.headers); incoming.pipe(res); });
            upstream.on('error', () => { res.writeHead(502); res.end(); });
            req.pipe(upstream);
        }).listen(0, '127.0.0.1');
    await once(proxy, 'listening');
    const root = 'https://127.0.0.1:' + proxy.address().port + '/';
    return {server, proxy, root, personalKey};
}

function fields(theme, identity, changes = {}) {
    return {
        action: 'Installer Dendrila Retro', theme, 'site[name]': identity.name, 'site[tagline]': identity.tagline,
        username, password, password_confirmation: password, ...changes,
    };
}

async function main() {
    const {server, proxy, root, personalKey} = await start();
    const options = {ignoreHTTPSErrors: true};
    let browser;
    const url = site => root + site + '/dendrila-retro/';
    const editor = site => url(site) + 'oldweb-editor.php';
    const releaseShots = assets && path.resolve(assets);
    if (releaseShots) mkdir(releaseShots);
    try {
        browser = await chromium.launch({executablePath: process.env.CHROMIUM_BIN || undefined, args: ['--no-sandbox', '--disable-gpu', '--force-device-scale-factor=1']});
        const context = await browser.newContext({...options, viewport: {width: 1120, height: 1000}});
        let ready;
        for (let i = 0; i < 30; i++) {
            ready = await context.request.get(root + 'static/retro-manifest.json');
            if (ready.ok()) break;
            await new Promise(resolve => setTimeout(resolve, 150));
        }
        assert(ready.ok(), 'Local HTTPS server did not start.');
        const manifest = await ready.json();
        const page = await context.newPage();
        const isolated = await browser.newContext(options);
        const token = p => p.locator('input[name=csrf]').inputValue();
        const post = (ctx, site, form, suffix = '') => ctx.request.post(editor(site) + suffix, {form, maxRedirects: 0});
        const action = async (p, name) => {
            const [response] = await Promise.all([p.waitForNavigation(), p.getByRole('button', {name, exact: true}).click()]);
            assert.equal(response.status(), 200);
        };

        assert.equal((await context.request.get(editor('insecure'))).status(), 403);
        assert.equal((await context.request.get(url('canal57') + 'retro/PortalStorage.php')).status(), 404);
        assert.equal((await context.request.get(url('canal57') + 'retro/portal-content.php')).status(), 404);
        assert.equal((await context.request.get(url('canal57') + 'retro/portal-content.json')).status(), 404);
        assert.equal((await context.request.get(url('canal57') + 'preview.php?theme=absent')).status(), 404);
        assert.equal((await context.request.post(url('canal57') + 'preview.php?theme=canal57')).status(), 405);
        assert.equal(dataPath('canal57'), undefined);

        for (const [id, content, demoPath, shot] of themeSpecs) {
            const identity = JSON.parse(fs.readFileSync('examples/oldweb/' + content + '.json', 'utf8'));
            const installer = await page.goto(editor(id));
            assert.equal(installer.headers()['x-frame-options'], 'DENY');
            assert.equal(await page.locator('#activation').count(), 0);
            await page.locator('#site-name').fill(identity.name);
            await page.locator('#site-tagline').fill(identity.tagline);
            await page.locator('#owner-username').fill(username);
            await page.locator('#owner-password').fill(password);
            await page.locator('#password-confirmation').fill(password);
            const previewUrls = [];
            const collect = request => { if (request.url().includes('preview.php')) previewUrls.push(request.url()); };
            page.on('request', collect);
            await page.locator('#theme').selectOption('borne-02');
            await page.locator('#theme').selectOption('signal-96');
            await page.locator('#theme').selectOption(id);
            await page.waitForFunction(theme => document.querySelector('iframe').contentWindow.location.search === '?theme=' + theme, id);
            page.off('request', collect);
            assert(previewUrls.length >= 2 && previewUrls.every(u => /^\?theme=(canal57|borne-02|signal-96)$/.test(new URL(u).search)));
            assert.equal(await page.locator('#owner-password').inputValue(), password);
            assert.equal(await page.locator('#site-name').inputValue(), identity.name);
            await page.locator('#site-name').fill('Mon club privé');
            assert.equal(await page.frameLocator('#theme-preview').locator('h1').textContent(), 'Mon club privé');
            await page.locator('#site-name').fill(identity.name);
            const preview = url(id) + 'preview.php?theme=' + id;
            await Promise.all([
                page.waitForEvent('framenavigated', {predicate: item => item.url() === preview}),
                page.locator('#theme').dispatchEvent('change'),
            ]);
            const responses = await ctxResponses(context, preview, root + 'static/' + demoPath, manifest.demos[id]);
            assert.equal(dataPath(id), undefined, 'Preview initialized a site.');

            const frame = page.frames().find(item => item.url() === preview);
            assert(frame, 'Install preview iframe did not load the selected theme.');
            const bounds = await frame.evaluate(() => [innerWidth, innerHeight]);
            const iframeImage = path.join(cwd, id + '-iframe.png');
            // Align the iframe to whole device pixels without changing its viewport.
            const frameElement = page.locator('#theme-preview');
            await frameElement.evaluate((node, width) => {
                Object.assign(node.style, {position:'fixed', top:'0', left:'0', width:width + 'px', zIndex:'1'});
            }, bounds[0]);
            await frame.waitForLoadState('load');
            await stable(frame, true);
            await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
            await frameElement.screenshot({path: iframeImage, animations: 'disabled'});
            await frameElement.evaluate(node => node.removeAttribute('style'));
            const bare = await context.newPage();
            await bare.setViewportSize({width: bounds[0], height: bounds[1]});
            await bare.goto(preview);
            await stable(bare);
            const bareImage = path.join(cwd, id + '-frame-compare.png');
            await bare.screenshot({path: bareImage, animations: 'disabled'});
            compare([iframeImage, bareImage]);

            const response = await post(context, id, fields(id, identity, {csrf: await token(page)}));
            assert.equal(response.status(), 303);
            assert.match(response.headers()['set-cookie'], /secure;.*HttpOnly;.*SameSite=Strict/i);
            const state = JSON.parse(fs.readFileSync(dataPath(id), 'utf8'));
            assert.equal(state.owner_username, username);
            assert(!JSON.stringify(state).includes(password));
            assert.equal(state.themes.published, id);
            assert.deepEqual(state.site, {name: identity.name, tagline: identity.tagline});
            assert.equal((await context.request.get(url(id))).status(), 200);
            assert.equal(await (await context.request.get(url(id))).text(), responses.preview);
            for (const width of [1024, 800]) {
                await bare.setViewportSize({width, height: 850});
                const screenshots = [];
                const documents = [preview, url(id), root + 'static/' + demoPath];
                for (const [index, address] of documents.entries()) {
                    await bare.goto(address);
                    await stable(bare);
                    const location = path.join(cwd, id + '-' + width + '-' + index + '.png');
                    await bare.screenshot({path: location, fullPage: true, animations: 'disabled'});
                    screenshots.push(location);
                }
                compare([screenshots[0], screenshots[1]]);
                compare([screenshots[0], screenshots[2]]);
                if (releaseShots && width === 1024) {
                    run(python, ['-c', 'from PIL import Image; import sys; img=Image.open(sys.argv[1]); img.thumbnail((880,1400),Image.Resampling.LANCZOS); img.save(sys.argv[2],quality=79,method=6)',
                        screenshots[2], path.join(releaseShots, shot + '.webp')]);
                }
            }
            console.log(id + ' : DOM, ressources, iframe et diff = 0 pixel à 1024 et 800 px.');
            await bare.close();

            await page.goto(editor(id));
            const bodyTheme = await page.locator('#page-mode').inputValue();
            assert.equal(bodyTheme, 'theme');
            await page.locator('#page-bg').fill(id === 'signal-96' ? '#FFFFFF' : '#182042');
            await page.locator('#page-mode').selectOption('color');
            await action(page, 'Publier');
            const live = await isolated.newPage();
            await live.goto(url(id));
            assert.equal(await live.locator('body').evaluate(el => getComputedStyle(el).backgroundImage), 'none');
            const fontTargets = id === 'signal-96'
                ? ['body', 'h1', '.menu a', 'a[name=bienvenue] + h2']
                : ['#site', '#enseigne', '#menu a', id === 'borne-02' ? '#ouverture h3' : '#ouverture h2'];
            const families = render => Promise.all(fontTargets.map(target =>
                render.locator(target).first().evaluate(el => getComputedStyle(el).fontFamily)));
            const originalFonts = await families(live);
            const downloadedFonts = [];
            for (const render of [page, live]) {
                render.on('request', request => {
                    if (request.resourceType() === 'font') downloadedFonts.push(request.url());
                });
            }
            for (const [zone, font] of [['page', 'tahoma'], ['title', 'impact'], ['menu', 'courier'], ['intro', 'times']]) {
                await page.locator('#' + zone + '-font').selectOption(font);
            }

            await page.locator('#page-mode').selectOption('image');
            await page.locator('#page-image').setInputFiles(gif);
            await page.locator('#intro-image').setInputFiles(gif);
            await page.locator('#intro-alt').fill('Illustration de test');
            await action(page, 'Enregistrer le brouillon');
            const draft = JSON.parse(fs.readFileSync(dataPath(id), 'utf8'));
            const media = draft.draft.page.image;
            assert.equal((await isolated.request.get(url(id) + '?media=' + media)).status(), 404);
            assert.equal((await isolated.request.get(editor(id) + '?preview=1&media=' + media)).status(), 403);
            await page.getByRole('link',{name:'Voir le brouillon', exact:true}).click();
            assert(await page.locator('img.illustration').first().evaluate(i => i.complete && i.naturalWidth > 0));
            assert.equal(await page.locator('body').evaluate(el => getComputedStyle(el).backgroundRepeat), 'repeat');
            const draftFonts = await families(page);
            for (const [index, family] of ['Tahoma', 'Impact', 'Courier New', 'Times New Roman'].entries()) {
                assert.equal(draftFonts[index].split(',')[0].replaceAll('"', ''), family);
            }
            await live.reload();
            assert.deepEqual(await families(live), originalFonts);
            await page.getByRole('link',{name:'Masquer les GIF'}).click();
            assert.equal(await page.locator('body').evaluate(el => getComputedStyle(el).backgroundImage), 'none');
            await page.goto(editor(id));
            await action(page, 'Publier');
            assert.equal((await isolated.request.get(url(id) + '?media=' + media)).status(), 200);
            await live.reload();
            assert.deepEqual(await families(live), draftFonts);
            await action(page, 'Rétablir la version précédente');
            await live.reload();
            assert.deepEqual(await families(live), originalFonts);
            assert.deepEqual(downloadedFonts, []);
            assert.equal(JSON.parse(fs.readFileSync(dataPath(id), 'utf8')).published.page.mode, 'color');
            await live.close();
        }

        const nojs = await browser.newContext({...options, javaScriptEnabled: false, viewport: {width: 390, height: 760}});
        const mobile = await nojs.newPage();
        await mobile.goto(editor('precreated'));
        await mobile.locator('#site-name').fill('Nom conservé');
        await mobile.getByRole('link', {name:'Voir Signal 96', exact:true}).click();
        await mobile.frameLocator('#theme-preview').getByRole('heading', {name: 'Signal 96', exact:true}).waitFor();
        assert.equal(await mobile.locator('#site-name').inputValue(), 'Nom conservé');
        assert(await mobile.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        assert(await mobile.locator('.preview-scroll').evaluate(el => el.scrollWidth > el.clientWidth));

        const demo = {name: 'Test du portail', tagline: 'Test'};
        let csrf = await token(mobile);
        assert.equal((await post(nojs, 'precreated', fields('canal57', demo, {csrf: 'invalid'}))).status(), 403);
        assert.equal((await post(nojs, 'precreated', fields('canal57', demo, {csrf,password:'trop court'}))).status(), 422);
        const mismatch = await post(nojs,'precreated',fields('signal-96',demo,{csrf,password_confirmation:'autre'}));
        assert.equal(mismatch.status(),422);
        assert.match(await mismatch.text(), /value="signal-96" selected/);
        assert(!(await mismatch.text()).includes(password));
        assert.equal((await post(nojs, 'precreated', fields('canal57', demo, {csrf}))).status(), 303);

        for (const name of ['locked', 'legacy-proof', 'personal']) {
            await page.goto(editor(name));
            csrf = await token(page);
            const values = fields('signal-96', demo, {csrf, mode: 'simple'});
            assert.equal((await post(context, name, values, '?mode=simple')).status(), 422);
            const activation = name === 'personal' ? personalKey : 'cle-fictive-de-test-longue-2026';
            if (name !== 'personal') {
                const proof = await post(context, name, {csrf, action:'Télécharger le fichier de clé', activation});
                assert.equal(proof.status(), 200);
                assert.match(await proof.text(), /^[a-f0-9]{64}\n$/);
                fs.writeFileSync(proofPath(name), await proof.text(), {mode: 0o600});
            }
            assert.equal((await post(context, name, {...values, activation:'mauvaise-cle-assez-longue-2026'})).status(), 422);
            assert.equal((await post(context, name, {...values, activation})).status(), 303);
        }

        const racers = await Promise.all([browser.newContext(options), browser.newContext(options)]);
        const raceTokens = await Promise.all(racers.map(async ctx => {
            const p = await ctx.newPage();
            await p.goto(editor('race'));
            return token(p);
        }));
        const outcomes = await Promise.all(racers.map((ctx, i) => post(ctx, 'race', fields('canal57', {name:'Concurrent ' + i,tagline:''}, {csrf:raceTokens[i]}))));
        const statuses = outcomes.map(r => r.status());
        assert.equal(statuses.filter(code => code === 303).length,1);
        assert(statuses.every(code => [303,403,409].includes(code)));
        assert.equal(JSON.parse(fs.readFileSync(dataPath('race'), 'utf8')).revision, 1);

        const log = await isolated.newPage();
        await log.goto(editor('canal57'));
        const loginToken = await log.locator('input[name=retro_token]').inputValue();
        const login = {retro_token:loginToken, action:'Se connecter', username, password};
        assert.equal((await post(isolated, 'canal57', {...login, username:'wrong'})).status(),403);
        assert.equal((await post(isolated, 'canal57', login)).status(),303);
        const stateFile = dataPath('precreated');
        const legacy = JSON.parse(fs.readFileSync(stateFile,'utf8'));
        delete legacy.owner_username;
        fs.writeFileSync(stateFile,JSON.stringify(legacy));
        await log.goto(editor('precreated'));
        assert.equal(await log.locator('#owner-username').count(),0);
        const legacyLogin = {...login, retro_token:await log.locator('input[name=retro_token]').inputValue()};
        delete legacyLogin.username;
        assert.equal((await post(isolated,'precreated',legacyLogin)).status(),303);
        const limited = await browser.newContext(options);
        const loginPage = await limited.newPage();
        await loginPage.goto(editor('personal'));
        const limitedLogin = {...login,retro_token:await loginPage.locator('input[name=retro_token]').inputValue(),password:'wrong'};
        for (let index=0; index<8; index++) assert.equal((await post(limited,'personal',limitedLogin)).status(),403);
        assert.equal((await post(limited,'personal',limitedLogin)).status(),429);
        console.log('HTTPS : parcours simple, clés protégées et anciennes, concurrence, CSRF, validations, connexion, rétablissement et limitation vérifiés.');
    } finally {
        if (browser) await browser.close();
        proxy.close();
        try { process.kill(-server.pid, 'SIGTERM'); } catch (error) { if (error.code !== 'ESRCH') throw error; }
    }
}

async function ctxResponses(context, previewUrl, demoUrl, expected) {
    const {createHash} = require('node:crypto');
    const sha = bytes => createHash('sha256').update(bytes).digest('hex');
    const [preview, demo] = await Promise.all([context.request.get(previewUrl), context.request.get(demoUrl)]);
    assert.equal(preview.headers()['x-frame-options'], 'SAMEORIGIN');
    assert.equal(await preview.text(), await demo.text());
    assert.equal(sha(await demo.body()), expected.html_sha256);
    for (const [relative, checksum] of Object.entries(expected.assets)) {
        const [one,two] = await Promise.all([context.request.get(new URL(relative,previewUrl).href),context.request.get(new URL(relative,demoUrl).href)]);
        assert(one.ok() && two.ok());
        assert.equal(sha(await one.body()), checksum);
        assert.equal(sha(await two.body()), checksum);
    }
    return {preview:await preview.text()};
}

main().catch(error => { console.error(error); process.exitCode = 1; }).finally(() => {
    if (!process.env.RETRO_KEEP_TEST_FILES) fs.rmSync(cwd,{recursive:true,force:true});
    else console.log('Dossier temporaire des tests : ' + cwd);
});
