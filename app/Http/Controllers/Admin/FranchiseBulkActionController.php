<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Models\FranchiseApplication;
use App\Models\FranchiseStore;
use App\Services\AuditTrail;
use App\Services\FranchiseStoreLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FranchiseBulkActionController extends Controller
{
    public function __invoke(Request $request, string $section): RedirectResponse
    {
        abort_unless(in_array($section, FranchiseManagementController::SECTIONS, true), 404);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', Rule::in(['close', 'terminate', 'trash'])],
        ]);
        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $action = $data['action'];

        $expected = match ($section) {
            'franchise-applications' => 'close',
            'franchise-retail-stores' => 'terminate',
            'franchise-dashboard', 'franchise-territories', 'store-setup', 'franchise-reports', 'data-management' => null,
            default => 'trash',
        };
        abort_unless($expected !== null, 404);
        if ($action !== $expected) {
            throw ValidationException::withMessages(['action' => 'That bulk action is not valid for this franchise section.']);
        }

        DB::transaction(function () use ($section, $ids, $action): void {
            if ($section === 'franchise-applications') {
                $applications = FranchiseApplication::query()->whereIn('id', $ids)->lockForUpdate()->get();
                abort_unless($applications->count() === count($ids), 422, 'One or more applications are unavailable.');
                if ($applications->contains(fn (FranchiseApplication $application) => (string) $application->status === 'converted')) {
                    throw ValidationException::withMessages([
                        'ids' => 'Converted franchise applications are retained as business history and cannot be closed in bulk.',
                    ]);
                }
                foreach ($applications as $application) {
                    $before = $application->toArray();
                    $application->update([
                        'status' => 'rejected',
                        'data' => array_merge($application->data ?? [], [
                            'closed_from_admin' => true,
                            'closed_at' => now()->toIso8601String(),
                            'bulk_action' => true,
                        ]),
                    ]);
                    AuditTrail::record('franchise-applications.bulk_close', $application, $before, $application->fresh()->toArray());
                }
                return;
            }

            if ($section === 'franchise-retail-stores') {
                $stores = FranchiseStore::query()->whereIn('id', $ids)->lockForUpdate()->get();
                abort_unless($stores->count() === count($ids), 422, 'One or more stores are unavailable.');
                foreach ($stores as $store) {
                    if ((string) $store->status === 'terminated') {
                        continue;
                    }
                    app(FranchiseStoreLifecycleService::class)->act($store, [
                        'action' => 'terminate',
                        'idempotency_key' => 'admin-bulk-terminate-'.$store->uuid.'-'.($store->version ?: 1),
                        'expected_version' => (int) ($store->version ?: 1),
                        'reason' => 'Closed from a franchise management bulk action.',
                    ]);
                }
                return;
            }

            $records = AdminRecord::query()->where('module', $section)->whereIn('id', $ids)->lockForUpdate()->get();
            abort_unless($records->count() === count($ids), 422, 'One or more selected records are unavailable.');
            foreach ($records as $record) {
                $before = $record->toArray();
                $record->delete();
                AuditTrail::record($section.'.bulk_trashed', $record, $before, null);
            }
        });

        return back()->with('success', match ($action) {
            'close' => 'Selected franchise applications closed and retained in history.',
            'terminate' => 'Selected franchise stores terminated and retained in history.',
            default => 'Selected records moved to recoverable trash.',
        });
    }
}
