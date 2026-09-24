<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
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
            ->with('category')
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
            $query->where(fn ($products) => $products
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('sku', 'like', '%'.$search.'%')
                ->orWhere('brand', 'like', '%'.$search.'%'));
        }

        $categoryId = (int) $request->query('category_id', 0);
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
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

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.product-manager.index', compact(
            'products',
            'categories',
            'stats',
            'tabs',
            'tab',
            'search',
            'categoryId',
            'minPrice',
            'maxPrice',
            'rating',
            'featured',
        ));
    }

    public function bulkPublish(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'products' => ['required', 'array', 'min:1', 'max:500'],
            'products.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', 'in:publish,unpublish'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['products'])));
        $records = Product::query()->whereKey($ids)->orderBy('id')->get();
        abort_unless($records->count() === count($ids), 404);

        $published = $validated['action'] === 'publish';
        foreach ($records as $record) {
            abort_unless($request->user()?->can('update', $record), 403);

            $before = $record->toArray();
            $metadata = is_array($record->product_metadata) ? $record->product_metadata : [];
            $metadata['published_website'] = $published;
            if ($published) {
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
        $label = $published ? 'published for public website' : 'removed from public website';

        return redirect()
            ->route('admin.product-manager.index')
            ->with('success', $count.' product'.($count === 1 ? '' : 's').' '.$label.'.');
    }

    public function publish(Request $request, int $product): RedirectResponse
    {
        $record = Product::query()->findOrFail($product);
        abort_unless($request->user()?->can('update', $record), 403);

        $action = (string) $request->input('action', 'publish');
        abort_unless(in_array($action, ['publish', 'unpublish'], true), 422, 'Invalid publishing action.');

        $published = $action === 'publish';
        $before = $record->toArray();
        $metadata = is_array($record->product_metadata) ? $record->product_metadata : [];
        $metadata['published_website'] = $published;
        if ($published) {
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

        return redirect()
            ->route('admin.product-manager.index')
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

        return redirect()
            ->route('admin.resource', ['module' => 'product-manager'])
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

        return redirect()
            ->route('admin.resource', ['module' => 'product-manager'])
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

    private function authorizeDeletion(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('products.delete'), 403);
    }
}
