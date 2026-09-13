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
    $mediaIsVideo = is_array($media) && str_starts_with((string) ($media['mime_type'] ?? ''), 'video/');
    $mediaAlt = is_array($media) ? ($media['alt'] ?? $copy('alt', $section->label ?: 'Homepage media')) : $copy('alt', $section->label ?: 'Homepage media');
    $heroReferenceMedia = is_array($homeHeroReferenceMedia ?? null) ? $homeHeroReferenceMedia : null;
    $sectionAttributes = 'id="'.$sectionId.'" data-home-section="'.$type.'" data-home-section-uuid="'.$section->uuid.'" data-home-animation="'.$animation.'" data-home-devices="'.$deviceValue.'"';
@endphp

<?php if ($type === 'hero') { ?>
        @php
            $heroPrimaryUrl = $safeUrl($settings['primary_href'] ?? null);
            $heroSecondaryUrl = $safeUrl($settings['secondary_href'] ?? null);
            $heroTertiaryUrl = $safeUrl($settings['tertiary_href'] ?? null);
            $heroTitle = $copy('title', $section->label ?: 'Emerald Rozalia');
            $heroReferenceUrl = $heroReferenceMedia['url'] ?? null;
            $heroCenterUrl = $mediaUrl ?: $heroReferenceUrl;
            $heroCenterAlt = $mediaUrl ? $mediaAlt : ($heroReferenceMedia['alt'] ?? $mediaAlt);
            $heroUsesReferenceArt = ! $mediaUrl && filled($heroReferenceUrl);
            $heroPhotoInputId = $sectionId . '-photo';
        @endphp
        <section {!! $sectionAttributes !!} class="home-hero home-hero--managed home-hero--three-column @if($heroUsesReferenceArt) home-hero--reference-art @endif" aria-labelledby="{{ $sectionId }}-title">
            <div class="home-hero-composition">
                <div class="home-hero-copy">
                    <p class="eyebrow">{{ $copy('eyebrow', $section->label ?: 'EMERALD ROZALIA') }}</p>
                    <h1 id="{{ $sectionId }}-title">
                        @if($heroTitle === 'CRAFTED IN LIMERICK. WORN EVERYWHERE.')
                            <span>CRAFTED IN</span><em>LIMERICK.</em><span>WORN</span><span>EVERYWHERE.</span>
                        @else
                            {!! nl2br(e($heroTitle)) !!}
                        @endif
                    </h1>
                    <p class="home-hero-description">{{ $copy('content') }}</p>
                    <div class="home-hero-actions">
                        <?php if ($heroSecondaryUrl) { ?>
                            <a class="btn home-hero-action--primary" href="{{ $heroSecondaryUrl }}">{{ $copy('secondary_label', 'SHOP NEW ARRIVALS') }} <x-icon name="arrow-right" /></a>
                        <?php } ?>
                        <?php if ($heroTertiaryUrl) { ?>
                            <a class="btn ghost home-hero-action--secondary" href="{{ $heroTertiaryUrl }}">{{ $copy('tertiary_label', 'OUR MANUFACTURING STORY') }} <x-icon name="arrow-right" /></a>
                        <?php } ?>
                        <?php if (! $heroSecondaryUrl && $heroPrimaryUrl) { ?>
                            <a class="btn home-hero-action--primary" href="{{ $heroPrimaryUrl }}">{{ $copy('primary_label', 'START TRY-ON') }} <x-icon name="arrow-right" /></a>
                        <?php } ?>
                    </div>
                </div>
                <div class="home-hero-center" data-public-media-state="{{ $mediaUrl ? 'approved' : ($heroReferenceMedia ? 'reference-baseline' : 'awaiting-approved-media') }}">
                    <div class="home-hero-center-media home-managed-media home-managed-media--hero" role="img" aria-label="{{ $heroCenterAlt }}">
                    <?php if ($heroCenterUrl) { ?>
                        @if($mediaIsVideo)
                            <video class="home-hero-center-image" src="{{ $heroCenterUrl }}" width="{{ $media['width'] ?: '' }}" height="{{ $media['height'] ?: '' }}" muted playsinline loop preload="metadata" aria-label="{{ $heroCenterAlt }}"></video>
                        @else
                            <img class="home-hero-center-image" src="{{ $heroCenterUrl }}" alt="{{ $heroCenterAlt }}" width="{{ $media['width'] ?? ($heroReferenceMedia['width'] ?? 864) }}" height="{{ $media['height'] ?? ($heroReferenceMedia['height'] ?? 384) }}" fetchpriority="high">
                        @endif
                    <?php } else { ?>
                        <span class="sr-only">Approved homepage hero media is not configured for this section.</span>
                    <?php } ?>
                    </div>
                    <span class="home-hero-center-caption">LIMERICK · IRELAND</span>
                </div>
                <aside class="home-hero-tryon" aria-labelledby="{{ $sectionId }}-tryon-title">
                    <p class="eyebrow">VIRTUAL TRY-ON</p>
                    <h2 id="{{ $sectionId }}-tryon-title">See It.<br>Love It.<br>Own It.</h2>
                    <p class="home-hero-tryon-copy">Upload your photo and see how our hats look on you.</p>
                    <div class="home-tryon-upload-panel">
                        <label class="home-tryon-upload-action" for="{{ $heroPhotoInputId }}">
                            <x-icon name="upload" size="23" />
                            <span>UPLOAD YOUR PHOTO</span>
                        </label>
                        <span class="home-tryon-or">or</span>
                        <label class="home-tryon-camera-action" for="{{ $heroPhotoInputId }}">
                            <x-icon name="camera" size="14" />
                            <span>TAKE PHOTO</span>
                        </label>
                        <input class="home-tryon-file" id="{{ $heroPhotoInputId }}" type="file" accept="image/jpeg,image/png,image/webp" capture="user" data-home-tryon-photo>
                        <small data-home-tryon-file-name>No photo selected</small>
                    </div>
                    <a class="btn home-hero-tryon-start" href="{{ $heroPrimaryUrl ?: route('virtual-tryon') }}">{{ $copy('primary_label', 'START TRY-ON') }} <x-icon name="arrow-right" /></a>
                    <p class="home-tryon-privacy"><x-icon name="lock" size="12" /> 100% Private &amp; Secure</p>
                    <div class="home-tryon-thumbnails" aria-hidden="true">
                        <span class="home-tryon-thumbnail home-tryon-thumbnail--one"></span>
                        <span class="home-tryon-thumbnail home-tryon-thumbnail--two"></span>
                        <span class="home-tryon-thumbnail home-tryon-thumbnail--three"></span>
                        <span class="home-tryon-thumbnail home-tryon-thumbnail--four"></span>
                    </div>
                    <p class="sr-only">Your photo is selected locally in the browser and is not uploaded from this homepage panel.</p>
                </aside>
            </div>
        </section>
<?php } elseif ($type === 'banners') { ?>

        <section {!! $sectionAttributes !!} class="home-published-banners @if(! isset($banners) || $banners->isEmpty()) home-published-banners--empty @endif" data-public-source="published-banner-records" data-banner-position="{{ $copy('position', 'Home - Main Slider') }}" aria-label="Published Emerald Rozalia banners">
            <?php if (isset($banners) && $banners->isNotEmpty()) { ?>
                <?php foreach ($banners as $banner) { ?>
                    <?php
                        $bannerTarget = $safeUrl($banner->target_url);
                        $bannerMedia = $banner->media && $banner->media->isApprovedPublic()
                            ? app(\App\Services\PublicMediaResolver::class)->describe($banner->media, $banner->alt_text ?: $banner->title)
                            : null;
                    ?>
                    <?php if ($bannerTarget) { ?>
                        <a class="home-published-banner" href="{{ $bannerTarget }}" data-banner-public-uuid="{{ $banner->public_uuid }}">
                    <?php } else { ?>
                        <div class="home-published-banner" data-banner-public-uuid="{{ $banner->public_uuid }}">
                    <?php } ?>
                        <?php if ($bannerMedia) { ?>
                            @if(str_starts_with((string) ($bannerMedia['mime_type'] ?? ''), 'video/'))
                                <video src="{{ $bannerMedia['url'] }}" width="{{ $bannerMedia['width'] ?: '' }}" height="{{ $bannerMedia['height'] ?: '' }}" muted playsinline loop preload="metadata" aria-label="{{ $bannerMedia['alt'] }}"></video>
                            @else
                                <img src="{{ $bannerMedia['url'] }}" @if($bannerMedia['srcset']) srcset="{{ $bannerMedia['srcset'] }}" sizes="{{ $bannerMedia['sizes'] }}" @endif width="{{ $bannerMedia['width'] ?: '' }}" height="{{ $bannerMedia['height'] ?: '' }}" alt="{{ $bannerMedia['alt'] }}">
                            @endif
                        <?php } ?>
                        <div class="home-published-banner__copy">
                            <?php if ($banner->title) { ?>
                                <strong>{{ $banner->title }}</strong>
                            <?php } ?>
                            <?php if ($banner->subtitle) { ?>
                                <span>{{ $banner->subtitle }}</span>
                            <?php } ?>
                        </div>
                    <?php if ($bannerTarget) { ?>
                        </a>
                    <?php } else { ?>
                        </div>
                    <?php } ?>
                <?php } ?>
            <?php } else { ?>
                <p class="home-managed-empty">No published campaign banners are available for this section.</p>
            <?php } ?>
        </section>
<?php } elseif ($type === 'benefits') { ?>

        <?php $benefitItems = is_array($settings['items'] ?? null) ? array_values($settings['items']) : []; ?>
        <section {!! $sectionAttributes !!} class="home-benefits" aria-label="{{ $copy('aria_label', $section->label ?: 'Emerald Rozalia benefits') }}">
            <?php foreach ($benefitItems as $item) { ?>
                <?php if (is_array($item) && filled($item['title'] ?? null)) { ?>
                    <div>
                        <span class="home-benefit-icon"><x-icon name="{{ $safeIcon($item['icon'] ?? null) }}" size="42" /></span>
                        <span class="home-benefit-copy"><b>{{ trim((string) $item['title']) }}</b><small>{{ trim((string) ($item['content'] ?? '')) }}</small></span>
                    </div>
                <?php } ?>
            <?php } ?>
        </section>
<?php } elseif ($type === 'collections') { ?>

        <?php $collectionItems = is_array($settings['items'] ?? null) ? array_values($settings['items']) : []; ?>
        <section {!! $sectionAttributes !!} class="home-section home-collections home-collections--managed">
            <div class="home-section-heading"><span></span><h2>{{ $copy('title', $section->label ?: 'SHOP BY COLLECTIONS') }}</h2><span></span></div>
            <div class="home-collection-grid">
                <?php foreach ($collectionItems as $collectionIndex => $item) { ?>
                    <?php if (is_array($item) && filled($item['title'] ?? null)) { ?>
                                @php
                                    $slug = trim((string) ($item['slug'] ?? ''));
                                    $category = isset($categories) && $categories instanceof \Illuminate\Support\Collection ? $categories->firstWhere('slug', $slug) : null;
                                    $collectionUrl = $category ? route('category', $category) : $safeUrl('/shop?category='.rawurlencode($slug));
                                    $collectionMedia = is_array($homeMedia ?? null) && filled($item['media_uuid'] ?? null) ? ($homeMedia[$item['media_uuid']] ?? null) : null;
                                @endphp
                        <?php if ($collectionUrl) { ?>
                            <a class="home-collection-card" href="{{ $collectionUrl }}">
                                <div class="home-managed-media home-managed-media--collection @if(! $collectionMedia) home-reference-media home-reference-media--collection-{{ $collectionIndex + 1 }} @endif" data-public-media-state="{{ $collectionMedia ? 'approved' : 'awaiting-approved-media' }}" role="img" aria-label="{{ $collectionMedia['alt'] ?? ($item['title'].' collection image') }}">
                                    <?php if ($collectionMedia) { ?>
                                        @if(str_starts_with((string) ($collectionMedia['mime_type'] ?? ''), 'video/'))
                                            <video src="{{ $collectionMedia['url'] }}" muted playsinline loop preload="metadata" aria-label="{{ $collectionMedia['alt'] }}"></video>
                                        @else
                                            <img src="{{ $collectionMedia['url'] }}" @if($collectionMedia['srcset']) srcset="{{ $collectionMedia['srcset'] }}" sizes="{{ $collectionMedia['sizes'] }}" @endif alt="{{ $collectionMedia['alt'] }}" loading="lazy">
                                        @endif
                                    <?php } ?>
                                    <?php if (!empty($item['new'])) { ?>
                                        <span class="home-collection-new">NEW</span>
                                    <?php } ?>
                                </div>
                                <div><h3>{{ $item['title'] }}</h3><p>{{ $item['copy'] ?? '' }}</p><span>{{ $copy('card_cta', 'SHOP NOW') }} <b aria-hidden="true"><x-icon name="arrow-right" /></b></span></div>
                            </a>
                        <?php } ?>
                    <?php } ?>
                <?php } ?>
            </div>
        </section>
<?php } elseif ($type === 'heritage') { ?>

        <?php $heritageButtonUrl = $safeUrl($settings['button_href'] ?? null); ?>
        <?php $heritageBadges = is_array($settings['badges'] ?? null) ? array_values($settings['badges']) : []; ?>
        <section {!! $sectionAttributes !!} class="home-heritage home-heritage--managed" aria-labelledby="{{ $sectionId }}-title">
            <div class="home-heritage-copy">
                <h2 id="{{ $sectionId }}-title">{{ $copy('eyebrow', $section->label ?: 'THE IRISH HERITAGE COLLECTION') }}</h2>
                <p class="home-heritage-tagline">{{ $copy('title', 'Tradition, Made in Limerick.') }}</p>
                <p class="home-heritage-content">{{ $copy('content') }}</p>
                <?php if ($heritageButtonUrl) { ?>
                    <a class="btn" href="{{ $heritageButtonUrl }}">{{ $copy('button_label', 'Explore') }} <x-icon name="arrow-right" /></a>
                <?php } ?>
            </div>
            <div class="home-heritage-visual home-managed-media home-managed-media--heritage @if(! $mediaUrl) home-reference-media home-reference-media--heritage @endif" data-public-media-state="{{ $mediaUrl ? 'approved' : 'awaiting-approved-media' }}" role="img" aria-label="{{ $mediaAlt }}">
                <?php if ($mediaUrl) { ?>
                    @if($mediaIsVideo)
                        <video src="{{ $mediaUrl }}" muted playsinline loop preload="metadata" aria-label="{{ $mediaAlt }}"></video>
                    @else
                        <img src="{{ $mediaUrl }}" alt="{{ $mediaAlt }}">
                    @endif
                <?php } else { ?>
                    <span class="sr-only">Approved homepage media is not configured for this section.</span>
                <?php } ?>
            </div>
            <div class="home-heritage-badges" aria-label="{{ $copy('badges_label', 'Irish heritage collection qualities') }}">
                <?php foreach ($heritageBadges as $badge) { ?>
                    <?php if (is_array($badge) && filled($badge['title'] ?? null)) { ?>
                        <div><x-icon name="{{ $safeIcon($badge['icon'] ?? null) }}" size="28" /><span>{{ $badge['title'] }}</span></div>
                    <?php } ?>
                <?php } ?>
            </div>
        </section>
<?php } elseif ($type === 'products') { ?>

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
                <?php if ($viewAllUrl) { ?>
                    <a href="{{ $viewAllUrl }}">{{ $copy('view_all_label', 'VIEW ALL') }} <x-icon name="arrow-right" /></a>
                <?php } ?>
            </div>
            <?php if ($homeProductItems->isNotEmpty()) { ?>
                <div class="home-product-carousel">
                    <button class="home-carousel-arrow home-carousel-arrow--prev" type="button" data-home-carousel-prev aria-label="Previous {{ strtolower($copy('title', 'products')) }}"><x-icon name="chevron-left" size="20" /></button>
                    <div class="home-product-grid" data-home-carousel-track>
                        <?php foreach ($homeProductItems as $productIndex => $product) { ?>
                            <?php
                                $productMedia = $product->media->firstWhere('type', 'image');
                                $productMediaDescriptor = $productMedia ? app(\App\Services\PublicMediaResolver::class)->forProductMedia($productMedia, $product->name) : null;
                            ?>
                            <article class="home-product-card">
                                <a class="home-product-link" href="{{ route('product', $product) }}">
                                        <div class="home-product-media home-managed-media home-managed-media--product @if(! $productMediaDescriptor) home-reference-media home-reference-media--product-{{ ($productIndex % 6) + 1 }} @endif" data-public-media-state="{{ $productMediaDescriptor ? 'approved' : 'awaiting-approved-media' }}" role="img" aria-label="{{ $productMediaDescriptor['alt'] ?? ($product->name.' product image') }}">
                                        <?php if ($productMediaDescriptor) { ?>
                                            <img src="{{ $productMediaDescriptor['url'] }}" @if($productMediaDescriptor['srcset']) srcset="{{ $productMediaDescriptor['srcset'] }}" sizes="{{ $productMediaDescriptor['sizes'] }}" @endif width="{{ $productMediaDescriptor['width'] ?: '' }}" height="{{ $productMediaDescriptor['height'] ?: '' }}" alt="{{ $productMediaDescriptor['alt'] }}" loading="lazy">
                                        <?php } else { ?>
                                            <span class="sr-only">Approved product media is not configured.</span>
                                        <?php } ?>
                                    </div>
                                    <span>{{ $product->name }}</span><strong>€{{ number_format((float) $product->price, 2) }}</strong>
                                </a>
                                <?php if ((int) $product->stock > 0) { ?>
                                    <form method="post" action="{{ route('cart.add', $product) }}" class="home-product-cart-form">@csrf<input type="hidden" name="quantity" value="1"><button class="home-product-cart" type="submit" aria-label="Add {{ $product->name }} to cart"><x-icon name="shopping-bag" size="16" /></button></form>
                                <?php } else { ?>
                                    <a class="home-product-cart" href="{{ route('product', $product) }}" aria-label="View {{ $product->name }}"><x-icon name="arrow-right" size="16" /></a>
                                <?php } ?>
                            </article>
                        <?php } ?>
                    </div>
                    <button class="home-carousel-arrow home-carousel-arrow--next" type="button" data-home-carousel-next aria-label="Next {{ strtolower($copy('title', 'products')) }}"><x-icon name="chevron-right" size="20" /></button>
                </div>
            <?php } else { ?>
                <p class="home-managed-empty">No published products are available for this section.</p>
            <?php } ?>
        </section>
<?php } elseif ($type === 'quality') { ?>

        <?php $qualityButtonUrl = $safeUrl($settings['button_href'] ?? null); ?>
        <section {!! $sectionAttributes !!} class="home-quality home-quality--managed" aria-labelledby="{{ $sectionId }}-title">
            <div class="home-quality-visual home-managed-media home-managed-media--quality @if(! $mediaUrl) home-reference-media home-reference-media--quality @endif" data-public-media-state="{{ $mediaUrl ? 'approved' : 'awaiting-approved-media' }}" role="img" aria-label="{{ $mediaAlt }}">
                <?php if ($mediaUrl) { ?>
                    @if($mediaIsVideo)
                        <video src="{{ $mediaUrl }}" muted playsinline loop preload="metadata" aria-label="{{ $mediaAlt }}"></video>
                    @else
                        <img src="{{ $mediaUrl }}" alt="{{ $mediaAlt }}">
                    @endif
                <?php } else { ?>
                    <span class="sr-only">Approved homepage media is not configured for this section.</span>
                <?php } ?>
            </div>
            <div class="home-quality-copy">
                <p class="eyebrow">{{ $copy('eyebrow', 'FROM CONCEPT TO CREATION.') }}</p>
                <h2 id="{{ $sectionId }}-title">{{ $copy('title', $section->label ?: 'QUALITY IN EVERY STITCH.') }}</h2>
                <p>{{ $copy('content') }}</p>
                <?php if ($qualityButtonUrl) { ?>
                    <a class="btn ghost" href="{{ $qualityButtonUrl }}">{{ $copy('button_label', 'SEE OUR PROCESS') }} <x-icon name="arrow-right" /></a>
                <?php } ?>
            </div>
        </section>
<?php } elseif ($type === 'franchise') { ?>

        <?php $franchiseButtonUrl = $safeUrl($settings['button_href'] ?? null); ?>
        <section {!! $sectionAttributes !!} class="home-franchise home-franchise--managed" aria-labelledby="{{ $sectionId }}-title">
            <div>
                <p class="eyebrow">{{ $copy('eyebrow', 'FRANCHISE OPEN NOW') }}</p>
                <h2 id="{{ $sectionId }}-title">{{ $copy('title', 'FOR IRELAND') }}</h2>
                <p>{{ $copy('content') }}</p>
                <?php if ($franchiseButtonUrl) { ?>
                    <a class="btn" href="{{ $franchiseButtonUrl }}">{{ $copy('button_label', 'APPLY FOR FRANCHISE') }} <x-icon name="arrow-right" /></a>
                <?php } ?>
            </div>
            <div class="home-franchise-visual home-managed-media home-managed-media--franchise @if(! $mediaUrl) home-reference-media home-reference-media--franchise @endif" data-public-media-state="{{ $mediaUrl ? 'approved' : 'awaiting-approved-media' }}" role="img" aria-label="{{ $mediaAlt }}">
                <?php if ($mediaUrl) { ?>
                    @if($mediaIsVideo)
                        <video src="{{ $mediaUrl }}" muted playsinline loop preload="metadata" aria-label="{{ $mediaAlt }}"></video>
                    @else
                        <img src="{{ $mediaUrl }}" alt="{{ $mediaAlt }}">
                    @endif
                <?php } else { ?>
                    <span class="sr-only">Approved homepage media is not configured for this section.</span>
                <?php } ?>
            </div>
        </section>
<?php } else { ?>

        <section {!! $sectionAttributes !!} class="home-section home-managed-content">
            <p class="eyebrow">{{ $section->label ?: 'Homepage content' }}</p>
            <h2>{{ $copy('title', $section->label ?: 'Homepage content') }}</h2>
            <p>{{ $copy('content') }}</p>
        </section>
<?php } ?>
