<?php

namespace App\Http\Middleware;

use App\Services\{StorefrontTranslator, TenantContext};
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ResolveTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $ctx = app(TenantContext::class);
        $company = $ctx->company($request->user());

        if ($company) {
            if ((int) session('company_id') !== (int) $company->id) session(['company_id' => $company->id]);
        } elseif ($request->user() && ! $request->user()->is_admin) {
            session()->forget('company_id');
        }

        $locale = $ctx->locale();
        App::setLocale($locale);
        $language = $ctx->languageModel($locale);

        view()->share([
            'tenantCompany' => $ctx->company(),
            'activeLocale' => $locale,
            'activeCurrency' => $ctx->currency(),
            'storefrontLanguages' => $ctx->availableLanguages(),
            'storefrontCurrencies' => $ctx->availableCurrencies(),
            'storefrontDirection' => $language?->isRtl() ? 'rtl' : 'ltr',
            'storefrontTranslate' => fn (string $source, ?string $key = null): string => app(StorefrontTranslator::class)->translate($source, $key, $locale),
            'storefrontMoney' => fn (int|float|string $amount, ?string $from = null, ?string $to = null): string => $ctx->formatMoney($amount, $from, $to),
        ]);

        return $next($request);
    }
}
