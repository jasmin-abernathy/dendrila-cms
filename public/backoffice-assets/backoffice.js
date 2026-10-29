(() => {
  'use strict';
  const chooser = document.querySelector('[data-page-chooser]');
  const editor = document.querySelector('[data-page-editor]');
  const page = chooser?.querySelector('select[name="page"]');
  if (chooser && page) {
    const originalPage = page.value;
    const original = editor ? new FormData(editor) : null;
    page.addEventListener('change', () => {
      if(page.value === originalPage) return;
      const current = editor ? new FormData(editor) : null;
      const changed = current && original &&
        Array.from(current.entries()).some(([key,value]) => original.get(key)!==value);
      if(changed && !window.confirm('Des modifications non enregistrées seront perdues. Changer de page ?')){
        page.value = originalPage;
        return;
      }
      chooser.requestSubmit();
    });
    document.querySelector('[data-publish-form]')?.addEventListener('submit',event => {
      const current = editor ? new FormData(editor) : null;
      const changed = current && original &&
        Array.from(current.entries()).some(([key,value]) => original.get(key)!==value);
      if(changed && !window.confirm('Le texte saisi n’est pas enregistré. Publier l’ancien brouillon ?')) event.preventDefault();
    });
  }
  const menuChooser=document.querySelector('select[name="menu"]');
  menuChooser?.addEventListener('change',() => menuChooser.form.requestSubmit());
  const list=document.querySelector('[data-sortable]');
  if(!list) return;
  const announce=document.querySelector('[data-sort-announcement]');
  const rows=() => Array.from(list.querySelectorAll(':scope > [data-sort-item]'));
  const notify=row => {
    if(announce)announce.textContent=(row.querySelector('.sort-title strong')?.textContent||'Page')+' déplacée. Enregistrez le menu.';
  };
  const move=(row,delta) => {
    const all=rows(), other=all[all.indexOf(row)+delta];
    if(!other)return;
    if(delta<0)list.insertBefore(row,other);
    else list.insertBefore(other,row);
    notify(row);
  };
  let active=null,pointer=null;
  const cancel=() => {
    active?.classList.remove('dragging');active=null;pointer=null;
    document.removeEventListener('pointermove',drag);
    document.removeEventListener('pointerup',cancel);
    document.removeEventListener('pointercancel',cancel);
  };
  const drag=event => {
    if(!active || event.pointerId!==pointer)return;
    event.preventDefault();
    const target=document.elementFromPoint(event.clientX,event.clientY)?.closest('[data-sort-item]');
    if(!target || target===active || target.parentElement!==list)return;
    const middle=target.getBoundingClientRect().top+target.getBoundingClientRect().height/2;
    list.insertBefore(active,event.clientY<middle?target:target.nextSibling);
    notify(active);
  };
  list.querySelectorAll('[data-sort-handle]').forEach(handle => {
    handle.addEventListener('keydown',event => {
      const delta=event.key==='ArrowUp'?-1:event.key==='ArrowDown'?1:0;
      if(delta===0)return;
      event.preventDefault();move(handle.closest('[data-sort-item]'),delta);handle.focus();
    });
    handle.addEventListener('pointerdown',event => {
      if(event.button!==0||active)return;
      event.preventDefault();active=handle.closest('[data-sort-item]');
      pointer=event.pointerId;active.classList.add('dragging');
      document.addEventListener('pointermove',drag,{passive:false});
      document.addEventListener('pointerup',cancel);
      document.addEventListener('pointercancel',cancel);
    });
  });
  list.querySelectorAll('[data-toggle-options]').forEach(button => {
    button.addEventListener('click',() => {
      const options=button.closest('[data-sort-item]').querySelector('.sort-options');
      options.hidden=!options.hidden;
      button.setAttribute('aria-expanded',String(!options.hidden));
      if(!options.hidden)options.querySelector('input')?.focus();
    });
  });
})();
