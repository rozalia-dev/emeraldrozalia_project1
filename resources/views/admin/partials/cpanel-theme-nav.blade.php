<script>
(() => {
    const addCpanelThemeLink=()=>{
        if(document.querySelector('[data-cpanel-theme-nav]'))return;
        const links=[...document.querySelectorAll('.admin-sidebar a')];
        const publicTheme=links.find(link=>link.getAttribute('href')?.includes('/admin/settings/theme'));
        if(!publicTheme)return;
        const link=document.createElement('a');
        link.href=@json(route('admin.settings.cpanel-theme.index'));
        link.dataset.cpanelThemeNav='';
        link.className=location.pathname===new URL(link.href,location.origin).pathname?'active':'';
        link.textContent='cPanel Appearance';
        publicTheme.insertAdjacentElement('afterend',link);
    };
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',addCpanelThemeLink,{once:true});else addCpanelThemeLink();
})();
</script>
