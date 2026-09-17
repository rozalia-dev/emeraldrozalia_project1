<?php

namespace App\Http\Controllers;

use App\Http\Requests\{CompanyContextRequest, CurrencyContextRequest, LocaleContextRequest};
use App\Services\TenantContext;
use Illuminate\Validation\ValidationException;

class ContextController extends Controller
{
    public function company(CompanyContextRequest $request)
    {
        $id = (int) $request->validated()['company_id'];
        if (! $request->user()->is_admin && ! $request->user()->companies()->whereKey($id)->exists()) abort(403);

        session(['company_id' => $id]);
        session()->forget(['locale', 'currency']);

        return back();
    }

    public function locale(LocaleContextRequest $request, TenantContext $context)
    {
        $locale = (string) $request->validated()['locale'];
        if (! $context->availableLanguages()->contains(fn ($language) => (string) $language->locale === $locale)) {
            throw ValidationException::withMessages(['locale' => 'That language is not enabled for this storefront.']);
        }

        session(['locale' => $locale]);

        return back();
    }

    public function currency(CurrencyContextRequest $request, TenantContext $context)
    {
        $currency = strtoupper((string) $request->validated()['currency']);
        if (! $context->availableCurrencies()->contains(fn ($item) => strtoupper((string) $item->code) === $currency)) {
            throw ValidationException::withMessages(['currency' => 'That currency is not enabled for this storefront.']);
        }

        $base = $context->baseCurrency();
        if ($currency !== $base && $context->exchangeRate($base, $currency) === null) {
            throw ValidationException::withMessages(['currency' => "{$currency} does not have an exchange rate from {$base} yet."]);
        }

        session(['currency' => $currency]);

        return back();
    }
}
