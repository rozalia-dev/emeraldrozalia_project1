<?php

namespace App\Http\Controllers;

use App\Http\Requests\{CatalogFilterRequest, PublicInquiryRequest};
use App\Models\{Banner,CatalogClub,CatalogCountry,Category,ContentPage,Conversation,FranchiseApplication,FranchiseStore,Inquiry,Product,ProductCollection,ProductVideo};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\AuditTrail;
use App\Services\PublicMediaResolver;
use App\Services\SalesQuoteService;
use App\Support\CatalogCounties;
use App\Support\CatalogProductTypes;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class SiteController extends Controller
{
    public function home()
    {
        $data = $this->homeData();
        abort_unless($data['homepage'], 404, 'The public homepage is not currently published.');

        return view('site.home', $data);
    }

    public function homeData(): array
    {
        $mediaResolver = app(PublicMediaResolver::class);
        $homepage = ContentPage::with('sections')
            ->where('slug', 'home')
            ->where('route_path', '/')
            ->where('page_kind', 'home')
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now()))
            ->first();

        $homeProducts = Product::with('media')
            ->published()
            ->where('is_new', true)
            ->latest()
            ->limit(8)
            ->get();
        $bestSellerCollection = ProductCollection::query()
            ->where('slug', 'best-sellers')
            ->where('status', 'active')
            ->where('visibility', 'visible')
            ->first();
        $homeBestsellers = $bestSellerCollection
            ? $bestSellerCollection->products()
                ->published()
                ->with('media')
                ->orderBy('collection_product.sort_order')
                ->orderBy('products.name')
                ->limit(8)
                ->get()
            : $homeProducts;

        $homeLatestProducts = Product::with('media')
            ->published()
            ->latest()
            ->limit(8)
            ->get();

        $banners = Banner::query()
            ->with('media')
            ->publishedFor('Home - Main Slider')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $homeCategories = Category::query()
            ->websiteVisible()
            ->whereNull('parent_id')
            ->withCount(['products' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $homeCollections = ProductCollection::query()
            ->with('media')
            ->withCount(['products' => fn ($query) => $query->published()])
            ->where('status', 'active')
            ->where('visibility', 'visible')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'categories' => $homeCategories,
            'homeCategories' => $homeCategories,
            'homeCollections' => $homeCollections,
            'homeProducts' => $homeProducts,
            'homeBestsellers' => $homeBestsellers,
            'homeLatestProducts' => $homeLatestProducts,
            'newProducts' => $homeProducts,
            'banners' => $banners,
            'homepage' => $homepage,
            'homeMedia' => $mediaResolver->forUuids($this->homepageMediaUuids($homepage)),
        ];
    }

    private function homepageMediaUuids(?ContentPage $homepage): array
    {
        if (! $homepage) {
            return [];
        }

        return $homepage->sections->flatMap(function ($section): array {
            $settings = is_array($section->settings) ? $section->settings : [];
            $uuids = [$section->media_uuid];
            foreach ((array) data_get($settings, 'items', []) as $item) {
                if (is_array($item)) {
                    $uuids[] = $item['media_uuid'] ?? null;
                }
            }

            return $uuids;
        })->filter()->values()->all();
    }

    public function collections()
    {
        $bestSellerCollection = ProductCollection::query()
            ->where('slug', 'best-sellers')
            ->where('status', 'active')
            ->where('visibility', 'visible')
            ->first();
        $bestsellers = $bestSellerCollection
            ? $bestSellerCollection->products()
                ->published()
                ->with('media')
                ->orderBy('collection_product.sort_order')
                ->limit(6)
                ->get()
            : Product::published()->with('media')->latest()->limit(6)->get();

        return view('site.collections', [
            'categories' => Category::withCount(['products' => fn ($q) => $q->published()])->where('is_active', true)->orderBy('sort_order')->get(),
            'bestsellers' => $bestsellers,
            'collections' => ProductCollection::with('media')->where('status', 'active')->where('visibility', 'visible')->orderBy('sort_order')->get(),
        ]);
    }

    public function newArrivals(CatalogFilterRequest $r)
    {
        $q = Product::with([
            'category',
            'media',
            'spins' => fn ($spinQuery) => $spinQuery
                ->where('status', 'published')
                ->where('visibility', 'public')
                ->latest('updated_at'),
        ])->withCount('reviews')->withAvg('reviews', 'rating')->published()->where('is_new', true);
        if ($r->filled('q')) $q->where(fn ($x) => $x->where('name', 'like', '%'.$r->q.'%')->orWhere('sku', 'like', '%'.$r->q.'%'));
        $categories = array_values(array_filter((array) $r->input('category', []), fn ($value) => is_string($value) && $value !== ''));
        if ($categories) $q->whereHas('category', fn ($c) => $c->whereIn('slug', $categories));
        $materials = array_values(array_filter((array) $r->input('material', []), fn ($value) => is_string($value) && $value !== ''));
        if ($materials) $q->where(function ($materialQuery) use ($materials) { foreach ($materials as $material) $materialQuery->orWhereRaw('LOWER(material) LIKE ?', ['%'.strtolower($material).'%']); });
        $colours = array_values(array_filter((array) $r->input('colour', []), fn ($value) => is_string($value) && $value !== ''));
        if ($colours) $q->where(function ($colourQuery) use ($colours) { foreach ($colours as $colour) $colourQuery->orWhereRaw('LOWER(CAST(colours AS TEXT)) LIKE ?', ['%"'.strtolower($colour).'"%']); });
        if ($r->filled('max_price')) $q->where('price', '<=', Money::round($r->input('max_price')));
        match ($r->input('sort')) {
            'price_low' => $q->orderBy('price'),
            'price_high' => $q->orderByDesc('price'),
            'name' => $q->orderBy('name'),
            default => $q->latest(),
        };
        $newArrivalsMax = Money::round((string) (Product::query()
            ->published()
            ->where('is_new', true)
            ->max('price') ?? '0'));
        $priceCeiling = (int) ceil(max(1, Money::toMinor($newArrivalsMax)) / 100);

        return view('site.new-arrivals', [
            'products' => $q->paginate(12)->withQueryString(),
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'priceCeiling' => max(1, $priceCeiling),
        ]);
    }

    public function virtualTryOn(Request $request)
    {
        $products = Product::published()
            ->with(['media', 'tryOnAssets' => fn ($query) => $query->where('status', 'published')->where('visibility', 'public')->latest('updated_at')])
            ->orderBy('name')->get();
        $selected = $products->firstWhere('id', (int) $request->input('product_id')) ?: $products->first();
        $assetMetaMap = [];
        $mediaResolver = app(PublicMediaResolver::class);
        $assetMap = $products->mapWithKeys(function ($product) use (&$assetMetaMap, $mediaResolver) {
            $assets = [];
            $managed = $product->tryOnAssets->first(fn ($asset) => $asset->isPublic());
            if ($managed) {
                $assets[] = $managed->previewUrl();
                $assetMetaMap[$product->id] = $managed->viewerData();
                return [$product->id => $assets];
            }
            foreach ($product->media->where('type', 'try_on') as $media) {
                if ($descriptor = $mediaResolver->forProductMedia($media, $product->name)) {
                    $assets[] = $descriptor['url'];
                }
            }
            return [$product->id => $assets];
        })->all();
        return view('site.virtual-tryon', compact('products', 'selected', 'assetMap', 'assetMetaMap'));
    }

    public function irishTraditional(CatalogFilterRequest $request)
    {
        return $this->categoryLanding($request, 'irish-traditional-flat-caps', 'IRISH TRADITIONAL', 'FLAT CAPS', 'Authentic Irish flat caps crafted from premium tweed. Timeless style. Made in Limerick, Ireland.');
    }

    public function irishHeritage(CatalogFilterRequest $request)
    {
        return $this->categoryLanding($request, 'irish-heritage-hats', 'IRISH HERITAGE', 'HATS', 'Classic hats with timeless Irish character. Crafted with care in Limerick using premium materials and traditional techniques.');
    }

    private function categoryLanding(CatalogFilterRequest $request, string $slug, string $eyebrow, string $title, string $intro)
    {
        $category = Category::where('slug', $slug)->where('is_active', true)->first() ?: new Category(['name' => trim($eyebrow.' '.$title)]);
        $query = $category->exists ? $category->products()->with(['category', 'media'])->published() : Product::whereRaw('1 = 0');
        if ($request->filled('q')) $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')->orWhere('sku', 'like', '%'.$request->q.'%'));
        match ($request->input('sort')) {
            'price_low' => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            default => $query->latest(),
        };
        $products = $query->paginate(12)->withQueryString();
        return view('site.category-landing', compact('category', 'eyebrow', 'title', 'intro', 'products'));
    }

    public function factory() { return view('site.factory'); }
    public function corporateOrders() { return view('site.corporate-order'); }
    public function bulkOrders() { return view('site.bulk-order'); }
    public function franchise()
    {
        return view('site.franchise');
    }
    public function quality() { return view('site.quality'); }
    public function careers() { return view('site.careers'); }
    public function globalNetwork() { return view('site.global-network'); }
    public function contact() { return view('site.contact'); }

    public function shop(CatalogFilterRequest $request)
    {
        return $this->shopCatalog($request);
    }

    public function category(CatalogFilterRequest $request, Category $category)
    {
        return $this->shopCatalog($request, $category);
    }

    private function shopCatalog(CatalogFilterRequest $request, ?Category $activeCategory = null)
    {
        // One category query supplies the sidebar, parent/descendant expansion and
        // toolbar subcategory context. Keeping the hierarchy in memory avoids N+1
        // parent/children queries while the product query eagerly loads card data.
        $categories = Category::query()
            ->websiteVisible()
            ->withCount(['products' => fn ($productQuery) => $productQuery->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $categoriesById = $categories->keyBy('id');
        $categoriesBySlug = $categories->keyBy('slug');
        $childrenByParent = $categories->groupBy(fn (Category $category): int => (int) ($category->parent_id ?? 0));

        $rawCategories = $request->input('category', []);
        if (is_string($rawCategories)) $rawCategories = [$rawCategories];
        $selectedCategories = array_values(array_unique(array_filter((array) $rawCategories, fn ($value) => is_string($value) && trim($value) !== '')));
        if ($activeCategory) $selectedCategories = [$activeCategory->slug];

        $contextCategory = $activeCategory;
        if (! $contextCategory && count($selectedCategories) === 1) {
            $contextCategory = $categoriesBySlug->get($selectedCategories[0]);
        }
        $catalogCountryScope = $this->catalogTaxonomyForCategory($contextCategory, $categoriesById);
        $catalogCountyEnabled = in_array($catalogCountryScope, ['traditional', 'heritage', 'gaa', 'english', 'uefa', 'fifa'], true);
        $catalogClubEnabled = in_array($catalogCountryScope, ['gaa', 'english', 'uefa', 'fifa'], true);

        $catalogFilterCountries = CatalogCountry::query()
            ->active()
            ->forTaxonomy($catalogCountryScope)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $selectedCountry = strtoupper(trim((string) $request->input('country', '')));
        $selectedCountryModel = $selectedCountry !== '' ? $catalogFilterCountries->firstWhere('code', $selectedCountry) : null;
        if ($selectedCountry !== '' && ! $selectedCountryModel) {
            throw ValidationException::withMessages(['country' => 'The selected country is not available for this catalogue category.']);
        }

        $selectedCounty = strtoupper(trim((string) $request->input('county', '')));
        if ($selectedCounty !== '' && (! $catalogCountyEnabled || $selectedCountry === '' || ! CatalogCounties::isValid($selectedCountry, $selectedCounty))) {
            throw ValidationException::withMessages(['county' => 'The selected county is not available for this catalogue category and country.']);
        }
        $catalogFilterCounties = $catalogCountyEnabled && $selectedCountry !== ''
            ? CatalogCounties::forCountry($selectedCountry)
            : [];

        $catalogFilterClubs = collect();
        if ($catalogClubEnabled && $selectedCountryModel && $selectedCounty !== '') {
            $clubBase = CatalogClub::query()
                ->active()
                ->where('governing_body', $catalogCountryScope)
                ->where('catalog_country_id', $selectedCountryModel->id);

            $hasExactCountyClubs = (clone $clubBase)
                ->where('catalog_county_code', $selectedCounty)
                ->exists();

            $catalogFilterClubs = $clubBase
                ->when(
                    $catalogCountryScope === 'fifa' && ! $hasExactCountyClubs,
                    fn ($query) => $query->whereNull('catalog_county_code'),
                    fn ($query) => $query->where('catalog_county_code', $selectedCounty),
                )
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'catalog_county_code']);
        }

        $selectedClub = trim((string) $request->input('club', ''));
        $selectedClubModel = $selectedClub !== '' ? $catalogFilterClubs->firstWhere('slug', $selectedClub) : null;
        if ($selectedClub !== '' && (! $catalogClubEnabled || ! $selectedClubModel)) {
            throw ValidationException::withMessages(['club' => 'The selected club is not available for this catalogue category, country and county.']);
        }

        $catalogSubcategoryOptions = collect(CatalogProductTypes::all())
            ->map(fn (string $label, string $value): array => [
                'value' => 'type:'.$value,
                'label' => $label,
                'group' => 'Common',
            ])->values()->all();
        if ($contextCategory) {
            foreach ($this->catalogDescendantOptions($contextCategory, $childrenByParent, $catalogCountyEnabled) as $option) {
                $catalogSubcategoryOptions[] = $option;
            }
        }

        $selectedSubcategory = trim((string) $request->input('subcategory', ''));
        if ($selectedSubcategory !== '' && ! in_array($selectedSubcategory, array_column($catalogSubcategoryOptions, 'value'), true)) {
            throw ValidationException::withMessages(['subcategory' => 'The selected subcategory is not available for this catalogue category.']);
        }

        $query = Product::query()
            ->with([
                'category',
                'media',
                'variants' => fn ($variantQuery) => $variantQuery->where('is_active', true)->orderBy('sort_order')->orderBy('id'),
                'spins' => fn ($spinQuery) => $spinQuery->where('status', 'published')->where('visibility', 'public')->latest('updated_at'),
                'tryOnAssets' => fn ($tryOnQuery) => $tryOnQuery->where('status', 'published')->where('visibility', 'public')->latest('updated_at'),
            ])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->published();

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $needle = '%'.strtolower($search).'%';
            $query->where(function ($searchQuery) use ($needle) {
                $searchQuery->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$needle]);
            });
        }

        if ($selectedCategories) {
            $selectedCategoryIds = [];
            foreach ($selectedCategories as $slug) {
                $category = $categoriesBySlug->get($slug);
                if (! $category) {
                    throw ValidationException::withMessages(['category' => 'One or more selected categories are unavailable.']);
                }
                $selectedCategoryIds = array_merge($selectedCategoryIds, $this->catalogDescendantIds($category, $childrenByParent));
            }
            $query->whereIn('category_id', array_values(array_unique($selectedCategoryIds)));
        }

        if ($selectedCountryModel) {
            $query->where(function ($countryQuery) use ($selectedCountryModel) {
                $countryQuery
                    ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('catalog_country_id', $selectedCountryModel->id))
                    ->orWhere('product_metadata->catalog_classification->catalog_country_id', (string) $selectedCountryModel->id);
            });
        }
        if ($selectedCounty !== '') {
            $query->where(function ($countyQuery) use ($selectedCounty) {
                $countyQuery
                    ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('catalog_county_code', $selectedCounty))
                    ->orWhere('product_metadata->catalog_classification->catalog_county_code', $selectedCounty);
            });
        }
        if ($selectedClubModel) {
            $query->where(function ($clubQuery) use ($selectedClubModel) {
                $clubQuery
                    ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('catalog_club_id', $selectedClubModel->id))
                    ->orWhere('product_metadata->catalog_classification->catalog_club_id', (string) $selectedClubModel->id);
            });
        }
        if ($selectedSubcategory !== '') {
            [$kind, $value] = explode(':', $selectedSubcategory, 2);
            if ($kind === 'type') {
                $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('product_type', $value));
            } else {
                $subcategory = $categoriesBySlug->get($value);
                if (! $subcategory) {
                    throw ValidationException::withMessages(['subcategory' => 'The selected subcategory is unavailable.']);
                }
                $query->whereIn('category_id', $this->catalogDescendantIds($subcategory, $childrenByParent));
            }
        }

        $selectedMaterials = array_values(array_unique(array_filter((array) $request->input('material', []), fn ($value) => is_string($value) && trim($value) !== '')));
        if ($selectedMaterials) {
            $query->where(function ($materialQuery) use ($selectedMaterials) {
                foreach ($selectedMaterials as $material) {
                    $materialQuery->orWhereRaw('LOWER(COALESCE(material, \'\')) LIKE ?', ['%'.strtolower($material).'%']);
                }
            });
        }

        $selectedColours = array_values(array_unique(array_filter((array) $request->input('colour', []), fn ($value) => is_string($value) && trim($value) !== '')));
        if ($selectedColours) {
            $query->where(function ($colourQuery) use ($selectedColours) {
                foreach ($selectedColours as $colour) {
                    $colourQuery->orWhereRaw('LOWER(CAST(colours AS TEXT)) LIKE ?', ['%"'.strtolower($colour).'"%']);
                }
            });
        }

        $selectedSizes = array_values(array_unique(array_filter((array) $request->input('size', []), fn ($value) => is_string($value) && trim($value) !== '')));
        if ($selectedSizes) {
            $query->where(function ($sizeQuery) use ($selectedSizes) {
                foreach ($selectedSizes as $size) {
                    $sizeQuery->orWhereRaw('LOWER(CAST(sizes AS TEXT)) LIKE ?', ['%"'.strtolower($size).'"%']);
                }
            });
        }

        $minPrice = $request->filled('min_price') ? Money::round($request->input('min_price')) : null;
        $maxPrice = $request->filled('max_price') ? Money::round($request->input('max_price')) : null;
        if ($minPrice !== null) $query->where('price', '>=', $minPrice);
        if ($maxPrice !== null) $query->where('price', '<=', $maxPrice);

        $availability = in_array($request->input('availability'), ['in_stock', 'out_of_stock'], true) ? $request->input('availability') : '';
        if ($availability === 'in_stock') {
            $query->where(function ($stockQuery) {
                $stockQuery->where('stock', '>', 0)
                    ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->where('is_active', true)->where('stock', '>', 0));
            });
        } elseif ($availability === 'out_of_stock') {
            $query->whereRaw('COALESCE(stock, 0) <= 0')
                ->whereDoesntHave('variants', fn ($variantQuery) => $variantQuery->where('is_active', true)->where('stock', '>', 0));
        }

        if ($request->boolean('sale')) {
            $query->whereNotNull('compare_price')->whereColumn('compare_price', '>', 'price');
        }

        $sort = in_array($request->input('sort'), ['newest', 'price_low', 'price_high', 'name', 'rating', 'popular'], true)
            ? $request->input('sort')
            : 'newest';
        match ($sort) {
            'price_low' => $query->orderBy('price')->orderBy('name'),
            'price_high' => $query->orderByDesc('price')->orderBy('name'),
            'name' => $query->orderBy('name'),
            'rating' => $query->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count')->latest('id'),
            'popular' => $query->orderByDesc('reviews_count')->orderByDesc('reviews_avg_rating')->latest('id'),
            default => $query->orderByDesc('is_new')->latest(),
        };

        $perPage = in_array((int) $request->input('per_page', 12), [12, 24, 36], true) ? (int) $request->input('per_page', 12) : 12;
        $products = $query->paginate($perPage)->withQueryString();

        $catalogMax = Money::round((string) (Product::published()->max('price') ?? '0'));
        $priceCeiling = max(50, (int) (ceil(max(1, Money::toMinor($catalogMax)) / 1000) * 10));

        return view('site.shop', [
            'products' => $products,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'selectedCategories' => $selectedCategories,
            'selectedMaterials' => $selectedMaterials,
            'selectedColours' => $selectedColours,
            'selectedSizes' => $selectedSizes,
            'selectedCountry' => $selectedCountry,
            'selectedCounty' => $selectedCounty,
            'selectedClub' => $selectedClub,
            'selectedSubcategory' => $selectedSubcategory,
            'catalogFilterCountries' => $catalogFilterCountries,
            'catalogFilterCounties' => $catalogFilterCounties,
            'catalogFilterClubs' => $catalogFilterClubs,
            'catalogSubcategoryOptions' => $catalogSubcategoryOptions,
            'catalogCountryScope' => $catalogCountryScope,
            'catalogCountyEnabled' => $catalogCountyEnabled,
            'catalogClubEnabled' => $catalogClubEnabled,
            'availability' => $availability,
            'sort' => $sort,
            'perPage' => $perPage,
            'priceCeiling' => $priceCeiling,
            'totalCatalog' => Product::published()->count(),
            'materialOptions' => ['Tweed', 'Wool', 'Cotton', 'Linen', 'Leather', 'Felt'],
            'sizeOptions' => ['XS', 'S', 'M', 'L', 'XL', 'One Size'],
            'colourOptions' => [
                'black' => '#121614',
                'green' => '#28543a',
                'navy' => '#1b2d43',
                'brown' => '#62482d',
                'beige' => '#b5a381',
                'grey' => '#747875',
                'white' => '#ecebe5',
                'red' => '#8b352d',
            ],
        ]);
    }

    private function catalogTaxonomyForCategory(?Category $category, Collection $categoriesById): ?string
    {
        $visited = [];
        while ($category) {
            if (filled($category->taxonomy_type)) {
                return strtolower((string) $category->taxonomy_type);
            }
            if (isset($visited[$category->id])) {
                break;
            }
            $visited[$category->id] = true;
            $category = $category->parent_id ? $categoriesById->get($category->parent_id) : null;
        }

        return null;
    }

    private function catalogDescendantIds(Category $root, Collection $childrenByParent): array
    {
        $ids = [];
        $queue = [$root->id];
        while ($queue) {
            $id = (int) array_shift($queue);
            if (isset($ids[$id])) {
                continue;
            }
            $ids[$id] = true;
            foreach ($childrenByParent->get($id, collect()) as $child) {
                $queue[] = $child->id;
            }
        }

        return array_keys($ids);
    }

    private function catalogDescendantOptions(Category $root, Collection $childrenByParent, bool $excludeLocationScoped = false): array
    {
        $options = [];
        $queue = [];
        foreach ($childrenByParent->get($root->id, collect()) as $child) {
            $queue[] = [$child, 0];
        }

        $visited = [];
        while ($queue) {
            [$category, $depth] = array_shift($queue);
            if (isset($visited[$category->id])) {
                continue;
            }
            $visited[$category->id] = true;

            $locationScoped = $excludeLocationScoped
                && (filled($category->catalog_country_id) || filled($category->catalog_county_code));

            if (! $locationScoped) {
                $options[] = [
                    'value' => 'category:'.$category->slug,
                    'label' => str_repeat('↳ ', $depth).$category->name,
                    'group' => 'This Category',
                ];
            }

            foreach ($childrenByParent->get($category->id, collect()) as $child) {
                $queue[] = [$child, $depth + 1];
            }
        }

        return $options;
    }

    public function product(Product $product)
    {
        abort_unless($product->isPubliclyPublished(), 404);
        $product->load([
            'variants',
            'reviews.user',
            'category',
            'media',
            'variants.approvedMedia',
            'spins' => fn ($query) => $query
                ->where('status', 'published')
                ->where('visibility', 'public')
                ->latest('updated_at'),
            'tryOnAssets' => fn ($query) => $query
                ->where('status', 'published')
                ->where('visibility', 'public')
                ->latest('updated_at'),
        ]);
        $managedSpin = $product->latestPublicSpin();
        $spinViewerData = $managedSpin?->viewerData();
        $spinFrames = collect($spinViewerData['frames'] ?? []);
        if (! $managedSpin) {
            $spinFrames = $spinFrames->merge($product->media
                ->where('type', 'spin_360')
                ->map(function ($media) use ($product) {
                $descriptor = app(PublicMediaResolver::class)->forProductMedia($media, $product->name);

                    return $descriptor['url'] ?? null;
                }));
        }
        $spinFrames = $spinFrames->filter()->unique()->values()->all();
        $productVideos = ProductVideo::query()
            ->where('product_id', $product->id)
            ->where('active', true)
            ->where('approval_status', 'approved')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ProductVideo $video): bool => $video->isPubliclyPlayable() && (bool) data_get($video->metadata, 'gallery', true))
            ->values();

        $tryOnAsset = $product->tryOnAssets
            ->first(fn ($asset): bool => $asset->isPublic());
        $tryOnViewerData = $tryOnAsset?->viewerData();

        $related = Product::published()
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with('media')
            ->limit(4)->get();

        return view('site.product', compact(
            'product',
            'related',
            'spinFrames',
            'spinViewerData',
            'productVideos',
            'tryOnViewerData',
        ));
    }

    public function page(string $page)
    {
        $allowed = ['collections', 'new-arrivals', 'corporate-orders', 'bulk-orders', 'franchise', 'careers', 'global-network', 'factory', 'contact', 'virtual-tryon', 'irish-traditional', 'irish-heritage'];
        $managedPage = ContentPage::with('sections')
            ->where('slug', $page)
            ->where('locale', app()->getLocale())
            ->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now()))
            ->first();

        if ($managedPage && ! $managedPage->isPublic()) {
            abort(404);
        }

        if ($managedPage?->requiresLogin() && ! auth()->check()) {
            return redirect()->guest(route('login'));
        }

        abort_unless($managedPage || in_array($page, $allowed, true), 404);
        return view('site.page', compact('page', 'managedPage'));
    }

    public function inquiry(PublicInquiryRequest $request)
    {
        $d = $request->validated();
        $r = $request;
        $requiresConsent = in_array((string) ($d['type'] ?? ''), ['contact', 'franchise'], true);
        $meeting = array_filter(['date' => $d['meeting_date'] ?? null, 'time' => $d['meeting_time'] ?? null], fn ($value) => filled($value));
        if ($meeting) {
            $slot = CarbonImmutable::createFromFormat('!Y-m-d H:i', $meeting['date'].' '.$meeting['time'], config('app.timezone'));
            if ($slot->isWeekend()) throw ValidationException::withMessages(['meeting_date' => 'Meetings are available Monday to Friday only.']);
            if ($slot->lessThanOrEqualTo(now(config('app.timezone')))) throw ValidationException::withMessages(['meeting_time' => 'Please choose a future meeting time.']);
        }
        $country = $d['type'] === 'franchise' ? 'Ireland' : ($d['country'] ?? null);
        $franchiseData = [];
        if ($d['type'] === 'franchise') {
            $franchiseData = [
                'preferred_location' => $d['preferred_location'] ?? $d['company'] ?? null,
                'investment_range' => $d['investment_range'] ?? null,
                'business_experience' => $d['business_experience'] ?? $d['message'] ?? null,
                'opening_timeline' => $d['opening_timeline'] ?? null,
            ];
            unset($d['preferred_location'], $d['investment_range'], $d['business_experience'], $d['opening_timeline'], $d['company']);
        }
        unset($d['meeting_date'], $d['meeting_time'], $d['consent'], $d['country']);
        $d['meta'] = ['source' => 'public_'.$d['type'].'_form', 'meeting' => $meeting ?: null, 'country' => $country];

        $idempotencyKey = (string) ($d['idempotency_key'] ?? '');
        unset($d['idempotency_key']);

        $correlationId = (string) ($r->attributes->get('correlation_id') ?: Str::uuid());
        $customerId = $r->user()?->id;
        $messageIdempotencyKey = $idempotencyKey !== ''
            ? hash('sha256', 'public-inbound:'.$idempotencyKey)
            : null;
        $consentCapturedAt = $requiresConsent ? now() : null;
        $requestHash = hash('sha256', (string) json_encode([
            'payload' => $d,
            'franchise_application' => $franchiseData,
            'meeting' => $meeting,
        ], JSON_UNESCAPED_SLASHES));

        if ($idempotencyKey !== '') {
            $existing = Inquiry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                    abort(409, 'The Idempotency-Key was already used for a different enquiry.');
                }

                return back()->with('success', 'This enquiry was already received and is in our Communication Centre.');
            }
        }

        try {
            DB::transaction(function () use ($d, $franchiseData, $country, $meeting, $correlationId, $idempotencyKey, $requestHash, $customerId, $messageIdempotencyKey, $consentCapturedAt): void {
                $inquiry = Inquiry::create(array_merge($d, [
                    'correlation_id' => $correlationId,
                    'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                    'request_hash' => $requestHash,
                ]));

                $application = null;
                if ($d['type'] === 'franchise') {
                    $application = FranchiseApplication::create([
                        'applicant_name' => $d['name'],
                        'email' => $d['email'],
                        'phone' => $d['phone'] ?? null,
                        'territory' => 'Ireland',
                        'preferred_location' => $franchiseData['preferred_location'] ?? null,
                        'investment_range' => $franchiseData['investment_range'] ?? null,
                        'business_experience' => $franchiseData['business_experience'] ?? null,
                        'status' => 'new',
                        'data' => array_filter([
                            'source' => 'public_franchise_form',
                            'country' => $country,
                            'opening_timeline' => $franchiseData['opening_timeline'] ?? null,
                        ], fn ($value) => $value !== null && $value !== ''),
                        'correlation_id' => $correlationId,
                        'inquiry_id' => $inquiry->id,
                    ]);
                }

                $conversation = Conversation::create([
                'company_id' => $inquiry->company_id,
                'customer_id' => $customerId,
                'channel' => 'web',
                'contact' => $d['email'],
                'subject' => $d['subject'] ?? str($d['type'])->headline(),
                'priority' => $d['type'] === 'franchise' ? 'high' : 'normal',
                'status' => 'new',
                'correlation_id' => $correlationId,
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                'request_hash' => $requestHash,
                'consent_captured_at' => $consentCapturedAt,
                'consent_version' => $consentCapturedAt ? 'public-enquiry-v1' : null,
                'inquiry_id' => $inquiry->id,
                'franchise_application_id' => $application?->id,
                'metadata' => [
                    'type' => $d['type'],
                    'name' => $d['name'],
                    'phone' => $d['phone'] ?? null,
                    'company' => $d['company'] ?? null,
                    'country' => $d['meta']['country'] ?? null,
                    'meeting' => $meeting ?: null,
                ],
                ]);
                $body = $d['message'] ?? $franchiseData['business_experience'] ?? 'Public form submission';
                if ($meeting) $body .= "\n\nMeeting requested: {$meeting['date']} at {$meeting['time']} (Europe/Dublin).";
                $message = $conversation->messages()->create([
                    'direction' => 'inbound',
                    'body' => $body,
                    'delivery_status' => 'stored',
                    'idempotency_key' => $messageIdempotencyKey,
                    'payload' => [
                        'source' => 'public_'.$d['type'].'_form',
                        'correlation_id' => $correlationId,
                        'consent_captured' => (bool) $consentCapturedAt,
                    ],
                    'sent_at' => now(),
                ]);
                AuditTrail::record('communication.public_submission.created', $conversation, null, [
                    'uuid' => (string) $conversation->uuid,
                    'channel' => $conversation->channel,
                    'correlation_id' => $correlationId,
                    'idempotency_key' => $conversation->idempotency_key,
                    'consent_captured' => (bool) $consentCapturedAt,
                    'message_uuid' => (string) $message->uuid,
                ]);
                $quoteService = app(SalesQuoteService::class);
                if ($quoteService->orderTypeForInquiryType((string) $d['type']) !== null) {
                    $quoteService->createFromInquiry($inquiry, $conversation, $application);
                }
            });
        } catch (QueryException $exception) {
            if ($idempotencyKey === '') {
                throw $exception;
            }

            $existing = Inquiry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing && hash_equals((string) $existing->request_hash, $requestHash)) {
                return back()->with('success', 'This enquiry was already received and is in our Communication Centre.');
            }

            throw $exception;
        }

        return back()->with('success', 'Thank you. Your enquiry is now in our Communication Centre.');
    }
}
