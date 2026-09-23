<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogClub;
use App\Models\CatalogCounty;
use App\Models\CatalogCountry;
use App\Models\Category;
use App\Services\AuditTrail;
use App\Support\CatalogProductTypes;
use App\Support\CatalogStyles;
use Illuminate\Http\JsonResponse;
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
        'traditional' => 'Traditional',
        'heritage' => 'Heritage',
        'gaa' => 'GAA',
        'english' => 'English',
        'uefa' => 'UEFA',
        'fifa' => 'FIFA',
        'gift' => 'Gift',
        'accessory' => 'Accessory',
        'customised' => 'Customised',
        'corporate' => 'Corporate',
    ];

    private const GEO_TAXONOMIES = ['traditional', 'heritage', 'gaa', 'english', 'uefa', 'fifa'];
    private const CLUB_TAXONOMIES = ['traditional', 'heritage', 'gaa', 'english', 'uefa', 'fifa'];
    private const REQUIRED_CLUB_TAXONOMIES = ['gaa', 'english', 'uefa', 'fifa'];
    private const COUNTY_TAXONOMIES = ['traditional', 'heritage', 'gaa', 'english', 'uefa', 'fifa'];
    private const REQUIRED_COUNTY_TAXONOMIES = ['traditional', 'heritage', 'gaa', 'english', 'uefa', 'fifa'];

    public function index(Request $request): View
    {
        $taxonomy = strtolower((string) $request->query('taxonomy_type', ''));
        $countryId = (int) $request->query('catalog_country_id', 0);
        $countyCode = strtoupper(trim((string) $request->query('catalog_county_code', '')));
        $clubId = (int) $request->query('catalog_club_id', 0);
        $productType = strtolower((string) $request->query('product_type', ''));
        $productTypes = CatalogProductTypes::all();
        $styles = CatalogStyles::all();

        $categories = Category::query()
            ->whereNotNull('taxonomy_type')
            ->with(['parent', 'catalogCountry', 'catalogClub'])
            ->when(isset(self::TAXONOMIES[$taxonomy]), fn ($query) => $query->where('taxonomy_type', $taxonomy))
            ->when($countryId > 0, fn ($query) => $query->where('catalog_country_id', $countryId))
            ->when($countyCode !== '', fn ($query) => $query->where('catalog_county_code', $countyCode))
            ->when($clubId > 0, fn ($query) => $query->where('catalog_club_id', $clubId))
            ->when(isset($productTypes[$productType]), fn ($query) => $query->where('product_type', $productType))
            ->orderBy('taxonomy_type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $countries = CatalogCountry::query()->active()->orderBy('name')->get();
        $clubs = collect();
        if ($clubId > 0) {
            $clubs = CatalogClub::query()->active()->with('country')->whereKey($clubId)->get();
        } elseif ($countryId > 0 && $countyCode !== '' && in_array($taxonomy, self::CLUB_TAXONOMIES, true)) {
            $clubs = $this->clubOptionQuery($taxonomy, $countryId, $countyCode)->with('country')->get();
        }
        $countyOptionsByCountry = $this->countyOptionsByCountry();
        $countyNames = collect($countyOptionsByCountry)
            ->flatten(1)
            ->filter(fn ($row) => is_array($row) && filled($row['code'] ?? null))
            ->mapWithKeys(fn ($row) => [(string) $row['code'] => (string) ($row['name'] ?? $row['code'])])
            ->all();

        return view('admin.categories.taxonomy', [
            'categories' => $categories,
            'countries' => $countries,
            'clubs' => $clubs,
            'countyOptionsByCountry' => $countyOptionsByCountry,
            'countyNames' => $countyNames,
            'taxonomyTypes' => self::TAXONOMIES,
            'productTypes' => $productTypes,
            'styles' => $styles,
            'geoTaxonomies' => self::GEO_TAXONOMIES,
            'clubTaxonomies' => self::CLUB_TAXONOMIES,
            'requiredClubTaxonomies' => self::REQUIRED_CLUB_TAXONOMIES,
            'countyTaxonomies' => self::COUNTY_TAXONOMIES,
            'requiredCountyTaxonomies' => self::REQUIRED_COUNTY_TAXONOMIES,
            'filters' => compact('taxonomy', 'countryId', 'countyCode', 'clubId', 'productType'),
        ]);
    }

    public function build(Request $request): RedirectResponse
    {
        $productTypes = CatalogProductTypes::all();
        $styles = CatalogStyles::all();
        $data = $request->validate([
            'taxonomy_type' => ['required', Rule::in(array_keys(self::TAXONOMIES))],
            'catalog_country_id' => ['nullable', 'integer', Rule::exists('catalog_countries', 'id')->where('is_active', true)],
            'catalog_county_code' => ['nullable', 'string', 'max:16'],
            'catalog_club_id' => ['nullable', 'integer', Rule::exists('catalog_clubs', 'id')->where('is_active', true)],
            'product_types' => ['required', 'array', 'min:1'],
            'product_types.*' => ['required', Rule::in(array_keys($productTypes))],
            'style' => ['nullable', Rule::in(array_keys($styles))],
        ]);

        $taxonomy = $data['taxonomy_type'];
        $requiresGeo = in_array($taxonomy, self::GEO_TAXONOMIES, true);

        if ($requiresGeo && ! filled($data['catalog_country_id'] ?? null)) {
            throw ValidationException::withMessages([
                'catalog_country_id' => 'Select a country for this category.',
            ]);
        }

        $country = filled($data['catalog_country_id'] ?? null)
            ? CatalogCountry::query()->active()->findOrFail((int) $data['catalog_country_id'])
            : null;
        $club = filled($data['catalog_club_id'] ?? null)
            ? CatalogClub::query()->active()->with('country')->findOrFail((int) $data['catalog_club_id'])
            : null;
        $countyCode = strtoupper(trim((string) ($data['catalog_county_code'] ?? '')));

        $this->guardCountryScope($taxonomy, $country);
        $county = $this->resolveCounty($taxonomy, $country, $countyCode);
        $countyCode = $county['code'] ?? null;
        $this->guardClubScope($taxonomy, $country, $countyCode, $club);
        $style = filled($data['style'] ?? null) ? (string) $data['style'] : null;

        $created = 0;
        $lastCategory = null;

        DB::transaction(function () use ($taxonomy, $country, $county, $countyCode, $club, $data, $productTypes, $styles, $style, &$created, &$lastCategory): void {
            $top = $this->ensureCategory(
                parent: null,
                name: self::TAXONOMIES[$taxonomy],
                slug: $taxonomy,
                taxonomy: $taxonomy,
                country: null,
                countyCode: null,
                club: null,
                productType: null,
                sortOrder: array_search($taxonomy, array_keys(self::TAXONOMIES), true) + 10,
                created: $created,
            );

            $parent = $top;

            if ($country) {
                $parent = $this->ensureCategory(
                    parent: $top,
                    name: $country->name,
                    slug: $taxonomy.'-'.strtolower($country->code),
                    taxonomy: $taxonomy,
                    country: $country,
                    countyCode: null,
                    club: null,
                    productType: null,
                    sortOrder: max(1, (int) $country->sort_order),
                    created: $created,
                );
            }

            if ($county && $country) {
                $parent = $this->ensureCategory(
                    parent: $parent,
                    name: $county['name'],
                    slug: $parent->slug.'-'.Str::slug(strtolower((string) $countyCode)),
                    taxonomy: $taxonomy,
                    country: $country,
                    countyCode: $countyCode,
                    club: null,
                    productType: null,
                    sortOrder: 10,
                    created: $created,
                );
            }

            if ($club && $country) {
                $parent = $this->ensureCategory(
                    parent: $parent,
                    name: $club->name,
                    slug: $parent->slug.'-'.$club->slug,
                    taxonomy: $taxonomy,
                    country: $country,
                    countyCode: $countyCode,
                    club: $club,
                    productType: null,
                    sortOrder: max(1, (int) $club->sort_order),
                    created: $created,
                );
            }

            foreach (array_values(array_unique($data['product_types'])) as $index => $productType) {
                $productCategory = $this->ensureCategory(
                    parent: $parent,
                    name: $productTypes[$productType],
                    slug: $parent->slug.'-'.$productType,
                    taxonomy: $taxonomy,
                    country: $country,
                    countyCode: $countyCode,
                    club: $club,
                    productType: $productType,
                    sortOrder: ($index + 1) * 10,
                    created: $created,
                );

                $lastCategory = $productCategory;

                if ($style) {
                    $lastCategory = $this->ensureCategory(
                        parent: $productCategory,
                        name: $styles[$style],
                        slug: $productCategory->slug.'-'.$style,
                        taxonomy: $taxonomy,
                        country: $country,
                        countyCode: $countyCode,
                        club: $club,
                        productType: $productType,
                        sortOrder: 10,
                        created: $created,
                    );
                }
            }
        });

        $redirect = ['taxonomy_type' => $taxonomy];
        if ($country) {
            $redirect['catalog_country_id'] = $country->id;
        }
        if ($countyCode) {
            $redirect['catalog_county_code'] = $countyCode;
        }
        if ($club) {
            $redirect['catalog_club_id'] = $club->id;
        }

        return redirect()->route('admin.categories.taxonomy', $redirect)
            ->with('success', ($created > 0 ? $created.' menu item(s) created.' : 'The menu hierarchy already existed and was synchronized.')
                .($lastCategory ? ' The selected subcategory path is ready under '.$lastCategory->parent?->name.'.' : ''));
    }

    public function countries(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $scope = strtolower((string) $request->query('scope', 'all'));
        $status = strtolower((string) $request->query('status', 'active'));

        $countries = CatalogCountry::query()
            ->withCount(['clubs', 'counties'])
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

    public function counties(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $countryId = (int) $request->query('catalog_country_id', 0);
        $status = strtolower((string) $request->query('status', 'active'));

        $counties = CatalogCounty::query()
            ->with('country:id,code,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($sub) => $sub
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('code', 'like', '%'.$search.'%')))
            ->when($countryId > 0, fn ($query) => $query->where('catalog_country_id', $countryId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('catalog_country_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(60)
            ->withQueryString();

        return view('admin.categories.counties', [
            'counties' => $counties,
            'countries' => CatalogCountry::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
            'search' => $search,
            'countryId' => $countryId,
            'status' => $status,
        ]);
    }

    public function storeCounty(Request $request): RedirectResponse
    {
        $data = $this->countyData($request);
        $county = CatalogCounty::create($data);
        AuditTrail::record('catalog.county.created', $county, null, $county->toArray());

        return back()->with('success', $county->name.' added to County / Region Master.');
    }

    public function updateCounty(Request $request, CatalogCounty $county): RedirectResponse
    {
        $before = $county->toArray();
        $oldCountryId = (int) $county->catalog_country_id;
        $oldCode = strtoupper((string) $county->code);
        $data = $this->countyData($request, $county);

        DB::transaction(function () use ($county, $data, $oldCountryId, $oldCode): void {
            $county->update($data);

            if ($oldCountryId !== (int) $county->catalog_country_id || $oldCode !== strtoupper((string) $county->code)) {
                CatalogClub::query()
                    ->where('catalog_country_id', $oldCountryId)
                    ->where('catalog_county_code', $oldCode)
                    ->update([
                        'catalog_country_id' => $county->catalog_country_id,
                        'catalog_county_code' => $county->code,
                    ]);

                Category::query()
                    ->where('catalog_country_id', $oldCountryId)
                    ->where('catalog_county_code', $oldCode)
                    ->update([
                        'catalog_country_id' => $county->catalog_country_id,
                        'catalog_county_code' => $county->code,
                    ]);
            }
        });

        AuditTrail::record('catalog.county.updated', $county, $before, $county->fresh()->toArray());

        return back()->with('success', $county->name.' updated.');
    }

    public function destroyCounty(CatalogCounty $county): RedirectResponse
    {
        $inUseByClubs = CatalogClub::query()
            ->where('catalog_country_id', $county->catalog_country_id)
            ->where('catalog_county_code', $county->code)
            ->exists();
        $inUseByCategories = Category::query()
            ->where('catalog_country_id', $county->catalog_country_id)
            ->where('catalog_county_code', $county->code)
            ->exists();

        if ($inUseByClubs || $inUseByCategories) {
            return back()->withErrors(['county' => 'This county / region is in use. Reassign its clubs and categories before deleting it.']);
        }

        $before = $county->toArray();
        AuditTrail::record('catalog.county.deleted', $county, $before, null);
        $county->delete();

        return back()->with('success', 'County / region removed from Master.');
    }

    public function clubs(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $governingBody = strtolower((string) $request->query('governing_body', ''));
        $countryId = (int) $request->query('catalog_country_id', 0);
        $countyCode = strtoupper(trim((string) $request->query('catalog_county_code', '')));
        $status = strtolower((string) $request->query('status', 'active'));

        $clubs = CatalogClub::query()
            ->with(['country', 'organizations'])
            ->withCount('categories')
            ->when($search !== '', fn ($query) => $query->where(fn ($sub) => $sub
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%')))
            ->when(in_array($governingBody, self::CLUB_TAXONOMIES, true), fn ($query) => $query->forOrganization($governingBody))
            ->when($countryId > 0, fn ($query) => $query->where('catalog_country_id', $countryId))
            ->when($countyCode !== '', fn ($query) => $query->where('catalog_county_code', $countyCode))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('catalog_country_id')
            ->orderBy('governing_body')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(60)
            ->withQueryString();

        return view('admin.categories.clubs', [
            'clubs' => $clubs,
            'countries' => CatalogCountry::query()->active()->orderBy('name')->get(),
            'countyOptionsByCountry' => $this->countyOptionsByCountry(),
            'countyNames' => CatalogCounty::query()->pluck('name', 'code')->all(),
            'governingBodies' => array_intersect_key(self::TAXONOMIES, array_flip(self::CLUB_TAXONOMIES)),
            'search' => $search,
            'governingBody' => $governingBody,
            'countryId' => $countryId,
            'countyCode' => $countyCode,
            'status' => $status,
        ]);
    }

    public function clubOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'governing_body' => ['required', Rule::in(self::CLUB_TAXONOMIES)],
            'catalog_country_id' => ['required', 'integer', Rule::exists('catalog_countries', 'id')->where('is_active', true)],
            'catalog_county_code' => ['required', 'string', 'max:16'],
        ]);

        $country = CatalogCountry::query()->active()->findOrFail((int) $data['catalog_country_id']);
        $countyCode = strtoupper(trim((string) $data['catalog_county_code']));

        if (! CatalogCounty::query()->active()->where('catalog_country_id', $country->id)->where('code', $countyCode)->exists()) {
            throw ValidationException::withMessages([
                'catalog_county_code' => 'The selected county / subdivision does not belong to the selected country.',
            ]);
        }

        $clubs = $this->clubOptionQuery(
            strtolower((string) $data['governing_body']),
            (int) $country->id,
            $countyCode,
        )->get(['id', 'name', 'slug', 'catalog_county_code']);

        return response()->json([
            'clubs' => $clubs->map(fn (CatalogClub $club): array => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'county_code' => $club->catalog_county_code,
                'scope' => filled($club->catalog_county_code) ? 'county' : 'country',
            ])->values(),
        ]);
    }

    public function storeClub(Request $request): RedirectResponse
    {
        $data = $this->clubData($request);
        $organizations = $data['organizations'];
        unset($data['organizations']);

        $club = CatalogClub::create($data);
        $club->syncOrganizations($organizations);
        $club->load('organizations');

        AuditTrail::record('catalog.club.created', $club, null, $club->toArray());

        return back()->with('success', $club->name.' added to Club Master.');
    }

    public function updateClub(Request $request, CatalogClub $club): RedirectResponse
    {
        $before = $club->load('organizations')->toArray();
        $data = $this->clubData($request, $club);
        $organizations = $data['organizations'];
        unset($data['organizations']);

        $club->update($data);
        $club->syncOrganizations($organizations);
        $club->load('organizations');

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

    private function clubOptionQuery(string $taxonomy, int $countryId, string $countyCode)
    {
        $base = CatalogClub::query()
            ->active()
            ->forOrganization($taxonomy)
            ->where('catalog_country_id', $countryId);

        return $base
            ->where('catalog_county_code', $countyCode)
            ->orderBy('sort_order')
            ->orderBy('name');
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

    private function countyData(Request $request, ?CatalogCounty $county = null): array
    {
        $data = $request->validate([
            'catalog_country_id' => ['required', 'integer', Rule::exists('catalog_countries', 'id')->where('is_active', true)],
            'code' => [
                'required',
                'string',
                'min:1',
                'max:16',
                Rule::unique('catalog_counties', 'code')
                    ->where(fn ($query) => $query->where('catalog_country_id', (int) $request->input('catalog_country_id')))
                    ->ignore($county?->id),
            ],
            'name' => ['required', 'string', 'max:180'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $data['code'] = strtoupper(trim((string) $data['code']));
        $data['name'] = trim((string) $data['name']);

        return $data;
    }

    private function clubData(Request $request, ?CatalogClub $club = null): array
    {
        $data = $request->validate([
            'catalog_country_id' => ['required', 'integer', Rule::exists('catalog_countries', 'id')->where('is_active', true)],
            'catalog_county_code' => ['required', 'string', 'max:16'],
            'organizations' => ['nullable', 'array'],
            'organizations.*' => ['required', Rule::in(self::CLUB_TAXONOMIES)],
            'governing_body' => ['nullable', Rule::in(self::CLUB_TAXONOMIES)],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:220'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $organizations = $data['organizations'] ?? [];
        if ($organizations === [] && filled($data['governing_body'] ?? null)) {
            $organizations = [(string) $data['governing_body']];
        }

        $data['organizations'] = array_values(array_unique(array_filter(array_map(
            static fn ($organization): string => strtolower(trim((string) $organization)),
            $organizations,
        ))));

        if ($data['organizations'] === []) {
            throw ValidationException::withMessages([
                'organizations' => 'Select at least one organization for this club.',
            ]);
        }

        $data['governing_body'] = $data['organizations'][0];

        $country = CatalogCountry::query()->active()->findOrFail((int) $data['catalog_country_id']);
        $data['catalog_county_code'] = strtoupper(trim((string) $data['catalog_county_code']));
        if (! CatalogCounty::query()->active()->where('catalog_country_id', $country->id)->where('code', $data['catalog_county_code'])->exists()) {
            throw ValidationException::withMessages([
                'catalog_county_code' => 'Select a county that belongs to the selected country.',
            ]);
        }

        $data['slug'] = Str::slug(filled($data['slug'] ?? null) ? $data['slug'] : $data['name']);
        $duplicate = CatalogClub::query()
            ->where('catalog_country_id', $data['catalog_country_id'])
            ->where('slug', $data['slug']);
        if ($club) {
            $duplicate->whereKeyNot($club->id);
        }
        if ($duplicate->exists()) {
            throw ValidationException::withMessages(['slug' => 'This club already exists for the selected country. Add another organization to the existing club instead.']);
        }

        return $data;
    }

    private function guardCountryScope(string $taxonomy, ?CatalogCountry $country): void
    {
        if (! in_array($taxonomy, self::GEO_TAXONOMIES, true)) {
            return;
        }

        if (! $country) {
            throw ValidationException::withMessages(['catalog_country_id' => 'Select a country for this category.']);
        }

        if ($taxonomy === 'uefa' && ! $country->is_uefa) {
            throw ValidationException::withMessages(['catalog_country_id' => 'UEFA menus can only use countries/associations enabled for UEFA.']);
        }
        if (in_array($taxonomy, ['traditional', 'heritage'], true) && ! $country->is_eu) {
            throw ValidationException::withMessages(['catalog_country_id' => 'Traditional and Heritage menus are restricted to EU countries.']);
        }
    }

    private function guardClubScope(string $taxonomy, ?CatalogCountry $country, ?string $countyCode, ?CatalogClub $club): void
    {
        if (! in_array($taxonomy, self::CLUB_TAXONOMIES, true)) {
            if ($club) {
                throw ValidationException::withMessages(['catalog_club_id' => 'This category does not use a club / city / town level.']);
            }
            return;
        }

        if (in_array($taxonomy, self::REQUIRED_CLUB_TAXONOMIES, true) && ! $club) {
            throw ValidationException::withMessages(['catalog_club_id' => 'Select a club / city / town for this category. Add it in Club Master first if necessary.']);
        }

        if (! $club) {
            return;
        }

        $clubCounty = strtoupper((string) $club->catalog_county_code);
        $countryWideFifaClub = $taxonomy === 'fifa' && $clubCounty === '';

        if (
            ! $country
            || ! $club->belongsToOrganization($taxonomy)
            || (int) $club->catalog_country_id !== (int) $country->id
            || (! $countryWideFifaClub && $clubCounty !== strtoupper((string) $countyCode))
        ) {
            throw ValidationException::withMessages(['catalog_club_id' => 'The selected club does not belong to this category, country and county.']);
        }
    }

    private function resolveCounty(string $taxonomy, ?CatalogCountry $country, string $countyCode): ?array
    {
        if (! in_array($taxonomy, self::COUNTY_TAXONOMIES, true)) {
            return null;
        }

        if ($countyCode === '') {
            if (in_array($taxonomy, self::REQUIRED_COUNTY_TAXONOMIES, true)) {
                throw ValidationException::withMessages(['catalog_county_code' => 'Select a county / subdivision for this category.']);
            }
            return null;
        }

        if (! $country) {
            throw ValidationException::withMessages(['catalog_county_code' => 'Select a country before selecting a county.']);
        }

        $county = CatalogCounty::query()
            ->active()
            ->where('catalog_country_id', $country->id)
            ->where('code', $countyCode)
            ->first();
        if (! $county) {
            throw ValidationException::withMessages(['catalog_county_code' => 'The selected county does not belong to the selected country.']);
        }

        return ['code' => $county->code, 'name' => $county->name];
    }

    private function countyOptionsByCountry(): array
    {
        return CatalogCounty::query()
            ->active()
            ->with('country:id,code')
            ->orderBy('catalog_country_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'catalog_country_id', 'code', 'name'])
            ->filter(fn (CatalogCounty $county) => filled($county->country?->code))
            ->groupBy(fn (CatalogCounty $county) => strtoupper((string) $county->country->code))
            ->map(fn ($rows) => $rows->map(fn (CatalogCounty $county): array => [
                'code' => $county->code,
                'name' => $county->name,
            ])->values()->all())
            ->all();
    }

    private function ensureCategory(
        ?Category $parent,
        string $name,
        string $slug,
        string $taxonomy,
        ?CatalogCountry $country,
        ?string $countyCode,
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
            'catalog_county_code' => $countyCode,
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
