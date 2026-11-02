(() => {
  'use strict';
  const root = document.querySelector('[data-appearance-editor]');
  if (!root) return;
  const form = root.querySelector('[data-appearance-form]');
  const theme = root.querySelector('[data-theme-select]');
  const preview = root.querySelector('[data-color-preview]');
  const status = root.querySelector('[data-contrast-status]');
  const change = root.querySelector('[data-change-state]');
  const save = root.querySelector('[data-save-button]');
  const hint = root.querySelector('[data-save-hint]');
  const swatches = Array.from(root.querySelectorAll('[data-color-key]'));
  const keys = ['primary','primary_strong','secondary','background','surface','accent_soft','text','muted','border'];
  const css = { primary:'primary', primary_strong:'primary-strong',secondary:'secondary',
    background:'background',surface:'surface',accent_soft:'accent-soft',
    text:'text',muted:'muted',border:'border' };
  const formSignature=()=>Array.from(new FormData(form).entries()).map(([k,v])=>k+'='+(v instanceof File ? v.name+':'+v.size+':'+v.lastModified : v)).sort().join('&');
  const original = new FormData(form);
  let originals = formSignature();
  const palette = () => {
    try { return JSON.parse(theme.selectedOptions[0].dataset.palette || '{}'); }
    catch { return {}; }
  };
  const rgb = hex => hex.match(/[0-9a-f]{2}/gi)?.map(v => parseInt(v,16)/255) || [0,0,0];
  const luminance = hex => {
    const [r,g,b] = rgb(hex).map(v => v <= .04045 ? v/12.92 : Math.pow((v+.055)/1.055,2.4));
    return .2126*r+.7152*g+.0722*b;
  };
  const contrast = (one,two) => {
    const [a,b]=[luminance(one),luminance(two)].sort((x,y)=>y-x);
    return (a+.05)/(b+.05);
  };
  const update = () => {
    const defaults=palette(), values={};
    for (const row of swatches) {
      const key=row.dataset.colorKey;
      if (!keys.includes(key)) continue;
      const picker=row.querySelector('[data-picker]'),enabled=row.querySelector('[data-override]');
      const fallback=defaults[key] || '#777777';
      const value=enabled.checked ? picker.value : fallback;
      values[key]=value;
      row.querySelector('[data-hex]').textContent=value.toUpperCase();
      row.querySelector('[data-color-status]').textContent=enabled.checked?'Personnalisée':'Thème';
      preview.style.setProperty('--ap-'+css[key],value);
    }
    // White text is the original CTA treatment. Show accurate color/contrast
    // feedback, without pretending this is a screenshot of the entire site.
    const issues=[];
    const textPage=contrast(values.text,values.background);
    const textCard=contrast(values.text,values.surface);
    const textBand=contrast(values.text,values.accent_soft);
    const buttons=contrast(values.primary,'#ffffff');
    if (textPage < 4.5) issues.push('texte sur fond');
    if (textCard < 4.5) issues.push('texte sur cartes');
    if (textBand < 4.5) issues.push('texte sur bandeau');
    if (buttons < 4.5) issues.push('texte des boutons');
    status.textContent = issues.length ? '⚠ Contraste insuffisant : '+issues.join(', ') : '✓ Contrastes de l’aperçu : AA (4,5:1)';
    status.dataset.level=issues.length?'warning':'ok';
    const current=formSignature();
    const dirty=current!==originals;
    change.textContent=dirty?'Modifications non enregistrées':'Réglages actuels';
    change.classList.toggle('is-dirty',dirty);
    hint.textContent=dirty?'Vos couleurs sont uniquement en aperçu. Enregistrez pour les publier.':'Choisissez une couleur pour voir immédiatement son effet.';
    save.disabled=false;
  };
  for (const row of swatches) {
    const picker=row.querySelector('[data-picker]');
    const checkbox=row.querySelector('[data-override]');
    picker.addEventListener('input',()=>{checkbox.checked=true;update();});
    checkbox.addEventListener('change',()=>{update();});
  }
  theme.addEventListener('change',update);
  form.addEventListener('change',update);
  form.addEventListener('input',event=>{
    if(!event.target.matches('[data-picker]'))update();
  });
  update();
  const fontFamilies={
    'neutral-sans':'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
    'humanist-sans':'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
    'editorial-serif':'Georgia, "Times New Roman", serif',
    'system-serif':'ui-serif, Georgia, "Times New Roman", serif'
  };
  const temporaryFontUrls=new Map();
  const temporaryImageUrls=new Map();
  const fontSlots=Array.from(root.querySelectorAll('[data-font-target]'));
  let fontCounter=0;
  async function refreshFont(slot) {
    const select=slot.querySelector('[data-font-select]');
    const output=slot.querySelector('[data-font-status]');
    const target=slot.dataset.fontTarget;
    const upload=slot.querySelector('[data-font-upload]');
    const current=slot.dataset.currentFont;
    const value=select.value;
    const variable=target==='heading_font'?'--ap-heading-font':'--ap-body-font';
    let family=fontFamilies[value] || (target==='heading_font'?'Georgia,serif':'system-ui,sans-serif');
    if(value==='custom' && (upload.files[0]||current)){
      const temp=temporaryFontUrls.get(target);
      const source=upload.files[0] ? temp :
        /^(woff2|woff|truetype|opentype):[a-f0-9]{48}$/.test(current) ? '/brand-font.php?font='+encodeURIComponent(current) : null;
      if(source && typeof FontFace!=='undefined'){
        const name='DendrilaLocalPreview'+(++fontCounter);
        try{
          const font=new FontFace(name,'url("'+source+'")');
          await font.load();
          if(select.value!=='custom')return;
          document.fonts.add(font);
          family='"'+name+'",system-ui,sans-serif';
          output.textContent='Aperçu de votre police personnalisée.';
        }catch(e){ output.textContent='Impossible de prévisualiser cette police. Vérifiez le fichier.'; }
      }
    }
    slot.style.setProperty('--ap-font-example',family);
    preview.style.setProperty(variable,family);
  }
  for(const slot of fontSlots){
    const select=slot.querySelector('[data-font-select]');
    const file=slot.querySelector('[data-font-upload]');
    const remove=slot.querySelector('[data-font-remove]');
    select.addEventListener('change',()=>{refreshFont(slot);update();});
    file.addEventListener('change',()=>{
      const key=slot.dataset.fontTarget, upload=file.files[0];
      const old=temporaryFontUrls.get(key);
      if(old)URL.revokeObjectURL(old);
      temporaryFontUrls.delete(key);
      if(upload){
        if(upload.size>3000000 || !/\.(woff2?|ttf|otf)$/i.test(upload.name)){
          slot.querySelector('[data-font-status]').textContent='Fichier refusé. Choisissez WOFF2, WOFF, TTF ou OTF (3 Mo maximum).';
          file.value='';update();return;
        }
        temporaryFontUrls.set(key,URL.createObjectURL(upload));
        select.querySelector('option[value=custom]').disabled=false;
        select.value='custom';
        if(remove)remove.checked=false;
        slot.querySelector('[data-font-status]').textContent=upload.name+' — aperçu en cours…';
      }else if(!slot.dataset.currentFont)select.value='';
      refreshFont(slot);update();
    });
    if(remove)remove.addEventListener('change',()=>{
      if(remove.checked){
        file.value='';
        select.value='';
      }else if(slot.dataset.currentFont)select.value='custom';
      refreshFont(slot);update();
    });
    refreshFont(slot);
  }
  for(const section of root.querySelectorAll('[data-brand-asset]')){
    const input=section.querySelector('[data-brand-file]');
    const image=section.querySelector('[data-brand-preview]');
    const placeholder=section.querySelector('[data-brand-placeholder]');
    const remove=section.querySelector('[data-brand-remove]');
    const original=image.getAttribute('src') || '';
    const key=section.dataset.brandAsset;
    const show=source=>{
      image.hidden=!source;placeholder.hidden=!!source;
      if(source)image.setAttribute('src',source);
      else image.removeAttribute('src');
    };
    input.addEventListener('change',()=>{
      const old=temporaryImageUrls.get(key);
      if(old)URL.revokeObjectURL(old);
      temporaryImageUrls.delete(key);
      const file=input.files[0];
      if(file){
        if(file.size>8000000 || !['image/png','image/jpeg','image/webp'].includes(file.type)){
          input.value='';show(remove&&remove.checked?'':original);
          update();return;
        }
        const url=URL.createObjectURL(file);
        temporaryImageUrls.set(key,url);
        if(remove)remove.checked=false;
        show(url);
      }else show(remove&&remove.checked?'':original);
      update();
    });
    if(remove)remove.addEventListener('change',()=>{
      if(remove.checked)input.value='';
      show(remove.checked?'':original);
      update();
    });
  }
})();
