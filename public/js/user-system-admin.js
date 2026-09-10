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
})();
