@php
    $regionValue = static fn (string $path, mixed $fallback = ''): mixed => data_get($editorRegions, $path, $fallback);
    $regionList = static fn (string $path, array $fallback = []): array => array_values((array) data_get($editorRegions, $path, $fallback));
    $primaryMenu = $regionList('header.primary_menu', array_values($defaults['header']['primary_menu'] ?? []));
    $utilityMenu = $regionList('header.utility_menu', $defaults['header']['utility_menu'] ?? []);
    $footerColumns = $regionList('footer.columns', $defaults['footer']['columns'] ?? []);
    $officialSocialLinks = [
        ['label' => 'Facebook', 'icon' => 'facebook', 'href' => 'https://www.facebook.com/emeraldrozalia/'],
        ['label' => 'Instagram', 'icon' => 'instagram', 'href' => 'https://www.instagram.com/emeraldrozalia2020/'],
        ['label' => 'X', 'icon' => 'x', 'href' => 'https://x.com/EmeraldRozalia'],
        ['label' => 'TikTok', 'icon' => 'tiktok', 'href' => 'https://www.tiktok.com/@emeraldrozalia1?lang=en'],
        ['label' => 'YouTube', 'icon' => 'youtube', 'href' => 'https://www.youtube.com/@EmeraldRozalia-w4p'],
        ['label' => 'LinkedIn', 'icon' => 'linkedin', 'href' => 'https://www.linkedin.com/in/emerald-rozalia-24921b410/'],
    ];
    $socialLinks = $regionList('footer.social_links', $officialSocialLinks);
    if ($socialLinks === []) {
        $socialLinks = $officialSocialLinks;
    }
    $legalLinks = $regionList('footer.legal_links', $defaults['footer']['legal_links'] ?? []);
@endphp

<div class="layout-visual-editor" data-layout-visual-editor>
    <input type="hidden" name="layout_lists_managed" value="1">
    <section class="layout-editor-group">
        <div class="layout-editor-group-heading">
            <div><span class="layout-editor-number">01</span><div><h3>Header announcement</h3><p>Set the small public message above the approved wordmark.</p></div></div>
        </div>
        <div class="layout-editor-fields">
            <label>Eyebrow<input name="regions[header][announcement][eyebrow]" maxlength="180" value="{{ $regionValue('header.announcement.eyebrow') }}" placeholder="Proudly Manufacturing in Limerick, Ireland"></label>
            <label>Headline<input name="regions[header][announcement][headline]" maxlength="180" value="{{ $regionValue('header.announcement.headline') }}" placeholder="Irish Made. Limerick Born. Worn Everywhere."></label>
        </div>
    </section>

    <section class="layout-editor-group">
        <div class="layout-editor-group-heading">
            <div><span class="layout-editor-number">02</span><div><h3>Approved header logo</h3><p>Only the supplied Emerald Rozalia wordmarks can be selected for the public shell.</p></div></div>
            <span class="layout-editor-badge">Approved assets only</span>
        </div>
        <div class="layout-editor-fields">
            <label>Logo asset<select name="regions[header][logo][path]">
                <option value="/assets/logo/logo_one_line.png" @selected($regionValue('header.logo.path', '/assets/logo/logo_one_line.png') === '/assets/logo/logo_one_line.png')>Approved one-line wordmark</option>
                <option value="/assets/logo/logo_two_line.png" @selected($regionValue('header.logo.path') === '/assets/logo/logo_two_line.png')>Approved two-line wordmark</option>
            </select></label>
            <label>Logo alt text<input name="regions[header][logo][alt]" maxlength="180" value="{{ $regionValue('header.logo.alt', 'Emerald Rozalia Limited') }}" placeholder="Emerald Rozalia Limited"></label>
        </div>
    </section>

    <section class="layout-editor-group">
        <div class="layout-editor-group-heading">
            <div><span class="layout-editor-number">02A</span><div><h3>Header colours</h3><p>Control the public header background, text and active/accent colour.</p></div></div>
        </div>
        <div class="layout-editor-fields">
            @foreach(['background' => 'Header background', 'text' => 'Header text', 'accent' => 'Header accent'] as $colorKey => $colorLabel)
                <label>{{ $colorLabel }}<span class="layout-colour-field"><input type="color" name="regions[header][colors][{{ $colorKey }}]" value="{{ $regionValue('header.colors.'.$colorKey, data_get($defaults, 'header.colors.'.$colorKey)) }}"></span></label>
            @endforeach
        </div>
    </section>

    <section class="layout-editor-group">
        <div class="layout-editor-group-heading">
            <div><span class="layout-editor-number">03</span><div><h3>Primary menu</h3><p>Add, edit, reorder, enable/disable or delete any public navigation item. Safe local and HTTPS destinations are validated on save.</p></div></div>
            <button class="layout-editor-add" type="button" data-layout-add="primary-menu">Add menu item</button>
        </div>
        <div class="layout-repeat-list" data-layout-list="primary-menu">
            @foreach($primaryMenu as $index => $item)
                <div class="layout-repeat-row" data-layout-row data-layout-index="{{ $index }}">
                    <div class="layout-repeat-row-heading">
                        <strong>Menu item {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</strong>
                        <span class="layout-actions">
                            <button class="layout-editor-add" type="button" data-layout-move="up" aria-label="Move menu item up">↑</button>
                            <button class="layout-editor-add" type="button" data-layout-move="down" aria-label="Move menu item down">↓</button>
                            <button class="layout-editor-remove" type="button" data-layout-remove>Remove</button>
                        </span>
                    </div>
                    <div class="layout-editor-fields">
                        <label>Label<input name="regions[header][primary_menu][{{ $index }}][label]" maxlength="120" value="{{ data_get($item, 'label') }}" placeholder="SHOP"></label>
                        <label>Destination<input name="regions[header][primary_menu][{{ $index }}][href]" value="{{ data_get($item, 'href') }}" placeholder="/shop"></label>
                        <label class="layout-editor-check"><input type="hidden" name="regions[header][primary_menu][{{ $index }}][enabled]" value="0"><input type="checkbox" name="regions[header][primary_menu][{{ $index }}][enabled]" value="1" @checked(!array_key_exists('enabled', $item) || filter_var(data_get($item, 'enabled'), FILTER_VALIDATE_BOOLEAN))> Show in header</label>
                    </div>
                </div>
            @endforeach
        </div>
        <template data-layout-template="primary-menu"><div class="layout-repeat-row" data-layout-row data-layout-index="__INDEX__"><div class="layout-repeat-row-heading"><strong>New menu item</strong><span class="layout-actions"><button class="layout-editor-add" type="button" data-layout-move="up">↑</button><button class="layout-editor-add" type="button" data-layout-move="down">↓</button><button class="layout-editor-remove" type="button" data-layout-remove>Remove</button></span></div><div class="layout-editor-fields"><label>Label<input name="regions[header][primary_menu][__INDEX__][label]" maxlength="120" placeholder="ABOUT US"></label><label>Destination<input name="regions[header][primary_menu][__INDEX__][href]" placeholder="/about"></label><label class="layout-editor-check"><input type="hidden" name="regions[header][primary_menu][__INDEX__][enabled]" value="0"><input type="checkbox" name="regions[header][primary_menu][__INDEX__][enabled]" value="1" checked> Show in header</label></div></div></template>
    </section>

    <section class="layout-editor-group">
        <div class="layout-editor-group-heading">
            <div><span class="layout-editor-number">04</span><div><h3>Utility menu</h3><p>Configure the search, login/account and cart controls shown on every public page.</p></div></div>
            <button class="layout-editor-add" type="button" data-layout-add="utility-menu">Add utility item</button>
        </div>
        <div class="layout-repeat-list" data-layout-list="utility-menu">
            @foreach($utilityMenu as $index => $item)
                <div class="layout-repeat-row" data-layout-row data-layout-index="{{ $index }}">
                    <div class="layout-repeat-row-heading"><strong>Utility item {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</strong><button class="layout-editor-remove" type="button" data-layout-remove>Remove</button></div>
                    <div class="layout-editor-fields layout-editor-fields--utility">
                        <label>Label<input name="regions[header][utility_menu][{{ $index }}][label]" maxlength="120" value="{{ data_get($item, 'label') }}" placeholder="Search"></label>
                        <label>Icon<select name="regions[header][utility_menu][{{ $index }}][icon]"><option value="search" @selected(data_get($item, 'icon') === 'search')>Search</option><option value="user" @selected(data_get($item, 'icon') === 'user')>User</option><option value="shopping-bag" @selected(data_get($item, 'icon') === 'shopping-bag')>Shopping bag</option><option value="link" @selected(data_get($item, 'icon') === 'link')>Link</option></select></label>
                        <label>Guest label<input name="regions[header][utility_menu][{{ $index }}][guest_label]" maxlength="120" value="{{ data_get($item, 'guest_label') }}" placeholder="Login"></label>
                        <label>Signed-in label<input name="regions[header][utility_menu][{{ $index }}][auth_label]" maxlength="120" value="{{ data_get($item, 'auth_label') }}" placeholder="Account"></label>
                        <label>Guest destination<input name="regions[header][utility_menu][{{ $index }}][href]" value="{{ data_get($item, 'href') }}" placeholder="/login"></label>
                        <label>Signed-in destination<input name="regions[header][utility_menu][{{ $index }}][auth_href]" value="{{ data_get($item, 'auth_href') }}" placeholder="/account"></label>
                    </div>
                </div>
            @endforeach
        </div>
        <template data-layout-template="utility-menu"><div class="layout-repeat-row" data-layout-row data-layout-index="__INDEX__"><div class="layout-repeat-row-heading"><strong>New utility item</strong><button class="layout-editor-remove" type="button" data-layout-remove>Remove</button></div><div class="layout-editor-fields layout-editor-fields--utility"><label>Label<input name="regions[header][utility_menu][__INDEX__][label]" maxlength="120" placeholder="Search"></label><label>Icon<select name="regions[header][utility_menu][__INDEX__][icon]"><option value="search">Search</option><option value="user">User</option><option value="shopping-bag">Shopping bag</option><option value="link">Link</option></select></label><label>Guest label<input name="regions[header][utility_menu][__INDEX__][guest_label]" maxlength="120" placeholder="Login"></label><label>Signed-in label<input name="regions[header][utility_menu][__INDEX__][auth_label]" maxlength="120" placeholder="Account"></label><label>Guest destination<input name="regions[header][utility_menu][__INDEX__][href]" placeholder="/login"></label><label>Signed-in destination<input name="regions[header][utility_menu][__INDEX__][auth_href]" placeholder="/account"></label></div></div></template>
    </section>

    <section class="layout-editor-group">
        <div class="layout-editor-group-heading">
            <div><span class="layout-editor-number">04A</span><div><h3>Footer logo &amp; colours</h3><p>Select the approved footer wordmark and control footer background, text and accent colours.</p></div></div>
            <span class="layout-editor-badge">Approved assets only</span>
        </div>
        <div class="layout-editor-fields">
            <label>Footer logo asset<select name="regions[footer][logo][path]">
                <option value="/assets/logo/logo_one_line.png" @selected($regionValue('footer.logo.path') === '/assets/logo/logo_one_line.png')>Approved one-line wordmark</option>
                <option value="/assets/logo/logo_two_line.png" @selected($regionValue('footer.logo.path', '/assets/logo/logo_two_line.png') === '/assets/logo/logo_two_line.png')>Approved two-line wordmark</option>
            </select></label>
            <label>Footer logo alt text<input name="regions[footer][logo][alt]" maxlength="180" value="{{ $regionValue('footer.logo.alt', 'Emerald Rozalia Limited') }}"></label>
            @foreach(['background' => 'Footer background', 'text' => 'Footer text', 'accent' => 'Footer accent'] as $colorKey => $colorLabel)
                <label>{{ $colorLabel }}<input type="color" name="regions[footer][colors][{{ $colorKey }}]" value="{{ $regionValue('footer.colors.'.$colorKey, data_get($defaults, 'footer.colors.'.$colorKey)) }}"></label>
            @endforeach
        </div>
    </section>

    <section class="layout-editor-group">
        <div class="layout-editor-group-heading">
            <div><span class="layout-editor-number">05</span><div><h3>Footer identity &amp; newsletter</h3><p>Control the footer message, newsletter destination and call-to-action label.</p></div></div>
        </div>
        <div class="layout-editor-fields">
            <label class="layout-editor-field-wide">Brand description<textarea name="regions[footer][brand_description]" maxlength="500" rows="3" placeholder="Proudly manufacturing hats and caps in Limerick, Ireland.">{{ $regionValue('footer.brand_description') }}</textarea></label>
            <label class="layout-editor-check"><input type="hidden" name="regions[footer][newsletter][enabled]" value="0"><input type="checkbox" name="regions[footer][newsletter][enabled]" value="1" @checked(filter_var($regionValue('footer.newsletter.enabled', false), FILTER_VALIDATE_BOOLEAN))> Enable newsletter panel</label>
            <label>Newsletter title<input name="regions[footer][newsletter][title]" maxlength="120" value="{{ $regionValue('footer.newsletter.title', 'NEWSLETTER') }}" placeholder="NEWSLETTER"></label>
            <label>Newsletter description<textarea name="regions[footer][newsletter][description]" maxlength="500" rows="3" placeholder="Stay updated with new arrivals and offers.">{{ $regionValue('footer.newsletter.description', 'Stay updated with new arrivals and offers.') }}</textarea></label>
            <label>Newsletter button label<input name="regions[footer][newsletter][cta_label]" maxlength="120" value="{{ $regionValue('footer.newsletter.cta_label', 'Contact our team') }}" placeholder="Contact our team"></label>
            <label>Newsletter destination<input name="regions[footer][newsletter][href]" value="{{ $regionValue('footer.newsletter.href', '/contact') }}" placeholder="/contact"></label>
        </div>
    </section>

    <section class="layout-editor-group">
        <div class="layout-editor-group-heading">
            <div><span class="layout-editor-number">06</span><div><h3>Footer columns</h3><p>Edit grouped customer-facing links. Safe local paths and HTTPS destinations are checked on save.</p></div></div>
            <button class="layout-editor-add" type="button" data-layout-add="footer-columns">Add footer column</button>
        </div>
        <div class="layout-repeat-list" data-layout-list="footer-columns">
            @foreach($footerColumns as $columnIndex => $column)
                @php($columnLinks = array_values((array) data_get($column, 'links', [])))
                <div class="layout-repeat-row layout-repeat-row--column" data-layout-row data-layout-index="{{ $columnIndex }}">
                    <div class="layout-repeat-row-heading"><strong>Footer column {{ str_pad((string) ($columnIndex + 1), 2, '0', STR_PAD_LEFT) }}</strong><button class="layout-editor-remove" type="button" data-layout-remove>Remove</button></div>
                    <label>Column title<input name="regions[footer][columns][{{ $columnIndex }}][title]" maxlength="120" value="{{ data_get($column, 'title') }}" placeholder="CUSTOMER CARE"></label>
                    <div class="layout-link-list" data-layout-link-list="{{ $columnIndex }}">
                        @foreach($columnLinks as $linkIndex => $link)
                            <div class="layout-link-row" data-layout-row data-layout-index="{{ $linkIndex }}"><label>Link label<input name="regions[footer][columns][{{ $columnIndex }}][links][{{ $linkIndex }}][label]" maxlength="120" value="{{ data_get($link, 'label') }}" placeholder="Contact Us"></label><label>Destination<input name="regions[footer][columns][{{ $columnIndex }}][links][{{ $linkIndex }}][href]" value="{{ data_get($link, 'href') }}" placeholder="/contact"></label><button class="layout-editor-remove" type="button" data-layout-remove>Remove link</button></div>
                        @endforeach
                    </div>
                    <button class="layout-editor-add layout-editor-add--link" type="button" data-layout-add-link="{{ $columnIndex }}">Add footer link</button>
                    <template data-layout-link-template="{{ $columnIndex }}"><div class="layout-link-row" data-layout-row data-layout-index="__LINK_INDEX__"><label>Link label<input name="regions[footer][columns][{{ $columnIndex }}][links][__LINK_INDEX__][label]" maxlength="120" placeholder="Link label"></label><label>Destination<input name="regions[footer][columns][{{ $columnIndex }}][links][__LINK_INDEX__][href]" placeholder="/destination"></label><button class="layout-editor-remove" type="button" data-layout-remove>Remove link</button></div></template>
                </div>
            @endforeach
        </div>
        <template data-layout-template="footer-columns"><div class="layout-repeat-row layout-repeat-row--column" data-layout-row data-layout-index="__INDEX__"><div class="layout-repeat-row-heading"><strong>New footer column</strong><button class="layout-editor-remove" type="button" data-layout-remove>Remove</button></div><label>Column title<input name="regions[footer][columns][__INDEX__][title]" maxlength="120" placeholder="COLUMN TITLE"></label><div class="layout-link-list"><div class="layout-link-row" data-layout-row data-layout-index="0"><label>Link label<input name="regions[footer][columns][__INDEX__][links][0][label]" maxlength="120" placeholder="Link label"></label><label>Destination<input name="regions[footer][columns][__INDEX__][links][0][href]" placeholder="/destination"></label><button class="layout-editor-remove" type="button" data-layout-remove>Remove link</button></div></div><button class="layout-editor-add layout-editor-add--link" type="button" data-layout-add-link="__INDEX__">Add footer link</button><template data-layout-link-template="__INDEX__"><div class="layout-link-row" data-layout-row data-layout-index="__LINK_INDEX__"><label>Link label<input name="regions[footer][columns][__INDEX__][links][__LINK_INDEX__][label]" maxlength="120" placeholder="Link label"></label><label>Destination<input name="regions[footer][columns][__INDEX__][links][__LINK_INDEX__][href]" placeholder="/destination"></label><button class="layout-editor-remove" type="button" data-layout-remove>Remove</button></div></template></div></template>
    </section>

    <section class="layout-editor-group">
        <div class="layout-editor-group-heading">
            <div><span class="layout-editor-number">07</span><div><h3>Social profiles</h3><p>Emerald Rozalia's approved Facebook, Instagram, X, TikTok, YouTube and LinkedIn profiles are preconfigured here.</p></div></div>
            <button class="layout-editor-add" type="button" data-layout-add="social-links">Add profile</button>
        </div>
        <div class="layout-repeat-list" data-layout-list="social-links">
            @foreach($socialLinks as $index => $social)
                <div class="layout-repeat-row" data-layout-row data-layout-index="{{ $index }}"><div class="layout-repeat-row-heading"><strong>Profile {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</strong><button class="layout-editor-remove" type="button" data-layout-remove>Remove</button></div><div class="layout-editor-fields"><label>Label<input name="regions[footer][social_links][{{ $index }}][label]" maxlength="120" value="{{ data_get($social, 'label') }}" placeholder="Instagram"></label><label>Icon<select name="regions[footer][social_links][{{ $index }}][icon]"><option value="facebook" @selected(data_get($social, 'icon') === 'facebook')>Facebook</option><option value="instagram" @selected(data_get($social, 'icon') === 'instagram')>Instagram</option><option value="x" @selected(data_get($social, 'icon') === 'x')>X</option><option value="tiktok" @selected(data_get($social, 'icon') === 'tiktok')>TikTok</option><option value="linkedin" @selected(data_get($social, 'icon') === 'linkedin')>LinkedIn</option><option value="youtube" @selected(data_get($social, 'icon') === 'youtube')>YouTube</option><option value="link" @selected(data_get($social, 'icon') === 'link')>Link</option></select></label><label class="layout-editor-field-wide">HTTPS profile URL<input name="regions[footer][social_links][{{ $index }}][href]" value="{{ data_get($social, 'href') }}" placeholder="https://www.instagram.com/your-profile"></label></div></div>
            @endforeach
        </div>
        <template data-layout-template="social-links"><div class="layout-repeat-row" data-layout-row data-layout-index="__INDEX__"><div class="layout-repeat-row-heading"><strong>New profile</strong><button class="layout-editor-remove" type="button" data-layout-remove>Remove</button></div><div class="layout-editor-fields"><label>Label<input name="regions[footer][social_links][__INDEX__][label]" maxlength="120" placeholder="Instagram"></label><label>Icon<select name="regions[footer][social_links][__INDEX__][icon]"><option value="facebook">Facebook</option><option value="instagram">Instagram</option><option value="x">X</option><option value="tiktok">TikTok</option><option value="linkedin">LinkedIn</option><option value="youtube">YouTube</option><option value="link">Link</option></select></label><label class="layout-editor-field-wide">HTTPS profile URL<input name="regions[footer][social_links][__INDEX__][href]" placeholder="https://www.instagram.com/your-profile"></label></div></div></template>
    </section>

    <section class="layout-editor-group">
        <div class="layout-editor-group-heading">
            <div><span class="layout-editor-number">08</span><div><h3>Legal links</h3><p>Keep the public footer policy links visible and safely routed.</p></div></div>
            <button class="layout-editor-add" type="button" data-layout-add="legal-links">Add legal link</button>
        </div>
        <div class="layout-repeat-list" data-layout-list="legal-links">
            @foreach($legalLinks as $index => $link)
                <div class="layout-repeat-row" data-layout-row data-layout-index="{{ $index }}"><div class="layout-repeat-row-heading"><strong>Legal link {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</strong><button class="layout-editor-remove" type="button" data-layout-remove>Remove</button></div><div class="layout-editor-fields"><label>Label<input name="regions[footer][legal_links][{{ $index }}][label]" maxlength="120" value="{{ data_get($link, 'label') }}" placeholder="Privacy Policy"></label><label>Destination<input name="regions[footer][legal_links][{{ $index }}][href]" value="{{ data_get($link, 'href') }}" placeholder="/factory"></label></div></div>
            @endforeach
        </div>
        <template data-layout-template="legal-links"><div class="layout-repeat-row" data-layout-row data-layout-index="__INDEX__"><div class="layout-repeat-row-heading"><strong>New legal link</strong><button class="layout-editor-remove" type="button" data-layout-remove>Remove</button></div><div class="layout-editor-fields"><label>Label<input name="regions[footer][legal_links][__INDEX__][label]" maxlength="120" placeholder="Privacy Policy"></label><label>Destination<input name="regions[footer][legal_links][__INDEX__][href]" placeholder="/factory"></label></div></div></template>
    </section>
</div>
