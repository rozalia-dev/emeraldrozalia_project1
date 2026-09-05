@extends('layouts.admin')

@php
    $isCreate = ! $page->exists;
    $meta = is_array($page->meta) ? $page->meta : [];
    $settings = is_array($meta['settings'] ?? null) ? $meta['settings'] : [];
    $sections = $page->relationLoaded('sections') ? $page->sections : collect();
    $devices = $settings['devices'] ?? ['desktop', 'tablet', 'mobile'];
    $builderInitial = $sections->map(fn ($section) => [
        'type' => $section->type,
        'label' => $section->label,
        'settings' => $section->settings ?: [],
        'visible' => (bool) $section->visible,
    ])->values()->all();
@endphp

@section('title', $isCreate ? 'Create Page' : 'Edit ' . $page->title)

@section('content')

<div class="page-builder-screen" data-page-builder data-builder-initial="{{ base64_encode(json_encode($builderInitial)) }}">
    <header class="page-builder-heading">
        <div>
            <nav class="page-builder-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('admin.pages') }}">Pages</a><x-icon name="chevron-right" size="13" /><span>{{ $isCreate ? 'Create Page' : 'Edit Page' }}</span></nav>
            <p class="pages-eyebrow">PREMIUM PAGE BUILDER / CONTENT CONTROL</p>
            <h1>{{ $isCreate ? 'Create New Page' : 'Edit Page' }}</h1>
            <p>{{ $isCreate ? 'Build a complete public or private page with reusable sections, search settings and controlled publishing.' : 'Update every content, SEO, access and publishing setting for this page from one revision-controlled workspace.' }}</p>
        </div>
        <div class="page-builder-heading-actions"><a class="pages-secondary-button" href="{{ route('admin.pages') }}"><x-icon name="arrow-left" size="15" /> Back to Pages</a>@if(! $isCreate)<a class="pages-secondary-button" href="{{ route('admin.pages.preview', $page) }}" target="_blank"><x-icon name="eye" size="15" /> Preview</a>@endif</div>
    </header>

    <form class="page-builder-form" id="page-builder-form" method="post" action="{{ $isCreate ? route('admin.pages.store') : route('admin.pages.update', $page) }}" data-page-form>
        @csrf
        @unless($isCreate) @method('PUT') @endunless
        <input type="hidden" name="sections" value="{{ json_encode($builderInitial) }}" data-page-sections>

        <div class="page-builder-layout">
            <main class="page-builder-main">
                <section class="page-builder-card" id="details">
                    <div class="page-builder-card-heading"><div><span class="page-builder-step">01</span><div><h2>Page details</h2><p>Set the identity, URL and editorial content for this page.</p></div></div><span class="page-builder-state">Core content</span></div>
                    <div class="page-builder-fields page-builder-fields--details">
                        <label>Page title <span class="field-required">Required</span><input name="title" value="{{ old('title', $page->title) }}" required data-builder-field="title" placeholder="About Emerald Rozalia"></label>
                        <label>URL slug <span class="field-required">Required</span><input name="slug" value="{{ old('slug', $page->slug) }}" required data-builder-field="slug" placeholder="about-emerald-rozalia"><small>Use lowercase letters, numbers and hyphens.</small></label>
                        <label class="page-builder-field-wide">Short description<textarea name="intro" rows="3" data-builder-field="intro" placeholder="A concise introduction shown in page previews and search results.">{{ old('intro', $page->intro) }}</textarea></label>
                        <label class="page-builder-field-wide">Page body<textarea name="body" rows="8" data-builder-field="body" placeholder="Write editorial copy or use structured sections below.">{{ old('body', $page->body) }}</textarea><small>Plain text and line breaks are preserved. Structured blocks are stored separately for future rendering.</small></label>
                    </div>
                </section>

                <section class="page-builder-card" id="sections">
                    <div class="page-builder-card-heading"><div><span class="page-builder-step">02</span><div><h2>Page sections</h2><p>Compose the page with reusable blocks and control their order.</p></div></div><span class="page-builder-state" data-builder-count>0 blocks</span></div>
                    <div class="page-builder-studio">
                        <aside class="page-builder-library"><div><h3>Section library</h3><p>Choose a block to add it to the canvas.</p></div><button type="button" data-builder-add="hero"><x-icon name="image" size="17" /><span><strong>Hero section</strong><small>Headline, media and intro</small></span><x-icon name="plus" size="14" /></button><button type="button" data-builder-add="content"><x-icon name="file-text" size="17" /><span><strong>Rich content</strong><small>Editorial text and details</small></span><x-icon name="plus" size="14" /></button><button type="button" data-builder-add="gallery"><x-icon name="camera" size="17" /><span><strong>Media gallery</strong><small>Images, captions and links</small></span><x-icon name="plus" size="14" /></button><button type="button" data-builder-add="cta"><x-icon name="arrow-right" size="17" /><span><strong>Call to action</strong><small>Conversion-focused panel</small></span><x-icon name="plus" size="14" /></button><button type="button" data-builder-add="form"><x-icon name="message" size="17" /><span><strong>Enquiry form</strong><small>Capture customer requests</small></span><x-icon name="plus" size="14" /></button></aside>
                        <div class="page-builder-canvas"><div class="page-builder-canvas-heading"><div><h3>Canvas</h3><p>Drag-free controls keep the order predictable and auditable.</p></div><span data-builder-count>0 blocks</span></div><div class="page-builder-block-list" data-builder-list></div><div class="page-builder-empty" data-builder-empty><x-icon name="file-text" size="25" /><strong>Your page is ready for sections</strong><span>Add a block from the library to start building.</span></div></div>
                    </div>
                </section>

                <section class="page-builder-card" id="seo">
                    <div class="page-builder-card-heading"><div><span class="page-builder-step">03</span><div><h2>SEO &amp; content settings</h2><p>Make the page clear, discoverable and ready for search.</p></div></div><span class="page-builder-state">Search settings</span></div>
                    <div class="page-builder-fields page-builder-fields--seo"><label>Meta title<input name="meta_title" value="{{ old('meta_title', $meta['title'] ?? $page->title) }}" data-builder-field="meta_title" placeholder="Page title — Emerald Rozalia"></label><label>Meta keywords<input name="meta_keywords" value="{{ old('meta_keywords', $meta['keywords'] ?? '') }}" data-builder-field="meta_keywords" placeholder="heritage, hats, limerick"></label><label class="page-builder-field-wide">Meta description<textarea name="meta_description" rows="3" data-builder-field="meta_description" placeholder="Describe the page in 150–160 characters.">{{ old('meta_description', $meta['description'] ?? $page->intro) }}</textarea></label></div>
                </section>

                <section class="page-builder-card" id="settings">
                    <div class="page-builder-card-heading"><div><span class="page-builder-step">04</span><div><h2>Page settings</h2><p>Choose the template, language and navigation behavior.</p></div></div><span class="page-builder-state">Presentation</span></div>
                    <div class="page-builder-fields page-builder-fields--settings"><label>Template<select name="template"><option value="standard" @selected(old('template', $page->template ?: 'standard') === 'standard')>Standard page</option><option value="landing" @selected(old('template', $page->template) === 'landing')>Landing page</option><option value="legal" @selected(old('template', $page->template) === 'legal')>Legal page</option><option value="system" @selected(old('template', $page->template) === 'system')>System page</option></select></label><label>Language / locale<input name="locale" value="{{ old('locale', $page->locale ?: 'en') }}" maxlength="10" placeholder="en"></label><div class="page-builder-check-grid page-builder-field-wide"><label><input type="checkbox" name="navigation_visible" value="1" @checked(old('navigation_visible', $page->navigation_visible))> Show in main navigation</label><label><input type="checkbox" name="show_in_footer" value="1" @checked(old('show_in_footer', $settings['show_in_footer'] ?? false))> Show in footer navigation</label><label><input type="checkbox" name="indexable" value="1" @checked(old('indexable', $settings['indexable'] ?? true))> Allow search engine indexing</label></div></div>
                </section>

                <section class="page-builder-card" id="access">
                    <div class="page-builder-card-heading"><div><span class="page-builder-step">05</span><div><h2>Visibility &amp; access</h2><p>Control who can see this page and on which devices.</p></div></div><span class="page-builder-state">Access policy</span></div>
                    <div class="page-builder-fields page-builder-fields--access"><label>Visibility<select name="visibility"><option value="public" @selected(old('visibility', $settings['visibility'] ?? 'public') === 'public')>Visible to everyone</option><option value="private" @selected(old('visibility', $settings['visibility'] ?? '') === 'private')>Private / admin preview</option></select></label><label>Country restriction<input name="country_restriction" value="{{ old('country_restriction', $settings['country_restriction'] ?? '') }}" placeholder="All countries"></label><label class="page-builder-switch"><input type="checkbox" name="login_required" value="1" @checked(old('login_required', $settings['login_required'] ?? false))><span><strong>Login required</strong><small>Require an authenticated customer before showing this page.</small></span></label><div class="page-builder-device-list"><span>Device visibility</span><label><input type="checkbox" name="devices[]" value="desktop" @checked(in_array('desktop', old('devices', $devices), true))> Desktop</label><label><input type="checkbox" name="devices[]" value="tablet" @checked(in_array('tablet', old('devices', $devices), true))> Tablet</label><label><input type="checkbox" name="devices[]" value="mobile" @checked(in_array('mobile', old('devices', $devices), true))> Mobile</label></div></div>
                </section>
            </main>

            <aside class="page-builder-sidebar">
                <section class="page-builder-side-card page-builder-publish-card"><div class="page-builder-side-heading"><h2>Publish</h2><span class="page-builder-live-dot"></span></div><label>Publish status<select name="status"><option value="draft" @selected(old('status', $page->status ?: 'draft') === 'draft')>Draft</option><option value="review" @selected(old('status', $page->status) === 'review')>In review</option><option value="scheduled" @selected(old('status', $page->status) === 'scheduled')>Scheduled</option><option value="published" @selected(old('status', $page->status) === 'published')>Published</option><option value="unpublished" @selected(old('status', $page->status) === 'unpublished')>Hidden</option><option value="archived" @selected(old('status', $page->status) === 'archived')>Archived</option></select></label><label>Schedule date<input type="datetime-local" name="scheduled_for" value="{{ old('scheduled_for', $page->scheduled_for?->format('Y-m-d\TH:i')) }}"></label><p class="page-builder-help"><x-icon name="check" size="14" /> Saving creates a new revision snapshot.</p><button class="page-builder-save" type="submit"><x-icon name="check" size="16" /> {{ $isCreate ? 'Create Page' : 'Save Changes' }}</button><a class="page-builder-cancel" href="{{ route('admin.pages') }}">Cancel and return</a></section>
                <section class="page-builder-side-card"><div class="page-builder-side-heading"><h2>Page preview</h2></div><div class="page-builder-preview-frame"><span>{{ $page->title ?: 'Your page title' }}</span><small>{{ $page->intro ?: 'A live preview is available after the first save.' }}</small></div>@if(! $isCreate)<a class="page-builder-preview-link" href="{{ route('admin.pages.preview', $page) }}" target="_blank">Open preview <x-icon name="arrow-right" size="14" /></a>@else<span class="page-builder-muted">Save this page to enable a preview URL.</span>@endif</section>
                <section class="page-builder-side-card"><div class="page-builder-side-heading"><h2>Traceability</h2></div><dl class="page-builder-meta-list"><div><dt>UUID</dt><dd>{{ $page->uuid ?: 'Assigned on save' }}</dd></div><div><dt>Created</dt><dd>{{ $page->created_at?->format('d M Y, H:i') ?: 'On first save' }}</dd></div><div><dt>Revisions</dt><dd>{{ $page->revisions?->count() ?? 0 }}</dd></div></dl></section>
                @if(! $isCreate)<section class="page-builder-side-card"><div class="page-builder-side-heading"><h2>Revision history</h2><span>{{ $page->revisions->count() }}</span></div><div class="page-builder-side-revisions">@forelse($page->revisions->take(5) as $revision)<div><span><strong>Version {{ $revision->version }}</strong><small>{{ $revision->reason ?: 'Content update' }}<br>{{ $revision->created_at?->format('d M Y, H:i') }}</small></span><form method="post" action="{{ route('admin.pages.revisions.restore', [$page, $revision]) }}">@csrf<button type="submit">Restore</button></form></div>@empty<p class="page-builder-muted">No revisions have been recorded.</p>@endforelse</div><a class="page-builder-audit-link" href="{{ route('admin.pages', ['edit' => $page->id]) }}#page-revisions">View full audit trail <x-icon name="arrow-right" size="14" /></a></section>@endif
            </aside>
        </div>
    </form>
</div>
@endsection
