<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectionController extends Controller
{
    private const TYPES = ['curated', 'seasonal', 'occasion', 'automated'];
    private const STATUSES = ['active', 'draft', 'archived'];
    private const VISIBILITIES = ['visible', 'hidden'];

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'all');
        $tabs = [
            'all' => 'All Collections',
            'featured' => 'Featured',
            'seasonal' => 'Seasonal',
            'trending' => 'Trending',
            'new' => 'New Arrivals',
            'hidden' => 'Hidden',
            'draft' => 'Draft',
        ];
        if (! array_key_exists($tab, $tabs)) {
            $tab = 'all';
        }

        $search = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');
        $status = (string) $request->query('status', '');
        $season = trim((string) $request->query('season', ''));

        $query = ProductCollection::query()->withCount('products');
        if ($search !== '') {
            $query->where(fn ($collections) => $collections
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%'));
        }
        if (in_array($type, self::TYPES, true)) {
            $query->where('type', $type);
        }
        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($season !== '') {
            $query->where('season', 'like', '%'.$season.'%');
        }

        match ($tab) {
            'featured' => $query->where('is_featured', true),
            'seasonal' => $query->where('type', 'seasonal'),
            'trending' => $query->where('sort_order', '<=', 5),
            'new' => $query->where('created_at', '>=', now()->subDays(30)),
            'hidden' => $query->where('visibility', 'hidden'),
            'draft' => $query->where('status', 'draft'),
            default => null,
        };

        $collections = $query->orderBy('sort_order')->orderBy('name')->paginate(10)->withQueryString();
        $selectedId = (int) $request->query('selected', 0);
        $selectedCollection = $selectedId
            ? ProductCollection::with([
                'products' => fn ($products) => $products->with('media')->orderByPivot('sort_order')->orderBy('products.name'),
                'creator',
                'updater',
            ])->find($selectedId)
            : null;
        if (! $selectedCollection && $collections->first()) {
            $selectedCollection = ProductCollection::with([
                'products' => fn ($products) => $products->with('media')->orderByPivot('sort_order')->orderBy('products.name'),
                'creator',
                'updater',
            ])->find($collections->first()->id);
        }

        $metrics = [
            'total' => ProductCollection::count(),
            'featured' => ProductCollection::where('is_featured', true)->count(),
            'seasonal' => ProductCollection::where('type', 'seasonal')->count(),
            'visible' => ProductCollection::where('visibility', 'visible')->where('status', 'active')->count(),
            'products' => DB::table('collection_product')->select('product_id')->distinct()->count(),
        ];
        $typeBreakdown = collect(self::TYPES)->mapWithKeys(fn (string $collectionType) => [
            $collectionType => ProductCollection::where('type', $collectionType)->count(),
        ])->all();
        $seasons = ProductCollection::query()->whereNotNull('season')->where('season', '!=', '')->distinct()->orderBy('season')->pluck('season');
        $products = Product::query()->with('category')->orderBy('name')->get();

        return view('admin.collections.index', compact(
            'collections', 'selectedCollection', 'metrics', 'typeBreakdown', 'tabs', 'tab',
            'search', 'type', 'status', 'season', 'seasons', 'products'
        ) + [
            'collectionTypes' => self::TYPES,
            'collectionStatuses' => self::STATUSES,
            'collectionVisibilities' => self::VISIBILITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $collection = ProductCollection::create($this->attributes($request, $data));
        $this->applyProductSync($collection, $data['product_ids'] ?? []);
        AuditTrail::record('collection.created', $collection, null, $collection->fresh()->toArray());

        return $this->redirectToCollection($collection, 'Collection created successfully.');
    }

    public function update(Request $request, ProductCollection $collection): RedirectResponse
    {
        $data = $this->validated($request, $collection);
        $before = $collection->toArray();
        $collection->update($this->attributes($request, $data, false));
        AuditTrail::record('collection.updated', $collection, $before, $collection->fresh()->toArray());

        return $this->redirectToCollection($collection, 'Collection updated successfully.');
    }

    public function destroy(ProductCollection $collection): RedirectResponse
    {
        $before = $collection->toArray();
        $collection->products()->detach();
        $collection->delete();
        AuditTrail::record('collection.deleted', $collection, $before, null);

        return redirect()->route('admin.collections.index')->with('success', 'Collection deleted.');
    }

    public function toggleVisibility(ProductCollection $collection): RedirectResponse
    {
        $before = $collection->toArray();
        $collection->update(['visibility' => $collection->visibility === 'visible' ? 'hidden' : 'visible', 'updated_by' => auth()->id()]);
        AuditTrail::record('collection.visibility.updated', $collection, $before, $collection->fresh()->toArray());

        return $this->redirectToCollection($collection, 'Collection visibility updated.');
    }

    public function duplicate(ProductCollection $collection): RedirectResponse
    {
        $copy = $collection->replicate(['public_uuid', 'created_by', 'updated_by']);
        $copy->name = Str::limit($collection->name.' Copy', 180, '');
        $copy->slug = $this->uniqueSlug($copy->name, $collection->slug);
        $copy->created_by = auth()->id();
        $copy->updated_by = auth()->id();
        $copy->save();
        $products = $collection->products()->orderByPivot('sort_order')->orderBy('products.name')->get();
        $copy->products()->sync($products->mapWithKeys(fn (Product $product, int $index) => [
            $product->id => ['sort_order' => $product->pivot->sort_order ?? $index],
        ])->all());
        AuditTrail::record('collection.duplicated', $copy, null, $copy->fresh()->toArray());

        return $this->redirectToCollection($copy, 'Collection duplicated successfully.');
    }

    public function syncProducts(Request $request, ProductCollection $collection): RedirectResponse
    {
        $data = $request->validate([
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);
        $before = $collection->products()->pluck('products.id')->all();
        $this->applyProductSync($collection, $data['product_ids'] ?? []);
        AuditTrail::record('collection.products.updated', $collection, ['product_ids' => $before], ['product_ids' => $data['product_ids'] ?? []]);

        return $this->redirectToCollection($collection, 'Collection products updated.');
    }

    public function removeProduct(ProductCollection $collection, Product $product): RedirectResponse
    {
        $collection->products()->detach($product->id);

        return $this->redirectToCollection($collection, 'Product removed from collection.');
    }

    private function validated(Request $request, ?ProductCollection $collection = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180'],
            'type' => ['required', Rule::in(self::TYPES)],
            'season' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'visibility' => ['required', Rule::in(self::VISIBILITIES)],
            'is_featured' => ['nullable', 'boolean'],
            'show_on_homepage' => ['nullable', 'boolean'],
            'allow_in_filters' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_image' => ['nullable', 'string', 'max:255'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);

        $slug = Str::slug($data['slug'] ?? $data['name']);
        validator(['slug' => $slug], ['slug' => ['required', 'max:180', Rule::unique('product_collections', 'slug')->ignore($collection?->id)]])->validate();
        $data['slug'] = $slug;

        return $data;
    }

    private function attributes(Request $request, array $data, bool $isCreating = true): array
    {
        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'type' => $data['type'],
            'season' => $data['season'] ?? null,
            'description' => $data['description'] ?? null,
            'image' => $data['image'] ?? null,
            'status' => $data['status'],
            'visibility' => $data['visibility'],
            'is_featured' => $request->boolean('is_featured'),
            'show_on_homepage' => $request->boolean('show_on_homepage'),
            'allow_in_filters' => $request->boolean('allow_in_filters'),
            'sort_order' => (int) $data['sort_order'],
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_image' => $data['meta_image'] ?? null,
            'updated_by' => auth()->id(),
            ...($isCreating ? ['created_by' => auth()->id()] : []),
        ];
    }

    private function applyProductSync(ProductCollection $collection, array $productIds): void
    {
        $collection->products()->sync(collect($productIds)->values()->mapWithKeys(fn ($productId, $index) => [
            (int) $productId => ['sort_order' => $index + 1],
        ])->all());
    }

    private function uniqueSlug(string $name, string $originalSlug): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;
        while (ProductCollection::where('slug', $slug)->where('slug', '!=', $originalSlug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function redirectToCollection(ProductCollection $collection, string $message): RedirectResponse
    {
        return redirect()->route('admin.collections.index', ['selected' => $collection->id])->with('success', $message);
    }
}
