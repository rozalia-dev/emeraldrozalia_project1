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
        if (!session()->has('company_id')) {
            if ($company = Company::where('active', true)->first()) {
                session(['company_id' => $company->id]);
            }
        }

        App::setLocale($ctx->locale());
        view()->share('tenantCompany', $ctx->company());
        view()->share('activeLocale', $ctx->locale());
        view()->share('activeCurrency', $ctx->currency());

        if ($request->isMethod('get') && $request->is('admin/resource/online-sales')) {
            return redirect()->route('admin.order-master.overview');
        }

        return $next($request);
    }
}
