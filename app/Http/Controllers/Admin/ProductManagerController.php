<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use App\Models\CatalogCounty;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\Review;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductManagerController extends Controller
{
    public function index(Request $request): View
    {
        $trashCount = (int) Product::onlyTrashed()->count();
        $tabs = [
            'all' => 'All Products',
            'published' => 'Published',
            'draft' => 'Draft',
            'hidden' => 'Hidden',
            'out_of_stock' => 'Out of Stock',
            'low_stock' => 'Low Stock',
            'featured' => 'Featured',
            'top_rated' => 'Top Rated',
            'trash' => 'Trash'.($trashCount > 0 ? ' ('.$trashCount.')' : ''),
        ];

        $tab = (string) $request->query('tab', 'all');
        if (! array_key_exists($tab, $tabs)) {
            $tab = 'all';
        }

        $query = ($tab === 'trash' ? Product::onlyTrashed() : Product::query())
            ->with(['category', 'previewMedia'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        if ($tab !== 'trash') {
            switch ($tab) {
                case 'published':
                    $query->where('is_active', true)->whereIn('status', ['active', 'published']);
                    break;
                case 'draft':
                    $query->whereIn('status', ['draft', 'planned']);
                    break;
                case 'hidden':
                    $query->where('is_active', false)->whereNotIn('status', ['draft', 'planned']);
                    break;
                case 'out_of_stock':
                    $query->where('stock', '<=', 0);
                    break;
                case 'low_stock':
                    $query->whereBetween('stock', [1, 10]);
                    break;
                case 'featured':
                    $query->where('is_new', true);
                    break;
                case 'top_rated':
                    $query->whereHas('reviews', fn ($reviews) => $reviews->where('rating', '>=', 4));
                    break;
            }
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function ($products) use ($needle): void {
                $products
                    ->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(brand, \'\')) LIKE ?', [$needle]);
            });
        }

        $allCategories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name']);

        $childrenByParent = $allCategories->groupBy(fn (Category $category) => (int) ($category->parent_id ?? 0));
        $descendantIds = function (int $rootId) use (&$descendantIds, $childrenByParent): array {
            $ids = [$rootId];
            foreach ($childrenByParent->get($rootId, collect()) as $child) {
                $ids = array_merge($ids, $descendantIds((int) $child->id));
            }
            return array_values(array_unique($ids));
        };

        $rootCategories = $allCategories
            ->filter(fn (Category $category) => ! $category->parent_id)
            ->values();

        $categoryRootMap = [];
        foreach ($rootCategories as $rootCategory) {
            foreach ($descendantIds((int) $rootCategory->id) as $descendantId) {
                $categoryRootMap[$descendantId] = [
                    'id' => (int) $rootCategory->id,
                    'name' => $rootCategory->name,
                ];
            }
        }

        $rootCategoryId = (int) $request->query('root_category_id', 0);
        if ($rootCategoryId > 0 && $rootCategories->contains('id', $rootCategoryId)) {
            $query->whereIn('category_id', $descendantIds($rootCategoryId));
        }

        $categoryId = (int) $request->query('category_id', 0);
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        $countryId = (int) $request->query('catalog_country_id', 0);
        if ($countryId > 0) {
            $query->where('product_metadata->catalog_classification->catalog_country_id', $countryId);
        }

        $countyCode = strtoupper(trim((string) $request->query('catalog_county_code', '')));
        if ($countyCode !== '') {
            $query->where('product_metadata->catalog_classification->catalog_county_code', $countyCode);
        }

        $clubId = (int) $request->query('catalog_club_id', 0);
        if ($clubId > 0) {
            $query->where('product_metadata->catalog_classification->catalog_club_id', $clubId);
        }

        $collectionId = (int) $request->query('collection_id', 0);
        if ($collectionId > 0) {
            $query->whereHas('collections', fn ($collections) => $collections->where('product_collections.id', $collectionId));
        }

        $minPrice = $request->query('min_price');
        if (is_numeric($minPrice)) {
            $query->where('price', '>=', (float) $minPrice);
        }

        $maxPrice = $request->query('max_price');
        if (is_numeric($maxPrice)) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        switch ((string) $request->query('stock_status', '')) {
            case 'in_stock':
                $query->where('stock', '>', 0);
                break;
            case 'low_stock':
                $query->whereBetween('stock', [1, 10]);
                break;
            case 'out_of_stock':
                $query->where('stock', '<=', 0);
                break;
        }

        switch ((string) $request->query('product_status', '')) {
            case 'published':
                $query->where('is_active', true)->whereIn('status', ['active', 'published']);
                break;
            case 'draft':
                $query->whereIn('status', ['draft', 'planned']);
                break;
            case 'hidden':
                $query->where('is_active', false);
                break;
            case 'inactive':
                $query->where('is_active', false)->where('status', 'inactive');
                break;
        }

        $rating = (string) $request->query('rating', '');
        if (preg_match('/^[1-5]_plus$/', $rating)) {
            $query->whereHas('reviews', fn ($reviews) => $reviews->where('rating', '>=', (int) $rating[0]));
        }

        $featured = $request->boolean('featured');
        if ($featured) {
            $query->where('is_new', true);
        }

        $products = $query->orderBy('name')->orderBy('id')->paginate(10)->withQueryString();

        $totalProducts = (int) Product::query()->count();
        $draftCount = (int) Product::query()->whereIn('status', ['draft', 'planned'])->count();
        $hiddenCount = (int) Product::query()->where('is_active', false)->whereNotIn('status', ['draft', 'planned'])->count();
        $stats = [
            'total' => $totalProducts,
            'published' => (int) Product::query()->where('is_active', true)->whereIn('status', ['active', 'published'])->count(),
            'hidden_draft' => $draftCount + $hiddenCount,
            'draft' => $draftCount,
            'hidden' => $hiddenCount,
            'out_of_stock' => (int) Product::query()->where('stock', '<=', 0)->count(),
            'total_value' => (float) (Product::query()->selectRaw('COALESCE(SUM(price * stock), 0) AS aggregate')->value('aggregate') ?? 0),
            'average_rating' => (float) (Review::query()->approved()->whereHas('product')->avg('rating') ?? 0),
            'trash' => $trashCount,
        ];

        $categories = $allCategories;

        $categoryProductCounts = Product::query()
            ->selectRaw('category_id, COUNT(*) AS aggregate')
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $rootCategorySummaries = $rootCategories
            ->map(function (Category $rootCategory) use ($descendantIds, $categoryProductCounts): array {
                $ids = $descendantIds((int) $rootCategory->id);
                $count = collect($ids)->sum(fn (int $id): int => (int) ($categoryProductCounts[$id] ?? 0));

                return [
                    'id' => (int) $rootCategory->id,
                    'name' => $rootCategory->name,
                    'count' => $count,
                ];
            })
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->sortBy(fn (array $row): string => strtolower($row['name']))
            ->values();

        $catalogCountries = CatalogCountry::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $catalogCounties = CatalogCounty::query()
            ->active()
            ->orderBy('catalog_country_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['catalog_country_id', 'code', 'name']);

        $catalogClubs = CatalogClub::query()
            ->active()
            ->with('organizations:catalog_club_id,taxonomy_type')
            ->orderBy('catalog_country_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'catalog_country_id', 'catalog_county_code', 'governing_body', 'name']);

        $collections = ProductCollection::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'main_category_id']);

        return view('admin.product-manager.index', compact(
            'products',
            'categories',
            'stats',
            'tabs',
            'tab',
            'search',
            'categoryId',
            'rootCategoryId',
            'rootCategorySummaries',
            'categoryRootMap',
            'minPrice',
            'maxPrice',
            'rating',
            'featured',
            'countryId',
            'countyCode',
            'clubId',
            'collectionId',
            'catalogCountries',
            'catalogCounties',
            'catalogClubs',
            'collections',
        ));
    }

    public function printView(Request $request): View
    {
        $products = $this->exportQuery($request)
            ->with('category')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(1000)
            ->get();

        return view('admin.product-manager.print', [
            'products' => $products,
            'filters' => $request->query(),
            'generatedAt' => now(),
        ]);
    }

    public function download(Request $request)
    {
        $filename = 'products-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['Product ID', 'Name', 'SKU', 'Category', 'Price', 'Stock', 'Status', 'Published']);

            $this->exportQuery($request)
                ->with('category')
                ->orderBy('id')
                ->chunkById(500, function ($products) use ($stream): void {
                    foreach ($products as $product) {
                        fputcsv($stream, [
                            $product->id,
                            $product->name,
                            $product->sku,
                            $product->category?->name,
                            $product->price,
                            $product->stock,
                            $product->status,
                            $product->isPubliclyPublished() ? 'Yes' : 'No',
                        ]);
                    }
                });

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function bulkPublish(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'products' => ['required', 'array', 'min:1', 'max:500'],
            'products.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', 'in:approve,publish,unpublish'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['products'])));
        $records = Product::query()->whereKey($ids)->orderBy('id')->get();
        abort_unless($records->count() === count($ids), 404);

        $action = $validated['action'];
        foreach ($records as $record) {
            abort_unless($request->user()?->can('update', $record), 403);

            $before = $record->toArray();
            $metadata = is_array($record->product_metadata) ? $record->product_metadata : [];
            if ($action === 'approve') {
                $metadata['approval_status'] = 'approved';
                $metadata['approved_at'] = now()->toISOString();
                $metadata['approved_by'] = $request->user()?->id;
                $record->forceFill(['product_metadata' => $metadata])->save();
                AuditTrail::record('product.approved', $record, $before, $record->fresh()->toArray());
                continue;
            }

            $published = $action === 'publish';
            $metadata['published_website'] = $published;
            if ($published) {
                $metadata['approval_status'] = 'approved';
                $metadata['approved_at'] ??= now()->toISOString();
                $metadata['approved_by'] ??= $request->user()?->id;
                $metadata['available_for_sale'] = true;
            }

            $record->forceFill([
                'is_active' => $published,
                'status' => $published ? 'active' : ($record->status === 'draft' ? 'draft' : 'active'),
                'published_at' => $published ? ($record->published_at ?: now()) : null,
                'product_metadata' => $metadata,
            ])->save();

            AuditTrail::record(
                $published ? 'product.published' : 'product.unpublished',
                $record,
                $before,
                $record->fresh()->toArray(),
            );
        }

        $count = $records->count();
        $label = match ($action) {
            'approve' => 'approved',
            'publish' => 'published for public website',
            default => 'removed from public website',
        };

        return $this->redirectAfterProductAction($request)
            ->with('success', $count.' product'.($count === 1 ? '' : 's').' '.$label.'.');
    }

    public function publish(Request $request, int $product): RedirectResponse
    {
        $record = Product::query()->findOrFail($product);
        abort_unless($request->user()?->can('update', $record), 403);

        $action = (string) $request->input('action', 'publish');
        abort_unless(in_array($action, ['approve', 'publish', 'unpublish'], true), 422, 'Invalid publishing action.');

        $before = $record->toArray();
        $metadata = is_array($record->product_metadata) ? $record->product_metadata : [];

        if ($action === 'approve') {
            $metadata['approval_status'] = 'approved';
            $metadata['approved_at'] = now()->toISOString();
            $metadata['approved_by'] = $request->user()?->id;
            $record->forceFill(['product_metadata' => $metadata])->save();
            AuditTrail::record('product.approved', $record, $before, $record->fresh()->toArray());

            return $this->redirectAfterProductAction($request)
                ->with('success', $record->name.' approved.');
        }

        $published = $action === 'publish';
        $metadata['published_website'] = $published;
        if ($published) {
            $metadata['approval_status'] = 'approved';
            $metadata['approved_at'] ??= now()->toISOString();
            $metadata['approved_by'] ??= $request->user()?->id;
            $metadata['available_for_sale'] = true;
        }

        $record->forceFill([
            'is_active' => $published,
            'status' => $published ? 'active' : ($record->status === 'draft' ? 'draft' : 'active'),
            'published_at' => $published ? ($record->published_at ?: now()) : null,
            'product_metadata' => $metadata,
        ])->save();

        AuditTrail::record(
            $published ? 'product.published' : 'product.unpublished',
            $record,
            $before,
            $record->fresh()->toArray(),
        );

        return $this->redirectAfterProductAction($request)
            ->with('success', $record->name.($published ? ' published for public website.' : ' removed from public website.'));
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorizeDeletion($request);

        $mode = (string) $request->input('mode', 'selected');
        abort_unless(in_array($mode, ['selected', 'all'], true), 422, 'Invalid bulk delete mode.');

        $ids = [];
        if ($mode === 'selected') {
            $validated = $request->validate([
                'products' => ['required', 'array', 'min:1', 'max:500'],
                'products.*' => ['required', 'integer', 'distinct'],
            ]);
            $ids = array_values(array_unique(array_map('intval', $validated['products'])));
        }

        $query = Product::query()->orderBy('id');
        if ($mode === 'selected') {
            $query->whereKey($ids);
        }

        $records = $query->get();
        if ($mode === 'selected') {
            abort_unless($records->count() === count($ids), 404);
        }

        $deleted = 0;
        foreach ($records as $record) {
            $before = $record->toArray();
            $record->delete();

            $after = $record->toArray();
            $after['deleted_at'] = $record->deleted_at?->toISOString();
            AuditTrail::record('product.trashed', $record, $before, $after);
            $deleted++;
        }

        if ($deleted === 0) {
            return redirect()
                ->route('admin.resource', ['module' => 'product-manager'])
                ->with('warning', 'No products were available to delete.');
        }

        return $this->redirectAfterProductAction($request)
            ->with('success', $deleted.' product'.($deleted === 1 ? '' : 's').' moved to Trash.');
    }

    public function destroy(Request $request, int $product): RedirectResponse
    {
        $this->authorizeDeletion($request);
        $record = Product::query()->findOrFail($product);
        $before = $record->toArray();
        $name = $record->name;

        $record->delete();

        $after = $record->toArray();
        $after['deleted_at'] = $record->deleted_at?->toISOString();
        AuditTrail::record('product.trashed', $record, $before, $after);

        return $this->redirectAfterProductAction($request)
            ->with('success', $name.' moved to Trash.');
    }

    public function restore(Request $request, int $product): RedirectResponse
    {
        $this->authorizeDeletion($request);
        $record = Product::onlyTrashed()->findOrFail($product);
        $before = $record->toArray();
        $name = $record->name;

        $record->restore();
        AuditTrail::record('product.restored', $record, $before, $record->fresh()->toArray());

        return redirect()
            ->route('admin.resource', ['module' => 'product-manager', 'tab' => 'trash'])
            ->with('success', $name.' restored.');
    }

    public function permanentDestroy(Request $request, int $product): RedirectResponse
    {
        $this->authorizeDeletion($request);
        $record = Product::onlyTrashed()->findOrFail($product);
        $before = $record->toArray();
        $name = $record->name;

        $record->forceDelete();
        AuditTrail::record('product.permanently_deleted', $record, $before, null);

        return redirect()
            ->route('admin.resource', ['module' => 'product-manager', 'tab' => 'trash'])
            ->with('success', $name.' permanently deleted.');
    }

    private function exportQuery(Request $request)
    {
        $query = Product::query();

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(fn ($products) => $products
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('sku', 'like', '%'.$search.'%')
                ->orWhere('brand', 'like', '%'.$search.'%'));
        }

        $rootCategoryId = (int) $request->query('root_category_id', 0);
        if ($rootCategoryId > 0) {
            $all = Category::query()->get(['id', 'parent_id']);
            $children = $all->groupBy(fn (Category $category) => (int) ($category->parent_id ?? 0));
            $descendants = function (int $id) use (&$descendants, $children): array {
                $ids = [$id];
                foreach ($children->get($id, collect()) as $child) {
                    $ids = array_merge($ids, $descendants((int) $child->id));
                }

                return array_values(array_unique($ids));
            };

            $query->whereIn('category_id', $descendants($rootCategoryId));
        }

        $categoryId = (int) $request->query('category_id', 0);
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        switch ((string) $request->query('product_status', '')) {
            case 'published':
                $query->where('is_active', true)->whereIn('status', ['active', 'published']);
                break;
            case 'draft':
                $query->whereIn('status', ['draft', 'planned']);
                break;
            case 'hidden':
                $query->where('is_active', false);
                break;
            case 'inactive':
                $query->where('is_active', false)->where('status', 'inactive');
                break;
        }

        switch ((string) $request->query('stock_status', '')) {
            case 'in_stock':
                $query->where('stock', '>', 10);
                break;
            case 'low_stock':
                $query->whereBetween('stock', [1, 10]);
                break;
            case 'out_of_stock':
                $query->where('stock', '<=', 0);
                break;
        }

        return $query;
    }

    private function redirectAfterProductAction(Request $request): RedirectResponse
    {
        $categoryId = (int) $request->input('return_category_id', 0);
        if ($categoryId > 0 && Category::query()->whereKey($categoryId)->exists()) {
            return redirect()->route('admin.categories.products', ['category' => $categoryId]);
        }

        return redirect()->route('admin.product-manager.index');
    }

    private function authorizeDeletion(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('products.delete'), 403);
    }
}
