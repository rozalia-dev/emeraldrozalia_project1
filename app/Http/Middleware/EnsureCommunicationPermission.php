<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCommunicationPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($user->is_admin) {
            return $next($request);
        }

        abort_unless(app(TenantContext::class)->company($user), 403);

        [$base, $action] = $this->target($request);
        $permissions = [];

        foreach ([$base, 'communication', 'communications'] as $prefix) {
            $permissions[] = $prefix.'.'.$action;

            if ($action === 'edit') {
                $permissions[] = $prefix.'.update';
            }

            if ($action === 'approve') {
                $permissions[] = $prefix.'.edit';
                $permissions[] = $prefix.'.update';
            }
        }

        abort_unless($user->hasAnyPermission(array_values(array_unique($permissions))), 403);

        return $next($request);
    }

    private function target(Request $request): array
    {
        $route = $request->route();
        $name = (string) ($route?->getName() ?? '');

        if (str_contains($name, '.export')) {
            return ['communication.center', 'export'];
        }

        $method = strtoupper($request->method());
        if (in_array($method, ['GET', 'HEAD'], true)) {
            return ['communication.center', 'view'];
        }

        if ($method === 'DELETE') {
            return ['communication.center', 'delete'];
        }

        if (in_array($method, ['PATCH', 'PUT'], true)) {
            return ['communication.center', 'edit'];
        }

        if (str_contains($name, '.action') || str_contains($name, '.restore')) {
            return ['communication.center', 'edit'];
        }

        return ['communication.center', 'create'];
    }
}
