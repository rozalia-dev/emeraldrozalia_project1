<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AttachRequestCorrelation
{
    public function handle(Request $request, Closure $next): Response
    {
        $candidate = $request->header('X-Correlation-ID');
        $correlationId = is_string($candidate) && Str::isUuid($candidate)
            ? $candidate
            : (string) Str::uuid();

        $request->attributes->set('correlation_id', $correlationId);

        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
