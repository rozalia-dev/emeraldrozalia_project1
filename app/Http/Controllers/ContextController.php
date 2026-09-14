<?php

namespace App\Http\Controllers;

use App\Http\Requests\{CompanyContextRequest, CurrencyContextRequest, LocaleContextRequest};

class ContextController extends Controller
{
    public function company(CompanyContextRequest $request)
    {
        $id = (int) $request->validated()['company_id'];
        if (! $request->user()->is_admin && ! $request->user()->companies()->whereKey($id)->exists()) {
            abort(403);
        }

        session(['company_id' => $id]);

        return back();
    }

    public function locale(LocaleContextRequest $request)
    {
        session(['locale' => $request->validated()['locale']]);

        return back();
    }

    public function currency(CurrencyContextRequest $request)
    {
        session(['currency' => $request->validated()['currency']]);

        return back();
    }
}
