<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Product, ProductMedia};
use App\Services\AuditTrail;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ImageManagerController extends Controller
{
    private const ROLES = [
        'primary',
        'additional',
        'lifestyle',
        'detail',
        'swatch',
        'infographic',
        'packaging',
    ];

    private const ORIENTATIONS = ['square', 'landscape', 'portrait'];

    public function index(Request $request)
    {
        $products = Product::query()
            ->where('is_active', true)
            ->withCount('media')
            ->orderBy('name')
            ->get();
        $productIds = $products->modelKeys();
        $allImages = ProductMedia::query()
            ->with('product')
            ->where('type', 'image')
            ->whereIn('product_id', $productIds)
            ->orderBy('id')
            ->get();

        $productId = (int) $request->query('product_id', 0);
        $selectedProduct = $products->firstWhere('id', $productId);
        $tab = (string) $request->query('tab', 'all');
        if (! in_array($tab, array_merge(['all'], self::ROLES), true)) {
            $tab = 'all';
        }
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['', 'active', 'inactive'], true)) {
            $status = '';
        }
        $orientation = (string) $request->query('orientation', '');
        if (! in_array($orientation, array_merge([''], self::ORIENTATIONS), true)) {
            $orientation = '';
        }
        $search = trim((string) $request->query('q', ''));
        $sort = (string) $request->query('sort', 'newest');
        if (! in_array($sort, ['newest', 'oldest', 'order', 'name'], true)) {
            $sort = 'newest';
        }

        $filtered = $allImages;
        if ($selectedProduct) {
            $filtered = $filtered->where('product_id', $selectedProduct->id);
        }
        if ($tab !== 'all') {
            $filtered = $filtered->filter(fn (ProductMedia $media): bool => $this->roleFor($media) === $tab);
        }
        if ($status === 'active') {
            $filtered = $filtered->where('active', true);
        } elseif ($status === 'inactive') {
            $filtered = $filtered->where('active', false);
        }
        if ($orientation !== '') {
            $filtered = $filtered->filter(fn (ProductMedia $media): bool => $this->orientationFor($media) === $orientation);
        }
        if ($search !== '') {
            $needle = strtolower($search);
            $filtered = $filtered->filter(function (ProductMedia $media) use ($needle): bool {
                $haystack = strtolower(implode(' ', [
                    basename($media->path),
                    (string) $media->path,
                    (string) $media->alt_text,
                    (string) $media->product?->name,
                    (string) $media->product?->sku,
                    $this->roleFor($media),
                ]));

                return str_contains($haystack, $needle);
            });
        }

        $filtered = $filtered->values();
        $filtered = match ($sort) {
            'oldest' => $filtered->sortBy(fn (ProductMedia $media): int => optional($media->created_at)->getTimestamp() ?: 0)->values(),
            'order' => $filtered->sortBy(fn (ProductMedia $media): array => [$media->sort_order, $media->id])->values(),
            'name' => $filtered->sortBy(fn (ProductMedia $media): string => strtolower(basename($media->path)))->values(),
            default => $filtered->sortByDesc(fn (ProductMedia $media): int => optional($media->created_at)->getTimestamp() ?: 0)->values(),
        };

        $perPage = 8;
        $page = max(1, (int) $request->query('page', 1));
        $media = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $sizes = $allImages
            ->map(fn (ProductMedia $media): int => $this->sizeBytesFor($media))
            ->filter(fn (int $size): bool => $size > 0);
        $roleCounts = array_fill_keys(self::ROLES, 0);
        foreach ($allImages as $image) {
            $roleCounts[$this->roleFor($image)]++;
        }
        $stats = [
            'total' => $allImages->count(),
            'products' => $allImages->pluck('product_id')->unique()->count(),
            'primary' => $roleCounts['primary'],
            'additional' => $roleCounts['additional'],
            'average_size' => $sizes->count() ? (int) round($sizes->average()) : 0,
            'storage_bytes' => (int) $sizes->sum(),
            'active' => $allImages->where('active', true)->count(),
            'inactive' => $allImages->where('active', false)->count(),
        ];
        $selectedMediaId = (int) $request->query('selected_media_id', 0);
        $selectedImage = $selectedMediaId ? $allImages->firstWhere('id', $selectedMediaId) : null;
        $selectedImage ??= $filtered->first() ?: $allImages->first();
        $filterQuery = array_filter([
            'product_id' => $selectedProduct?->id,
            'q' => $search ?: null,
            'status' => $status ?: null,
            'orientation' => $orientation ?: null,
            'sort' => $sort === 'newest' ? null : $sort,
        ], fn ($value) => $value !== null && $value !== '');

        $performance = [
            'views' => (int) $allImages->sum(fn (ProductMedia $media): int => (int) data_get($media->metadata, 'views', 0)),
            'clicks' => (int) $allImages->sum(fn (ProductMedia $media): int => (int) data_get($media->metadata, 'zoom_clicks', 0)),
            'engagement' => (float) ($allImages->avg(fn (ProductMedia $media): float => (float) data_get($media->metadata, 'engagement', 0)) ?: 0),
        ];

        return view('admin.images.index', compact(
            'products',
            'selectedProduct',
            'media',
            'allImages',
            'selectedImage',
            'stats',
            'roleCounts',
            'performance',
            'tab',
            'status',
            'orientation',
            'search',
            'sort',
            'filterQuery'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'files' => 'required|array|min:1|max:20',
            'files.*' => 'required|file|image|max:20480|mimes:jpg,jpeg,png,webp,avif',
            'image_role' => ['required', Rule::in(self::ROLES)],
            'alt_text' => 'nullable|string|max:255',
        ]);
        $product = Product::query()->whereKey($data['product_id'])->where('is_active', true)->firstOrFail();
        $role = $data['image_role'];
        $nextOrder = ((int) ProductMedia::query()->where('product_id', $product->id)->where('type', 'image')->max('sort_order')) + 1;
        $created = [];

        foreach ($request->file('files', []) as $index => $file) {
            $path = $file->store('product-media/'.$product->id, 'public');
            $dimensions = @getimagesize($file->getRealPath());
            $width = (int) ($dimensions[0] ?? 0);
            $height = (int) ($dimensions[1] ?? 0);
            $sizeBytes = (int) ($file->getSize() ?: 0);
            $metadata = [
                'image_role' => $role,
                'orientation' => $this->orientationForDimensions($width, $height),
                'width' => $width ?: null,
                'height' => $height ?: null,
                'size_bytes' => $sizeBytes,
                'size' => $this->formatBytes($sizeBytes),
                'format' => strtoupper($file->getClientOriginalExtension()),
            ];
            $media = ProductMedia::create([
                'product_id' => $product->id,
                'type' => 'image',
                'disk' => 'public',
                'path' => $path,
                'alt_text' => $data['alt_text'] ?? null,
                'sort_order' => $role === 'primary' && $index === 0 ? 0 : $nextOrder + $index,
                'metadata' => $metadata,
                'active' => true,
            ]);
            AuditTrail::record('image.created', $media, null, $media->toArray());
            $created[] = $media;
        }

        $selected = end($created);

        return redirect()->route('admin.images.index', [
            'product_id' => $product->id,
            'selected_media_id' => $selected?->id,
        ])->with('success', count($created).' image'.(count($created) === 1 ? '' : 's').' uploaded.');
    }

    public function update(Request $request, ProductMedia $media)
    {
        abort_unless($media->type === 'image', 404);
        $data = $request->validate([
            'image_role' => ['required', Rule::in(self::ROLES)],
            'alt_text' => 'nullable|string|max:255',
            'sort_order' => 'required|integer|min:0|max:10000',
            'active' => 'nullable|boolean',
        ]);
        $before = $media->toArray();
        $metadata = is_array($media->metadata) ? $media->metadata : [];
        $metadata['image_role'] = $data['image_role'];
        $media->update([
            'alt_text' => $data['alt_text'] ?? null,
            'sort_order' => $data['sort_order'],
            'active' => (bool) ($data['active'] ?? false),
            'metadata' => $metadata,
        ]);
        AuditTrail::record('image.updated', $media, $before, $media->fresh()->toArray());

        return redirect()->route('admin.images.index', [
            'product_id' => $media->product_id,
            'selected_media_id' => $media->id,
        ])->with('success', 'Image details updated.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
            'media_ids' => 'required|array|min:1',
            'media_ids.*' => 'integer',
        ]);
        $images = ProductMedia::query()->where('type', 'image')->whereIn('id', $data['media_ids'])->get();

        foreach ($images as $image) {
            $before = $image->toArray();
            if ($data['action'] === 'delete') {
                AuditTrail::record('image.deleted', $image, $before, null);
                $image->delete();
            } else {
                $image->update(['active' => $data['action'] === 'activate']);
                AuditTrail::record('image.updated', $image, $before, $image->fresh()->toArray());
            }
        }

        return back()->with('success', count($images).' selected image'.(count($images) === 1 ? '' : 's').' updated.');
    }

    public function destroy(ProductMedia $media)
    {
        abort_unless($media->type === 'image', 404);
        $productId = $media->product_id;
        $before = $media->toArray();
        AuditTrail::record('image.deleted', $media, $before, null);
        if ($media->disk === 'public' && Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }
        $media->delete();

        return redirect()->route('admin.images.index', ['product_id' => $productId])->with('success', 'Image removed.');
    }

    private function roleFor(ProductMedia $media): string
    {
        $role = (string) data_get($media->metadata, 'image_role', '');

        return in_array($role, self::ROLES, true) ? $role : ($media->sort_order === 0 ? 'primary' : 'additional');
    }

    private function orientationFor(ProductMedia $media): string
    {
        $orientation = (string) data_get($media->metadata, 'orientation', '');
        if (in_array($orientation, self::ORIENTATIONS, true)) {
            return $orientation;
        }

        return $this->orientationForDimensions(
            (int) data_get($media->metadata, 'width', 0),
            (int) data_get($media->metadata, 'height', 0)
        );
    }

    private function orientationForDimensions(int $width, int $height): string
    {
        if ($width <= 0 || $height <= 0 || $width === $height) {
            return 'square';
        }

        return $width > $height ? 'landscape' : 'portrait';
    }

    private function sizeBytesFor(ProductMedia $media): int
    {
        $bytes = (int) data_get($media->metadata, 'size_bytes', 0);
        if ($bytes > 0) {
            return $bytes;
        }

        return is_numeric(data_get($media->metadata, 'size')) ? (int) data_get($media->metadata, 'size') : 0;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return 'Size pending';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0).' KB';
        }

        return $bytes.' B';
    }
}
