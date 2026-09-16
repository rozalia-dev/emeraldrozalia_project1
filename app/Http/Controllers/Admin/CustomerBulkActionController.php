<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerBulkActionController extends Controller
{
    public function customers(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', Rule::in(['active', 'inactive', 'blocked', 'restricted', 'delete'])],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $action = $data['action'];

        DB::transaction(function () use ($ids, $action): void {
            $customers = User::query()
                ->where('is_admin', false)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            abort_unless($customers->count() === count($ids), 422, 'One or more selected customers are unavailable.');

            foreach ($customers as $customer) {
                $before = $customer->load('customerProfile')->toArray();

                if ($action === 'delete') {
                    AuditTrail::record('customer.bulk_deleted', $customer, $before, null);
                    $customer->delete();
                    continue;
                }

                $profile = $customer->customerProfile()->firstOrCreate([], [
                    'uuid' => (string) Str::uuid(),
                    'account_status' => 'active',
                ]);
                $profile->update(['account_status' => $action]);
                AuditTrail::record('customer.bulk_status_updated', $customer, $before, $customer->fresh()->load('customerProfile')->toArray());
            }
        });

        return back()->with('success', $action === 'delete' ? 'Selected customers deleted.' : 'Selected customer statuses updated.');
    }

    public function groups(Request $request): RedirectResponse
    {
        return $this->toggleOrDelete($request, CustomerGroup::class, 'customer-group');
    }

    public function segments(Request $request): RedirectResponse
    {
        return $this->toggleOrDelete($request, CustomerSegment::class, 'customer-segment');
    }

    private function toggleOrDelete(Request $request, string $model, string $auditPrefix): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $action = $data['action'];

        DB::transaction(function () use ($ids, $action, $model, $auditPrefix): void {
            $records = $model::query()->whereIn('id', $ids)->lockForUpdate()->get();
            abort_unless($records->count() === count($ids), 422, 'One or more selected records are unavailable.');

            foreach ($records as $record) {
                $before = $record->toArray();
                if ($action === 'delete') {
                    AuditTrail::record($auditPrefix.'.bulk_deleted', $record, $before, null);
                    $record->delete();
                    continue;
                }

                $record->update(['is_active' => $action === 'activate']);
                AuditTrail::record($auditPrefix.'.bulk_status_updated', $record, $before, $record->fresh()->toArray());
            }
        });

        return back()->with('success', $action === 'delete' ? 'Selected records deleted.' : 'Selected records updated.');
    }
}
