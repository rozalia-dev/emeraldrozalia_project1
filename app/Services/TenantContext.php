<?php

namespace App\Services;

use App\Models\{Company, Currency, ExchangeRate, Language, User};
use Illuminate\Support\Collection;

class TenantContext
{
    public function company(?User $user = null): ?Company
    {
        $user ??= auth()->user();
        $selectedId = session('company_id');

        if ($selectedId) {
            $query = Company::query()->whereKey((int) $selectedId)->where('active', true);
            if ($user && ! $user->is_admin) {
                $query->whereHas('users', fn ($users) => $users->whereKey($user->getKey()));
            }
            if ($company = $query->first()) return $company;
        }

        if ($user && ! $user->is_admin) {
            return $user->companies()->where('companies.active', true)->orderByDesc('company_user.is_default')->first();
        }

        return Company::where('active', true)->first();
    }

    public function availableLanguages(): Collection
    {
        $company = $this->company();
        if ($company) {
            $languages = $company->languages()
                ->where('languages.active', true)
                ->orderByDesc('company_languages.is_default')
                ->orderBy('languages.name')
                ->get();
            if ($languages->isNotEmpty()) return $languages;
        }

        return Language::query()->where('active', true)->orderBy('name')->get();
    }

    public function availableCurrencies(): Collection
    {
        $company = $this->company();
        if ($company) {
            $currencies = $company->currencies()
                ->where('currencies.active', true)
                ->wherePivot('enabled_storefront', true)
                ->orderByDesc('company_currencies.is_base')
                ->orderBy('currencies.code')
                ->get();
            if ($currencies->isNotEmpty()) return $currencies;
        }

        return Currency::query()->where('active', true)->orderBy('code')->get();
    }

    public function locale(): string
    {
        $available = $this->availableLanguages()->pluck('locale')->map(fn ($locale) => (string) $locale)->all();
        $requested = (string) session('locale', '');
        if ($requested !== '' && in_array($requested, $available, true)) {
            return $requested;
        }

        $company = $this->company();
        $fallback = (string) (app(PublishedSiteSettings::class)->forCompany($company)['localization']['default_language']
            ?? $company?->default_locale
            ?? config('app.locale'));

        return in_array($fallback, $available, true) ? $fallback : ((string) ($available[0] ?? config('app.locale')));
    }

    public function currency(): string
    {
        $available = $this->availableCurrencies()->pluck('code')->map(fn ($code) => strtoupper((string) $code))->all();
        $requested = strtoupper((string) session('currency', ''));
        if ($requested !== '' && in_array($requested, $available, true)) {
            return $requested;
        }

        $company = $this->company();
        $fallback = strtoupper((string) (app(PublishedSiteSettings::class)->forCompany($company)['localization']['default_currency']
            ?? $company?->base_currency
            ?? 'EUR'));

        return in_array($fallback, $available, true) ? $fallback : ((string) ($available[0] ?? 'EUR'));
    }

    public function exchangeRate(string $from = 'EUR', ?string $to = null): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to ?? $this->currency());
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

    public function convert(float $amount, string $from = 'EUR', ?string $to = null): float
    {
        $to ??= $this->currency();
        $rate = $this->exchangeRate($from, $to);
        return $rate === null ? $amount : round($amount * $rate, 2);
    }

    public function currencyModel(?string $code = null): ?Currency
    {
        $code = strtoupper($code ?? $this->currency());
        return $this->availableCurrencies()->firstWhere('code', $code)
            ?? Currency::query()->whereKey($code)->where('active', true)->first();
    }

    public function formatMoney(int|float|string $amount, string $from = 'EUR', ?string $to = null): string
    {
        $to = strtoupper($to ?? $this->currency());
        $currency = $this->currencyModel($to);
        $converted = $this->convert((float) $amount, $from, $to);
        $decimals = (int) ($currency?->decimals ?? 2);
        $symbol = (string) ($currency?->symbol ?: $to.' ');

        return $symbol.number_format($converted, $decimals, '.', ',');
    }

    public function storefrontPayload(): array
    {
        $currency = $this->currencyModel();
        $code = $currency?->code ?? $this->currency();

        return [
            'locale' => $this->locale(),
            'currency' => $code,
            'symbol' => (string) ($currency?->symbol ?: $code.' '),
            'decimals' => (int) ($currency?->decimals ?? 2),
            'rate' => $this->exchangeRate('EUR', $code) ?? 1.0,
        ];
    }
}
