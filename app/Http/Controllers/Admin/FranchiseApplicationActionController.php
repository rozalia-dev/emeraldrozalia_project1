<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FranchiseApplicationActionRequest;
use App\Models\Conversation;
use App\Models\FranchiseApplication;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

final class FranchiseApplicationActionController extends Controller
{
    public function __invoke(
        FranchiseApplicationActionRequest $request,
        FranchiseApplication $application,
        string $action,
    ) {
        $transitions = [
            'start-review' => ['from' => ['new'], 'to' => 'under-review'],
            'approve' => ['from' => ['under-review'], 'to' => 'approved'],
            'reject' => ['from' => ['new', 'under-review'], 'to' => 'rejected'],
            'start-onboarding' => ['from' => ['approved'], 'to' => 'onboarding'],
            // "convert" is retained as the legacy route/action key used by the
            // existing cPanel UI. It now means activate the approved franchise
            // relationship; it no longer converts an application into an Order.
            'convert' => ['from' => ['onboarding', 'converted'], 'to' => 'converted'],
        ];

        abort_unless(isset($transitions[$action]), 404);
        $validated = $request->validated();
        $activationKey = $action === 'convert' ? (string) ($validated['idempotency_key'] ?? '') : null;

        return DB::transaction(function () use ($application, $action, $transitions, $activationKey) {
            $locked = FranchiseApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $current = $this->normaliseStatus($locked->status);
            $transition = $transitions[$action];

            abort_unless(
                in_array($current, $transition['from'], true),
                422,
                'This application cannot take that action from its current state.',
            );

            $data = is_array($locked->data) ? $locked->data : [];
            if ($action === 'convert' && $current === 'converted') {
                $storedKey = (string) ($data['activation_key'] ?? '');
                if ($storedKey !== '' && ! hash_equals($storedKey, (string) $activationKey)) {
                    abort(409, 'This franchise application was already activated with another idempotency key.');
                }

                return back()->with('success', 'This franchise application is already an active partner. No sales order was created.');
            }

            $before = $locked->toArray();
            if ($action === 'convert') {
                $data = array_merge($data, [
                    'active_partner_at' => now()->toIso8601String(),
                    'activation_source' => 'franchise_application_lifecycle',
                    'activation_key' => $activationKey,
                ]);
            }

            $locked->update([
                'status' => $transition['to'],
                'data' => $data,
            ]);

            $conversation = Conversation::withoutGlobalScopes()
                ->where('franchise_application_id', $locked->getKey())
                ->lockForUpdate()
                ->first();
            if ($conversation) {
                $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
                $metadata['franchise_application_uuid'] = (string) $locked->uuid;
                $metadata['franchise_status'] = (string) $transition['to'];
                $conversation->update([
                    'status' => $conversation->status === 'closed' ? 'closed' : 'open',
                    'metadata' => $metadata,
                ]);
            }

            AuditTrail::record(
                $action === 'convert' ? 'franchise.application.activated' : 'franchise.application.'.$action,
                $locked,
                $before,
                $locked->fresh()->toArray(),
            );

            if ($action === 'convert') {
                return back()->with('success', 'Franchise application activated as a partner. Continue with territory, agreement, training and Store Setup. Franchise Orders remain a separate supply-order workflow.');
            }

            $labels = [
                'under-review' => 'Under Review',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                'onboarding' => 'Onboarding',
            ];

            return back()->with('success', 'Application moved to '.($labels[$transition['to']] ?? str($transition['to'])->headline()).'.');
        });
    }

    private function normaliseStatus(?string $status): string
    {
        return match ((string) $status) {
            'pending', 'under_review', 'review' => 'under-review',
            'on_boarding' => 'onboarding',
            default => (string) $status,
        };
    }
}
