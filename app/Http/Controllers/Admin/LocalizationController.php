<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Category, Currency, ExchangeRate, Language, Product, StorefrontTranslation};
use App\Services\{EcbExchangeRateService, StorefrontTranslator, TenantContext};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class LocalizationController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $company = $context->company();
        $base = $context->baseCurrency();
        $languagePivots = $company?->languages()->get()->keyBy('locale') ?? collect();
        $currencyPivots = $company?->currencies()->get()->keyBy('code') ?? collect();
        $languages = Language::query()->orderBy('sort_order')->orderBy('name')->get();
        $currencies = Currency::query()->orderBy('sort_order')->orderBy('code')->get();
        $selectedLocale = (string) $request->query('locale', $languages->firstWhere('locale', 'fr')?->locale ?? $context->defaultLocale());
        if (! $languages->contains('locale', $selectedLocale)) $selectedLocale = $context->defaultLocale();

        $rates = ExchangeRate::query()
            ->where('base_currency', $base)
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->get()
            ->unique('quote_currency')
            ->keyBy('quote_currency');

        $translations = StorefrontTranslation::withoutGlobalScopes()
            ->where('locale', $selectedLocale)
            ->where(function ($query) use ($company): void {
                $query->whereNull('company_id');
                if ($company) $query->orWhere('company_id', $company->id);
            })
            ->orderBy('namespace')
            ->orderBy('source_text')
            ->get();

        return view('admin.settings.localization-manager', [
            'company'=>$company,
            'baseCurrency'=>$base,
            'languages'=>$languages,
            'currencies'=>$currencies,
            'rates'=>$rates,
            'languagePivots'=>$languagePivots,
            'currencyPivots'=>$currencyPivots,
            'selectedLocale'=>$selectedLocale,
            'translations'=>$translations,
            'categories'=>Category::query()->orderBy('sort_order')->orderBy('name')->limit(100)->get(),
            'products'=>Product::query()->orderBy('name')->limit(150)->get(),
        ]);
    }

    public function language(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'locale'=>['required','string','max:10','regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?$/'],
            'name'=>['required','string','max:100'],
            'native_name'=>['required','string','max:100'],
            'direction'=>['required',Rule::in(['ltr','rtl'])],
            'fallback_locale'=>['nullable','string','max:10',Rule::exists('languages','locale')],
            'active'=>['nullable','boolean'],
            'enabled_storefront'=>['nullable','boolean'],
            'is_default'=>['nullable','boolean'],
        ]);

        $locale = str_replace('_', '-', trim($data['locale']));
        $language = Language::query()->updateOrCreate(['locale'=>$locale], [
            'name'=>$data['name'],
            'native_name'=>$data['native_name'],
            'direction'=>$data['direction'],
            'fallback_locale'=>($data['fallback_locale'] ?? null) === $locale ? null : ($data['fallback_locale'] ?? null),
            'active'=>$request->boolean('active'),
        ]);

        if ($company = $context->company()) {
            $makeDefault = $request->boolean('is_default');
            if ($makeDefault) {
                foreach ($company->languages()->get() as $existing) {
                    $company->languages()->updateExistingPivot($existing->locale, ['is_default'=>false]);
                }
                $company->forceFill(['default_locale'=>$language->locale])->save();
            }
            $company->languages()->syncWithoutDetaching([$language->locale=>[
                'is_default'=>$makeDefault || $company->default_locale === $language->locale,
                'enabled_storefront'=>$makeDefault || $request->boolean('enabled_storefront'),
            ]]);
        }

        return back()->with('success','Language settings updated.');
    }

    public function currency(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'code'=>['required','string','size:3','regex:/^[A-Za-z]{3}$/'],
            'name'=>['required','string','max:100'],
            'symbol'=>['required','string','max:8'],
            'decimals'=>['required','integer','min:0','max:4'],
            'symbol_position'=>['required',Rule::in(['before','after'])],
            'active'=>['nullable','boolean'],
            'enabled_storefront'=>['nullable','boolean'],
            'is_base'=>['nullable','boolean'],
        ]);
        $code = strtoupper($data['code']);
        $currency = Currency::query()->updateOrCreate(['code'=>$code], [
            'name'=>$data['name'],
            'symbol'=>$data['symbol'],
            'decimals'=>$data['decimals'],
            'symbol_position'=>$data['symbol_position'],
            'active'=>$request->boolean('active'),
        ]);

        if ($company = $context->company()) {
            $makeBase = $request->boolean('is_base');
            if ($makeBase) {
                foreach ($company->currencies()->get() as $existing) $company->currencies()->updateExistingPivot($existing->code, ['is_base'=>false]);
                $company->forceFill(['base_currency'=>$currency->code])->save();
            }
            $company->currencies()->syncWithoutDetaching([$currency->code=>[
                'is_base'=>$makeBase || $company->base_currency === $currency->code,
                'enabled_storefront'=>$makeBase || $request->boolean('enabled_storefront'),
            ]]);
        }

        return back()->with('success','Currency settings updated.');
    }

    public function rate(Request $request, TenantContext $context): RedirectResponse
    {
        $base = $context->baseCurrency();
        $data = $request->validate([
            'quote_currency'=>['required','string','size:3',Rule::exists('currencies','code')],
            'rate'=>['required','numeric','gt:0','max:1000000'],
        ]);
        $quote = strtoupper($data['quote_currency']);
        if ($quote === $base) return back()->withErrors(['quote_currency'=>'Choose a currency different from the base currency.']);

        ExchangeRate::query()->updateOrCreate(
            ['base_currency'=>$base,'quote_currency'=>$quote,'rate_date'=>today()->toDateString()],
            ['rate'=>$data['rate'],'source'=>'manual-cpanel']
        );

        return back()->with('success',"{$base}/{$quote} exchange rate updated.");
    }

    public function syncRates(EcbExchangeRateService $rates): RedirectResponse
    {
        try {
            $saved = $rates->sync();
            return back()->with('success', count($saved).' ECB exchange rates synchronized.');
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['rates'=>$exception->getMessage()]);
        }
    }

    public function translation(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'locale'=>['required','string','max:10',Rule::exists('languages','locale')],
            'translation_key'=>['required','string','max:180'],
            'namespace'=>['required','string','max:50'],
            'source_text'=>['required','string','max:1000'],
            'translation'=>['required','string','max:4000'],
            'active'=>['nullable','boolean'],
        ]);
        $company = $context->company();
        StorefrontTranslation::withoutGlobalScopes()->updateOrCreate([
            'company_id'=>$company?->id,
            'locale'=>$data['locale'],
            'translation_key'=>$data['translation_key'],
        ], [
            'namespace'=>$data['namespace'],
            'source_text'=>$data['source_text'],
            'source_hash'=>sha1(StorefrontTranslator::normalize($data['source_text'])),
            'translation'=>$data['translation'],
            'active'=>$request->boolean('active', true),
        ]);

        return back()->with('success','Storefront translation saved.');
    }

    public function contentTranslation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entity_type'=>['required',Rule::in(['category','product'])],
            'entity_id'=>['required','integer','min:1'],
            'locale'=>['required','string','max:10',Rule::exists('languages','locale')],
            'name'=>['nullable','string','max:255'],
            'description'=>['nullable','string','max:10000'],
        ]);

        $model = $data['entity_type'] === 'category'
            ? Category::query()->findOrFail($data['entity_id'])
            : Product::query()->findOrFail($data['entity_id']);
        $translations = is_array($model->translations) ? $model->translations : [];
        $translations[$data['locale']] = [
            'name'=>trim((string) ($data['name'] ?? '')) ?: null,
            'description'=>trim((string) ($data['description'] ?? '')) ?: null,
        ];
        $model->forceFill(['translations'=>$translations])->save();

        return back()->with('success', ucfirst($data['entity_type']).' translation saved.');
    }
}
