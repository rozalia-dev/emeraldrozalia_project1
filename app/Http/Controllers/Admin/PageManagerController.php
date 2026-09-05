<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ContentPage, PageRevision};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PageManagerController extends Controller
{
    private const STATUSES = ['draft', 'review', 'scheduled', 'published', 'unpublished', 'archived'];

    private function validated(Request $request, ?ContentPage $page = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'alpha_dash', 'max:180', 'unique:content_pages,slug,' . ($page?->id ?? 'NULL')],
            'intro' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:100000'],
            'status' => ['required', 'in:' . implode(',', self::STATUSES)],
            'locale' => ['required', 'string', 'max:10'],
            'template' => ['required', 'string', 'max:80'],
            'navigation_visible' => ['nullable', 'boolean'],
            'scheduled_for' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'show_in_footer' => ['nullable', 'boolean'],
            'indexable' => ['nullable', 'boolean'],
            'login_required' => ['nullable', 'boolean'],
            'visibility' => ['nullable', 'in:public,private'],
            'country_restriction' => ['nullable', 'string', 'max:120'],
            'devices' => ['nullable', 'array'],
            'devices.*' => ['string', 'in:desktop,tablet,mobile'],
            'sections' => ['nullable', 'json', 'max:200000'],
        ]);
    }

    private function normalise(Request $request, array $data, ?ContentPage $page = null): array
    {
        $meta = is_array($page?->meta) ? $page->meta : [];
        $meta['title'] = $data['meta_title'] ?? null;
        $meta['description'] = $data['meta_description'] ?? null;
        $meta['keywords'] = $data['meta_keywords'] ?? null;
        $meta['settings'] = [
            'visibility' => $data['visibility'] ?? 'public',
            'show_in_footer' => $request->boolean('show_in_footer'),
            'indexable' => $request->has('indexable') ? $request->boolean('indexable') : false,
            'login_required' => $request->boolean('login_required'),
            'country_restriction' => $data['country_restriction'] ?? null,
            'devices' => array_values($request->has('devices') ? ($data['devices'] ?? []) : []),
        ];

        unset(
            $data['meta_title'],
            $data['meta_description'],
            $data['meta_keywords'],
            $data['show_in_footer'],
            $data['indexable'],
            $data['login_required'],
            $data['visibility'],
            $data['country_restriction'],
            $data['devices'],
            $data['sections']
        );

        if (($data['status'] ?? null) === 'scheduled' && empty($data['scheduled_for'])) {
            $data['scheduled_for'] = now()->addDay();
        }

        return $data + [
            'navigation_visible' => $request->boolean('navigation_visible'),
            'meta' => $meta,
            'published_at' => ($data['status'] ?? null) === 'published' ? now() : null,
            'archived_at' => ($data['status'] ?? null) === 'archived' ? now() : null,
        ];
    }

    private function sectionRows(?string $payload): array
    {
        if (!$payload) {
            return [];
        }

        $rows = json_decode($payload, true);
        if (!is_array($rows)) {
            return [];
        }

        return collect($rows)->values()->map(function ($row, $index) {
            $settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
            if (isset($row['content']) && !isset($settings['content'])) {
                $settings['content'] = (string) $row['content'];
            }

            return [
                'type' => Str::limit(Str::slug((string) ($row['type'] ?? 'content')), 60, ''),
                'label' => Str::limit((string) ($row['label'] ?? 'Content block'), 180, ''),
                'sort_order' => $index,
                'settings' => $settings,
                'visible' => (bool) ($row['visible'] ?? true),
            ];
        })->all();
    }

    private function syncSections(ContentPage $page, ?string $payload): void
    {
        $page->sections()->delete();
        foreach ($this->sectionRows($payload) as $section) {
            $page->sections()->create($section);
        }
    }

    private function snapshot(ContentPage $page, string $reason): void
    {
        $page->load('sections');
        $snapshot = $page->fresh()->toArray();
        $snapshot['sections'] = $page->sections->map(fn ($section) => $section->only(['type', 'label', 'sort_order', 'settings', 'visible']))->values()->all();

        $page->revisions()->create([
            'user_id' => auth()->id(),
            'version' => ((int) $page->revisions()->max('version')) + 1,
            'snapshot' => $snapshot,
            'reason' => $reason,
        ]);
    }

    private function typeQuery($query, string $type)
    {
        return match ($type) {
            'landing' => $query->whereIn('template', ['landing', 'landing-page']),
            'legal' => $query->whereIn('template', ['legal', 'policy']),
            'system' => $query->where('template', 'system'),
            'standard' => $query->whereNotIn('template', ['landing', 'landing-page', 'legal', 'policy', 'system']),
            default => $query,
        };
    }

    public function create(Request $request)
    {
        $template = (string) $request->query('template', 'standard');
        $template = in_array($template, ['standard', 'landing', 'legal', 'system'], true) ? $template : 'standard';
        $page = new ContentPage([
            'status' => 'draft',
            'locale' => 'en',
            'template' => $template,
            'navigation_visible' => true,
            'meta' => [
                'title' => null,
                'description' => null,
                'keywords' => null,
                'settings' => [
                    'visibility' => 'public',
                    'indexable' => true,
                    'devices' => ['desktop', 'tablet', 'mobile'],
                ],
            ],
        ]);

        return view('admin.pages.builder', compact('page'));
    }

    public function edit(ContentPage $page)
    {
        $page->load(['sections', 'revisions']);

        return view('admin.pages.builder', compact('page'));
    }

    public function index(Request $request)
    {
        $tab = (string) $request->query('tab', 'all');
        $type = (string) $request->query('type', '');
        $query = ContentPage::query()->with(['sections', 'revisions'])->latest('updated_at');

        if ($tab === 'trash') {
            $query->onlyTrashed();
        } else {
            $query->whereNull('deleted_at');
            match ($tab) {
                'published' => $query->where('status', 'published'),
                'drafts' => $query->whereIn('status', ['draft', 'review', 'scheduled']),
                'hidden' => $query->whereIn('status', ['unpublished', 'archived']),
                'landing' => $query->whereIn('template', ['landing', 'landing-page']),
                'legal' => $query->whereIn('template', ['legal', 'policy']),
                'system' => $query->where('template', 'system'),
                default => null,
            };
        }

        if ($request->filled('status')) {
            $status = (string) $request->query('status');
            if ($status === 'trash') {
                $query->onlyTrashed();
            } else {
                $query->where('status', $status);
            }
        }

        $query->when($request->filled('q'), fn ($pages) => $pages->where(fn ($search) => $search
            ->where('title', 'like', '%' . $request->query('q') . '%')
            ->orWhere('slug', 'like', '%' . $request->query('q') . '%')));
        $query->when($request->filled('locale'), fn ($pages) => $pages->where('locale', (string) $request->query('locale')));
        $query->when($type !== '', fn ($pages) => $this->typeQuery($pages, $type));

        $pages = $query->paginate(12)->withQueryString();
        $allPages = ContentPage::withTrashed()->with('sections')->latest('updated_at')->get();
        $livePages = $allPages->filter(fn ($page) => !$page->trashed());
        $countBy = fn (callable $filter) => $livePages->filter($filter)->count();
        $pageViews = (int) $livePages->sum(fn ($page) => (int) data_get($page->meta, 'analytics.views', data_get($page->meta, 'views', 0)));
        $stats = [
            'total' => $livePages->count(),
            'published' => $countBy(fn ($page) => $page->status === 'published'),
            'drafts' => $countBy(fn ($page) => in_array($page->status, ['draft', 'review', 'scheduled'], true)),
            'hidden' => $countBy(fn ($page) => in_array($page->status, ['unpublished', 'archived'], true)),
            'trash' => $allPages->filter->trashed()->count(),
            'page_views' => $pageViews,
        ];
        $typeCounts = [
            'standard' => $countBy(fn ($page) => !in_array($page->template, ['landing', 'landing-page', 'legal', 'policy', 'system'], true)),
            'landing' => $countBy(fn ($page) => in_array($page->template, ['landing', 'landing-page'], true)),
            'legal' => $countBy(fn ($page) => in_array($page->template, ['legal', 'policy'], true)),
            'system' => $countBy(fn ($page) => $page->template === 'system'),
            'other' => 0,
        ];
        $editingPage = $request->filled('edit')
            ? ContentPage::withTrashed()->with(['sections', 'revisions'])->find($request->integer('edit'))
            : $pages->first();

        return view('admin.pages.index', compact('pages', 'allPages', 'editingPage', 'stats', 'typeCounts', 'tab', 'type'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($request, $data, &$page) {
            $page = ContentPage::create($this->normalise($request, $data));
            $this->syncSections($page, $data['sections'] ?? null);
            $this->snapshot($page, 'Created');
        });

        return redirect()->route('admin.pages', ['edit' => $page->id])->with('success', 'Page created and saved to the builder.');
    }

    public function update(Request $request, ContentPage $page)
    {
        $data = $this->validated($request, $page);
        DB::transaction(function () use ($request, $data, $page) {
            $this->snapshot($page, 'Before update');
            $page->update($this->normalise($request, $data, $page));
            $this->syncSections($page, $data['sections'] ?? null);
        });

        return redirect()->route('admin.pages', ['edit' => $page->id])->with('success', 'Page settings and content updated.');
    }

    public function action(Request $request, ContentPage $page, string $action)
    {
        abort_unless(in_array($action, ['duplicate', 'publish', 'unpublish', 'archive', 'trash', 'schedule'], true), 404);
        DB::transaction(function () use ($request, $page, $action) {
            if ($action === 'duplicate') {
                $copy = $page->replicate();
                $copy->uuid = Str::uuid();
                $copy->title .= ' Copy';
                $copy->slug .= '-' . Str::lower(Str::random(6));
                $copy->status = 'draft';
                $copy->scheduled_for = null;
                $copy->published_at = null;
                $copy->archived_at = null;
                $copy->save();
                foreach ($page->sections as $section) {
                    $copy->sections()->create($section->only(['type', 'label', 'sort_order', 'settings', 'visible']));
                }
                $this->snapshot($copy, 'Duplicated');
                return;
            }
            $this->snapshot($page, Str::headline($action));
            if ($action === 'trash') {
                $page->delete();
                return;
            }
            $status = ['publish' => 'published', 'unpublish' => 'unpublished', 'archive' => 'archived', 'schedule' => 'scheduled'][$action];
            $page->update([
                'status' => $status,
                'scheduled_for' => $status === 'scheduled' ? ($request->date('scheduled_for') ?: now()->addDay()) : null,
                'published_at' => $status === 'published' ? now() : null,
                'archived_at' => $status === 'archived' ? now() : null,
            ]);
        });

        return back()->with('success', 'Page action completed.');
    }

    public function restore(int $page)
    {
        $record = ContentPage::onlyTrashed()->with('sections')->findOrFail($page);
        $record->restore();
        $this->snapshot($record, 'Restored');
        return back()->with('success', 'Page restored from trash.');
    }

    public function restoreRevision(ContentPage $page, PageRevision $revision)
    {
        abort_unless((int) $revision->content_page_id === (int) $page->id, 404);
        $snapshot = (array) $revision->snapshot;
        $fields = collect($snapshot)->only(['title', 'slug', 'intro', 'body', 'status', 'meta', 'locale', 'template', 'navigation_visible', 'scheduled_for', 'published_at', 'archived_at'])->all();
        DB::transaction(function () use ($page, $snapshot, $fields, $revision) {
            $this->snapshot($page, 'Before revision restore');
            $page->update($fields);
            $page->sections()->delete();
            foreach ((array) ($snapshot['sections'] ?? []) as $section) {
                $page->sections()->create(collect($section)->only(['type', 'label', 'sort_order', 'settings', 'visible'])->all());
            }
            $this->snapshot($page, 'Revision v' . $revision->version . ' restored');
        });
        return back()->with('success', 'Revision restored and saved as the current page version.');
    }

    public function preview(ContentPage $page)
    {
        $page->load('sections');
        return view('admin.pages.preview', compact('page'));
    }

    public function destroy(int $page)
    {
        ContentPage::onlyTrashed()->findOrFail($page)->forceDelete();
        return back()->with('success', 'Page permanently deleted.');
    }
}
