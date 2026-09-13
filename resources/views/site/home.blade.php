@extends('layouts.site')
@section('body-class', 'home-body')
@section('title', 'Emerald Rozalia — Irish Made Hats & Caps')
@push('styles')
    <link rel="stylesheet" href="/css/home-collections.css?v=20260913-managed-home">
@endpush
@section('content')
    @php($homepageSections = $homepage?->sections ?? collect())
    <div class="home-page" data-homepage-page-uuid="{{ $homepage?->uuid ?: 'reserved-homepage-pending' }}" data-homepage-source="{{ $homepage ? 'content-page-active-record' : 'reserved-homepage-fallback' }}" data-homepage-renderer="persisted-page-sections">
        @if(($previewMode ?? false) || ($layoutPreviewMode ?? false))
            <div class="managed-page-preview-banner" role="status">
                Previewing {{ ($layoutPreviewMode ?? false) ? 'shared layout · Homepage render' : 'Homepage' }} · unpublished preview
                <a href="{{ ($layoutPreviewMode ?? false) ? route('admin.pages.layouts') : route('admin.pages.edit', $homepage) }}">Return to control panel</a>
            </div>
        @endif

        @forelse($homepageSections as $section)
            @if($section->visible)
                @include('site.partials.home-section', [
                    'section' => $section,
                    'categories' => $categories ?? collect(),
                    'homeProducts' => $homeProducts ?? $newProducts ?? collect(),
                    'homeLatestProducts' => $homeLatestProducts ?? $homeProducts ?? $newProducts ?? collect(),
                    'banners' => $banners ?? collect(),
                    'homeMedia' => $homeMedia ?? [],
                ])
            @endif
        @empty
            <section class="home-managed-empty-state" data-home-section="empty" aria-labelledby="homepage-empty-title">
                <p class="eyebrow">EMERALD ROZALIA</p>
                <h1 id="homepage-empty-title">Homepage content is not configured.</h1>
                <p>An administrator can add and publish homepage sections from Page Manager.</p>
            </section>
        @endforelse
    </div>
@endsection
@push('scripts')
    <script>
        (() => {
            document.querySelectorAll('[data-home-bestsellers]').forEach((root) => {
                const track = root.querySelector('[data-home-carousel-track]');
                const previous = root.querySelector('[data-home-carousel-prev]');
                const next = root.querySelector('[data-home-carousel-next]');
                if (!track || !previous || !next) return;

                const step = () => Math.max(220, Math.round(track.clientWidth * .72));
                const updateControls = () => {
                    const max = Math.max(0, track.scrollWidth - track.clientWidth - 2);
                    previous.disabled = track.scrollLeft <= 2;
                    next.disabled = track.scrollLeft >= max;
                    previous.setAttribute('aria-disabled', String(previous.disabled));
                    next.setAttribute('aria-disabled', String(next.disabled));
                };

                previous.addEventListener('click', () => track.scrollBy({left: -step(), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'}));
                next.addEventListener('click', () => track.scrollBy({left: step(), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'}));
                track.addEventListener('scroll', updateControls, {passive: true});
                window.addEventListener('resize', updateControls, {passive: true});
                updateControls();
            });
        })();
    </script>
@endpush
