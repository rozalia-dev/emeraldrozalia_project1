<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PageBulkActionController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', Rule::in(['publish', 'unpublish', 'archive', 'trash', 'restore', 'permanent_delete'])],
        ]);
        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $action = $data['action'];

        DB::transaction(function () use ($ids, $action): void {
            $query = ContentPage::query();
            if (in_array($action, ['restore', 'permanent_delete'], true)) {
                $query->onlyTrashed();
            }
            $pages = $query->whereIn('id', $ids)->lockForUpdate()->get();
            abort_unless($pages->count() === count($ids), 422, 'One or more selected pages are unavailable in this view.');

            if (in_array($action, ['archive', 'trash', 'permanent_delete'], true)
                && $pages->contains(fn (ContentPage $page): bool => $page->isHomepage())) {
                throw ValidationException::withMessages([
                    'ids' => 'The reserved homepage cannot be archived, trashed or permanently deleted.',
                ]);
            }

            foreach ($pages as $page) {
                $before = $page->toArray();

                if ($action === 'permanent_delete') {
                    AuditTrail::record('pages.bulk_permanent_delete', $page, $before, null);
                    $page->forceDelete();
                    continue;
                }

                if ($action === 'restore') {
                    $page->restore();
                    $this->snapshot($page, 'Bulk restore');
                    AuditTrail::record('pages.bulk_restore', $page, $before, $page->fresh()->toArray());
                    continue;
                }

                $this->snapshot($page, 'Before bulk '.Str::headline($action));
                if ($action === 'trash') {
                    $page->delete();
                    AuditTrail::record('pages.bulk_trash', $page, $before, null);
                    continue;
                }

                $status = match ($action) {
                    'publish' => 'published',
                    'unpublish' => 'unpublished',
                    'archive' => 'archived',
                };
                $page->update([
                    'status' => $status,
                    'scheduled_for' => null,
                    'published_at' => $status === 'published' ? now() : null,
                    'archived_at' => $status === 'archived' ? now() : null,
                ]);
                AuditTrail::record('pages.bulk_'.$action, $page, $before, $page->fresh()->toArray());
            }
        });

        return back()->with('success', 'Selected page lifecycle action completed.');
    }

    private function snapshot(ContentPage $page, string $reason): void
    {
        $page->load('sections');
        $snapshot = $page->toArray();
        $snapshot['sections'] = $page->sections->map(fn ($section) => $section->only([
            'type', 'label', 'sort_order', 'region', 'locale', 'media_uuid', 'focal_point', 'devices',
            'variant', 'animation', 'analytics_key', 'validation_errors', 'settings', 'visible',
        ]))->values()->all();

        $page->revisions()->create([
            'user_id' => auth()->id(),
            'version' => ((int) $page->revisions()->max('version')) + 1,
            'snapshot' => $snapshot,
            'reason' => $reason,
        ]);
    }
}
