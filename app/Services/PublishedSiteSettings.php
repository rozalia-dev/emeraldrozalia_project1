<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PublishedSiteSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class PublishedSiteSettings
{
    public const PUBLIC_SECTIONS = [
        'general-configuration',
        'company-branding',
        'localization',
    ];

    private const PUBLIC_KEYS = [
        'general-configuration' => [
            'company_name', 'system_name', 'company_legal_name', 'website_url', 'country',
            'timezone', 'default_language', 'default_currency', 'primary_email', 'primary_phone',
            'support_email', 'support_phone',
        ],
        'company-branding' => [
            'legal_name', 'trading_name', 'address', 'city', 'county', 'postcode', 'country',
            'phone', 'email', 'website', 'description', 'logo_path', 'brand_primary',
            'brand_secondary', 'brand_accent', 'footer_text',
        ],
        'localization' => [
            'default_language', 'default_currency', 'country_code', 'region', 'timezone',
            'date_format', 'time_format', 'first_day', 'decimal_separator', 'thousands_separator',
            'measurement_system', 'rtl_support', 'fallback_language',
        ],
    ];

    public function publish(
        string $section,
        array $data,
        ?Company $company = null,
        ?User $user = null,
    ): ?PublishedSiteSetting {
        if (! in_array($section, self::PUBLIC_SECTIONS, true)) {
            return null;
        }

        $company ??= app(TenantContext::class)->company();
        $query = $this->forCompanyQuery($section, $company);
        $latest = $query->latest('version')->lockForUpdate()->first();

        return PublishedSiteSetting::create([
            'company_id' => $company?->getKey(),
            'section' => $section,
            'version' => ((int) ($latest?->version ?? 0)) + 1,
            'data' => $this->onlyPublic($section, $data),
            'published_by' => $user?->getKey() ?? auth()->id(),
            'published_at' => now(),
        ]);
    }

    public function bootstrap(Company $company, ?User $user = null): void
    {
        $defaults = $this->defaults($company);

        foreach (self::PUBLIC_SECTIONS as $section) {
            if ($this->forCompanyQuery($section, $company)->exists()) {
                continue;
            }

            $this->publish($section, $defaults[$section], $company, $user);
        }
    }

    public function forCompany(?Company $company = null): array
    {
        $company ??= app(TenantContext::class)->company();
        $defaults = $this->defaults($company);
        $sections = [];
        $versions = [];
        $publishedAt = [];
        $hasPublishedRevision = false;

        foreach (self::PUBLIC_SECTIONS as $section) {
            $revision = $this->forCompanyQuery($section, $company)->latest('version')->first();
            $fallback = $this->companyFallback($section, $company);

            if ($revision) {
                $hasPublishedRevision = true;
                $sections[$section] = array_merge(
                    $defaults[$section],
                    $this->onlyPublic($section, $revision->data ?? []),
                );
                $versions[$section] = (int) $revision->version;
                $publishedAt[$section] = $revision->published_at?->toIso8601String();
            } else {
                $sections[$section] = array_merge($defaults[$section], $fallback);
                $versions[$section] = 0;
                $publishedAt[$section] = null;
            }
        }

        $branding = $sections['company-branding'];
        $branding['header_logo_path'] = $this->headerLogoPath($branding['logo_path'] ?? null);
        $branding['footer_logo_path'] = $this->footerLogoPath($branding['logo_path'] ?? null);
        $branding['brand_primary'] = $this->safeHex($branding['brand_primary'] ?? null, '#075b2f');
        $branding['brand_secondary'] = $this->safeHex($branding['brand_secondary'] ?? null, '#0b1711');
        $branding['brand_accent'] = $this->safeHex($branding['brand_accent'] ?? null, '#7fbd42');
        $sections['company-branding'] = $branding;
        $theme = app(ThemeVersionService::class)->publicSnapshot($company, 'production', app()->getLocale());

        return [
            'meta' => [
                'source' => $hasPublishedRevision ? 'published-site-settings' : 'company-fallback',
                'version' => max($versions),
                'section_versions' => $versions,
                'published_at' => $publishedAt,
            ],
            'theme' => $theme['tokens'],
            'theme_meta' => $theme['meta'],
            'theme_assets' => $theme['assets'],
            ...$sections,
        ];
    }

    /** @return Builder<PublishedSiteSetting> */
    private function forCompanyQuery(string $section, ?Company $company): Builder
    {
        return PublishedSiteSetting::query()
            ->where('section', $section)
            ->when(
                $company,
                fn (Builder $query): Builder => $query->where('company_id', $company->getKey()),
                fn (Builder $query): Builder => $query->whereNull('company_id'),
            );
    }

    private function onlyPublic(string $section, array $data): array
    {
        $filtered = array_intersect_key($data, array_flip(self::PUBLIC_KEYS[$section] ?? []));

        if ($section === 'company-branding') {
            if (array_key_exists('logo_path', $filtered)) {
                $filtered['logo_path'] = in_array($filtered['logo_path'], [
                    '/assets/logo/logo_one_line.png',
                    '/assets/logo/logo_two_line.png',
                ], true) ? $filtered['logo_path'] : '/assets/logo/logo_two_line.png';
            }
            foreach (['brand_primary' => '#075b2f', 'brand_secondary' => '#0b1711', 'brand_accent' => '#7fbd42'] as $key => $fallback) {
                if (array_key_exists($key, $filtered)) {
                    $filtered[$key] = $this->safeHex($filtered[$key], $fallback);
                }
            }
        }

        return $filtered;
    }

    private function companyFallback(string $section, ?Company $company): array
    {
        if (! $company) {
            return [];
        }

        $stored = is_array($company->settings) ? $company->settings : [];

        return match ($section) {
            'general-configuration' => [
                'company_name' => $company->name,
                'company_legal_name' => $company->legal_name,
                'default_language' => $company->default_locale,
                'default_currency' => $company->base_currency,
                'country' => $company->country_code,
            ],
            'company-branding' => [
                'legal_name' => $company->legal_name,
                'trading_name' => $company->name,
                'country' => $company->country_code === 'IE' ? 'Ireland' : $company->country_code,
                ...$this->onlyPublic('company-branding', $stored),
            ],
            'localization' => [
                'default_language' => $company->default_locale,
                'default_currency' => $company->base_currency,
                'country_code' => $company->country_code,
            ],
            default => [],
        };
    }

    private function defaults(?Company $company): array
    {
        $name = $company?->name ?: 'Emerald Rozalia';
        $legalName = $company?->legal_name ?: 'Emerald Rozalia Limited';
        $locale = $company?->default_locale ?: 'en';
        $currency = $company?->base_currency ?: 'EUR';
        $country = $company?->country_code ?: 'IE';

        return [
            'general-configuration' => [
                'company_name' => $name,
                'system_name' => 'Emerald Rozalia Hats & Caps Management System',
                'company_legal_name' => $legalName,
                'website_url' => 'https://emeraldrozalia.ie',
                'country' => $country,
                'timezone' => 'Europe/Dublin',
                'default_language' => $locale,
                'default_currency' => $currency,
                'primary_email' => 'urmos@rozalia.ie',
                'primary_phone' => '0899788187',
                'support_email' => 'urmos@rozalia.ie',
                'support_phone' => '0899788187',
            ],
            'company-branding' => [
                'legal_name' => $legalName,
                'trading_name' => $name,
                'address' => 'Limerick, Ireland',
                'city' => 'Limerick',
                'county' => 'Limerick',
                'postcode' => '',
                'country' => 'Ireland',
                'phone' => '0899788187',
                'email' => 'urmos@rozalia.ie',
                'website' => 'https://emeraldrozalia.ie',
                'description' => 'Proudly manufacturing hats and caps in Limerick, Ireland.',
                'logo_path' => '/assets/logo/logo_two_line.png',
                'brand_primary' => '#075b2f',
                'brand_secondary' => '#0b1711',
                'brand_accent' => '#7fbd42',
                'footer_text' => '© '.now()->year.' '.$legalName.'. All rights reserved.',
            ],
            'localization' => [
                'default_language' => $locale,
                'default_currency' => $currency,
                'country_code' => $country,
                'region' => 'Ireland',
                'timezone' => 'Europe/Dublin',
                'date_format' => 'DD/MM/YYYY',
                'time_format' => '24-hour',
                'first_day' => 'Monday',
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'measurement_system' => 'Metric',
                'rtl_support' => false,
                'fallback_language' => 'en',
            ],
        ];
    }

    private function headerLogoPath(?string $path): string
    {
        return $path === '/assets/logo/logo_one_line.png'
            ? $path
            : '/assets/logo/logo_one_line.png';
    }

    private function footerLogoPath(?string $path): string
    {
        return $path === '/assets/logo/logo_two_line.png'
            ? $path
            : '/assets/logo/logo_two_line.png';
    }

    private function safeHex(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/\A#[0-9a-fA-F]{6}\z/', $value)
            ? strtolower($value)
            : $fallback;
    }
}
