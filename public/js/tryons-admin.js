(() => {
  'use strict';
  const create=document.getElementById('to-create'),audit=document.getElementById('to-audit');
  document.querySelectorAll('[data-create]').forEach(button=>button.addEventListener('click',()=>create?.showModal()));
  document.querySelectorAll('[data-close]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog')?.close()));

  document.querySelectorAll('[data-audit]').forEach(button=>button.addEventListener('click',async()=>{
    if(!audit)return;
    const output=audit.querySelector('[data-audit-content]');
    output.textContent='Loading audit history…';
    audit.showModal();
    try{
      const response=await fetch(button.dataset.audit,{headers:{Accept:'application/json'}});
      if(!response.ok)throw new Error('Could not load audit history.');
      const data=await response.json();
      output.textContent=`UUID: ${data.uuid}\n\n`+(data.entries.length?data.entries.map(entry=>`${entry.created_at}  ${entry.action}  User #${entry.user_id??'—'}`).join('\n'):'No audit entries yet.');
    }catch(error){output.textContent=error.message;}
  }));

  document.querySelectorAll('[data-copy-uuid]').forEach(button=>button.addEventListener('click',async()=>{
    try{await navigator.clipboard.writeText(button.dataset.copyUuid);const old=button.textContent;button.textContent='✓';setTimeout(()=>button.textContent=old,1200);}catch{button.title='Copy is unavailable in this browser.';}
  }));

  const focusWorkbench=selector=>{const target=document.querySelector(selector);target?.scrollIntoView({behavior:'smooth',block:'center'});setTimeout(()=>target?.querySelector('select,input,textarea,button')?.focus(),250);};
  document.querySelectorAll('[data-focus-target]').forEach(link=>link.addEventListener('click',()=>focusWorkbench('#to-target-field')));
  document.querySelectorAll('[data-focus-settings]').forEach(link=>link.addEventListener('click',()=>focusWorkbench('[data-settings-card]')));

  const bulk=document.querySelector('[data-bulk]'),all=bulk?.querySelector('[data-select-all]');
  const selection=()=>{
    if(!bulk)return;
    const boxes=[...bulk.querySelectorAll('[name="ids[]"]')],selected=boxes.filter(box=>box.checked),note=bulk.querySelector('[data-selection]');
    if(note)note.textContent=`${selected.length} selected`;
    if(all){all.checked=boxes.length>0&&selected.length===boxes.length;all.indeterminate=selected.length>0&&selected.length<boxes.length;}
  };
  all?.addEventListener('change',()=>{bulk.querySelectorAll('[name="ids[]"]').forEach(box=>box.checked=all.checked);selection();});
  bulk?.querySelectorAll('[name="ids[]"]').forEach(box=>box.addEventListener('change',selection));
  bulk?.addEventListener('submit',event=>{
    if(!bulk.querySelector('[name="ids[]"]:checked')){event.preventDefault();alert('Select at least one try-on asset.');return;}
    if(bulk.elements.action.value==='delete'&&!confirm('Permanently delete the selected try-on assets and stored files?'))event.preventDefault();
  });

  document.querySelector('[data-per-page]')?.addEventListener('change',event=>{const url=new URL(location.href);url.searchParams.set('per_page',event.target.value);url.searchParams.delete('page');location.assign(url);});

  document.querySelectorAll('[data-tryon-form]').forEach(form=>{
    const input=form.querySelector('[name="asset"]'),drop=form.querySelector('.sd-drop'),note=form.querySelector('[data-file-note]');
    const validate=()=>{
      const file=input?.files?.[0];
      if(note)note.textContent=file?.name||'Choose Files';
      if(!input||!file)return;
      const allowed=['zip','png','jpg','jpeg','webp','glb','usdz'];
      const ext=(file.name.split('.').pop()||'').toLowerCase();
      input.setCustomValidity(!allowed.includes(ext)?'Use ZIP, PNG, JPG, WebP, GLB or USDZ only.':file.size>20*1024*1024?'Choose a file smaller than 20 MB.':'');
    };
    input?.addEventListener('change',validate);
    drop?.addEventListener('dragover',event=>{event.preventDefault();drop.classList.add('dragging');});
    drop?.addEventListener('dragleave',()=>drop.classList.remove('dragging'));
    drop?.addEventListener('drop',event=>{event.preventDefault();drop.classList.remove('dragging');if(input&&event.dataTransfer.files.length===1){try{const transfer=new DataTransfer();transfer.items.add(event.dataTransfer.files[0]);input.files=transfer.files;}catch{}validate();}});
    form.addEventListener('submit',event=>{
      validate();
      if(input&&!input.checkValidity()){event.preventDefault();input.reportValidity();return;}
      const message=form.querySelector('[data-upload-message]');
      if(message)message.textContent='Saving and validating try-on assets… Please keep this page open.';
      form.querySelectorAll('[type="submit"]').forEach(button=>button.disabled=true);
    });
  });
})();
