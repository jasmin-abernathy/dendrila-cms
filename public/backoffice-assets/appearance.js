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
  const original = new FormData(form);
  let originals = Array.from(original.entries()).map(([k,v]) => k+'='+v).sort().join('&');
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
    const current=Array.from(new FormData(form).entries()).map(([k,v])=>k+'='+v).sort().join('&');
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
})();
