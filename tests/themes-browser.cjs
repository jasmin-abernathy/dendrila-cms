const fs=require('fs');
const cp=require('child_process');
const assert=require('assert');
module.exports=async function reviewThemes(browser,php){
 const original=fs.readFileSync('config/local.php');
 const styles={'simple-illustrated':'simple-illustrated','quiet-elegant':'quiet-elegant','image-forward':'image-centered','ultra-minimal':'very-minimal','graphic-rhythm':'dynamic-graphic',editorial:'editorial'};
 const page=await browser.newPage();const errors=[];const signatures=new Set();const structures=new Set();
 // The installer selected quiet-elegant. Previewing another kit must not change the installed site.
 await page.goto('http://127.0.0.1:8080/');
 const installedTheme=await page.locator('body').getAttribute('data-dendrila-theme');
 assert.equal(installedTheme,'quiet-elegant','Installation must persist the chosen theme');
 const installedCss=await page.locator('link[rel=stylesheet]').first().getAttribute('href');
 page.on('pageerror',e=>errors.push(e.message));
 page.on('response',r=>{if(r.status()>=400)errors.push(`${r.status()} ${r.url()}`)});
 await page.route('https://www.youtube-nocookie.com/**',route=>route.fulfill({body:'Test embed',contentType:'text/html'}));
 const config=(style,brand={})=>cp.execFileSync(php,['-r',`$c=require 'config/local.php';$c['DENDRILA_THEME']=$argv[1];$c['DENDRILA_BRAND_JSON']=$argv[2];file_put_contents('config/local.php',"<?php\\nreturn ".var_export($c,true).";\\n");`,style,JSON.stringify(brand)]);
 try{
  for(const [style,theme] of Object.entries(styles)){
   for(const width of [1280,390]){
    await page.setViewportSize({width,height:900});
    const empty=await page.request.get('http://127.0.0.1:8080/theme-preview.php?theme='+style+'&mode=empty');
    assert.equal(empty.status(),200,'Empty preview must work before database installation');
    const emptyHtml=await empty.text();
    assert(emptyHtml.includes('Bienvenue sur notre site.'),'Empty preview mirrors the installed starter text');
    assert(!emptyHtml.includes('class="d-gallery"')&&!emptyHtml.includes('class="d-card-grid"'),'Empty mode does not preview optional content');
    await page.goto('http://127.0.0.1:8080/theme-preview.php?theme='+style+'&mode=demo');
    assert.equal(await page.locator('body').getAttribute('data-dendrila-theme'),theme);
    await require('./accessibility-browser.cjs')(page);
    assert(!/blog|actualités|lorem ipsum/i.test(await page.locator('body').innerText()),'No blog feed imposed by a theme');
    if(width===1280)structures.add(await page.locator('main > section, .d-main-content > section').evaluateAll(sections=>sections.map(s=>s.className).join('|')));
    const css=await page.locator('link[rel=stylesheet]').first().getAttribute('href');assert(css.startsWith('/design-kits/themes/'+theme+'/theme.css?v='));
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`Theme ${style} fits ${width}px`);
    const buttonContrasts=await page.locator('.d-button').evaluateAll(buttons=>buttons.map(button=>{
     const css=getComputedStyle(button);
     const luminance=color=>{const rgb=color.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4});return .2126*rgb[0]+.7152*rgb[1]+.0722*rgb[2]};
     const a=luminance(css.color),b=luminance(css.backgroundColor);return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);
    }));
    assert(buttonContrasts.every(ratio=>ratio>=4.5),`Theme ${style} button text meets AA: ${buttonContrasts}`);
    if(style==='graphic-rhythm'&&width===390)assert.equal(await page.locator('.d-hero').evaluate(el=>getComputedStyle(el,'::before').display),'none','Mobile decoration stays away from title');
    for(const img of await page.locator('img').all())await img.scrollIntoViewIfNeeded();
    await page.locator('img').evaluateAll(images=>Promise.all(images.map(img=>img.decode())));
    await page.evaluate(()=>scrollTo(0,0));
    assert(await page.locator('img').evaluateAll(images=>images.every(i=>i.complete&&i.naturalWidth>0)),`Theme ${style} images loaded`);
    if(width===1280)signatures.add(await page.evaluate(()=>[getComputedStyle(document.body).backgroundColor,getComputedStyle(document.querySelector('h1')).fontFamily,getComputedStyle(document.querySelector('h1')).fontSize].join('|')));
    await page.screenshot({path:`build/test-artifacts/theme-${style}-${width}.png`,fullPage:true});
    await page.goto('http://127.0.0.1:8080/');
    assert.equal(await page.locator('body').getAttribute('data-dendrila-theme'),installedTheme,'Previewing a kit must not replace the installed theme');
    assert.equal(await page.locator('link[rel=stylesheet]').first().getAttribute('href'),installedCss,'Installed CSS must remain stable');
    if(style==='quiet-elegant')assert.equal(installedCss,css,'Chosen theme preview and installed CSS must match');
    await page.emulateMedia({reducedMotion:'reduce'});
    assert(await page.evaluate(()=>matchMedia('(prefers-reduced-motion: reduce)').matches));
   }
  }
  assert(structures.size===6,'Six materially different demo structures');
  assert(signatures.size>=5,'Themes have distinct computed visual signatures');
  config('editorial',{primary:'#204060',body_font:'neutral-sans',logo_url:'https://brand.example/logo.svg'});
  // The PHP dev server retains its environment between requests. Generate the brand
  // variation in a fresh PHP process to exercise the updated private configuration.
  const brandedPreview=cp.execFileSync(php,['-r',`$_GET['theme']='editorial';require 'public/theme-preview.php';`],{encoding:'utf8'});
  await page.route('http://127.0.0.1:8080/theme-preview.php?theme=editorial',route=>route.fulfill({status:200,contentType:'text/html',body:brandedPreview}));
  await page.route('https://brand.example/logo.svg',route=>route.fulfill({body:fs.readFileSync('public/assets/preview.svg'),contentType:'image/svg+xml'}));
  await page.goto('http://127.0.0.1:8080/theme-preview.php?theme=editorial');
  assert.equal((await page.evaluate(()=>getComputedStyle(document.body).getPropertyValue('--dendrila-color-primary'))).trim(),'#204060');
  assert.equal(await page.locator('.d-brand img').getAttribute('src'),'https://brand.example/logo.svg');
  // A blocked storage API must not disable the controls or throw a page error.
  const blocked=await browser.newContext();
  await blocked.addInitScript(()=>Object.defineProperty(window,'localStorage',{get(){throw new DOMException('Blocked','SecurityError')}}));
  const noStorage=await blocked.newPage();noStorage.on('pageerror',e=>errors.push(e.message));
  await noStorage.goto('http://127.0.0.1:8080/theme-preview.php?theme=editorial&mode=demo');
  await noStorage.getByRole('button',{name:'Accessibilité',exact:true}).click();
  await noStorage.getByLabel('Taille du texte').selectOption('150');
  assert((await noStorage.locator('#d-reading-status').innerText()).includes('empêche'));
  assert.equal(await noStorage.locator('html').getAttribute('data-reading-size'),'150');
  await blocked.close();
  assert.deepEqual(errors,[]);
  console.log('Themes browser: six HTML empty and demo previews at 1280/390px, local assets, preview/site parity and brand overrides passed.');
 }finally{fs.writeFileSync('config/local.php',original);await page.close();}
};


