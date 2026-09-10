document.addEventListener('DOMContentLoaded',()=>{
  const root=document.querySelector('[data-customer-dashboard]'); if(!root)return;
  const open=name=>{const modal=document.querySelector(`[data-crm-modal="${name}"]`);if(modal){modal.hidden=false;modal.querySelector('input,select,textarea')?.focus();}};
  const close=modal=>{modal.hidden=true;};
  document.addEventListener('click',e=>{
    const trigger=e.target.closest('[data-crm-open]');if(trigger){e.preventDefault();open(trigger.dataset.crmOpen);return;}
    const closer=e.target.closest('[data-crm-close]');if(closer){close(closer.closest('.crm-modal'));return;}
    if(e.target.classList.contains('crm-modal'))close(e.target);
    const copy=e.target.closest('[data-copy]');if(copy){navigator.clipboard?.writeText(copy.dataset.copy||'');copy.title='Copied';}
    const tab=e.target.closest('[data-crm-tab]');if(tab){const scope=tab.closest('[data-crm-tabs]');scope.querySelectorAll('[data-crm-tab]').forEach(x=>x.classList.toggle('is-active',x===tab));document.querySelectorAll('[data-crm-panel]').forEach(p=>p.classList.toggle('is-active',p.dataset.crmPanel===tab.dataset.crmTab));}
    const edit=e.target.closest('[data-edit-record]');if(edit){const data=JSON.parse(edit.dataset.editRecord||'{}');const modal=document.querySelector(`[data-crm-modal="${edit.dataset.modal}"]`);if(modal){Object.entries(data).forEach(([k,v])=>{const el=modal.querySelector(`[name="${CSS.escape(k)}"]`);if(!el)return;if(el.type==='checkbox')el.checked=!!v;else el.value=v??'';});const form=modal.querySelector('form');if(form&&edit.dataset.action)form.action=edit.dataset.action;modal.hidden=false;}}
  });
  document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.crm-modal:not([hidden])').forEach(close);});
  document.querySelectorAll('[data-import-file]').forEach(input=>input.addEventListener('change',()=>{if(input.files.length)input.form.submit();}));
  const all=document.querySelector('[data-select-all]');if(all)all.addEventListener('change',()=>document.querySelectorAll('[data-row-check]').forEach(x=>x.checked=all.checked));
});
