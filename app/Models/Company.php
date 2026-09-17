<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Company extends Model
{
    protected $guarded = [];
    protected $casts = ['settings' => 'array', 'active' => 'boolean'];

    protected static function booted(): void
    {
        static::created(function (self $company): void {
            if (! Schema::hasTable('languages') || ! Schema::hasTable('currencies')) return;

            $languagePivotHasEnabled = Schema::hasColumn('company_languages', 'enabled_storefront');
            $languages = Language::query()->where('active', true)->pluck('locale')->mapWithKeys(function ($locale) use ($company, $languagePivotHasEnabled): array {
                $pivot = ['is_default' => (string) $locale === (string) $company->default_locale];
                if ($languagePivotHasEnabled) $pivot['enabled_storefront'] = (string) $locale === (string) $company->default_locale;
                return [(string) $locale => $pivot];
            })->all();
            if ($languages) $company->languages()->syncWithoutDetaching($languages);

            $currencies = Currency::query()->where('active', true)->pluck('code')->mapWithKeys(
                fn ($code) => [(string) $code => [
                    'is_base' => (string) $code === (string) $company->base_currency,
                    'enabled_storefront' => (string) $code === (string) $company->base_currency,
                ]]
            )->all();
            if ($currencies) $company->currencies()->syncWithoutDetaching($currencies);
        });
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot(['role', 'is_default']);
    }

    public function languages()
    {
        return $this->belongsToMany(Language::class, 'company_languages', 'company_id', 'locale', 'id', 'locale')
            ->withPivot(['is_default', 'enabled_storefront']);
    }

    public function currencies()
    {
        return $this->belongsToMany(Currency::class, 'company_currencies', 'company_id', 'currency_code', 'id', 'code')
            ->withPivot(['is_base', 'enabled_storefront']);
    }
}
