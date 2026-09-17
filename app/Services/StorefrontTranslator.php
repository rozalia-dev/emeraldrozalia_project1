<?php

namespace App\Services;

use App\Models\{Language, StorefrontTranslation};
use Illuminate\Support\Collection;

class StorefrontTranslator
{
    private array $maps = [];

    public function translate(string $source, ?string $key = null, ?string $locale = null): string
    {
        $source = trim($source) === '' ? $source : $source;
        $locale ??= app(TenantContext::class)->locale();

        foreach ($this->localeChain($locale) as $candidate) {
            $map = $this->map($candidate);
            if ($key !== null && isset($map['keys'][$key])) return $map['keys'][$key];

            $hash = sha1(self::normalize($source));
            if (isset($map['sources'][$hash])) return $map['sources'][$hash];
        }

        return $source;
    }

    public function runtimeMap(?string $locale = null): array
    {
        $locale ??= app(TenantContext::class)->locale();
        $result = [];

        foreach (array_reverse($this->localeChain($locale)) as $candidate) {
            $map = $this->map($candidate);
            foreach ($map['records'] as $record) {
                $result[self::normalize((string) $record->source_text)] = (string) $record->translation;
            }
        }

        return $result;
    }

    public function records(?string $locale = null): Collection
    {
        $locale ??= app(TenantContext::class)->locale();
        return $this->map($locale)['records'];
    }

    public static function normalize(string $value): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    private function map(string $locale): array
    {
        if (isset($this->maps[$locale])) return $this->maps[$locale];

        $companyId = app(TenantContext::class)->company()?->id;
        $records = StorefrontTranslation::withoutGlobalScopes()
            ->where('locale', $locale)
            ->where('active', true)
            ->where(function ($query) use ($companyId): void {
                $query->whereNull('company_id');
                if ($companyId) $query->orWhere('company_id', $companyId);
            })
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->get();

        $keys = [];
        $sources = [];
        foreach ($records as $record) {
            $keys[(string) $record->translation_key] = (string) $record->translation;
            $sources[(string) $record->source_hash] = (string) $record->translation;
        }

        return $this->maps[$locale] = compact('records', 'keys', 'sources');
    }

    private function localeChain(string $locale): array
    {
        $chain = [];
        $candidate = $locale;

        for ($i = 0; $i < 4 && $candidate !== ''; $i++) {
            if (! in_array($candidate, $chain, true)) $chain[] = $candidate;
            $language = Language::query()->whereKey($candidate)->first();
            $candidate = trim((string) ($language?->fallback_locale ?? ''));
        }

        $companyDefault = trim((string) (app(TenantContext::class)->company()?->default_locale ?? config('app.fallback_locale', 'en')));
        if ($companyDefault !== '' && ! in_array($companyDefault, $chain, true)) $chain[] = $companyDefault;

        return $chain;
    }
}
