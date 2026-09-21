<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Support\CatalogCounties;
use App\Support\CatalogStyles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AddProductController extends Controller
{
    private const CHANNELS = ['website', 'franchise', 'franchise_retail', 'corporate_bulk', 'buyer'];
    private const ORDER_CATEGORIES = ['online', 'corporate', 'bulk', 'franchise', 'franchise_retail', 'buyer'];
    private const REQUIRED_CLUB_TAXONOMIES = ['gaa', 'english', 'uefa', 'fifa'];
    private const REQUIRED_COUNTY_TAXONOMIES = ['gaa', 'english', 'uefa', 'fifa'];

    public function create(): View
    {
        Gate::authorize('create', Product::class);
        return view('admin.add-product', [
            ...$this->classificationData(),
            'collections' => $this->collections(),
            'product' => null,
        ]);
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);
        $product->load('collections');

        return view('admin.add-product', [
            ...$this->classificationData($product),
            'collections' => $this->collections(),
            'product' => $product,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Product::class);
        $data = $this->validated($request);
        $product = $this->saveProduct($request, $data);

        return $this->afterSave($request, $product, 'Product created successfully.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);
        $data = $this->validated($request, $product);
        $this->saveProduct($request, $data, $product);

        return $this->afterSave($request, $product, 'Product updated successfully.');
    }

    private function classificationData(?Product $product = null): array
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->with(['children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'taxonomy_type', 'product_type']);

        $byId = $categories->keyBy('id');
        $subcategoryOptionsByRoot = [];
        foreach ($categories as $category) {
            $cursor = $category;
            $segments = [$category->name];

            while ($cursor->parent_id && $byId->has($cursor->parent_id)) {
                $cursor = $byId->get($cursor->parent_id);
                array_unshift($segments, $cursor->name);
            }

            if (! $cursor || $cursor->parent_id !== null) {
                continue;
            }

            $rootId = (int) $cursor->id;
            $label = (int) $category->id === $rootId
                ? 'Main category only'
                : implode(' › ', array_slice($segments, 1));

            $subcategoryOptionsByRoot[$rootId][] = [
                'id' => (int) $category->id,
                'label' => $label,
                'product_type' => $category->product_type,
            ];
        }

        return [
            'categories' => $categories,
            'categoryRoots' => $categories->whereNull('parent_id')->values(),
            'subcategoryOptionsByRoot' => $subcategoryOptionsByRoot,
            'catalogCountries' => CatalogCountry::query()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name']),
            'catalogCountyOptionsByCountry' => CatalogCounties::all(),
            'catalogClubs' => $this->selectedCatalogClubs($product),
            'catalogStyles' => CatalogStyles::all(),
            'clubRequiredTaxonomies' => self::REQUIRED_CLUB_TAXONOMIES,
            'countyRequiredTaxonomies' => self::REQUIRED_COUNTY_TAXONOMIES,
        ];
    }

    private function selectedCatalogClubs(?Product $product = null)
    {
        $selectedClubId = (int) (
            session()->getOldInput('catalog_club_id')
            ?: data_get($product?->product_metadata, 'catalog_classification.catalog_club_id')
            ?: 0
        );

        if ($selectedClubId <= 0) {
            return collect();
        }

        return CatalogClub::query()
            ->active()
            ->with('country:id,code,name')
            ->whereKey($selectedClubId)
            ->get();
    }

    private function collections()
    {
        return ProductCollection::query()
            ->orderByRaw("CASE WHEN slug = 'best-sellers' THEN 0 WHEN slug = 'irish-heritage' THEN 1 ELSE 2 END")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'status', 'visibility']);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'short_description' => ['required', 'string', 'max:1500'],
            'slug' => ['nullable', 'string', 'max:180'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product?->id)],
            'category_root_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'catalog_country_id' => ['nullable', 'integer', 'exists:catalog_countries,id'],
            'catalog_county_code' => ['nullable', 'string', 'max:16'],
            'catalog_club_id' => ['nullable', 'integer', 'exists:catalog_clubs,id'],
            'catalog_style' => ['nullable', Rule::in(array_keys(CatalogStyles::all()))],
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
            'minimum_order_quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
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
            'is_new_arrival' => ['nullable', 'boolean'],
            'collection_ids_present' => ['nullable', 'boolean'],
            'collection_ids' => ['nullable', 'array'],
            'collection_ids.*' => ['integer', 'distinct'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(self::CHANNELS)],
            'order_categories' => ['nullable', 'array'],
            'order_categories.*' => [Rule::in(self::ORDER_CATEGORIES)],
            'save_action' => ['required', Rule::in(['draft', 'media', 'save'])],
        ]);

        $slug = Str::slug($data['slug'] ?: $data['name']);
        $slugRule = Rule::unique('products', 'slug');
        if ($product) {
            $slugRule->ignore($product->id);
        }
        validator(['slug' => $slug], ['slug' => ['required', 'string', 'max:180', $slugRule]])->validate();
        $data['slug'] = $slug;
        $data['collection_ids'] = array_values(array_map('intval', $data['collection_ids'] ?? []));

        if (! empty($data['category_root_id'])) {
            $selectedCategory = Category::query()->findOrFail((int) $data['category_id']);
            $cursor = $selectedCategory;
            while ($cursor->parent_id) {
                $cursor = Category::query()->findOrFail((int) $cursor->parent_id);
            }
            if ((int) $cursor->id !== (int) $data['category_root_id']) {
                throw ValidationException::withMessages([
                    'category_id' => 'The selected subcategory does not belong to the selected category.',
                ]);
            }
        }

        $rootCategory = ! empty($data['category_root_id'])
            ? Category::query()->find((int) $data['category_root_id'])
            : null;
        $rootTaxonomy = strtolower((string) ($rootCategory?->taxonomy_type ?? $rootCategory?->slug ?? ''));

        if (in_array($rootTaxonomy, self::REQUIRED_CLUB_TAXONOMIES, true) && empty($data['catalog_country_id'])) {
            throw ValidationException::withMessages([
                'catalog_country_id' => 'Select a country before selecting the '.strtoupper($rootTaxonomy).' club / city / town.',
            ]);
        }

        if (in_array($rootTaxonomy, self::REQUIRED_COUNTY_TAXONOMIES, true) && empty($data['catalog_county_code'])) {
            throw ValidationException::withMessages([
                'catalog_county_code' => 'Select a county before selecting the '.strtoupper($rootTaxonomy).' club.',
            ]);
        }

        if (! empty($data['catalog_county_code'])) {
            $country = ! empty($data['catalog_country_id'])
                ? CatalogCountry::query()->find((int) $data['catalog_country_id'])
                : null;
            if (! $country || ! CatalogCounties::isValid($country->code, $data['catalog_county_code'])) {
                throw ValidationException::withMessages([
                    'catalog_county_code' => 'Select a county that belongs to the selected country.',
                ]);
            }
        }

        if (in_array($rootTaxonomy, self::REQUIRED_CLUB_TAXONOMIES, true) && empty($data['catalog_club_id'])) {
            throw ValidationException::withMessages([
                'catalog_club_id' => 'Select a '.strtoupper($rootTaxonomy).' club / city / town.',
            ]);
        }

        if (! empty($data['catalog_club_id'])) {
            $club = CatalogClub::query()->find((int) $data['catalog_club_id']);
            $wrongCountry = ! empty($data['catalog_country_id'])
                && (int) $club?->catalog_country_id !== (int) $data['catalog_country_id'];
            $wrongOrganization = $rootTaxonomy !== ''
                && in_array($rootTaxonomy, self::REQUIRED_CLUB_TAXONOMIES, true)
                && $club?->governing_body !== $rootTaxonomy;
            $clubCounty = strtoupper((string) $club?->catalog_county_code);
            $selectedCounty = strtoupper((string) ($data['catalog_county_code'] ?? ''));
            $countryWideFifaClub = $rootTaxonomy === 'fifa' && $clubCounty === '';
            $wrongCounty = $selectedCounty !== ''
                && ! $countryWideFifaClub
                && $clubCounty !== $selectedCounty;

            if (! $club || $wrongCountry || $wrongOrganization || $wrongCounty) {
                throw ValidationException::withMessages([
                    'catalog_club_id' => 'Select a club that belongs to the selected category, country and county.',
                ]);
            }
        }

        if ($data['collection_ids'] !== []) {
            $availableCollectionIds = $this->collections()
                ->whereIn('id', $data['collection_ids'])
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            if (array_diff($data['collection_ids'], $availableCollectionIds) !== []) {
                throw ValidationException::withMessages([
                    'collection_ids' => 'Select only collections available to your company.',
                ]);
            }
        }

        return $data;
    }

    private function saveProduct(Request $request, array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($request, $data, $product): Product {
            $action = $data['save_action'];
            $status = $action === 'draft' ? 'draft' : $data['status'];
            $publishedWebsite = $status !== 'draft' && $request->boolean('published_website');
            $metadata = [
                'short_description' => $data['short_description'],
                'tags' => $this->tags($data['tags'] ?? null),
                'product_type' => $data['product_type'],
                'tax_class' => $data['tax_class'],
                'cost_price' => $data['cost_price'] ?? null,
                'vat_rate' => $data['vat_rate'],
                'currency' => $data['currency'],
                'minimum_order_quantity' => $data['minimum_order_quantity'] ?? null,
                'catalog_classification' => [
                    'category_root_id' => isset($data['category_root_id']) ? (int) $data['category_root_id'] : null,
                    'catalog_country_id' => isset($data['catalog_country_id']) ? (int) $data['catalog_country_id'] : null,
                    'catalog_county_code' => $data['catalog_county_code'] ?? null,
                    'catalog_club_id' => isset($data['catalog_club_id']) ? (int) $data['catalog_club_id'] : null,
                    'style' => $data['catalog_style'] ?? null,
                ],
                'dimensions' => ['length' => $data['length'] ?? null, 'width' => $data['width'] ?? null, 'height' => $data['height'] ?? null],
                'channels' => array_values($data['channels'] ?? []),
                'order_categories' => array_values($data['order_categories'] ?? []),
                'published_website' => $publishedWebsite,
                'available_for_sale' => $request->boolean('available_for_sale'),
            ];
            $attributes = [
                'category_id' => $data['category_id'], 'name' => $data['name'], 'slug' => $data['slug'], 'sku' => $data['sku'],
                'description' => $data['description'], 'price' => $data['price'], 'compare_price' => $data['compare_price'] ?? null,
                'stock' => $data['stock'], 'material' => $data['material'] ?? null, 'brand' => $data['brand'] ?? null, 'care' => $data['care'] ?? null,
                'meta_title' => $data['meta_title'] ?? null, 'meta_description' => $data['meta_description'] ?? null, 'weight' => $data['weight'] ?? null,
                'hs_code' => $data['hs_code'] ?? null,
                'is_new' => $request->boolean('is_new_arrival', $request->boolean('featured')),
                'is_active' => $publishedWebsite && $request->boolean('available_for_sale'),
                'status' => $status, 'product_metadata' => $metadata,
                'published_at' => $publishedWebsite && filled($data['publish_date'] ?? null) ? Carbon::parse($data['publish_date']) : null,
            ];

            if ($product) {
                $product->update($attributes);
                $product = $product->fresh();
            } else {
                $product = Product::create($attributes);
            }

            if ($request->boolean('collection_ids_present')) {
                $product->collections()->sync($data['collection_ids'] ?? []);
            }

            return $product;
        });
    }

    private function afterSave(Request $request, Product $product, string $message): RedirectResponse
    {
        if ($request->input('save_action') === 'media') {
            return redirect()->route('admin.media.index', ['product_id' => $product->id])->with('success', $message.' Add images, video and 360° media next.');
        }

        return redirect()->route('admin.resource', 'product-manager')->with('success', $message);
    }

    private function tags(?string $tags): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $tag): string => trim($tag),
            explode(',', (string) $tags),
        ))));
    }
}
