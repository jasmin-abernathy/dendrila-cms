const { chromium } = require('playwright');
const fs=require('fs');const cp=require('child_process');const assert=require('assert');
const php=process.env.PHP_BINARY||'php';
(async()=>{
 const token=cp.execFileSync(php,['bin/prepare-install.php'],{encoding:'utf8'}).trim().split('\n').pop();
 fs.mkdirSync('build/test-artifacts',{recursive:true});
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_EXECUTABLE||undefined,headless:true,args:['--no-sandbox']});
 // Requests stay on loopback. A synthetic, DNS-shaped Host exercises HTTPS origin derivation.
 const context=await browser.newContext({viewport:{width:1280,height:900}});
 const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 try {
  await page.goto('http://127.0.0.1:8080/install.php');
  await require('./accessibility-browser.cjs')(page);
  const csrf=await page.locator('input[name=csrf]').inputValue();
  const bypass=await page.request.post('http://127.0.0.1:8080/install.php',{form:{csrf,back:'1'}});assert.equal(bypass.status(),422);
  await page.getByText('Option avancée : utiliser une clé de préparation',{exact:true}).click();
  await page.getByLabel('Clé de préparation (facultative)').fill(token);
  await page.getByRole('button',{name:'Continuer avec la clé'}).click();
  assert.equal(await page.locator('input[name=theme]').count(),6);
  // This is the real first-installation wizard, not a separate gallery.
  const themeCards=page.locator('.theme-card');
  assert.equal(await themeCards.count(),6,'Six theme cards are available before addons/database');
  assert((await page.locator('.setup-steps [aria-current=step]').textContent()).includes('Thème'),'Theme selection precedes addons');
  assert.equal(await page.locator('.theme-preview iframe').count(),6,'Each theme has an HTML iframe');
  assert.equal(await page.locator('.theme-card__actions a').count(),12,'Each theme exposes empty/full HTML preview');
  for(const card of await themeCards.all()){
    const frameUrl=await card.locator('iframe').getAttribute('src');
    assert(frameUrl.includes('&mode=demo') || frameUrl.includes('&amp;mode=demo'),'The thumbnail must show the rich version');
    const emptyLink=await card.locator('a[href$="mode=empty"]').getAttribute('href');
    const demoLink=await card.locator('a[href$="mode=demo"]').getAttribute('href');
    assert(emptyLink.endsWith('mode=empty')&&demoLink.endsWith('mode=demo'));
    assert.equal(await card.locator('a[href$="mode=empty"]').getAttribute('target'),'_blank');
    assert.equal(await card.locator('a[href$="mode=demo"]').getAttribute('target'),'_blank');
    const empty=await page.request.get('http://127.0.0.1:8080'+emptyLink);
    const demo=await page.request.get('http://127.0.0.1:8080'+demoLink);
    assert.equal(empty.status(),200);assert.equal(demo.status(),200);
    assert((await empty.text()).includes('class="d-card-grid"'),'Starter preview includes real editable theme sections');
    const demoHtml=await demo.text();
    assert(demoHtml.includes('Démonstration')&&demoHtml.includes('class="d-button"'),'Thumbnail presents a usable site direction');
    assert(!/Lorem ipsum|Blog|Actualités/.test(demoHtml),'No generic blog feed in the theme chooser');
    if(!/ultra-minimal|graphic-rhythm/.test(demoLink))assert(demoHtml.includes('/assets/install-demo/'),'Visual directions use bundled CC0 photos');
  }
  await page.locator('input[value="editorial"]').check();
  const selectedBefore=await page.locator('input[name=theme]:checked').inputValue();
  await page.locator('.theme-card__actions a').first().evaluate(a=>a.focus());
  assert.equal(await page.locator('input[name=theme]:checked').inputValue(),selectedBefore,'Preview links never alter theme selection');

  await page.screenshot({path:'build/test-artifacts/install-themes-desktop.png',fullPage:true});
  await page.setViewportSize({width:390,height:844});
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No mobile overflow');
  await page.screenshot({path:'build/test-artifacts/install-themes-mobile.png',fullPage:true});
  await page.locator('input[value="quiet-elegant"]').check();await page.getByRole('button',{name:'Continuer'}).click();
  await page.locator('input[value=media]').check();await page.locator('input[value=forum]').check();
   const bookingChoice=page.locator('input[name="addons[]"][value="booking"]');
   assert(await bookingChoice.isEnabled(),'Booking is selectable in the real wizard');
   await bookingChoice.check();
   assert(await bookingChoice.isChecked(),'Booking request remains checked');
  assert(await page.getByText('Formulaire classique uniquement').count(),'Classic contact is offered');
  assert(await page.locator('input[name=guided_journey][value=none]').isChecked(),'Guided journey is not imposed');
  await page.getByRole('button',{name:'Continuer'}).click();
  assert(await page.getByRole('heading',{name:'À quoi doit servir Booking ?'}).count(),'Booking mode step displayed');
  await page.locator('input[name=booking_mode][value=payments]').check();
  await page.getByRole('button',{name:'Continuer'}).click();
  assert(await page.getByText('Votre stockage SQLite sera créé automatiquement.').count(),'SQLite needs no external connection');
  assert.equal(await page.locator('input[name=host],input[name=url]').count(),0,'No SQL host or site URL must be entered');
  const secret=(await page.locator('code.secret').textContent()).trim();
  const code=(offset=0)=>cp.execFileSync(php,['-r',`require 'vendor/autoload.php';echo Dendrila\\Cms\\Auth\\Totp::code('${secret}',intdiv(time(),30)+${offset});`],{encoding:'utf8'}).trim();
  await page.getByLabel('Nom du site').fill('Atelier de démonstration');
  await page.getByLabel('Votre e-mail').fill('owner@atelier.example.test');await page.getByLabel('Votre mot de passe').fill('Browser-test-password-2026!');await page.getByLabel('Code actuel').fill(code());
  await page.getByRole('button',{name:'Installer mon site'}).click();
  assert(await page.getByRole('heading',{name:'Votre site est prêt'}).count(),'Private SQLite installation completes');
  assert(fs.existsSync('storage/site.sqlite'),'The installed database is private SQLite, not MariaDB');
  const settings=cp.execFileSync(php,['-r',`$c=require 'config/local.php';echo $c['APP_BASE_URL'];`],{encoding:'utf8'}).trim();
  assert.equal(settings,'https://atelier.example.test','HTTPS origin derived from current host, not entered by owner');
   const bookingState=cp.execFileSync(php,['-r',"$c=require 'config/local.php';echo $c['DENDRILA_BOOKING_SETUP_REQUESTED'].'|'.$c['DENDRILA_BOOKING_MODE'].'|'.$c['DENDRILA_BOOKING_STORAGE_DRIVER'].'|'.$c['DENDRILA_ADDONS'];"],{encoding:'utf8'}).trim();
   assert(bookingState.startsWith('1|payments|sqlite|')&&!bookingState.includes('|booking'),
     'Booking intent persists while payment routes remain inactive');
  await page.getByRole('link',{name:'Ouvrir l’administration'}).click();await page.getByLabel('E-mail',{exact:true}).fill('owner@atelier.example.test');await page.getByLabel('Mot de passe',{exact:true}).fill('Browser-test-password-2026!');await page.getByLabel('Code de votre application').fill(code(1));await page.getByRole('button',{name:'Se connecter'}).click();
  assert.equal(await page.locator('[data-page-editor]').count(),1,'Authenticated editor');
   await page.getByRole('link',{name:'Booking · à configurer'}).click();
   assert(await page.getByRole('heading',{name:'Booking : configuration en attente'}).count(),
     'Booking setup status remains explicit in back-office');
   assert(await page.getByText('Usage sélectionné : Paiements ponctuels.').count(),'Selected Booking mode visible');
   await page.goto('http://127.0.0.1:8080/admin/?section=textes');

  // A native SEO panel uses the existing account, not a second external tool.
  assert(await page.getByRole('link',{name:'SEO',exact:true}).count(),'SEO tab appears in back-office');
  await page.goto('http://127.0.0.1:8080/admin/seo.php');
  assert(await page.getByRole('heading',{name:'SEO du site'}).count(),'Native SEO page is available');
  assert(await page.getByText('Conseils de rédaction',{exact:true}).count(),'Local writing tips are shown');
  await page.locator('textarea[name=topics]').fill('atelier de démonstration\nconseils pratiques');
  await page.getByRole('button',{name:'Enregistrer les sujets'}).click();
  assert(await page.getByText('Enregistrement effectué.',{exact:false}).count(),'Site topics persist');
  await page.locator('textarea[name=page_topics]').fill('atelier de démonstration');
  await page.locator('input[name=seo_title]').fill('Atelier de démonstration — services');
  await page.locator('textarea[name=seo_description]').fill('Découvrez les services, les démarches et les informations pratiques de notre atelier de démonstration.');
  await page.getByRole('button',{name:'Enregistrer le SEO de cette page'}).click();
  assert(await page.getByText('Enregistrement effectué.',{exact:false}).count(),'Page metadata persists');
  const seoLive=await (await page.request.get('http://127.0.0.1:8080/')).text();
  assert(seoLive.includes('name="keywords" content="atelier de démonstration"'),'Published HTML contains selected SEO topics');
  assert(seoLive.includes('<title>Atelier de démonstration — services</title>'),'Published SEO title overrides default');
  assert(seoLive.includes('name="description"'),'Published meta description is server-rendered');
  await page.setViewportSize({width:390,height:844});
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),
    'SEO interface is readable at phone width');
  await page.screenshot({path:'build/test-artifacts/seo-mobile.png',fullPage:true});
  await page.setViewportSize({width:1280,height:900});


  await page.goto('http://127.0.0.1:8080/admin/appearance.php');
  assert(await page.getByRole('heading',{name:'Apparence du site'}).count(),'Owner can open appearance panel');
  assert.equal(await page.locator('[data-color-group]').count(),3,'Colors are grouped by use, not a flat field list');
  assert.equal(await page.locator('[data-color-key]').count(),9,'Nine existing theme tokens remain editable');
  assert.equal(await page.locator('[data-color-preview]').count(),1,'Live page preview is present');
  await page.locator('select[name=theme]').selectOption('editorial');
  await page.locator('input[name="color[primary]"]').fill('#9d2941');
  assert(await page.locator('input[name="enabled[]"][value="primary"]').isChecked(),
    'Changing the swatch activates the override automatically');
  assert.equal(await page.locator('[data-color-preview] .appearance-mini-button').evaluate(
    e=>getComputedStyle(e).backgroundColor),'rgb(157, 41, 65)',
    'Live preview updates button color before save');
  // First-party media and typography use file pickers, never a URL field.
  assert.equal(await page.locator('input[name=logo_url]').count(),0);
  assert.equal(await page.locator('input[name="asset_file[logo_url]"]').count(),1);
  const originalHeading=await page.locator('[data-font-target=heading_font] [data-font-sample]')
    .evaluate(el=>getComputedStyle(el).fontFamily);
  await page.locator('select[name=heading_font]').selectOption('editorial-serif');
  assert((await page.locator('[data-font-target=heading_font] [data-font-sample]')
    .evaluate(el=>getComputedStyle(el).fontFamily)).includes('Georgia'),'Font sample updates on selection');
  assert(originalHeading !== '', 'Initial font remains inspectable');
  const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFElEQVQImWOcq+nIAANMDEgANwcANL4BD1ttcPoAAAAASUVORK5CYII=','base64');
  await page.locator('.appearance-details summary').click();
  await page.locator('input[name="asset_file[logo_url]"]').setInputFiles({
    name:'brand-icon.png',mimeType:'image/png',buffer:png
  });
  assert(await page.locator('[data-brand-asset=logo_url] img').isVisible(),'Chosen logo is previewed');
  await page.getByRole('button',{name:'Enregistrer les changements'}).click();
  assert(await page.getByText('Apparence enregistrée.',{exact:false}).count(),'Successful appearance persistence');
  const branded=await (await page.request.get('http://127.0.0.1:8080/')).text();
  assert(branded.includes('data-dendrila-theme="editorial"')&&branded.includes('#9d2941'),'Public site uses saved theme and brand');
  await page.locator('input[name="enabled[]"][value="text"]').check();
  await page.locator('input[name="color[text]"]').fill('#ffffff');
  await page.locator('input[name="enabled[]"][value="background"]').check();
  await page.locator('input[name="color[background]"]').fill('#ffffff');
  await page.getByRole('button',{name:'Enregistrer les changements'}).click();
  assert(await page.getByRole('alert').count(),'WCAG contrast rejection is visible');
  const afterRejection=await (await page.request.get('http://127.0.0.1:8080/')).text();
  assert(afterRejection.includes('data-dendrila-theme="editorial"'),'Unsafe appearance rejected without public changes');
  await page.setViewportSize({width:390,height:844});
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Appearance mobile has no horizontal overflow');
  assert(await page.locator('[data-color-preview]').isVisible(),'Preview remains visible on mobile');
  await page.screenshot({path:'build/test-artifacts/appearance-mobile.png',fullPage:true});
  // Restore the originally installed theme before the independent six-theme parity suite.
  await page.locator('select[name=theme]').selectOption('quiet-elegant');
  await page.locator('input[name="enabled[]"][value="primary"]').uncheck();
  await page.locator('select[name=heading_font]').selectOption('');
  await page.locator('.appearance-details summary').click();
  await page.locator('input[name="remove_asset[]"][value=logo_url]').check();
  await page.getByRole('button',{name:'Enregistrer les changements'}).click();
  const reverted=await (await page.request.get('http://127.0.0.1:8080/')).text();
  assert(reverted.includes('data-dendrila-theme="quiet-elegant"'),'Appearance can revert without losing content');
  await page.goto('http://127.0.0.1:8080/admin/?section=textes');

  const introBlock=page.locator('.block-editor').first();
  await introBlock.locator('textarea[name="blocks[0][text]"]').fill('Texte modifié depuis le navigateur.');
  await introBlock.getByRole('button',{name:'Enregistrer ce bloc'}).click();
  const guest=await browser.newPage();await guest.goto('http://127.0.0.1:8080/');assert(!(await guest.textContent('body')).includes('Texte modifié depuis le navigateur.'));
  await page.getByRole('button',{name:'Publier le brouillon enregistré'}).click();await guest.reload();assert((await guest.textContent('body')).includes('Texte modifié depuis le navigateur.'));
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Admin has no mobile overflow');
  // The starter now seeds multiple editable blocks; appended blocks cannot assume indexes 1/2/3.
  const blockCount=()=>page.locator('.block-editor').count();
  const addBlock=async(type)=>{const index=await blockCount();await page.locator('select[name=type]').selectOption(type);await page.getByRole('button',{name:'Ajouter le bloc',exact:true}).click();return index};
  const rich=await addBlock('rich_text');
  const richInput=page.locator('textarea[name="blocks['+rich+'][text]"]');
  await richInput.fill('Texte complémentaire');
  await page.locator('form').filter({has:richInput}).getByRole('button',{name:'Enregistrer ce bloc'}).click();
  const faq=await addBlock('faq');
  const faqSection=page.locator('.block-editor').nth(faq);
  await faqSection.getByRole('button',{name:'Ajouter un élément',exact:true}).click();
  const q=page.locator('textarea[name="blocks['+faq+'][items][0][question]"]');
  await q.fill('Une question ?');
  await page.locator('textarea[name="blocks['+faq+'][items][0][answer]"]').fill('Une réponse.');
  await page.locator('form').filter({has:q}).getByRole('button',{name:'Enregistrer ce bloc'}).click();
  await page.locator('.block-editor').nth(faq).getByRole('button',{name:'Ajouter un élément',exact:true}).click();
  await page.locator('.block-editor').nth(faq).getByRole('button',{name:'Retirer l’élément 2',exact:true}).click();
  assert.equal(await page.locator('textarea[name^="blocks['+faq+'][items]"][name$="[question]"]').count(),1);
  const embed=await blockCount();
  await page.locator('select[name=type]').selectOption('embed_safe');
  await page.getByText('Options du contenu externe',{exact:true}).click();
  await page.locator('input[name="initial[url]"]').fill('https://www.youtube-nocookie.com/embed/example');
  await page.getByRole('button',{name:'Ajouter le bloc',exact:true}).click();
  const embedInput=page.locator('textarea[name="blocks['+embed+'][url]"]');
  assert.equal(await embedInput.inputValue(),'https://www.youtube-nocookie.com/embed/example');
  await page.locator('form').filter({has:embedInput}).getByRole('button',{name:'Enregistrer ce bloc'}).click();
  await page.getByRole('button',{name:'Publier le brouillon enregistré'}).click();
  await guest.route('https://www.youtube-nocookie.com/**',route=>route.fulfill({body:'External frame test',contentType:'text/html'}));await guest.goto('http://127.0.0.1:8080/');
  assert.equal(await guest.locator('iframe').getAttribute('src'),null,'External content waits for consent');
  await guest.locator('[data-dendrila-consent-action=reject-all]').click();await guest.getByRole('button',{name:'Autoriser ce contenu'}).click();
  assert.equal(await guest.locator('iframe').getAttribute('src'),'https://www.youtube-nocookie.com/embed/example');
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Collections do not overflow mobile');
  await require('./accessibility-browser.cjs')(page);
  await page.screenshot({path:'build/test-artifacts/admin-mobile.png',fullPage:true});
  await require('./themes-browser.cjs')(browser,php);
  await page.goto('http://127.0.0.1:8080/forum/');await page.getByLabel('Titre',{exact:true}).fill('Discussion de test');await page.getByLabel('Message',{exact:true}).fill('Message soumis à modération.');await page.getByRole('button',{name:'Envoyer pour validation'}).click();
  await guest.goto('http://127.0.0.1:8080/forum/');assert(!(await guest.textContent('body')).includes('Discussion de test'));
  await page.getByRole('button',{name:'Publier',exact:true}).click();await guest.reload();assert((await guest.textContent('body')).includes('Discussion de test'));
  const denied=await page.request.post('http://127.0.0.1:8080/admin/',{form:{action:'save_page',csrf:'bad'}});assert.equal(denied.status(),403);
  const locked=await page.request.get('http://127.0.0.1:8080/install.php');assert.equal(locked.status(),410);
  assert.deepEqual(errors,[]);console.log('Browser: wizard, six previews, mobile layout, TOTP login, edit/publish, moderation, CSRF and install lock passed.');
 } finally {await browser.close();if(fs.existsSync('config/local.php'))fs.unlinkSync('config/local.php');}
})().catch(e=>{console.error(e);process.exit(1)});

