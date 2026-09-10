document.addEventListener('DOMContentLoaded',()=>{
  const root=document.querySelector('[data-reports-root]');
  if(!root)return;
  root.querySelectorAll('[data-report-add-filter]').forEach(button=>button.addEventListener('click',()=>{
    const table=root.querySelector('.reports-filter-table tbody'); if(!table)return;
    const row=table.querySelector('tr').cloneNode(true); row.querySelectorAll('input').forEach(input=>input.value=''); table.appendChild(row);
  }));
  root.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{const button=form.querySelector('button[type="submit"]');if(button&&!button.dataset.busy){button.dataset.busy='1';button.dataset.label=button.textContent;button.textContent='Saving…';}}));
});
