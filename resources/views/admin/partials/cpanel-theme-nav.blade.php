<script>
(() => {
    const addCpanelThemeLink=()=>{
        if(document.querySelector('[data-cpanel-theme-nav]'))return;
        const publicThemeUrl=@json(route('admin.settings.theme.index'));
        const publicThemePath=new URL(publicThemeUrl,location.origin).pathname;
        const publicTheme=[...document.querySelectorAll('.admin-sidebar a')]
            .find(link=>new URL(link.href,location.origin).pathname===publicThemePath);
        if(!publicTheme)return;
        const link=document.createElement('a');
        link.href=@json(route('admin.settings.cpanel-theme.index'));
        link.dataset.cpanelThemeNav='';
        link.className=location.pathname===new URL(link.href,location.origin).pathname?'active':'';
        link.innerHTML='<span>cPanel Appearance</span>';
        publicTheme.insertAdjacentElement('afterend',link);
    };
    const addLocalizationManagerLink=()=>{
        if(document.querySelector('[data-localization-manager-nav]'))return;
        const localizationPath=new URL(@json(route('admin.settings.page','localization')),location.origin).pathname;
        const localization=[...document.querySelectorAll('.admin-sidebar a')]
            .find(link=>new URL(link.href,location.origin).pathname===localizationPath);
        if(!localization)return;
        const link=document.createElement('a');
        link.href=@json(route('admin.localization.index'));
        link.dataset.localizationManagerNav='';
        link.className=location.pathname===new URL(link.href,location.origin).pathname?'active':'';
        link.innerHTML='<span>Language &amp; Currency Manager</span>';
        localization.insertAdjacentElement('afterend',link);
    };
    const mount=()=>{addCpanelThemeLink();addLocalizationManagerLink();};
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});else mount();
})();
</script>
