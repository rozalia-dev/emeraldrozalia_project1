<?php

namespace App\Services;

use App\Models\{Company, Currency, ExchangeRate, User};

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

    public function locale(): string
    {
        if (session()->has('locale')) {
            return (string) session('locale');
        }

        $company = $this->company();
        return (string) (app(PublishedSiteSettings::class)->forCompany($company)['localization']['default_language']
            ?? $company?->default_locale
            ?? config('app.locale'));
    }

    public function currency(): string
    {
        if (session()->has('currency')) {
            return (string) session('currency');
        }

        $company = $this->company();
        return (string) (app(PublishedSiteSettings::class)->forCompany($company)['localization']['default_currency']
            ?? $company?->base_currency
            ?? 'EUR');
    }

    public function convert(float $amount, string $from = 'EUR', ?string $to = null): float
    {
        $to ??= $this->currency();
        if ($from === $to) return $amount;
        $rate = ExchangeRate::where('base_currency', $from)->where('quote_currency', $to)->latest('rate_date')->value('rate');
        return $rate ? round($amount * (float) $rate, 2) : $amount;
    }
}
