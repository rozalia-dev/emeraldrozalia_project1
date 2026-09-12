<?php

namespace App\Services;

use App\Events\ApprovalRequestChanged;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApprovalRequestService
{
    public const ACTIONS = ['approve', 'reject', 'escalate', 'reopen', 'cancel'];

    public function create(array $attributes, ?string $idempotencyKey = null): Approval
    {
        $payload = $this->normalize($attributes);
        $requestHash = $this->requestHash($payload);
        $this->assertIdempotencyKey($idempotencyKey);
        $approval = null;
        $replayed = false;

        try {
            DB::transaction(function () use (&$approval, &$replayed, $payload, $idempotencyKey, $requestHash): void {
                if ($idempotencyKey) {
                    $existing = Approval::withTrashed()
                        ->withoutGlobalScopes()
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        $this->assertTenant($existing);
                        $this->assertIdempotentReplay($existing, $requestHash);
                        abort_unless(! $existing->trashed(), 409, 'The Idempotency-Key belongs to a deleted approval request.');
                        $approval = $existing;
                        $replayed = true;

                        return;
                    }
                }

                $approval = new Approval($this->approvalAttributes($payload));
                $approval->uuid = (string) Str::uuid();
                $approval->reference = filled($payload['reference'] ?? null)
                    ? $payload['reference']
                    : $this->referenceFor($approval->uuid);
                $approval->idempotency_key = $idempotencyKey;
                $approval->request_hash = $requestHash;
                $approval->correlation_id = $this->correlationId();
                $approval->save();

                AuditTrail::record(
                    'communication.approval.created',
                    $approval,
                    null,
                    $this->auditState($approval),
                );
            });
        } catch (QueryException $exception) {
            if (! $idempotencyKey) {
                throw $exception;
            }

            $existing = Approval::withTrashed()
                ->withoutGlobalScopes()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if (! $existing) {
                throw $exception;
            }

            $this->assertTenant($existing);
            $this->assertIdempotentReplay($existing, $requestHash);
            abort_unless(! $existing->trashed(), 409, 'The Idempotency-Key belongs to a deleted approval request.');
            $approval = $existing;
            $replayed = true;
        }

        if (! $replayed && $approval) {
            $this->dispatchChanged($approval, 'created');
        }

        return $approval;
    }

    public function update(Approval $approval, array $attributes): Approval
    {
        $payload = $this->normalize($attributes, false);
        $expectedVersion = $payload['expected_version'] ?? null;
        unset($payload['expected_version']);
        $updated = null;

        DB::transaction(function () use (&$updated, $approval, $payload, $expectedVersion): void {
            $locked = Approval::query()
                ->withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->whereKey($approval->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertTenant($locked);

            if ($expectedVersion !== null && (int) $locked->version !== (int) $expectedVersion) {
                abort(409, 'The approval request changed before this update was saved. Refresh and try again.');
            }

            $before = $this->auditState($locked);
            $locked->fill($this->approvalAttributes($payload, false));
            if (array_key_exists('status', $payload)) {
                $this->applyStatusMetadata($locked, (string) $payload['status']);
            }
            $locked->version = (int) $locked->version + 1;
            $locked->save();

            AuditTrail::record(
                'communication.approval.updated',
                $locked,
                $before,
                $this->auditState($locked),
            );
            $updated = $locked;
        });

        $this->dispatchChanged($updated, 'updated');

        return $updated;
    }

    public function decide(Approval $approval, string $action, array $attributes = []): Approval
    {
        abort_unless(in_array($action, self::ACTIONS, true), 404);
        $expectedVersion = $attributes['expected_version'] ?? null;
        $decisionNote = array_key_exists('decision_note', $attributes) ? $attributes['decision_note'] : null;
        $decided = null;

        DB::transaction(function () use (&$decided, $approval, $action, $expectedVersion, $decisionNote): void {
            $locked = Approval::query()
                ->withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->whereKey($approval->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertTenant($locked);

            if ($expectedVersion !== null && (int) $locked->version !== (int) $expectedVersion) {
                abort(409, 'The approval request changed before this decision was saved. Refresh and try again.');
            }

            $allowed = match ($action) {
                'reopen' => in_array($locked->status, ['approved', 'rejected', 'cancelled'], true),
                default => in_array($locked->status, ['pending', 'in-progress', 'escalated'], true),
            };
            abort_unless($allowed, 409, 'This approval request cannot receive that decision in its current state.');

            $before = $this->auditState($locked);
            $locked->status = match ($action) {
                'approve' => 'approved',
                'reject' => 'rejected',
                'escalate' => 'escalated',
                'reopen' => 'pending',
                'cancel' => 'cancelled',
            };

            if (in_array($action, ['approve', 'reject'], true)) {
                $locked->decision_note = $decisionNote;
                $locked->decided_by = auth()->id();
                $locked->decided_at = now();
            } elseif ($action === 'reopen') {
                $locked->decision_note = null;
                $locked->decided_by = null;
                $locked->decided_at = null;
            } else {
                $locked->decision_note = $decisionNote;
            }

            $locked->version = (int) $locked->version + 1;
            $locked->save();

            AuditTrail::record(
                'communication.approval.'.$action,
                $locked,
                $before,
                $this->auditState($locked),
            );
            $decided = $locked;
        });

        $this->dispatchChanged($decided, $action);

        return $decided;
    }

    public function delete(Approval $approval): void
    {
        DB::transaction(function () use ($approval): void {
            $locked = Approval::withTrashed()
                ->withoutGlobalScopes()
                ->whereKey($approval->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertTenant($locked);
            abort_unless(! $locked->trashed(), 404);

            $before = $this->auditState($locked);
            $locked->delete();

            AuditTrail::record('communication.approval.deleted', $locked, $before, null);
        });

        $this->dispatchChanged($approval, 'deleted');
    }

    public function auditState(Approval $approval): array
    {
        return [
            'uuid' => $approval->uuid,
            'reference' => $approval->reference,
            'request_type' => $approval->request_type,
            'title' => $approval->title,
            'status' => $approval->status,
            'priority' => $approval->priority,
            'requested_by_uuid' => $this->userUuid($approval->requested_by),
            'approver_uuid' => $this->userUuid($approval->approver_id),
            'decided_by_uuid' => $this->userUuid($approval->decided_by),
            'entity' => $approval->entity,
            'entity_type' => $approval->entity_type,
            'entity_uuid' => $approval->entity_uuid,
            'source' => $approval->source,
            'due_at' => $approval->due_at?->toIso8601String(),
            'record_date' => $approval->record_date?->toDateString(),
            'version' => (int) $approval->version,
            'correlation_id' => $approval->correlation_id,
        ];
    }

    private function normalize(array $attributes, bool $creating = true): array
    {
        $payload = $attributes;

        if (! array_key_exists('request_type', $payload) && array_key_exists('type', $payload)) {
            $payload['request_type'] = $payload['type'];
        }
        if (! array_key_exists('requester_name', $payload) && array_key_exists('requested_by', $payload) && ! is_numeric($payload['requested_by'])) {
            $payload['requester_name'] = $payload['requested_by'];
        }
        if (! array_key_exists('approver_name', $payload) && array_key_exists('approver', $payload) && ! is_numeric($payload['approver'])) {
            $payload['approver_name'] = $payload['approver'];
        }

        if ($creating) {
            $payload['status'] ??= 'pending';
            $payload['priority'] ??= 'normal';
            $payload['source'] ??= 'Communication Center';
            $payload['record_date'] ??= now()->toDateString();
        }

        foreach (['title', 'reference', 'request_type', 'description', 'requester_name', 'approver_name', 'entity', 'entity_type', 'source', 'priority', 'status'] as $key) {
            if (array_key_exists($key, $payload) && is_string($payload[$key])) {
                $payload[$key] = trim($payload[$key]);
            }
        }
        if (array_key_exists('status', $payload)) {
            $payload['status'] = Str::lower((string) $payload['status']);
        }
        if (array_key_exists('priority', $payload)) {
            $payload['priority'] = Str::lower((string) $payload['priority']);
        }
        if (! $creating && array_key_exists('reference', $payload) && $payload['reference'] === '') {
            unset($payload['reference']);
        }
        if (array_key_exists('metadata', $payload) && is_array($payload['metadata'])) {
            ksort($payload['metadata']);
        }

        abort_unless(! $creating || filled($payload['title'] ?? null), 422, 'An approval request title is required.');
        abort_unless(in_array($payload['status'] ?? 'pending', Approval::STATUSES, true), 422, 'The approval request status is invalid.');
        abort_unless(in_array($payload['priority'] ?? 'normal', ['low', 'normal', 'medium', 'high', 'urgent'], true), 422, 'The approval request priority is invalid.');

        return $payload;
    }

    private function approvalAttributes(array $payload, bool $creating = true): array
    {
        $attributes = [];
        foreach ([
            'title', 'description', 'request_type', 'requester_name', 'approver_name', 'reference', 'entity',
            'entity_type', 'entity_uuid', 'source', 'priority', 'due_at', 'record_date', 'metadata', 'status',
            'decision_note',
        ] as $key) {
            if (array_key_exists($key, $payload)) {
                $attributes[$key] = $payload[$key];
            }
        }

        if (array_key_exists('requested_by_id', $payload)) {
            $attributes['requested_by'] = $payload['requested_by_id'];
        } elseif ($creating) {
            $attributes['requested_by'] = auth()->id();
        }
        if (array_key_exists('approver_id', $payload)) {
            $attributes['approver_id'] = $payload['approver_id'];
        }
        if (session()->has('company_id')) {
            $attributes['company_id'] = (int) session('company_id');
        }
        if ($creating) {
            $attributes['status'] ??= 'pending';
            $attributes['priority'] ??= 'normal';
            $attributes['source'] ??= 'Communication Center';
            $attributes['record_date'] ??= now()->toDateString();
            $attributes['metadata'] ??= [];
        }

        return $attributes;
    }

    private function applyStatusMetadata(Approval $approval, string $status): void
    {
        if (in_array($status, ['approved', 'rejected'], true)) {
            $approval->decided_by = auth()->id();
            $approval->decided_at = now();
        } elseif (! in_array($status, ['approved', 'rejected'], true)) {
            $approval->decided_by = null;
            $approval->decided_at = null;
        }
    }

    private function dispatchChanged(?Approval $approval, string $action): void
    {
        if (! $approval) {
            return;
        }

        event(new ApprovalRequestChanged(
            $approval,
            $action,
            (string) ($approval->correlation_id ?: $this->correlationId()),
        ));
    }

    private function assertIdempotencyKey(?string $idempotencyKey): void
    {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return;
        }

        abort_unless(
            preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $idempotencyKey) === 1,
            422,
            'Idempotency-Key must contain only letters, numbers, dots, underscores, colons or hyphens.',
        );
    }

    private function assertIdempotentReplay(Approval $approval, string $requestHash): void
    {
        if (! hash_equals((string) $approval->request_hash, $requestHash)) {
            abort(409, 'The Idempotency-Key was already used for a different approval request.');
        }
    }

    private function requestHash(array $payload): string
    {
        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function referenceFor(string $uuid): string
    {
        return 'APR-'.Str::upper(Str::substr(str_replace('-', '', $uuid), 0, 10));
    }

    private function correlationId(): string
    {
        $candidate = app()->bound('request') ? request()->attributes->get('correlation_id') : null;

        return is_string($candidate) && Str::isUuid($candidate) ? $candidate : (string) Str::uuid();
    }

    private function assertTenant(Approval $approval): void
    {
        $companyId = session('company_id');
        if ($companyId && (int) $approval->company_id !== (int) $companyId) {
            abort(404);
        }
    }

    private function userUuid(?int $userId): ?string
    {
        return $userId ? User::query()->whereKey($userId)->value('public_uuid') : null;
    }
}
