@php
    $settings = is_array($section->settings) ? $section->settings : [];
    $publicMedia = app(\App\Services\PublicMediaResolver::class);
    $sectionMedia = $publicMedia->forUuid($section->media_uuid, $section->label ?: null);
    $rawType = strtolower(trim((string) $section->type));
    $type = in_array($rawType, ['hero', 'content', 'gallery', 'cta', 'form'], true) ? $rawType : 'content';
    $copy = static function (string $key, string $fallback = '') use ($settings): string {
        $value = data_get($settings, $key, $fallback);

        return is_scalar($value) ? trim((string) $value) : $fallback;
    };
    $safeUrl = static function ($value): ?string {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/\A(?:javascript|data|vbscript):/i', $value)) {
            return null;
        }
        if (str_starts_with($value, '/') || str_starts_with($value, '#') || preg_match('/\Ahttps?:\/\/[^\s]+/i', $value)) {
            return $value;
        }

        return null;
    };
    $label = trim((string) ($section->label ?: str($type)->headline()));
    $content = $copy('content', 'Section content is ready to be edited in the Page Manager.');
    $sectionId = 'managed-section-' . ($section->id ?: str($type)->slug());
@endphp

@switch($type)
    @case('hero')
        @php
            $heroTitle = $copy('title', $label);
            $heroImage = $sectionMedia;
            $heroUrl = $safeUrl($settings['url'] ?? $settings['primary_href'] ?? $settings['cta_url'] ?? null);
            $heroCta = $copy('button_label', $copy('primary_label', $copy('cta_label', 'Explore more')));
        @endphp
        <section id="{{ $sectionId }}" class="managed-page-section managed-page-section--hero" data-managed-section="hero">
            <div class="managed-page-section-copy">
                <p class="managed-page-eyebrow">{{ $label }}</p>
                <h2>{{ $heroTitle }}</h2>
                <div class="managed-page-copy">{!! nl2br(e($content)) !!}</div>
                @if($heroUrl)<a class="btn" href="{{ $heroUrl }}">{{ $heroCta }}</a>@endif
            </div>
            @if($heroImage)
                <figure class="managed-page-section-media">
                    @if(str_starts_with((string) ($heroImage['mime_type'] ?? ''), 'video/'))
                        <video src="{{ $heroImage['url'] }}" width="{{ $heroImage['width'] ?: '' }}" height="{{ $heroImage['height'] ?: '' }}" muted playsinline loop preload="metadata" aria-label="{{ $heroImage['alt'] ?: $copy('alt', $heroTitle) }}"></video>
                    @else
                        <img src="{{ $heroImage['url'] }}" @if($heroImage['srcset']) srcset="{{ $heroImage['srcset'] }}" sizes="{{ $heroImage['sizes'] }}" @endif width="{{ $heroImage['width'] ?: '' }}" height="{{ $heroImage['height'] ?: '' }}" alt="{{ $heroImage['alt'] ?: $copy('alt', $heroTitle) }}" loading="lazy">
                    @endif
                </figure>

            @endif
        </section>
        @break

    @case('gallery')
        @php
            $galleryItems = is_array($settings['items'] ?? null) ? array_values($settings['items']) : [];
        @endphp
        <section id="{{ $sectionId }}" class="managed-page-section managed-page-section--gallery" data-managed-section="gallery">
            <header class="managed-page-section-heading"><p class="managed-page-eyebrow">{{ $label }}</p><h2>{{ $copy('title', $label) }}</h2>@if($content !== 'Section content is ready to be edited in the Page Manager.')<div class="managed-page-copy">{!! nl2br(e($content)) !!}</div>@endif</header>
            @if($galleryItems)
                <div class="managed-page-gallery">
                    @foreach($galleryItems as $item)
                        @if(is_array($item))
                            @php
                                $itemImage = $publicMedia->forUuid($item['media_uuid'] ?? null, $item['alt'] ?? $label);
                                $legacyReference = basename((string) ($item['image'] ?? ''));
                                $legacyReference = preg_match('/\A[A-Za-z0-9._-]+\z/', $legacyReference) ? $legacyReference : null;
                                $itemUrl = $safeUrl($item['url'] ?? $item['href'] ?? null);
                                $itemAlt = is_scalar($item['alt'] ?? null) ? trim((string) $item['alt']) : $label;
                                $itemCaption = is_scalar($item['caption'] ?? null) ? trim((string) $item['caption']) : '';
                            @endphp
                            @if($itemImage)
                                <figure class="managed-page-gallery-item">
                                    @if($itemUrl)<a href="{{ $itemUrl }}">@endif
                                    @if(str_starts_with((string) ($itemImage['mime_type'] ?? ''), 'video/'))
                                        <video src="{{ $itemImage['url'] }}" width="{{ $itemImage['width'] ?: '' }}" height="{{ $itemImage['height'] ?: '' }}" muted playsinline loop preload="metadata" aria-label="{{ $itemImage['alt'] ?: $itemAlt }}"></video>
                                    @else
                                        <img src="{{ $itemImage['url'] }}" @if($itemImage['srcset']) srcset="{{ $itemImage['srcset'] }}" sizes="{{ $itemImage['sizes'] }}" @endif width="{{ $itemImage['width'] ?: '' }}" height="{{ $itemImage['height'] ?: '' }}" alt="{{ $itemImage['alt'] ?: $itemAlt }}" loading="lazy">
                                    @endif
                                    @if($itemUrl)</a>@endif
                                    @if($itemCaption)<figcaption>{{ $itemCaption }}</figcaption>@endif
                                </figure>
                            @elseif($legacyReference)
                                <span class="managed-page-media-reference sr-only" data-media-migration-reference>Legacy media reference awaiting approval: {{ $legacyReference }}</span>
                            @endif
                        @endif
                    @endforeach
                </div>
            @else
                <p class="managed-page-empty">Add gallery items in the Page Manager to publish this media grid.</p>
            @endif
        </section>
        @break

    @case('cta')
        @php
            $ctaUrl = $safeUrl($settings['url'] ?? $settings['href'] ?? null);
            $ctaButton = $copy('button_label', $copy('label', 'Learn more'));
        @endphp
        <section id="{{ $sectionId }}" class="managed-page-section managed-page-section--cta" data-managed-section="cta">
            <div><p class="managed-page-eyebrow">{{ $label }}</p><h2>{{ $copy('title', $label) }}</h2><div class="managed-page-copy">{!! nl2br(e($content)) !!}</div></div>
            @if($ctaUrl)<a class="btn" href="{{ $ctaUrl }}">{{ $ctaButton }}</a>@endif
        </section>
        @break

    @case('form')
        @php
            $formTypes = ['contact', 'franchise', 'careers', 'corporate-orders', 'bulk-orders'];
            $formType = strtolower($copy('inquiry_type', $copy('type', 'contact')));
            $formType = in_array($formType, $formTypes, true) ? $formType : 'contact';
            $requiresConsent = in_array($formType, ['contact', 'franchise'], true);
            $formHeading = $copy('title', $label);
            $formButton = $copy('button_label', 'Submit enquiry');
        @endphp
        <section id="{{ $sectionId }}" class="managed-page-section managed-page-section--form" data-managed-section="form">
            <div class="managed-page-form-intro"><p class="managed-page-eyebrow">{{ $label }}</p><h2>{{ $formHeading }}</h2><div class="managed-page-copy">{!! nl2br(e($content)) !!}</div></div>
            <form method="post" action="{{ route('inquiry') }}" class="managed-page-form">
                @csrf
                <input type="hidden" name="type" value="{{ $formType }}">
                <label>Full name<input name="name" required maxlength="120" autocomplete="name"></label>
                <label>Email address<input type="email" name="email" required maxlength="255" autocomplete="email"></label>
                <label>Phone number<input name="phone" maxlength="50" autocomplete="tel"></label>
                <label>Company / club<input name="company" maxlength="120" autocomplete="organization"></label>
                @if($formType === 'contact')<label>Subject<input name="subject" required maxlength="150"></label>@endif
                <label>Country<input name="country" maxlength="120" autocomplete="country-name"></label>
                <label class="managed-page-form-wide">Message<textarea name="message" rows="5" @if(in_array($formType, ['contact', 'franchise', 'corporate-orders', 'bulk-orders'], true)) required @endif></textarea></label>
                @if($requiresConsent)<label class="managed-page-consent managed-page-form-wide"><input type="checkbox" name="consent" value="1" required> I agree to Emerald Rozalia processing this enquiry so the team can respond.</label>@endif
                <button class="btn managed-page-form-wide" type="submit">{{ $formButton }}</button>
            </form>
        </section>
        @break

    @default
        <article id="{{ $sectionId }}" class="managed-page-section managed-page-section--content" data-managed-section="content">
            <p class="managed-page-eyebrow">{{ $label }}</p>
            <h2>{{ $copy('title', $label) }}</h2>
            <div class="managed-page-copy">{!! nl2br(e($content)) !!}</div>
        </article>
@endswitch
