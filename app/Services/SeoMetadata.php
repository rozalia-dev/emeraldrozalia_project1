<?php

namespace App\Services;

use App\Models\{Category, ContentPage, Product, ProductSpin, ProductVideo, SeoSetting};
use Illuminate\Support\Str;

final class SeoMetadata
{
    private const DEFAULT_DESCRIPTION = 'Emerald Rozalia — Irish made hats and caps, proudly manufacturing in Limerick, Ireland.';

    private const DEFAULT_SCHEMA = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Emerald Rozalia Limited',
        'url' => 'https://emeraldrozalia.com',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Limerick',
            'addressCountry' => 'IE',
        ],
    ];

    public function forView(
        string $fallbackTitle,
        ?Product $product = null,
        ?Category $category = null,
        ?ContentPage $managedPage = null,
        ?ProductVideo $video = null,
        ?ProductSpin $spin = null,
    ): array {
        // Blade view variables can persist across multiple requests in the same
        // long-lived application/test process. Only treat a $product variable as
        // SEO context on the actual public product route so category pages cannot
        // inherit stale Product metadata/schema from a previous render.
        if (! request()->routeIs('product', 'legacy.product')) {
            $product = null;
        }
        if (! request()->routeIs('videos.watch')) {
            $video = null;
        }
        if (! request()->routeIs('spins.show')) {
            $spin = null;
        }
        if (! request()->routeIs('category', 'legacy.category')) {
            $category = null;
        }

        $isProductCatalogue = request()->routeIs('catalogue.show');

        if (! $product && ! $category && ! $managedPage && ! $video && ! $spin && ! $isProductCatalogue
            && request()->routeIs('content.page', 'page', 'size-guide', 'shipping-delivery', 'privacy-policy', 'terms-conditions')) {
            $managedPage = $this->publishedPageForCurrentRequest();
        }

        $title = trim($fallbackTitle) ?: 'Emerald Rozalia';
        $description = self::DEFAULT_DESCRIPTION;
        $noindex = false;

        if ($isProductCatalogue) {
            $title = 'Emerald Rozalia Product Catalogue';
            $description = 'Browse the current Emerald Rozalia product catalogue of published Irish-made hats and caps.';
        }

        if ($video) {
            $title = data_get($video->metadata, 'seo_title') ?: $video->title.' | Emerald Rozalia';
            $description = trim((string) data_get($video->metadata, 'description', ''));
            if ($description === '') {
                $description = $video->product?->name
                    ? $video->product->name.' video by Emerald Rozalia.'
                    : 'Emerald Rozalia product video.';
            }
            $noindex = ! $video->isPubliclyPlayable();
        } elseif ($spin) {
            $spinSeo = (array) $spin->seo;
            $title = trim((string) ($spinSeo['title'] ?? '')) ?: $spin->title.' | Emerald Rozalia';
            $description = trim((string) ($spinSeo['description'] ?? ''));
            if ($description === '') {
                $description = $spin->product?->name
                    ? 'Explore a 360° view of '.$spin->product->name.' by Emerald Rozalia.'
                    : 'Explore this Emerald Rozalia 360° product view.';
            }
            $noindex = ! $spin->isPublic();
        } elseif ($product) {
            $seo = (array) $product->seo;
            $title = $product->meta_title ?: $product->name.' | Emerald Rozalia';
            $description = $product->meta_description ?: $this->productDescription($product);
            $noindex = (bool) ($seo['noindex'] ?? false);
        } elseif ($category) {
            $seo = (array) $category->seo;
            $title = $category->meta_title ?: $category->name.' Hats & Caps | Emerald Rozalia';
            $description = $category->meta_description ?: $this->categoryDescription($category);
            $noindex = (bool) ($seo['noindex'] ?? false);
        } elseif ($managedPage) {
            $meta = (array) $managedPage->meta;
            $settings = $managedPage->settings();
            $savedTitle = trim((string) ($meta['title'] ?? ''));
            $savedDescription = trim((string) ($meta['description'] ?? ''));
            $title = $savedTitle !== '' ? $savedTitle : $title;
            $description = $savedDescription !== '' ? $savedDescription : $this->managedPageDescription($managedPage);
            $noindex = (
                array_key_exists('indexable', $settings)
                    ? ! (bool) $settings['indexable']
                    : (bool) ($meta['noindex'] ?? false)
            ) || $managedPage->requiresLogin() || request()->routeIs('admin.pages.preview');
        } elseif ($fixed = $this->fixedPageMetadata()) {
            $title = $fixed['title'];
            $description = $fixed['description'];
            $noindex = (bool) ($fixed['noindex'] ?? false);
        } elseif (request()->routeIs('home')) {
            $homeMeta = (array) ($this->setting('home_meta') ?? []);
            $title = $homeMeta['title'] ?? $title;
            $description = $homeMeta['description'] ?? $description;
            $noindex = (bool) ($homeMeta['noindex'] ?? false);
        }

        if ($this->hasCatalogFacetQuery()) {
            $noindex = true;
        }

        $canonical = $this->canonicalUrl();
        $socialImage = $this->socialImageForVideo($video)
            ?: $this->socialImageForSpin($spin)
            ?: $this->socialImageForProduct($product);
        $schema = $this->schemaForPage(
            $canonical,
            trim((string) $title),
            trim((string) $description),
            $product,
            $category,
            $video,
            $spin,
            $socialImage,
        );

        return [
            'title' => trim((string) $title),
            'description' => trim((string) $description),
            'canonical' => $canonical,
            'noindex' => $noindex,
            'image' => $socialImage,
            'og_type' => $product ? 'product' : ($video ? 'video.other' : 'website'),
            'schema' => $schema,
        ];
    }

    private function schemaForPage(
        string $canonical,
        string $title,
        string $description,
        ?Product $product,
        ?Category $category,
        ?ProductVideo $video,
        ?ProductSpin $spin,
        ?string $socialImage,
    ): array {
        $organization = $this->setting('organization_schema', self::DEFAULT_SCHEMA);
        if (! is_array($organization)) {
            $organization = self::DEFAULT_SCHEMA;
        }

        unset($organization['@context']);
        $logo = app(PublicMediaResolver::class)->forLegacyPath(
            'assets/brand/emerald-rozalia-wordmark.png',
            'Emerald Rozalia wordmark',
        );
        if ($logo) {
            $organization['logo'] = $logo['url'];
        } else {
            unset($organization['logo']);
        }

        $base = rtrim((string) config('app.url', 'https://emeraldrozalia.com'), '/');
        $organization['@id'] ??= $base.'/#organization';
        $organizationId = (string) $organization['@id'];
        $websiteId = $base.'/#website';

        $graph = [
            $organization,
            [
                '@type' => 'WebSite',
                '@id' => $websiteId,
                'url' => $base.'/',
                'name' => 'Emerald Rozalia',
                'publisher' => ['@id' => $organizationId],
            ],
        ];

        if ($video) {
            $videoSchema = [
                '@type' => 'VideoObject',
                '@id' => $canonical.'#video',
                'name' => $title,
                'description' => $description,
                'url' => $canonical,
                'uploadDate' => optional($this->videoPublicationDate($video))->toAtomString(),
            ];

            if ($socialImage) {
                $videoSchema['thumbnailUrl'] = [$socialImage];
            }

            $duration = (int) round((float) data_get($video->metadata, 'duration', 0));
            if ($duration > 0) {
                $videoSchema['duration'] = 'PT'.$duration.'S';
            }

            if ($video->platform === 'Website') {
                $videoSchema['contentUrl'] = route('videos.asset', [$video->uuid, 'video']);
            } elseif ($video->embed_url) {
                $videoSchema['embedUrl'] = $video->embed_url;
            }

            if ($video->product) {
                $videoSchema['about'] = [
                    '@type' => 'Product',
                    'name' => $video->product->name,
                    'url' => route('product', $video->product),
                ];
            }

            $graph[] = array_filter($videoSchema, fn ($value) => $value !== null && $value !== '');

            $breadcrumbItems = [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => rtrim((string) config('app.url'), '/').'/'],
            ];
            if ($video->product) {
                $breadcrumbItems[] = [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $video->product->name,
                    'item' => route('product', $video->product),
                ];
            }
            $breadcrumbItems[] = [
                '@type' => 'ListItem',
                'position' => count($breadcrumbItems) + 1,
                'name' => $title,
                'item' => $canonical,
            ];
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => $breadcrumbItems,
            ];
        } elseif ($product) {
            $inStock = (int) $product->stock > 0;
            if ($product->relationLoaded('variants')) {
                $inStock = $inStock || $product->variants
                    ->where('is_active', true)
                    ->sum(fn ($variant) => (int) $variant->stock) > 0;
            }

            $productSchema = [
                '@type' => 'Product',
                '@id' => $canonical.'#product',
                'name' => $product->name,
                'description' => $description,
                'sku' => (string) $product->sku,
                'url' => $canonical,
                'brand' => [
                    '@type' => 'Brand',
                    'name' => 'Emerald Rozalia',
                ],
                'offers' => [
                    '@type' => 'Offer',
                    'url' => $canonical,
                    'priceCurrency' => 'EUR',
                    'price' => number_format((float) $product->price, 2, '.', ''),
                    'availability' => $inStock
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'itemCondition' => 'https://schema.org/NewCondition',
                ],
            ];

            if ($socialImage) {
                $productSchema['image'] = [$socialImage];
            }

            if ($product->category) {
                $productSchema['category'] = $product->category->name;
            }

            if ($product->relationLoaded('reviews') && $product->reviews->isNotEmpty()) {
                $productSchema['aggregateRating'] = [
                    '@type' => 'AggregateRating',
                    'ratingValue' => round((float) $product->reviews->avg('rating'), 1),
                    'reviewCount' => $product->reviews->count(),
                ];
            }

            $graph[] = $productSchema;

            $breadcrumbItems = [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => rtrim((string) config('app.url'), '/').'/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Shop', 'item' => rtrim((string) config('app.url'), '/').'/shop'],
            ];
            if ($product->category) {
                $breadcrumbItems[] = [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $product->category->name,
                    'item' => rtrim((string) config('app.url'), '/').'/category/'.$product->category->slug,
                ];
            }
            $breadcrumbItems[] = [
                '@type' => 'ListItem',
                'position' => count($breadcrumbItems) + 1,
                'name' => $product->name,
                'item' => $canonical,
            ];

            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => $breadcrumbItems,
            ];
        } elseif ($category) {
            $graph[] = [
                '@type' => 'CollectionPage',
                '@id' => $canonical.'#collection',
                'name' => $title,
                'description' => $description,
                'url' => $canonical,
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => 'Emerald Rozalia',
                    'url' => rtrim((string) config('app.url'), '/').'/',
                ],
            ];
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => rtrim((string) config('app.url'), '/').'/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Shop', 'item' => rtrim((string) config('app.url'), '/').'/shop'],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $category->name, 'item' => $canonical],
                ],
            ];
        }

        if (! $category) {
            $webPage = [
                '@type' => 'WebPage',
                '@id' => $canonical.'#webpage',
                'url' => $canonical,
                'name' => $title,
                'description' => $description,
                'isPartOf' => ['@id' => $websiteId],
                'publisher' => ['@id' => $organizationId],
            ];

            if ($product) {
                $webPage['mainEntity'] = ['@id' => $canonical.'#product'];
            } elseif ($video) {
                $webPage['mainEntity'] = ['@id' => $canonical.'#video'];
            } elseif ($spin && $spin->product) {
                $webPage['about'] = [
                    '@type' => 'Product',
                    'name' => $spin->product->name,
                    'url' => route('product', $spin->product),
                ];
                if ($socialImage) {
                    $webPage['primaryImageOfPage'] = [
                        '@type' => 'ImageObject',
                        'contentUrl' => $socialImage,
                    ];
                }
            }

            $graph[] = $webPage;

            if ($spin && $spin->product) {
                $graph[] = [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base.'/'],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $spin->product->name, 'item' => route('product', $spin->product)],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => $canonical],
                    ],
                ];
            } elseif (! $product && ! $video && $canonical !== $base.'/') {
                $graph[] = [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base.'/'],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $canonical],
                    ],
                ];
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    private function fixedPageMetadata(): ?array
    {
        $path = '/'.trim((string) request()->path(), '/');
        if (request()->routeIs('page')) {
            $page = trim((string) request()->route('page'), '/');
            if ($page !== '') {
                $path = '/'.$page;
            }
        }

        $defaults = $this->fixedPageDefaults($path);
        if (! $defaults) {
            return null;
        }

        $saved = (array) data_get((array) $this->setting('fixed_page_meta', []), $path, []);

        return [
            'title' => trim((string) ($saved['title'] ?? '')) ?: $defaults['title'],
            'description' => trim((string) ($saved['description'] ?? '')) ?: $defaults['description'],
            'noindex' => (bool) ($saved['noindex'] ?? false),
        ];
    }

    public function fixedPageDefaults(string $path): ?array
    {
        $path = '/'.trim($path, '/');

        return match ($path) {
            '/shop' => ['title' => 'Shop | Emerald Rozalia', 'description' => 'Shop Emerald Rozalia hats, caps and headwear designed and manufactured in Limerick, Ireland.'],
            '/product-catalogue' => ['title' => 'Product Catalogue | Emerald Rozalia', 'description' => 'Browse the Emerald Rozalia product catalogue of Irish-designed hats, caps and headwear.'],
            '/collections' => ['title' => 'Collections | Emerald Rozalia', 'description' => 'Explore Emerald Rozalia headwear collections, including Irish traditional, heritage and seasonal styles.'],
            '/new-arrivals' => ['title' => 'New Arrivals | Emerald Rozalia', 'description' => 'Discover the latest Emerald Rozalia hats, caps and headwear added to our online collection.'],
            '/virtual-tryon' => ['title' => 'Virtual Try-On | Emerald Rozalia', 'description' => 'Preview selected Emerald Rozalia hats and caps with our virtual try-on experience.'],
            '/irish-traditional' => ['title' => 'Irish Traditional Headwear | Emerald Rozalia', 'description' => 'Explore Irish traditional caps and headwear by Emerald Rozalia, designed and manufactured in Limerick, Ireland.'],
            '/irish-heritage' => ['title' => 'Irish Heritage Headwear | Emerald Rozalia', 'description' => 'Explore Irish heritage hats and caps by Emerald Rozalia, inspired by timeless Irish style and craftsmanship.'],
            '/factory' => ['title' => 'Factory | Emerald Rozalia', 'description' => 'Learn about Emerald Rozalia headwear manufacturing, production and craftsmanship in Limerick, Ireland.'],
            '/quality' => ['title' => 'Quality | Emerald Rozalia', 'description' => 'Learn about Emerald Rozalia quality standards, materials and craftsmanship for hats and caps.'],
            '/corporate-orders' => ['title' => 'Corporate Orders | Emerald Rozalia', 'description' => 'Explore corporate headwear orders, branded hats and caps from Emerald Rozalia.'],
            '/bulk-orders' => ['title' => 'Bulk Orders | Emerald Rozalia', 'description' => 'Order Emerald Rozalia hats and caps in bulk for teams, organizations, retailers and events.'],
            '/franchise' => ['title' => 'Franchise | Emerald Rozalia', 'description' => 'Explore Emerald Rozalia franchise opportunities and retail partnership information.'],
            '/careers' => ['title' => 'Careers | Emerald Rozalia', 'description' => 'Explore career opportunities with Emerald Rozalia across headwear, manufacturing, retail and operations.'],
            '/global-network' => ['title' => 'Global Network | Emerald Rozalia', 'description' => 'Discover the Emerald Rozalia global network for headwear, retail, franchise and distribution partnerships.'],
            '/contact' => ['title' => 'Contact Emerald Rozalia', 'description' => 'Contact Emerald Rozalia for product, order, wholesale, franchise and customer service enquiries.'],
            '/size-guide' => ['title' => 'Hat & Cap Size Guide | Emerald Rozalia', 'description' => 'Use the Emerald Rozalia size guide to measure your head and choose the right hat or cap fit before ordering.'],
            '/shipping-delivery' => ['title' => 'Shipping & Delivery | Emerald Rozalia', 'description' => 'View Emerald Rozalia delivery estimates for Ireland, the EU, USA, Canada and worldwide orders.'],
            '/privacy-policy' => ['title' => 'Privacy Policy | Emerald Rozalia', 'description' => 'Read how Emerald Rozalia Limited handles personal information submitted through our website, accounts, enquiries and orders.'],
            '/terms-conditions' => ['title' => 'Terms & Conditions | Emerald Rozalia', 'description' => 'Read the Emerald Rozalia website and ordering terms covering products, availability, pricing, delivery and customer rights.'],
            '/returns-refunds' => ['title' => 'Returns & Refunds | Emerald Rozalia', 'description' => 'Read the Emerald Rozalia returns and refunds information for online purchases and customer support.'],
            '/our-story' => ['title' => 'Our Story | Emerald Rozalia', 'description' => 'Discover Emerald Rozalia, an Irish headwear brand focused on design, manufacturing and craftsmanship in Limerick, Ireland.'],
            default => null,
        };
    }

    public function managedPageDescription(ContentPage $page): string
    {
        $source = trim((string) ($page->intro ?: $page->body ?: self::DEFAULT_DESCRIPTION));
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($source)));

        return Str::limit($plain !== '' ? $plain : self::DEFAULT_DESCRIPTION, 160, '');
    }

    public function productDescription(Product $product): string
    {
        $description = trim(strip_tags((string) $product->description));
        if (Str::length($description) >= 80) {
            return Str::limit($description, 160, '');
        }

        $details = collect([
            $description,
            filled($product->material) ? 'Made with '.$product->material.'.' : null,
            $product->category?->name ? 'Explore '.$product->category->name.' by Emerald Rozalia.' : null,
            'Irish headwear designed and manufactured in Limerick, Ireland.',
        ])->filter()->implode(' ');

        return Str::limit($details ?: 'Shop '.$product->name.' from Emerald Rozalia, Irish headwear designed and manufactured in Limerick, Ireland.', 160, '');
    }

    public function categoryDescription(Category $category): string
    {
        $description = trim(strip_tags((string) $category->description));
        if (Str::length($description) >= 80) {
            return Str::limit($description, 160, '');
        }

        $details = collect([
            $description,
            'Shop '.$category->name.' hats, caps and headwear from Emerald Rozalia.',
            'Designed and manufactured in Limerick, Ireland.',
        ])->filter()->implode(' ');

        return Str::limit($details, 160, '');
    }

    private function videoPublicationDate(ProductVideo $video): mixed
    {
        $publishAt = data_get($video->metadata, 'publish_at');
        if (filled($publishAt)) {
            try {
                return \Illuminate\Support\Carbon::parse((string) $publishAt);
            } catch (\Throwable) {
                // Legacy metadata may contain an invalid publication value.
            }
        }

        return $video->created_at;
    }

    private function socialImageForSpin(?ProductSpin $spin): ?string
    {
        if (! $spin || ! $spin->isPublic()) {
            return null;
        }

        $frames = $spin->frames ?? [];
        if (count($frames) < 1) {
            return null;
        }

        return route('spins.frame', [$spin->uuid, array_key_first($frames)]);
    }

    private function socialImageForVideo(?ProductVideo $video): ?string
    {
        if (! $video || blank(data_get($video->metadata, 'poster'))) {
            return null;
        }

        return route('videos.asset', [$video->uuid, 'poster']);
    }

    private function socialImageForProduct(?Product $product): ?string
    {
        if (! $product || ! $product->relationLoaded('media')) {
            return null;
        }

        $resolver = app(PublicMediaResolver::class);
        foreach ($product->media as $media) {
            if (! in_array((string) $media->type, ['image', 'gallery'], true)) {
                continue;
            }

            $descriptor = $resolver->forProductMedia($media, $product->name);
            if (filled($descriptor['url'] ?? null)) {
                return (string) $descriptor['url'];
            }
        }

        return null;
    }

    private function hasCatalogFacetQuery(): bool
    {
        if (! request()->routeIs('shop', 'category', 'legacy.category', 'new.arrivals')) {
            return false;
        }

        foreach (array_keys(request()->query()) as $key) {
            if ((string) $key !== 'page') {
                return true;
            }
        }

        return false;
    }

    private function canonicalUrl(): string
    {
        $base = rtrim((string) config('app.url', 'https://emeraldrozalia.com'), '/');
        $path = '/'.ltrim((string) request()->path(), '/');

        if (request()->routeIs('admin.pages.preview')) {
            $previewPage = request()->route('page');
            if ($previewPage instanceof ContentPage) {
                return $previewPage->isHomepage()
                    ? $base.'/'
                    : $base.'/'.trim((string) $previewPage->slug, '/');
            }
        }

        if (request()->routeIs('store.owner')) {
            return $base.'/franchise';
        }

        if (request()->routeIs('page')) {
            $page = trim((string) request()->route('page'), '/');
            if (in_array($page, [
                'collections',
                'new-arrivals',
                'corporate-orders',
                'bulk-orders',
                'franchise',
                'careers',
                'global-network',
                'factory',
                'contact',
                'virtual-tryon',
                'irish-traditional',
                'irish-heritage',
                'size-guide',
                'shipping-delivery',
                'privacy-policy',
                'terms-conditions',
            ], true)) {
                return $base.'/'.$page;
            }
        }

        if (request()->routeIs('shop', 'category', 'legacy.category', 'new.arrivals')) {
            $page = (int) request()->query('page', 1);
            if ($page > 1) {
                return $base.$path.'?page='.$page;
            }
        }

        return $path === '/' ? $base.'/' : $base.$path;
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return SeoSetting::query()->where('key', $key)->first()?->value ?? $default;
    }

    private function publishedPageForCurrentRequest(): ?ContentPage
    {
        $slug = (string) request()->segment(1);
        if (blank($slug) || in_array($slug, ['admin', 'account', 'api', 'cart', 'category', 'checkout', 'login', 'product', 'product-catalogue', 'register', 'shop', 'storage', 'up'], true)) {
            return null;
        }

        return ContentPage::query()
            ->where('slug', $slug)
            ->where('locale', app()->getLocale())
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now()))
            ->get()
            ->first(fn (ContentPage $page): bool => $page->isPublic()
                && (! $page->requiresLogin() || auth()->check()));
    }
}
