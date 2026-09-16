<?php

namespace App\Http\Middleware;

use App\Models\Product;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InjectProductMoqField
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET')
            || ! ($request->routeIs('admin.add-product') || $request->routeIs('admin.product.edit'))
            || ! method_exists($response, 'getContent')) {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        if ($response->getStatusCode() !== 200 || ! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $content = (string) $response->getContent();
        if ($content === '' || str_contains($content, 'name="minimum_order_quantity"')) {
            return $response;
        }

        $product = $request->route('product');
        $saved = $product instanceof Product
            ? data_get($product->product_metadata, 'minimum_order_quantity')
            : null;
        $value = old('minimum_order_quantity', $saved);
        $value = is_numeric($value) && (int) $value > 0 ? (string) (int) $value : '';

        $field = '<label class="ap-field"><span>Minimum Order Quantity (MOQ)</span>'
            .'<input type="number" name="minimum_order_quantity" value="'.e($value).'" min="1" step="1" placeholder="e.g. 100">'
            .'<small class="ap-field-help">Used by Chat 24/7 and bulk/corporate enquiries. Leave blank when MOQ requires manual confirmation.</small>'
            .'</label>';

        $pattern = '/(<label class="ap-field"><span>Stock Quantity.*?<\/label>)/s';
        $updated = preg_replace($pattern, '$1'.$field, $content, 1, $count);
        if ($count === 1 && is_string($updated)) {
            $response->setContent($updated);
        }

        return $response;
    }
}
