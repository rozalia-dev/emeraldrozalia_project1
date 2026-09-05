<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Category, ContentPage, Product, SeoAudit, SeoIssue, SeoRedirect, SeoSetting};
use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class SeoController extends Controller
{
    private const TABS = [
        'overview',
        'meta',
        'keywords',
        'content',
        'redirects',
        'sitemap',
        'robots',
        'schema',
    ];

    private const DEFAULT_ROBOTS_PATHS = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /account\nDisallow: /checkout\n\nSitemap: ";

    private const DEFAULT_KEYWORDS = [
        ['keyword' => 'emerald rozalia hats', 'position' => null, 'change' => null, 'target' => '/'],
        ['keyword' => 'luxury caps ireland', 'position' => null, 'change' => null, 'target' => '/shop'],
        ['keyword' => 'premium baseball caps', 'position' => null, 'change' => null, 'target' => '/category/baseball-caps'],
        ['keyword' => 'emerald signature cap', 'position' => null, 'change' => null, 'target' => '/product/emerald-signature-cap'],
        ['keyword' => 'franchise hats ireland', 'position' => null, 'change' => null, 'target' => '/franchise'],
    ];

    private const DEFAULT_SCHEMA = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Emerald Rozalia Limited',
        'url' => 'https://emeraldrozalia.com',
        'logo' => 'https://emeraldrozalia.com/assets/brand/emerald-rozalia-wordmark.png',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Limerick',
            'addressCountry' => 'IE',
        ],
    ];

    public function index(Request $request): View
    {
        $tab = $request->string('tab')->toString() ?: 'overview';
        abort_unless(in_array($tab, self::TABS, true), 404);

        $sources = $this->sources();
        $issues = $this->currentIssues($sources);
        $query = trim($request->string('q')->toString());

        if ($query !== '') {
            $needle = Str::lower($query);
            $issues = $issues->filter(fn (array $issue): bool => str_contains(Str::lower($issue['path'].' '.$issue['title'].' '.$issue['details']), $needle))->values();
        }

        $summary = $this->summary($sources, $issues);
        $healthBreakdown = $this->healthBreakdown($sources);
        $metaStatus = $this->metaStatus($sources);
        $keywords = $this->settingValue('keywords', self::DEFAULT_KEYWORDS);
        $robots = (string) $this->settingValue('robots_txt', $this->defaultRobots());
        $schema = $this->settingValue('organization_schema', self::DEFAULT_SCHEMA);
        $schemaJson = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $metaRows = $sources->filter(function (array $source) use ($query): bool {
            if ($query === '') {
                return true;
            }

            return str_contains(Str::lower($source['path'].' '.$source['title'].' '.$source['kind']), Str::lower($query));
        })->values();
        $redirects = SeoRedirect::query()->latest()->paginate(10)->withQueryString();
        $audits = SeoAudit::query()->latest('completed_at')->limit(6)->get();

        return view('admin.seo.index', compact(
            'audits',
            'healthBreakdown',
            'issues',
            'keywords',
            'metaRows',
            'metaStatus',
            'redirects',
            'robots',
            'schema',
            'schemaJson',
            'summary',
            'tab',
        ));
    }

    public function runAudit(): RedirectResponse
    {
        $sources = $this->sources();
        $definitions = $this->scanIssues($sources);
        $activeKeys = [];
        $auditedIssueTypes = $definitions->pluck('issue_type')->unique()->all();
        $audit = null;

        DB::transaction(function () use ($definitions, $sources, $auditedIssueTypes, &$activeKeys, &$audit): void {
            $now = now();
            foreach ($definitions as $definition) {
                $activeKeys[] = $this->issueKey($definition);
                SeoIssue::updateOrCreate(
                    [
                        'company_id' => $this->tenantId(),
                        'source_type' => $definition['source_type'],
                        'source_id' => $definition['source_id'],
                        'issue_type' => $definition['issue_type'],
                    ],
                    [
                        'path' => $definition['path'],
                        'title' => $definition['title'],
                        'severity' => $definition['severity'],
                        'status' => 'open',
                        'details' => $definition['details'],
                        'last_seen_at' => $now,
                        'resolved_at' => null,
                        'resolved_by' => null,
                    ],
                );
            }

            $existing = SeoIssue::query()->where('status', 'open')->get();
            foreach ($existing as $issue) {
                if (in_array($issue->issue_type, $auditedIssueTypes, true)
                    && ! in_array($issue->source_type.'|'.$issue->source_id.'|'.$issue->issue_type, $activeKeys, true)) {
                    $issue->update([
                        'status' => 'resolved',
                        'resolved_at' => $now,
                        'resolved_by' => auth()->id(),
                    ]);
                }
            }

            $audit = SeoAudit::create([
                'company_id' => $this->tenantId(),
                'score' => $this->healthScore($sources),
                'summary' => $this->summary($sources, $definitions),
                'created_by' => auth()->id(),
                'started_at' => $now,
                'completed_at' => $now,
            ]);
        });

        AuditTrail::record('seo.audit_completed', $audit, null, $audit?->toArray());

        return redirect()->route('admin.seo.dashboard', ['tab' => 'overview'])
            ->with('success', 'SEO audit completed. '.count($activeKeys).' issues are ready for review.');
    }

    public function checkBrokenLinks(): RedirectResponse
    {
        $sources = $this->sources();
        $definitions = $this->scanBrokenLinks($sources);
        $activeKeys = [];

        DB::transaction(function () use ($definitions, &$activeKeys): void {
            $now = now();

            foreach ($definitions as $definition) {
                $activeKeys[] = $this->issueKey($definition);
                SeoIssue::updateOrCreate(
                    [
                        'company_id' => $this->tenantId(),
                        'source_type' => $definition['source_type'],
                        'source_id' => $definition['source_id'],
                        'issue_type' => $definition['issue_type'],
                    ],
                    [
                        'path' => $definition['path'],
                        'title' => $definition['title'],
                        'severity' => $definition['severity'],
                        'status' => 'open',
                        'details' => $definition['details'],
                        'last_seen_at' => $now,
                        'resolved_at' => null,
                        'resolved_by' => null,
                    ],
                );
            }

            SeoIssue::query()
                ->where('issue_type', 'broken-internal-link')
                ->where('status', 'open')
                ->get()
                ->each(function (SeoIssue $issue) use ($activeKeys, $now): void {
                    if (! in_array($this->issueKey($issue->toArray()), $activeKeys, true)) {
                        $issue->update([
                            'status' => 'resolved',
                            'resolved_at' => $now,
                            'resolved_by' => auth()->id(),
                        ]);
                    }
                });

            $this->saveSetting('broken_links', count($definitions));
            $this->saveSetting('links_checked_at', $now->toIso8601String());
        });

        AuditTrail::record('seo.broken_links_checked');

        return redirect()->route('admin.seo.dashboard', ['tab' => 'content'])
            ->with('success', 'Broken-link check completed. '.count($definitions).' broken internal-link issues found.');
    }

    public function updateMeta(Request $request, string $sourceType, int $sourceId): RedirectResponse
    {
        abort_unless(in_array($sourceType, ['home', 'page', 'product', 'category'], true), 404);
        $data = $request->validate([
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'focus_keyword' => ['nullable', 'string', 'max:120'],
            'noindex' => ['nullable', 'boolean'],
        ]);

        $before = null;
        $subject = null;

        DB::transaction(function () use ($data, $sourceType, $sourceId, &$before, &$subject): void {
            $meta = [
                'title' => trim((string) ($data['meta_title'] ?? '')) ?: null,
                'description' => trim((string) ($data['meta_description'] ?? '')) ?: null,
            ];
            $seo = [
                'focus_keyword' => trim((string) ($data['focus_keyword'] ?? '')) ?: null,
                'noindex' => (bool) ($data['noindex'] ?? false),
            ];

            if ($sourceType === 'home') {
                $setting = $this->settingRecord('home_meta');
                $before = $setting?->toArray();
                $subject = $this->saveSetting('home_meta', array_merge((array) ($setting?->value ?? []), $meta, $seo));

                return;
            }

            $record = $this->sourceModel($sourceType, $sourceId);
            abort_unless($record, 404);
            $before = $record->toArray();
            $subject = $record;

            if ($sourceType === 'page') {
                $record->update(['meta' => array_merge((array) $record->meta, $meta, $seo)]);
            } else {
                $existingSeo = (array) $record->seo;
                $record->update([
                    'meta_title' => $meta['title'],
                    'meta_description' => $meta['description'],
                    'seo' => array_merge($existingSeo, $seo),
                ]);
            }
        });

        AuditTrail::record('seo.meta_updated', $subject, $before, $subject?->fresh()->toArray());

        return back()->with('success', 'SEO metadata saved.');
    }

    public function fixIssue(string $sourceType, int $sourceId, string $issueType): RedirectResponse
    {
        abort_unless(in_array($sourceType, ['home', 'page', 'product', 'category'], true), 404);
        abort_unless(in_array($issueType, [
            'missing-meta-title',
            'meta-title-too-long',
            'missing-meta-description',
            'meta-description-too-long',
            'missing-h1',
            'missing-alt-text',
            'missing-internal-links',
            'duplicate-meta-title',
        ], true), 404);

        $before = null;
        $subject = null;

        DB::transaction(function () use ($sourceType, $sourceId, $issueType, &$before, &$subject): void {
            $record = $sourceType === 'home' ? $this->settingRecord('home_meta') : $this->sourceModel($sourceType, $sourceId);
            abort_unless($record || $sourceType === 'home', 404);
            $before = $record?->toArray();

            $name = $sourceType === 'home' ? 'Emerald Rozalia | Irish Made Hats & Caps' : $this->sourceName($sourceType, $sourceId);
            $description = $sourceType === 'home'
                ? 'Discover Irish-made hats and caps from Emerald Rozalia Limited, proudly based in Limerick, Ireland.'
                : Str::limit($name.' by Emerald Rozalia Limited. Irish-made headwear from Limerick, Ireland.', 155, '');

            if ($sourceType === 'home') {
                $current = (array) ($record?->value ?? []);
                $current['title'] = $issueType === 'duplicate-meta-title' ? $name : ($current['title'] ?? $name);
                $current['description'] = $current['description'] ?? $description;
                $subject = $this->saveSetting('home_meta', $current);
            } elseif ($sourceType === 'page') {
                /** @var ContentPage $record */
                $meta = (array) $record->meta;
                if (in_array($issueType, ['missing-meta-title', 'meta-title-too-long', 'duplicate-meta-title'], true)) {
                    $meta['title'] = $issueType === 'meta-title-too-long' ? Str::limit((string) ($meta['title'] ?: $name), 60, '') : $name.' | Emerald Rozalia';
                }
                if (in_array($issueType, ['missing-meta-description', 'meta-description-too-long'], true)) {
                    $meta['description'] = $issueType === 'meta-description-too-long' ? Str::limit((string) ($meta['description'] ?: $description), 155, '') : $description;
                }
                if ($issueType === 'missing-h1' && ! preg_match('/<h1\b/i', (string) $record->body)) {
                    $record->body = '<h1>'.e($record->title).'</h1>'.(filled($record->body) ? "\n".$record->body : '');
                }
                if ($issueType === 'missing-alt-text') {
                    $alt = e(Str::limit($name, 80, ''));
                    $record->body = preg_replace_callback(
                        '/<img\b(?![^>]*\balt\s*=)([^>]*)>/i',
                        static fn (array $match): string => '<img alt="'.$alt.'"'.$match[1].'>',
                        (string) $record->body,
                    ) ?: $record->body;
                }
                if ($issueType === 'missing-internal-links') {
                    $record->body = rtrim((string) $record->body)."\n<p><a href=\"/shop\">Explore the Emerald Rozalia collection.</a></p>";
                }
                $record->update(['meta' => $meta, 'body' => $record->body]);
                $subject = $record;
            } else {
                /** @var Product|Category $record */
                $title = (string) ($record->meta_title ?: $name.' | Emerald Rozalia');
                $desc = (string) ($record->meta_description ?: $description);
                $replacementTitle = $issueType === 'duplicate-meta-title' ? $name.' | Emerald Rozalia' : $title;
                $record->update([
                    'meta_title' => in_array($issueType, ['missing-meta-title', 'meta-title-too-long', 'duplicate-meta-title'], true) ? Str::limit($replacementTitle, 60, '') : $record->meta_title,
                    'meta_description' => in_array($issueType, ['missing-meta-description', 'meta-description-too-long'], true) ? Str::limit($desc, 155, '') : $record->meta_description,
                ]);
                $subject = $record;
            }
        });

        $issue = SeoIssue::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('issue_type', $issueType)
            ->where('status', 'open')
            ->first();
        if ($issue) {
            $issue->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => auth()->id()]);
        }

        AuditTrail::record('seo.issue_fixed', $subject, $before, $subject?->fresh()->toArray());

        return back()->with('success', 'SEO issue fixed and recorded in the audit log.');
    }

    public function resolveIssue(SeoIssue $issue): RedirectResponse
    {
        $before = $issue->toArray();
        $issue->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => auth()->id()]);
        AuditTrail::record('seo.issue_resolved', $issue, $before, $issue->fresh()->toArray());

        return back()->with('success', 'SEO issue marked as resolved.');
    }

    public function saveKeywords(Request $request): RedirectResponse
    {
        $data = $request->validate(['keywords' => ['nullable', 'string', 'max:5000']]);
        $existing = collect($this->settingValue('keywords', self::DEFAULT_KEYWORDS))->keyBy('keyword');
        $keywords = collect(preg_split('/\R+/', (string) ($data['keywords'] ?? '')))
            ->map(fn (?string $keyword): string => trim((string) $keyword))
            ->filter()
            ->unique()
            ->take(50)
            ->map(fn (string $keyword): array => [
                'keyword' => $keyword,
                'position' => data_get($existing->get($keyword), 'position'),
                'change' => data_get($existing->get($keyword), 'change'),
                'target' => data_get($existing->get($keyword), 'target'),
            ])->values()->all();

        $before = $this->settingRecord('keywords')?->toArray();
        $setting = $this->saveSetting('keywords', $keywords);
        AuditTrail::record('seo.keywords_updated', $setting, $before, $setting->fresh()->toArray());

        return back()->with('success', 'Target keywords saved. Connect Search Console later to populate live positions.');
    }

    public function updateRobots(Request $request): RedirectResponse
    {
        $data = $request->validate(['robots_txt' => ['required', 'string', 'max:10000']]);
        $before = $this->settingRecord('robots_txt')?->toArray();
        $setting = $this->saveSetting('robots_txt', rtrim($data['robots_txt'])."\n");
        AuditTrail::record('seo.robots_updated', $setting, $before, $setting->fresh()->toArray());

        return back()->with('success', 'robots.txt rules saved.');
    }

    public function updateSchema(Request $request): RedirectResponse
    {
        $data = $request->validate(['schema_json' => ['required', 'json', 'max:20000']]);
        $value = json_decode($data['schema_json'], true, 512, JSON_THROW_ON_ERROR);
        abort_unless(is_array($value), 422, 'Schema must be a JSON object.');
        $before = $this->settingRecord('organization_schema')?->toArray();
        $setting = $this->saveSetting('organization_schema', $value);
        AuditTrail::record('seo.schema_updated', $setting, $before, $setting->fresh()->toArray());

        return back()->with('success', 'Structured data schema saved.');
    }

    public function storeRedirect(Request $request): RedirectResponse
    {
        $request->merge(['from_path' => '/'.ltrim((string) $request->input('from_path'), '/')]);
        $data = $request->validate([
            'from_path' => [
                'required',
                'string',
                'max:255',
                'regex:/^\//',
                Rule::unique('seo_redirects', 'from_path')->where(fn ($query) => $query->where('company_id', $this->tenantId())),
            ],
            'to_path' => ['required', 'string', 'max:255', 'regex:/^(?:\/(?!\/)|https?:\/\/)/'],
            'status_code' => ['required', 'integer', 'in:301,302,307,308'],
            'active' => ['nullable', 'boolean'],
        ]);
        $redirect = SeoRedirect::create($data + ['active' => (bool) ($data['active'] ?? true)]);
        AuditTrail::record('seo.redirect_created', $redirect, null, $redirect->toArray());

        return back()->with('success', 'Redirect added to the 301 manager.');
    }

    public function destroyRedirect(SeoRedirect $redirect): RedirectResponse
    {
        $before = $redirect->toArray();
        AuditTrail::record('seo.redirect_deleted', $redirect, $before, null);
        $redirect->delete();

        return back()->with('success', 'Redirect removed.');
    }

    public function generateSitemap(): RedirectResponse
    {
        $sources = $this->sources();
        $xml = $this->sitemapXml($sources);
        $before = $this->settingRecord('sitemap_xml')?->toArray();
        $setting = $this->saveSetting('sitemap_xml', $xml);
        $this->saveSetting('sitemap_generated_at', now()->toIso8601String());
        AuditTrail::record('seo.sitemap_generated', $setting, $before, ['url' => route('seo.sitemap')]);

        return back()->with('success', 'XML sitemap generated for '.$sources->where('indexed', true)->count().' indexable pages.');
    }

    public function robots()
    {
        return response((string) $this->settingValue('robots_txt', $this->defaultRobots()), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function sitemap()
    {
        $xml = $this->settingValue('sitemap_xml');
        if (! is_string($xml) || ! str_starts_with(trim($xml), '<?xml')) {
            $xml = $this->sitemapXml($this->sources());
        }

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function redirectFallback(Request $request)
    {
        abort_unless(in_array($request->method(), ['GET', 'HEAD'], true), 404);
        $fromPath = '/'.ltrim($request->path(), '/');
        $redirect = SeoRedirect::query()
            ->where('from_path', $fromPath)
            ->where('active', true)
            ->first();

        $targetPath = $redirect ? parse_url($redirect->to_path, PHP_URL_PATH) : null;
        $targetPath = is_string($targetPath) ? '/'.ltrim($targetPath, '/') : null;
        abort_unless($redirect && $targetPath !== $fromPath, 404);

        return redirect()->to($redirect->to_path, (int) $redirect->status_code);
    }

    private function sources(): Collection
    {
        $homeMeta = (array) $this->settingValue('home_meta', []);
        $sources = collect([[
            'source_type' => 'home',
            'source_id' => 0,
            'path' => '/',
            'title' => 'Home',
            'kind' => 'Homepage',
            'meta_title' => $homeMeta['title'] ?? null,
            'meta_description' => $homeMeta['description'] ?? null,
            'focus_keyword' => $homeMeta['focus_keyword'] ?? null,
            'noindex' => (bool) ($homeMeta['noindex'] ?? false),
            'has_h1' => true,
            'has_alt_text' => true,
            'has_internal_links' => true,
            'indexed' => ! (bool) ($homeMeta['noindex'] ?? false),
            'word_count' => 0,
            'updated_at' => now(),
        ]]);

        $pages = ContentPage::query()
            ->whereNotIn('status', ['archived', 'unpublished'])
            ->latest('updated_at')
            ->get()
            ->map(function (ContentPage $page): array {
                $meta = (array) $page->meta;
                $body = (string) $page->body;

                return [
                    'source_type' => 'page',
                    'source_id' => (int) $page->id,
                    'path' => '/'.$page->slug,
                    'title' => $page->title,
                    'kind' => 'Content page',
                    'meta_title' => $meta['title'] ?? null,
                    'meta_description' => $meta['description'] ?? null,
                    'focus_keyword' => $meta['focus_keyword'] ?? null,
                    'noindex' => (bool) ($meta['noindex'] ?? false),
                    'has_h1' => (bool) preg_match('/<h1\b/i', $body),
                    'has_alt_text' => $this->hasAltText($body),
                    'has_internal_links' => $this->hasInternalLinks($body),
                    'indexed' => $page->status === 'published' && ! (bool) ($meta['noindex'] ?? false),
                    'word_count' => str_word_count(strip_tags($body)),
                    'content' => $body,
                    'updated_at' => $page->updated_at,
                ];
            });

        $products = Product::query()
            ->where('is_active', true)
            ->with('category')
            ->latest('updated_at')
            ->get()
            ->map(function (Product $product): array {
                $seo = (array) $product->seo;

                return [
                    'source_type' => 'product',
                    'source_id' => (int) $product->id,
                    'path' => '/product/'.$product->slug,
                    'title' => $product->name,
                    'kind' => 'Product',
                    'meta_title' => $product->meta_title,
                    'meta_description' => $product->meta_description,
                    'focus_keyword' => $seo['focus_keyword'] ?? null,
                    'noindex' => (bool) ($seo['noindex'] ?? false),
                    'has_h1' => true,
                    'has_alt_text' => true,
                    'has_internal_links' => true,
                    'indexed' => ! (bool) ($seo['noindex'] ?? false),
                    'word_count' => str_word_count(strip_tags((string) $product->description)),
                    'content' => (string) $product->description,
                    'updated_at' => $product->updated_at,
                ];
            });

        $categories = Category::query()
            ->where('is_active', true)
            ->latest('updated_at')
            ->get()
            ->map(function (Category $category): array {
                $seo = (array) $category->seo;

                return [
                    'source_type' => 'category',
                    'source_id' => (int) $category->id,
                    'path' => '/category/'.$category->slug,
                    'title' => $category->name,
                    'kind' => 'Category',
                    'meta_title' => $category->meta_title,
                    'meta_description' => $category->meta_description,
                    'focus_keyword' => $seo['focus_keyword'] ?? null,
                    'noindex' => (bool) ($seo['noindex'] ?? false),
                    'has_h1' => true,
                    'has_alt_text' => true,
                    'has_internal_links' => true,
                    'indexed' => ! (bool) ($seo['noindex'] ?? false),
                    'word_count' => str_word_count(strip_tags((string) $category->description)),
                    'content' => (string) $category->description,
                    'updated_at' => $category->updated_at,
                ];
            });

        return $sources->concat($pages)->concat($products)->concat($categories)->values();
    }

    private function scanIssues(Collection $sources): Collection
    {
        $issues = $sources->flatMap(function (array $source): Collection {
            $items = collect();
            $add = function (string $type, string $title, string $severity, string $details) use (&$items, $source): void {
                $items->push([
                    'source_type' => $source['source_type'],
                    'source_id' => $source['source_id'],
                    'path' => $source['path'],
                    'title' => $title,
                    'issue_type' => $type,
                    'severity' => $severity,
                    'status' => 'open',
                    'details' => $details,
                    'last_updated' => $source['updated_at'],
                ]);
            };

            if (blank($source['meta_title'])) {
                $add('missing-meta-title', 'Missing meta title', 'high', 'Add a clear title of 50–60 characters for this page.');
            } elseif (mb_strlen((string) $source['meta_title']) > 60) {
                $add('meta-title-too-long', 'Meta title is too long', 'medium', 'Keep the title within 60 characters to reduce truncation in results.');
            }

            if (blank($source['meta_description'])) {
                $add('missing-meta-description', 'Missing meta description', 'medium', 'Add a useful description of 120–155 characters.');
            } elseif (mb_strlen((string) $source['meta_description']) > 160) {
                $add('meta-description-too-long', 'Meta description is too long', 'medium', 'Keep the description within 155–160 characters.');
            }

            if ($source['source_type'] === 'page' && ! $source['has_h1']) {
                $add('missing-h1', 'Missing H1 heading', 'high', 'Add one clear H1 heading that matches the page purpose.');
            }

            if ($source['source_type'] === 'page' && ! $source['has_alt_text']) {
                $add('missing-alt-text', 'Image missing alt text', 'medium', 'Add descriptive alt text to every content image for accessibility and image search.');
            }

            if ($source['source_type'] === 'page' && ! $source['has_internal_links']) {
                $add('missing-internal-links', 'No internal links', 'low', 'Add at least one useful link to another Emerald Rozalia page.');
            }

            return $items;
        })->values();

        $duplicates = $sources->filter(fn (array $source): bool => filled($source['meta_title']))
            ->groupBy(fn (array $source): string => Str::lower(trim((string) $source['meta_title'])));
        foreach ($duplicates as $sameTitle) {
            if ($sameTitle->count() < 2) {
                continue;
            }
            foreach ($sameTitle->slice(1) as $source) {
                $issues->push([
                    'source_type' => $source['source_type'],
                    'source_id' => $source['source_id'],
                    'path' => $source['path'],
                    'title' => 'Duplicate meta title',
                    'issue_type' => 'duplicate-meta-title',
                    'severity' => 'low',
                    'status' => 'open',
                    'details' => 'This title is shared with another indexed page.',
                    'last_updated' => $source['updated_at'],
                ]);
            }
        }

        return $issues->sortBy(fn (array $issue): int => ['high' => 0, 'medium' => 1, 'low' => 2][$issue['severity']] ?? 3)->values();
    }

    private function scanBrokenLinks(Collection $sources): Collection
    {
        $knownPaths = collect([
            '/',
            '/shop',
            '/collections',
            '/new-arrivals',
            '/virtual-tryon',
            '/irish-traditional',
            '/irish-heritage',
            '/factory',
            '/corporate-orders',
            '/bulk-orders',
            '/franchise',
            '/careers',
            '/global-network',
            '/contact',
            '/login',
            '/register',
            '/account',
            '/cart',
            '/checkout',
            '/robots.txt',
            '/sitemap.xml',
        ])->merge($sources->pluck('path'))
            ->merge(SeoRedirect::query()->where('active', true)->pluck('from_path'))
            ->map(fn ($path): string => '/'.ltrim((string) $path, '/'))
            ->unique()
            ->flip();

        return $sources->flatMap(function (array $source) use ($knownPaths): Collection {
            $content = (string) ($source['content'] ?? '');
            if ($content === '' || ! preg_match('/\bhref\s*=/i', $content)) {
                return collect();
            }

            preg_match_all('/\bhref\s*=\s*["\']([^"\']+)["\']/i', $content, $matches);
            $broken = collect($matches[1] ?? [])
                ->map(fn (string $href): ?string => $this->internalLinkPath($href))
                ->filter()
                ->reject(fn (string $path): bool => $knownPaths->has($path))
                ->unique()
                ->values();

            if ($broken->isEmpty()) {
                return collect();
            }

            $paths = $broken->take(5)->implode(', ');
            if ($broken->count() > 5) {
                $paths .= ' and '.($broken->count() - 5).' more';
            }

            return collect([[
                'source_type' => $source['source_type'],
                'source_id' => $source['source_id'],
                'path' => $source['path'],
                'title' => 'Broken internal links',
                'issue_type' => 'broken-internal-link',
                'severity' => 'high',
                'status' => 'open',
                'details' => 'Unresolved paths: '.$paths,
                'last_updated' => $source['updated_at'],
            ]]);
        })->values();
    }

    private function internalLinkPath(string $href): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, '//') || preg_match('/^(?:mailto|tel|javascript|data):/i', $href)) {
            return null;
        }

        $parsed = parse_url($href);
        if ($parsed === false) {
            return null;
        }

        if (isset($parsed['host'])) {
            $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
            if ($appHost === '' || strtolower((string) $parsed['host']) !== $appHost) {
                return null;
            }
        }

        $path = $parsed['path'] ?? (isset($parsed['host']) ? '/' : null);
        if (! is_string($path) || ! str_starts_with($path, '/')) {
            return null;
        }

        if (str_starts_with($path, '/assets/') || str_starts_with($path, '/storage/') || preg_match('/\.(?:css|js|jpe?g|png|gif|svg|webp|avif|pdf|xml)$/i', $path)) {
            return null;
        }

        return '/'.ltrim($path, '/');
    }

    private function currentIssues(Collection $sources): Collection
    {
        if (SeoIssue::query()->exists()) {
            return SeoIssue::query()
                ->where('status', 'open')
                ->latest('last_seen_at')
                ->get()
                ->map(fn (SeoIssue $issue): array => [
                    'source_type' => $issue->source_type,
                    'source_id' => (int) $issue->source_id,
                    'path' => $issue->path,
                    'title' => $issue->title,
                    'issue_type' => $issue->issue_type,
                    'severity' => $issue->severity,
                    'status' => $issue->status,
                    'details' => $issue->details,
                    'last_updated' => $issue->last_seen_at ?: $issue->updated_at,
                    'uuid' => $issue->uuid,
                    'persistent' => true,
                ])->values();
        }

        return $this->scanIssues($sources)->map(fn (array $issue): array => $issue + ['persistent' => false]);
    }

    private function summary(Collection $sources, Collection $issues): array
    {
        $audit = SeoAudit::query()->latest('completed_at')->first();
        $keywords = $this->settingValue('keywords', self::DEFAULT_KEYWORDS);
        $sitemapGenerated = $this->settingValue('sitemap_generated_at');

        return [
            'health_score' => $this->healthScore($sources),
            'total_pages' => $sources->count(),
            'indexed_pages' => $sources->where('indexed', true)->count(),
            'noindex_pages' => $sources->where('noindex', true)->count(),
            'organic_traffic' => (int) $this->settingValue('organic_traffic', 0),
            'top_keywords' => count(is_array($keywords) ? $keywords : []),
            'backlinks' => (int) $this->settingValue('backlinks', 0),
            'pages_with_issues' => $issues->map(fn (array $issue): string => $issue['source_type'].'|'.$issue['source_id'])->unique()->count(),
            'pages_crawled' => data_get($audit?->summary, 'total_pages', 0),
            'last_crawl' => $audit?->completed_at,
            'redirects' => SeoRedirect::query()->where('status_code', 301)->count(),
            'broken_links' => $issues->where('issue_type', 'broken-internal-link')->count()
                ?: (int) $this->settingValue('broken_links', 0),
            'sitemap_generated_at' => $sitemapGenerated,
        ];
    }

    private function healthScore(Collection $sources): int
    {
        if ($sources->isEmpty()) {
            return 0;
        }

        $checks = 0;
        $passed = 0;
        foreach ($sources as $source) {
            $checks += 3;
            $passed += filled($source['meta_title']) && mb_strlen((string) $source['meta_title']) <= 60 ? 1 : 0;
            $passed += filled($source['meta_description']) && mb_strlen((string) $source['meta_description']) <= 160 ? 1 : 0;
            $passed += $source['has_h1'] ? 1 : 0;
        }

        return $checks > 0 ? (int) round(($passed / $checks) * 100) : 0;
    }

    private function healthBreakdown(Collection $sources): array
    {
        $percent = function (callable $test) use ($sources): int {
            if ($sources->isEmpty()) {
                return 0;
            }

            return (int) round(($sources->filter($test)->count() / $sources->count()) * 100);
        };

        $schema = $this->settingValue('organization_schema', self::DEFAULT_SCHEMA);

        return [
            ['label' => 'Meta Title', 'score' => $percent(fn (array $source): bool => filled($source['meta_title']) && mb_strlen((string) $source['meta_title']) <= 60)],
            ['label' => 'Meta Description', 'score' => $percent(fn (array $source): bool => filled($source['meta_description']) && mb_strlen((string) $source['meta_description']) <= 160)],
            ['label' => 'Headings (H1–H6)', 'score' => $percent(fn (array $source): bool => $source['has_h1'])],
            ['label' => 'Images (Alt Text)', 'score' => $percent(fn (array $source): bool => $source['has_alt_text'])],
            ['label' => 'Internal Linking', 'score' => $percent(fn (array $source): bool => $source['has_internal_links'])],
            ['label' => 'Page Speed (Mobile)', 'score' => null],
            ['label' => 'Structured Data', 'score' => is_array($schema) && filled($schema['@type'] ?? null) ? 100 : 0],
        ];
    }

    private function hasAltText(string $html): bool
    {
        preg_match_all('/<img\b[^>]*>/i', $html, $matches);

        foreach ($matches[0] as $image) {
            if (! preg_match('/\balt\s*=\s*["\']\s*[^"\']+\s*["\']/i', $image)) {
                return false;
            }
        }

        return true;
    }

    private function hasInternalLinks(string $html): bool
    {
        return (bool) preg_match('/<a\b[^>]*href\s*=\s*["\'](?:\/|https?:\/\/emeraldrozalia\.com)/i', $html);
    }

    private function metaStatus(Collection $sources): array
    {
        $optimized = $sources->filter(fn (array $source): bool => filled($source['meta_title']) && filled($source['meta_description']) && mb_strlen((string) $source['meta_title']) <= 60 && mb_strlen((string) $source['meta_description']) <= 160)->count();
        $missing = $sources->filter(fn (array $source): bool => blank($source['meta_title']) || blank($source['meta_description']))->count();
        $tooLong = $sources->filter(fn (array $source): bool => mb_strlen((string) $source['meta_title']) > 60 || mb_strlen((string) $source['meta_description']) > 160)->count();
        $duplicates = $sources->filter(fn (array $source): bool => filled($source['meta_title']))
            ->groupBy(fn (array $source): string => Str::lower(trim((string) $source['meta_title'])))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->sum(fn (Collection $group): int => $group->count() - 1);

        return [
            'total' => $sources->count(),
            'optimized' => $optimized,
            'missing' => $missing,
            'duplicate' => $duplicates,
            'too_long' => $tooLong,
        ];
    }

    private function sourceModel(string $sourceType, int $sourceId): ?Model
    {
        return match ($sourceType) {
            'page' => ContentPage::query()->find($sourceId),
            'product' => Product::query()->find($sourceId),
            'category' => Category::query()->find($sourceId),
            default => null,
        };
    }

    private function sourceName(string $sourceType, int $sourceId): string
    {
        $model = $this->sourceModel($sourceType, $sourceId);

        return (string) ($model?->title ?: $model?->name ?: 'Emerald Rozalia page');
    }

    private function issueKey(array $issue): string
    {
        return $issue['source_type'].'|'.$issue['source_id'].'|'.$issue['issue_type'];
    }

    private function tenantId(): ?int
    {
        if (session()->has('company_id')) {
            return (int) session('company_id');
        }

        return auth()->user()?->companies()->wherePivot('is_default', true)->value('companies.id');
    }

    private function settingRecord(string $key): ?SeoSetting
    {
        $query = SeoSetting::query()->where('key', $key);
        if ($this->tenantId()) {
            $query->where('company_id', $this->tenantId());
        }

        return $query->first();
    }

    private function settingValue(string $key, mixed $default = null): mixed
    {
        return $this->settingRecord($key)?->value ?? $default;
    }

    private function defaultRobots(): string
    {
        return self::DEFAULT_ROBOTS_PATHS.rtrim((string) config('app.url', 'https://emeraldrozalia.com'), '/')."/sitemap.xml\n";
    }

    private function saveSetting(string $key, mixed $value): SeoSetting
    {
        $attributes = ['key' => $key];
        if ($this->tenantId()) {
            $attributes['company_id'] = $this->tenantId();
        }

        return SeoSetting::updateOrCreate($attributes, ['value' => $value]);
    }

    private function sitemapXml(Collection $sources): string
    {
        $base = rtrim((string) config('app.url', 'https://emeraldrozalia.com'), '/');
        $urls = $sources->filter(fn (array $source): bool => $source['indexed'])->map(function (array $source) use ($base): string {
            $loc = htmlspecialchars($base.$source['path'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $lastmod = $source['updated_at']?->toAtomString() ?: now()->toAtomString();

            return "    <url>\n        <loc>{$loc}</loc>\n        <lastmod>{$lastmod}</lastmod>\n    </url>";
        })->implode("\n");

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$urls}\n</urlset>\n";
    }
}
