<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectLegacyShopFilters
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || ! $request->is('shop')) {
            return $next($request);
        }

        $query = $request->query();

        if (count($query) === 1
            && array_key_exists('page', $query)
            && trim((string) $query['page']) === '1') {
            return redirect()->to(route('shop'), 301);
        }

        $legacy = array_key_exists('organization', $query)
            || array_key_exists('product_type', $query)
            || $this->containsNumericLegacyValue($query['category'] ?? null)
            || $this->containsNumericLegacyValue($query['country'] ?? null)
            || $this->containsNumericLegacyValue($query['club'] ?? null);

        if ($legacy) {
            return redirect()->to(route('shop'), 301);
        }

        return $next($request);
    }

    private function containsNumericLegacyValue(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->containsNumericLegacyValue($item)) {
                    return true;
                }
            }

            return false;
        }

        return is_scalar($value)
            && preg_match('/^\d+$/', trim((string) $value)) === 1;
    }
}
