<?php

namespace App\Http\Middleware;

use App\Models\SeoRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySeoRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $path = '/'.ltrim($request->getPathInfo(), '/');
        $redirect = SeoRedirect::query()
            ->where('from_path', $path)
            ->where('active', true)
            ->first();

        $targetPath = $redirect ? parse_url($redirect->to_path, PHP_URL_PATH) : null;
        $targetPath = is_string($targetPath) ? '/'.ltrim($targetPath, '/') : null;

        if (! $redirect || $targetPath === $path) {
            return $next($request);
        }

        return redirect()->to($redirect->to_path, (int) $redirect->status_code);
    }
}
