<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use App\Models\Category;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CatalogTaxonomyController extends Controller
{
    private const TAXONOMIES = [
        'uefa' => 'UEFA',
        'fifa' => 'FIFA',
        'gaa' => 'GAA',
        'traditional' => 'Traditional',
        'heritage' => 'Heritage',
    ];

    private const PRODUCT_TYPES = [
        'beanies' => 'Beanies',
        'caps' => 'Caps',
        'hats' => 'Hats',
    ];

    private const CLUB_TAXONOMIES = ['uefa', 'fifa', 'gaa'];

    public function index(Request $request): View
    {
        $taxonomy = strtolower((string) $request->query('taxonomy_type', ''));
        $countryId = (int) $request->query('catalog_country_id', 0);
        $clubId = (int) $request->query('catalog_club_id', 0);
        $productType = strtolower((string) $request->query('product_type', ''));

        $categories = Category::query()
            ->whereNotNull('taxonomy_type')
            ->with(['parent', 'catalogCountry', 'catalogClub'])
            ->when(isset(self::TAXONOMIES[$taxonomy]), fn ($query) => $query->where('taxonomy_type', $taxonomy))
            ->when($countryId > 0, fn ($query) => $query->where('catalog_country_id', $countryId))
            ->when($clubId > 0, fn ($query) => $query->where('catalog_club_id', $clubId))
            ->when(isset(self::PRODUCT_TYPES[$productType]), fn ($query) => $query->where('product_type', $productType))
            ->orderBy('taxonomy_type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.categories.taxonomy', [
            'categories' => $categories,
            'countries' => CatalogCountry::query()->active()->orderBy('name')->get(),
            'clubs' => CatalogClub::query()->active()->with('country')->orderBy('governing_body')->orderBy('name')->get(),
            'taxonomyTypes' => self::TAXONOMIES,
            'productTypes' => self::PRODUCT_TYPES,
            'filters' => compact('taxonomy', 'countryId', 'clubId', 'productType'),
        ]);
    }

    public function build(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'taxonomy_type' => ['required', Rule::in(array_keys(self::TAXONOMIES))],
            'catalog_country_id' => ['required', 'integer', Rule::exists('catalog_countries', 'id')->where('is_active', true)],
            'catalog_club_id' => ['nullable', 'integer', Rule::exists('catalog_clubs', 'id')->where('is_active', true)],
            'product_types' => ['required', 'array', 'min:1'],
            'product_types.*' => ['required', Rule::in(array_keys(self::PRODUCT_TYPES))],
        ]);

        $taxonomy = $data['taxonomy_type'];
        $country = CatalogCountry::query()->active()->findOrFail((int) $data['catalog_country_id']);
        $club = filled($data['catalog_club_id'] ?? null)
            ? CatalogClub::query()->active()->with('country')->findOrFail((int) $data['catalog_club_id'])
            : null;

        $this->guardCountryScope($taxonomy, $country);
        $this->guardClubScope($taxonomy, $country, $club);

        $created = 0;
        $lastCategory = null;

        DB::transaction(function () use ($taxonomy, $country, $club, $data, &$created, &$lastCategory): void {
            $top = $this->ensureCategory(
                parent: null,
                name: self::TAXONOMIES[$taxonomy],
                slug: $taxonomy,
                taxonomy: $taxonomy,
                country: null,
                club: null,
                productType: null,
                sortOrder: array_search($taxonomy, array_keys(self::TAXONOMIES), true) + 10,
                created: $created,
            );

            $countryCategory = $this->ensureCategory(
                parent: $top,
                name: $country->name,
                slug: $taxonomy.'-'.strtolower($country->code),
                taxonomy: $taxonomy,
                country: $country,
                club: null,
                productType: null,
                sortOrder: max(1, (int) $country->sort_order),
                created: $created,
            );

            $parent = $countryCategory;
            if (in_array($taxonomy, self::CLUB_TAXONOMIES, true) && $club) {
                $parent = $this->ensureCategory(
                    parent: $countryCategory,
                    name: $club->name,
                    slug: $taxonomy.'-'.strtolower($country->code).'-'.$club->slug,
                    taxonomy: $taxonomy,
                    country: $country,
                    club: $club,
                    productType: null,
                    sortOrder: max(1, (int) $club->sort_order),
                    created: $created,
                );
            }

            foreach (array_values(array_unique($data['product_types'])) as $index => $productType) {
                $lastCategory = $this->ensureCategory(
                    parent: $parent,
                    name: self::PRODUCT_TYPES[$productType],
                    slug: $parent->slug.'-'.$productType,
                    taxonomy: $taxonomy,
                    country: $country,
                    club: $club,
                    productType: $productType,
                    sortOrder: ($index + 1) * 10,
                    created: $created,
                );
            }
        });

        return redirect()->route('admin.categories.taxonomy', [
            'taxonomy_type' => $taxonomy,
            'catalog_country_id' => $country->id,
            'catalog_club_id' => $club?->id,
        ])->with('success', ($created > 0 ? $created.' menu item(s) created.' : 'The menu hierarchy already existed and was synchronized.').($lastCategory ? ' Product types are ready under '.$lastCategory->parent?->name.'.' : ''));
    }

    public function countries(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $scope = strtolower((string) $request->query('scope', 'all'));
        $status = strtolower((string) $request->query('status', 'active'));

        $countries = CatalogCountry::query()
            ->withCount('clubs')
            ->when($search !== '', fn ($query) => $query->where(fn ($sub) => $sub
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('code', 'like', '%'.$search.'%')))
            ->when($scope === 'eu', fn ($query) => $query->where('is_eu', true))
            ->when($scope === 'uefa', fn ($query) => $query->where('is_uefa', true))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(60)
            ->withQueryString();

        return view('admin.categories.countries', compact('countries', 'search', 'scope', 'status'));
    }

    public function storeCountry(Request $request): RedirectResponse
    {
        $data = $this->countryData($request);
        $country = CatalogCountry::create($data);
        AuditTrail::record('catalog.country.created', $country, null, $country->toArray());

        return back()->with('success', $country->name.' added to Country Master.');
    }

    public function updateCountry(Request $request, CatalogCountry $country): RedirectResponse
    {
        $before = $country->toArray();
        $country->update($this->countryData($request, $country));
        AuditTrail::record('catalog.country.updated', $country, $before, $country->fresh()->toArray());

        return back()->with('success', $country->name.' updated.');
    }

    public function clubs(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $governingBody = strtolower((string) $request->query('governing_body', ''));
        $countryId = (int) $request->query('catalog_country_id', 0);
        $status = strtolower((string) $request->query('status', 'active'));

        $clubs = CatalogClub::query()
            ->with('country')
            ->withCount('categories')
            ->when($search !== '', fn ($query) => $query->where(fn ($sub) => $sub
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%')))
            ->when(in_array($governingBody, self::CLUB_TAXONOMIES, true), fn ($query) => $query->where('governing_body', $governingBody))
            ->when($countryId > 0, fn ($query) => $query->where('catalog_country_id', $countryId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('governing_body')
            ->orderBy('catalog_country_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(60)
            ->withQueryString();

        return view('admin.categories.clubs', [
            'clubs' => $clubs,
            'countries' => CatalogCountry::query()->active()->orderBy('name')->get(),
            'governingBodies' => array_intersect_key(self::TAXONOMIES, array_flip(self::CLUB_TAXONOMIES)),
            'search' => $search,
            'governingBody' => $governingBody,
            'countryId' => $countryId,
            'status' => $status,
        ]);
    }

    public function storeClub(Request $request): RedirectResponse
    {
        $data = $this->clubData($request);
        $club = CatalogClub::create($data);
        AuditTrail::record('catalog.club.created', $club, null, $club->toArray());

        return back()->with('success', $club->name.' added to Club Master.');
    }

    public function updateClub(Request $request, CatalogClub $club): RedirectResponse
    {
        $before = $club->toArray();
        $club->update($this->clubData($request, $club));
        AuditTrail::record('catalog.club.updated', $club, $before, $club->fresh()->toArray());

        return back()->with('success', $club->name.' updated.');
    }

    public function destroyClub(CatalogClub $club): RedirectResponse
    {
        if ($club->categories()->exists()) {
            return back()->withErrors(['club' => 'This club is used by category menu items. Remove or reassign those categories first.']);
        }

        $before = $club->toArray();
        AuditTrail::record('catalog.club.deleted', $club, $before, null);
        $club->delete();

        return back()->with('success', 'Club removed from Club Master.');
    }

    private function countryData(Request $request, ?CatalogCountry $country = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:2', 'max:3', Rule::unique('catalog_countries', 'code')->ignore($country?->id)],
            'name' => ['required', 'string', 'max:160'],
            'is_eu' => ['required', 'boolean'],
            'is_uefa' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);
        $data['code'] = strtoupper(trim($data['code']));

        return $data;
    }

    private function clubData(Request $request, ?CatalogClub $club = null): array
    {
        $data = $request->validate([
            'catalog_country_id' => ['required', 'integer', Rule::exists('catalog_countries', 'id')->where('is_active', true)],
            'governing_body' => ['required', Rule::in(self::CLUB_TAXONOMIES)],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:220'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $data['slug'] = Str::slug(filled($data['slug'] ?? null) ? $data['slug'] : $data['name']);
        $duplicate = CatalogClub::query()
            ->where('governing_body', $data['governing_body'])
            ->where('catalog_country_id', $data['catalog_country_id'])
            ->where('slug', $data['slug']);
        if ($club) {
            $duplicate->whereKeyNot($club->id);
        }
        if ($duplicate->exists()) {
            throw ValidationException::withMessages(['slug' => 'This club already exists for the selected organization and country.']);
        }

        return $data;
    }

    private function guardCountryScope(string $taxonomy, CatalogCountry $country): void
    {
        if ($taxonomy === 'uefa' && ! $country->is_uefa) {
            throw ValidationException::withMessages(['catalog_country_id' => 'UEFA menus can only use countries/associations enabled for UEFA.']);
        }
        if (in_array($taxonomy, ['traditional', 'heritage'], true) && ! $country->is_eu) {
            throw ValidationException::withMessages(['catalog_country_id' => 'Traditional and Heritage menus are restricted to EU countries.']);
        }
    }

    private function guardClubScope(string $taxonomy, CatalogCountry $country, ?CatalogClub $club): void
    {
        if (! in_array($taxonomy, self::CLUB_TAXONOMIES, true)) {
            if ($club) {
                throw ValidationException::withMessages(['catalog_club_id' => 'Traditional and Heritage menus do not use a club level.']);
            }
            return;
        }

        if (! $club) {
            throw ValidationException::withMessages(['catalog_club_id' => 'Select a club for UEFA, FIFA or GAA. Add it in Club Master first if necessary.']);
        }
        if ($club->governing_body !== $taxonomy || (int) $club->catalog_country_id !== (int) $country->id) {
            throw ValidationException::withMessages(['catalog_club_id' => 'The selected club does not belong to this organization and country.']);
        }
    }

    private function ensureCategory(
        ?Category $parent,
        string $name,
        string $slug,
        string $taxonomy,
        ?CatalogCountry $country,
        ?CatalogClub $club,
        ?string $productType,
        int $sortOrder,
        int &$created,
    ): Category {
        $category = Category::query()->where('slug', $slug)->first();
        $attributes = [
            'parent_id' => $parent?->id,
            'name' => $name,
            'taxonomy_type' => $taxonomy,
            'catalog_country_id' => $country?->id,
            'catalog_club_id' => $club?->id,
            'product_type' => $productType,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
            'sort_order' => max(0, $sortOrder),
            'updated_by' => auth()->id(),
        ];

        if (! $category) {
            $category = Category::create([
                ...$attributes,
                'slug' => $slug,
                'created_by' => auth()->id(),
            ]);
            $created++;
            AuditTrail::record('category.taxonomy.created', $category, null, $category->toArray());
            return $category;
        }

        $before = $category->toArray();
        $category->update($attributes);
        if ($before !== $category->fresh()->toArray()) {
            AuditTrail::record('category.taxonomy.synced', $category, $before, $category->fresh()->toArray());
        }

        return $category;
    }
}
