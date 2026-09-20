<?php

namespace App\Http\Controllers;

use App\Http\Requests\CollectionFilterRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Services\PublicMediaResolver;
use Illuminate\View\View;

final class PublicCollectionController extends Controller
{
    public function show(CollectionFilterRequest $request, ProductCollection $collection): View
    {
        abort_unless($collection->status === 'active' && $collection->visibility === 'visible', 404);

        $collection->load('media');

        $isNewArrivals = $collection->slug === 'new-arrivals';
        $query = ($isNewArrivals
            ? Product::query()->where('products.is_new', true)
                : $collection->products())
            ->published()
            ->with(['category', 'media', 'variants.approvedMedia'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $needle = '%'.strtolower($search).'%';
            $query->where(function ($productQuery) use ($needle): void {
                $productQuery->whereRaw('LOWER(products.name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(products.sku) LIKE ?', [$needle]);
            });
        }

        $rawCategories = $request->input('category', []);
        if (is_string($rawCategories)) {
            $rawCategories = [$rawCategories];
        }
        $selectedCategories = array_values(array_unique(array_filter(
            (array) $rawCategories,
            fn ($value): bool => is_string($value) && trim($value) !== ''
        )));
        if ($selectedCategories) {
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->whereIn('slug', $selectedCategories));
        }

        $sort = in_array($request->input('sort'), ['featured', 'newest', 'price_low', 'price_high', 'name'], true)
            ? (string) $request->input('sort')
            : 'featured';

        match ($sort) {
            'newest' => $query->latest('products.created_at'),
            'price_low' => $query->orderBy('products.price')->orderBy('products.name'),
            'price_high' => $query->orderByDesc('products.price')->orderBy('products.name'),
            'name' => $query->orderBy('products.name'),
            default => $isNewArrivals
                ? $query->latest('products.created_at')
                : $query->orderBy('collection_product.sort_order')->orderBy('products.name'),
        };

        $products = $query->paginate(12)->withQueryString();

        $categories = Category::query()
            ->websiteVisible()
            ->whereHas('products', function ($productQuery) use ($collection, $isNewArrivals): void {
                $productQuery->published();
                if ($isNewArrivals) {
                    $productQuery->where('products.is_new', true);
                } else {
                    $productQuery->whereHas('collections', fn ($collectionQuery) => $collectionQuery->whereKey($collection->getKey()));
                }
            })
            ->withCount(['products' => function ($productQuery) use ($collection, $isNewArrivals): void {
                $productQuery->published();
                if ($isNewArrivals) {
                    $productQuery->where('products.is_new', true);
                } else {
                    $productQuery->whereHas('collections', fn ($collectionQuery) => $collectionQuery->whereKey($collection->getKey()));
                }
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $collectionMedia = $collection->media && $collection->media->isApprovedPublic()
            ? app(PublicMediaResolver::class)->describe($collection->media, $collection->name)
            : null;

        return view('site.collection', compact(
            'collection',
            'collectionMedia',
            'products',
            'categories',
            'selectedCategories',
            'sort'
        ));
    }
}
