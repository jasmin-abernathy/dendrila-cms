/* Local reading preferences. No content correction, tracking or remote requests. */
(() => {
 'use strict';
 if (document.getElementById('d-reading-tools') || typeof HTMLDialogElement === 'undefined') return;
 const en=document.documentElement.lang.startsWith('en');
 const t=(fr,eng)=>en?eng:fr;
 const key='dendrila.reading.v1';
 const defaults={size:'100',readable:false,spacing:false,motion:false,links:false};
 const clean=value=>({size:['100','125','150','200'].includes(value?.size)?value.size:'100',...Object.fromEntries(['readable','spacing','motion','links'].map(k=>[k,value?.[k]===true]))});
 let prefs={...defaults};
 try { prefs=clean(JSON.parse(localStorage.getItem(key))); } catch (_) {}
 const area=document.createElement('div');area.id='d-reading-tools';
 area.innerHTML=`<button type="button" id="d-reading-open" aria-haspopup="dialog" aria-controls="d-reading-dialog"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></svg>${t('Accessibilité','Accessibility')}</button>
 <dialog id="d-reading-dialog" aria-labelledby="d-reading-title" aria-describedby="d-reading-help">
 <h2 id="d-reading-title">${t('Confort de lecture','Reading preferences')}</h2>
 <button type="button" id="d-reading-close" autofocus>${t('Fermer','Close')}</button>
 <p id="d-reading-help">${t('Ces réglages s’appliquent uniquement à votre navigateur. Ils ne modifient pas le contenu du site.','These settings apply only to your browser. They do not change site content.')}</p>
 <label for="d-reading-size">${t('Taille du texte','Text size')}</label><select id="d-reading-size"><option value="100">100 %</option><option value="125">125 %</option><option value="150">150 %</option><option value="200">200 %</option></select>
 ${[['readable',t('Lecture simplifiée : contraste élevé, police simple, sans décors','Simplified reading: high contrast, simple font, no decoration')],['spacing',t('Espacement du texte augmenté','Increased text spacing')],['motion',t('Réduire les animations','Reduce animations')],['links',t('Souligner les liens','Underline links')]].map(([k,label])=>`<label class="d-reading-option"><input type="checkbox" data-reading="${k}">${label}</label>`).join('')}
 <p>${t('La préférence de réduction des animations de votre appareil reste respectée.','Your device’s reduced motion preference remains respected.')}</p>
 <button type="button" id="d-reading-reset">${t('Réinitialiser les réglages','Reset preferences')}</button><p id="d-reading-status" role="status"></p></dialog>`;
 document.body.append(area);
 const dialog=area.querySelector('dialog'),opener=area.querySelector('#d-reading-open'),size=area.querySelector('select'),status=area.querySelector('[role=status]');
 const apply=()=>{
  document.documentElement.dataset.readingSize=prefs.size;
  for(const k of ['readable','spacing','motion','links'])document.documentElement.classList.toggle('d-reading-'+k,prefs[k]);
  size.value=prefs.size;
  area.querySelectorAll('[data-reading]').forEach(input=>{input.checked=prefs[input.dataset.reading];});
 };
 const save=()=>{apply();try{localStorage.setItem(key,JSON.stringify(prefs));status.textContent=t('Réglages enregistrés dans ce navigateur.','Preferences saved in this browser.');}catch(_){status.textContent=t('Réglages appliqués pour cette page ; le navigateur empêche leur mémorisation.','Preferences applied to this page; your browser prevents saving them.');}};
 opener.addEventListener('click',()=>dialog.showModal());
 area.querySelector('#d-reading-close').addEventListener('click',()=>dialog.close());
 dialog.addEventListener('close',()=>opener.focus());
 size.addEventListener('change',()=>{prefs.size=size.value;save();});
 area.querySelectorAll('[data-reading]').forEach(input=>input.addEventListener('change',()=>{prefs[input.dataset.reading]=input.checked;save();}));
 area.querySelector('#d-reading-reset').addEventListener('click',()=>{prefs={...defaults};apply();try{localStorage.removeItem(key);status.textContent=t('Réglages réinitialisés.','Preferences reset.');}catch(_){status.textContent=t('Réglages réinitialisés pour cette page.','Preferences reset for this page.');}});
 window.addEventListener('storage',event=>{if(event.key===key||event.key===null){try{prefs=clean(JSON.parse(event.newValue));apply();}catch(_){}}});
 apply();
})();
