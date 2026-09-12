<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_admin && ! app(TenantContext::class)->company($user)) {
            abort(403);
        }

        abort_unless($user && collect($permissions)->contains(
            static fn (string $permission): bool => $user->hasPermission($permission),
        ), 403);

        return $next($request);
    }
}
