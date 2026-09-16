(()=>{
  const open=id=>{const d=document.getElementById(id);if(d&&typeof d.showModal==='function')d.showModal();};
  document.addEventListener('click',e=>{const t=e.target.closest('[data-us-open]');if(t){e.preventDefault();open(t.dataset.usOpen);return;}const c=e.target.closest('[data-us-close]');if(c){e.preventDefault();c.closest('dialog')?.close();}});
  document.querySelectorAll('.us-modal').forEach(d=>d.addEventListener('click',e=>{if(e.target===d)d.close();}));
  const utility=document.querySelector('.admin-nav-utility');
  if(utility){
    const groups=[...utility.querySelectorAll(':scope > details')];
    const group=groups.find(g=>g.querySelector('summary span')?.textContent.trim()==='USERS & ROLES');
    if(group){
      const summary=group.querySelector('summary span'); if(summary) summary.textContent='USERS & SYSTEM';
      const box=group.querySelector('.admin-nav-items');
      if(box){
        const links=[
          ['/admin/users-system/users','Users'],['/admin/users-system/roles','Roles'],['/admin/users-system/user-roles-permissions','User Roles & Permissions'],['/admin/users-system/role-assignments','Role Assignments'],['/admin/users-system/permission-groups','Permission Groups'],['/admin/users-system/permission-matrix','Permission Matrix'],['/admin/users-system/roles?type=api','API Roles'],['/admin/users-system/activity-log','Activity Log']
        ];
        const path=location.pathname;
        box.innerHTML='<details class="admin-nav-subgroup" open><summary class="'+(path.includes('/users-system/')?'active':'')+'"><span class="admin-nav-parent-label"><span>♙</span><span>Users & Roles</span></span><span class="admin-group-chevron">›</span></summary><div class="admin-nav-subitems">'+links.map(([href,label])=>'<a class="'+(path===href.split('?')[0]?'active':'')+'" href="'+href+'"><span>'+label+'</span></a>').join('')+'</div></details>';
      }
    }
  }

  if(location.pathname.replace(/\/$/,'')==='/admin/users-system/users'){
    const page=document.querySelector('.us-page');
    const table=page?.querySelector('.us-table');
    const tableWrap=page?.querySelector('.us-table-wrap');
    const token=page?.querySelector('input[name="_token"]')?.value||'';
    if(page&&table&&tableWrap&&token){
      const rows=[...table.querySelectorAll('tbody tr')];
      const checks=[];
      rows.forEach(row=>{
        const link=row.querySelector('a[href*="selected="]');
        if(!link)return;
        let id=null;
        try{id=new URL(link.href,location.origin).searchParams.get('selected');}catch(error){id=null;}
        if(!id)return;
        const firstCell=row.querySelector('td');
        if(!firstCell)return;
        const check=document.createElement('input');
        check.type='checkbox';check.name='ids[]';check.value=id;check.setAttribute('form','user-bulk-form');check.setAttribute('aria-label','Select user');check.className='us-bulk-check';
        firstCell.prepend(check);checks.push(check);
      });

      if(checks.length){
        const form=document.createElement('form');
        form.id='user-bulk-form';form.method='post';form.action='/admin/users-system/users/bulk';form.className='us-filterbar us-bulkbar';
        form.innerHTML='<input type="hidden" name="_token" value="'+token+'"><label><span>Select visible users</span><input type="checkbox" data-us-select-all aria-label="Select all visible users"></label><label><span>Bulk action</span><select name="action"><option value="activate">Activate</option><option value="deactivate">Deactivate</option><option value="lock">Lock access</option><option value="unlock">Unlock access</option><option value="enable_2fa">Enable 2FA</option><option value="disable_2fa">Disable 2FA</option></select></label><button type="submit" class="us-btn us-btn-primary">Apply</button><span class="us-bulk-count" data-us-bulk-count>0 selected</span>';
        tableWrap.before(form);
        const all=form.querySelector('[data-us-select-all]'),count=form.querySelector('[data-us-bulk-count]'),action=form.querySelector('[name="action"]');
        const update=()=>{const selected=checks.filter(check=>check.checked).length;count.textContent=selected+' selected';all.checked=selected>0&&selected===checks.length;all.indeterminate=selected>0&&selected<checks.length;};
        all.addEventListener('change',()=>{checks.forEach(check=>check.checked=all.checked);update();});
        checks.forEach(check=>check.addEventListener('change',update));
        form.addEventListener('submit',event=>{
          const selected=checks.filter(check=>check.checked).length;
          if(!selected){event.preventDefault();alert('Select at least one user first.');return;}
          if(['deactivate','lock','disable_2fa'].includes(action.value)&&!confirm('Apply this access restriction to '+selected+' selected user'+(selected===1?'':'s')+'?'))event.preventDefault();
        });
        update();
      }
    }
  }
})();
