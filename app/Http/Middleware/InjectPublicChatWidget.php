<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InjectPublicChatWidget
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('admin*', 'api*', 'chat-24-7*') || $request->expectsJson() || ! method_exists($response, 'getContent')) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($response->getStatusCode() !== 200 || ! str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        $content = (string) $response->getContent();
        if ($content === '' || ! str_contains($content, '</body>') || str_contains($content, 'data-chat-24-7-widget')) {
            return $response;
        }

        $routeProduct = $request->route('product');
        $contextProductSlug = is_object($routeProduct) && isset($routeProduct->slug)
            ? (string) $routeProduct->slug
            : (is_string($routeProduct) ? $routeProduct : '');

        $widget = view('site.partials.chat-24-7', [
            'contextProductSlug' => $contextProductSlug,
        ])->render();

        $response->setContent(str_replace('</body>', $widget."\n</body>", $content));

        return $response;
    }
}
