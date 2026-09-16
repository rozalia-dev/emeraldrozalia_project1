<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GenericResourceBulkController extends Controller
{
    public function __invoke(Request $request, string $module): RedirectResponse
    {
        $resourceController = app(ResourceController::class);
        abort_unless(in_array($module, $resourceController->modules, true), 404);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', Rule::in(['archive', 'trash', 'restore', 'permanent_delete'])],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $action = $data['action'];

        DB::transaction(function () use ($ids, $module, $action): void {
            $query = in_array($action, ['restore', 'permanent_delete'], true)
                ? AdminRecord::onlyTrashed()
                : AdminRecord::query();

            $records = $query
                ->where('module', $module)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            abort_unless(
                $records->count() === count($ids),
                422,
                'One or more selected records are unavailable in this module or company.'
            );

            foreach ($records as $record) {
                match ($action) {
                    'archive' => $this->archive($module, $record),
                    'trash' => $this->trash($module, $record),
                    'restore' => $this->restore($module, $record),
                    'permanent_delete' => $this->permanentDelete($module, $record),
                };
            }
        });

        $message = match ($action) {
            'archive' => 'Selected records archived.',
            'trash' => 'Selected records moved to trash.',
            'restore' => 'Selected records restored.',
            'permanent_delete' => 'Selected records permanently deleted.',
        };

        return back()->with('success', $message);
    }

    private function archive(string $module, AdminRecord $record): void
    {
        Gate::authorize('archive', $record);
        if ($record->status === 'archived') {
            return;
        }

        $before = $record->toArray();
        $record->update(['status' => 'archived']);
        AuditTrail::record($module.'.bulk_archived', $record, $before, $record->fresh()->toArray());
    }

    private function trash(string $module, AdminRecord $record): void
    {
        Gate::authorize('trash', $record);
        $before = $record->toArray();
        $record->delete();
        $after = $record->toArray();
        $after['deleted_at'] = $record->deleted_at?->toISOString();
        AuditTrail::record($module.'.bulk_trashed', $record, $before, $after);
    }

    private function restore(string $module, AdminRecord $record): void
    {
        Gate::authorize('restore', $record);
        $before = $record->toArray();
        $record->restore();
        AuditTrail::record($module.'.bulk_restored', $record, $before, $record->fresh()->toArray());
    }

    private function permanentDelete(string $module, AdminRecord $record): void
    {
        Gate::authorize('permanentlyDelete', $record);
        abort_unless($record->trashed(), 422, 'Only records already in trash can be permanently deleted.');

        $before = $record->toArray();
        $record->forceDelete();
        AuditTrail::record($module.'.bulk_permanently_deleted', $record, $before, null);
    }
}
