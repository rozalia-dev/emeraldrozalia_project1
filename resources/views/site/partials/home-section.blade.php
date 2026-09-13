@php
    $settings = is_array($section->settings) ? $section->settings : [];
    $type = strtolower(trim((string) $section->type));
    $sectionId = 'home-section-' . ($section->uuid ?: $section->id);
    $devices = is_array($section->devices) && $section->devices !== [] ? $section->devices : ['desktop', 'tablet', 'mobile'];
    $animation = in_array($section->animation, ['none', 'fade', 'rise', 'slide'], true) ? $section->animation : 'none';
    $deviceValue = implode(' ', array_values(array_intersect(['desktop', 'tablet', 'mobile'], $devices)));
    $copy = static function (string $key, string $fallback = '') use ($settings): string {
        $value = data_get($settings, $key, $fallback);

        return is_scalar($value) ? trim((string) $value) : $fallback;
    };
    $safeUrl = static function ($value): ?string {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/\A(?:javascript|data|vbscript):/i', $value)) {
            return null;
        }
        if ((str_starts_with($value, '/') && !str_starts_with($value, '//')) || preg_match('/\Ahttps:\/\/[^\s]+/i', $value)) {
            return $value;
        }

        return null;
    };
    $safeIcon = static function ($value, string $fallback = 'star'): string {
        $value = (string) $value;
        $allowed = ['arrow-right', 'clover', 'globe', 'home', 'package', 'settings', 'star', 'truck', 'users'];

        return in_array($value, $allowed, true) ? $value : $fallback;
    };
    $media = is_array($homeMedia ?? null) ? ($homeMedia[$section->media_uuid] ?? null) : null;
    $mediaUrl = is_array($media) ? ($media['url'] ?? null) : null;
    $mediaAlt = is_array($media) ? ($media['alt'] ?? $copy('alt', $section->label ?: 'Homepage media')) : $copy('alt', $section->label ?: 'Homepage media');
    $sectionAttributes = 'id="'.$sectionId.'" data-home-section="'.$type.'" data-home-section-uuid="'.$section->uuid.'" data-home-animation="'.$animation.'" data-home-devices="'.$deviceValue.'"';
@endphp

@switch($type)
    @case('hero')
        @php
            $heroPrimaryUrl = $safeUrl($settings['primary_href'] ?? null);
            $heroSecondaryUrl = $safeUrl($settings['secondary_href'] ?? null);
            $heroTertiaryUrl = $safeUrl($settings['tertiary_href'] ?? null);
        @endphp
        <section {!! $sectionAttributes !!} class="home-hero home-hero--managed" aria-labelledby="{{ $sectionId }}-title">
            <div class="home-hero-composition">
                <div class="home-hero-copy">
                    <p class="eyebrow">{{ $copy('eyebrow', $section->label ?: 'EMERALD ROZALIA') }}</p>
                    <h1 id="{{ $sectionId }}-title">{{ $copy('title', $section->label ?: 'Emerald Rozalia') }}</h1>
                    <p>{{ $copy('content') }}</p>
                    <div class="home-hero-actions">
                        @if($heroPrimaryUrl)
                            <a class="btn" href="{{ $heroPrimaryUrl }}">{{ $copy('primary_label', 'Explore') }} <x-icon name="arrow-right" /></a>
                        @endif
                        @if($heroSecondaryUrl)
                            <a class="btn ghost" href="{{ $heroSecondaryUrl }}">{{ $copy('secondary_label', 'Shop now') }}</a>
                        @endif
                        @if($heroTertiaryUrl)
                            <a class="home-hero-text-link" href="{{ $heroTertiaryUrl }}">{{ $copy('tertiary_label', 'Our story') }} <x-icon name="arrow-right" size="16" /></a>
                        @endif
                    </div>
                </div>
                <div class="home-hero-visual home-managed-media home-managed-media--hero" data-public-media-state="{{ $mediaUrl ? 'approved' : 'awaiting-approved-media' }}" role="img" aria-label="{{ $mediaAlt }}">
                    @if($mediaUrl)
                        <img src="{{ $mediaUrl }}" alt="{{ $mediaAlt }}">
                    @else
                        <span class="sr-only">Approved homepage media is not configured for this section.</span>
                    @endif
                </div>
            </div>
        </section>
        @break

    @case('banners')
        <section {!! $sectionAttributes !!} class="home-published-banners" data-public-source="published-banner-records" data-banner-position="{{ $copy('position', 'Home - Main Slider') }}" aria-label="Published Emerald Rozalia banners">
            @if(isset($banners) && $banners->isNotEmpty())
                @foreach($banners as $banner)
                    @php($bannerTarget = $safeUrl($banner->target_url))
                    @if($bannerTarget)
                        <a class="home-published-banner" href="{{ $bannerTarget }}" data-banner-public-uuid="{{ $banner->public_uuid }}">
                    @else
                        <div class="home-published-banner" data-banner-public-uuid="{{ $banner->public_uuid }}">
                    @endif
                        @if($banner->imageUrl())
                            <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->alt_text ?: $banner->title }}">
                        @endif
                        <div class="home-published-banner__copy">
                            @if($banner->title)
                                <strong>{{ $banner->title }}</strong>
                            @endif
                            @if($banner->subtitle)
                                <span>{{ $banner->subtitle }}</span>
                            @endif
                        </div>
                    @if($bannerTarget)
                        </a>
                    @else
                        </div>
                    @endif
                @endforeach
            @else
                <p class="home-managed-empty">No published campaign banners are available for this section.</p>
            @endif
        </section>
        @break

    @case('benefits')
        @php($benefitItems = is_array($settings['items'] ?? null) ? array_values($settings['items']) : [])
        <section {!! $sectionAttributes !!} class="home-benefits" aria-label="{{ $copy('aria_label', $section->label ?: 'Emerald Rozalia benefits') }}">
            @foreach($benefitItems as $item)
                @if(is_array($item) && filled($item['title'] ?? null))
                    <div>
                        <span class="home-benefit-icon"><x-icon name="{{ $safeIcon($item['icon'] ?? null) }}" size="42" /></span>
                        <span class="home-benefit-copy"><b>{{ trim((string) $item['title']) }}</b><small>{{ trim((string) ($item['content'] ?? '')) }}</small></span>
                    </div>
                @endif
            @endforeach
        </section>
        @break

    @case('collections')
        @php($collectionItems = is_array($settings['items'] ?? null) ? array_values($settings['items']) : [])
        <section {!! $sectionAttributes !!} class="home-section home-collections home-collections--managed">
            <div class="home-section-heading"><span></span><h2>{{ $copy('title', $section->label ?: 'SHOP BY COLLECTIONS') }}</h2><span></span></div>
            <div class="home-collection-grid">
                @foreach($collectionItems as $item)
                    @if(is_array($item) && filled($item['title'] ?? null))
                        @php
                            $slug = trim((string) ($item['slug'] ?? ''));
                            $category = isset($categories) && $categories instanceof \Illuminate\Support\Collection ? $categories->firstWhere('slug', $slug) : null;
                            $collectionUrl = $category ? route('category', $category) : $safeUrl('/shop?category='.rawurlencode($slug));
                        @endphp
                        @if($collectionUrl)
                            <a class="home-collection-card" href="{{ $collectionUrl }}">
                                <div class="home-managed-media home-managed-media--collection" data-public-media-state="awaiting-approved-media" role="img" aria-label="{{ $item['title'] }} collection image">
                                    @if(!empty($item['new']))
                                        <span class="home-collection-new">NEW</span>
                                    @endif
                                </div>
                                <div><h3>{{ $item['title'] }}</h3><p>{{ $item['copy'] ?? '' }}</p><span>{{ $copy('card_cta', 'SHOP NOW') }} <b aria-hidden="true"><x-icon name="arrow-right" /></b></span></div>
                            </a>
                        @endif
                    @endif
                @endforeach
            </div>
        </section>
        @break

    @case('heritage')
        @php($heritageButtonUrl = $safeUrl($settings['button_href'] ?? null))
        @php($heritageBadges = is_array($settings['badges'] ?? null) ? array_values($settings['badges']) : [])
        <section {!! $sectionAttributes !!} class="home-heritage home-heritage--managed" aria-labelledby="{{ $sectionId }}-title">
            <div class="home-heritage-copy">
                <p class="eyebrow">{{ $copy('eyebrow', $section->label ?: 'THE IRISH HERITAGE COLLECTION') }}</p>
                <h2 id="{{ $sectionId }}-title">{{ $copy('title', 'Tradition, Made in Limerick.') }}</h2>
                <p>{{ $copy('content') }}</p>
                @if($heritageButtonUrl)
                    <a class="btn" href="{{ $heritageButtonUrl }}">{{ $copy('button_label', 'Explore') }} <x-icon name="arrow-right" /></a>
                @endif
            </div>
            <div class="home-heritage-visual home-managed-media home-managed-media--heritage" data-public-media-state="{{ $mediaUrl ? 'approved' : 'awaiting-approved-media' }}" role="img" aria-label="{{ $mediaAlt }}">
                @if($mediaUrl)
                    <img src="{{ $mediaUrl }}" alt="{{ $mediaAlt }}">
                @else
                    <span class="sr-only">Approved homepage media is not configured for this section.</span>
                @endif
            </div>
            <div class="home-heritage-badges" aria-label="{{ $copy('badges_label', 'Irish heritage collection qualities') }}">
                @foreach($heritageBadges as $badge)
                    @if(is_array($badge) && filled($badge['title'] ?? null))
                        <div><x-icon name="{{ $safeIcon($badge['icon'] ?? null) }}" size="28" /><span>{{ $badge['title'] }}</span></div>
                    @endif
                @endforeach
            </div>
        </section>
        @break

    @case('products')
        @php
            $productLimit = max(1, min(12, (int) ($settings['limit'] ?? 6)));
            $productPool = ($settings['source'] ?? 'new_products') === 'latest' ? ($homeLatestProducts ?? $homeProducts ?? collect()) : ($homeProducts ?? collect());
            $homeProductItems = $productPool instanceof \Illuminate\Support\Collection ? $productPool->take($productLimit) : collect();
            $viewAllUrl = $safeUrl($settings['view_all_href'] ?? '/shop');
        @endphp
        <section {!! $sectionAttributes !!} class="home-section home-bestsellers home-bestsellers--managed" data-home-bestsellers>
            <div class="home-section-heading home-section-heading--left">
                <h2>{{ $copy('title', $section->label ?: 'BESTSELLERS') }}</h2>
                <span></span>
                @if($viewAllUrl)
                    <a href="{{ $viewAllUrl }}">{{ $copy('view_all_label', 'VIEW ALL') }} <x-icon name="arrow-right" /></a>
                @endif
            </div>
            @if($homeProductItems->isNotEmpty())
                <div class="home-product-carousel">
                    <button class="home-carousel-arrow home-carousel-arrow--prev" type="button" data-home-carousel-prev aria-label="Previous {{ strtolower($copy('title', 'products')) }}"><x-icon name="chevron-left" size="20" /></button>
                    <div class="home-product-grid" data-home-carousel-track>
                        @foreach($homeProductItems as $product)
                            @php($productMedia = $product->media->firstWhere('type', 'image'))
                            <article class="home-product-card">
                                <a class="home-product-link" href="{{ route('product', $product) }}">
                                    <div class="home-product-media home-managed-media home-managed-media--product" data-public-media-state="{{ $productMedia ? 'approved' : 'awaiting-approved-media' }}" role="img" aria-label="{{ $product->name }} product image">
                                        @if($productMedia)
                                            <img src="{{ Storage::disk($productMedia->disk)->url($productMedia->path) }}" alt="{{ $productMedia->alt_text ?: $product->name }}" loading="lazy">
                                        @else
                                            <span class="sr-only">Approved product media is not configured.</span>
                                        @endif
                                    </div>
                                    <span>{{ $product->name }}</span><strong>€{{ number_format((float) $product->price, 2) }}</strong>
                                </a>
                                @if((int) $product->stock > 0)
                                    <form method="post" action="{{ route('cart.add', $product) }}" class="home-product-cart-form">@csrf<input type="hidden" name="quantity" value="1"><button class="home-product-cart" type="submit" aria-label="Add {{ $product->name }} to cart"><x-icon name="shopping-bag" size="16" /></button></form>
                                @else
                                    <a class="home-product-cart" href="{{ route('product', $product) }}" aria-label="View {{ $product->name }}"><x-icon name="arrow-right" size="16" /></a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                    <button class="home-carousel-arrow home-carousel-arrow--next" type="button" data-home-carousel-next aria-label="Next {{ strtolower($copy('title', 'products')) }}"><x-icon name="chevron-right" size="20" /></button>
                </div>
            @else
                <p class="home-managed-empty">No published products are available for this section.</p>
            @endif
        </section>
        @break

    @case('quality')
        @php($qualityButtonUrl = $safeUrl($settings['button_href'] ?? null))
        <section {!! $sectionAttributes !!} class="home-quality home-quality--managed" aria-labelledby="{{ $sectionId }}-title">
            <div class="home-quality-visual home-managed-media home-managed-media--quality" data-public-media-state="{{ $mediaUrl ? 'approved' : 'awaiting-approved-media' }}" role="img" aria-label="{{ $mediaAlt }}">
                @if($mediaUrl)
                    <img src="{{ $mediaUrl }}" alt="{{ $mediaAlt }}">
                @else
                    <span class="sr-only">Approved homepage media is not configured for this section.</span>
                @endif
            </div>
            <div class="home-quality-copy">
                <p class="eyebrow">{{ $copy('eyebrow', 'FROM CONCEPT TO CREATION.') }}</p>
                <h2 id="{{ $sectionId }}-title">{{ $copy('title', $section->label ?: 'QUALITY IN EVERY STITCH.') }}</h2>
                <p>{{ $copy('content') }}</p>
                @if($qualityButtonUrl)
                    <a class="btn ghost" href="{{ $qualityButtonUrl }}">{{ $copy('button_label', 'SEE OUR PROCESS') }} <x-icon name="arrow-right" /></a>
                @endif
            </div>
        </section>
        @break

    @case('franchise')
        @php($franchiseButtonUrl = $safeUrl($settings['button_href'] ?? null))
        <section {!! $sectionAttributes !!} class="home-franchise home-franchise--managed" aria-labelledby="{{ $sectionId }}-title">
            <div>
                <p class="eyebrow">{{ $copy('eyebrow', 'FRANCHISE OPEN NOW') }}</p>
                <h2 id="{{ $sectionId }}-title">{{ $copy('title', 'FOR IRELAND') }}</h2>
                <p>{{ $copy('content') }}</p>
                @if($franchiseButtonUrl)
                    <a class="btn" href="{{ $franchiseButtonUrl }}">{{ $copy('button_label', 'APPLY FOR FRANCHISE') }} <x-icon name="arrow-right" /></a>
                @endif
            </div>
            <div class="home-franchise-visual home-managed-media home-managed-media--franchise" data-public-media-state="{{ $mediaUrl ? 'approved' : 'awaiting-approved-media' }}" role="img" aria-label="{{ $mediaAlt }}">
                @if($mediaUrl)
                    <img src="{{ $mediaUrl }}" alt="{{ $mediaAlt }}">
                @else
                    <span class="sr-only">Approved homepage media is not configured for this section.</span>
                @endif
            </div>
        </section>
        @break

    @default
        <section {!! $sectionAttributes !!} class="home-section home-managed-content">
            <p class="eyebrow">{{ $section->label ?: 'Homepage content' }}</p>
            <h2>{{ $copy('title', $section->label ?: 'Homepage content') }}</h2>
            <p>{{ $copy('content') }}</p>
        </section>
@endswitch
