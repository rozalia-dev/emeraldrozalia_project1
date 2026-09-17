<?php

namespace App\Services;

use App\Models\{Company, Currency, ExchangeRate, Language, User};
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class TenantContext
{
    public function company(?User $user = null): ?Company
    {
        $user ??= auth()->user();
        $selectedId = session('company_id');

        if ($selectedId) {
            $query = Company::query()->whereKey((int) $selectedId)->where('active', true);
            if ($user && ! $user->is_admin) $query->whereHas('users', fn ($users) => $users->whereKey($user->getKey()));
            if ($company = $query->first()) return $company;
        }

        if ($user && ! $user->is_admin) {
            return $user->companies()->where('companies.active', true)->orderByDesc('company_user.is_default')->first();
        }

        return Company::where('active', true)->first();
    }

    public function baseCurrency(): string
    {
        return strtoupper((string) ($this->company()?->base_currency ?: 'EUR'));
    }

    public function defaultLocale(): string
    {
        return (string) ($this->company()?->default_locale ?: config('app.locale', 'en'));
    }

    public function availableLanguages(): Collection
    {
        $company = $this->company();
        if ($company) {
            $query = $company->languages()->where('languages.active', true);
            if (Schema::hasColumn('company_languages', 'enabled_storefront')) $query->wherePivot('enabled_storefront', true);
            $languages = $query
                ->orderByDesc('company_languages.is_default')
                ->orderBy('languages.sort_order')
                ->orderBy('languages.name')
                ->get();
            if ($languages->isNotEmpty()) return $languages;
        }

        return Language::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    public function availableCurrencies(): Collection
    {
        $company = $this->company();
        if ($company) {
            $currencies = $company->currencies()
                ->where('currencies.active', true)
                ->wherePivot('enabled_storefront', true)
                ->orderByDesc('company_currencies.is_base')
                ->orderBy('currencies.sort_order')
                ->orderBy('currencies.code')
                ->get();
            if ($currencies->isNotEmpty()) return $currencies;
        }

        return Currency::query()->where('active', true)->orderBy('sort_order')->orderBy('code')->get();
    }

    public function locale(): string
    {
        $available = $this->availableLanguages()->pluck('locale')->map(fn ($locale) => (string) $locale)->all();
        $requested = (string) session('locale', '');
        if ($requested !== '' && in_array($requested, $available, true)) return $requested;

        $company = $this->company();
        $fallback = (string) (app(PublishedSiteSettings::class)->forCompany($company)['localization']['default_language']
            ?? $company?->default_locale
            ?? config('app.locale'));

        return in_array($fallback, $available, true) ? $fallback : ((string) ($available[0] ?? config('app.locale', 'en')));
    }

    public function languageModel(?string $locale = null): ?Language
    {
        $locale ??= $this->locale();
        return $this->availableLanguages()->firstWhere('locale', $locale)
            ?? Language::query()->whereKey($locale)->where('active', true)->first();
    }

    public function currency(): string
    {
        $available = $this->availableCurrencies()->pluck('code')->map(fn ($code) => strtoupper((string) $code))->all();
        $requested = strtoupper((string) session('currency', ''));
        if ($requested !== '' && in_array($requested, $available, true)) return $requested;

        $company = $this->company();
        $fallback = strtoupper((string) (app(PublishedSiteSettings::class)->forCompany($company)['localization']['default_currency']
            ?? $company?->base_currency
            ?? 'EUR'));

        return in_array($fallback, $available, true) ? $fallback : ((string) ($available[0] ?? $this->baseCurrency()));
    }

    public function exchangeRate(?string $from = null, ?string $to = null): ?float
    {
        $from = strtoupper($from ?: $this->baseCurrency());
        $to = strtoupper($to ?: $this->currency());
        if ($from === $to) return 1.0;

        if (($direct = $this->storedRate($from, $to)) !== null) return $direct;

        foreach (array_values(array_unique([$this->baseCurrency(), 'EUR'])) as $bridge) {
            if ($bridge === $from || $bridge === $to) continue;
            $fromBridge = $this->storedRate($from, $bridge);
            $bridgeTo = $this->storedRate($bridge, $to);
            if ($fromBridge !== null && $bridgeTo !== null) return $fromBridge * $bridgeTo;
        }

        return null;
    }

    public function convert(int|float|string $amount, ?string $from = null, ?string $to = null): float
    {
        $from ??= $this->baseCurrency();
        $to ??= $this->currency();
        $rate = $this->exchangeRate($from, $to);
        if ($rate === null) return (float) $amount;

        $target = $this->currencyModel($to);
        return round((float) $amount * $rate, (int) ($target?->decimals ?? 2));
    }

    public function convertAmount(int|float|string $amount, ?string $from = null, ?string $to = null): string
    {
        return Money::round($this->convert($amount, $from, $to));
    }

    public function currencyModel(?string $code = null): ?Currency
    {
        $code = strtoupper($code ?? $this->currency());
        return $this->availableCurrencies()->firstWhere('code', $code)
            ?? Currency::query()->whereKey($code)->where('active', true)->first();
    }

    public function formatMoney(int|float|string $amount, ?string $from = null, ?string $to = null): string
    {
        $from ??= $this->baseCurrency();
        $to = strtoupper($to ?? $this->currency());
        $currency = $this->currencyModel($to);
        $converted = $this->convert($amount, $from, $to);

        return $currency?->format($converted) ?? $to.' '.number_format($converted, 2, '.', ',');
    }

    public function storefrontPayload(): array
    {
        $currency = $this->currencyModel();
        $base = $this->baseCurrency();
        $baseModel = $this->currencyModel($base) ?? Currency::query()->whereKey($base)->first();
        $code = $currency?->code ?? $this->currency();
        $language = $this->languageModel();

        return [
            'locale' => $this->locale(),
            'direction' => $language?->isRtl() ? 'rtl' : 'ltr',
            'currency' => $code,
            'symbol' => (string) ($currency?->symbol ?: $code),
            'symbol_position' => (string) ($currency?->symbol_position ?: 'before'),
            'decimal_separator' => (string) ($currency?->decimal_separator ?: '.'),
            'thousands_separator' => (string) ($currency?->thousands_separator ?: ','),
            'decimals' => (int) ($currency?->decimals ?? 2),
            'base_currency' => $base,
            'base_symbol' => (string) ($baseModel?->symbol ?: $base),
            'rate' => $this->exchangeRate($base, $code) ?? 1.0,
        ];
    }

    private function storedRate(string $from, string $to): ?float
    {
        if ($from === $to) return 1.0;

        $rate = ExchangeRate::query()
            ->where('base_currency', $from)
            ->where('quote_currency', $to)
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->value('rate');
        if ($rate !== null && (float) $rate > 0) return (float) $rate;

        $inverse = ExchangeRate::query()
            ->where('base_currency', $to)
            ->where('quote_currency', $from)
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->value('rate');

        return $inverse !== null && (float) $inverse > 0 ? 1 / (float) $inverse : null;
    }
}
