@extends('layouts.site')
@section('body-class', 'home-body')
@section('title', 'Emerald Rozalia — Irish Made Hats & Caps')
@push('styles')
    <link rel="stylesheet" href="/css/home-collections.css?v=20260913-managed-home">
    <link rel="stylesheet" href="/css/home-hero-layout.css?v=20260913-structured-hero">
@endpush
@section('content')
    @php
        $homepageSections = $homepage?->sections ?? collect();
        $heroSection = $homepageSections->first(fn ($section) => $section->visible && strtolower(trim((string) $section->type)) === 'hero');
        $heroSettings = $heroSection && is_array($heroSection->settings) ? $heroSection->settings : [];
        $heroCopy = static function (string $key, string $fallback = '') use ($heroSettings): string {
            $value = data_get($heroSettings, $key, $fallback);
            return is_scalar($value) ? trim((string) $value) : $fallback;
        };
        $heroHref = static function ($value, string $fallback): string {
            $value = trim((string) $value);
            return $value !== '' && str_starts_with($value, '/') && ! str_starts_with($value, '//') ? $value : $fallback;
        };
        $heroMedia = $heroSection && is_array($homeMedia ?? null) && filled($heroSection->media_uuid)
            ? ($homeMedia[$heroSection->media_uuid] ?? null)
            : null;
        $heroVisual = is_array($heroMedia) ? $heroMedia : (is_array($homeHeroReferenceMedia ?? null) ? $homeHeroReferenceMedia : null);
        $heroVisualIsReference = ! is_array($heroMedia) && is_array($heroVisual);
        $heroVisualUrl = is_array($heroVisual) ? ($heroVisual['url'] ?? null) : null;
        $heroVisualAlt = is_array($heroVisual) ? ($heroVisual['alt'] ?? 'Emerald Rozalia hat') : 'Emerald Rozalia hat';
        $heroId = $heroSection ? 'home-section-' . ($heroSection->uuid ?: $heroSection->id) : 'home-hero';
        $heroDevices = $heroSection && is_array($heroSection->devices) && $heroSection->devices !== [] ? $heroSection->devices : ['desktop', 'tablet', 'mobile'];
        $heroDeviceValue = implode(' ', array_values(array_intersect(['desktop', 'tablet', 'mobile'], $heroDevices)));
        $heroAnimation = $heroSection && in_array($heroSection->animation, ['none', 'fade', 'rise', 'slide'], true) ? $heroSection->animation : 'none';
        $tryOnProducts = ($homeLatestProducts ?? $homeProducts ?? collect()) instanceof \Illuminate\Support\Collection
            ? ($homeLatestProducts ?? $homeProducts)->take(8)
            : collect();
    @endphp

    <div class="home-page" data-homepage-page-uuid="{{ $homepage?->uuid ?: 'reserved-homepage-pending' }}" data-homepage-source="{{ $homepage ? 'content-page-active-record' : 'reserved-homepage-fallback' }}" data-homepage-renderer="persisted-page-sections">
        @if(($previewMode ?? false) || ($layoutPreviewMode ?? false))
            <div class="managed-page-preview-banner" role="status">
                Previewing {{ ($layoutPreviewMode ?? false) ? 'shared layout · Homepage render' : 'Homepage' }} · unpublished preview
                <a href="{{ ($layoutPreviewMode ?? false) ? route('admin.pages.layouts') : route('admin.pages.edit', $homepage) }}">Return to control panel</a>
            </div>
        @endif

        @if($heroSection)
            <section id="{{ $heroId }}" class="home-hero home-hero--structured" data-home-section="hero" data-home-section-uuid="{{ $heroSection->uuid }}" data-home-animation="{{ $heroAnimation }}" data-home-devices="{{ $heroDeviceValue }}" aria-labelledby="{{ $heroId }}-title">
                <div class="home-hero-structured-grid">
                    <div class="home-hero-structured-copy">
                        <p class="eyebrow">{{ $heroCopy('eyebrow', $heroSection->label ?: 'IRISH MADE. LIMERICK BORN.') }}</p>
                        <h1 id="{{ $heroId }}-title">{{ $heroCopy('title', 'Crafted in Limerick. Worn Everywhere.') }}</h1>
                        <p class="home-hero-structured-intro">{{ $heroCopy('content', 'Premium hats and caps made with Irish craftsmanship, contemporary design, and a global outlook.') }}</p>
                        <div class="home-hero-structured-actions">
                            <a class="btn" href="{{ $heroHref($heroSettings['secondary_href'] ?? null, '/shop') }}">{{ $heroCopy('secondary_label', 'SHOP NOW') }} <x-icon name="arrow-right" size="16" /></a>
                            <a class="btn ghost" href="{{ $heroHref($heroSettings['tertiary_href'] ?? null, '/factory') }}">{{ $heroCopy('tertiary_label', 'OUR STORY') }}</a>
                        </div>
                    </div>

                    <div class="home-hero-structured-product @if($heroVisualIsReference) is-reference-crop @endif" data-public-media-state="{{ is_array($heroMedia) ? 'approved' : ($heroVisualUrl ? 'reference-crop' : 'awaiting-approved-media') }}">
                        @if($heroVisualUrl)
                            <img src="{{ $heroVisualUrl }}" alt="{{ $heroVisualAlt }}" fetchpriority="high">
                        @else
                            <div class="home-hero-structured-placeholder" role="img" aria-label="Approved hero product media is not configured yet.">Approved hero product media</div>
                        @endif
                    </div>

                    <aside class="home-hero-tryon" aria-labelledby="{{ $heroId }}-tryon-title">
                        <div class="home-hero-tryon-heading">
                            <p class="eyebrow">VIRTUAL TRY-ON</p>
                            <h2 id="{{ $heroId }}-tryon-title">See how it looks on you.</h2>
                            <p>Choose a style, preview your photo locally, then open the full Try-On Studio.</p>
                        </div>
                        <form action="{{ route('virtual-tryon') }}" method="get" class="home-hero-tryon-form" data-home-tryon-form>
                            <label>
                                <span>Choose a hat</span>
                                <select name="product_id">
                                    <option value="">Select a product</option>
                                    @foreach($tryOnProducts as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="home-hero-tryon-upload">
                                <span>Upload your photo</span>
                                <input type="file" accept="image/jpeg,image/png,image/webp" data-home-tryon-upload>
                                <strong data-home-tryon-file>Choose JPG, PNG or WebP</strong>
                            </label>
                            <div class="home-hero-tryon-preview" data-home-tryon-preview hidden>
                                <img alt="Your selected Try-On preview photo" data-home-tryon-preview-image>
                            </div>
                            <button class="btn home-hero-tryon-submit" type="submit">{{ $heroCopy('primary_label', 'START TRY-ON') }} <x-icon name="arrow-right" size="16" /></button>
                            <small>Your selected photo is previewed only in this browser. The full Try-On Studio handles fitting and adjustments.</small>
                        </form>
                    </aside>
                </div>
            </section>
        @endif

        @foreach($homepageSections as $section)
            @if($section->visible && (! $heroSection || $section->id !== $heroSection->id))
                @include('site.partials.home-section', [
                    'section' => $section,
                    'categories' => $categories ?? collect(),
                    'homeProducts' => $homeProducts ?? $newProducts ?? collect(),
                    'homeLatestProducts' => $homeLatestProducts ?? $homeProducts ?? $newProducts ?? collect(),
                    'banners' => $banners ?? collect(),
                    'homeMedia' => $homeMedia ?? [],
                    'homeHeroReferenceMedia' => $homeHeroReferenceMedia ?? null,
                ])
            @endif
        @endforeach

        @if($homepageSections->isEmpty())
            <section class="home-managed-empty-state" data-home-section="empty" aria-labelledby="homepage-empty-title">
                <p class="eyebrow">EMERALD ROZALIA</p>
                <h1 id="homepage-empty-title">Homepage content is not configured.</h1>
                <p>An administrator can add and publish homepage sections from Page Manager.</p>
            </section>
        @endif
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

            document.querySelectorAll('[data-home-tryon-form]').forEach((form) => {
                const input = form.querySelector('[data-home-tryon-upload]');
                const filename = form.querySelector('[data-home-tryon-file]');
                const preview = form.querySelector('[data-home-tryon-preview]');
                const previewImage = form.querySelector('[data-home-tryon-preview-image]');
                if (!input || !filename || !preview || !previewImage) return;

                input.addEventListener('change', () => {
                    const file = input.files && input.files[0];
                    if (!file) {
                        filename.textContent = 'Choose JPG, PNG or WebP';
                        preview.hidden = true;
                        previewImage.removeAttribute('src');
                        return;
                    }
                    filename.textContent = file.name;
                    const reader = new FileReader();
                    reader.addEventListener('load', () => {
                        previewImage.src = String(reader.result || '');
                        preview.hidden = false;
                    }, {once: true});
                    reader.readAsDataURL(file);
                });
            });
        })();
    </script>
@endpush