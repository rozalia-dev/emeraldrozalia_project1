<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Models\FranchiseApplication;
use App\Models\FranchiseStore;
use App\Services\AuditTrail;
use App\Services\FranchiseStoreLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class FranchiseSafeDestroyController extends Controller
{
    public function __invoke(string $section, int $id): RedirectResponse
    {
        if ($section === 'franchise-applications') {
            $application = FranchiseApplication::query()->findOrFail($id);
            if ((string) $application->status === 'converted') {
                throw ValidationException::withMessages([
                    'application' => 'A converted franchise application is retained as business history and cannot be deleted.',
                ]);
            }

            $before = $application->toArray();
            $application->update([
                'status' => 'rejected',
                'data' => array_merge($application->data ?? [], [
                    'closed_from_admin' => true,
                    'closed_at' => now()->toIso8601String(),
                ]),
            ]);
            AuditTrail::record('franchise-applications.closed', $application, $before, $application->fresh()->toArray());

            return back()->with('success', 'Franchise application closed and retained in history.');
        }

        if ($section === 'franchise-retail-stores') {
            $store = FranchiseStore::query()->findOrFail($id);
            if ((string) $store->status === 'terminated') {
                return back()->with('success', 'Franchise store is already terminated and retained in history.');
            }

            app(FranchiseStoreLifecycleService::class)->act($store, [
                'action' => 'terminate',
                'idempotency_key' => 'admin-remove-'.$store->uuid.'-'.($store->version ?: 1),
                'expected_version' => (int) ($store->version ?: 1),
                'reason' => 'Closed from the franchise management remove action.',
            ]);

            return back()->with('success', 'Franchise store terminated and retained in history.');
        }

        $record = AdminRecord::query()->where('module', $section)->findOrFail($id);
        $before = $record->toArray();
        $record->delete();
        AuditTrail::record($section.'.trashed', $record, $before, null);

        return back()->with('success', 'Record moved to recoverable trash.');
    }
}
