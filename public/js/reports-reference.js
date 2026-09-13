document.addEventListener('DOMContentLoaded',()=>{
  const root=document.querySelector('[data-reports-root]');
  if(!root)return;
  const reduceMotion=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const metricRoutes={
    'Total Reports':'/admin/resource/reports/custom','Reports Run':'/admin/resource/reports/history','Scheduled Reports':'/admin/resource/reports/scheduler',
    'Exports':'/admin/resource/reports/history','Active Users':'/admin/users-system/users','Favorite Reports':'/admin/resource/reports/custom',
    'Reports Shared':'/admin/resource/reports/custom','Data Sources':'/admin/resource/reports/custom','Total Approval Requests':'/admin/resource/reports/approvals',
    'Approved':'/admin/resource/reports/approvals','Pending':'/admin/resource/reports/approvals','Rejected':'/admin/resource/reports/approvals',
    'On Hold':'/admin/resource/reports/approvals','Avg. Turnaround Time':'/admin/resource/reports/approvals','Total Returns':'/admin/resource/reports/returns',
    'Total Refunds (EUR)':'/admin/resource/reports/returns','Return Orders':'/admin/resource/reports/returns','Refund Orders':'/admin/resource/reports/returns',
    'Return Rate':'/admin/resource/reports/returns','Avg. Processing Time':'/admin/resource/reports/returns','Total Runs':'/admin/resource/reports/history',
    'Successful Runs':'/admin/resource/reports/history','Failed Runs':'/admin/resource/reports/history','In Progress':'/admin/resource/reports/history',
    'Downloads':'/admin/resource/reports/history','Avg. Run Time':'/admin/resource/reports/history','Total Roles':'/admin/users-system/roles',
    'Total Users':'/admin/users-system/users','Disabled Users':'/admin/users-system/users',
    'Permissions':'/admin/users-system/permission-matrix','System Access':'/admin/users-system/roles'
  };
  const metricLink=(label)=>{
    const target=new URL(metricRoutes[label]||window.location.pathname,window.location.origin);
    const current=new URLSearchParams(window.location.search);
    ['from','to','company','compare','view','module','report_type','created_by','tag','status','favorite'].forEach(key=>{
      if(current.has(key))target.searchParams.set(key,current.get(key));
    });
    return target.pathname+target.search+target.hash;
  };
  const animateMetric=(metric)=>{
    metric.tabIndex=0;
    metric.setAttribute('role','link');
    const label=(metric.querySelector('small')?.textContent||'Report metric').trim();
    metric.setAttribute('aria-label',`Open ${label}`);
    metric.classList.add('reports-metric-link');
    const value=metric.querySelector('strong');
    if(!value||value.textContent.includes(':'))return;
    const source=value.textContent.trim();
    const match=source.match(/(-?[\d,]+(?:\.\d+)?)/);
    if(!match)return;
    const target=Number(match[1].replace(/,/g,''));
    if(!Number.isFinite(target))return;
    const prefix=source.slice(0,match.index), suffix=source.slice((match.index||0)+match[0].length);
    const render=(number)=>{value.textContent=`${prefix}${number.toLocaleString(undefined,{maximumFractionDigits:2})}${suffix}`;};
    if(reduceMotion){render(target);return;}
    const started=performance.now();
    const tick=(now)=>{const progress=Math.min(1,(now-started)/650);const eased=1-Math.pow(1-progress,3);render(target*eased);if(progress<1)window.requestAnimationFrame(tick);};
    render(0);window.requestAnimationFrame(tick);
  };
  root.querySelectorAll('.reports-metric').forEach(metric=>{
    animateMetric(metric);
    const open=()=>{window.location.href=metricLink((metric.querySelector('small')?.textContent||'').trim());};
    metric.addEventListener('click',event=>{if(event.target.closest('a,button,form'))return;open();});
    metric.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();open();}});
  });
  root.querySelectorAll('[data-report-add-filter]').forEach(button=>button.addEventListener('click',()=>{
    const table=root.querySelector('.reports-filter-table tbody');const first=table?.querySelector('tr');if(!first)return;
    const row=first.cloneNode(true);const index=table.querySelectorAll('tr').length;
    row.querySelectorAll('[name]').forEach(input=>{input.name=input.name.replace(/filters\[\d+\]/,`filters[${index}]`);if(input.tagName==='INPUT')input.value='';if(input.tagName==='SELECT')input.selectedIndex=0;});
    table.appendChild(row);
  }));
  root.querySelectorAll('[data-report-add-sort]').forEach(button=>button.addEventListener('click',()=>{
    const card=button.closest('.reports-card');const first=card?.querySelector('[data-report-sort-row]');if(!first)return;
    const row=first.cloneNode(true);const index=card.querySelectorAll('[data-report-sort-row]').length;
    row.querySelectorAll('[name]').forEach(input=>{input.name=input.name.replace(/sorts\[\d+\]/,`sorts[${index}]`);});
    row.firstChild.textContent=`${index+1} `;card.insertBefore(row,button);
  }));
  root.querySelectorAll('[data-report-add-calculated]').forEach(button=>button.addEventListener('click',()=>{
    const card=button.closest('.reports-card');if(!card)return;
    const label=document.createElement('label');label.className='reports-calculated-field';label.innerHTML='<span>Calculated field</span><input name="calculated_fields[]" placeholder="e.g. profit / sales">';card.insertBefore(label,button);
    label.querySelector('input').focus();
  }));
  root.querySelectorAll('[data-report-add-recipient]').forEach(button=>button.addEventListener('click',()=>{
    const extra=button.parentElement?.querySelector('[data-report-recipient-extra]');if(!extra)return;
    const label=document.createElement('label');label.className='reports-form-label reports-recipient-extra';label.innerHTML='<span>Additional recipient</span><input name="recipient_extra[]" type="email" placeholder="name@example.com">';extra.appendChild(label);label.querySelector('input').focus();
  }));
  root.querySelectorAll('[data-report-recipient-tab]').forEach(tab=>tab.addEventListener('click',event=>{
    event.preventDefault();root.querySelectorAll('[data-report-recipient-tab]').forEach(item=>{const active=item===tab;item.classList.toggle('active',active);item.setAttribute('aria-selected',String(active));});
    const input=root.querySelector('[data-report-recipient-input]');if(input){const groups=tab.dataset.reportRecipientTab==='groups';input.placeholder=groups?'group@example.com':'name@example.com';input.setAttribute('aria-label',groups?'Email group recipients':'Report recipients');}
  }));
  root.querySelectorAll('[data-report-template]').forEach(button=>button.addEventListener('click',()=>{
    const name=button.dataset.reportTemplate;const input=root.querySelector('.reports-builder-form [name="name"]');if(input){input.value=name;input.focus();input.scrollIntoView({behavior:reduceMotion?'auto':'smooth',block:'center'});}root.dataset.selectedTemplate=name;
  }));
  root.querySelectorAll('form[method="POST"],form:not([method])').forEach(form=>form.addEventListener('submit',()=>{const button=form.querySelector('button[type="submit"]');if(button&&!button.dataset.busy){button.dataset.busy='1';button.dataset.label=button.textContent;button.textContent='Saving…';}}));
});
