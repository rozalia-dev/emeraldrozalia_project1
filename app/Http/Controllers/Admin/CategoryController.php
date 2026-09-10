<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryController extends Controller
{
    private const STATUSES = ['active', 'draft', 'inactive'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $visibility = (string) $request->query('visibility', '');
        $perPage = in_array((int) $request->query('per_page', 10), [10, 25, 50], true)
            ? (int) $request->query('per_page', 10)
            : 10;

        $rootsQuery = Category::query()
            ->whereNull('parent_id')
            ->with(['childrenRecursive', 'creator', 'updater'])
            ->withCount('products');

        if ($search !== '') {
            $rootsQuery->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhereHas('children', fn ($children) => $children
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%'));
            });
        }

        if (in_array($status, self::STATUSES, true)) {
            $rootsQuery->where('status', $status);
        }

        if ($visibility === 'visible') {
            $rootsQuery->where('is_visible', true);
        } elseif ($visibility === 'hidden') {
            $rootsQuery->where('is_visible', false);
        }

        $roots = $rootsQuery
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $selected = null;
        $selectedUuid = (string) $request->query('selected', '');
        if ($selectedUuid !== '') {
            $selected = Category::query()
                ->with(['parent', 'creator', 'updater'])
                ->withCount('products')
                ->where('public_uuid', $selectedUuid)
                ->first();
        }

        if (! $selected) {
            $selected = Category::query()
                ->with(['parent', 'creator', 'updater'])
                ->withCount('products')
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->first();
        }

        $allCategories = Category::query()
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'public_uuid', 'parent_id', 'name', 'slug']);

        $total = Category::query()->count();
        $visible = Category::query()->where('is_visible', true)->where('status', 'active')->count();
        $stats = [
            'total' => $total,
            'level_one' => Category::query()->whereNull('parent_id')->count(),
            'subcategories' => Category::query()->whereNotNull('parent_id')->count(),
            'products_mapped' => Product::query()->whereNotNull('category_id')->count(),
            'visible' => $visible,
            'hidden' => max(0, $total - $visible),
            'draft' => Category::query()->where('status', 'draft')->count(),
            'inactive' => Category::query()->where('status', 'inactive')->count(),
        ];

        return view('admin.categories.index', compact(
            'roots',
            'selected',
            'allCategories',
            'stats',
            'search',
            'status',
            'visibility',
            'perPage'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $parent = $this->resolveParent($data['parent_id'] ?? null);

        $category = DB::transaction(function () use ($data, $parent): Category {
            $category = Category::create([
                ...$data,
                'parent_id' => $parent?->id,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'is_active' => $data['status'] === 'active',
            ]);

            AuditTrail::record('category.created', $category, null, $category->toArray());

            return $category;
        });

        return redirect()->route('admin.categories.index', ['selected' => $category->public_uuid])
            ->with('success', 'Category created successfully.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validatedData($request, $category);
        $parent = $this->resolveParent($data['parent_id'] ?? null);
        $this->guardAgainstCycle($category, $parent);

        $before = $category->toArray();
        $category->update([
            ...$data,
            'parent_id' => $parent?->id,
            'updated_by' => auth()->id(),
            'is_active' => $data['status'] === 'active',
        ]);
        AuditTrail::record('category.updated', $category, $before, $category->fresh()->toArray());

        return redirect()->route('admin.categories.index', ['selected' => $category->public_uuid])
            ->with('success', 'Category updated successfully.');
    }

    public function toggleVisibility(Category $category): RedirectResponse
    {
        $before = $category->toArray();
        $category->update([
            'is_visible' => ! $category->is_visible,
            'updated_by' => auth()->id(),
        ]);
        AuditTrail::record('category.visibility.changed', $category, $before, $category->fresh()->toArray());

        return back()->with('success', $category->is_visible ? 'Category is now visible on the website.' : 'Category is now hidden from the website.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'uuid'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'visibility' => ['nullable', Rule::in(['visible', 'hidden'])],
        ]);

        if (! filled($data['status'] ?? null) && ! filled($data['visibility'] ?? null)) {
            throw ValidationException::withMessages(['bulk' => 'Choose a status or visibility change first.']);
        }

        $categories = Category::query()->whereIn('public_uuid', $data['categories'])->get();
        if ($categories->count() !== count(array_unique($data['categories']))) {
            throw ValidationException::withMessages(['categories' => 'One or more selected categories are unavailable.']);
        }

        DB::transaction(function () use ($categories, $data): void {
            foreach ($categories as $category) {
                $before = $category->toArray();
                $changes = ['updated_by' => auth()->id()];

                if (filled($data['status'] ?? null)) {
                    $changes['status'] = $data['status'];
                    $changes['is_active'] = $data['status'] === 'active';
                }
                if (filled($data['visibility'] ?? null)) {
                    $changes['is_visible'] = $data['visibility'] === 'visible';
                }

                $category->update($changes);
                AuditTrail::record('category.bulk.updated', $category, $before, $category->fresh()->toArray());
            }
        });

        return back()->with('success', $categories->count().' categories updated.');
    }

    public function reorder(Request $request): Response
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'uuid'],
        ]);

        $categories = Category::query()->whereIn('public_uuid', $data['order'])->get()->keyBy('public_uuid');
        if ($categories->count() !== count(array_unique($data['order']))) {
            throw ValidationException::withMessages(['order' => 'The category order contains an unavailable category.']);
        }

        $parentIds = $categories->pluck('parent_id')->unique()->values();
        if ($parentIds->count() !== 1) {
            throw ValidationException::withMessages(['order' => 'Only sibling categories can be reordered together.']);
        }

        DB::transaction(function () use ($data, $categories): void {
            foreach ($data['order'] as $position => $uuid) {
                $category = $categories[$uuid];
                if ((int) $category->sort_order === $position + 1) {
                    continue;
                }

                $before = $category->toArray();
                $category->update(['sort_order' => $position + 1, 'updated_by' => auth()->id()]);
                AuditTrail::record('category.reordered', $category, $before, $category->fresh()->toArray());
            }
        });

        return response()->noContent();
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'rb');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'The CSV file could not be opened.']);
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'The CSV file is empty.']);
        }

        $headers = array_map(fn ($header) => Str::snake(trim((string) $header)), $headers);
        $required = ['name', 'slug'];
        if (array_diff($required, $headers)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'CSV headers must include name and slug.']);
        }

        $imported = 0;
        $rowNumber = 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if (count($row) !== count($headers)) {
                    throw ValidationException::withMessages(['file' => "CSV row {$rowNumber} has the wrong number of columns."]);
                }

                $record = array_combine($headers, $row);
                $name = trim((string) ($record['name'] ?? ''));
                $slug = Str::slug((string) ($record['slug'] ?? $name));
                if ($name === '' || $slug === '') {
                    throw ValidationException::withMessages(['file' => "CSV row {$rowNumber} requires a name and slug."]);
                }

                $parent = null;
                if (filled($record['parent_slug'] ?? null)) {
                    $parent = Category::query()->where('slug', trim((string) $record['parent_slug']))->first();
                    if (! $parent) {
                        throw ValidationException::withMessages(['file' => "CSV row {$rowNumber} references an unknown parent_slug."]);
                    }
                }

                $status = strtolower(trim((string) ($record['status'] ?? 'active')));
                if (! in_array($status, self::STATUSES, true)) {
                    $status = 'active';
                }
                $isVisible = ! in_array(strtolower(trim((string) ($record['visibility'] ?? 'visible'))), ['hidden', '0', 'false', 'no'], true);

                $category = Category::query()->where('slug', $slug)->first();
                $before = $category?->toArray();
                $values = [
                    'name' => $name,
                    'slug' => $slug,
                    'parent_id' => $parent?->id,
                    'status' => $status,
                    'is_active' => $status === 'active',
                    'is_visible' => $isVisible,
                    'sort_order' => max(0, (int) ($record['sort_order'] ?? 0)),
                    'description' => filled($record['description'] ?? null) ? trim((string) $record['description']) : null,
                    'meta_title' => filled($record['meta_title'] ?? null) ? trim((string) $record['meta_title']) : null,
                    'meta_description' => filled($record['meta_description'] ?? null) ? trim((string) $record['meta_description']) : null,
                    'updated_by' => auth()->id(),
                ];

                if ($category) {
                    $this->guardAgainstCycle($category, $parent);
                    $category->update($values);
                    AuditTrail::record('category.import.updated', $category, $before, $category->fresh()->toArray());
                } else {
                    $category = Category::create([...$values, 'created_by' => auth()->id()]);
                    AuditTrail::record('category.import.created', $category, null, $category->toArray());
                }

                $imported++;
            }
            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            fclose($handle);
            throw $exception;
        }

        fclose($handle);

        return back()->with('success', $imported.' categories imported or updated.');
    }

    public function export(): StreamedResponse
    {
        $filename = 'emerald-rozalia-categories-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['name', 'slug', 'parent_slug', 'status', 'visibility', 'sort_order', 'description', 'meta_title', 'meta_description', 'uuid']);

            Category::query()
                ->with('parent')
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->chunk(250, function ($categories) use ($output): void {
                    foreach ($categories as $category) {
                        fputcsv($output, [
                            $category->name,
                            $category->slug,
                            $category->parent?->slug,
                            $category->status,
                            $category->is_visible ? 'visible' : 'hidden',
                            $category->sort_order,
                            $category->description,
                            $category->meta_title,
                            $category->meta_description,
                            $category->public_uuid,
                        ]);
                    }
                });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function audit(Category $category): View
    {
        $logs = AuditLog::query()
            ->where('subject_type', $category->getMorphClass())
            ->where('subject_id', $category->getKey())
            ->latest('created_at')
            ->paginate(30);

        return view('admin.categories.audit', compact('category', 'logs'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return back()->withErrors(['category' => 'Move or delete the sub-categories before deleting this category.']);
        }
        if ($category->products()->exists()) {
            return back()->withErrors(['category' => 'Reassign the products before deleting this category.']);
        }

        $before = $category->toArray();
        AuditTrail::record('category.deleted', $category, $before, null);
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    private function validatedData(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'is_visible' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['slug'] = Str::slug(filled($data['slug'] ?? null) ? $data['slug'] : $data['name']);
        if ($data['slug'] === '') {
            throw ValidationException::withMessages(['slug' => 'Enter a valid category name or slug.']);
        }

        $duplicate = Category::query()->where('slug', $data['slug']);
        if ($category) {
            $duplicate->whereKeyNot($category->id);
        }
        if ($duplicate->exists()) {
            throw ValidationException::withMessages(['slug' => 'This category slug is already in use.']);
        }

        return $data;
    }

    private function resolveParent(mixed $parentId): ?Category
    {
        if (! filled($parentId)) {
            return null;
        }

        return Category::query()->findOrFail((int) $parentId);
    }

    private function guardAgainstCycle(Category $category, ?Category $parent): void
    {
        if (! $parent) {
            return;
        }

        if ($parent->is($category)) {
            throw ValidationException::withMessages(['parent_id' => 'A category cannot be its own parent.']);
        }

        $cursor = $parent;
        while ($cursor) {
            if ($cursor->is($category)) {
                throw ValidationException::withMessages(['parent_id' => 'A category cannot be moved below one of its descendants.']);
            }
            $cursor = $cursor->parent()->first();
        }
    }
}
