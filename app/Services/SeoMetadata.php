<?php

namespace App\Services;

use App\Models\{Category, ContentPage, Product, SeoSetting};
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
    ): array {
        // Blade view variables can persist across multiple requests in the same
        // long-lived application/test process. Only treat a $product variable as
        // SEO context on the actual public product route so category pages cannot
        // inherit stale Product metadata/schema from a previous render.
        if (! request()->routeIs('product')) {
            $product = null;
        }

        $isProductCatalogue = request()->routeIs('catalogue.show');

        if (! $product && ! $category && ! $managedPage && ! $isProductCatalogue) {
            $managedPage = $this->publishedPageForCurrentRequest();
        }

        $title = trim($fallbackTitle) ?: 'Emerald Rozalia';
        $description = self::DEFAULT_DESCRIPTION;
        $noindex = false;

        if ($isProductCatalogue) {
            $title = 'Emerald Rozalia Product Catalogue';
            $description = 'Browse the current Emerald Rozalia product catalogue of published Irish-made hats and caps.';
        }

        if ($product) {
            $seo = (array) $product->seo;
            $title = $product->meta_title ?: $product->name.' | Emerald Rozalia';
            $description = $product->meta_description
                ?: Str::limit((string) ($product->description ?: 'Shop '.$product->name.' from Emerald Rozalia, Irish headwear designed and manufactured in Limerick, Ireland.'), 160, '');
            $noindex = (bool) ($seo['noindex'] ?? false);
        } elseif ($category) {
            $seo = (array) $category->seo;
            $title = $category->meta_title ?: $category->name.' Hats & Caps | Emerald Rozalia';
            $description = $category->meta_description
                ?: Str::limit((string) ($category->description ?: 'Shop '.$category->name.' hats, caps and headwear from Emerald Rozalia, proudly manufacturing in Limerick, Ireland.'), 160, '');
            $noindex = (bool) ($seo['noindex'] ?? false);
        } elseif ($managedPage) {
            $meta = (array) $managedPage->meta;
            $title = $meta['title'] ?? $title;
            $description = $meta['description'] ?? Str::limit((string) ($managedPage->intro ?: $managedPage->body ?: self::DEFAULT_DESCRIPTION), 160, '');
            $noindex = (bool) ($meta['noindex'] ?? false);
        } elseif (request()->routeIs('home')) {
            $homeMeta = (array) ($this->setting('home_meta') ?? []);
            $title = $homeMeta['title'] ?? $title;
            $description = $homeMeta['description'] ?? $description;
            $noindex = (bool) ($homeMeta['noindex'] ?? false);
        }

        $canonical = $this->canonicalUrl();
        $schema = $this->schemaForPage(
            $canonical,
            trim((string) $title),
            trim((string) $description),
            $product,
            $category,
        );

        return [
            'title' => trim((string) $title),
            'description' => trim((string) $description),
            'canonical' => $canonical,
            'noindex' => $noindex,
            'schema' => $schema,
        ];
    }

    private function schemaForPage(
        string $canonical,
        string $title,
        string $description,
        ?Product $product,
        ?Category $category,
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

        $graph = [$organization];

        if ($product) {
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

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    private function canonicalUrl(): string
    {
        $base = rtrim((string) config('app.url', 'https://emeraldrozalia.com'), '/');
        $path = '/'.ltrim((string) request()->path(), '/');

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
