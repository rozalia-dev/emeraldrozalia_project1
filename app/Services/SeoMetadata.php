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
        'logo' => 'https://emeraldrozalia.com/assets/brand/emerald-rozalia-wordmark.png',
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
        if (! $product && ! $category && ! $managedPage) {
            $managedPage = $this->publishedPageForCurrentRequest();
        }

        $title = trim($fallbackTitle) ?: 'Emerald Rozalia';
        $description = self::DEFAULT_DESCRIPTION;
        $noindex = false;

        if ($product) {
            $seo = (array) $product->seo;
            $title = $product->meta_title ?: $title;
            $description = $product->meta_description ?: Str::limit((string) ($product->description ?: self::DEFAULT_DESCRIPTION), 160, '');
            $noindex = (bool) ($seo['noindex'] ?? false);
        } elseif ($category) {
            $seo = (array) $category->seo;
            $title = $category->meta_title ?: $title;
            $description = $category->meta_description ?: Str::limit((string) ($category->description ?: self::DEFAULT_DESCRIPTION), 160, '');
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

        $schema = $this->setting('organization_schema', self::DEFAULT_SCHEMA);

        return [
            'title' => trim((string) $title),
            'description' => trim((string) $description),
            'canonical' => url()->current(),
            'noindex' => $noindex,
            'schema' => is_array($schema) ? $schema : self::DEFAULT_SCHEMA,
        ];
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return SeoSetting::query()->where('key', $key)->first()?->value ?? $default;
    }

    private function publishedPageForCurrentRequest(): ?ContentPage
    {
        $slug = (string) request()->segment(1);
        if (blank($slug) || in_array($slug, ['admin', 'account', 'api', 'cart', 'category', 'checkout', 'login', 'product', 'register', 'shop', 'storage', 'up'], true)) {
            return null;
        }

        return ContentPage::query()
            ->where('slug', $slug)
            ->where('locale', app()->getLocale())
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now()))
            ->first();
    }
}
