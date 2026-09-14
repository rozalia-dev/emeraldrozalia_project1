<?php

namespace App\Services;

use App\Events\FranchiseStoreLifecycleChanged;
use App\Models\{FranchiseStore, FranchiseStoreAction};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FranchiseStoreLifecycleService
{
    public const ACTIONS = [
        'activate',
        'suspend',
        'resume',
        'terminate',
    ];

    private const TRANSITIONS = [
        'activate' => [
            'pending' => 'active',
            'onboarding' => 'active',
            'inactive' => 'active',
        ],
        'suspend' => [
            'active' => 'suspended',
            'open' => 'suspended',
        ],
        'resume' => [
            'suspended' => 'active',
        ],
        'terminate' => [
            'pending' => 'terminated',
            'onboarding' => 'terminated',
            'active' => 'terminated',
            'open' => 'terminated',
            'inactive' => 'terminated',
            'suspended' => 'terminated',
        ],
    ];

    public function act(FranchiseStore $store, array $changes): FranchiseStore
    {
        $action = strtolower(trim((string) ($changes['action'] ?? '')));
        if (! in_array($action, self::ACTIONS, true)) {
            throw ValidationException::withMessages([
                'action' => 'That franchise store lifecycle action is not supported.',
            ]);
        }

        $idempotencyKey = trim((string) ($changes['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'An idempotency key is required for store lifecycle actions.',
            ]);
        }

        $reason = trim((string) ($changes['reason'] ?? ''));
        if (in_array($action, ['suspend', 'terminate'], true) && $reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when suspending or terminating a store.',
            ]);
        }

        $requestHash = hash('sha256', json_encode([
            'action' => $action,
            'reason' => $reason,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use ($store, $changes, $action, $idempotencyKey, $reason, $requestHash): FranchiseStore {
            $locked = FranchiseStore::query()
                ->whereKey($store->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existing = FranchiseStoreAction::withoutGlobalScopes()
                ->where('franchise_store_id', $locked->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                    abort(409, 'This idempotency key was already used for another store lifecycle action.');
                }

                return $locked->fresh();
            }

            $expectedVersion = $changes['expected_version'] ?? null;
            if ($expectedVersion !== null && (int) ($locked->version ?: 1) !== (int) $expectedVersion) {
                abort(409, 'This franchise store changed while you were editing it. Refresh and try again.');
            }

            $currentStatus = strtolower((string) $locked->status);
            $nextStatus = self::TRANSITIONS[$action][$currentStatus] ?? null;
            if ($nextStatus === null) {
                throw ValidationException::withMessages([
                    'action' => 'That franchise store cannot take this action from its current status.',
                ]);
            }

            $correlationId = '';
            if (app()->bound('request')) {
                $correlationId = trim((string) request()->attributes->get('correlation_id', ''));
            }
            $correlationId = $correlationId !== '' ? $correlationId : (string) Str::uuid();
            $before = $locked->toArray();
            $nextVersion = (int) ($locked->version ?: 1) + 1;

            $locked->update([
                'status' => $nextStatus,
                'version' => $nextVersion,
                'status_changed_at' => now(),
                'status_changed_by' => auth()->id(),
                'opened_at' => $nextStatus === 'active' && ! $locked->opened_at ? today() : $locked->opened_at,
            ]);

            $actionRecord = FranchiseStoreAction::create([
                'company_id' => $locked->company_id,
                'franchise_store_id' => $locked->getKey(),
                'performed_by' => auth()->id(),
                'action' => $action,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'expected_version' => $changes['expected_version'] ?? null,
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'reason' => $reason !== '' ? $reason : null,
                'metadata' => [
                    'store_uuid' => (string) $locked->uuid,
                    'correlation_id' => $correlationId,
                    'expected_version' => $changes['expected_version'] ?? null,
                ],
            ]);

            $after = $locked->fresh()->toArray();
            $after['_lifecycle'] = [
                'action' => $action,
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'version' => $nextVersion,
                'correlation_id' => $correlationId,
            ];
            AuditTrail::record('franchise.store.status_changed', $locked, $before, $after);
            AuditTrail::record('franchise.store.lifecycle_action', $actionRecord, null, $actionRecord->toArray());

            FranchiseStoreLifecycleChanged::dispatch($locked->fresh(), $actionRecord->fresh());

            return $locked->fresh();
        });
    }
}
