<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureFranchisePermission
{
    private const SECTION_BASES = [
        'franchise-dashboard' => 'dashboard.system',
        'franchise-applications' => 'applications.leads',
        'franchise-territories' => 'territories',
        'franchise-agreements' => 'agreements',
        'franchisees' => 'franchisees',
        'franchise-retail-stores' => 'franchise.retail.stores',
        'store-setup' => 'franchise.retail.stores',
        'training-documents' => 'training.documents',
        'marketing-assets' => 'marketing.assets',
        'performance-targets' => 'performance.targets',
        'renewals' => 'renewals',
        'franchise-reports' => 'reports',
        'data-management' => 'data.management',
    ];

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

        foreach ([$base, 'franchise', 'franchise.management'] as $prefix) {
            $permissions[] = $prefix.'.'.$action;

            if ($action === 'edit') {
                $permissions[] = $prefix.'.update';
            }
        }

        if ($action === 'edit') {
            $permissions[] = $base.'.create';
        }

        abort_unless($user->hasAnyPermission(array_values(array_unique($permissions))), 403);

        return $next($request);
    }

    private function target(Request $request): array
    {
        $route = $request->route();
        $name = (string) ($route?->getName() ?? '');
        $section = (string) ($route?->parameter('section') ?? '');

        $base = self::SECTION_BASES[$section] ?? null;
        if ($base === null && str_contains($name, 'territor')) {
            $base = 'territories';
        } elseif ($base === null && str_contains($name, 'application')) {
            $base = 'applications.leads';
        } elseif ($base === null && (str_contains($name, 'store-setup') || str_contains($name, 'store.action'))) {
            $base = 'franchise.retail.stores';
        } elseif ($base === null && str_contains($name, 'dashboard')) {
            $base = 'dashboard.system';
        }

        $base ??= 'franchise';

        if (str_contains($name, '.export')) {
            return [$base, 'export'];
        }

        $method = strtoupper($request->method());
        if (in_array($method, ['GET', 'HEAD'], true)) {
            return [$base, 'view'];
        }

        if ($method === 'DELETE') {
            return [$base, 'delete'];
        }

        if (in_array($method, ['PATCH', 'PUT'], true)) {
            return [$base, 'edit'];
        }

        if (str_contains($name, '.action') || str_contains($name, '.restore')) {
            return [$base, 'edit'];
        }

        return [$base, 'create'];
    }
}
