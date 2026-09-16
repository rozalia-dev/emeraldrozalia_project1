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

  const path=window.location.pathname.replace(/\/$/,'');
  const config=path==='/admin/customers'
    ? {bulk:'/admin/customers/bulk',destroy:'/admin/customers/',actions:[['active','Set Active'],['inactive','Set Inactive'],['blocked','Block'],['restricted','Restrict'],['delete','Delete selected']]}
    : path==='/admin/customer-groups'
      ? {bulk:'/admin/customer-groups/bulk',destroy:'/admin/customer-groups/',actions:[['activate','Activate'],['deactivate','Deactivate'],['delete','Delete selected']]}
      : path==='/admin/customer-segments'
        ? {bulk:'/admin/customer-segments/bulk',destroy:'/admin/customer-segments/',actions:[['activate','Activate'],['deactivate','Deactivate'],['delete','Delete selected']]}
        : null;

  const token=root.querySelector('input[name="_token"]')?.value||'';
  const rows=[...root.querySelectorAll('.crm-table tbody tr')];
  const checks=[];
  const idForRow=row=>{
    const link=row.querySelector('a[href*="selected="]');
    if(!link)return null;
    try{return new URL(link.href,window.location.origin).searchParams.get('selected');}catch{return null;}
  };

  if(config&&token){
    const card=root.querySelector('.crm-table-card');
    const tableWrap=card?.querySelector('.crm-table-wrap');
    if(card&&tableWrap){
      const form=document.createElement('form');
      form.id='crm-bulk-form';
      form.className='crm-filter crm-bulk-actions';
      form.method='post';
      form.action=config.bulk;
      form.innerHTML=`<input type="hidden" name="_token" value="${token}"><label>Bulk action <select name="action">${config.actions.map(([value,label])=>`<option value="${value}">${label}</option>`).join('')}</select></label><button class="crm-btn" type="submit">Apply</button><span class="crm-cell-sub" data-bulk-count>0 selected</span>`;
      tableWrap.before(form);

      rows.forEach(row=>{
        const id=idForRow(row);if(!id)return;
        const check=row.querySelector('[data-row-check]');
        if(check){check.name='ids[]';check.value=id;check.setAttribute('form',form.id);checks.push(check);}
        const actions=row.querySelector('.crm-actions');
        if(actions&&!actions.querySelector('[data-crm-delete]')){
          const deleteForm=document.createElement('form');
          deleteForm.className='crm-inline-form';
          deleteForm.method='post';
          deleteForm.action=config.destroy+encodeURIComponent(id);
          deleteForm.innerHTML=`<input type="hidden" name="_token" value="${token}"><input type="hidden" name="_method" value="DELETE"><button class="crm-action" type="submit" data-crm-delete title="Delete" aria-label="Delete record">×</button>`;
          deleteForm.addEventListener('submit',event=>{if(!window.confirm('Delete this record? This action will be audited.'))event.preventDefault();});
          actions.append(deleteForm);
        }
      });

      const count=form.querySelector('[data-bulk-count]');
      const update=()=>{const selected=checks.filter(check=>check.checked).length;if(count)count.textContent=`${selected} selected`;if(all){all.checked=checks.length>0&&selected===checks.length;all.indeterminate=selected>0&&selected<checks.length;}};
      checks.forEach(check=>check.addEventListener('change',update));
      form.addEventListener('submit',event=>{
        const selected=checks.filter(check=>check.checked).length;
        if(!selected){event.preventDefault();window.alert('Select at least one record first.');return;}
        if(form.action.value==='delete'&&!window.confirm(`Delete ${selected} selected record${selected===1?'':'s'}? This action will be audited.`)){event.preventDefault();}
      });
      update();
    }
  }

  const all=document.querySelector('[data-select-all]');
  if(all)all.addEventListener('change',()=>{document.querySelectorAll('[data-row-check]').forEach(x=>x.checked=all.checked);document.querySelectorAll('[data-row-check]').forEach(x=>x.dispatchEvent(new Event('change')));});
});
