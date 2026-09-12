<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BannerIndexRequest;
use App\Http\Requests\Api\V1\ProductIndexRequest;
use App\Http\Resources\Api\V1\BannerResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\{Banner,Product};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function products(ProductIndexRequest $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->with(['category', 'media'])
            ->where('is_active', true)
            ->whereIn('status', ['active', 'published']);

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $needle = '%'.strtolower($search).'%';
            $query->where(fn ($products) => $products
                ->whereRaw('LOWER(name) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(sku) LIKE ?', [$needle]));
        }

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($category) => $category->where('slug', $request->input('category')));
        }
        if ($request->filled('min_price')) $query->where('price', '>=', (float) $request->input('min_price'));
        if ($request->filled('max_price')) $query->where('price', '<=', (float) $request->input('max_price'));

        match ($request->input('sort', 'newest')) {
            'price_low' => $query->orderBy('price')->orderBy('name'),
            'price_high' => $query->orderByDesc('price')->orderBy('name'),
            'name' => $query->orderBy('name')->orderBy('id'),
            default => $query->orderByDesc('is_new')->latest('id'),
        };

        $perPage = min(48, max(1, (int) $request->input('per_page', 12)));

        return ProductResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function product(Product $product): ProductResource
    {
        abort_unless($product->is_active && in_array($product->status, ['active', 'published'], true), 404);

        $product->load([
            'category',
            'media',
            'variants' => fn ($variants) => $variants->where('is_active', true)->orderBy('sort_order')->orderBy('id'),
        ]);

        return new ProductResource($product);
    }

    public function banners(BannerIndexRequest $request): AnonymousResourceCollection
    {
        $query = Banner::query()->publishedFor($request->input('position'))
            ->when($request->filled('device'), fn ($banners) => $banners->whereJsonContains('device_visibility', $request->input('device')))
            ->orderByDesc('priority')
            ->orderBy('id');

        $perPage = min(48, max(1, (int) $request->input('per_page', 12)));

        return BannerResource::collection($query->paginate($perPage)->withQueryString());
    }
}
