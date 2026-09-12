<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\TenantContext;
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
            if ((int) session('company_id') !== (int) $company->id) {
                session(['company_id' => $company->id]);
            }
        } elseif ($request->user() && ! $request->user()->is_admin) {
            session()->forget('company_id');
        }

        App::setLocale($ctx->locale());
        view()->share('tenantCompany', $ctx->company());
        view()->share('activeLocale', $ctx->locale());
        view()->share('activeCurrency', $ctx->currency());

        return $next($request);
    }
}
