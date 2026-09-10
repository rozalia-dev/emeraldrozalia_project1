<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantMedia;
use App\Models\VariantSetting;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VariantController extends Controller
{
    private const STATUSES = ['active', 'inactive', 'discontinued'];
    private const MEDIA_TYPES = ['image', 'video', 'spin_360'];

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'all');
        if (! in_array($tab, ['all', 'active', 'inactive', 'low_stock', 'out_of_stock', 'discontinued'], true)) {
            $tab = 'all';
        }

        $search = trim((string) $request->query('q', ''));
        $productId = (int) $request->query('product_id', 0);
        $status = (string) $request->query('status', '');
        $optionSet = (string) $request->query('option_set', '');
        $stockStatus = (string) $request->query('stock_status', '');
        $perPage = in_array((int) $request->query('per_page', 8), [8, 16, 32, 64], true)
            ? (int) $request->query('per_page', 8)
            : 8;

        $settings = VariantSetting::current();
        $query = $this->scopedVariants()->with(['product', 'media']);

        if ($search !== '') {
            $query->where(function ($variants) use ($search): void {
                $variants->where('sku', 'like', '%'.$search.'%')
                    ->orWhere('barcode', 'like', '%'.$search.'%')
                    ->orWhere('colour', 'like', '%'.$search.'%')
                    ->orWhere('size', 'like', '%'.$search.'%')
                    ->orWhere('material', 'like', '%'.$search.'%')
                    ->orWhere('style', 'like', '%'.$search.'%')
                    ->orWhereHas('product', fn ($products) => $products
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%'));
            });
        }

        if ($productId > 0) {
            $query->where('product_id', $productId);
        }

        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        if (in_array($optionSet, ['colour', 'size', 'material', 'style'], true)) {
            $query->whereNotNull($optionSet)->where($optionSet, '!=', '');
        }

        if ($tab === 'active') {
            $query->where('status', 'active')->where('is_active', true);
        } elseif ($tab === 'inactive') {
            $query->where(fn ($q) => $q->where('status', 'inactive')->orWhere('is_active', false));
        } elseif ($tab === 'low_stock') {
            $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold');
        } elseif ($tab === 'out_of_stock') {
            $query->where('stock', '<=', 0);
        } elseif ($tab === 'discontinued') {
            $query->where('status', 'discontinued');
        }

        if ($stockStatus === 'in_stock') {
            $query->whereColumn('stock', '>', 'low_stock_threshold');
        } elseif ($stockStatus === 'low_stock') {
            $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold');
        } elseif ($stockStatus === 'out_of_stock') {
            $query->where('stock', '<=', 0);
        }

        $variants = $query
            ->orderByDesc('is_active')
            ->orderBy('product_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $selected = null;
        $selectedUuid = (string) $request->query('selected', '');
        if ($selectedUuid !== '') {
            $selected = $this->scopedVariants()
                ->with(['product', 'media'])
                ->where('public_uuid', $selectedUuid)
                ->first();
        }
        if (! $selected) {
            $selected = $variants->first() ?: $this->scopedVariants()->with(['product', 'media'])->first();
        }

        $all = $this->scopedVariants();
        $total = (clone $all)->count();
        $active = (clone $all)->where('status', 'active')->where('is_active', true)->count();
        $outOfStock = (clone $all)->where('stock', '<=', 0)->count();
        $lowStock = (clone $all)->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold')->count();
        $inactive = max(0, $total - $active);
        $stats = [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
            'total_stock' => (int) ((clone $all)->sum('stock') ?? 0),
            'average_price' => (float) ((clone $all)->whereNotNull('price')->avg('price') ?? 0),
            'highest_price' => (float) ((clone $all)->max('price') ?? 0),
            'lowest_price' => (float) ((clone $all)->whereNotNull('price')->min('price') ?? 0),
        ];

        $optionOverview = $this->optionOverview();
        $products = Product::query()
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price', 'compare_price', 'stock', 'image']);

        return view('admin.variants.index', compact(
            'variants',
            'selected',
            'products',
            'settings',
            'stats',
            'optionOverview',
            'tab',
            'search',
            'productId',
            'status',
            'optionSet',
            'stockStatus',
            'perPage'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedVariant($request);
        $product = Product::query()->findOrFail((int) $data['product_id']);
        $settings = VariantSetting::current();

        $variant = DB::transaction(function () use ($data, $product, $settings): ProductVariant {
            $sku = filled($data['sku'] ?? null)
                ? trim($data['sku'])
                : $this->generateSku($product, $data);

            $variant = ProductVariant::create([
                ...$data,
                'sku' => $sku,
                'price' => $data['price'] ?? $product->price,
                'compare_price' => $data['compare_price'] ?? $product->compare_price,
                'stock_total' => max((int) ($data['stock_total'] ?? 0), (int) $data['stock']),
                'status' => $data['status'] ?? 'active',
                'is_active' => ($data['status'] ?? 'active') === 'active',
                'low_stock_threshold' => $data['low_stock_threshold'] ?? $settings->low_stock_threshold,
                'track_inventory' => array_key_exists('track_inventory', $data)
                    ? (bool) $data['track_inventory']
                    : $settings->track_variant_inventory,
                'backorder' => array_key_exists('backorder', $data)
                    ? (bool) $data['backorder']
                    : $settings->backorder,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if ($variant->stock !== 0) {
                InventoryMovement::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $variant->stock,
                    'type' => 'opening_balance',
                    'reference' => 'VARIANT-MANAGER',
                    'note' => 'Variant opening stock',
                ]);
            }

            AuditTrail::record('variant.created', $variant, null, $variant->toArray());

            return $variant;
        });

        return redirect()->route('admin.variants.index', ['selected' => $variant->public_uuid])
            ->with('success', 'Variant created successfully.');
    }

    public function update(Request $request, ProductVariant $variant): RedirectResponse
    {
        $this->guardTenant($variant);
        $data = $this->validatedVariant($request, $variant);
        Product::query()->findOrFail((int) $data['product_id']);

        DB::transaction(function () use ($variant, $data): void {
            $before = $variant->toArray();
            $oldStock = (int) $variant->stock;

            $variant->update([
                ...$data,
                'stock_total' => max((int) ($data['stock_total'] ?? 0), (int) $data['stock']),
                'status' => $data['status'],
                'is_active' => $data['status'] === 'active',
                'disabled_at' => $data['status'] === 'active' ? null : ($variant->disabled_at ?: now()),
                'updated_by' => auth()->id(),
            ]);

            $delta = (int) $variant->stock - $oldStock;
            if ($delta !== 0) {
                InventoryMovement::create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $delta,
                    'type' => 'adjustment',
                    'reference' => 'VARIANT-MANAGER',
                    'note' => 'Variant stock updated from management dashboard',
                ]);
            }

            AuditTrail::record('variant.updated', $variant, $before, $variant->fresh()->toArray());
        });

        return redirect()->route('admin.variants.index', ['selected' => $variant->public_uuid])
            ->with('success', 'Variant updated successfully.');
    }

    public function bulkCreate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'colours' => ['nullable', 'string', 'max:3000'],
            'sizes' => ['nullable', 'string', 'max:3000'],
            'materials' => ['nullable', 'string', 'max:3000'],
            'styles' => ['nullable', 'string', 'max:3000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'compare_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000000'],
            'stock_total' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        $product = Product::query()->findOrFail((int) $data['product_id']);
        $sets = [
            'colour' => $this->csvValues($data['colours'] ?? ''),
            'size' => $this->csvValues($data['sizes'] ?? ''),
            'material' => $this->csvValues($data['materials'] ?? ''),
            'style' => $this->csvValues($data['styles'] ?? ''),
        ];
        foreach ($sets as $key => $values) {
            if ($values === []) {
                $sets[$key] = [null];
            }
        }

        $count = array_product(array_map('count', $sets));
        if ($count > 500) {
            throw ValidationException::withMessages(['combinations' => 'A bulk create is limited to 500 combinations at a time.']);
        }

        $created = 0;
        DB::transaction(function () use ($sets, $data, $product, &$created): void {
            foreach ($sets['colour'] as $colour) {
                foreach ($sets['size'] as $size) {
                    foreach ($sets['material'] as $material) {
                        foreach ($sets['style'] as $style) {
                            $payload = [
                                'colour' => $colour,
                                'size' => $size,
                                'material' => $material,
                                'style' => $style,
                            ];
                            $sku = $this->generateSku($product, $payload);

                            if (ProductVariant::query()->where('sku', $sku)->exists()) {
                                continue;
                            }

                            $variant = ProductVariant::create([
                                'product_id' => $product->id,
                                'sku' => $sku,
                                ...$payload,
                                'price' => $data['price'] ?? $product->price,
                                'compare_price' => $data['compare_price'] ?? $product->compare_price,
                                'stock' => (int) $data['stock'],
                                'stock_total' => max((int) ($data['stock_total'] ?? 0), (int) $data['stock']),
                                'status' => 'active',
                                'is_active' => true,
                                'low_stock_threshold' => VariantSetting::current()->low_stock_threshold,
                                'created_by' => auth()->id(),
                                'updated_by' => auth()->id(),
                            ]);
                            AuditTrail::record('variant.bulk.created', $variant, null, $variant->toArray());
                            $created++;
                        }
                    }
                }
            }
        });

        return back()->with('success', $created.' variants created from option combinations.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variants' => ['required', 'array', 'min:1'],
            'variants.*' => ['required', 'uuid'],
            'action' => ['required', Rule::in(['activate', 'deactivate', 'discontinue', 'set_stock', 'delete'])],
            'stock' => ['nullable', 'required_if:action,set_stock', 'integer', 'min:0', 'max:100000000'],
            'stock_total' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        $variants = $this->scopedVariants()
            ->whereIn('public_uuid', array_unique($data['variants']))
            ->get();

        if ($variants->count() !== count(array_unique($data['variants']))) {
            throw ValidationException::withMessages(['variants' => 'One or more selected variants are unavailable.']);
        }

        DB::transaction(function () use ($variants, $data): void {
            foreach ($variants as $variant) {
                $before = $variant->toArray();

                if ($data['action'] === 'delete') {
                    AuditTrail::record('variant.bulk.deleted', $variant, $before, null);
                    $variant->delete();
                    continue;
                }

                $changes = ['updated_by' => auth()->id()];
                if ($data['action'] === 'activate') {
                    $changes += ['status' => 'active', 'is_active' => true, 'disabled_at' => null];
                } elseif ($data['action'] === 'deactivate') {
                    $changes += ['status' => 'inactive', 'is_active' => false, 'disabled_at' => now()];
                } elseif ($data['action'] === 'discontinue') {
                    $changes += ['status' => 'discontinued', 'is_active' => false, 'disabled_at' => now()];
                } elseif ($data['action'] === 'set_stock') {
                    $oldStock = (int) $variant->stock;
                    $changes['stock'] = (int) $data['stock'];
                    $changes['stock_total'] = max((int) ($data['stock_total'] ?? 0), (int) $data['stock']);

                    $delta = $changes['stock'] - $oldStock;
                    if ($delta !== 0) {
                        InventoryMovement::create([
                            'product_id' => $variant->product_id,
                            'product_variant_id' => $variant->id,
                            'quantity' => $delta,
                            'type' => 'adjustment',
                            'reference' => 'VARIANT-BULK',
                            'note' => 'Bulk stock update from variant dashboard',
                        ]);
                    }
                }

                $variant->update($changes);
                AuditTrail::record('variant.bulk.updated', $variant, $before, $variant->fresh()->toArray());
            }
        });

        return back()->with('success', $variants->count().' variants processed.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'auto_generate_sku' => ['nullable', 'boolean'],
            'auto_manage_stock' => ['nullable', 'boolean'],
            'sync_variant_stock' => ['nullable', 'boolean'],
            'track_variant_inventory' => ['nullable', 'boolean'],
            'price_rounding' => ['nullable', 'integer', Rule::in([0, 2])],
            'inventory_policy' => ['nullable', Rule::in(['deny', 'allow'])],
            'backorder' => ['nullable', 'boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $setting = VariantSetting::current(true);
        $before = $setting->exists ? $setting->toArray() : null;
        $setting->fill([
            'auto_generate_sku' => $request->boolean('auto_generate_sku', $setting->auto_generate_sku),
            'auto_manage_stock' => $request->boolean('auto_manage_stock', $setting->auto_manage_stock),
            'sync_variant_stock' => $request->boolean('sync_variant_stock', $setting->sync_variant_stock),
            'track_variant_inventory' => $request->boolean('track_variant_inventory', $setting->track_variant_inventory),
            'price_rounding' => $data['price_rounding'] ?? $setting->price_rounding,
            'inventory_policy' => $data['inventory_policy'] ?? $setting->inventory_policy,
            'backorder' => $request->boolean('backorder', $setting->backorder),
            'low_stock_threshold' => $data['low_stock_threshold'] ?? $setting->low_stock_threshold,
            'updated_by' => auth()->id(),
        ]);
        $setting->save();

        AuditTrail::record('variant.settings.updated', $setting, $before, $setting->fresh()->toArray());

        return back()->with('success', 'Variant settings saved.');
    }

    public function storeMedia(Request $request, ProductVariant $variant): RedirectResponse
    {
        $this->guardTenant($variant);
        $data = $request->validate([
            'type' => ['required', Rule::in(self::MEDIA_TYPES)],
            'file' => ['required', 'file', 'max:102400', 'mimetypes:image/jpeg,image/png,image/webp,image/avif,video/mp4,video/webm,video/quicktime'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $disk = 'public';
        $path = $request->file('file')->store('variant-media/'.$variant->id, $disk);
        $media = $variant->media()->create([
            'type' => $data['type'],
            'disk' => $disk,
            'path' => $path,
            'alt_text' => $data['alt_text'] ?? null,
            'sort_order' => ((int) $variant->media()->max('sort_order')) + 1,
            'active' => true,
        ]);

        if ($data['type'] === 'image' && blank($variant->image)) {
            $variant->update(['image' => $path, 'updated_by' => auth()->id()]);
        }

        AuditTrail::record('variant.media.created', $media, null, $media->toArray());

        return redirect()->route('admin.variants.index', ['selected' => $variant->public_uuid])
            ->with('success', 'Variant media uploaded.');
    }

    public function destroyMedia(ProductVariant $variant, VariantMedia $media): RedirectResponse
    {
        $this->guardTenant($variant);
        abort_unless($media->product_variant_id === $variant->id, 404);

        $before = $media->toArray();
        if ($media->disk === 'public' && Storage::disk('public')->exists($media->path)) {
            Storage::disk('public')->delete($media->path);
        }
        AuditTrail::record('variant.media.deleted', $media, $before, null);
        $media->delete();

        return back()->with('success', 'Variant media removed.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:4096']]);
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
        if (array_diff(['product_sku', 'sku'], $headers)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'CSV headers must include product_sku and sku.']);
        }

        $imported = 0;
        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($headers)) {
                    throw ValidationException::withMessages(['file' => 'A CSV row has the wrong number of columns.']);
                }
                $record = array_combine($headers, $row);
                $product = Product::query()->where('sku', trim((string) $record['product_sku']))->first();
                if (! $product) {
                    throw ValidationException::withMessages(['file' => 'CSV references an unknown product SKU: '.($record['product_sku'] ?? '')]);
                }

                $sku = trim((string) $record['sku']);
                if ($sku === '') {
                    throw ValidationException::withMessages(['file' => 'Every imported variant requires a SKU.']);
                }

                $variant = ProductVariant::query()->where('sku', $sku)->first();
                if ($variant && ! Product::query()->whereKey($variant->product_id)->exists()) {
                    abort(403);
                }

                $before = $variant?->toArray();
                $status = strtolower(trim((string) ($record['status'] ?? 'active')));
                if (! in_array($status, self::STATUSES, true)) {
                    $status = 'active';
                }

                $values = [
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'barcode' => filled($record['barcode'] ?? null) ? trim((string) $record['barcode']) : null,
                    'colour' => filled($record['colour'] ?? null) ? trim((string) $record['colour']) : null,
                    'size' => filled($record['size'] ?? null) ? trim((string) $record['size']) : null,
                    'material' => filled($record['material'] ?? null) ? trim((string) $record['material']) : null,
                    'style' => filled($record['style'] ?? null) ? trim((string) $record['style']) : null,
                    'price' => is_numeric($record['price'] ?? null) ? (float) $record['price'] : $product->price,
                    'compare_price' => is_numeric($record['compare_price'] ?? null) ? (float) $record['compare_price'] : null,
                    'stock' => max(0, (int) ($record['stock'] ?? 0)),
                    'stock_total' => max(0, (int) ($record['stock_total'] ?? ($record['stock'] ?? 0))),
                    'status' => $status,
                    'is_active' => $status === 'active',
                    'low_stock_threshold' => max(0, (int) ($record['low_stock_threshold'] ?? VariantSetting::current()->low_stock_threshold)),
                    'updated_by' => auth()->id(),
                ];

                if ($variant) {
                    $variant->update($values);
                    AuditTrail::record('variant.import.updated', $variant, $before, $variant->fresh()->toArray());
                } else {
                    $variant = ProductVariant::create([...$values, 'created_by' => auth()->id()]);
                    AuditTrail::record('variant.import.created', $variant, null, $variant->toArray());
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

        return back()->with('success', $imported.' variants imported or updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $report = (string) $request->query('report', 'variants');
        $filename = 'emerald-rozalia-variant-'.$report.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'wb');

            if ($report === 'stock') {
                fputcsv($output, ['product_sku', 'variant_sku', 'barcode', 'stock_available', 'stock_total', 'low_stock_threshold', 'status']);
            } elseif ($report === 'combinations') {
                fputcsv($output, ['product_sku', 'variant_sku', 'colour', 'size', 'material', 'style', 'status']);
            } else {
                fputcsv($output, ['product_sku', 'sku', 'barcode', 'colour', 'size', 'material', 'style', 'price', 'compare_price', 'stock', 'stock_total', 'status', 'low_stock_threshold', 'uuid']);
            }

            $this->scopedVariants()
                ->with('product')
                ->orderBy('product_id')
                ->orderBy('id')
                ->chunk(250, function ($variants) use ($output, $report): void {
                    foreach ($variants as $variant) {
                        if ($report === 'stock') {
                            fputcsv($output, [$variant->product?->sku, $variant->sku, $variant->barcode, $variant->stock, $variant->stock_total, $variant->low_stock_threshold, $variant->status]);
                        } elseif ($report === 'combinations') {
                            fputcsv($output, [$variant->product?->sku, $variant->sku, $variant->colour, $variant->size, $variant->material, $variant->style, $variant->status]);
                        } else {
                            fputcsv($output, [$variant->product?->sku, $variant->sku, $variant->barcode, $variant->colour, $variant->size, $variant->material, $variant->style, $variant->price, $variant->compare_price, $variant->stock, $variant->stock_total, $variant->status, $variant->low_stock_threshold, $variant->public_uuid]);
                        }
                    }
                });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function audit(ProductVariant $variant): View
    {
        $this->guardTenant($variant);
        $logs = AuditLog::query()
            ->where('subject_type', $variant->getMorphClass())
            ->where('subject_id', $variant->getKey())
            ->latest('created_at')
            ->paginate(30);

        return view('admin.variants.audit', compact('variant', 'logs'));
    }

    public function destroy(ProductVariant $variant): RedirectResponse
    {
        $this->guardTenant($variant);
        $before = $variant->toArray();
        AuditTrail::record('variant.deleted', $variant, $before, null);
        $variant->delete();

        return redirect()->route('admin.variants.index')->with('success', 'Variant deleted.');
    }

    private function validatedVariant(Request $request, ?ProductVariant $variant = null): array
    {
        return $request->validate([
            'product_id' => ['required', 'integer'],
            'sku' => ['nullable', 'string', 'max:120', Rule::unique('product_variants', 'sku')->ignore($variant?->id)],
            'barcode' => ['nullable', 'string', 'max:120', Rule::unique('product_variants', 'barcode')->ignore($variant?->id)],
            'colour' => ['nullable', 'string', 'max:120'],
            'size' => ['nullable', 'string', 'max:120'],
            'material' => ['nullable', 'string', 'max:120'],
            'style' => ['nullable', 'string', 'max:120'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'compare_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000000'],
            'stock_total' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'track_inventory' => ['nullable', 'boolean'],
            'backorder' => ['nullable', 'boolean'],
        ]);
    }

    private function scopedVariants()
    {
        return ProductVariant::query()->whereHas('product');
    }

    private function guardTenant(ProductVariant $variant): void
    {
        abort_unless(Product::query()->whereKey($variant->product_id)->exists(), 404);
    }

    private function csvValues(?string $value): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($item) => trim((string) $item), preg_split('/[\r\n,]+/', (string) $value) ?: []),
            static fn ($item) => $item !== ''
        )));
    }

    private function generateSku(Product $product, array $data): string
    {
        $parts = collect([
            $data['colour'] ?? null,
            $data['size'] ?? null,
            $data['material'] ?? null,
            $data['style'] ?? null,
        ])->filter()->map(fn ($value) => strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', (string) $value), 0, 5)))->filter();

        $base = strtoupper(Str::limit(preg_replace('/[^A-Za-z0-9-]+/', '-', $product->sku), 45, ''));
        $suffix = $parts->isEmpty() ? 'VAR' : $parts->implode('-');
        $candidate = trim($base.'-'.$suffix, '-');
        $counter = 2;

        while (ProductVariant::query()->where('sku', $candidate)->exists()) {
            $candidate = trim($base.'-'.$suffix.'-'.$counter, '-');
            $counter++;
        }

        return Str::limit($candidate, 120, '');
    }

    private function optionOverview(): array
    {
        $query = $this->scopedVariants();
        $sets = [];
        $totalUses = 0;

        foreach (['colour' => 'Colour', 'size' => 'Size', 'material' => 'Material', 'style' => 'Style'] as $column => $label) {
            $uses = (clone $query)->whereNotNull($column)->where($column, '!=', '')->count();
            $values = (clone $query)->whereNotNull($column)->where($column, '!=', '')->distinct()->count($column);
            $sets[$column] = ['label' => $label, 'uses' => $uses, 'values' => $values];
            $totalUses += $uses;
        }

        foreach ($sets as $key => $set) {
            $sets[$key]['percent'] = $totalUses > 0 ? round(($set['uses'] / $totalUses) * 100, 1) : 0;
        }

        $valueCounts = array_map(fn ($set) => $set['values'], $sets);
        $possible = 1;
        foreach ($valueCounts as $count) {
            $possible *= max(1, $count);
        }

        return [
            'sets' => $sets,
            'total_sets' => count(array_filter($sets, fn ($set) => $set['values'] > 0)),
            'total_values' => array_sum($valueCounts),
            'combinations_possible' => $possible,
            'combinations_created' => $this->scopedVariants()->count(),
        ];
    }
}
