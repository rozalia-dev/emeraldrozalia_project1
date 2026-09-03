<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AddProductController extends Controller
{
    private const CHANNELS = [
        'website',
        'franchise_portal',
        'franchise_retail',
        'corporate_bulk',
        'buyer',
    ];

    private const ORDER_CATEGORIES = [
        'online',
        'corporate',
        'bulk',
        'franchise',
        'franchise_retail',
        'buyer',
    ];

    public function create(): View
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.add-product', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'short_description' => ['required', 'string', 'max:1500'],
            'slug' => ['nullable', 'string', 'max:180'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'string', 'max:500'],
            'product_type' => ['required', Rule::in(['simple', 'variable', 'bundle'])],
            'tax_class' => ['required', Rule::in(['standard', 'reduced', 'zero'])],
            'hs_code' => ['nullable', 'string', 'max:30'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'description' => ['required', 'string', 'max:12000'],
            'material' => ['nullable', 'string', 'max:180'],
            'care' => ['nullable', 'string', 'max:1000'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'currency' => ['required', Rule::in(['EUR', 'GBP', 'USD'])],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'active'])],
            'publish_date' => ['nullable', 'date'],
            'published_website' => ['nullable', 'boolean'],
            'available_for_sale' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(self::CHANNELS)],
            'order_categories' => ['nullable', 'array'],
            'order_categories.*' => [Rule::in(self::ORDER_CATEGORIES)],
            'save_action' => ['required', Rule::in(['draft', 'media', 'save'])],
        ]);

        $slug = Str::slug($data['slug'] ?: $data['name']);
        Validator::make(
            ['slug' => $slug],
            ['slug' => ['required', 'string', 'max:180', Rule::unique('products', 'slug')]],
        )->validate();

        $action = $data['save_action'];
        $status = $action === 'draft' ? 'draft' : $data['status'];
        $publishedWebsite = $status !== 'draft' && $request->boolean('published_website');
        $availableForSale = $request->boolean('available_for_sale');

        $metadata = [
            'short_description' => $data['short_description'],
            'tags' => $this->tags($data['tags'] ?? null),
            'product_type' => $data['product_type'],
            'tax_class' => $data['tax_class'],
            'cost_price' => $data['cost_price'] ?? null,
            'vat_rate' => $data['vat_rate'],
            'currency' => $data['currency'],
            'dimensions' => [
                'length' => $data['length'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
            ],
            'channels' => array_values($data['channels'] ?? []),
            'order_categories' => array_values($data['order_categories'] ?? []),
            'published_website' => $publishedWebsite,
            'available_for_sale' => $availableForSale,
        ];

        $product = Product::create([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'slug' => $slug,
            'sku' => $data['sku'],
            'description' => $data['description'],
            'price' => $data['price'],
            'compare_price' => $data['compare_price'] ?? null,
            'stock' => $data['stock'],
            'material' => $data['material'] ?? null,
            'brand' => $data['brand'] ?? null,
            'care' => $data['care'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'weight' => $data['weight'] ?? null,
            'hs_code' => $data['hs_code'] ?? null,
            'is_new' => $request->boolean('featured'),
            'is_active' => $publishedWebsite && $availableForSale,
            'status' => $status,
            'product_metadata' => $metadata,
            'published_at' => $publishedWebsite && ! empty($data['publish_date'])
                ? Carbon::parse($data['publish_date'])
                : null,
        ]);

        if ($action === 'media') {
            return redirect()
                ->route('admin.media.index', ['product_id' => $product->id])
                ->with('success', 'Product saved. Add images, video and 360° media next.');
        }

        return redirect()
            ->route('admin.resource', 'product-manager')
            ->with('success', $status === 'draft' ? 'Product draft saved.' : 'Product created successfully.');
    }

    private function tags(?string $tags): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $tag): string => trim($tag),
            explode(',', (string) $tags),
        ))));
    }
}
