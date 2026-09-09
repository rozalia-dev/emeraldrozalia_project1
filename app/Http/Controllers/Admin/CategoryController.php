<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, Category, Product};
use App\Services\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryController extends Controller
{
    private const STATUSES = ['active' => 'Active', 'draft' => 'Draft', 'inactive' => 'Inactive'];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $visibility = (string) $request->query('visibility', '');
        $perPage = in_array((int) $request->query('per_page', 10), [10, 20, 40], true) ? (int) $request->query('per_page', 10) : 10;

        $query = Category::query()->with(['parent:id,name,uuid', 'creator:id,name', 'updater:id,name'])->withCount('products');
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%'));
        }
        if (isset(self::STATUSES[$status])) {
            $query->where('status', $status);
        }
        if ($visibility === 'visible') {
            $query->where('is_visible', true);
        } elseif ($visibility === 'hidden') {
            $query->where('is_visible', false);
        }

        $all = $query->orderBy('sort_order')->orderBy('name')->orderBy('id')->get();
        $idSet = $all->pluck('id')->flip();
        $byParent = $all->groupBy(fn (Category $category) => (int) ($category->parent_id ?? 0));
        $roots = $all->filter(fn (Category $category) => ! $category->parent_id || ! $idSet->has($category->parent_id))->values();
        $page = max(1, (int) $request->query('page', 1));
        $rootPage = new LengthAwarePaginator(
            $roots->forPage($page, $perPage)->values(),
            $roots->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $treeRows = collect();
        $flatten = function (Collection $nodes, int $depth = 0) use (&$flatten, &$treeRows, $byParent): void {
            foreach ($nodes as $category) {
                $category->setAttribute('tree_depth', $depth);
                $treeRows->push($category);
                $flatten($byParent->get($category->id, collect()), $depth + 1);
            }
        };
        $flatten(collect($rootPage->items()));

        $productCounts = Product::query()->whereNotNull('category_id')->selectRaw('category_id, COUNT(*) AS aggregate')->groupBy('category_id')->pluck('aggregate', 'category_id');
        $allForCounts = Category::query()->orderBy('id')->get(['id', 'parent_id']);
        $childrenMap = $allForCounts->groupBy(fn (Category $category) => (int) ($category->parent_id ?? 0));
        $subtreeCount = function (int $id) use (&$subtreeCount, $childrenMap, $productCounts): int {
            $count = (int) ($productCounts[$id] ?? 0);
            foreach ($childrenMap->get($id, collect()) as $child) {
                $count += $subtreeCount($child->id);
            }
            return $count;
        };
        foreach ($treeRows as $category) {
            $category->setAttribute('mapped_products', $subtreeCount($category->id));
        }

        $selected = null;
        if ($request->filled('selected')) {
            $selected = Category::query()->with(['parent:id,name', 'creator:id,name', 'updater:id,name'])->withCount('products')->where('uuid', $request->string('selected'))->first();
        }
        $selected ??= $treeRows->first();
        if ($selected) {
            $selected->setAttribute('tree_depth', $this->depth($selected));
            $selected->setAttribute('mapped_products', $subtreeCount($selected->id));
        }

        $total = Category::query()->count();
        $topLevel = Category::query()->whereNull('parent_id')->count();
        $visible = Category::query()->where('status', 'active')->where('is_active', true)->where('is_visible', true)->count();
        $stats = [
            'total' => $total,
            'top_level' => $topLevel,
            'subcategories' => max(0, $total - $topLevel),
            'products_mapped' => Product::query()->whereNotNull('category_id')->count(),
            'visible' => $visible,
            'hidden' => Category::query()->where('is_visible', false)->count(),
            'draft' => Category::query()->where('status', 'draft')->count(),
            'inactive' => Category::query()->where(fn ($q) => $q->where('status', 'inactive')->orWhere('is_active', false))->count(),
        ];

        $parents = Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'parent_id']);
        $pageStart = max(1, $rootPage->currentPage() - 2);
        $pageEnd = min($rootPage->lastPage(), $rootPage->currentPage() + 2);

        return view('admin.categories.index', compact(
            'treeRows', 'rootPage', 'selected', 'parents', 'stats', 'search', 'status', 'visibility', 'perPage', 'pageStart', 'pageEnd'
        ) + ['statuses' => self::STATUSES]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $category = DB::transaction(function () use ($data): Category {
            $category = Category::create([
                ...$data,
                'is_active' => $data['status'] === 'active',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
            AuditTrail::record('category.created', $category, null, $category->toArray());
            return $category;
        });

        return redirect()->route('admin.categories.index', ['selected' => $category->uuid])->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);
        $this->assertParentAllowed($category, $data['parent_id'] ?? null);
        $before = $category->toArray();
        $category->update([...$data, 'is_active' => $data['status'] === 'active', 'updated_by' => auth()->id()]);
        AuditTrail::record('category.updated', $category, $before, $category->fresh()->toArray());

        return redirect()->route('admin.categories.index', ['selected' => $category->uuid])->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return back()->withErrors(['category' => 'Reassign products and sub-categories before deleting this category.']);
        }
        $before = $category->toArray();
        AuditTrail::record('category.deleted', $category, $before, null);
        $category->delete();
        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', Rule::exists('categories', 'id')],
            'action' => ['required', Rule::in(['active', 'draft', 'inactive', 'visible', 'hidden', 'delete'])],
        ]);
        $categories = Category::query()->whereIn('id', array_values(array_unique($data['ids'])))->get();
        if ($data['action'] === 'delete' && $categories->contains(fn (Category $category) => $category->products()->exists() || $category->children()->exists())) {
            return back()->withErrors(['category' => 'Categories with products or sub-categories cannot be bulk deleted.']);
        }

        DB::transaction(function () use ($categories, $data): void {
            foreach ($categories as $category) {
                $before = $category->toArray();
                if ($data['action'] === 'delete') {
                    AuditTrail::record('category.deleted', $category, $before, null);
                    $category->delete();
                    continue;
                }
                if (in_array($data['action'], ['visible', 'hidden'], true)) {
                    $category->update(['is_visible' => $data['action'] === 'visible', 'updated_by' => auth()->id()]);
                } else {
                    $category->update(['status' => $data['action'], 'is_active' => $data['action'] === 'active', 'updated_by' => auth()->id()]);
                }
                AuditTrail::record('category.bulk_updated', $category, $before, $category->fresh()->toArray());
            }
        });

        return back()->with('success', 'Selected categories updated.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1', 'max:200'],
            'order.*.id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'order.*.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);
        DB::transaction(function () use ($data): void {
            foreach ($data['order'] as $row) {
                $category = Category::query()->findOrFail($row['id']);
                if ((int) $category->sort_order === (int) $row['sort_order']) {
                    continue;
                }
                $before = $category->toArray();
                $category->update(['sort_order' => (int) $row['sort_order'], 'updated_by' => auth()->id()]);
                AuditTrail::record('category.reordered', $category, $before, $category->fresh()->toArray());
            }
        });
        return response()->json(['ok' => true]);
    }

    public function export(): StreamedResponse
    {
        $filename = 'categories-'.now()->format('Ymd-His').'.csv';
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['uuid','name','slug','parent_slug','status','visibility','sort_order','products','description','meta_title','meta_description']);
            Category::query()->with('parent:id,slug')->withCount('products')->orderBy('sort_order')->orderBy('id')->chunk(200, function ($categories) use ($out): void {
                foreach ($categories as $category) {
                    fputcsv($out, [
                        $category->uuid, $category->name, $category->slug, $category->parent?->slug,
                        $category->status, $category->is_visible ? 'visible' : 'hidden', $category->sort_order,
                        $category->products_count, $category->description, $category->meta_title, $category->meta_description,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $handle = fopen($request->file('file')->getRealPath(), 'rb');
        $header = fgetcsv($handle);
        $expected = ['name','slug','parent_slug','status','visibility','sort_order','description','meta_title','meta_description'];
        if (! $header) {
            throw ValidationException::withMessages(['file' => 'The CSV is empty.']);
        }
        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);
        foreach (['name', 'slug'] as $required) {
            if (! in_array($required, $header, true)) {
                throw ValidationException::withMessages(['file' => 'CSV must include name and slug columns.']);
            }
        }

        $rows = [];
        while (($values = fgetcsv($handle)) !== false && count($rows) < 500) {
            $values = array_pad($values, count($header), null);
            $row = array_combine($header, array_slice($values, 0, count($header)));
            if (filled($row['name'] ?? null) && filled($row['slug'] ?? null)) {
                $rows[] = $row;
            }
        }
        fclose($handle);
        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'No valid category rows were found.']);
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $status = isset(self::STATUSES[$row['status'] ?? '']) ? $row['status'] : 'active';
                $category = Category::query()->firstOrNew(['slug' => Str::slug($row['slug'])]);
                $before = $category->exists ? $category->toArray() : null;
                $category->fill([
                    'name' => trim($row['name']),
                    'description' => $row['description'] ?? null,
                    'status' => $status,
                    'is_active' => $status === 'active',
                    'is_visible' => strtolower((string) ($row['visibility'] ?? 'visible')) !== 'hidden',
                    'sort_order' => is_numeric($row['sort_order'] ?? null) ? max(0, (int) $row['sort_order']) : 0,
                    'meta_title' => $row['meta_title'] ?? null,
                    'meta_description' => $row['meta_description'] ?? null,
                    'updated_by' => auth()->id(),
                ]);
                if (! $category->exists) {
                    $category->created_by = auth()->id();
                }
                $category->save();
                AuditTrail::record($before ? 'category.import_updated' : 'category.import_created', $category, $before, $category->toArray());
            }
            foreach ($rows as $row) {
                if (blank($row['parent_slug'] ?? null)) {
                    continue;
                }
                $category = Category::query()->where('slug', Str::slug($row['slug']))->first();
                $parent = Category::query()->where('slug', Str::slug($row['parent_slug']))->first();
                if ($category && $parent && $category->id !== $parent->id) {
                    $this->assertParentAllowed($category, $parent->id);
                    $category->update(['parent_id' => $parent->id, 'updated_by' => auth()->id()]);
                }
            }
        });

        return redirect()->route('admin.categories.index')->with('success', count($rows).' categories imported or updated.');
    }

    public function audit(Category $category)
    {
        $entries = AuditLog::query()
            ->where('subject_type', $category->getMorphClass())
            ->where('subject_id', (string) $category->id)
            ->orderByDesc('created_at')->orderByDesc('id')->limit(50)->get()
            ->map(fn (AuditLog $log) => [
                'action' => $log->action,
                'at' => optional($log->created_at)->toIso8601String(),
                'user_id' => $log->user_id,
                'before' => $log->before,
                'after' => $log->after,
            ]);
        return response()->json(['uuid' => $category->uuid, 'entries' => $entries]);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        if (blank($request->input('slug')) && filled($request->input('name'))) {
            $request->merge(['slug' => Str::slug($request->input('name'))]);
        }
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'is_visible' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function assertParentAllowed(Category $category, ?int $parentId): void
    {
        if (! $parentId) {
            return;
        }
        if ($category->id === $parentId) {
            throw ValidationException::withMessages(['parent_id' => 'A category cannot be its own parent.']);
        }
        $cursor = Category::query()->find($parentId);
        while ($cursor) {
            if ($cursor->id === $category->id) {
                throw ValidationException::withMessages(['parent_id' => 'A category cannot be moved under one of its descendants.']);
            }
            $cursor = $cursor->parent_id ? Category::query()->find($cursor->parent_id) : null;
        }
    }

    private function depth(Category $category): int
    {
        $depth = 0;
        $parentId = $category->parent_id;
        $seen = [];
        while ($parentId && ! isset($seen[$parentId]) && $depth < 50) {
            $seen[$parentId] = true;
            $parent = Category::query()->find($parentId);
            if (! $parent) {
                break;
            }
            $depth++;
            $parentId = $parent->parent_id;
        }
        return $depth;
    }
}
