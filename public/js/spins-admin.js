(() => {
  'use strict';
  const create=document.getElementById('sd-create'),audit=document.getElementById('sd-audit');
  document.querySelectorAll('[data-create]').forEach(b=>b.addEventListener('click',()=>create?.showModal()));
  document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click',()=>b.closest('dialog')?.close()));
  document.querySelectorAll('[data-audit]').forEach(b=>b.addEventListener('click',async()=>{
    if(!audit)return;
    const output=audit.querySelector('[data-audit-content]');output.textContent='Loading audit history…';audit.showModal();
    try{const r=await fetch(b.dataset.audit,{headers:{Accept:'application/json'}});if(!r.ok)throw new Error('Could not load audit history.');const d=await r.json();output.textContent=`UUID: ${d.uuid}\n\n`+(d.entries.length?d.entries.map(x=>`${x.created_at}  ${x.action}  User #${x.user_id??'—'}`).join('\n'):'No audit entries yet.');}catch(e){output.textContent=e.message;}
  }));
  document.querySelectorAll('[data-copy-uuid]').forEach(b=>b.addEventListener('click',async()=>{
    try{await navigator.clipboard.writeText(b.dataset.copyUuid);const old=b.textContent;b.textContent='✓';setTimeout(()=>b.textContent=old,1200);}catch{b.title='Copy is unavailable in this browser.';}
  }));
  const focusWorkbench=selector=>{const target=document.querySelector(selector);target?.scrollIntoView({behavior:'smooth',block:'center'});setTimeout(()=>target?.querySelector('select,input,textarea,button')?.focus(),250);};
  document.querySelectorAll('[data-focus-category]').forEach(a=>a.addEventListener('click',()=>focusWorkbench('#sd-category-field')));
  document.querySelectorAll('[data-focus-settings]').forEach(a=>a.addEventListener('click',()=>focusWorkbench('[data-settings-card]')));
  const bulk=document.querySelector('[data-bulk]'),all=bulk?.querySelector('[data-select-all]');
  const selection=()=>{if(!bulk)return;const boxes=[...bulk.querySelectorAll('[name="ids[]"]')],selected=boxes.filter(x=>x.checked);const note=bulk.querySelector('[data-selection]');if(note)note.textContent=`${selected.length} selected`;if(all){all.checked=boxes.length>0&&selected.length===boxes.length;all.indeterminate=selected.length>0&&selected.length<boxes.length;}};
  all?.addEventListener('change',()=>{bulk.querySelectorAll('[name="ids[]"]').forEach(x=>x.checked=all.checked);selection();});
  bulk?.querySelectorAll('[name="ids[]"]').forEach(x=>x.addEventListener('change',selection));
  bulk?.addEventListener('submit',e=>{if(!bulk.querySelector('[name="ids[]"]:checked')){e.preventDefault();alert('Select at least one view.');return;}if(bulk.elements.action.value==='delete'&&!confirm('Permanently delete the selected views and their frame files? This cannot be undone from the dashboard.'))e.preventDefault();});
  document.querySelector('[data-per-page]')?.addEventListener('change',e=>{const url=new URL(location.href);url.searchParams.set('per_page',e.target.value);url.searchParams.delete('page');location.assign(url);});
  document.querySelectorAll('[data-spin-form]').forEach(form=>{
    const input=form.querySelector('[name="archive"]'),drop=form.querySelector('.sd-drop'),note=form.querySelector('[data-file-note]');
    input?.addEventListener('change',()=>{if(note)note.textContent=input.files[0]?.name||'Choose Files';input.setCustomValidity(input.files[0]?.size>20*1024*1024?'Choose a ZIP smaller than 20 MB.':'');});
    drop?.addEventListener('dragover',e=>{e.preventDefault();drop.classList.add('dragging');});
    drop?.addEventListener('dragleave',()=>drop.classList.remove('dragging'));
    drop?.addEventListener('drop',e=>{e.preventDefault();drop.classList.remove('dragging');if(input&&e.dataTransfer.files.length===1){input.files=e.dataTransfer.files;input.dispatchEvent(new Event('change'));}});
    const hotspot=form.querySelector('[name="hotspot_data"]');
    hotspot?.addEventListener('input',e=>e.target.setCustomValidity(''));
    form.addEventListener('submit',e=>{
      const field=form.querySelector('[name="hotspot_data"]');
      try{const d=JSON.parse(field?.value||'[]');if(!Array.isArray(d))throw new Error();field?.setCustomValidity('');}
      catch{e.preventDefault();field?.setCustomValidity('Enter a JSON array of hotspots.');const details=field?.closest('details');if(details)details.open=true;field?.reportValidity();return;}
      const message=form.querySelector('[data-upload-message]');if(message)message.textContent='Saving and processing frames… Please keep this page open.';
      form.querySelectorAll('[type="submit"]').forEach(button=>button.disabled=true);
    });
  });
})();
