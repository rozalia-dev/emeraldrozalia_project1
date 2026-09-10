<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $perPage = in_array((int) $request->query('per_page', 10), [10, 25, 50], true)
            ? (int) $request->query('per_page', 10)
            : 10;

        $query = $this->filteredQuery($request, $tab)->withCount('products');
        $collections = $query->orderBy('sort_order')->orderBy('name')->paginate($perPage)->withQueryString();

        $selectedId = (int) $request->query('selected', 0);
        $selectedCollection = $selectedId ? $this->collectionWithDetails($selectedId) : null;
        if (! $selectedCollection && $collections->first()) {
            $selectedCollection = $this->collectionWithDetails((int) $collections->first()->id);
        }

        $metrics = [
            'total' => ProductCollection::query()->count(),
            'featured' => ProductCollection::query()->where('is_featured', true)->count(),
            'seasonal' => ProductCollection::query()->where('type', 'seasonal')->count(),
            'visible' => ProductCollection::query()->where('visibility', 'visible')->where('status', 'active')->count(),
            'products' => DB::table('collection_product')->count(),
            'draft' => ProductCollection::query()->where('status', 'draft')->count(),
            'hidden' => ProductCollection::query()->where('visibility', 'hidden')->count(),
        ];

        $typeBreakdown = collect(self::TYPES)->mapWithKeys(fn (string $collectionType) => [
            $collectionType => ProductCollection::query()->where('type', $collectionType)->count(),
        ])->all();

        $seasons = ProductCollection::query()
            ->whereNotNull('season')
            ->where('season', '!=', '')
            ->distinct()
            ->orderBy('season')
            ->pluck('season');

        $products = Product::query()->with('category')->orderBy('name')->get();
        $categories = Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $reorderCollections = ProductCollection::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'sort_order']);

        return view('admin.collections.index', compact(
            'collections', 'selectedCollection', 'metrics', 'typeBreakdown', 'tabs', 'tab',
            'search', 'type', 'status', 'season', 'seasons', 'products', 'categories',
            'reorderCollections', 'perPage'
        ) + [
            'collectionTypes' => self::TYPES,
            'collectionStatuses' => self::STATUSES,
            'collectionVisibilities' => self::VISIBILITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $collection = DB::transaction(function () use ($request, $data): ProductCollection {
            $collection = ProductCollection::create($this->attributes($request, $data));
            $this->applyProductSync($collection, $data['product_ids'] ?? []);
            AuditTrail::record('collection.created', $collection, null, $collection->fresh()->toArray());

            return $collection;
        });

        return $this->redirectToCollection($collection, 'Collection created successfully.');
    }

    public function update(Request $request, ProductCollection $collection): RedirectResponse
    {
        $data = $this->validated($request, $collection);

        DB::transaction(function () use ($request, $data, $collection): void {
            $before = $collection->toArray();
            $collection->update($this->attributes($request, $data, false, $collection));
            AuditTrail::record('collection.updated', $collection, $before, $collection->fresh()->toArray());
        });

        return $this->redirectToCollection($collection, 'Collection updated successfully.');
    }

    public function updateSettings(Request $request, ProductCollection $collection): RedirectResponse
    {
        $data = $request->validate([
            'visibility' => ['required', Rule::in(self::VISIBILITIES)],
            'status' => ['required', Rule::in(self::STATUSES)],
            'is_featured' => ['nullable', 'boolean'],
            'show_on_homepage' => ['nullable', 'boolean'],
            'allow_in_filters' => ['nullable', 'boolean'],
        ]);

        $before = $collection->toArray();
        $collection->update([
            'visibility' => $data['visibility'],
            'status' => $data['status'],
            'is_featured' => $request->boolean('is_featured'),
            'show_on_homepage' => $request->boolean('show_on_homepage'),
            'allow_in_filters' => $request->boolean('allow_in_filters'),
            'updated_by' => auth()->id(),
        ]);
        AuditTrail::record('collection.settings.updated', $collection, $before, $collection->fresh()->toArray());

        return $this->redirectToCollection($collection, 'Collection settings saved.');
    }

    public function destroy(ProductCollection $collection): RedirectResponse
    {
        DB::transaction(function () use ($collection): void {
            $before = $collection->toArray();
            AuditTrail::record('collection.deleted', $collection, $before, null);
            $collection->products()->detach();
            $collection->delete();
        });

        return redirect()->route('admin.collections.index')->with('success', 'Collection deleted.');
    }

    public function toggleVisibility(ProductCollection $collection): RedirectResponse
    {
        $before = $collection->toArray();
        $collection->update([
            'visibility' => $collection->visibility === 'visible' ? 'hidden' : 'visible',
            'updated_by' => auth()->id(),
        ]);
        AuditTrail::record('collection.visibility.updated', $collection, $before, $collection->fresh()->toArray());

        return $this->redirectToCollection($collection, 'Collection visibility updated.');
    }

    public function duplicate(ProductCollection $collection): RedirectResponse
    {
        $copy = DB::transaction(function () use ($collection): ProductCollection {
            $copy = $collection->replicate(['public_uuid', 'created_by', 'updated_by']);
            $copy->name = Str::limit($collection->name.' Copy', 180, '');
            $copy->slug = $this->uniqueSlug($copy->name);
            $copy->created_by = auth()->id();
            $copy->updated_by = auth()->id();
            $copy->save();

            $products = $collection->products()->orderByPivot('sort_order')->orderBy('products.name')->get();
            $copy->products()->sync($products->mapWithKeys(fn (Product $product, int $index) => [
                $product->id => ['sort_order' => $product->pivot->sort_order ?: $index + 1],
            ])->all());
            AuditTrail::record('collection.duplicated', $copy, null, $copy->fresh()->toArray());

            return $copy;
        });

        return $this->redirectToCollection($copy, 'Collection duplicated successfully.');
    }

    public function syncProducts(Request $request, ProductCollection $collection): RedirectResponse
    {
        $data = $request->validate([
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);

        DB::transaction(function () use ($collection, $data): void {
            $before = $collection->products()->pluck('products.id')->all();
            $this->applyProductSync($collection, $data['product_ids'] ?? []);
            AuditTrail::record('collection.products.updated', $collection, ['product_ids' => $before], ['product_ids' => $data['product_ids'] ?? []]);
        });

        return $this->redirectToCollection($collection, 'Collection products updated.');
    }

    public function addByCategory(Request $request, ProductCollection $collection): RedirectResponse
    {
        $data = $request->validate(['category_id' => ['required', 'integer', 'exists:categories,id']]);
        $category = Category::query()->findOrFail($data['category_id']);
        $productIds = Product::query()
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('id');

        if ($productIds->isEmpty()) {
            return $this->redirectToCollection($collection, 'No active products were found in that category.');
        }

        DB::transaction(function () use ($collection, $productIds, $category): void {
            $before = $collection->products()->pluck('products.id')->all();
            $nextSort = ((int) $collection->products()->max('collection_product.sort_order')) + 1;
            $sync = [];
            foreach ($productIds as $productId) {
                $sync[(int) $productId] = ['sort_order' => $nextSort++];
            }
            $collection->products()->syncWithoutDetaching($sync);
            AuditTrail::record('collection.products.category_added', $collection, ['product_ids' => $before], [
                'category_id' => $category->id,
                'product_ids' => $collection->products()->pluck('products.id')->all(),
            ]);
        });

        return $this->redirectToCollection($collection, $productIds->count().' products added from '.$category->name.'.');
    }

    public function importProducts(Request $request, ProductCollection $collection): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:4096']]);
        [$headers, $rows] = $this->readCsv($request->file('file')->getRealPath());

        if (! in_array('sku', $headers, true)) {
            throw ValidationException::withMessages(['file' => 'Product CSV must include a sku column.']);
        }

        DB::transaction(function () use ($collection, $headers, $rows): void {
            $before = $collection->products()->pluck('products.id')->all();
            $nextSort = ((int) $collection->products()->max('collection_product.sort_order')) + 1;
            $sync = [];

            foreach ($rows as $offset => $row) {
                $record = array_combine($headers, $row);
                $sku = trim((string) ($record['sku'] ?? ''));
                if ($sku === '') {
                    throw ValidationException::withMessages(['file' => 'CSV row '.($offset + 2).' is missing sku.']);
                }

                $product = Product::query()->where('sku', $sku)->first();
                if (! $product) {
                    throw ValidationException::withMessages(['file' => 'CSV row '.($offset + 2).' references unknown SKU '.$sku.'.']);
                }

                $sortOrder = isset($record['sort_order']) && is_numeric($record['sort_order'])
                    ? max(0, (int) $record['sort_order'])
                    : $nextSort++;
                $sync[$product->id] = ['sort_order' => $sortOrder];
            }

            $collection->products()->syncWithoutDetaching($sync);
            AuditTrail::record('collection.products.csv_imported', $collection, ['product_ids' => $before], [
                'product_ids' => $collection->products()->pluck('products.id')->all(),
            ]);
        });

        return $this->redirectToCollection($collection, count($rows).' product rows imported.');
    }

    public function removeProduct(ProductCollection $collection, Product $product): RedirectResponse
    {
        $before = $collection->products()->pluck('products.id')->all();
        $collection->products()->detach($product->id);
        AuditTrail::record('collection.product.removed', $collection, ['product_ids' => $before], [
            'product_ids' => $collection->products()->pluck('products.id')->all(),
        ]);

        return $this->redirectToCollection($collection, 'Product removed from collection.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'collections' => ['required', 'array', 'min:1'],
            'collections.*' => ['required', 'integer', 'distinct', 'exists:product_collections,id'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'visibility' => ['nullable', Rule::in(self::VISIBILITIES)],
            'featured' => ['nullable', Rule::in(['keep', 'yes', 'no'])],
        ]);

        if (! filled($data['status'] ?? null) && ! filled($data['visibility'] ?? null) && (($data['featured'] ?? 'keep') === 'keep')) {
            throw ValidationException::withMessages(['bulk' => 'Choose at least one bulk change.']);
        }

        $collections = ProductCollection::query()->whereKey($data['collections'])->get();
        if ($collections->count() !== count($data['collections'])) {
            throw ValidationException::withMessages(['collections' => 'One or more selected collections are unavailable.']);
        }

        DB::transaction(function () use ($collections, $data): void {
            foreach ($collections as $collection) {
                $before = $collection->toArray();
                $changes = ['updated_by' => auth()->id()];
                if (filled($data['status'] ?? null)) {
                    $changes['status'] = $data['status'];
                }
                if (filled($data['visibility'] ?? null)) {
                    $changes['visibility'] = $data['visibility'];
                }
                if (($data['featured'] ?? 'keep') !== 'keep') {
                    $changes['is_featured'] = $data['featured'] === 'yes';
                }
                $collection->update($changes);
                AuditTrail::record('collection.bulk.updated', $collection, $before, $collection->fresh()->toArray());
            }
        });

        return back()->with('success', $collections->count().' collections updated.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'integer', 'distinct', 'exists:product_collections,id'],
        ]);

        $collections = ProductCollection::query()->whereKey($data['order'])->get()->keyBy('id');
        if ($collections->count() !== count($data['order'])) {
            throw ValidationException::withMessages(['order' => 'The reorder list contains an unavailable collection.']);
        }

        DB::transaction(function () use ($data, $collections): void {
            foreach ($data['order'] as $position => $id) {
                $collection = $collections[(int) $id];
                $sortOrder = $position + 1;
                if ((int) $collection->sort_order === $sortOrder) {
                    continue;
                }
                $before = $collection->toArray();
                $collection->update(['sort_order' => $sortOrder, 'updated_by' => auth()->id()]);
                AuditTrail::record('collection.reordered', $collection, $before, $collection->fresh()->toArray());
            }
        });

        return redirect()->route('admin.collections.index')->with('success', 'Collection order saved.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:4096']]);
        [$headers, $rows] = $this->readCsv($request->file('file')->getRealPath());

        foreach (['name', 'type'] as $required) {
            if (! in_array($required, $headers, true)) {
                throw ValidationException::withMessages(['file' => 'Collection CSV must include name and type columns.']);
            }
        }

        $imported = 0;
        DB::transaction(function () use ($headers, $rows, &$imported): void {
            foreach ($rows as $offset => $row) {
                $record = array_combine($headers, $row);
                $rowNumber = $offset + 2;
                $name = trim((string) ($record['name'] ?? ''));
                $type = strtolower(trim((string) ($record['type'] ?? '')));
                $slug = Str::slug((string) ($record['slug'] ?? $name));
                $status = strtolower(trim((string) ($record['status'] ?? 'active')));
                $visibility = strtolower(trim((string) ($record['visibility'] ?? 'visible')));

                if ($name === '' || $slug === '') {
                    throw ValidationException::withMessages(['file' => "CSV row {$rowNumber} requires a name."]);
                }
                if (! in_array($type, self::TYPES, true)) {
                    throw ValidationException::withMessages(['file' => "CSV row {$rowNumber} has an invalid type."]);
                }
                if (! in_array($status, self::STATUSES, true)) {
                    throw ValidationException::withMessages(['file' => "CSV row {$rowNumber} has an invalid status."]);
                }
                if (! in_array($visibility, self::VISIBILITIES, true)) {
                    throw ValidationException::withMessages(['file' => "CSV row {$rowNumber} has an invalid visibility."]);
                }

                $collection = ProductCollection::query()->where('slug', $slug)->first();
                $before = $collection?->toArray();
                $values = [
                    'name' => $name,
                    'slug' => $slug,
                    'type' => $type,
                    'season' => $this->nullableCsv($record['season'] ?? null),
                    'description' => $this->nullableCsv($record['description'] ?? null),
                    'image' => $this->nullableCsv($record['image'] ?? null),
                    'status' => $status,
                    'visibility' => $visibility,
                    'is_featured' => $this->csvBoolean($record['is_featured'] ?? false),
                    'show_on_homepage' => $this->csvBoolean($record['show_on_homepage'] ?? false),
                    'allow_in_filters' => $this->csvBoolean($record['allow_in_filters'] ?? true),
                    'sort_order' => max(0, (int) ($record['sort_order'] ?? 0)),
                    'meta_title' => $this->nullableCsv($record['meta_title'] ?? null),
                    'meta_description' => $this->nullableCsv($record['meta_description'] ?? null),
                    'meta_image' => $this->nullableCsv($record['meta_image'] ?? null),
                    'updated_by' => auth()->id(),
                ];

                if ($collection) {
                    $collection->update($values);
                    AuditTrail::record('collection.import.updated', $collection, $before, $collection->fresh()->toArray());
                } else {
                    $collection = ProductCollection::create($values + ['created_by' => auth()->id()]);
                    AuditTrail::record('collection.import.created', $collection, null, $collection->toArray());
                }
                $imported++;
            }
        });

        return redirect()->route('admin.collections.index')->with('success', $imported.' collections imported or updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $tab = (string) $request->query('tab', 'all');
        $filename = 'emerald-rozalia-collections-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request, $tab): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, [
                'name', 'slug', 'type', 'season', 'status', 'visibility', 'is_featured',
                'show_on_homepage', 'allow_in_filters', 'sort_order', 'products', 'description',
                'meta_title', 'meta_description', 'image', 'meta_image', 'uuid',
            ]);

            $this->filteredQuery($request, $tab)
                ->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->chunkById(250, function ($collections) use ($output): void {
                    foreach ($collections as $collection) {
                        fputcsv($output, [
                            $collection->name,
                            $collection->slug,
                            $collection->type,
                            $collection->season,
                            $collection->status,
                            $collection->visibility,
                            $collection->is_featured ? 1 : 0,
                            $collection->show_on_homepage ? 1 : 0,
                            $collection->allow_in_filters ? 1 : 0,
                            $collection->sort_order,
                            $collection->products_count,
                            $collection->description,
                            $collection->meta_title,
                            $collection->meta_description,
                            $collection->image,
                            $collection->meta_image,
                            $collection->public_uuid,
                        ]);
                    }
                });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function audit(ProductCollection $collection): View
    {
        $logs = AuditLog::query()
            ->where('subject_type', $collection->getMorphClass())
            ->where('subject_id', $collection->getKey())
            ->latest('created_at')
            ->paginate(30);

        return view('admin.collections.audit', compact('collection', 'logs'));
    }

    private function filteredQuery(Request $request, string $tab): Builder
    {
        $query = ProductCollection::query();
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $search = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');
        $status = (string) $request->query('status', '');
        $season = trim((string) $request->query('season', ''));

        if ($search !== '') {
            $query->where(fn ($collections) => $collections
                ->where('name', $like, '%'.$search.'%')
                ->orWhere('slug', $like, '%'.$search.'%')
                ->orWhere('description', $like, '%'.$search.'%'));
        }
        if (in_array($type, self::TYPES, true)) {
            $query->where('type', $type);
        }
        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($season !== '') {
            $query->where('season', $like, '%'.$season.'%');
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

        return $query;
    }

    private function collectionWithDetails(int $id): ?ProductCollection
    {
        return ProductCollection::query()->with([
            'products' => fn ($products) => $products->with('media')->orderByPivot('sort_order')->orderBy('products.name'),
            'creator',
            'updater',
        ])->find($id);
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
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'visibility' => ['required', Rule::in(self::VISIBILITIES)],
            'is_featured' => ['nullable', 'boolean'],
            'show_on_homepage' => ['nullable', 'boolean'],
            'allow_in_filters' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_image' => ['nullable', 'string', 'max:255'],
            'meta_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);

        $slug = Str::slug((string) (($data['slug'] ?? null) ?: $data['name']));
        validator(['slug' => $slug], ['slug' => ['required', 'max:180', Rule::unique('product_collections', 'slug')->ignore($collection?->id)]])->validate();
        $data['slug'] = $slug;

        return $data;
    }

    private function attributes(Request $request, array $data, bool $isCreating = true, ?ProductCollection $collection = null): array
    {
        $image = $data['image'] ?? $collection?->image;
        $metaImage = $data['meta_image'] ?? $collection?->meta_image;

        if ($request->hasFile('image_file')) {
            $image = $request->file('image_file')->store('collections', 'public');
        }
        if ($request->hasFile('meta_image_file')) {
            $metaImage = $request->file('meta_image_file')->store('collections/meta', 'public');
        }

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'type' => $data['type'],
            'season' => $data['season'] ?? null,
            'description' => $data['description'] ?? null,
            'image' => $image,
            'status' => $data['status'],
            'visibility' => $data['visibility'],
            'is_featured' => $request->boolean('is_featured'),
            'show_on_homepage' => $request->boolean('show_on_homepage'),
            'allow_in_filters' => $request->boolean('allow_in_filters'),
            'sort_order' => (int) $data['sort_order'],
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_image' => $metaImage,
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

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;
        while (ProductCollection::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function redirectToCollection(ProductCollection $collection, string $message): RedirectResponse
    {
        return redirect()->route('admin.collections.index', ['selected' => $collection->id])->with('success', $message);
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'The CSV file could not be opened.']);
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'The CSV file is empty.']);
        }

        $headers = array_map(fn ($header) => Str::snake(trim((string) $header)), $headers);
        $rows = [];
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($row === [null] || $row === []) {
                continue;
            }
            if (count($row) !== count($headers)) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => "CSV row {$rowNumber} has the wrong number of columns."]);
            }
            $rows[] = $row;
        }
        fclose($handle);

        return [$headers, $rows];
    }

    private function nullableCsv(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function csvBoolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'on'], true);
    }
}
