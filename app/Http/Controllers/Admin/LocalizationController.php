<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Currency, ExchangeRate, Language};
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocalizationController extends Controller
{
    public function index(TenantContext $context): View
    {
        $company = $context->company();
        $base = strtoupper((string) ($company?->base_currency ?: 'EUR'));
        $rates = ExchangeRate::query()->where('base_currency',$base)->orderByDesc('rate_date')->get()->unique('quote_currency')->keyBy('quote_currency');
        return view('admin.settings.localization-manager', [
            'company'=>$company,'baseCurrency'=>$base,'languages'=>Language::query()->orderBy('name')->get(),
            'currencies'=>Currency::query()->orderBy('code')->get(),'rates'=>$rates,
        ]);
    }

    public function language(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'locale'=>['required','string','max:10','regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z]{2,4})?$/'],
            'name'=>['required','string','max:100'],'native_name'=>['required','string','max:100'],'active'=>['nullable','boolean'],
        ]);
        $language = Language::query()->updateOrCreate(['locale'=>strtolower($data['locale'])],[
            'name'=>$data['name'],'native_name'=>$data['native_name'],'active'=>$request->boolean('active'),
        ]);
        if ($company = $context->company()) $company->languages()->syncWithoutDetaching([$language->locale=>['is_default'=>$company->default_locale===$language->locale]]);
        return back()->with('success','Language settings updated.');
    }

    public function currency(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'code'=>['required','string','size:3','regex:/^[A-Za-z]{3}$/'],'name'=>['required','string','max:100'],
            'symbol'=>['required','string','max:8'],'decimals'=>['required','integer','min:0','max:4'],'active'=>['nullable','boolean'],
            'enabled_storefront'=>['nullable','boolean'],
        ]);
        $code = strtoupper($data['code']);
        $currency = Currency::query()->updateOrCreate(['code'=>$code],[
            'name'=>$data['name'],'symbol'=>$data['symbol'],'decimals'=>$data['decimals'],'active'=>$request->boolean('active'),
        ]);
        if ($company = $context->company()) $company->currencies()->syncWithoutDetaching([$currency->code=>[
            'is_base'=>$company->base_currency===$currency->code,'enabled_storefront'=>$request->boolean('enabled_storefront'),
        ]]);
        return back()->with('success','Currency settings updated.');
    }

    public function rate(Request $request, TenantContext $context): RedirectResponse
    {
        $company = $context->company();
        $base = strtoupper((string) ($company?->base_currency ?: 'EUR'));
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
}
