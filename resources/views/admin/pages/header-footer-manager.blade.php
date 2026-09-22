@extends('layouts.admin')

@php
    $navigation = array_values((array) data_get($regions, 'header.primary_menu', []));
    $footerColumns = array_values((array) data_get($regions, 'footer.columns', []));
    $legalLinks = array_values((array) data_get($regions, 'footer.legal_links', []));
    $headerLogo = data_get($regions, 'header.logo.path', '/assets/logo/logo_one_line.png');
    $footerLogo = data_get($regions, 'footer.logo.path', '/assets/logo/logo_two_line.png');
@endphp

@section('title', 'Header & Footer Manager')

@push('styles')
<style>
.hf-manager{max-width:1450px;margin:0 auto;color:#183126}.hf-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:18px}.hf-head h1{margin:2px 0 5px;font-size:28px}.hf-head p{margin:0;color:#718078}.hf-actions{display:flex;gap:8px;flex-wrap:wrap}.hf-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:38px;padding:0 13px;border:1px solid #0a743d;border-radius:6px;background:#0a743d;color:#fff;font-weight:700;text-decoration:none;cursor:pointer}.hf-btn--soft{background:#fff;color:#0a743d}.hf-status{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 16px}.hf-pill{padding:6px 9px;border-radius:999px;background:#eef6f0;color:#237144;font-size:12px}.hf-grid{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:16px}.hf-main{display:grid;gap:16px}.hf-card{border:1px solid #dbe5dd;border-radius:9px;background:#fff;box-shadow:0 2px 10px rgba(18,58,34,.04)}.hf-card-head{display:flex;justify-content:space-between;gap:14px;align-items:center;padding:15px 16px;border-bottom:1px solid #edf2ee}.hf-card-head h2{margin:0;font-size:18px}.hf-card-head p{margin:4px 0 0;color:#748278;font-size:12px}.hf-card-body{padding:16px}.hf-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.hf-fields label,.hf-field{display:grid;gap:6px;font-weight:700;color:#43564a}.hf-fields input,.hf-fields select,.hf-fields textarea,.hf-field input{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd9ce;border-radius:6px;background:#fff;color:#1d3024;font:inherit}.hf-wide{grid-column:1/-1}.hf-logo-preview{display:flex;align-items:center;justify-content:center;min-height:94px;padding:12px;border:1px solid #dfe8e1;border-radius:7px;background:#06130d}.hf-logo-preview img{max-width:270px;max-height:70px;object-fit:contain}.hf-list{display:grid;gap:10px}.hf-row{border:1px solid #e0e8e2;border-radius:7px;background:#fbfdfb;padding:12px}.hf-row-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}.hf-row-actions{display:flex;gap:6px;flex-wrap:wrap}.hf-mini{min-height:30px;padding:0 9px;border:1px solid #bfd2c3;border-radius:5px;background:#fff;color:#226b3d;font:inherit;font-size:12px;font-weight:700;cursor:pointer}.hf-mini--danger{border-color:#e2b5b0;color:#9a433b}.hf-row-fields{display:grid;grid-template-columns:1fr 1.5fr auto;gap:10px;align-items:end}.hf-check{display:flex!important;align-items:center;gap:7px!important;white-space:nowrap}.hf-check input{width:auto!important}.hf-column-links{display:grid;gap:8px;margin-top:10px}.hf-link-row{display:grid;grid-template-columns:1fr 1.5fr auto;gap:8px;align-items:end}.hf-side{display:grid;align-content:start;gap:16px}.hf-note{padding:11px;border-radius:6px;background:#f3f8f4;color:#5e7064;font-size:12px;line-height:1.5}.hf-live-preview{padding:14px;border-radius:8px;background:linear-gradient(135deg,#08180f,#0a5d35);color:#fff}.hf-live-preview strong,.hf-live-preview small{display:block}.hf-live-preview small{margin:5px 0 10px;color:#d7e9db}.hf-empty{padding:18px;text-align:center;color:#718078;border:1px dashed #cad9ce;border-radius:7px}.hf-savebar{display:flex;justify-content:flex-end;position:sticky;bottom:0;padding:12px 0 0;background:linear-gradient(transparent,#f7f9f7 40%)}@media(max-width:1000px){.hf-grid{grid-template-columns:1fr}.hf-side{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.hf-head{display:block}.hf-actions{margin-top:12px}.hf-fields,.hf-row-fields,.hf-link-row,.hf-side{grid-template-columns:1fr}.hf-wide{grid-column:auto}}
</style>
@endpush

@section('content')
<div class="hf-manager" data-hf-manager>
    <header class="hf-head">
        <div><p>WEBSITE &amp; PRODUCTS / PUBLIC SHELL</p><h1>Header &amp; Footer Manager</h1><p>Change the approved logo, manage header navigation, and add/edit/delete footer sections and links from one cPanel screen.</p></div>
        <div class="hf-actions"><a class="hf-btn hf-btn--soft" href="{{ route('admin.pages.layouts') }}">Advanced Layout Manager</a><a class="hf-btn hf-btn--soft" href="{{ url('/') }}" target="_blank" rel="noopener">Open Live Website</a></div>
    </header>

    <div class="hf-status"><span class="hf-pill">Live: {{ $active ? 'v'.$active->version : 'default layout' }}</span><span class="hf-pill">Draft: {{ $draft ? 'v'.$draft->version : 'created on first save' }}</span><span class="hf-pill">Locale: {{ strtoupper($locale) }}</span></div>

    <form method="post" action="{{ route('admin.pages.header-footer.save') }}" data-hf-form>
        @csrf
        <input type="hidden" name="locale" value="{{ $locale }}">
        <div class="hf-grid">
            <main class="hf-main">
                <section class="hf-card">
                    <div class="hf-card-head"><div><h2>Header Logo</h2><p>Approved Emerald Rozalia wordmarks only.</p></div></div>
                    <div class="hf-card-body"><div class="hf-fields">
                        <label>Logo<select name="header_logo" data-logo-select="header" required>@foreach($approvedLogos as $logo)<option value="{{ $logo }}" @selected(old('header_logo', $headerLogo) === $logo)>{{ str($logo)->afterLast('/')->replace('_',' ')->headline() }}</option>@endforeach</select></label>
                        <label>Alt text<input name="header_logo_alt" maxlength="180" required value="{{ old('header_logo_alt', data_get($regions, 'header.logo.alt', 'Emerald Rozalia Limited')) }}"></label>
                        <div class="hf-logo-preview hf-wide"><img data-logo-preview="header" src="{{ old('header_logo', $headerLogo) }}" alt="Header logo preview"></div>
                    </div></div>
                </section>

                <section class="hf-card">
                    <div class="hf-card-head"><div><h2>Header Navigation</h2><p>Add, edit, reorder, enable/disable or delete menu items.</p></div><button type="button" class="hf-btn hf-btn--soft" data-add-nav>Add Menu Item</button></div>
                    <div class="hf-card-body"><div class="hf-list" data-nav-list>
                        @forelse(old('navigation', $navigation) as $index => $item)
                            <div class="hf-row" data-nav-row>
                                <div class="hf-row-head"><strong>Menu item {{ $index + 1 }}</strong><div class="hf-row-actions"><button type="button" class="hf-mini" data-move="up">↑</button><button type="button" class="hf-mini" data-move="down">↓</button><button type="button" class="hf-mini hf-mini--danger" data-remove-row>Delete</button></div></div>
                                <div class="hf-row-fields"><label class="hf-field">Label<input name="navigation[{{ $index }}][label]" maxlength="120" required value="{{ data_get($item, 'label') }}"></label><label class="hf-field">Destination<input name="navigation[{{ $index }}][href]" required value="{{ data_get($item, 'href') }}" placeholder="/shop"></label><label class="hf-check"><input type="hidden" name="navigation[{{ $index }}][enabled]" value="0"><input type="checkbox" name="navigation[{{ $index }}][enabled]" value="1" @checked(!array_key_exists('enabled', $item) || filter_var(data_get($item,'enabled'), FILTER_VALIDATE_BOOLEAN))> Show</label></div>
                            </div>
                        @empty
                            <div class="hf-empty" data-empty-nav>No navigation items. Add one above.</div>
                        @endforelse
                    </div></div>
                </section>

                <section class="hf-card">
                    <div class="hf-card-head"><div><h2>Footer Identity</h2><p>Footer logo and public brand text.</p></div></div>
                    <div class="hf-card-body"><div class="hf-fields">
                        <label>Footer logo<select name="footer_logo" data-logo-select="footer" required>@foreach($approvedLogos as $logo)<option value="{{ $logo }}" @selected(old('footer_logo', $footerLogo) === $logo)>{{ str($logo)->afterLast('/')->replace('_',' ')->headline() }}</option>@endforeach</select></label>
                        <label>Footer logo alt<input name="footer_logo_alt" maxlength="180" required value="{{ old('footer_logo_alt', data_get($regions, 'footer.logo.alt', 'Emerald Rozalia Limited')) }}"></label>
                        <div class="hf-logo-preview hf-wide"><img data-logo-preview="footer" src="{{ old('footer_logo', $footerLogo) }}" alt="Footer logo preview"></div>
                        <label class="hf-wide">Brand description<textarea name="footer_brand_description" maxlength="500" rows="3">{{ old('footer_brand_description', data_get($regions, 'footer.brand_description')) }}</textarea></label>
                        <label>Copyright text<input name="footer_copyright_text" maxlength="240" value="{{ old('footer_copyright_text', data_get($regions, 'footer.copyright_text')) }}"></label>
                        <label>Manufacturing line<input name="footer_manufacturing_text" maxlength="240" value="{{ old('footer_manufacturing_text', data_get($regions, 'footer.manufacturing_text')) }}"></label>
                    </div></div>
                </section>

                <section class="hf-card">
                    <div class="hf-card-head"><div><h2>Footer Columns &amp; Links</h2><p>Add, edit or delete footer sections and their links.</p></div><button type="button" class="hf-btn hf-btn--soft" data-add-column>Add Footer Column</button></div>
                    <div class="hf-card-body"><div class="hf-list" data-columns-list>
                        @forelse(old('footer_columns', $footerColumns) as $columnIndex => $column)
                            <div class="hf-row" data-column-row>
                                <div class="hf-row-head"><strong>Footer column {{ $columnIndex + 1 }}</strong><button type="button" class="hf-mini hf-mini--danger" data-remove-row>Delete Column</button></div>
                                <label class="hf-field">Column title<input name="footer_columns[{{ $columnIndex }}][title]" maxlength="120" required value="{{ data_get($column, 'title') }}"></label>
                                <div class="hf-column-links" data-links-list>
                                    @foreach(array_values((array) data_get($column, 'links', [])) as $linkIndex => $link)
                                        <div class="hf-link-row" data-link-row><label class="hf-field">Link label<input name="footer_columns[{{ $columnIndex }}][links][{{ $linkIndex }}][label]" maxlength="120" required value="{{ data_get($link, 'label') }}"></label><label class="hf-field">Destination<input name="footer_columns[{{ $columnIndex }}][links][{{ $linkIndex }}][href]" required value="{{ data_get($link, 'href') }}"></label><button type="button" class="hf-mini hf-mini--danger" data-remove-row>Delete Link</button></div>
                                    @endforeach
                                </div>
                                <div style="margin-top:10px"><button type="button" class="hf-mini" data-add-link>Add Link</button></div>
                            </div>
                        @empty
                            <div class="hf-empty" data-empty-footer>No footer columns configured.</div>
                        @endforelse
                    </div></div>
                </section>

                <section class="hf-card">
                    <div class="hf-card-head"><div><h2>Footer Legal Links</h2><p>Add, edit or delete bottom footer links.</p></div><button type="button" class="hf-btn hf-btn--soft" data-add-legal>Add Legal Link</button></div>
                    <div class="hf-card-body"><div class="hf-list" data-legal-list>
                        @forelse(old('footer_legal_links', $legalLinks) as $index => $link)
                            <div class="hf-link-row hf-row" data-legal-row><label class="hf-field">Label<input name="footer_legal_links[{{ $index }}][label]" maxlength="120" required value="{{ data_get($link, 'label') }}"></label><label class="hf-field">Destination<input name="footer_legal_links[{{ $index }}][href]" required value="{{ data_get($link, 'href') }}"></label><button type="button" class="hf-mini hf-mini--danger" data-remove-row>Delete</button></div>
                        @empty
                            <div class="hf-empty" data-empty-legal>No legal links configured.</div>
                        @endforelse
                    </div></div>
                </section>

                <div class="hf-savebar"><button class="hf-btn" type="submit">Save Header &amp; Footer Draft</button></div>
            </main>

            <aside class="hf-side">
                <section class="hf-card"><div class="hf-card-head"><div><h2>Publishing</h2><p>Versioned and audited.</p></div></div><div class="hf-card-body"><div class="hf-note">Save first. The live website changes only after Publish.</div>@if($draft)<a class="hf-btn hf-btn--soft" href="{{ route('admin.pages.layouts.preview', $draft) }}" target="_blank" rel="noopener">Preview Draft</a><button class="hf-btn" type="submit" form="hf-publish-form">Publish Draft</button>@else<div class="hf-note">No draft yet. Saving creates one automatically.</div>@endif</div></section>
                <section class="hf-card"><div class="hf-card-head"><div><h2>Public Sync</h2><p>Single source of truth.</p></div></div><div class="hf-card-body"><div class="hf-live-preview"><strong>Header &amp; Footer Manager</strong><small>Uses the same Site Layout snapshot rendered by every public page.</small><a class="hf-btn hf-btn--soft" href="{{ url('/') }}" target="_blank" rel="noopener">View Storefront</a></div></div></section>
                <section class="hf-card"><div class="hf-card-head"><div><h2>Safety</h2></div></div><div class="hf-card-body"><div class="hf-note">Logo choices are restricted to approved Emerald Rozalia assets. Menu/footer destinations are checked as safe local paths or HTTPS URLs.</div></div></section>
            </aside>
        </div>
    </form>

    @if($draft)<form id="hf-publish-form" method="post" action="{{ route('admin.pages.header-footer.publish', $draft) }}" onsubmit="return confirm('Publish this header and footer draft to the live public website?')">@csrf</form>@endif
</div>
@endsection

@push('scripts')
<script>
(function(){
    var root=document.querySelector('[data-hf-manager]'); if(!root)return;
    root.querySelectorAll('[data-logo-select]').forEach(function(select){select.addEventListener('change',function(){var img=root.querySelector('[data-logo-preview="'+select.dataset.logoSelect+'"]');if(img)img.src=select.value;});});
    function renumber(){
        root.querySelectorAll('[data-nav-row]').forEach(function(row,i){row.querySelector('strong').textContent='Menu item '+(i+1);row.querySelectorAll('[name]').forEach(function(f){f.name=f.name.replace(/navigation\\[\\d+\\]/,'navigation['+i+']');});});
        root.querySelectorAll('[data-column-row]').forEach(function(col,ci){col.querySelector('.hf-row-head strong').textContent='Footer column '+(ci+1);col.querySelectorAll('[name]').forEach(function(f){f.name=f.name.replace(/footer_columns\\[\\d+\\]/,'footer_columns['+ci+']');});col.querySelectorAll('[data-link-row]').forEach(function(link,li){link.querySelectorAll('[name]').forEach(function(f){f.name=f.name.replace(/\\[links\\]\\[\\d+\\]/,'[links]['+li+']');});});});
        root.querySelectorAll('[data-legal-row]').forEach(function(row,i){row.querySelectorAll('[name]').forEach(function(f){f.name=f.name.replace(/footer_legal_links\\[\\d+\\]/,'footer_legal_links['+i+']');});});
    }
    root.addEventListener('click',function(e){
        var remove=e.target.closest('[data-remove-row]'); if(remove){var row=remove.closest('[data-nav-row],[data-link-row],[data-column-row],[data-legal-row]');if(row)row.remove();renumber();return;}
        var move=e.target.closest('[data-move]'); if(move){var n=move.closest('[data-nav-row]');if(!n)return;if(move.dataset.move==='up'&&n.previousElementSibling&&n.previousElementSibling.matches('[data-nav-row]'))n.parentNode.insertBefore(n,n.previousElementSibling);if(move.dataset.move==='down'&&n.nextElementSibling&&n.nextElementSibling.matches('[data-nav-row]'))n.parentNode.insertBefore(n.nextElementSibling,n);renumber();return;}
        if(e.target.closest('[data-add-nav]')){root.querySelector('[data-empty-nav]')?.remove();var list=root.querySelector('[data-nav-list]'),i=list.querySelectorAll('[data-nav-row]').length;list.insertAdjacentHTML('beforeend','<div class="hf-row" data-nav-row><div class="hf-row-head"><strong>Menu item '+(i+1)+'</strong><div class="hf-row-actions"><button type="button" class="hf-mini" data-move="up">↑</button><button type="button" class="hf-mini" data-move="down">↓</button><button type="button" class="hf-mini hf-mini--danger" data-remove-row>Delete</button></div></div><div class="hf-row-fields"><label class="hf-field">Label<input name="navigation['+i+'][label]" maxlength="120" required></label><label class="hf-field">Destination<input name="navigation['+i+'][href]" required placeholder="/page"></label><label class="hf-check"><input type="hidden" name="navigation['+i+'][enabled]" value="0"><input type="checkbox" name="navigation['+i+'][enabled]" value="1" checked> Show</label></div></div>');return;}
        if(e.target.closest('[data-add-column]')){root.querySelector('[data-empty-footer]')?.remove();var list2=root.querySelector('[data-columns-list]'),ci=list2.querySelectorAll('[data-column-row]').length;list2.insertAdjacentHTML('beforeend','<div class="hf-row" data-column-row><div class="hf-row-head"><strong>Footer column '+(ci+1)+'</strong><button type="button" class="hf-mini hf-mini--danger" data-remove-row>Delete Column</button></div><label class="hf-field">Column title<input name="footer_columns['+ci+'][title]" maxlength="120" required></label><div class="hf-column-links" data-links-list></div><div style="margin-top:10px"><button type="button" class="hf-mini" data-add-link>Add Link</button></div></div>');return;}
        var addLink=e.target.closest('[data-add-link]');if(addLink){var col=addLink.closest('[data-column-row]'),list3=col.querySelector('[data-links-list]'),ci2=Array.prototype.indexOf.call(root.querySelectorAll('[data-column-row]'),col),li=list3.querySelectorAll('[data-link-row]').length;list3.insertAdjacentHTML('beforeend','<div class="hf-link-row" data-link-row><label class="hf-field">Link label<input name="footer_columns['+ci2+'][links]['+li+'][label]" maxlength="120" required></label><label class="hf-field">Destination<input name="footer_columns['+ci2+'][links]['+li+'][href]" required></label><button type="button" class="hf-mini hf-mini--danger" data-remove-row>Delete Link</button></div>');return;}
        if(e.target.closest('[data-add-legal]')){root.querySelector('[data-empty-legal]')?.remove();var list4=root.querySelector('[data-legal-list]'),j=list4.querySelectorAll('[data-legal-row]').length;list4.insertAdjacentHTML('beforeend','<div class="hf-link-row hf-row" data-legal-row><label class="hf-field">Label<input name="footer_legal_links['+j+'][label]" maxlength="120" required></label><label class="hf-field">Destination<input name="footer_legal_links['+j+'][href]" required></label><button type="button" class="hf-mini hf-mini--danger" data-remove-row>Delete</button></div>');}
    });
    root.querySelector('[data-hf-form]')?.addEventListener('submit',renumber);
})();
</script>
@endpush
