<?php

namespace App\Http\Controllers;

use App\Http\Requests\{CatalogFilterRequest, PublicInquiryRequest};
use App\Models\{Banner,Category,ContentPage,Conversation,FranchiseApplication,FranchiseStore,Inquiry,Product,ProductCollection};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\AuditTrail;
use App\Services\PublicMediaResolver;
use App\Services\SalesQuoteService;
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

        return [
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'homeProducts' => $homeProducts,
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
        return view('site.collections', [
            'categories' => Category::withCount(['products' => fn ($q) => $q->published()])->where('is_active', true)->orderBy('sort_order')->get(),
            'bestsellers' => Product::published()->with('media')->latest()->limit(6)->get(),
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
        $stores = FranchiseStore::query()
            ->whereIn('status', ['active', 'open'])
            ->get(['territory', 'address']);
        $activeProducts = Product::published()->count();
        $countries = $stores
            ->map(fn (FranchiseStore $store): ?string => strtoupper(trim((string) data_get($store->address, 'country'))))
            ->filter()
            ->unique()
            ->count();

        return view('site.franchise', [
            'franchiseMetrics' => [
                [
                    'icon' => 'home',
                    'value' => $stores->count() ?: '—',
                    'label' => "Active retail partner".($stores->count() === 1 ? '' : 's').'<br>recorded',
                    'state' => $stores->isNotEmpty() ? 'live' : 'not-configured',
                ],
                [
                    'icon' => 'globe',
                    'value' => $countries ?: '—',
                    'label' => ($countries === 1 ? 'Country' : 'Countries').'<br>recorded',
                    'state' => $countries ? 'live' : 'not-configured',
                ],
                [
                    'icon' => 'tag',
                    'value' => $activeProducts ?: '—',
                    'label' => 'Active product'.($activeProducts === 1 ? '' : 's').'<br>in catalog',
                    'state' => $activeProducts ? 'live' : 'not-configured',
                ],
                [
                    'icon' => 'calendar',
                    'value' => '—',
                    'label' => 'Heritage year<br>not configured',
                    'state' => 'not-configured',
                ],
            ],
        ]);
    }
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

        $rawCategories = $request->input('category', []);
        if (is_string($rawCategories)) $rawCategories = [$rawCategories];
        $selectedCategories = array_values(array_unique(array_filter((array) $rawCategories, fn ($value) => is_string($value) && trim($value) !== '')));
        if ($activeCategory) $selectedCategories = [$activeCategory->slug];
        if ($selectedCategories) {
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->whereIn('slug', $selectedCategories));
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

        $categories = Category::query()
            ->websiteVisible()
            ->withCount(['products' => fn ($productQuery) => $productQuery->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

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
        $related = Product::published()
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with('media')
            ->limit(4)->get();
        return view('site.product', compact('product', 'related', 'spinFrames', 'spinViewerData'));
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
        $country = $d['country'] ?? null;
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
            DB::transaction(function () use ($d, $meeting, $correlationId, $idempotencyKey, $requestHash, $customerId, $messageIdempotencyKey, $consentCapturedAt): void {
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
                'preferred_location' => $d['company'] ?? null,
                'business_experience' => $d['message'] ?? null,
                'status' => 'new',
                'data' => ['source' => 'public_franchise_form'],
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
                $body = $d['message'] ?? 'Public form submission';
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
